<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

$customerId = (int) currentUserId();


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| e() is already declared globally in includes/auth.php.
| Do NOT declare function e() again in this file.
|
*/

function displayOrderStatus(string $status): string
{
    return match ($status) {
        'Completed' => 'Delivered',
        default     => $status !== '' ? $status : 'Pending',
    };
}


function orderStatusClass(string $status): string
{
    return match ($status) {

        'Pending' =>
            'status-pending',

        'Approved',
        'Assigned',
        'Accepted' =>
            'status-approved',

        'In Transit',
        'Arrived' =>
            'status-transit',

        'Completed',
        'Delivered' =>
            'status-delivered',

        'Cancelled',
        'Failed' =>
            'status-failed',

        default =>
            'status-pending',
    };
}


/*
|--------------------------------------------------------------------------
| Get Order ID
|--------------------------------------------------------------------------
|
| Supports:
| ?order_id=123
| ?id=123
|
*/

$orderId = 0;

if (isset($_GET['order_id'])) {

    $orderId = (int) $_GET['order_id'];

} elseif (isset($_GET['id'])) {

    $orderId = (int) $_GET['id'];
}


if ($orderId <= 0) {

    header(
        'Location: ' .
        BASE_URL .
        'customer/order_history.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Order
|--------------------------------------------------------------------------
|
| IMPORTANT:
| customer_id is included in the WHERE clause.
| This prevents a customer from viewing another customer's order simply
| by changing the order_id in the URL.
|
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
        o.total_amount,
        o.total_weight,
        o.total_weight_kg,
        o.order_date,
        o.expected_delivery_date,
        o.priority_level,
        o.completion_status,
        o.created_at

    FROM `order` o

    WHERE o.order_id = ?
      AND o.customer_id = ?

    LIMIT 1
");


$orderStmt->execute([
    $orderId,
    $customerId
]);


$order = $orderStmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$order) {

    header(
        'Location: ' .
        BASE_URL .
        'customer/order_history.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Delivery Location
|--------------------------------------------------------------------------
*/

$locationStmt = $db->prepare("
    SELECT
        location_id,
        order_id,
        full_address,
        city,
        province,
        latitude,
        longitude,
        contact_person,
        contact_number

    FROM delivery_location

    WHERE order_id = ?

    ORDER BY location_id DESC

    LIMIT 1
");


$locationStmt->execute([
    $orderId
]);


$deliveryLocation =
    $locationStmt->fetch(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Order Items
|--------------------------------------------------------------------------
*/

$itemStmt = $db->prepare("
    SELECT
        oi.order_item_id,
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.unit_weight_kg,
        oi.total_weight_kg,
        oi.subtotal,
        p.product_name,
        p.product_code,
        p.product_image

    FROM order_item oi

    LEFT JOIN product p
        ON p.product_id = oi.product_id

    WHERE oi.order_id = ?

    ORDER BY oi.order_item_id ASC
");


$itemStmt->execute([
    $orderId
]);


$orderItems =
    $itemStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Delivery Status History
|--------------------------------------------------------------------------
*/

$statusStmt = $db->prepare("
    SELECT
        status_id,
        order_id,
        status,
        latitude,
        longitude,
        timestamp,
        notes

    FROM delivery_status

    WHERE order_id = ?

    ORDER BY
        timestamp ASC,
        status_id ASC
");


$statusStmt->execute([
    $orderId
]);


$statusHistory =
    $statusStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Latest Delivery Status
|--------------------------------------------------------------------------
*/

$latestStatus = null;

if (!empty($statusHistory)) {

    $latestStatus =
        $statusHistory[
            count($statusHistory) - 1
        ];
}


/*
|--------------------------------------------------------------------------
| Driver
|--------------------------------------------------------------------------
*/

$driver = null;

if (!empty($order['driver_id'])) {

    $driverStmt = $db->prepare("
        SELECT
            driver_id,
            full_name,
            phone_number

        FROM driver

        WHERE driver_id = ?

        LIMIT 1
    ");


    $driverStmt->execute([
        $order['driver_id']
    ]);


    $driver =
        $driverStmt->fetch(
            PDO::FETCH_ASSOC
        );
}


/*
|--------------------------------------------------------------------------
| Truck
|--------------------------------------------------------------------------
*/

$truck = null;

if (!empty($order['truck_id'])) {

    $truckStmt = $db->prepare("
        SELECT
            truck_id,
            plate_number,
            model

        FROM truck

        WHERE truck_id = ?

        LIMIT 1
    ");


    $truckStmt->execute([
        $order['truck_id']
    ]);


    $truck =
        $truckStmt->fetch(
            PDO::FETCH_ASSOC
        );
}


/*
|--------------------------------------------------------------------------
| Totals
|--------------------------------------------------------------------------
*/

$totalItems = 0;

foreach ($orderItems as $item) {

    $totalItems +=
        (int) (
            $item['quantity']
            ?? 0
        );
}


$totalAmount =
    (float) (
        $order['total_amount']
        ?? 0
    );


$totalWeight =
    (float) (
        $order['total_weight_kg']
        ?? $order['total_weight']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| Order Status
|--------------------------------------------------------------------------
*/

$databaseOrderStatus =
    (string) (
        $order['completion_status']
        ?? 'Pending'
    );


$customerOrderStatus =
    displayOrderStatus(
        $databaseOrderStatus
    );


/*
|--------------------------------------------------------------------------
| Current Delivery Status
|--------------------------------------------------------------------------
|
| If delivery_status has progressed farther than order.completion_status,
| use the latest delivery status.
|
*/

$latestDeliveryStatus =
    $latestStatus['status']
    ?? null;


$statusRank = [

    'Pending' =>
        0,

    'Approved' =>
        1,

    'Assigned' =>
        2,

    'Accepted' =>
        2,

    'In Transit' =>
        3,

    'Arrived' =>
        4,

    'Completed' =>
        5,

    'Delivered' =>
        5,

    'Cancelled' =>
        6,

    'Failed' =>
        6
];


$currentStatus =
    $databaseOrderStatus;


if ($latestDeliveryStatus !== null) {

    $orderRank =
        $statusRank[
            $databaseOrderStatus
        ] ?? 0;


    $deliveryRank =
        $statusRank[
            $latestDeliveryStatus
        ] ?? 0;


    if ($deliveryRank >= $orderRank) {

        $currentStatus =
            $latestDeliveryStatus;
    }
}


$currentDisplayStatus =
    displayOrderStatus(
        $currentStatus
    );


/*
|--------------------------------------------------------------------------
| Status Description
|--------------------------------------------------------------------------
*/

$statusDescription =
    match ($currentStatus) {

        'Pending' =>
            'Your order is waiting for administrator approval.',

        'Approved' =>
            'Your order has been approved and is waiting for delivery assignment.',

        'Assigned' =>
            'A driver and truck have been assigned to your order.',

        'Accepted' =>
            'The assigned driver has accepted the delivery.',

        'In Transit' =>
            'Your order is currently on the way to the delivery location.',

        'Arrived' =>
            'The delivery vehicle has arrived at the delivery location.',

        'Completed',
        'Delivered' =>
            'Your order has been successfully delivered.',

        'Cancelled' =>
            'This order has been cancelled.',

        'Failed' =>
            'The delivery could not be completed.',

        default =>
            'Your order is being processed.'
    };


/*
|--------------------------------------------------------------------------
| Dates
|--------------------------------------------------------------------------
*/

$orderDate = '—';

if (!empty($order['order_date'])) {

    $timestamp =
        strtotime(
            $order['order_date']
        );


    if ($timestamp !== false) {

        $orderDate =
            date(
                'F d, Y · h:i A',
                $timestamp
            );
    }
}


$expectedDeliveryDate = '—';

if (
    !empty(
        $order[
            'expected_delivery_date'
        ]
    )
) {

    $timestamp =
        strtotime(
            $order[
                'expected_delivery_date'
            ]
        );


    if ($timestamp !== false) {

        $expectedDeliveryDate =
            date(
                'F d, Y',
                $timestamp
            );
    }
}


/*
|--------------------------------------------------------------------------
| Delivery Address
|--------------------------------------------------------------------------
*/

$fullAddress =
    trim(
        (string) (
            $deliveryLocation[
                'full_address'
            ]
            ?? ''
        )
    );


$city =
    trim(
        (string) (
            $deliveryLocation[
                'city'
            ]
            ?? ''
        )
    );


$province =
    trim(
        (string) (
            $deliveryLocation[
                'province'
            ]
            ?? ''
        )
    );


$contactPerson =
    trim(
        (string) (
            $deliveryLocation[
                'contact_person'
            ]
            ?? ''
        )
    );


$contactNumber =
    trim(
        (string) (
            $deliveryLocation[
                'contact_number'
            ]
            ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| Latest GPS
|--------------------------------------------------------------------------
*/

$latestLatitude = null;
$latestLongitude = null;


if ($latestStatus) {

    if (
        $latestStatus['latitude'] !== null
        &&
        $latestStatus['longitude'] !== null
    ) {

        $latestLatitude =
            (float)
            $latestStatus['latitude'];


        $latestLongitude =
            (float)
            $latestStatus['longitude'];
    }
}


/*
|--------------------------------------------------------------------------
| Feedback
|--------------------------------------------------------------------------
*/

$isDelivered =
    $databaseOrderStatus === 'Completed'
    ||
    $databaseOrderStatus === 'Delivered'
    ||
    $currentStatus === 'Completed'
    ||
    $currentStatus === 'Delivered';


$feedbackUrl =
    BASE_URL .
    'customer/feedback.php?order_id=' .
    $orderId;


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'Order Details';


$navItems = [

    [
        'label' => 'Dashboard',
        'url'   =>
            BASE_URL .
            'customer/dashboard.php'
    ],

    [
        'label' => 'Products',
        'url'   =>
            BASE_URL .
            'customer/products.php'
    ],

    [
        'label' => 'Cart',
        'url'   =>
            BASE_URL .
            'customer/cart.php'
    ],

    [
        'label' => 'My Orders',
        'url'   =>
            BASE_URL .
            'customer/order_history.php'
    ],

    [
        'label' => 'Notifications',
        'url'   =>
            BASE_URL .
            'customer/notifications.php'
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
        Order <?= e($order['order_number']) ?> · Hong Sing LKRR Commercial
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        .order-details-page {
            max-width: 1150px;
            margin: 0 auto;
        }

        .details-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        .details-header h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }

        .details-header p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .action-primary {
            background: var(--color-accent);
            color: #ffffff;
        }

        .action-secondary {
            border: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text-primary);
        }

        .action-success {
            background: #059669;
            color: #ffffff;
        }

        /*
        |--------------------------------------------------------------------------
        | Current Status
        |--------------------------------------------------------------------------
        */

        .current-status-card {
            padding: 22px;
            margin-bottom: 20px;
        }

        .current-status-layout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .small-label {
            margin-bottom: 5px;
            color: var(--color-text-muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .current-status-title {
            margin-bottom: 5px;
            font-size: 24px;
            font-weight: 800;
        }

        .current-status-description {
            max-width: 650px;
            color: var(--color-text-secondary);
            font-size: 13px;
            line-height: 1.5;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.12);
            color: #b45309;
        }

        .status-approved {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
        }

        .status-transit {
            background: rgba(14, 165, 233, 0.12);
            color: #0284c7;
        }

        .status-delivered {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
        }

        /*
        |--------------------------------------------------------------------------
        | Main Grid
        |--------------------------------------------------------------------------
        */

        .details-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            gap: 20px;
            align-items: start;
        }

        .details-card {
            padding: 20px;
            margin-bottom: 18px;
        }

        .details-card h2 {
            margin: 0 0 5px;
            font-size: 17px;
        }

        .card-description {
            margin: 0 0 17px;
            color: var(--color-text-secondary);
            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Order Items
        |--------------------------------------------------------------------------
        */

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .item-row {
            display: grid;
            grid-template-columns: 55px minmax(0, 1fr) auto;
            align-items: center;
            gap: 13px;
            padding: 12px;
            border: 1px solid var(--color-border);
            border-radius: 9px;
        }

        .item-image {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 8px;
            background: var(--color-background);
            border: 1px solid var(--color-border);
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-placeholder {
            font-size: 22px;
        }

        .item-name {
            margin-bottom: 3px;
            font-size: 13px;
            font-weight: 700;
        }

        .item-code {
            margin-bottom: 5px;
            color: var(--color-text-muted);
            font-size: 10px;
        }

        .item-meta {
            color: var(--color-text-secondary);
            font-size: 11px;
        }

        .item-price {
            text-align: right;
            white-space: nowrap;
        }

        .item-subtotal {
            font-size: 13px;
            font-weight: 800;
        }

        .item-unit-price {
            margin-top: 3px;
            color: var(--color-text-muted);
            font-size: 10px;
        }

        /*
        |--------------------------------------------------------------------------
        | Information Rows
        |--------------------------------------------------------------------------
        */

        .info-list {
            display: flex;
            flex-direction: column;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding: 11px 0;
            border-bottom: 1px solid var(--color-border);
        }

        .info-row:last-child {
            border-bottom: 0;
        }

        .info-label {
            color: var(--color-text-secondary);
            font-size: 11px;
        }

        .info-value {
            text-align: right;
            font-size: 12px;
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | Address
        |--------------------------------------------------------------------------
        */

        .address-box {
            padding: 14px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            line-height: 1.55;
        }

        .address-main {
            font-size: 13px;
            font-weight: 700;
        }

        .address-secondary {
            margin-top: 4px;
            color: var(--color-text-secondary);
            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Driver
        |--------------------------------------------------------------------------
        */

        .driver-box {
            padding: 14px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
        }

        .driver-name {
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 800;
        }

        .driver-detail {
            margin-top: 4px;
            color: var(--color-text-secondary);
            font-size: 11px;
        }

        /*
        |--------------------------------------------------------------------------
        | Timeline
        |--------------------------------------------------------------------------
        */

        .timeline {
            position: relative;
        }

        .timeline-item {
            position: relative;
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr);
            gap: 12px;
            min-height: 72px;
        }

        .timeline-line {
            position: absolute;
            left: 12px;
            top: 27px;
            bottom: -4px;
            width: 2px;
            background: var(--color-border);
        }

        .timeline-item:last-child .timeline-line {
            display: none;
        }

        .timeline-dot {
            position: relative;
            z-index: 2;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--color-border);
            border-radius: 50%;
            background: var(--color-surface);
            color: var(--color-text-muted);
            font-size: 10px;
            font-weight: 700;
        }

        .timeline-dot.active {
            background: var(--color-accent);
            border-color: var(--color-accent);
            color: #ffffff;
        }

        .timeline-content {
            padding-bottom: 16px;
        }

        .timeline-title {
            font-size: 12px;
            font-weight: 800;
        }

        .timeline-time {
            margin-top: 3px;
            color: var(--color-text-muted);
            font-size: 10px;
        }

        .timeline-notes {
            margin-top: 5px;
            color: var(--color-text-secondary);
            font-size: 11px;
            line-height: 1.45;
        }

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        .summary-card {
            position: sticky;
            top: 20px;
            padding: 20px;
        }

        .summary-card h2 {
            margin: 0 0 14px;
            font-size: 17px;
        }

        .summary-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid var(--color-border);
        }

        .summary-label {
            color: var(--color-text-secondary);
            font-size: 11px;
        }

        .summary-value {
            text-align: right;
            font-size: 12px;
            font-weight: 700;
        }

        .summary-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding-top: 15px;
            margin-top: 4px;
        }

        .summary-total-label {
            font-size: 13px;
            font-weight: 700;
        }

        .summary-total-value {
            font-size: 20px;
            font-weight: 800;
        }

        .summary-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 18px;
        }

        .summary-action {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 850px) {

            .details-grid {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }

        }

        @media (max-width: 650px) {

            .details-header {
                flex-direction: column;
            }

            .header-actions {
                width: 100%;
            }

            .action-button {
                flex: 1;
            }

            .current-status-layout {
                flex-direction: column;
                align-items: flex-start;
            }

            .item-row {
                grid-template-columns: 50px minmax(0, 1fr);
            }

            .item-price {
                grid-column: 2;
                text-align: left;
            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/header.php'; ?>

    <main class="app-main">

        <div class="order-details-page">

            <!-- Header -->

            <div class="details-header">

                <div>

                    <h1>
                        Order Details
                    </h1>

                    <p>
                        Order #
                        <strong>
                            <?= e($order['order_number']) ?>
                        </strong>
                    </p>

                </div>

                <div class="header-actions">

                    <a
                        href="<?= BASE_URL ?>customer/order_history.php"
                        class="action-button action-secondary"
                    >
                        ← My Orders
                    </a>

                    <?php if (
                        !in_array(
                            $databaseOrderStatus,
                            ['Cancelled', 'Failed'],
                            true
                        )
                    ): ?>

                        <a
                            href="<?= BASE_URL ?>customer/track_delivery.php?order_id=<?= $orderId ?>"
                            class="action-button action-primary"
                        >
                            Track Delivery
                        </a>

                    <?php endif; ?>

                    <?php if ($isDelivered): ?>

                        <a
                            href="<?= e($feedbackUrl) ?>"
                            class="action-button action-success"
                        >
                            Give Feedback
                        </a>

                    <?php endif; ?>

                </div>

            </div>

            <!-- Current Status -->

            <div class="card current-status-card">

                <div class="current-status-layout">

                    <div>

                        <div class="small-label">
                            Current Order Status
                        </div>

                        <div class="current-status-title">
                            <?= e($currentDisplayStatus) ?>
                        </div>

                        <div class="current-status-description">
                            <?= e($statusDescription) ?>
                        </div>

                    </div>

                    <span
                        class="status-pill <?= e(
                            orderStatusClass($currentStatus)
                        ) ?>"
                    >
                        <?= e($currentDisplayStatus) ?>
                    </span>

                </div>

            </div>

            <div class="details-grid">

                <!-- Main -->

                <section>

                    <!-- Order Items -->

                    <div class="card details-card">

                        <h2>
                            Order Items
                        </h2>

                        <p class="card-description">
                            Products included in this order.
                        </p>

                        <?php if (empty($orderItems)): ?>

                            <p
                                style="
                                    color: var(--color-text-secondary);
                                    font-size: 12px;
                                "
                            >
                                No order items found.
                            </p>

                        <?php else: ?>

                            <div class="items-list">

                                <?php foreach ($orderItems as $item): ?>

                                    <?php

                                    $productName =
                                        trim(
                                            (string) (
                                                $item['product_name']
                                                ?? 'Product'
                                            )
                                        );

                                    $productCode =
                                        trim(
                                            (string) (
                                                $item['product_code']
                                                ?? ''
                                            )
                                        );

                                    $quantity =
                                        (int) $item['quantity'];

                                    $unitPrice =
                                        (float) $item['unit_price'];

                                    $subtotal =
                                        (float) $item['subtotal'];

                                    $image =
                                        trim(
                                            (string) (
                                                $item['product_image']
                                                ?? ''
                                            )
                                        );

                                    ?>

                                    <div class="item-row">

                                        <div class="item-image">

                                            <?php if ($image !== ''): ?>

                                                <img
                                                    src="<?= BASE_URL ?>assets/uploads/products/<?= e($image) ?>"
                                                    alt="<?= e($productName) ?>"
                                                >

                                            <?php else: ?>

                                                <span class="item-placeholder">
                                                    📦
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                        <div>

                                            <div class="item-name">
                                                <?= e($productName) ?>
                                            </div>

                                            <?php if ($productCode !== ''): ?>

                                                <div class="item-code">
                                                    Code:
                                                    <?= e($productCode) ?>
                                                </div>

                                            <?php endif; ?>

                                            <div class="item-meta">

                                                Quantity:
                                                <?= $quantity ?>

                                                <?php if (
                                                    $item['unit_weight_kg'] !== null
                                                ): ?>

                                                    ·
                                                    <?= number_format(
                                                        (float) $item['unit_weight_kg'],
                                                        2
                                                    ) ?>
                                                    kg/unit

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                        <div class="item-price">

                                            <div class="item-subtotal">
                                                ₱<?= number_format(
                                                    $subtotal,
                                                    2
                                                ) ?>
                                            </div>

                                            <div class="item-unit-price">
                                                ₱<?= number_format(
                                                    $unitPrice,
                                                    2
                                                ) ?>
                                                each
                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- Delivery Information -->

                    <div class="card details-card">

                        <h2>
                            Delivery Information
                        </h2>

                        <p class="card-description">
                            Address and contact information for this order.
                        </p>

                        <?php if (
                            $fullAddress !== ''
                            || $city !== ''
                            || $province !== ''
                        ): ?>

                            <div class="address-box">

                                <div class="address-main">
                                    <?= e($fullAddress ?: 'Delivery address') ?>
                                </div>

                                <?php if (
                                    $city !== ''
                                    || $province !== ''
                                ): ?>

                                    <div class="address-secondary">

                                        <?= e($city) ?>

                                        <?php if (
                                            $city !== ''
                                            && $province !== ''
                                        ): ?>
                                            ,
                                        <?php endif; ?>

                                        <?= e($province) ?>

                                    </div>

                                <?php endif; ?>

                                <?php if ($contactPerson !== ''): ?>

                                    <div class="address-secondary">

                                        Contact Person:
                                        <?= e($contactPerson) ?>

                                    </div>

                                <?php endif; ?>

                                <?php if ($contactNumber !== ''): ?>

                                    <div class="address-secondary">

                                        Contact Number:
                                        <?= e($contactNumber) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php else: ?>

                            <div class="address-box">

                                <div class="address-main">
                                    Delivery address not available.
                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- Driver -->

                    <div class="card details-card">

                        <h2>
                            Delivery Assignment
                        </h2>

                        <p class="card-description">
                            Driver and vehicle assigned to your order.
                        </p>

                        <?php if ($driver): ?>

                            <div class="driver-box">

                                <div class="driver-name">
                                    <?= e($driver['full_name']) ?>
                                </div>

                                <?php if (
                                    !empty($driver['phone_number'])
                                ): ?>

                                    <div class="driver-detail">

                                        Phone:
                                        <?= e($driver['phone_number']) ?>

                                    </div>

                                <?php endif; ?>

                                <?php if ($truck): ?>

                                    <div class="driver-detail">

                                        Vehicle:
                                        <?= e($truck['model'] ?? 'Truck') ?>

                                    </div>

                                    <div class="driver-detail">

                                        Plate Number:
                                        <?= e($truck['plate_number'] ?? '—') ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php else: ?>

                            <div class="driver-box">

                                <div class="driver-name">
                                    Driver not assigned yet.
                                </div>

                                <div class="driver-detail">
                                    You will see the driver information here
                                    once your order has been assigned.
                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- Status History -->

                    <div class="card details-card">

                        <h2>
                            Delivery History
                        </h2>

                        <p class="card-description">
                            Recorded status updates for this order.
                        </p>

                        <?php if (empty($statusHistory)): ?>

                            <p
                                style="
                                    color: var(--color-text-secondary);
                                    font-size: 12px;
                                "
                            >
                                No delivery status updates have been recorded yet.
                            </p>

                        <?php else: ?>

                            <div class="timeline">

                                <?php foreach (
                                    $statusHistory
                                    as $index => $status
                                ): ?>

                                    <?php

                                    $historyStatus =
                                        (string) (
                                            $status['status']
                                            ?? 'Pending'
                                        );

                                    $historyDisplayStatus =
                                        displayOrderStatus(
                                            $historyStatus
                                        );

                                    $historyTime = '—';

                                    if (!empty($status['timestamp'])) {

                                        $timestamp =
                                            strtotime(
                                                $status['timestamp']
                                            );

                                        if ($timestamp !== false) {

                                            $historyTime =
                                                date(
                                                    'M d, Y · h:i A',
                                                    $timestamp
                                                );
                                        }
                                    }

                                    ?>

                                    <div class="timeline-item">

                                        <?php if (
                                            $index <
                                            count($statusHistory) - 1
                                        ): ?>

                                            <div class="timeline-line"></div>

                                        <?php endif; ?>

                                        <div
                                            class="timeline-dot active"
                                        >
                                            ✓
                                        </div>

                                        <div class="timeline-content">

                                            <div class="timeline-title">
                                                <?= e($historyDisplayStatus) ?>
                                            </div>

                                            <div class="timeline-time">
                                                <?= e($historyTime) ?>
                                            </div>

                                            <?php if (
                                                !empty($status['notes'])
                                            ): ?>

                                                <div class="timeline-notes">
                                                    <?= e($status['notes']) ?>
                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </section>

                <!-- Sidebar -->

                <aside>

                    <div class="card summary-card">

                        <h2>
                            Order Summary
                        </h2>

                        <div class="summary-row">

                            <span class="summary-label">
                                Order Number
                            </span>

                            <span class="summary-value">
                                <?= e($order['order_number']) ?>
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Order Date
                            </span>

                            <span class="summary-value">
                                <?= e($orderDate) ?>
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Expected Delivery
                            </span>

                            <span class="summary-value">
                                <?= e($expectedDeliveryDate) ?>
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Priority
                            </span>

                            <span class="summary-value">
                                <?= e(
                                    $order['priority_level']
                                    ?? 'Normal'
                                ) ?>
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Total Items
                            </span>

                            <span class="summary-value">
                                <?= $totalItems ?>
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Total Weight
                            </span>

                            <span class="summary-value">
                                <?= number_format(
                                    $totalWeight,
                                    2
                                ) ?>
                                kg
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Status
                            </span>

                            <span class="summary-value">

                                <span
                                    class="status-pill <?= e(
                                        orderStatusClass(
                                            $currentStatus
                                        )
                                    ) ?>"
                                >
                                    <?= e($currentDisplayStatus) ?>
                                </span>

                            </span>

                        </div>

                        <div class="summary-total">

                            <span class="summary-total-label">
                                Total Amount
                            </span>

                            <span class="summary-total-value">
                                ₱<?= number_format(
                                    $totalAmount,
                                    2
                                ) ?>
                            </span>

                        </div>

                        <div class="summary-actions">

                            <?php if (
                                !in_array(
                                    $databaseOrderStatus,
                                    ['Cancelled', 'Failed'],
                                    true
                                )
                            ): ?>

                                <a
                                    href="<?= BASE_URL ?>customer/track_delivery.php?order_id=<?= $orderId ?>"
                                    class="summary-action action-primary"
                                >
                                    Track Delivery
                                </a>

                            <?php endif; ?>

                            <?php if ($isDelivered): ?>

                                <a
                                    href="<?= e($feedbackUrl) ?>"
                                    class="summary-action action-success"
                                >
                                    Give Feedback
                                </a>

                            <?php endif; ?>

                            <a
                                href="<?= BASE_URL ?>customer/order_history.php"
                                class="summary-action action-secondary"
                            >
                                Back to My Orders
                            </a>

                        </div>

                    </div>

                </aside>

            </div>

        </div>

    </main>

</div>

</body>

</html>