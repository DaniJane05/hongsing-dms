<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

$db = Database::getConnection();

echo "<pre>";

echo "=============================================\n";
echo " HONG SING DMS - DEMO DRIVER/ML SEEDER\n";
echo "=============================================\n\n";

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

const DRIVER_COUNT = 15;
const TRAINING_ROWS = 2000;

$driverAccounts = [
    [
        'Adrian Cruz',
        'adrian.cruz@hongsing.test',
        'Driver@2026!3',
        '09170000003',
    ],
    [
        'Benjamin Santos',
        'benjamin.santos@hongsing.test',
        'Driver@2026!4',
        '09170000004',
    ],
    [
        'Carlo Mendoza',
        'carlo.mendoza@hongsing.test',
        'Driver@2026!5',
        '09170000005',
    ],
    [
        'Daniel Reyes',
        'daniel.reyes@hongsing.test',
        'Driver@2026!6',
        '09170000006',
    ],
    [
        'Eduardo Garcia',
        'eduardo.garcia@hongsing.test',
        'Driver@2026!7',
        '09170000007',
    ],
    [
        'Francis Dela Cruz',
        'francis.delacruz@hongsing.test',
        'Driver@2026!8',
        '09170000008',
    ],
    [
        'Gabriel Flores',
        'gabriel.flores@hongsing.test',
        'Driver@2026!9',
        '09170000009',
    ],
    [
        'Harold Aquino',
        'harold.aquino@hongsing.test',
        'Driver@2026!10',
        '09170000010',
    ],
    [
        'Ivan Bautista',
        'ivan.bautista@hongsing.test',
        'Driver@2026!11',
        '09170000011',
    ],
    [
        'Jerome Navarro',
        'jerome.navarro@hongsing.test',
        'Driver@2026!12',
        '09170000012',
    ],
    [
        'Kevin Ramos',
        'kevin.ramos@hongsing.test',
        'Driver@2026!13',
        '09170000013',
    ],
    [
        'Luis Mercado',
        'luis.mercado@hongsing.test',
        'Driver@2026!14',
        '09170000014',
    ],
    [
        'Marco Villanueva',
        'marco.villanueva@hongsing.test',
        'Driver@2026!15',
        '09170000015',
    ],
    [
        'Nathan Torres',
        'nathan.torres@hongsing.test',
        'Driver@2026!16',
        '09170000016',
    ],
    [
        'Oscar Fernandez',
        'oscar.fernandez@hongsing.test',
        'Driver@2026!17',
        '09170000017',
    ],
];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function randomFloatValue(
    float $min,
    float $max,
    int $decimals = 2
): float {
    $value =
        $min
        +
        (
            mt_rand()
            /
            mt_getrandmax()
        )
        *
        (
            $max - $min
        );

    return round(
        $value,
        $decimals
    );
}

function randomIntValue(
    int $min,
    int $max
): int {
    return mt_rand(
        $min,
        $max
    );
}

function generateLicenseNumber(
    int $number
): string {
    return 'B' .
        str_pad(
            (string) $number,
            2,
            '0',
            STR_PAD_LEFT
        )
        .
        '-26-'
        .
        str_pad(
            (string) randomIntValue(
                100000,
                999999
            ),
            6,
            '0',
            STR_PAD_LEFT
        );
}

function generatePlateNumber(
    int $number
): string {
    $letters = [
        'ABC',
        'DEF',
        'GHI',
        'JKL',
        'MNO',
        'PQR',
        'STU',
        'VWX',
        'XYZ',
    ];

    $letter =
        $letters[
            ($number - 1)
            %
            count($letters)
        ];

    return $letter
        .
        '-'
        .
        randomIntValue(
            1000,
            9999
        );
}

