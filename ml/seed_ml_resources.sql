USE hongsing_dms;

-- =========================================================
-- SYNTHETIC ML DRIVERS
-- Existing drivers 1 and 2 are NOT modified.
-- These drivers are for synthetic ML training scenarios.
-- =========================================================

INSERT INTO `driver`
(
    `license_number`,
    `full_name`,
    `phone_number`,
    `email`,
    `password_hash`,
    `status`,
    `current_truck_id`,
    `total_deliveries`,
    `rating`,
    `created_at`,
    `last_location`,
    `current_latitude`,
    `current_longitude`,
    `location_updated_at`,
    `phone_verified`,
    `phone_verified_at`
)
VALUES
(
    'S03-26-100001',
    'Synthetic Driver 03',
    '09000000003',
    'synthetic.driver03@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    280,
    4.80,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
),
(
    'S04-26-100002',
    'Synthetic Driver 04',
    '09000000004',
    'synthetic.driver04@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    215,
    4.60,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
),
(
    'S05-26-100003',
    'Synthetic Driver 05',
    '09000000005',
    'synthetic.driver05@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    170,
    4.40,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
),
(
    'S06-26-100004',
    'Synthetic Driver 06',
    '09000000006',
    'synthetic.driver06@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    120,
    4.20,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
),
(
    'S07-26-100005',
    'Synthetic Driver 07',
    '09000000007',
    'synthetic.driver07@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    350,
    4.90,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
),
(
    'S08-26-100006',
    'Synthetic Driver 08',
    '09000000008',
    'synthetic.driver08@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    85,
    4.10,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
),
(
    'S09-26-100007',
    'Synthetic Driver 09',
    '09000000009',
    'synthetic.driver09@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    240,
    4.70,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
),
(
    'S10-26-100008',
    'Synthetic Driver 10',
    '09000000010',
    'synthetic.driver10@hongsing.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC9QwJf9y1Jm5XwXW',
    'Available',
    NULL,
    60,
    3.90,
    NOW(),
    'Synthetic Training Profile',
    NULL,
    NULL,
    NULL,
    0,
    NULL
);

-- =========================================================
-- SYNTHETIC ML TRUCKS
-- Existing trucks are NOT modified.
-- =========================================================

INSERT INTO `truck`
(
    `plate_number`,
    `model`,
    `capacity`,
    `status`,
    `last_maintenance`,
    `current_load`
)
VALUES
(
    'SYN-TRK-03',
    'Synthetic Light Delivery Truck',
    1000.00,
    'Available',
    CURDATE(),
    0.00
),
(
    'SYN-TRK-04',
    'Synthetic Medium Delivery Truck',
    1500.00,
    'Available',
    CURDATE(),
    0.00
),
(
    'SYN-TRK-05',
    'Synthetic Medium Cargo Truck',
    2000.00,
    'Available',
    CURDATE(),
    0.00
),
(
    'SYN-TRK-06',
    'Synthetic Heavy Cargo Truck',
    3000.00,
    'Available',
    CURDATE(),
    0.00
),
(
    'SYN-TRK-07',
    'Synthetic Heavy Duty Truck',
    4000.00,
    'Available',
    CURDATE(),
    0.00
),
(
    'SYN-TRK-08',
    'Synthetic Large Cargo Truck',
    5000.00,
    'Available',
    CURDATE(),
    0.00
);