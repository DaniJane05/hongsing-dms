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
*/

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function displayTrackingStatus(string $status): string
{
    return match ($status) {
        'Completed' => 'Delivered',
        default     => $status !== '' ? $status : 'Pending',
    };
}

function trackingStatusClass(string $status): string
{
    return match ($status) {
        'Pending'                 => 'status-pending',

        'Approved',
        'Assigned',
        'Accepted'                => 'status-approved',

        'In Transit',
        'Arrived'                 => 'status-transit',

        'Completed',
        'Delivered'               => 'status-delivered',

        'Cancelled',
        'Failed'                  => 'status-failed',

        default                   => 'status-pending',
    };
}

/*
|--------------------------------------------------------------------------
| Order ID
|--------------------------------------------------------------------------
|
| If order_id isn't provided, show the customer's latest active order.
|
*/

$orderId = 0;

if (isset($_GET['order_id'])) {
    $orderId = (int) $_GET['order_id'];
} elseif (isset($_GET['id'])) {
    $orderId = (int) $_GET['id'];
}

/*
|--------------------------------------------------------------------------
| Latest Active Order
|--------------------------------------------------------------------------
*/

if ($orderId <= 0) {

    $latestOrderStmt = $db->prepare("
        SELECT
            order_id

        FROM `order`

        WHERE customer_id = ?

          AND completion_status NOT IN (
              'Completed',
              'Cancelled',
              'Failed'
          )

        ORDER BY
            created_at DESC,
            order_id DESC

        LIMIT 1
    ");

    $latestOrderStmt->execute([
        $customerId
    ]);

    $latestOrder =
        $latestOrderStmt->fetch(PDO::FETCH_ASSOC);

    if ($latestOrder) {

        $orderId =
            (int) $latestOrder['order_id'];

    } else {

        header(
            'Location: ' .
            BASE_URL .
            'customer/order_history.php'
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Get Order
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

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

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
    $locationStmt->fetch(PDO::FETCH_ASSOC);

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

    ORDER BY timestamp ASC, status_id ASC
");

$statusStmt->execute([
    $orderId
]);

$statusHistory =
    $statusStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Latest Status
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
        $driverStmt->fetch(PDO::FETCH_ASSOC);
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
        $truckStmt->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Determine Current Status
|--------------------------------------------------------------------------
|
| The order table and delivery_status table can contain different
| stages. We use whichever has progressed farther.
|
*/

$orderStatus =
    (string) (
        $order['completion_status']
        ?? 'Pending'
    );

$deliveryStatus =
    $latestStatus['status']
    ?? null;

$statusRank = [
    'Pending'    => 0,
    'Approved'   => 1,
    'Assigned'   => 2,
    'Accepted'   => 2,
    'In Transit' => 3,
    'Arrived'    => 4,
    'Completed'  => 5,
    'Delivered'  => 5,
    'Cancelled'  => 6,
    'Failed'     => 6,
];

$currentStatus = $orderStatus;

if ($deliveryStatus !== null) {

    $orderRank =
        $statusRank[$orderStatus]
        ?? 0;

    $deliveryRank =
        $statusRank[$deliveryStatus]
        ?? 0;

    if ($deliveryRank >= $orderRank) {
        $currentStatus = $deliveryStatus;
    }
}

$displayStatus =
    displayTrackingStatus($currentStatus);

/*
|--------------------------------------------------------------------------
| Status Description
|--------------------------------------------------------------------------
*/

$statusDescription = match ($currentStatus) {

    'Pending' =>
        'Your order is waiting for administrator approval.',

    'Approved' =>
        'Your order has been approved and is waiting for driver assignment.',

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
        'Your order has been delivered successfully.',

    'Cancelled' =>
        'This delivery has been cancelled.',

    'Failed' =>
        'The delivery could not be completed. Please check the latest update.',

    default =>
        'Your delivery is being processed.',
};

/*
|--------------------------------------------------------------------------
| Tracking Flow
|--------------------------------------------------------------------------
*/

$trackingFlow = [
    'Pending',
    'Approved',
    'Assigned',
    'In Transit',
    'Arrived',
    'Delivered'
];

/*
|--------------------------------------------------------------------------
| Normalize Status For Flow
|--------------------------------------------------------------------------
*/

$flowStatus = $currentStatus;

if ($flowStatus === 'Accepted') {
    $flowStatus = 'Assigned';
}

if ($flowStatus === 'Completed') {
    $flowStatus = 'Delivered';
}

/*
|--------------------------------------------------------------------------
| Find Current Flow Index
|--------------------------------------------------------------------------
*/

$currentFlowIndex = 0;

foreach (
    $trackingFlow as $index => $flow
) {

    if ($flow === $flowStatus) {

        $currentFlowIndex = $index;

        break;
    }
}

/*
|--------------------------------------------------------------------------
| Special Statuses
|--------------------------------------------------------------------------
*/

$isCancelled =
    $currentStatus === 'Cancelled';

$isFailed =
    $currentStatus === 'Failed';

$isFinished =
    $currentStatus === 'Completed'
    || $currentStatus === 'Delivered';

/*
|--------------------------------------------------------------------------
| Latest GPS Coordinates
|--------------------------------------------------------------------------
*/

$latestLatitude = null;
$latestLongitude = null;

if ($latestStatus) {

    if (
        $latestStatus['latitude'] !== null &&
        $latestStatus['longitude'] !== null
    ) {

        $latestLatitude =
            (float) $latestStatus['latitude'];

        $latestLongitude =
            (float) $latestStatus['longitude'];
    }
}

/*
|--------------------------------------------------------------------------
| Fallback To Delivery Location
|--------------------------------------------------------------------------
*/

if (
    $latestLatitude === null
    && $deliveryLocation
    && $deliveryLocation['latitude'] !== null
    && $deliveryLocation['longitude'] !== null
) {

    $latestLatitude =
        (float) $deliveryLocation['latitude'];

    $latestLongitude =
        (float) $deliveryLocation['longitude'];
}

/*
|--------------------------------------------------------------------------
| Latest Update
|--------------------------------------------------------------------------
*/

$latestUpdate = 'No tracking update yet.';

if ($latestStatus && !empty($latestStatus['timestamp'])) {

    $timestamp =
        strtotime(
            $latestStatus['timestamp']
        );

    if ($timestamp !== false) {

        $latestUpdate =
            date(
                'M d, Y · h:i A',
                $timestamp
            );
    }
}

/*
|--------------------------------------------------------------------------
| Expected Delivery
|--------------------------------------------------------------------------
*/

$expectedDelivery = '—';

if (!empty($order['expected_delivery_date'])) {

    $timestamp =
        strtotime(
            $order['expected_delivery_date']
        );

    if ($timestamp !== false) {

        $expectedDelivery =
            date(
                'F d, Y',
                $timestamp
            );
    }
}

/*
|--------------------------------------------------------------------------
| Address
|--------------------------------------------------------------------------
*/

$fullAddress =
    trim(
        (string) (
            $deliveryLocation['full_address']
            ?? ''
        )
    );

$city =
    trim(
        (string) (
            $deliveryLocation['city']
            ?? ''
        )
    );

$province =
    trim(
        (string) (
            $deliveryLocation['province']
            ?? ''
        )
    );

/*
|--------------------------------------------------------------------------
| Total
|--------------------------------------------------------------------------
*/

$totalWeight = (float) (
    $order['total_weight_kg']
    ?? $order['total_weight']
    ?? 0
);

$totalAmount =
    (float) (
        $order['total_amount']
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'Track Delivery';

$navItems = [
    [
        'label' => 'Dashboard',
        'url'   => BASE_URL . 'customer/dashboard.php'
    ],
    [
        'label' => 'Products',
        'url'   => BASE_URL . 'customer/products.php'
    ],
    [
        'label' => 'Cart',
        'url'   => BASE_URL . 'customer/cart.php'
    ],
    [
        'label' => 'My Orders',
        'url'   => BASE_URL . 'customer/order_history.php'
    ],
    [
        'label' => 'Notifications',
        'url'   => BASE_URL . 'customer/notifications.php'
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
        Track Delivery · <?= e($order['order_number']) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <?php if (
        $latestLatitude !== null &&
        $latestLongitude !== null
    ): ?>

        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        >

    <?php endif; ?>

    <style>

        .tracking-page {
            max-width: 1150px;
            margin: 0 auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .tracking-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        .tracking-header h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }

        .tracking-header p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 13px;
        }

        .tracking-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .header-button {
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

        .header-button.primary {
            background: var(--color-accent);
            color: #ffffff;
        }

        .header-button.secondary {
            border: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text-primary);
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

        .status-label {
            margin-bottom: 5px;
            color: var(--color-text-muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .current-status {
            margin-bottom: 6px;
            font-size: 25px;
            font-weight: 800;
        }

        .status-description {
            max-width: 700px;
            color: var(--color-text-secondary);
            font-size: 13px;
            line-height: 1.5;
        }

        .update-time {
            margin-top: 8px;
            color: var(--color-text-muted);
            font-size: 10px;
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
        | Flow
        |--------------------------------------------------------------------------
        */

        .flow-card {
            padding: 20px;
            margin-bottom: 20px;
        }

        .flow-card h2 {
            margin: 0 0 5px;
            font-size: 17px;
        }

        .flow-description {
            margin: 0 0 20px;
            color: var(--color-text-secondary);
            font-size: 12px;
        }

        .tracking-flow {
            display: flex;
            align-items: flex-start;
            width: 100%;
        }

        .flow-step {
            position: relative;
            flex: 1;
            min-width: 0;
            text-align: center;
        }

        .flow-step:not(:last-child)::after {
            content: '';
            position: absolute;
            z-index: 0;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: var(--color-border);
        }

        .flow-step.completed:not(:last-child)::after {
            background: var(--color-accent);
        }

        .flow-dot {
            position: relative;
            z-index: 2;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            border: 2px solid var(--color-border);
            border-radius: 50%;
            background: var(--color-surface);
            color: var(--color-text-muted);
            font-size: 11px;
            font-weight: 700;
        }

        .flow-step.completed .flow-dot {
            background: var(--color-accent);
            border-color: var(--color-accent);
            color: #ffffff;
        }

        .flow-step.current .flow-dot {
            box-shadow:
                0 0 0 5px rgba(59, 130, 246, 0.10);
        }

        .flow-label {
            color: var(--color-text-secondary);
            font-size: 10px;
            font-weight: 600;
        }

        .flow-step.completed .flow-label {
            color: var(--color-text-primary);
        }

        /*
        |--------------------------------------------------------------------------
        | Special Status
        |--------------------------------------------------------------------------
        */

        .special-status {
            padding: 17px;
            margin-bottom: 20px;
            border: 1px solid var(--color-border);
            border-radius: 9px;
            background: var(--color-surface);
        }

        .special-status-title {
            margin-bottom: 4px;
            font-size: 14px;
            font-weight: 800;
        }

        .special-status-text {
            color: var(--color-text-secondary);
            font-size: 12px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Grid
        |--------------------------------------------------------------------------
        */

        .tracking-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            gap: 20px;
            align-items: start;
        }

        .tracking-card {
            padding: 20px;
            margin-bottom: 18px;
        }

        .tracking-card h2 {
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
        | Map
        |--------------------------------------------------------------------------
        */

        #tracking-map {
            width: 100%;
            height: 410px;
            overflow: hidden;
            border-radius: 9px;
            border: 1px solid var(--color-border);
        }

        .map-placeholder {
            min-height: 280px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 25px;
            text-align: center;
            border: 1px dashed var(--color-border);
            border-radius: 9px;
            color: var(--color-text-secondary);
        }

        .map-placeholder-icon {
            margin-bottom: 10px;
            font-size: 38px;
        }

        .map-placeholder strong {
            display: block;
            margin-bottom: 5px;
            color: var(--color-text-primary);
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
            font-size: 11px;
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
            justify-content: space-between;
            align-items: flex-start;
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
            justify-content: space-between;
            align-items: center;
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

        .summary-primary {
            background: var(--color-accent);
            color: #ffffff;
        }

        .summary-secondary {
            border: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text-primary);
        }

        .summary-success {
            background: #059669;
            color: #ffffff;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 850px) {

            .tracking-grid {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }

        }

        @media (max-width: 700px) {

            .tracking-header {
                flex-direction: column;
            }

            .tracking-header-actions {
                width: 100%;
            }

            .header-button {
                flex: 1;
            }

            .current-status-layout {
                flex-direction: column;
                align-items: flex-start;
            }

            .tracking-flow {
                overflow-x: auto;
                padding-bottom: 10px;
            }

            .flow-step {
                flex: 0 0 105px;
            }

            #tracking-map {
                height: 320px;
            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/header.php'; ?>

    <main class="app-main">

        <div class="tracking-page">

            <!-- Header -->

            <div class="tracking-header">

                <div>

                    <h1>
                        Track Delivery
                    </h1>

                    <p>
                        Order #
                        <strong>
                            <?= e($order['order_number']) ?>
                        </strong>
                    </p>

                </div>

                <div class="tracking-header-actions">

                    <a
                        href="<?= BASE_URL ?>customer/order_details.php?order_id=<?= $orderId ?>"
                        class="header-button primary"
                    >
                        Order Details
                    </a>

                    <a
                        href="<?= BASE_URL ?>customer/order_history.php"
                        class="header-button secondary"
                    >
                        My Orders
                    </a>

                </div>

            </div>

            <!-- Current Status -->

            <div class="card current-status-card">

                <div class="current-status-layout">

                    <div>

                        <div class="status-label">
                            Current Delivery Status
                        </div>

                        <div class="current-status">
                            <?= e($displayStatus) ?>
                        </div>

                        <div class="status-description">
                            <?= e($statusDescription) ?>
                        </div>

                        <div class="update-time">
                            Last update:
                            <?= e($latestUpdate) ?>
                        </div>

                    </div>

                    <span
                        class="status-pill <?= e(
                            trackingStatusClass(
                                $currentStatus
                            )
                        ) ?>"
                    >
                        <?= e($displayStatus) ?>
                    </span>

                </div>

            </div>

            <!-- Special Cancelled / Failed -->

            <?php if ($isCancelled): ?>

                <div class="special-status">

                    <div class="special-status-title">
                        Order Cancelled
                    </div>

                    <div class="special-status-text">
                        This order has been cancelled and is no longer
                        progressing through delivery.
                    </div>

                </div>

            <?php elseif ($isFailed): ?>

                <div class="special-status">

                    <div class="special-status-title">
                        Delivery Failed
                    </div>

                    <div class="special-status-text">

                        The delivery could not be completed.
                        Please review the latest tracking note below
                        or contact the store if you need assistance.

                    </div>

                </div>

            <?php endif; ?>

            <!-- Progress -->

            <?php if (!$isCancelled && !$isFailed): ?>

                <div class="card flow-card">

                    <h2>
                        Delivery Progress
                    </h2>

                    <p class="flow-description">
                        Follow your order from approval to successful delivery.
                    </p>

                    <div class="tracking-flow">

                        <?php foreach (
                            $trackingFlow
                            as $index => $flow
                        ): ?>

                            <?php

                            $isCompleted =
                                $index <= $currentFlowIndex;

                            $isCurrent =
                                $index === $currentFlowIndex;

                            ?>

                            <div
                                class="
                                    flow-step
                                    <?= $isCompleted ? 'completed' : '' ?>
                                    <?= $isCurrent ? 'current' : '' ?>
                                "
                            >

                                <div class="flow-dot">

                                    <?php if ($isCompleted): ?>

                                        ✓

                                    <?php else: ?>

                                        <?= $index + 1 ?>

                                    <?php endif; ?>

                                </div>

                                <div class="flow-label">

                                    <?= e(
                                        $flow
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

            <div class="tracking-grid">

                <!-- Main -->

                <section>

                    <!-- Map -->

                    <div class="card tracking-card">

                        <h2>
                            Delivery Location
                        </h2>

                        <p class="card-description">
                            Latest location recorded by the delivery system.
                        </p>

                        <?php if (
                            $latestLatitude !== null &&
                            $latestLongitude !== null
                        ): ?>

                            <div id="tracking-map"></div>

                        <?php else: ?>

                            <div class="map-placeholder">

                                <div class="map-placeholder-icon">
                                    📍
                                </div>

                                <strong>
                                    No GPS location available yet
                                </strong>

                                <span>
                                    The delivery map will display a location
                                    once GPS coordinates are recorded.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- Delivery Address -->

                    <div class="card tracking-card">

                        <h2>
                            Delivery Address
                        </h2>

                        <p class="card-description">
                            Destination for this order.
                        </p>

                        <div class="address-box">

                            <div class="address-main">

                                <?= e(
                                    $fullAddress
                                    ?: 'Delivery address not available.'
                                ) ?>

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

                        </div>

                    </div>

                    <!-- Tracking History -->

                    <div class="card tracking-card">

                        <h2>
                            Tracking History
                        </h2>

                        <p class="card-description">
                            Recorded updates for this delivery.
                        </p>

                        <?php if (empty($statusHistory)): ?>

                            <p
                                style="
                                    color: var(--color-text-secondary);
                                    font-size: 12px;
                                "
                            >
                                No tracking updates have been recorded yet.
                            </p>

                        <?php else: ?>

                            <div class="timeline">

                                <?php foreach (
                                    $statusHistory
                                    as $index => $history
                                ): ?>

                                    <?php

                                    $historyStatus =
                                        (string) (
                                            $history['status']
                                            ?? 'Pending'
                                        );

                                    $historyDisplay =
                                        displayTrackingStatus(
                                            $historyStatus
                                        );

                                    $historyTime = '—';

                                    if (
                                        !empty(
                                            $history['timestamp']
                                        )
                                    ) {

                                        $timestamp =
                                            strtotime(
                                                $history['timestamp']
                                            );

                                        if (
                                            $timestamp !== false
                                        ) {

                                            $historyTime =
                                                date(
                                                    'M d, Y · h:i A',
                                                    $timestamp
                                                );
                                        }
                                    }

                                    $isLatest =
                                        $index ===
                                        count($statusHistory) - 1;

                                    ?>

                                    <div class="timeline-item">

                                        <?php if (
                                            $index <
                                            count($statusHistory) - 1
                                        ): ?>

                                            <div class="timeline-line"></div>

                                        <?php endif; ?>

                                        <div
                                            class="
                                                timeline-dot
                                                <?= $isLatest ? 'active' : '' ?>
                                            "
                                        >

                                            <?php if ($isLatest): ?>

                                                ●

                                            <?php else: ?>

                                                ✓

                                            <?php endif; ?>

                                        </div>

                                        <div class="timeline-content">

                                            <div class="timeline-title">
                                                <?= e($historyDisplay) ?>
                                            </div>

                                            <div class="timeline-time">
                                                <?= e($historyTime) ?>
                                            </div>

                                            <?php if (
                                                !empty(
                                                    $history['notes']
                                                )
                                            ): ?>

                                                <div class="timeline-notes">

                                                    <?= e(
                                                        $history['notes']
                                                    ) ?>

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

                    <!-- Driver -->

                    <div class="card tracking-card">

                        <h2>
                            Delivery Driver
                        </h2>

                        <p class="card-description">
                            Driver assigned to your order.
                        </p>

                        <?php if ($driver): ?>

                            <div class="driver-box">

                                <div class="driver-name">
                                    <?= e($driver['full_name']) ?>
                                </div>

                                <?php if (
                                    !empty(
                                        $driver['phone_number']
                                    )
                                ): ?>

                                    <div class="driver-detail">

                                        Phone:
                                        <?= e(
                                            $driver['phone_number']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                                <?php if ($truck): ?>

                                    <div class="driver-detail">

                                        Vehicle:
                                        <?= e(
                                            $truck['model']
                                            ?? 'Truck'
                                        ) ?>

                                    </div>

                                    <div class="driver-detail">

                                        Plate:
                                        <?= e(
                                            $truck['plate_number']
                                            ?? '—'
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php else: ?>

                            <div class="driver-box">

                                <div class="driver-name">
                                    Not assigned yet
                                </div>

                                <div class="driver-detail">
                                    Driver information will appear here
                                    after your order is assigned.
                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- Order Summary -->

                    <div class="card summary-card">

                        <h2>
                            Order Summary
                        </h2>

                        <div class="summary-row">

                            <span class="summary-label">
                                Order Number
                            </span>

                            <span class="summary-value">
                                <?= e(
                                    $order['order_number']
                                ) ?>
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Expected Delivery
                            </span>

                            <span class="summary-value">
                                <?= e(
                                    $expectedDelivery
                                ) ?>
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
                                Weight
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
                                        trackingStatusClass(
                                            $currentStatus
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        $displayStatus
                                    ) ?>
                                </span>

                            </span>

                        </div>

                        <div class="summary-total">

                            <span class="summary-total-label">
                                Total
                            </span>

                            <span class="summary-total-value">
                                ₱<?= number_format(
                                    $totalAmount,
                                    2
                                ) ?>
                            </span>

                        </div>

                        <div class="summary-actions">

                            <a
                                href="<?= BASE_URL ?>customer/order_details.php?order_id=<?= $orderId ?>"
                                class="summary-action summary-primary"
                            >
                                Order Details
                            </a>

                            <?php if ($isFinished): ?>

                                <a
                                    href="<?= BASE_URL ?>customer/feedback.php?order_id=<?= $orderId ?>"
                                    class="summary-action summary-success"
                                >
                                    Give Feedback
                                </a>

                            <?php endif; ?>

                            <a
                                href="<?= BASE_URL ?>customer/order_history.php"
                                class="summary-action summary-secondary"
                            >
                                My Orders
                            </a>

                        </div>

                    </div>

                </aside>

            </div>

        </div>

    </main>

</div>

<?php if (
    $latestLatitude !== null &&
    $latestLongitude !== null
): ?>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    ></script>

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const latitude =
                    <?= json_encode($latestLatitude) ?>;

                const longitude =
                    <?= json_encode($latestLongitude) ?>;

                const status =
                    <?= json_encode($displayStatus) ?>;

                const mapElement =
                    document.getElementById(
                        'tracking-map'
                    );

                if (
                    !mapElement
                    || latitude === null
                    || longitude === null
                ) {
                    return;
                }

                const map =
                    L.map(
                        'tracking-map'
                    ).setView(
                        [
                            latitude,
                            longitude
                        ],
                        15
                    );

                L.tileLayer(
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    {
                        maxZoom: 19,
                        attribution:
                            '&copy; OpenStreetMap contributors'
                    }
                ).addTo(map);

                const marker =
                    L.marker(
                        [
                            latitude,
                            longitude
                        ]
                    ).addTo(map);

                marker.bindPopup(
                    '<strong>Latest Delivery Location</strong><br>' +
                    'Status: ' +
                    String(status)
                ).openPopup();

            }
        );

    </script>

<?php endif; ?>

</body>

</html>