/*
|--------------------------------------------------------------------------
| START TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | GET EXISTING DRIVERS
    |--------------------------------------------------------------------------
    */

    $existingDriversStmt = $db->query("
        SELECT
            driver_id,
            full_name,
            email,
            status,
            current_truck_id,
            total_deliveries,
            rating
        FROM driver
        ORDER BY driver_id ASC
    ");

    $existingDrivers =
        $existingDriversStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    echo "Existing drivers: "
        .
        count($existingDrivers)
        .
        "\n";

    /*
    |--------------------------------------------------------------------------
    | INSERT 15 DRIVER ACCOUNTS
    |--------------------------------------------------------------------------
    */

    $driverIds = [];

    $insertDriverStmt = $db->prepare("
        INSERT INTO driver (
            license_number,
            full_name,
            phone_number,
            email,
            password_hash,
            status,
            current_truck_id,
            total_deliveries,
            rating,
            created_at
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'Available',
            NULL,
            0,
            ?,
            NOW()
        )
    ");

    $findDriverStmt = $db->prepare("
        SELECT driver_id
        FROM driver
        WHERE email = ?
        LIMIT 1
    ");

    foreach (
        $driverAccounts
        as $index => $account
    ) {

        [
            $fullName,
            $email,
            $password,
            $phone
        ] = $account;

        /*
         * Check if account already exists.
         */
        $findDriverStmt->execute([
            $email
        ]);

        $existingDriverId =
            $findDriverStmt->fetchColumn();

        if ($existingDriverId) {

            $driverId =
                (int) $existingDriverId;

            echo "Driver exists: "
                .
                $fullName
                .
                " (#"
                .
                $driverId
                .
                ")\n";

        } else {

            $license =
                generateLicenseNumber(
                    $index + 3
                );

            $rating =
                randomFloatValue(
                    3.8,
                    5.0,
                    2
                );

            /*
             * password_hash uses bcrypt/PASSWORD_DEFAULT.
             */
            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $insertDriverStmt->execute([
                $license,
                $fullName,
                $phone,
                $email,
                $passwordHash,
                $rating
            ]);

            $driverId =
                (int) $db->lastInsertId();

            echo "Created driver: "
                .
                $fullName
                .
                " (#"
                .
                $driverId
                .
                ")\n";
        }

        $driverIds[] =
            $driverId;
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL DRIVERS
    |--------------------------------------------------------------------------
    */

    $allDriversStmt = $db->query("
        SELECT
            driver_id,
            full_name,
            rating,
            total_deliveries,
            status
        FROM driver
        ORDER BY driver_id ASC
    ");

    $allDrivers =
        $allDriversStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    echo "\nTotal drivers now: "
        .
        count($allDrivers)
        .
        "\n";

    /*
    |--------------------------------------------------------------------------
    | CREATE TRUCKS FOR NEW DRIVERS
    |--------------------------------------------------------------------------
    */

    $truckIds = [];

    $existingTruckStmt = $db->query("
        SELECT
            truck_id,
            plate_number,
            capacity,
            status,
            current_load
        FROM truck
        ORDER BY truck_id ASC
    ");

    $existingTrucks =
        $existingTruckStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach ($existingTrucks as $truck) {

        $truckIds[] =
            (int) $truck['truck_id'];
    }

    $findTruckStmt = $db->prepare("
        SELECT truck_id
        FROM truck
        WHERE plate_number = ?
        LIMIT 1
    ");

    $insertTruckStmt = $db->prepare("
        INSERT INTO truck (
            plate_number,
            model,
            capacity,
            status,
            last_maintenance,
            current_load
        )
        VALUES (
            ?,
            ?,
            ?,
            'Available',
            CURDATE(),
            0
        )
    ");

    foreach ($driverIds as $index => $driverId) {

        $plate =
            'HS'
            .
            str_pad(
                (string) ($index + 3),
                2,
                '0',
                STR_PAD_LEFT
            )
            .
            '-'
            .
            randomIntValue(
                100,
                999
            );

        /*
         * Prevent duplicate plate.
         */
        $findTruckStmt->execute([
            $plate
        ]);

        if ($findTruckStmt->fetchColumn()) {
            continue;
        }

        /*
         * Different capacities make the ML
         * learn that truck capacity matters.
         */
        $capacities = [
            1000,
            1500,
            2000,
            2500,
            3000,
            3500,
            4000
        ];

        $capacity =
            $capacities[
                $index
                %
                count($capacities)
            ];

        $models = [
            'Isuzu Elf',
            'Mitsubishi Canter',
            'Hino Dutro',
            'Fuso Canter',
            'Isuzu N-Series',
        ];

        $model =
            $models[
                $index
                %
                count($models)
            ];

        $insertTruckStmt->execute([
            $plate,
            $model,
            $capacity
        ]);

        $truckId =
            (int) $db->lastInsertId();

        $truckIds[] =
            $truckId;

        echo "Created truck #"
            .
            $truckId
            .
            " - "
            .
            $plate
            .
            " - "
            .
            $capacity
            .
            " kg\n";
    }

    /*
    |--------------------------------------------------------------------------
    | GET EXISTING TRUCKS AGAIN
    |--------------------------------------------------------------------------
    */

    $truckStmt = $db->query("
        SELECT
            truck_id,
            plate_number,
            model,
            capacity,
            current_load,
            status
        FROM truck
        ORDER BY truck_id ASC
    ");

    $trucks =
        $truckStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | DRIVER + TRUCK ASSIGNMENTS
    |--------------------------------------------------------------------------
    */

    $assignmentCheckStmt = $db->prepare("
        SELECT assignment_id
        FROM driver_truck_assignment
        WHERE driver_id = ?
          AND truck_id = ?
          AND is_active = 1
        LIMIT 1
    ");

    $assignmentInsertStmt = $db->prepare("
        INSERT INTO driver_truck_assignment (
            driver_id,
            truck_id,
            assigned_date,
            is_active
        )
        VALUES (?, ?, CURDATE(), 1)
    ");

    /*
     * Give every new driver a truck.
     */
    foreach ($driverIds as $index => $driverId) {

        /*
         * Use existing/new trucks cyclically.
         */
        $truck =
            $trucks[
                $index
                %
                count($trucks)
            ];

        $truckId =
            (int) $truck['truck_id'];

        $assignmentCheckStmt->execute([
            $driverId,
            $truckId
        ]);

        $assignmentExists =
            $assignmentCheckStmt->fetchColumn();

        if (!$assignmentExists) {

            $assignmentInsertStmt->execute([
                $driverId,
                $truckId
            ]);
        }

        /*
         * Update driver's current truck.
         */
        $updateDriverTruck =
            $db->prepare("
                UPDATE driver
                SET
                    current_truck_id = ?,
                    status = 'Available'
                WHERE driver_id = ?
            ");

        $updateDriverTruck->execute([
            $truckId,
            $driverId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ROUTES
    |--------------------------------------------------------------------------
    |
    | We need different route distances and travel times
    | so the ML model can learn route-related effects.
    |
    */

    $routeIds = [];

    $routeCheckStmt = $db->prepare("
        SELECT route_id
        FROM route
        WHERE route_code = ?
        LIMIT 1
    ");

    $routeInsertStmt = $db->prepare("
        INSERT INTO route (
            route_code,
            path_details,
            estimated_distance,
            estimated_time,
            waypoints
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $routeTemplates = [
        [
            'North Valenzuela Route',
            6.50,
            18
        ],
        [
            'Malabon Route',
            9.80,
            27
        ],
        [
            'Navotas Route',
            12.40,
            35
        ],
        [
            'Caloocan Route',
            15.20,
            42
        ],
        [
            'Quezon City Route',
            21.50,
            58
        ],
        [
            'Manila Route',
            18.30,
            51
        ],
        [
            'Pasig Route',
            29.40,
            75
        ],
        [
            'Makati Route',
            25.80,
            68
        ],
        [
            'Marikina Route',
            31.70,
            82
        ],
        [
            'Parañaque Route',
            34.50,
            91
        ],
        [
            'Las Piñas Route',
            38.20,
            101
        ],
        [
            'Muntinlupa Route',
            43.60,
            112
        ],
        [
            'Bulacan Route',
            27.80,
            70
        ],
        [
            'Bocaue Route',
            34.20,
            86
        ],
        [
            'Malolos Route',
            47.50,
            118
        ],
    ];

    foreach ($routeTemplates as $index => $template) {

        [
            $routeName,
            $distance,
            $minutes
        ] = $template;

        $routeCode =
            'DEMO-RT-'
            .
            str_pad(
                (string) ($index + 1),
                3,
                '0',
                STR_PAD_LEFT
            );

        $routeCheckStmt->execute([
            $routeCode
        ]);

        $existingRoute =
            $routeCheckStmt->fetchColumn();

        if ($existingRoute) {

            $routeIds[] =
                (int) $existingRoute;

            continue;
        }

        $pathDetails =
            'Hong Sing LKRR Commercial'
            .
            ' → '
            .
            $routeName
            .
            ' | Demo ML training route';

        $routeTime =
            $minutes
            .
            ' min';

        $routeInsertStmt->execute([
            $routeCode,
            $pathDetails,
            $distance,
            $routeTime,
            2
        ]);

        $routeIds[] =
            (int) $db->lastInsertId();
    }

    echo "\nRoutes ready: "
        .
        count($routeIds)
        .
        "\n";

    /*
    |--------------------------------------------------------------------------
    | GET CUSTOMER
    |--------------------------------------------------------------------------
    |
    | Historical fake orders need a valid customer_id.
    | We use an existing customer dynamically.
    |
    */

    $customerStmt = $db->query("
        SELECT customer_id
        FROM customer
        ORDER BY customer_id ASC
        LIMIT 1
    ");

    $customerId =
        $customerStmt->fetchColumn();

    if (!$customerId) {

        throw new RuntimeException(
            'No customer account exists. Create at least one customer before running the seeder.'
        );
    }

    $customerId =
        (int) $customerId;

    /*
    |--------------------------------------------------------------------------
    | GENERATE HISTORICAL ORDERS
    |--------------------------------------------------------------------------
    |
    | These records simulate completed deliveries.
    |
    */

    $insertOrderStmt = $db->prepare("
        INSERT INTO `order` (
            order_number,
            customer_id,
            driver_id,
            truck_id,
            route_id,
            item_description,
            total_amount,
            total_weight,
            total_weight_kg,
            order_date,
            expected_delivery_date,
            priority_level,
            completion_status
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'Completed'
        )
    ");

    $insertStatusStmt = $db->prepare("
        INSERT INTO delivery_status (
            order_id,
            status,
            timestamp,
            notes
        )
        VALUES (?, 'Delivered', ?, ?)
    ");

    $insertMlLogStmt = $db->prepare("
        INSERT INTO ml_assignment_log (
            order_id,
            driver_id,
            truck_id,
            delivery_location,
            order_volume,
            predicted_tat_minutes,
            actual_tat_minutes,
            model_version,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'demo-v1', ?)
    ");

    /*
    |--------------------------------------------------------------------------
    | DRIVER PERFORMANCE PROFILES
    |--------------------------------------------------------------------------
    |
    | Different driver ratings and experience levels.
    |
    */

    $driverProfiles = [];

    foreach ($allDrivers as $driver) {

        $driverId =
            (int) $driver['driver_id'];

        $rating =
            $driver['rating'] !== null
                ? (float) $driver['rating']
                : randomFloatValue(
                    3.5,
                    5.0,
                    2
                );

        $experience =
            max(
                0,
                (int) $driver['total_deliveries']
            );

        /*
         * For demo training, new drivers receive
         * synthetic experience levels.
         */
        if ($experience < 20) {

            $experience =
                randomIntValue(
                    20,
                    350
                );
        }

        $driverProfiles[$driverId] = [
            'rating' =>
                $rating,

            'deliveries' =>
                $experience
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE 120 HISTORICAL ORDERS
    |--------------------------------------------------------------------------
    */

    $historicalOrderCount = 120;

    $historicalOrderIds = [];

    for (
        $i = 1;
        $i <= $historicalOrderCount;
        $i++
    ) {

        $driver =
            $allDrivers[
                array_rand(
                    $allDrivers
                )
            ];

        $driverId =
            (int) $driver['driver_id'];

        /*
         * Find a truck assigned to driver.
         */
        $driverTruckStmt =
            $db->prepare("
                SELECT truck_id
                FROM driver_truck_assignment
                WHERE driver_id = ?
                  AND is_active = 1
                ORDER BY assignment_id DESC
                LIMIT 1
            ");

        $driverTruckStmt->execute([
            $driverId
        ]);

        $truckId =
            (int) $driverTruckStmt->fetchColumn();

        if ($truckId <= 0) {

            $truck =
                $trucks[
                    array_rand(
                        $trucks
                    )
                ];

            $truckId =
                (int) $truck['truck_id'];
        }

        $route =
            $routeTemplates[
                array_rand(
                    $routeTemplates
                )
            ];

        $routeIndex =
            array_search(
                $route,
                $routeTemplates,
                true
            );

        $routeId =
            $routeIds[
                $routeIndex
            ];

        $distance =
            (float) $route[1];

        $estimatedMinutes =
            (int) $route[2];

        /*
         * Random order weight.
         */
        $weight =
            randomFloatValue(
                100,
                2800,
                2
            );

        /*
         * Priority affects TAT.
         */
        $priority =
            mt_rand(
                1,
                100
            ) <= 25
                ? 'Urgent'
                : 'Normal';

        /*
         * Driver quality.
         */
        $driverRating =
            $driverProfiles[
                $driverId
            ]['rating'];

        $driverExperience =
            $driverProfiles[
                $driverId
            ]['deliveries'];

        /*
         * Truck data.
         */
        $truckInfo = null;

        foreach (
            $trucks
            as $truck
        ) {

            if (
                (int) $truck['truck_id']
                ===
                $truckId
            ) {

                $truckInfo =
                    $truck;

                break;
            }
        }

        if (!$truckInfo) {

            $truckInfo =
                $trucks[
                    array_rand(
                        $trucks
                    )
                ];

            $truckId =
                (int) $truckInfo['truck_id'];
        }

        $truckCapacity =
            (float) $truckInfo['capacity'];

        /*
         * Simulate existing truck load.
         */
        $truckLoad =
            randomFloatValue(
                0,
                min(
                    $truckCapacity * 0.70,
                    1200
                ),
                2
            );

        /*
         * Keep capacity valid.
         */
        if (
            $truckLoad
            +
            $weight
            >
            $truckCapacity
        ) {

            $weight =
                max(
                    50,
                    $truckCapacity
                    -
                    $truckLoad
                    -
                    50
                );
        }

        /*
         * ML-style TAT calculation.
         *
         * This is only for generating realistic
         * historical training labels.
         */
        $tat =
            $estimatedMinutes;

        /*
         * Weight impact.
         */
        $tat +=
            $weight
            *
            0.012;

        /*
         * Heavy truck load impact.
         */
        $loadRatio =
            $truckCapacity > 0
                ? $truckLoad
                    /
                    $truckCapacity
                : 0;

        $tat +=
            $loadRatio
            *
            20;

        /*
         * Driver experience helps reduce TAT.
         */
        $experienceFactor =
            min(
                $driverExperience,
                400
            )
            /
            400;

        $tat -=
            $experienceFactor
            *
            8;

        /*
         * Driver rating helps slightly.
         */
        $tat -=
            max(
                0,
                $driverRating - 3.0
            )
            *
            4;

        /*
         * Urgent delivery gets lower target TAT.
         */
        if ($priority === 'Urgent') {

            $tat -=
                randomFloatValue(
                    5,
                    15
                );
        } else {

            $tat +=
                randomFloatValue(
                    3,
                    12
                );
        }

        /*
         * Realistic random traffic/noise.
         */
        $tat +=
            randomFloatValue(
                -8,
                15
            );

        $actualTat =
            max(
                20,
                min(
                    360,
                    (int) round($tat)
                )
            );

        /*
         * Predicted TAT is deliberately close
         * but not identical to actual TAT.
         */
        $predictedTat =
            max(
                15,
                $actualTat
                +
                randomIntValue(
                    -15,
                    15
                )
            );

        $orderDate =
            date(
                'Y-m-d',
                strtotime(
                    '-' .
                    randomIntValue(
                        30,
                        700
                    )
                    .
                    ' days'
                )
            );

        $expectedDate =
            date(
                'Y-m-d',
                strtotime(
                    $orderDate
                    .
                    ' +'
                    .
                    randomIntValue(
                        1,
                        5
                    )
                    .
                    ' days'
                )
            );

        $orderNumber =
            'DEMO-'
            .
            date(
                'Ymd',
                strtotime(
                    $orderDate
                )
            )
            .
            '-'
            .
            str_pad(
                (string) $i,
                5,
                '0',
                STR_PAD_LEFT
            );

        $itemDescription =
            'Historical ML Training Delivery #'
            .
            $i;

        $totalAmount =
            randomFloatValue(
                500,
                50000,
                2
            );

        $insertOrderStmt->execute([
            $orderNumber,
            $customerId,
            $driverId,
            $truckId,
            $routeId,
            $itemDescription,
            $totalAmount,
            $weight,
            $weight,
            $orderDate,
            $expectedDate,
            $priority
        ]);

        $orderId =
            (int) $db->lastInsertId();

        $historicalOrderIds[] =
            $orderId;

        /*
         * Historical delivered status.
         */
        $deliveredTimestamp =
            date(
                'Y-m-d H:i:s',
                strtotime(
                    $orderDate
                    .
                    ' +'
                    .
                    max(
                        1,
                        (int) ceil(
                            $actualTat
                            /
                            1440
                        )
                    )
                    .
                    ' days'
                )
            );

        $insertStatusStmt->execute([
            $orderId,
            $deliveredTimestamp,
            'Demo historical delivery used for ML training.'
        ]);

        /*
         * ML assignment history.
         */
        $insertMlLogStmt->execute([
            $orderId,
            $driverId,
            $truckId,
            'Demo delivery location - ' . $route[0],
            $weight,
            $predictedTat,
            $actualTat,
            $deliveredTimestamp
        ]);
    }

    echo "\nHistorical orders created: "
        .
        count($historicalOrderIds)
        .
        "\n";

    /*
    |--------------------------------------------------------------------------
    | GENERATE 2,000 ML TRAINING CSV ROWS
    |--------------------------------------------------------------------------
    */

    $mlDirectory =
        __DIR__ . '/data';

    if (!is_dir($mlDirectory)) {

        mkdir(
            $mlDirectory,
            0777,
            true
        );
    }

    $csvPath =
        $mlDirectory
        .
        '/synthetic_delivery_training_data.csv';

    $csv =
        fopen(
            $csvPath,
            'w'
        );

    if (!$csv) {

        throw new RuntimeException(
            'Unable to create ML training CSV.'
        );
    }

    fputcsv(
        $csv,
        [
            'synthetic_order_ref',
            'driver_id',
            'truck_id',
            'weight_kg',
            'priority_level',
            'route_distance_km',
            'estimated_route_time_minutes',
            'driver_rating',
            'driver_total_deliveries',
            'truck_capacity_kg',
            'truck_current_load_kg',
            'actual_tat_minutes',
            'is_synthetic'
        ]
    );

    /*
     * Use real drivers and trucks from DB.
     */
    $usableDrivers =
        $allDrivers;

    $usableTrucks =
        $trucks;

    $trainingRows =
        0;

    for (
        $row = 1;
        $row <= TRAINING_ROWS;
        $row++
    ) {

        $driver =
            $usableDrivers[
                array_rand(
                    $usableDrivers
                )
            ];

        $truck =
            $usableTrucks[
                array_rand(
                    $usableTrucks
                )
            ];

        $driverId =
            (int) $driver['driver_id'];

        $truckId =
            (int) $truck['truck_id'];

        $profile =
            $driverProfiles[
                $driverId
            ]
            ??
            [
                'rating' =>
                    randomFloatValue(
                        3.5,
                        5.0,
                        2
                    ),

                'deliveries' =>
                    randomIntValue(
                        10,
                        400
                    )
            ];

        $weight =
            randomFloatValue(
                50,
                min(
                    2800,
                    max(
                        500,
                        (float) $truck['capacity']
                    )
                ),
                2
            );

        $routeIndex =
            array_rand(
                $routeTemplates
            );

        $route =
            $routeTemplates[
                $routeIndex
            ];

        $distance =
            (float) $route[1];

        $routeMinutes =
            (int) $route[2];

        $priority =
            mt_rand(
                1,
                100
            ) <= 27
                ? 'Urgent'
                : 'Normal';

        $capacity =
            (float) $truck['capacity'];

        /*
         * Generate realistic current truck load.
         */
        $maxLoad =
            max(
                0,
                min(
                    $capacity * 0.70,
                    $capacity - $weight
                )
            );

        $currentLoad =
            randomFloatValue(
                0,
                max(
                    0,
                    $maxLoad
                ),
                2
            );

        /*
         * TAT model for synthetic label.
         */
        $actualTat =
            $routeMinutes;

        /*
         * Weight.
         */
        $actualTat +=
            $weight
            *
            0.012;

        /*
         * Current load.
         */
        $actualTat +=
            (
                $currentLoad
                /
                max(
                    1,
                    $capacity
                )
            )
            *
            20;

        /*
         * Driver experience.
         */
        $actualTat -=
            min(
                400,
                $profile['deliveries']
            )
            /
            400
            *
            8;

        /*
         * Driver rating.
         */
        $actualTat -=
            max(
                0,
                $profile['rating']
                -
                3
            )
            *
            4;

        /*
         * Urgent priority.
         */
        if (
            $priority
            ===
            'Urgent'
        ) {

            $actualTat -=
                randomFloatValue(
                    5,
                    15
                );

        } else {

            $actualTat +=
                randomFloatValue(
                    3,
                    12
                );
        }

        /*
         * Traffic/random variation.
         */
        $actualTat +=
            randomFloatValue(
                -10,
                18
            );

        $actualTat =
            max(
                20,
                min(
                    360,
                    (int) round(
                        $actualTat
                    )
                )
            );

        fputcsv(
            $csv,
            [
                'DEMO-' .
                str_pad(
                    (string) $row,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),

                $driverId,

                $truckId,

                number_format(
                    $weight,
                    2,
                    '.',
                    ''
                ),

                $priority,

                number_format(
                    $distance,
                    2,
                    '.',
                    ''
                ),

                $routeMinutes,

                number_format(
                    $profile['rating'],
                    2,
                    '.',
                    ''
                ),

                (int) $profile['deliveries'],

                number_format(
                    $capacity,
                    2,
                    '.',
                    ''
                ),

                number_format(
                    $currentLoad,
                    2,
                    '.',
                    ''
                ),

                $actualTat,

                1
            ]
        );

        $trainingRows++;
    }

    fclose($csv);

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $db->commit();

    echo "\n=============================================\n";
    echo " SEED COMPLETE\n";
    echo "=============================================\n\n";

    echo "Drivers created/available: "
        .
        count($driverIds)
        .
        "\n";

    echo "Historical orders: "
        .
        count($historicalOrderIds)
        .
        "\n";

    echo "ML CSV rows: "
        .
        $trainingRows
        .
        "\n";

    echo "ML CSV:\n"
        .
        $csvPath
        .
        "\n\n";

    echo "Driver login accounts:\n\n";

    foreach ($driverAccounts as $account) {

        echo
            $account[0]
            .
            " | "
            .
            $account[1]
            .
            " | "
            .
            $account[2]
            .
            "\n";
    }

    echo "\n=============================================\n";
    echo " NEXT STEP:\n";
    echo " Train the ML model using the generated CSV.\n";
    echo "=============================================\n";

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    echo "\n\nERROR:\n";
    echo $e->getMessage();
}

echo "</pre>";