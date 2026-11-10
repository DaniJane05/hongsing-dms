<?php

/**
 * Get the total weight currently occupying a truck.
 *
 * Active truck load includes:
 * Assigned
 * Accepted
 * In Transit
 *
 * Completed and Cancelled orders are excluded.
 */
function getTruckCurrentLoad(
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

    $currentLoad =
        getTruckCurrentLoad(
            $db,
            $truckId,
            $excludeOrderId
        );

    return max(
        0,
        $capacity - $currentLoad
    );
}


/**
 * Synchronize truck.current_load and truck.status.
 */
function syncTruckLoad(
    PDO $db,
    int $truckId
): array {

    $stmt = $db->prepare("
        SELECT
            capacity,
            status
        FROM truck
        WHERE truck_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $truckId
    ]);

    $truck =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$truck) {

        throw new RuntimeException(
            'Truck not found.'
        );
    }

    $currentLoad =
        getTruckCurrentLoad(
            $db,
            $truckId
        );

    $capacity =
        (float) $truck['capacity'];

    /*
     * Do not overwrite Maintenance status.
     */
    if ($truck['status'] === 'Maintenance') {

        $truckStatus = 'Maintenance';

    } elseif ($currentLoad > 0) {

        $truckStatus = 'In Use';

    } else {

        $truckStatus = 'Available';
    }

    $updateStmt = $db->prepare("
        UPDATE truck
        SET
            current_load = ?,
            status = ?
        WHERE truck_id = ?
    ");

    $updateStmt->execute([
        $currentLoad,
        $truckStatus,
        $truckId
    ]);

    return [
        'capacity' => $capacity,
        'current_load' => $currentLoad,
        'remaining_capacity' =>
            max(
                0,
                $capacity - $currentLoad
            ),
        'status' => $truckStatus
    ];
}


/**
 * Synchronize driver status.
 *
 * Driver becomes On Delivery ONLY if there is
 * at least one In Transit order.
 */
function syncDriverStatus(
    PDO $db,
    int $driverId
): string {

    $stmt = $db->prepare("
        SELECT
            status
        FROM driver
        WHERE driver_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $driverId
    ]);

    $driver =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {

        throw new RuntimeException(
            'Driver not found.'
        );
    }

    /*
     * Do not automatically change Absent
     * or Inactive drivers.
     */
    if (
        in_array(
            $driver['status'],
            ['Absent', 'Inactive'],
            true
        )
    ) {

        return $driver['status'];
    }


    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM `order`
        WHERE driver_id = ?
        AND completion_status = 'In Transit'
    ");

    $stmt->execute([
        $driverId
    ]);

    $inTransit =
        (int) $stmt->fetchColumn();


    $newStatus =
        $inTransit > 0
            ? 'On Delivery'
            : 'Available';


    $updateStmt = $db->prepare("
        UPDATE driver
        SET status = ?
        WHERE driver_id = ?
    ");

    $updateStmt->execute([
        $newStatus,
        $driverId
    ]);


    return $newStatus;
}