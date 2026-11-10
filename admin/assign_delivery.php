<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ml_service.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

$errors = [];


/*
|--------------------------------------------------------------------------
| GET ORDER ID
|--------------------------------------------------------------------------
*/

$orderId = (int) (
    $_GET['order_id']
    ?? $_POST['order_id']
    ?? 0
);

if ($orderId <= 0) {
    header(
        "Location: orders.php?error=invalid_order"
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/


/**
 * Get current active load of a truck.
 *
 * Assigned, Accepted and In Transit orders occupy capacity.
 */
function getTruckLoad(
    PDO $db,
    int $truckId,
    int $excludeOrderId = 0
): float {

    $sql = "
        SELECT COALESCE(
            SUM(total_weight_kg),
            0
        )
        FROM `order`
        WHERE truck_id = ?
        AND completion_status IN (
            'Assigned',
            'Accepted',
            'In Transit'
        )
    ";

    $params = [$truckId];

    if ($excludeOrderId > 0) {

        $sql .= "
            AND order_id <> ?
        ";

        $params[] = $excludeOrderId;
    }

    $stmt = $db->prepare($sql);

    $stmt->execute($params);

    return (float) $stmt->fetchColumn();
}


/**
 * Get truck capacity.
 */
function getTruckCapacity(
    PDO $db,
    int $truckId
): float {

    $stmt = $db->prepare("
        SELECT capacity
        FROM truck
        WHERE truck_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $truckId
    ]);

    return (float) $stmt->fetchColumn();
}


/**
 * Get remaining truck capacity.
 */
function getTruckRemainingCapacity(
    PDO $db,
    int $truckId,
    int $excludeOrderId = 0
): float {

    $capacity =
        getTruckCapacity(
            $db,
            $truckId
        );

    $load =
        getTruckLoad(
            $db,
            $truckId,
            $excludeOrderId
        );

    return max(
        0,
        $capacity - $load
    );
}


/**
 * Convert route estimated time into minutes.
 *
 * Supports values such as:
 *
 * 45
 * 60
 * 1 hour
 * 1.5 hours
 * 1 hr 30 min
 * 90 minutes
 */
function parseRouteTimeToMinutes(
    $value
): int {

    if ($value === null) {
        return 0;
    }

    $text = strtolower(
        trim((string) $value)
    );

    if ($text === '') {
        return 0;
    }


    /*
     * Pure number.
     */
    if (is_numeric($text)) {

        return max(
            0,
            (int) round(
                (float) $text
            )
        );
    }


    $hours = 0;
    $minutes = 0;


    /*
     * Hours.
     */
    if (
        preg_match(
            '/(\d+(?:\.\d+)?)\s*(?:hours?|hrs?|hr|h)/i',
            $text,
            $match
        )
    ) {

        $hours =
            (float) $match[1];
    }


    /*
     * Minutes.
     */
    if (
        preg_match(
            '/(\d+(?:\.\d+)?)\s*(?:minutes?|mins?|min|m)/i',
            $text,
            $match
        )
    ) {

        $minutes =
            (float) $match[1];
    }


    /*
     * If nothing matched, try extracting
     * the first number.
     */
    if (
        $hours === 0
        &&
        $minutes === 0
        &&
        preg_match(
            '/\d+(?:\.\d+)?/',
            $text,
            $match
        )
    ) {

        $minutes =
            (float) $match[0];
    }


    return max(
        0,
        (int) round(
            ($hours * 60) + $minutes
        )
    );
}


/**
 * Format minutes as a readable duration.
 */
function formatTat(
    ?int $minutes
): string {

    if ($minutes === null) {
        return '-';
    }

    $minutes =
        max(
            0,
            $minutes
        );

    $hours =
        intdiv(
            $minutes,
            60
        );

    $remaining =
        $minutes % 60;


    if ($hours > 0) {

        if ($remaining > 0) {

            return $hours
                . 'h '
                . $remaining
                . 'm';
        }

        return $hours . 'h';
    }


    return $minutes . ' min';
}




/**
 * Historical incident features for the selected driver.
 *
 * Only Reviewed/Resolved incidents are used. If the incident table is not
 * installed yet, the function gracefully returns zero rates so assignment
 * remains usable.
 */
function getDriverIncidentMlFeatures(
    PDO $db,
    int $driverId,
    int $totalDeliveries
): array {

    $features = [
        'driver_delay_rate' => 0.0,
        'driver_verified_violation_rate' => 0.0,

        'driver_delayed_deliveries' => 0,
        'driver_verified_violations' => 0,
        'driver_history_deliveries' => 0,

        'history_source' => 'none',
        'is_synthetic_history' => false,
    ];

    if ($driverId <= 0) {
        return $features;
    }

    try {

        /*
         * ====================================================
         * FIRST PRIORITY:
         * Actual operational history
         * ====================================================
         */

        $actualStmt = $db->prepare("
            SELECT

                COUNT(
                    DISTINCT CASE
                        WHEN estimated_delay_minutes > 0
                        THEN order_id
                    END
                ) AS delayed_deliveries,

                COUNT(
                    DISTINCT CASE
                        WHEN violation_status = 'Verified Violation'
                        THEN order_id
                    END
                ) AS verified_violations

            FROM delivery_incident

            WHERE driver_id = ?
        ");

        $actualStmt->execute([
            $driverId
        ]);

        $actual = $actualStmt->fetch(
            PDO::FETCH_ASSOC
        );

        $actualDelayed = intval(
            $actual['delayed_deliveries'] ?? 0
        );

        $actualViolations = intval(
            $actual['verified_violations'] ?? 0
        );


        /*
         * If actual incident history exists,
         * use ACTUAL history.
         */
        if (
            $actualDelayed > 0
            || $actualViolations > 0
        ) {

            $deliveryCount = max(
                1,
                $totalDeliveries
            );

            return [
                'driver_delay_rate' => min(
                    1.0,
                    $actualDelayed / $deliveryCount
                ),

                'driver_verified_violation_rate' => min(
                    1.0,
                    $actualViolations / $deliveryCount
                ),

                'driver_delayed_deliveries' =>
                    $actualDelayed,

                'driver_verified_violations' =>
                    $actualViolations,

                'driver_history_deliveries' =>
                    $deliveryCount,

                'history_source' => 'actual',

                'is_synthetic_history' => false,
            ];
        }


        /*
         * ====================================================
         * FALLBACK:
         * Synthetic historical profile
         * ====================================================
         */

        $syntheticStmt = $db->prepare("
            SELECT
                completed_deliveries,
                delayed_deliveries,
                verified_violations,
                is_synthetic

            FROM ml_driver_history

            WHERE driver_id = ?

            LIMIT 1
        ");

        $syntheticStmt->execute([
            $driverId
        ]);

        $history = $syntheticStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if ($history) {

            $completed = max(
                1,
                intval(
                    $history['completed_deliveries']
                )
            );

            $delayed = max(
                0,
                intval(
                    $history['delayed_deliveries']
                )
            );

            $violations = max(
                0,
                intval(
                    $history['verified_violations']
                )
            );


            /*
             * Prevent invalid synthetic counts.
             */
            $delayed = min(
                $delayed,
                $completed
            );

            $violations = min(
                $violations,
                $completed
            );


            return [
                'driver_delay_rate' => min(
                    1.0,
                    $delayed / $completed
                ),

                'driver_verified_violation_rate' => min(
                    1.0,
                    $violations / $completed
                ),

                'driver_delayed_deliveries' =>
                    $delayed,

                'driver_verified_violations' =>
                    $violations,

                'driver_history_deliveries' =>
                    $completed,

                'history_source' => 'synthetic',

                'is_synthetic_history' => true,
            ];
        }

    } catch (PDOException $e) {

        return $features;
    }


    return $features;
}


/**
 * Historical reviewed Vehicle Breakdown rate for a truck.
 */
function getTruckIncidentMlFeatures(
    PDO $db,
    int $truckId
): array {

    $features = [
        'truck_breakdown_rate' => 0.0,

        'truck_breakdown_deliveries' => 0,
        'truck_completed_deliveries' => 0,

        'truck_history_source' => 'none',
        'truck_is_synthetic_history' => false,
    ];


    if ($truckId <= 0) {
        return $features;
    }


    try {

        /*
         * ====================================================
         * FIRST PRIORITY:
         * Actual breakdown history
         * ====================================================
         */

        $actualBreakdownStmt = $db->prepare("
            SELECT
                COUNT(DISTINCT order_id)

            FROM delivery_incident

            WHERE truck_id = ?

              AND incident_type = 'Vehicle Breakdown'
        ");

        $actualBreakdownStmt->execute([
            $truckId
        ]);

        $actualBreakdowns = intval(
            $actualBreakdownStmt->fetchColumn()
        );


        if ($actualBreakdowns > 0) {

            $completedStmt = $db->prepare("
                SELECT
                    COUNT(*)

                FROM `order`

                WHERE truck_id = ?

                  AND completion_status = 'Completed'
            ");

            $completedStmt->execute([
                $truckId
            ]);

            $completed = max(
                1,
                intval(
                    $completedStmt->fetchColumn()
                )
            );


            return [
                'truck_breakdown_rate' => min(
                    1.0,
                    $actualBreakdowns / $completed
                ),

                'truck_breakdown_deliveries' =>
                    $actualBreakdowns,

                'truck_completed_deliveries' =>
                    $completed,

                'truck_history_source' =>
                    'actual',

                'truck_is_synthetic_history' =>
                    false,
            ];
        }


        /*
         * ====================================================
         * FALLBACK:
         * Synthetic truck history
         * ====================================================
         */

        $syntheticStmt = $db->prepare("
            SELECT
                completed_deliveries,
                breakdown_deliveries,
                is_synthetic

            FROM ml_truck_history

            WHERE truck_id = ?

            LIMIT 1
        ");

        $syntheticStmt->execute([
            $truckId
        ]);

        $history = $syntheticStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if ($history) {

            $completed = max(
                1,
                intval(
                    $history['completed_deliveries']
                )
            );

            $breakdowns = max(
                0,
                intval(
                    $history['breakdown_deliveries']
                )
            );

            $breakdowns = min(
                $breakdowns,
                $completed
            );


            return [
                'truck_breakdown_rate' => min(
                    1.0,
                    $breakdowns / $completed
                ),

                'truck_breakdown_deliveries' =>
                    $breakdowns,

                'truck_completed_deliveries' =>
                    $completed,

                'truck_history_source' =>
                    'synthetic',

                'truck_is_synthetic_history' =>
                    true,
            ];
        }

    } catch (PDOException $e) {

        return $features;
    }


    return $features;
}


/**
 * Synchronize truck.current_load and truck.status.
 *
 * Maintenance, Out of Service and Retired are never overwritten.
 */
function synchronizeTruck(
    PDO $db,
    int $truckId
): array {

    $truckStmt = $db->prepare("
        SELECT
            truck_id,
            capacity,
            status
        FROM truck
        WHERE truck_id = ?
        FOR UPDATE
    ");

    $truckStmt->execute([
        $truckId
    ]);

    $truck =
        $truckStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$truck) {

        throw new RuntimeException(
            'Truck not found.'
        );
    }

    $load =
        getTruckLoad(
            $db,
            $truckId
        );

    $capacity =
        (float) $truck['capacity'];


    $protectedStatuses = [
        'Maintenance',
        'Out of Service',
        'Retired'
    ];

    if (
        in_array(
            $truck['status'],
            $protectedStatuses,
            true
        )
    ) {

        $status = $truck['status'];

    } elseif ($load > 0) {

        $status =
            'In Use';

    } else {

        $status =
            'Available';
    }


    $updateStmt = $db->prepare("
        UPDATE truck
        SET
            current_load = ?,
            status = ?
        WHERE truck_id = ?
    ");

    $updateStmt->execute([
        $load,
        $status,
        $truckId
    ]);


    return [
        'capacity' =>
            $capacity,

        'current_load' =>
            $load,

        'remaining_capacity' =>
            max(
                0,
                $capacity - $load
            ),

        'status' =>
            $status
    ];
}


/*
|--------------------------------------------------------------------------
| FETCH ORDER
|--------------------------------------------------------------------------
*/

$orderStmt = $db->prepare("
    SELECT

        o.order_id,
        o.order_number,

        o.customer_id,
        o.driver_id,
        o.truck_id,
        o.route_id,

        o.item_description,

        o.subtotal,
        o.urgent_fee,
        o.total_amount,
        o.total_weight,
        o.total_weight_kg,

        o.order_date,
        o.expected_delivery_date,

        o.priority_level,
        o.completion_status,

        c.full_name AS customer_name,
        c.phone_number AS customer_phone,
        c.address AS customer_address,
        c.city AS customer_city,
        c.province AS customer_province,

        r.route_code,
        r.path_details,
        r.estimated_distance,
        r.estimated_time

    FROM `order` o

    LEFT JOIN customer c
        ON c.customer_id = o.customer_id

    LEFT JOIN route r
        ON r.route_id = o.route_id

    WHERE o.order_id = ?

    LIMIT 1
");

$orderStmt->execute([
    $orderId
]);

$order =
    $orderStmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$order) {

    header(
        "Location: orders.php?error=order_not_found"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK IF ORDER CAN BE ASSIGNED
|--------------------------------------------------------------------------
*/

$assignableStatuses = [
    'Approved',
    'Assigned',
    'Accepted',
    'In Transit'
];

$isViewOnly = in_array(
    $order['completion_status'],
    ['Assigned', 'Accepted', 'In Transit'],
    true
);

if (
    !in_array(
        $order['completion_status'],
        $assignableStatuses,
        true
    )
) {

    header(
        "Location: orders.php?error=not_assignable"
    );

    exit;
}



/*
|--------------------------------------------------------------------------
| CURRENT ASSIGNMENT (VIEW MODE)
|--------------------------------------------------------------------------
*/

$currentAssignment = null;

if ($isViewOnly) {
    $currentAssignmentStmt = $db->prepare("
        SELECT
            d.full_name AS driver_name,
            d.phone_number AS driver_phone,
            d.status AS driver_status,
            t.plate_number,
            t.model AS truck_model,
            t.capacity AS truck_capacity,
            t.current_load AS truck_current_load
        FROM `order` o
        LEFT JOIN driver d ON d.driver_id = o.driver_id
        LEFT JOIN truck t ON t.truck_id = o.truck_id
        WHERE o.order_id = ?
        LIMIT 1
    ");
    $currentAssignmentStmt->execute([$orderId]);
    $currentAssignment = $currentAssignmentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$deliveryStageTitle = 'Ready for Assignment';
$deliveryStageText = 'Select a driver and truck for this approved order.';

if ($order['completion_status'] === 'Assigned') {
    $deliveryStageTitle = 'Waiting for Driver';
    $deliveryStageText = 'The delivery has been assigned, but the driver has not accepted or started the delivery yet.';
} elseif ($order['completion_status'] === 'Accepted') {
    $deliveryStageTitle = 'Driver Accepted';
    $deliveryStageText = 'The driver has accepted the assignment and is waiting to start the delivery.';
} elseif ($order['completion_status'] === 'In Transit') {
    $deliveryStageTitle = 'Delivery In Transit';
    $deliveryStageText = 'The driver has started the delivery and the order is currently in transit.';
}

/*
|--------------------------------------------------------------------------
| ORDER / ROUTE FEATURES FOR ML
|--------------------------------------------------------------------------
*/

$orderWeight =
    (float) (
        $order['total_weight_kg']
        ?? $order['total_weight']
        ?? 0
    );


$routeDistance =
    (float) (
        $order['estimated_distance']
        ?? 0
    );


$routeTimeMinutes =
    parseRouteTimeToMinutes(
        $order['estimated_time']
        ?? null
    );


/*
|--------------------------------------------------------------------------
| LOCATION FOR ML LOG
|--------------------------------------------------------------------------
*/

$deliveryLocationParts = [];

if (
    !empty(
        $order['customer_address']
    )
) {

    $deliveryLocationParts[] =
        $order['customer_address'];
}

if (
    !empty(
        $order['customer_city']
    )
) {

    $deliveryLocationParts[] =
        $order['customer_city'];
}

if (
    !empty(
        $order['customer_province']
    )
) {

    $deliveryLocationParts[] =
        $order['customer_province'];
}

$deliveryLocation =
    implode(
        ', ',
        $deliveryLocationParts
    );


/*
|--------------------------------------------------------------------------
| ASSIGN DELIVERY
|--------------------------------------------------------------------------
*/

if (
    !$isViewOnly
    &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['assign_delivery'])
) {

    $driverId =
        (int) (
            $_POST['driver_id']
            ?? 0
        );

    $truckId =
        (int) (
            $_POST['truck_id']
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | ML INFORMATION SUBMITTED BY THE FORM
    |--------------------------------------------------------------------------
    |
    | These values are only used for logging.
    | The actual assignment is revalidated from the database.
    |
    */

    $mlPredictedTat =
        isset(
            $_POST['ml_predicted_tat_minutes']
        )
            ? (int)
            $_POST['ml_predicted_tat_minutes']
            : null;


    $mlMinimumTat =
        isset(
            $_POST['ml_minimum_tat_minutes']
        )
            ? (int)
            $_POST['ml_minimum_tat_minutes']
            : null;


    $mlMaximumTat =
        isset(
            $_POST['ml_maximum_tat_minutes']
        )
            ? (int)
            $_POST['ml_maximum_tat_minutes']
            : null;


    $mlRecommendation =
        isset(
            $_POST['ml_recommendation']
        )
            ? trim(
                $_POST['ml_recommendation']
            )
            : null;


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($driverId <= 0) {

        $errors[] =
            'Please select a driver.';
    }

    if ($truckId <= 0) {

        $errors[] =
            'Please select a truck.';
    }


    if (empty($errors)) {

        try {

            $db->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LOCK ORDER
            |--------------------------------------------------------------------------
            */

            $lockedOrderStmt =
                $db->prepare("
                    SELECT

                        order_id,
                        order_number,
                        driver_id,
                        truck_id,
                        total_weight_kg,
                        completion_status

                    FROM `order`

                    WHERE order_id = ?

                    FOR UPDATE
                ");

            $lockedOrderStmt->execute([
                $orderId
            ]);

            $lockedOrder =
                $lockedOrderStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$lockedOrder) {

                throw new RuntimeException(
                    'Order not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PREVIOUS ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $oldDriverId =
                !empty($lockedOrder['driver_id'])
                    ? (int) $lockedOrder['driver_id']
                    : 0;

            $oldTruckId =
                !empty($lockedOrder['truck_id'])
                    ? (int) $lockedOrder['truck_id']
                    : 0;


            /*
            |--------------------------------------------------------------------------
            | RE-CHECK ORDER STATUS
            |--------------------------------------------------------------------------
            */

            if (
                !in_array(
                    $lockedOrder[
                        'completion_status'
                    ],
                    [
                        'Approved',
                        'Assigned'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    'This order is no longer available for assignment.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | ORDER WEIGHT
            |--------------------------------------------------------------------------
            */

            $orderWeight =
                (float)
                $lockedOrder[
                    'total_weight_kg'
                ];


            /*
            |--------------------------------------------------------------------------
            | LOCK DRIVER
            |--------------------------------------------------------------------------
            */

            $driverStmt =
                $db->prepare("
                    SELECT

                        driver_id,
                        full_name,
                        phone_number,
                        status,
                        current_truck_id,
                        total_deliveries,
                        rating

                    FROM driver

                    WHERE driver_id = ?

                    FOR UPDATE
                ");

            $driverStmt->execute([
                $driverId
            ]);

            $driver =
                $driverStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$driver) {

                throw new RuntimeException(
                    'Selected driver does not exist.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DRIVER STATUS
            |--------------------------------------------------------------------------
            */

            if ($driver['status'] !== 'Available') {

                throw new RuntimeException(
                    "Driver {$driver['full_name']} is currently "
                    . strtolower($driver['status'])
                    . " and cannot be assigned. Only Available drivers can receive a new delivery."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DOUBLE-CHECK ACTIVE DELIVERY
            |--------------------------------------------------------------------------
            */

            $activeDeliveryStmt =
                $db->prepare("
                    SELECT COUNT(*)

                    FROM `order`

                    WHERE driver_id = ?

                    AND completion_status = 'In Transit'

                    AND order_id <> ?
                ");

            $activeDeliveryStmt->execute([
                $driverId,
                $orderId
            ]);

            $activeDeliveries =
                (int)
                $activeDeliveryStmt
                    ->fetchColumn();


            if ($activeDeliveries > 0) {

                throw new RuntimeException(
                    'This driver is currently delivering another order.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DRIVER'S CURRENT TRUCK
            |--------------------------------------------------------------------------
            */

            if (
                !empty($driver['current_truck_id'])
                &&
                (int) $driver['current_truck_id'] !== $truckId
            ) {

                $driverCurrentTruckId =
                    (int) $driver['current_truck_id'];

                /*
                 * Allow the same driver to move this already-assigned order
                 * to another truck only when the driver has no other active
                 * orders using the old truck.
                 */
                $canSwitchOwnTruck = false;

                if (
                    $oldDriverId === $driverId
                    &&
                    $oldTruckId === $driverCurrentTruckId
                ) {

                    $otherDriverOrdersStmt = $db->prepare("
                        SELECT COUNT(*)
                        FROM `order`
                        WHERE driver_id = ?
                          AND order_id <> ?
                          AND completion_status IN (
                              'Assigned',
                              'Accepted',
                              'In Transit'
                          )
                    ");

                    $otherDriverOrdersStmt->execute([
                        $driverId,
                        $orderId
                    ]);

                    $canSwitchOwnTruck =
                        (int) $otherDriverOrdersStmt->fetchColumn() === 0;
                }

                if (!$canSwitchOwnTruck) {
                    throw new RuntimeException(
                        'This driver is already assigned to another truck.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | LOCK TRUCK
            |--------------------------------------------------------------------------
            */

            $truckStmt =
                $db->prepare("
                    SELECT

                        truck_id,
                        plate_number,
                        model,
                        capacity,
                        status,
                        current_load

                    FROM truck

                    WHERE truck_id = ?

                    FOR UPDATE
                ");

            $truckStmt->execute([
                $truckId
            ]);

            $truck =
                $truckStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$truck) {

                throw new RuntimeException(
                    'Selected truck does not exist.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | TRUCK AVAILABILITY
            |--------------------------------------------------------------------------
            */

            $blockedTruckStatuses = [
                'Maintenance',
                'Out of Service',
                'Retired'
            ];

            if (
                in_array(
                    $truck['status'],
                    $blockedTruckStatuses,
                    true
                )
            ) {

                throw new RuntimeException(
                    "Truck {$truck['plate_number']} is currently "
                    . $truck['status']
                    . " and cannot receive a delivery."
                );
            }

            if (
                !in_array(
                    $truck['status'],
                    ['Available', 'In Use'],
                    true
                )
            ) {

                throw new RuntimeException(
                    "Truck {$truck['plate_number']} is not available for assignment."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK TRUCK OWNER
            |--------------------------------------------------------------------------
            */

            $truckDriverStmt =
                $db->prepare("
                    SELECT

                        driver_id,
                        full_name,
                        status

                    FROM driver

                    WHERE current_truck_id = ?

                    AND driver_id <> ?

                    AND status IN (
                        'Available',
                        'On Delivery'
                    )

                    LIMIT 1

                    FOR UPDATE
                ");

            $truckDriverStmt->execute([
                $truckId,
                $driverId
            ]);

            $truckDriver =
                $truckDriverStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if ($truckDriver) {

                throw new RuntimeException(
                    "Truck {$truck['plate_number']} is currently assigned to "
                    . $truckDriver['full_name']
                    . "."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CALCULATE CURRENT LOAD
            |--------------------------------------------------------------------------
            */

            $currentLoad =
                getTruckLoad(
                    $db,
                    $truckId,
                    $orderId
                );


            $capacity =
                (float)
                $truck['capacity'];


            $remainingCapacity =
                max(
                    0,
                    $capacity - $currentLoad
                );


            /*
            |--------------------------------------------------------------------------
            | CAPACITY CHECK
            |--------------------------------------------------------------------------
            */

            if (
                $orderWeight
                >
                $remainingCapacity
            ) {

                throw new RuntimeException(

                    "Insufficient truck capacity. "
                    . "Truck capacity: "
                    . number_format(
                        $capacity,
                        2
                    )
                    . " kg. "

                    . "Current load: "
                    . number_format(
                        $currentLoad,
                        2
                    )
                    . " kg. "

                    . "Remaining capacity: "
                    . number_format(
                        $remainingCapacity,
                        2
                    )
                    . " kg. "

                    . "Order weight: "
                    . number_format(
                        $orderWeight,
                        2
                    )
                    . " kg."

                );
            }


            /*
            |--------------------------------------------------------------------------
            | SERVER-SIDE ML VERIFICATION
            |--------------------------------------------------------------------------
            |
            | Hidden form values are not trusted as the authoritative prediction.
            | Recalculate the selected valid driver/truck combination on the server.
            | ML remains advisory, so assignment can continue if the service is down.
            |
            */

            $mlPredictedTat = null;
            $mlMinimumTat = null;
            $mlMaximumTat = null;

            if (
                $routeDistance > 0
                &&
                $routeTimeMinutes > 0
                &&
                $orderWeight > 0
            ) {

                $driverRating =
                    !empty($driver['rating'])
                        ? (float) $driver['rating']
                        : 4.2;

                $driverIncidentFeatures =
                    getDriverIncidentMlFeatures(
                        $db,
                        $driverId,
                        (int) ($driver['total_deliveries'] ?? 0)
                    );

                $truckIncidentFeatures =
                    getTruckIncidentMlFeatures(
                        $db,
                        $truckId
                    );

                $serverMlInput = [[
                    'driver_id' => $driverId,
                    'truck_id' => $truckId,
                    'weight_kg' => $orderWeight,
                    'priority_level' => $order['priority_level'],
                    'route_distance_km' => $routeDistance,
                    'estimated_route_time_minutes' => $routeTimeMinutes,
                    'driver_rating' => $driverRating,
                    'driver_total_deliveries' =>
                        (int) ($driver['total_deliveries'] ?? 0),
                    'driver_delay_rate' =>
                        (float) $driverIncidentFeatures['driver_delay_rate'],
                    'driver_verified_violation_rate' =>
                        (float) $driverIncidentFeatures['driver_verified_violation_rate'],
                    'truck_capacity_kg' => $capacity,
                    'truck_current_load_kg' => $currentLoad,
                    'truck_breakdown_rate' =>
                        (float) $truckIncidentFeatures['truck_breakdown_rate'],
                    'driver_name' => $driver['full_name'],
                    'driver_phone' => $driver['phone_number'],
                    'truck_plate' => $truck['plate_number'],
                    'truck_model' => $truck['model'],
                    'truck_remaining_capacity' => $remainingCapacity
                ]];

                try {
                    $serverMlResults =
                        predictDeliveryCombinations($serverMlInput);

                    if (
                        !empty($serverMlResults[0])
                        &&
                        isset($serverMlResults[0]['predicted_tat_minutes'])
                        &&
                        $serverMlResults[0]['predicted_tat_minutes'] !== null
                    ) {
                        $verifiedMlPrediction = $serverMlResults[0];
                        $mlPredictedTat =
                            (int) $verifiedMlPrediction['predicted_tat_minutes'];
                        $mlMinimumTat =
                            isset($verifiedMlPrediction['minimum_tat_minutes'])
                                ? (int) $verifiedMlPrediction['minimum_tat_minutes']
                                : null;
                        $mlMaximumTat =
                            isset($verifiedMlPrediction['maximum_tat_minutes'])
                                ? (int) $verifiedMlPrediction['maximum_tat_minutes']
                                : null;
                    }
                } catch (Throwable $mlException) {
                    error_log(
                        'Server-side ML verification failed: '
                        . $mlException->getMessage()
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE ORDER
            |--------------------------------------------------------------------------
            */

            $updateOrderStmt =
                $db->prepare("
                    UPDATE `order`

                    SET
                        driver_id = ?,
                        truck_id = ?,
                        completion_status = 'Assigned'

                    WHERE order_id = ?
                ");

            $updateOrderStmt->execute([
                $driverId,
                $truckId,
                $orderId
            ]);


            /*
            |--------------------------------------------------------------------------
            | CLEAN UP PREVIOUS ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            if (
                $oldDriverId > 0
                &&
                $oldDriverId !== $driverId
            ) {

                $oldDriverOrdersStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM `order`
                    WHERE driver_id = ?
                      AND order_id <> ?
                      AND completion_status IN (
                          'Assigned',
                          'Accepted',
                          'In Transit'
                      )
                ");

                $oldDriverOrdersStmt->execute([
                    $oldDriverId,
                    $orderId
                ]);

                $oldDriverActiveOrders =
                    (int) $oldDriverOrdersStmt->fetchColumn();

                if ($oldDriverActiveOrders === 0) {

                    $releaseOldDriverStmt = $db->prepare("
                        UPDATE driver
                        SET current_truck_id = NULL
                        WHERE driver_id = ?
                          AND status <> 'On Delivery'
                    ");

                    $releaseOldDriverStmt->execute([
                        $oldDriverId
                    ]);

                    $deactivateOldAssignmentStmt = $db->prepare("
                        UPDATE driver_truck_assignment
                        SET is_active = 0
                        WHERE driver_id = ?
                          AND is_active = 1
                    ");

                    $deactivateOldAssignmentStmt->execute([
                        $oldDriverId
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE DRIVER
            |--------------------------------------------------------------------------
            |
            | Driver remains Available until delivery actually starts.
            |
            */

            $updateDriverStmt =
                $db->prepare("
                    UPDATE driver

                    SET
                        current_truck_id = ?,
                        status = 'Available'

                    WHERE driver_id = ?
                ");

            $updateDriverStmt->execute([
                $truckId,
                $driverId
            ]);


            /*
            |--------------------------------------------------------------------------
            | UPDATE DRIVER-TRUCK ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $deactivateAssignmentStmt =
                $db->prepare("
                    UPDATE driver_truck_assignment

                    SET is_active = 0

                    WHERE driver_id = ?

                    AND is_active = 1
                ");

            $deactivateAssignmentStmt->execute([
                $driverId
            ]);


            /*
            |--------------------------------------------------------------------------
            | CREATE ACTIVE DRIVER-TRUCK ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $assignmentStmt =
                $db->prepare("
                    INSERT INTO driver_truck_assignment (
                        driver_id,
                        truck_id,
                        assigned_date,
                        is_active
                    )

                    VALUES (
                        ?,
                        ?,
                        CURDATE(),
                        1
                    )
                ");

            $assignmentStmt->execute([
                $driverId,
                $truckId
            ]);


            /*
            |--------------------------------------------------------------------------
            | SYNCHRONIZE NEW AND PREVIOUS TRUCKS
            |--------------------------------------------------------------------------
            */

            $newTruckState =
                synchronizeTruck(
                    $db,
                    $truckId
                );

            $newTruckLoad =
                (float) $newTruckState['current_load'];

            $newRemainingCapacity =
                (float) $newTruckState['remaining_capacity'];

            if (
                $oldTruckId > 0
                &&
                $oldTruckId !== $truckId
            ) {
                synchronizeTruck(
                    $db,
                    $oldTruckId
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CREATE DELIVERY STATUS
            |--------------------------------------------------------------------------
            */

            $deliveryStatusStmt =
                $db->prepare("
                    INSERT INTO delivery_status (
                        order_id,
                        status,
                        notes
                    )

                    VALUES (
                        ?,
                        'Assigned',
                        ?
                    )
                ");


            $deliveryNotes =
                'Delivery assigned by admin.';


            if (
                $mlPredictedTat !== null
                &&
                $mlPredictedTat > 0
            ) {

                $deliveryNotes .=
                    ' ML predicted TAT: '
                    . $mlPredictedTat
                    . ' minutes.';
            }


            $deliveryStatusStmt->execute([
                $orderId,
                $deliveryNotes
            ]);


            /*
            |--------------------------------------------------------------------------
            | SAVE ML ASSIGNMENT LOG
            |--------------------------------------------------------------------------
            |
            | We save the prediction that was associated with
            | the Admin's confirmed assignment.
            |
            */

            if (
                $mlPredictedTat !== null
                &&
                $mlPredictedTat > 0
            ) {

                $mlLogStmt =
                    $db->prepare("
                        INSERT INTO ml_assignment_log (

                            order_id,
                            driver_id,
                            truck_id,
                            delivery_location,
                            order_volume,
                            predicted_tat_minutes,
                            actual_tat_minutes,
                            model_version

                        )

                        VALUES (

                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            NULL,
                            ?

                        )
                    ");


                $mlLogStmt->execute([

                    $orderId,

                    $driverId,

                    $truckId,

                    $deliveryLocation,

                    /*
                     * The current schema has order_volume,
                     * while the order table provides weight.
                     * We store the order weight here as the
                     * available delivery-load measure.
                     */
                    $orderWeight,

                    $mlPredictedTat,

                    'RF-TAT-v2-incidents'

                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | DRIVER NOTIFICATION
            |--------------------------------------------------------------------------
            */

            $driverNotificationMessage =
                "You have been assigned delivery "
                . $lockedOrder['order_number']
                . ". "
                . "Order weight: "
                . number_format(
                    $orderWeight,
                    2
                )
                . " kg. "
                . "Truck remaining capacity: "
                . number_format(
                    $newRemainingCapacity,
                    2
                )
                . " kg.";


            if (
                $mlPredictedTat !== null
                &&
                $mlPredictedTat > 0
            ) {

                $driverNotificationMessage .=
                    " ML estimated delivery time: "
                    . formatTat(
                        $mlPredictedTat
                    )
                    . ".";
            }


            $driverNotificationStmt =
                $db->prepare("
                    INSERT INTO notification (

                        order_id,
                        status_id,
                        notification_type,
                        recipient_role,
                        recipient_id,
                        recipient_phone,
                        is_read,
                        delivery_status,
                        message

                    )

                    VALUES (

                        ?,
                        NULL,
                        'In-App',
                        'Driver',
                        ?,
                        NULL,
                        0,
                        'Sent',
                        ?

                    )
                ");

            $driverNotificationStmt->execute([
                $orderId,
                $driverId,
                $driverNotificationMessage
            ]);


            /*
            |--------------------------------------------------------------------------
            | ADMIN NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            $adminNotificationMessage =
                "Order "
                . $lockedOrder['order_number']
                . " has been assigned to "
                . $driver['full_name']
                . " with truck "
                . $truck['plate_number']
                . ". "
                . "Truck load: "
                . number_format(
                    $newTruckLoad,
                    2
                )
                . " kg / "
                . number_format(
                    $capacity,
                    2
                )
                . " kg.";


            if (
                $mlPredictedTat !== null
                &&
                $mlPredictedTat > 0
            ) {

                $adminNotificationMessage .=
                    " ML predicted TAT: "
                    . formatTat(
                        $mlPredictedTat
                    )
                    . ".";
            }


            $adminStmt =
                $db->query("
                    SELECT user_id

                    FROM system_user

                    WHERE role = 'Admin'
                ");

            $adminUsers =
                $adminStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $adminNotificationStmt =
                $db->prepare("
                    INSERT INTO notification (

                        order_id,
                        status_id,
                        notification_type,
                        recipient_role,
                        recipient_id,
                        recipient_phone,
                        is_read,
                        delivery_status,
                        message

                    )

                    VALUES (

                        ?,
                        NULL,
                        'In-App',
                        'Admin',
                        ?,
                        NULL,
                        0,
                        'Sent',
                        ?

                    )
                ");


            foreach (
                $adminUsers
                as $adminUser
            ) {

                $adminNotificationStmt->execute([
                    $orderId,
                    (int)
                    $adminUser['user_id'],
                    $adminNotificationMessage
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $db->commit();


            header(
                "Location: orders.php?success=assigned"
            );

            exit;


        } catch (Throwable $e) {

            if (
                $db->inTransaction()
            ) {

                $db->rollBack();
            }


            $errors[] =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD AVAILABLE DRIVERS
|--------------------------------------------------------------------------
*/

$driverStmt =
    $db->query("
        SELECT

            d.driver_id,
            d.full_name,
            d.license_number,
            d.phone_number,
            d.status,
            d.current_truck_id,
            d.total_deliveries,
            d.rating

        FROM driver d

        WHERE d.status = 'Available'

        AND NOT EXISTS (

            SELECT 1

            FROM `order` o

            WHERE o.driver_id = d.driver_id

            AND o.completion_status = 'In Transit'

        )

        ORDER BY d.full_name ASC
    ");

$drivers =
    $driverStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| LOAD DRIVER TRUCK INFORMATION
|--------------------------------------------------------------------------
*/

foreach (
    $drivers
    as &$driver
) {

    $driver['truck_capacity'] =
        null;

    $driver['truck_load'] =
        null;

    $driver['remaining_capacity'] =
        null;

    $driver['truck_plate'] =
        null;

    $driver['truck_model'] =
        null;

    $driver['truck_status'] =
        null;

    $driver['can_accept'] =
        true;


    if (
        !empty(
            $driver['current_truck_id']
        )
    ) {

        $driverTruckStmt =
            $db->prepare("
                SELECT

                    plate_number,
                    model,
                    capacity,
                    status

                FROM truck

                WHERE truck_id = ?

                LIMIT 1
            ");

        $driverTruckStmt->execute([
            (int)
            $driver['current_truck_id']
        ]);

        $driverTruck =
            $driverTruckStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($driverTruck) {

            $driver['truck_capacity'] =
                (float)
                $driverTruck['capacity'];

            $driver['truck_load'] =
                getTruckLoad(
                    $db,
                    (int)
                    $driver['current_truck_id'],
                    $orderId
                );

            $driver['remaining_capacity'] =
                max(
                    0,
                    $driver['truck_capacity']
                    -
                    $driver['truck_load']
                );

            $driver['truck_plate'] =
                $driverTruck['plate_number'];

            $driver['truck_model'] =
                $driverTruck['model'];

            $driver['truck_status'] =
                $driverTruck['status'];


            $driver['can_accept'] =
                (
                    $orderWeight
                    <=
                    $driver[
                        'remaining_capacity'
                    ]
                );
        }
    }
}

unset($driver);


/*
|--------------------------------------------------------------------------
| LOAD TRUCKS
|--------------------------------------------------------------------------
*/

$truckStmt =
    $db->query("
        SELECT

            truck_id,
            plate_number,
            model,
            capacity,
            status,
            current_load,
            last_maintenance

        FROM truck

        WHERE status IN ('Available', 'In Use')

        ORDER BY plate_number ASC
    ");

$trucks =
    $truckStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| CALCULATE TRUCK CAPACITY
|--------------------------------------------------------------------------
*/

foreach (
    $trucks
    as &$truck
) {

    $truck['calculated_load'] =
        getTruckLoad(
            $db,
            (int)
            $truck['truck_id'],
            $orderId
        );


    $truck['remaining_capacity'] =
        max(
            0,
            (float)
            $truck['capacity']
            -
            $truck['calculated_load']
        );


    $truck['can_accept'] =
        (
            $orderWeight
            <=
            $truck[
                'remaining_capacity'
            ]
        );


    /*
     * Determine whether truck is already
     * attached to another active driver.
     */
    $truckOwnerStmt =
        $db->prepare("
            SELECT
                driver_id,
                full_name

            FROM driver

            WHERE current_truck_id = ?

            AND status IN (
                'Available',
                'On Delivery'
            )

            LIMIT 1
        ");

    $truckOwnerStmt->execute([
        (int)
        $truck['truck_id']
    ]);

    $truckOwner =
        $truckOwnerStmt->fetch(
            PDO::FETCH_ASSOC
        );


    $truck['owner_driver_id'] =
        $truckOwner
            ? (int)
            $truckOwner['driver_id']
            : 0;

    $truck['owner_driver_name'] =
        $truckOwner
            ? $truckOwner['full_name']
            : null;
}

unset($truck);


/*
|--------------------------------------------------------------------------
| BUILD ML DRIVER/TRUCK COMBINATIONS
|--------------------------------------------------------------------------
|
| Only valid combinations are sent to Python.
|
*/

$mlResults = [];

$mlServiceError = null;

$mlRecommendation = null;


/*
 * We need a route distance and route time
 * for meaningful ML prediction.
 */
$mlRouteReady =
    (
        $routeDistance > 0
        &&
        $routeTimeMinutes > 0
    );


if (
    $mlRouteReady
    &&
    $orderWeight > 0
    &&
    !empty($drivers)
    &&
    !empty($trucks)
) {

    $combinations = [];


    foreach (
        $drivers
        as $driver
    ) {

        foreach (
            $trucks
            as $truck
        ) {

            $driverId =
                (int)
                $driver['driver_id'];

            $truckId =
                (int)
                $truck['truck_id'];


            /*
             * Driver already has another truck.
             */
            if (
                !empty(
                    $driver['current_truck_id']
                )
                &&
                (int)
                $driver['current_truck_id']
                !==
                $truckId
            ) {

                continue;
            }


            /*
             * Truck belongs to another driver.
             */
            if (
                !empty(
                    $truck['owner_driver_id']
                )
                &&
                (int)
                $truck['owner_driver_id']
                !==
                $driverId
            ) {

                continue;
            }


            /*
             * Truck does not have enough capacity.
             */
            if (
                $orderWeight
                >
                (float)
                $truck['remaining_capacity']
            ) {

                continue;
            }


            /*
             * Only Available and valid In Use trucks can participate.
             */
            if (
                !in_array(
                    $truck['status'],
                    ['Available', 'In Use'],
                    true
                )
            ) {

                continue;
            }


            /*
             * Driver cannot be unavailable.
             */
            if ($driver['status'] !== 'Available') {

                continue;
            }


            /*
             * Rating fallback.
             *
             * The training data uses ratings.
             * A driver without a rating gets
             * a reasonable neutral value.
             */
            $driverRating =
                !empty(
                    $driver['rating']
                )
                    ? (float)
                    $driver['rating']
                    : 4.2;


            $driverIncidentFeatures =
                getDriverIncidentMlFeatures(
                    $db,
                    $driverId,
                    (int) ($driver['total_deliveries'] ?? 0)
                );

            $truckIncidentFeatures =
                getTruckIncidentMlFeatures(
                    $db,
                    $truckId
                );

            /*
             * Create ML input. Driver/truck IDs remain metadata only;
             * the Python v2 model does not use IDs as predictive features.
             */
            $combinations[] = [

                'driver_id' =>
                    $driverId,

                'truck_id' =>
                    $truckId,

                'weight_kg' =>
                    $orderWeight,

                'priority_level' =>
                    $order[
                        'priority_level'
                    ],

                'route_distance_km' =>
                    $routeDistance,

                'estimated_route_time_minutes' =>
                    $routeTimeMinutes,

                'driver_rating' =>
                    $driverRating,

                'driver_total_deliveries' =>
                    (int)
                    (
                        $driver[
                            'total_deliveries'
                        ]
                        ?? 0
                    ),

                'driver_delay_rate' =>
                    (float) $driverIncidentFeatures['driver_delay_rate'],

                'driver_verified_violation_rate' =>
                    (float) $driverIncidentFeatures['driver_verified_violation_rate'],

                'truck_capacity_kg' =>
                    (float)
                    $truck['capacity'],

                'truck_current_load_kg' =>
                    (float)
                    $truck[
                        'calculated_load'
                    ],

                'truck_breakdown_rate' =>
                    (float) $truckIncidentFeatures['truck_breakdown_rate'],

                /*
                 * Historical-profile metadata for transparent UI display.
                 * These values are NOT additional ML predictive features.
                 */
                'driver_delayed_deliveries' =>
                    (int) ($driverIncidentFeatures['driver_delayed_deliveries'] ?? 0),

                'driver_verified_violations' =>
                    (int) ($driverIncidentFeatures['driver_verified_violations'] ?? 0),

                'driver_history_deliveries' =>
                    (int) ($driverIncidentFeatures['driver_history_deliveries'] ?? 0),

                'history_source' =>
                    (string) ($driverIncidentFeatures['history_source'] ?? 'none'),

                'is_synthetic_history' =>
                    (bool) ($driverIncidentFeatures['is_synthetic_history'] ?? false),

                'truck_breakdown_deliveries' =>
                    (int) ($truckIncidentFeatures['truck_breakdown_deliveries'] ?? 0),

                'truck_completed_deliveries' =>
                    (int) ($truckIncidentFeatures['truck_completed_deliveries'] ?? 0),

                'truck_history_source' =>
                    (string) ($truckIncidentFeatures['truck_history_source'] ?? 'none'),

                'truck_is_synthetic_history' =>
                    (bool) ($truckIncidentFeatures['truck_is_synthetic_history'] ?? false),

                /*
                 * UI information.
                 */
                'driver_name' =>
                    $driver['full_name'],

                'driver_phone' =>
                    $driver['phone_number'],

                'truck_plate' =>
                    $truck['plate_number'],

                'truck_model' =>
                    $truck['model'],

                'truck_remaining_capacity' =>
                    (float)
                    $truck[
                        'remaining_capacity'
                    ]
            ];
        }
    }


    /*
     * Send combinations to Python.
     */
    if (!empty($combinations)) {

        $mlResults =
            predictDeliveryCombinations(
                $combinations
            );


        /*
         * Find first successful result.
         */
        foreach (
            $mlResults
            as $result
        ) {

            if (
                isset(
                    $result[
                        'predicted_tat_minutes'
                    ]
                )
                &&
                $result[
                    'predicted_tat_minutes'
                ] !== null
            ) {

                $mlRecommendation =
                    $result;

                break;
            }
        }


        /*
         * Check whether all predictions failed.
         */
        if (
            $mlRecommendation === null
        ) {

            $mlServiceError =
                'The ML service did not return a valid prediction for the available combinations.';
        }
    }


} elseif (
    !$mlRouteReady
) {

    $mlServiceError =
        'ML recommendation is unavailable because this order does not have complete route distance and estimated route time data.';


} elseif (
    $orderWeight <= 0
) {

    $mlServiceError =
        'ML recommendation is unavailable because the order weight is missing or zero.';
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

$navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL .
            'admin/dashboard.php'
    ],

    [
        'label' => 'Orders',
        'url' =>
            BASE_URL .
            'admin/orders.php'
    ],

    [
        'label' => 'Route Optimization',
        'url' =>
            BASE_URL .
            'admin/route_optimization.php'
    ],

    [
        'label' => 'Delivery Incidents',
        'url' =>
            BASE_URL .
            'admin/delivery_incidents.php'
    ],

    [
        'label' => 'Delay Analytics',
        'url' =>
            BASE_URL .
            'admin/delay_analytics.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL .
            'admin/notifications.php'
    ],

    [
        'label' => 'Products',
        'url' =>
            BASE_URL .
            'admin/products.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'admin/profile.php'
    ]

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= $isViewOnly ? 'View Assignment' : 'Assign Delivery' ?>
</title>


<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>


<style>

.assignment-layout {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        minmax(320px, 420px);

    gap: 24px;

    align-items: start;
}


.assignment-main {

    min-width: 0;
}


.assignment-side {

    min-width: 0;

    position: sticky;

    top: 24px;
}


.ml-card {

    border: 1px solid
        var(--color-border);

    border-radius:
        var(--radius-lg);

    background:
        var(--color-surface);

    overflow: hidden;

}


.ml-card-header {

    padding: 20px 22px;

    border-bottom: 1px solid
        var(--color-border);

    background:
        var(--color-accent-soft);
}


.ml-card-header h2 {

    margin: 0;

}


.ml-card-header p {

    margin: 6px 0 0;

    color:
        var(--color-text-secondary);

}


.ml-recommendation {

    padding: 22px;
}


.ml-recommendation-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 18px;
}


.ml-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 7px 11px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 700;

    background:
        var(--color-success);

    color: #fff;
}


.ml-rank {

    font-size: 12px;

    font-weight: 700;

    color:
        var(--color-text-muted);
}


.ml-driver-truck {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 12px;

    margin-bottom: 18px;
}


.ml-person {

    padding: 16px;

    border: 1px solid
        var(--color-border);

    border-radius:
        var(--radius-md);

    background:
        var(--color-surface-alt);
}


.ml-person-label {

    display: block;

    margin-bottom: 5px;

    font-size: 12px;

    color:
        var(--color-text-muted);
}


.ml-person strong {

    display: block;

    font-size: 15px;
}


.ml-tat {

    padding: 18px;

    border-radius:
        var(--radius-md);

    background:
        var(--color-bg);

    text-align: center;

    margin-bottom: 18px;
}


.ml-tat-label {

    display: block;

    font-size: 12px;

    color:
        var(--color-text-muted);

    margin-bottom: 4px;
}


.ml-tat-value {

    display: block;

    font-size: 32px;

    line-height: 1.1;

    font-weight: 800;

}


.ml-tat-range {

    margin-top: 7px;

    font-size: 13px;

    color:
        var(--color-text-secondary);
}


.ml-meta {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 10px;

    margin-bottom: 18px;
}


.ml-meta-item {

    padding: 12px;

    border: 1px solid
        var(--color-border);

    border-radius:
        var(--radius-sm);
}


.ml-meta-item span {

    display: block;

    font-size: 11px;

    color:
        var(--color-text-muted);

    margin-bottom: 3px;
}


.ml-meta-item strong {

    font-size: 14px;
}

.ml-history-note {
    display: block;
    margin-top: 4px;
    font-size: 10px;
    line-height: 1.35;
    color: var(--color-text-muted);
}

.ml-history-badge {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 9px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-text-secondary);
}


.ml-use-button {

    width: 100%;

    border: 0;

    cursor: pointer;

    padding: 13px 16px;

    border-radius:
        var(--radius-md);

    background:
        var(--color-accent);

    color: #fff;

    font-weight: 700;

    font-size: 14px;

}


.ml-use-button:hover {

    background:
        var(--color-accent-hover);
}


.ml-disabled {

    padding: 20px;

    color:
        var(--color-text-secondary);

    background:
        var(--color-surface);
}


.ml-disabled strong {

    display: block;

    margin-bottom: 7px;

    color:
        var(--color-text-primary);
}


.ml-table {

    width: 100%;

    border-collapse: collapse;

    font-size: 13px;
}


.ml-table th,
.ml-table td {

    padding: 11px 12px;

    border-bottom: 1px solid
        var(--color-border);

    text-align: left;

}


.ml-table th {

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .04em;

    color:
        var(--color-text-muted);
}


.ml-table tr.ml-best {

    background:
        var(--color-accent-soft);
}


.ml-tat-small {

    font-weight: 800;

    white-space: nowrap;
}


.ml-model-note {

    margin-top: 14px;

    font-size: 11px;

    color:
        var(--color-text-muted);

    line-height: 1.5;
}


.route-info {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 12px;

    margin-top: 18px;
}


.route-info-item {

    padding: 14px;

    border: 1px solid
        var(--color-border);

    border-radius:
        var(--radius-md);

    background:
        var(--color-surface-alt);
}


.route-info-item span {

    display: block;

    font-size: 11px;

    color:
        var(--color-text-muted);

    margin-bottom: 5px;
}


.route-info-item strong {

    font-size: 14px;
}



.assignment-status-card {
    margin-bottom: 20px;
    padding: 20px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-surface);
}
.assignment-status-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}
.assignment-status-title {
    margin: 0 0 6px;
    color: var(--color-text-primary);
    font-size: 18px;
}
.assignment-status-text {
    margin: 0;
    color: var(--color-text-secondary);
    font-size: 13px;
    line-height: 1.55;
}
.assignment-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 7px 11px;
    border-radius: 999px;
    background: var(--color-accent-soft);
    color: var(--color-accent);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}
.current-assignment-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 20px;
}
.current-assignment-item {
    padding: 15px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface-alt);
}
.current-assignment-item span {
    display: block;
    margin-bottom: 5px;
    color: var(--color-text-muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}
.current-assignment-item strong {
    color: var(--color-text-primary);
    font-size: 14px;
}
@media (max-width: 700px) {
    .current-assignment-grid { grid-template-columns: 1fr; }
    .assignment-status-top { flex-direction: column; }
}

@media (max-width: 1100px) {

    .assignment-layout {

        grid-template-columns: 1fr;

    }


    .assignment-side {

        position: static;

    }

}


@media (max-width: 700px) {

    .ml-driver-truck,
    .ml-meta,
    .route-info {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>

<?php
require __DIR__ .
    '/../includes/header.php';
?>


<main class="app-main">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="page-header">

    <div>

        <h1>
            <?= $isViewOnly ? 'View Assignment' : 'Assign Delivery' ?>
        </h1>

        <p>
            <?php if ($isViewOnly): ?>
                Monitor the assigned driver, truck, and current delivery progress.
            <?php else: ?>
                Review the ML recommendation and confirm the driver and truck for this order.
            <?php endif; ?>
        </p>

    </div>


    <a
        href="<?= BASE_URL ?>admin/orders.php"
        class="btn-secondary"
    >
        ← Back to Orders
    </a>

</div>


<!-- ==========================================================
     ERROR MESSAGE
========================================================== -->

<?php if (!empty($errors)): ?>

    <div class="alert-error">

        <?php foreach ($errors as $error): ?>

            <div>
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<div class="assignment-layout">


<div class="assignment-main">


<!-- ==========================================================
     ORDER INFORMATION
========================================================== -->

<div class="card panel">

    <h2>
        Order Information
    </h2>


    <div class="stat-grid">


        <div>

            <span class="stat-label">
                Order Number
            </span>

            <strong>
                <?= htmlspecialchars(
                    $order['order_number']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="stat-label">
                Customer
            </span>

            <strong>
                <?= htmlspecialchars(
                    $order['customer_name']
                    ?? '-'
                ) ?>
            </strong>

        </div>


        <div>

            <span class="stat-label">
                Priority
            </span>

            <?php if (
                $order['priority_level']
                === 'Urgent'
            ): ?>

                <span
                    class="status-pill status-failed"
                >
                    Urgent
                </span>

            <?php else: ?>

                <span
                    class="status-pill status-pending"
                >
                    Normal
                </span>

            <?php endif; ?>

        </div>


        <div>

            <span class="stat-label">
                Status
            </span>

            <span
                class="status-pill status-pending"
            >
                <?= htmlspecialchars(
                    $order['completion_status']
                ) ?>
            </span>

        </div>

    </div>


    <br>


    <div>

        <span class="stat-label">
            Delivery Address
        </span>

        <p>

            <?= htmlspecialchars(
                $order['customer_address']
                ?? '-'
            ) ?>


            <?php if (
                !empty(
                    $order['customer_city']
                )
            ): ?>

                ,
                <?= htmlspecialchars(
                    $order['customer_city']
                ) ?>

            <?php endif; ?>


            <?php if (
                !empty(
                    $order['customer_province']
                )
            ): ?>

                ,
                <?= htmlspecialchars(
                    $order['customer_province']
                ) ?>

            <?php endif; ?>

        </p>

    </div>


    <br>


    <div>

        <span class="stat-label">
            Item Description
        </span>

        <p>

            <?= nl2br(
                htmlspecialchars(
                    $order['item_description']
                    ?? '-'
                )
            ) ?>

        </p>

    </div>


    <div class="route-info">


        <div class="route-info-item">

            <span>
                Order Weight
            </span>

            <strong>

                <?= number_format(
                    $orderWeight,
                    2
                ) ?>

                kg

            </strong>

        </div>


        <div class="route-info-item">

            <span>
                Route Distance
            </span>

            <strong>

                <?php if (
                    $routeDistance > 0
                ): ?>

                    <?= number_format(
                        $routeDistance,
                        2
                    ) ?>

                    km

                <?php else: ?>

                    Not available

                <?php endif; ?>

            </strong>

        </div>


        <div class="route-info-item">

            <span>
                Estimated Route Time
            </span>

            <strong>

                <?php if (
                    $routeTimeMinutes > 0
                ): ?>

                    <?= formatTat(
                        $routeTimeMinutes
                    ) ?>

                <?php else: ?>

                    Not available

                <?php endif; ?>

            </strong>

        </div>


        <div class="route-info-item">

            <span>
                Route
            </span>

            <strong>

                <?= htmlspecialchars(
                    $order['route_code']
                    ?? 'No route'
                ) ?>

            </strong>

        </div>


        <div class="route-info-item">

            <span>
                Subtotal
            </span>

            <strong>
                ₱<?= number_format(
                    (float) (
                        $order['subtotal']
                        ?? $order['total_amount']
                    ),
                    2
                ) ?>
            </strong>

        </div>


        <div class="route-info-item">

            <span>
                Urgent Delivery Fee
            </span>

            <strong>
                ₱<?= number_format(
                    (float) (
                        $order['urgent_fee']
                        ?? 0
                    ),
                    2
                ) ?>
            </strong>

        </div>


        <div class="route-info-item">

            <span>
                Total Amount
            </span>

            <strong>

                ₱<?= number_format(
                    (float)
                    $order['total_amount'],
                    2
                ) ?>

            </strong>

        </div>

    </div>

</div>


<br>


<!-- ==========================================================
     CURRENT ASSIGNMENT / DELIVERY PROGRESS
========================================================== -->

<?php if ($isViewOnly): ?>

<div class="assignment-status-card">

    <div class="assignment-status-top">
        <div>
            <h2 class="assignment-status-title">
                <?= htmlspecialchars($deliveryStageTitle) ?>
            </h2>
            <p class="assignment-status-text">
                <?= htmlspecialchars($deliveryStageText) ?>
            </p>
        </div>

        <span class="assignment-status-badge">
            <?= htmlspecialchars($order['completion_status']) ?>
        </span>
    </div>

    <div class="current-assignment-grid">

        <div class="current-assignment-item">
            <span>Assigned Driver</span>
            <strong>
                <?= htmlspecialchars($currentAssignment['driver_name'] ?? 'Not assigned') ?>
            </strong>
        </div>

        <div class="current-assignment-item">
            <span>Driver Status</span>
            <strong>
                <?= htmlspecialchars($currentAssignment['driver_status'] ?? '-') ?>
            </strong>
        </div>

        <div class="current-assignment-item">
            <span>Assigned Truck</span>
            <strong>
                <?= htmlspecialchars($currentAssignment['plate_number'] ?? 'Not assigned') ?>
            </strong>
        </div>

        <div class="current-assignment-item">
            <span>Truck Model</span>
            <strong>
                <?= htmlspecialchars($currentAssignment['truck_model'] ?? '-') ?>
            </strong>
        </div>

        <div class="current-assignment-item">
            <span>Driver Contact</span>
            <strong>
                <?= htmlspecialchars($currentAssignment['driver_phone'] ?? '-') ?>
            </strong>
        </div>

        <div class="current-assignment-item">
            <span>Current Delivery Stage</span>
            <strong>
                <?= htmlspecialchars($deliveryStageTitle) ?>
            </strong>
        </div>

    </div>

</div>

<?php else: ?>

<!-- ==========================================================
     ASSIGNMENT FORM
========================================================== -->

<div class="card panel">

    <h2>
        Driver & Truck Assignment
    </h2>


    <p
        style="
            color:var(--color-text-secondary);
        "
    >
        The ML recommendation appears on the right.
        You may use it or select another valid
        driver and truck manually.
    </p>


    <br>


    <form
        method="POST"
        id="assignmentForm"
    >

        <input
            type="hidden"
            name="order_id"
            value="<?= $orderId ?>"
        >


        <!-- ==================================================
             ML HIDDEN VALUES
        ================================================== -->

        <input
            type="hidden"
            name="ml_predicted_tat_minutes"
            id="mlPredictedTat"
            value=""
        >

        <input
            type="hidden"
            name="ml_minimum_tat_minutes"
            id="mlMinimumTat"
            value=""
        >

        <input
            type="hidden"
            name="ml_maximum_tat_minutes"
            id="mlMaximumTat"
            value=""
        >

        <input
            type="hidden"
            name="ml_recommendation"
            id="mlRecommendation"
            value=""
        >


        <!-- ==================================================
             DRIVER
        ================================================== -->

        <label class="form-label">
            Select Driver
        </label>


        <?php if (
            empty($drivers)
        ): ?>

            <div class="alert-error">

                No available drivers found.

            </div>

        <?php else: ?>


            <select
                name="driver_id"
                id="driver_id"
                class="form-input"
                required
            >

                <option value="">
                    -- Select Driver --
                </option>


                <?php foreach (
                    $drivers
                    as $driver
                ): ?>


                    <option

                        value="<?= (int)
                            $driver['driver_id'] ?>"

                        data-truck-id="<?= (int)
                            (
                                $driver[
                                    'current_truck_id'
                                ]
                                ?? 0
                            ) ?>"

                        data-remaining-capacity="<?= htmlspecialchars(
                            (string)(
                                $driver[
                                    'remaining_capacity'
                                ]
                                ?? ''
                            )
                        ) ?>"

                    >

                        <?= htmlspecialchars(
                            $driver['full_name']
                        ) ?>

                        —

                        <?= htmlspecialchars(
                            $driver['phone_number']
                        ) ?>


                        <?php if (
                            $driver['truck_plate']
                        ): ?>

                            —

                            Truck:
                            <?= htmlspecialchars(
                                $driver[
                                    'truck_plate'
                                ]
                            ) ?>

                            —

                            Remaining:
                            <?= number_format(
                                (float)
                                $driver[
                                    'remaining_capacity'
                                ],
                                2
                            ) ?>

                            kg

                        <?php else: ?>

                            —
                            No truck assigned

                        <?php endif; ?>

                    </option>


                <?php endforeach; ?>


            </select>


        <?php endif; ?>


        <br>
        <br>


        <!-- ==================================================
             TRUCK
        ================================================== -->

        <label class="form-label">
            Select Truck
        </label>


        <?php if (
            empty($trucks)
        ): ?>

            <div class="alert-error">

                No usable trucks found.

            </div>

        <?php else: ?>


            <select
                name="truck_id"
                id="truck_id"
                class="form-input"
                required
            >

                <option value="">
                    -- Select Truck --
                </option>


                <?php foreach (
                    $trucks
                    as $truck
                ): ?>


                    <option

                        value="<?= (int)
                            $truck['truck_id'] ?>"

                        data-remaining-capacity="<?= htmlspecialchars(
                            (string)
                            $truck[
                                'remaining_capacity'
                            ]
                        ) ?>"

                    >

                        <?= htmlspecialchars(
                            $truck['plate_number']
                        ) ?>

                        —

                        <?= htmlspecialchars(
                            $truck['model']
                        ) ?>

                        —

                        Capacity:
                        <?= number_format(
                            (float)
                            $truck['capacity'],
                            2
                        ) ?>

                        kg

                        —

                        Load:
                        <?= number_format(
                            (float)
                            $truck[
                                'calculated_load'
                            ],
                            2
                        ) ?>

                        kg

                        —

                        Remaining:
                        <?= number_format(
                            (float)
                            $truck[
                                'remaining_capacity'
                            ],
                            2
                        ) ?>

                        kg

                    </option>


                <?php endforeach; ?>


            </select>


        <?php endif; ?>


        <br>
        <br>


        <!-- ==================================================
             CAPACITY PREVIEW
        ================================================== -->

        <div
            id="capacityPreview"
            class="card"
            style="
                padding:18px;
                margin-top:10px;
                background:var(--color-bg);
            "
        >

            <strong>
                Capacity Check
            </strong>


            <p
                id="capacityText"
                style="
                    margin-top:8px;
                    color:var(--color-text-secondary);
                "
            >
                Select a driver and truck to see
                the capacity information.
            </p>

        </div>


        <br>


        <!-- ==================================================
             BUTTONS
        ================================================== -->

        <div class="modal-footer">


            <a
                href="<?= BASE_URL ?>admin/orders.php"
                class="btn-secondary"
            >
                Cancel
            </a>


            <button
                type="submit"
                name="assign_delivery"
                id="assignButton"
                class="btn-success"

                <?= (
                    empty($drivers)
                    ||
                    empty($trucks)
                )
                    ? 'disabled'
                    : ''
                ?>

            >

                Confirm Assignment

            </button>


        </div>


    </form>

</div>


<br>



<?php endif; ?>

<!-- ==========================================================
     ASSIGNMENT RULES
========================================================== -->

<div class="card panel">

    <h2>
        Assignment Rules
    </h2>


    <ul>

        <li>
            Driver must be
            <strong>Available</strong>.
        </li>

        <li>
            A driver with an
            <strong>In Transit</strong>
            delivery cannot receive another delivery.
        </li>

        <li>
            Assigned and Accepted orders
            <strong>occupy truck capacity</strong>.
        </li>

        <li>
            The order must fit within the truck's
            <strong>remaining capacity</strong>.
        </li>

        <li>
            A truck may contain multiple assigned
            orders while capacity remains available.
        </li>

        <li>
            Completed and Cancelled orders
            no longer occupy truck capacity.
        </li>

        <li>
            The driver remains
            <strong>Available</strong>
            after assignment.
        </li>

        <li>
            The driver becomes
            <strong>On Delivery</strong>
            only when the delivery starts.
        </li>

        <li>
            Trucks with
            <strong>Maintenance</strong>,
            <strong>Out of Service</strong>,
            or <strong>Retired</strong> status
            cannot be assigned.
        </li>

        <li>
            An <strong>In Use</strong> truck may receive
            additional assigned orders when it belongs to
            the selected driver and has enough remaining capacity.
        </li>

        <li>
            <strong>Urgent</strong> orders receive priority
            during assignment, but urgent priority does not
            guarantee same-day delivery.
        </li>

        <li>
            The ML recommendation is
            <strong>advisory</strong>.
            Final validation is performed by the DMS.
        </li>

    </ul>

</div>


</div>


<!-- ==========================================================
     ML SIDEBAR
========================================================== -->

<aside class="assignment-side">


<div class="ml-card">


<div class="ml-card-header">

    <h2>
        🤖 ML Recommendation
    </h2>

    <p>
        Delivery TAT prediction
    </p>

</div>


<?php if (
    $mlRecommendation
): ?>


<div class="ml-recommendation">


    <div class="ml-recommendation-top">

        <span class="ml-badge">
            🤖 Recommended
        </span>

        <span class="ml-rank">
            Best predicted TAT
        </span>

    </div>


    <div class="ml-driver-truck">


        <div class="ml-person">

            <span class="ml-person-label">
                Driver
            </span>

            <strong>
                <?= htmlspecialchars(
                    $mlRecommendation[
                        'driver_name'
                    ]
                ) ?>
            </strong>

        </div>


        <div class="ml-person">

            <span class="ml-person-label">
                Truck
            </span>

            <strong>
                <?= htmlspecialchars(
                    $mlRecommendation[
                        'truck_plate'
                    ]
                ) ?>
            </strong>

        </div>


    </div>


    <div class="ml-tat">

        <span class="ml-tat-label">
            Predicted Delivery TAT
        </span>


        <span class="ml-tat-value">

            <?= formatTat(
                (int)
                $mlRecommendation[
                    'predicted_tat_minutes'
                ]
            ) ?>

        </span>


        <div class="ml-tat-range">

            Estimated range:

            <?= formatTat(
                (int)
                $mlRecommendation[
                    'minimum_tat_minutes'
                ]
            ) ?>

            —

            <?= formatTat(
                (int)
                $mlRecommendation[
                    'maximum_tat_minutes'
                ]
            ) ?>

        </div>

    </div>


    <div class="ml-meta">


        <div class="ml-meta-item">

            <span>
                Driver Rating
            </span>

            <strong>

                <?= number_format(
                    (float)
                    $mlRecommendation[
                        'driver_rating'
                    ],
                    2
                ) ?>

                / 5

            </strong>

        </div>


        <div class="ml-meta-item">

            <span>
                Deliveries
            </span>

            <strong>

                <?= number_format(
                    (int)
                    $mlRecommendation[
                        'driver_total_deliveries'
                    ]
                ) ?>

            </strong>

        </div>


        <div class="ml-meta-item">

            <span>
                Historical Delay Rate
            </span>

            <strong>
                <?= number_format(
                    ((float) ($mlRecommendation['driver_delay_rate'] ?? 0)) * 100,
                    1
                ) ?>%
            </strong>
            <small class="ml-history-note">
                <?= number_format((int) ($mlRecommendation['driver_delayed_deliveries'] ?? 0)) ?> delayed of
                <?= number_format((int) ($mlRecommendation['driver_history_deliveries'] ?? 0)) ?> history deliveries
            </small>

        </div>


        <div class="ml-meta-item">

            <span>
                Verified Violation Rate
            </span>

            <strong>
                <?= number_format(
                    ((float) ($mlRecommendation['driver_verified_violation_rate'] ?? 0)) * 100,
                    1
                ) ?>%
            </strong>
            <small class="ml-history-note">
                <?= number_format((int) ($mlRecommendation['driver_verified_violations'] ?? 0)) ?> verified of
                <?= number_format((int) ($mlRecommendation['driver_history_deliveries'] ?? 0)) ?> history deliveries
            </small>

        </div>


        <div class="ml-meta-item">

            <span>
                Truck Breakdown Rate
            </span>

            <strong>
                <?= number_format(
                    ((float) ($mlRecommendation['truck_breakdown_rate'] ?? 0)) * 100,
                    1
                ) ?>%
            </strong>
            <small class="ml-history-note">
                <?= number_format((int) ($mlRecommendation['truck_breakdown_deliveries'] ?? 0)) ?> breakdown of
                <?= number_format((int) ($mlRecommendation['truck_completed_deliveries'] ?? 0)) ?> history deliveries
            </small>

        </div>


        <div class="ml-meta-item">

            <span>
                Truck Capacity
            </span>

            <strong>

                <?= number_format(
                    (float)
                    $mlRecommendation[
                        'truck_capacity_kg'
                    ],
                    2
                ) ?>

                kg

            </strong>

        </div>


        <div class="ml-meta-item">

            <span>
                Remaining Capacity
            </span>

            <strong>

                <?= number_format(
                    (float)
                    $mlRecommendation[
                        'truck_remaining_capacity'
                    ],
                    2
                ) ?>

                kg

            </strong>

        </div>


    </div>


    <button
        type="button"
        class="ml-use-button"
        id="useMlRecommendation"
    >

        Use ML Recommendation

    </button>


    <div class="ml-model-note">

        Model: Random Forest Regressor (RF-TAT-v2-incidents)<br>

        Target: Delivery TAT<br>

        Eligibility is rule-based. ML ranks only valid combinations using
        order, route, driver, truck, and reviewed historical incident features.
        The recommendation remains advisory and the Admin makes the final decision.

    </div>

</div>


<?php else: ?>


<div class="ml-disabled">

    <strong>
        ML recommendation unavailable
    </strong>

    <?= htmlspecialchars(
        $mlServiceError
        ?? 'No recommendation is currently available.'
    ) ?>


    <?php if (
        !$mlRouteReady
    ): ?>

        <div
            style="
                margin-top:12px;
            "
        >

            For ML prediction, this order needs:

            <ul>

                <li>
                    Route distance
                </li>

                <li>
                    Estimated route time
                </li>

                <li>
                    Order weight
                </li>

            </ul>

        </div>

    <?php endif; ?>

</div>


<?php endif; ?>


</div>


<?php if (
    count($mlResults) > 1
): ?>


<br>


<div class="ml-card">

    <div class="ml-card-header">

        <h2>
            Other Predictions
        </h2>

        <p>
            Ranked valid driver/truck combinations
        </p>

    </div>


    <div
        style="
            overflow-x:auto;
        "
    >

        <table class="ml-table">

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        Driver
                    </th>

                    <th>
                        Truck
                    </th>

                    <th>
                        TAT
                    </th>

                    <th>
                        History
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php
            $rank = 0;
            ?>


            <?php foreach (
                $mlResults
                as $result
            ): ?>


                <?php
                if (
                    $result[
                        'predicted_tat_minutes'
                    ] === null
                ) {
                    continue;
                }

                $rank++;
                ?>


                <tr
                    class="<?= (
                        $rank === 1
                    )
                        ? 'ml-best'
                        : ''
                    ?>"
                >

                    <td>
                        <strong>
                            <?= $rank ?>
                        </strong>
                    </td>


                    <td>
                        <?= htmlspecialchars(
                            $result[
                                'driver_name'
                            ]
                        ) ?>
                    </td>


                    <td>
                        <?= htmlspecialchars(
                            $result[
                                'truck_plate'
                            ]
                        ) ?>
                    </td>


                    <td>

                        <span class="ml-tat-small">

                            <?= formatTat(
                                (int)
                                $result[
                                    'predicted_tat_minutes'
                                ]
                            ) ?>

                        </span>

                    </td>

                    <td>
                        Delay <?= number_format(((float) ($result['driver_delay_rate'] ?? 0)) * 100, 1) ?>%<br>
                        Violation <?= number_format(((float) ($result['driver_verified_violation_rate'] ?? 0)) * 100, 1) ?>%<br>
                        Breakdown <?= number_format(((float) ($result['truck_breakdown_rate'] ?? 0)) * 100, 1) ?>%
                    </td>

                </tr>


            <?php endforeach; ?>


            </tbody>

        </table>

    </div>

</div>


<?php endif; ?>


</aside>


</div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| BASIC ELEMENTS
|--------------------------------------------------------------------------
*/

const orderWeight =
    <?= json_encode(
        $orderWeight
    ) ?>;


const driverSelect =
    document.getElementById(
        'driver_id'
    );


const truckSelect =
    document.getElementById(
        'truck_id'
    );


const capacityText =
    document.getElementById(
        'capacityText'
    );


const assignButton =
    document.getElementById(
        'assignButton'
    );


/*
|--------------------------------------------------------------------------
| ML RECOMMENDATION DATA
|--------------------------------------------------------------------------
*/

const mlRecommendation =
    <?= json_encode(
        $mlRecommendation,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;


const mlPredictedTat =
    document.getElementById(
        'mlPredictedTat'
    );


const mlMinimumTat =
    document.getElementById(
        'mlMinimumTat'
    );


const mlMaximumTat =
    document.getElementById(
        'mlMaximumTat'
    );


const mlRecommendationInput =
    document.getElementById(
        'mlRecommendation'
    );


/*
|--------------------------------------------------------------------------
| CAPACITY PREVIEW
|--------------------------------------------------------------------------
*/

function updateCapacityPreview() {

    if (
        !driverSelect
        ||
        !truckSelect
    ) {

        return;
    }


    const driverOption =
        driverSelect.options[
            driverSelect.selectedIndex
        ];


    const truckOption =
        truckSelect.options[
            truckSelect.selectedIndex
        ];


    if (
        !driverOption
        ||
        !truckOption
        ||
        !driverOption.value
        ||
        !truckOption.value
    ) {

        capacityText.textContent =
            'Select a driver and truck to see the capacity information.';

        return;
    }


    const driverTruckId =
        driverOption.dataset.truckId;


    const selectedTruckId =
        truckOption.value;


    /*
     * Driver already has a different truck.
     */
    if (
        driverTruckId
        &&
        driverTruckId !== '0'
        &&
        driverTruckId !== selectedTruckId
    ) {

        capacityText.textContent =
            'This driver is already assigned to another truck.';

        assignButton.disabled =
            true;

        return;
    }


    const remainingCapacity =
        parseFloat(
            truckOption.dataset
                .remainingCapacity
        );


    if (
        isNaN(
            remainingCapacity
        )
    ) {

        capacityText.textContent =
            'Unable to determine truck capacity.';

        assignButton.disabled =
            true;

        return;
    }


    const remainingAfter =
        remainingCapacity
        -
        orderWeight;


    if (
        remainingAfter < 0
    ) {

        capacityText.textContent =
            'Not enough capacity. '
            +
            'Order weight: '
            +
            orderWeight.toFixed(2)
            +
            ' kg. '
            +
            'Truck remaining capacity: '
            +
            remainingCapacity.toFixed(2)
            +
            ' kg.';

        assignButton.disabled =
            true;

        return;
    }


    capacityText.textContent =
        'Order weight: '
        +
        orderWeight.toFixed(2)
        +
        ' kg. '
        +
        'Truck remaining capacity before assignment: '
        +
        remainingCapacity.toFixed(2)
        +
        ' kg. '
        +
        'Remaining after assignment: '
        +
        remainingAfter.toFixed(2)
        +
        ' kg.';


    assignButton.disabled =
        false;
}


/*
|--------------------------------------------------------------------------
| DRIVER CHANGE
|--------------------------------------------------------------------------
*/

if (driverSelect) {

    driverSelect.addEventListener(
        'change',
        function () {

            const option =
                this.options[
                    this.selectedIndex
                ];


            const driverTruckId =
                option.dataset.truckId;


            /*
             * Automatically select the driver's
             * existing truck.
             */
            if (
                driverTruckId
                &&
                driverTruckId !== '0'
                &&
                truckSelect
            ) {

                const truckOption =
                    Array.from(
                        truckSelect.options
                    ).find(
                        option =>
                            option.value
                            ===
                            driverTruckId
                    );


                if (truckOption) {

                    truckSelect.value =
                        driverTruckId;
                }
            }


            updateCapacityPreview();
        }
    );
}


/*
|--------------------------------------------------------------------------
| TRUCK CHANGE
|--------------------------------------------------------------------------
*/

if (truckSelect) {

    truckSelect.addEventListener(
        'change',
        updateCapacityPreview
    );
}


/*
|--------------------------------------------------------------------------
| USE ML RECOMMENDATION
|--------------------------------------------------------------------------
*/

const useMlButton =
    document.getElementById(
        'useMlRecommendation'
    );


if (
    useMlButton
    &&
    mlRecommendation
) {

    useMlButton.addEventListener(
        'click',
        function () {

            /*
             * Select recommended driver.
             */
            driverSelect.value =
                String(
                    mlRecommendation.driver_id
                );


            /*
             * Select recommended truck.
             */
            truckSelect.value =
                String(
                    mlRecommendation.truck_id
                );


            /*
             * Save ML information
             * into hidden fields.
             */
            mlPredictedTat.value =
                mlRecommendation[
                    'predicted_tat_minutes'
                ];


            mlMinimumTat.value =
                mlRecommendation[
                    'minimum_tat_minutes'
                ];


            mlMaximumTat.value =
                mlRecommendation[
                    'maximum_tat_minutes'
                ];


            mlRecommendationInput.value =
                'ML Recommended';


            updateCapacityPreview();


            /*
             * Visual feedback.
             */
            useMlButton.textContent =
                '✓ ML Recommendation Selected';


            useMlButton.style.opacity =
                '0.85';


            /*
             * Scroll toward assignment form.
             */
            if (driverSelect) {

                driverSelect.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

            }

        }
    );
}


/*
|--------------------------------------------------------------------------
| MANUAL SELECTION
|--------------------------------------------------------------------------
|
| If Admin manually changes the driver/truck after
| using the recommendation, clear the ML hidden
| values. This prevents logging an old prediction
| against a different assignment.
|
*/

function clearMlSelection() {

    if (
        !mlPredictedTat
        ||
        !mlRecommendationInput
    ) {

        return;
    }


    mlPredictedTat.value = '';

    mlMinimumTat.value = '';

    mlMaximumTat.value = '';

    mlRecommendationInput.value = '';


    if (useMlButton) {

        useMlButton.textContent =
            'Use ML Recommendation';

        useMlButton.style.opacity =
            '1';
    }
}


if (driverSelect) {

    driverSelect.addEventListener(
        'change',
        function () {

            if (
                mlRecommendation
                &&
                String(
                    this.value
                )
                !==
                String(
                    mlRecommendation.driver_id
                )
            ) {

                clearMlSelection();
            }

        }
    );
}


if (truckSelect) {

    truckSelect.addEventListener(
        'change',
        function () {

            if (
                mlRecommendation
                &&
                String(
                    this.value
                )
                !==
                String(
                    mlRecommendation.truck_id
                )
            ) {

                clearMlSelection();
            }

        }
    );
}


/*
|--------------------------------------------------------------------------
| INITIAL CHECK
|--------------------------------------------------------------------------
*/

updateCapacityPreview();

</script>


</body>

</html>