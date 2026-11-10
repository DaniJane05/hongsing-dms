<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

$customerId = (int) currentUserId();

if ($customerId <= 0) {
    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| CUSTOMER NAME
|--------------------------------------------------------------------------
*/

$fullName = $_SESSION['full_name'] ?? 'Customer';

$nameParts = preg_split('/\s+/', trim($fullName));

$firstName = $nameParts[0] ?? 'Customer';


/*
|--------------------------------------------------------------------------
| ORDER STATISTICS
|--------------------------------------------------------------------------
|
| These statistics belong to the logged-in customer only.
|
*/

$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total_orders,

        SUM(
            CASE
                WHEN completion_status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_orders,

        SUM(
            CASE
                WHEN completion_status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approved_orders,

        SUM(
            CASE
                WHEN completion_status = 'In Transit'
                THEN 1
                ELSE 0
            END
        ) AS in_transit_orders,

        SUM(
            CASE
                WHEN completion_status = 'Completed'
                THEN 1
                ELSE 0
            END
        ) AS completed_orders,

        SUM(
            CASE
                WHEN completion_status = 'Cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelled_orders

    FROM `order`

    WHERE customer_id = ?
");

$statsStmt->execute([$customerId]);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];


$totalOrders = (int) ($stats['total_orders'] ?? 0);
$pendingOrders = (int) ($stats['pending_orders'] ?? 0);
$approvedOrders = (int) ($stats['approved_orders'] ?? 0);
$inTransitOrders = (int) ($stats['in_transit_orders'] ?? 0);
$completedOrders = (int) ($stats['completed_orders'] ?? 0);
$cancelledOrders = (int) ($stats['cancelled_orders'] ?? 0);


/*
|--------------------------------------------------------------------------
| ACTIVE ORDER
|--------------------------------------------------------------------------
|
| Active order means the most recent order that has not yet been
| completed or cancelled.
|
*/

$activeOrderStmt = $db->prepare("
    SELECT
        o.order_id,
        o.order_number,
        o.total_amount,
        o.item_description,
        o.order_date,
        o.expected_delivery_date,
        o.completion_status
    FROM `order` o
    WHERE o.customer_id = ?
      AND o.completion_status NOT IN ('Completed', 'Cancelled', 'Failed')
    ORDER BY o.order_date DESC, o.order_id DESC
    LIMIT 1
");
$activeOrderStmt->execute([$customerId]);
$activeOrder = $activeOrderStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| CURRENT DELIVERY STATUS
|--------------------------------------------------------------------------
|
| Get the latest delivery status for the active order.
|
*/

$currentDeliveryStatus = null;

if ($activeOrder) {

    $statusStmt = $db->prepare("
        SELECT
            ds.status,
            ds.latitude,
            ds.longitude,
            ds.notes,
            ds.timestamp
        FROM delivery_status ds

        WHERE ds.order_id = ?

        ORDER BY
            ds.timestamp DESC,
            ds.status_id DESC

        LIMIT 1
    ");

    $statusStmt->execute([
        (int) $activeOrder['order_id']
    ]);

    $currentDeliveryStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/

$recentOrdersStmt = $db->prepare("
    SELECT
        o.order_id,
        o.order_number,
        o.order_date,
        o.expected_delivery_date,
        o.completion_status,
        o.total_amount
    FROM `order` o

    WHERE o.customer_id = ?

    ORDER BY
        o.order_date DESC,
        o.order_id DESC

    LIMIT 5
");

$recentOrdersStmt->execute([$customerId]);

$recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| CART COUNT
|--------------------------------------------------------------------------
*/

$cartCount = 0;

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {

    foreach ($_SESSION['cart'] as $item) {

        $cartCount += (int) (
            $item['quantity'] ?? 0
        );
    }
}


/*
|--------------------------------------------------------------------------
| STATUS HELPER
|--------------------------------------------------------------------------
*/

function dashboardStatusClass(string $status): string
{
    return match ($status) {

        'Pending' =>
            'status-pending',

        'Approved' =>
            'status-approved',

        'Assigned' =>
            'status-assigned',

        'Accepted' =>
            'status-accepted',

        'In Transit' =>
            'status-transit',

        'Completed' =>
            'status-completed',

        'Cancelled' =>
            'status-cancelled',

        'Failed' =>
            'status-failed',

        default =>
            'status-default'
    };
}


/*
|--------------------------------------------------------------------------
| DISPLAY DELIVERY STATUS
|--------------------------------------------------------------------------
*/

$deliveryStatus = 'No active delivery';

if ($currentDeliveryStatus) {

    $deliveryStatus =
        $currentDeliveryStatus['status'];

} elseif ($activeOrder) {

    $deliveryStatus =
        $activeOrder['completion_status'];
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
|
| Step 1 navigation.
| The shared customer sidebar in includes/header.php handles this.
|
*/

$navItems = [
    [
        'label' => 'Dashboard',
        'url' => BASE_URL . 'customer/dashboard.php'
    ],
    [
        'label' => 'Products',
        'url' => BASE_URL . 'customer/products.php'
    ],
    [
        'label' => 'Cart',
        'url' => BASE_URL . 'customer/cart.php'
    ],
    [
        'label' => 'My Orders',
        'url' => BASE_URL . 'customer/order_history.php'
    ],
    [
        'label' => 'Notifications',
        'url' => BASE_URL . 'customer/notifications.php'
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
        Customer Dashboard · Hong Sing LKRR Commercial
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | DASHBOARD
        |--------------------------------------------------------------------------
        */

        .customer-dashboard {
            max-width: 1250px;
            margin: 0 auto;
        }


        /*
        |--------------------------------------------------------------------------
        | WELCOME
        |--------------------------------------------------------------------------
        */

        .dashboard-welcome {
            margin-bottom: 24px;
        }

        .dashboard-welcome h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .dashboard-welcome p {
            margin: 0;
            color: var(--color-text-secondary);
        }


        /*
        |--------------------------------------------------------------------------
        | DASHBOARD GRID
        |--------------------------------------------------------------------------
        */

        .dashboard-top-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;

            margin-bottom: 24px;
        }


        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .dashboard-card {
            background: var(--color-surface);

            border: 1px solid
                var(--color-border);

            border-radius: 14px;

            padding: 22px;
        }


        .dashboard-card-header {
            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 18px;
        }


        .dashboard-card-header h2 {
            margin: 0;

            font-size: 18px;
        }


        .dashboard-card-header p {
            margin: 5px 0 0;

            color:
                var(--color-text-secondary);

            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | ACTIVE ORDER
        |--------------------------------------------------------------------------
        */

        .active-order-number {
            font-size: 22px;

            font-weight: 700;

            margin-bottom: 8px;
        }


        .active-order-date {
            color:
                var(--color-text-secondary);

            font-size: 13px;

            margin-bottom: 16px;
        }


        .active-order-info {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 15px;

            margin-bottom: 18px;
        }


        .info-label {
            display: block;

            font-size: 12px;

            color:
                var(--color-text-secondary);

            margin-bottom: 4px;
        }


        .info-value {
            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS BADGES
        |--------------------------------------------------------------------------
        */

        .status-badge {
            display: inline-flex;

            align-items: center;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 700;
        }


        .status-pending {
            background: #fff3cd;
            color: #856404;
        }


        .status-approved {
            background: #dbeafe;
            color: #1e40af;
        }


        .status-assigned {
            background: #e0e7ff;
            color: #3730a3;
        }


        .status-accepted {
            background: #ede9fe;
            color: #6d28d9;
        }


        .status-transit {
            background: #dbeafe;
            color: #1d4ed8;
        }


        .status-completed {
            background: #dcfce7;
            color: #166534;
        }


        .status-cancelled,
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }


        .status-default {
            background: var(--color-surface-hover);
            color: var(--color-text-secondary);
        }


        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        .dashboard-button {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 10px 15px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

            border: 1px solid
                var(--color-border);

            background:
                var(--color-surface);

            color:
                var(--color-text-primary);

            transition:
                background .15s ease,
                transform .15s ease;
        }


        .dashboard-button:hover {
            background:
                var(--color-surface-hover);

            transform: translateY(-1px);
        }


        .dashboard-button.primary {
            background:
                var(--color-accent);

            border-color:
                var(--color-accent);

            color: #fff;
        }


        .dashboard-button.primary:hover {
            opacity: .92;
        }


        .button-row {
            display: flex;

            flex-wrap: wrap;

            gap: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | DELIVERY STATUS
        |--------------------------------------------------------------------------
        */

        .delivery-status-display {
            display: flex;

            align-items: center;

            gap: 15px;

            padding: 18px;

            border-radius: 10px;

            background:
                var(--color-surface-hover);

            margin-bottom: 18px;
        }


        .delivery-status-icon {
            width: 46px;
            height: 46px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background:
                var(--color-accent);

            color: #fff;

            font-size: 20px;

            flex-shrink: 0;
        }


        .delivery-status-text strong {
            display: block;

            font-size: 17px;

            margin-bottom: 4px;
        }


        .delivery-status-text span {
            color:
                var(--color-text-secondary);

            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | STATISTICS
        |--------------------------------------------------------------------------
        */

        .dashboard-stats {
            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 15px;

            margin-bottom: 24px;
        }


        .stat-card {
            background:
                var(--color-surface);

            border: 1px solid
                var(--color-border);

            border-radius: 12px;

            padding: 20px;
        }


        .stat-label {
            display: block;

            color:
                var(--color-text-secondary);

            font-size: 13px;

            margin-bottom: 8px;
        }


        .stat-value {
            font-size: 28px;

            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | RECENT ORDERS
        |--------------------------------------------------------------------------
        */

        .recent-orders-card {
            margin-bottom: 24px;
        }


        .table-wrapper {
            overflow-x: auto;
        }


        .recent-orders-table {
            width: 100%;

            border-collapse: collapse;
        }


        .recent-orders-table th,
        .recent-orders-table td {
            padding: 13px 10px;

            text-align: left;

            border-bottom:
                1px solid var(--color-border);

            vertical-align: middle;
        }


        .recent-orders-table th {
            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .4px;

            color:
                var(--color-text-secondary);
        }


        .recent-orders-table td {
            font-size: 13px;
        }


        .recent-orders-table tbody tr:last-child td {
            border-bottom: none;
        }


        .order-number-link {
            font-weight: 700;

            color:
                var(--color-text-primary);

            text-decoration: none;
        }


        .order-number-link:hover {
            color:
                var(--color-accent);
        }


        .empty-state {
            padding: 35px 20px;

            text-align: center;

            color:
                var(--color-text-secondary);
        }


        /*
        |--------------------------------------------------------------------------
        | QUICK ACTIONS
        |--------------------------------------------------------------------------
        */

        .quick-actions {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 15px;
        }


        .quick-action {
            display: flex;

            flex-direction: column;

            justify-content: center;

            min-height: 110px;

            padding: 18px;

            border: 1px solid
                var(--color-border);

            border-radius: 12px;

            text-decoration: none;

            color:
                var(--color-text-primary);

            background:
                var(--color-surface);

            transition:
                transform .15s ease,
                background .15s ease;
        }


        .quick-action:hover {
            transform: translateY(-2px);

            background:
                var(--color-surface-hover);
        }


        .quick-action-icon {
            font-size: 24px;

            margin-bottom: 10px;
        }


        .quick-action-title {
            font-weight: 700;

            margin-bottom: 4px;
        }


        .quick-action-description {
            color:
                var(--color-text-secondary);

            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 950px) {

            .dashboard-top-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-stats {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 650px) {

            .dashboard-stats {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .active-order-info {
                grid-template-columns: 1fr;
            }

            .dashboard-welcome h1 {
                font-size: 24px;
            }

        }

    </style>

</head>


<body>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/header.php'; ?>


    <main class="app-main">

        <div class="customer-dashboard">


            <!-- ==================================================
                 WELCOME
            =================================================== -->

            <section class="dashboard-welcome">

                <h1>
                    Welcome back,
                    <?= htmlspecialchars($firstName) ?>! 👋
                </h1>

                <p>
                    Here's a quick overview of your orders and deliveries.
                </p>

            </section>


            <!-- ==================================================
                 ACTIVE ORDER + DELIVERY STATUS
            =================================================== -->

            <div class="dashboard-top-grid">


                <!-- ==================================================
                     ACTIVE ORDER
                =================================================== -->

                <section class="dashboard-card">

                    <div class="dashboard-card-header">

                        <div>

                            <h2>
                                Active Order
                            </h2>

                            <p>
                                Your current order
                            </p>

                        </div>

                    </div>


                    <?php if ($activeOrder): ?>

                        <div class="active-order-number">

                            <?= htmlspecialchars(
                                $activeOrder['order_number']
                            ) ?>

                        </div>


                        <div class="active-order-date">

                            Ordered
                            <?= date(
                                'M d, Y',
                                strtotime($activeOrder['order_date'])
                            ) ?>

                        </div>


                        <div class="active-order-info">

                            <div>

                                <span class="info-label">
                                    Order Status
                                </span>

                                <span class="status-badge <?= dashboardStatusClass(
                                    $activeOrder['completion_status']
                                ) ?>">

                                    <?= htmlspecialchars(
                                        $activeOrder['completion_status']
                                    ) ?>

                                </span>

                            </div>


                            <div>

                                <span class="info-label">
                                    Expected Delivery
                                </span>

                                <span class="info-value">

                                    <?php if (!empty($activeOrder['expected_delivery_date'])): ?>

                                        <?= date(
                                            'M d, Y',
                                            strtotime(
                                                $activeOrder['expected_delivery_date']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Not available

                                    <?php endif; ?>

                                </span>

                            </div>

                        </div>


                        <div class="button-row">

                            <a
                                href="<?= BASE_URL ?>customer/order_details.php?id=<?= (int)$activeOrder['order_id'] ?>"
                                class="dashboard-button primary"
                            >
                                View Order
                            </a>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">

                            <p>
                                You don't have an active order right now.
                            </p>

                            <a
                                href="<?= BASE_URL ?>customer/products.php"
                                class="dashboard-button primary"
                            >
                                Browse Products
                            </a>

                        </div>

                    <?php endif; ?>

                </section>


                <!-- ==================================================
                     CURRENT DELIVERY STATUS
                =================================================== -->

                <section class="dashboard-card">

                    <div class="dashboard-card-header">

                        <div>

                            <h2>
                                Current Delivery Status
                            </h2>

                            <p>
                                Latest update for your active order
                            </p>

                        </div>

                    </div>


                    <?php if ($activeOrder): ?>

                        <div class="delivery-status-display">

                            <div class="delivery-status-icon">
                                🚚
                            </div>

                            <div class="delivery-status-text">

                                <strong>
                                    <?= htmlspecialchars(
                                        $deliveryStatus
                                    ) ?>
                                </strong>

                                <span>

                                    <?php if ($currentDeliveryStatus && !empty($currentDeliveryStatus['notes'])): ?>

                                        <?= htmlspecialchars(
                                            $currentDeliveryStatus['notes']
                                        ) ?>

                                    <?php else: ?>

                                        Your order's latest delivery status.

                                    <?php endif; ?>

                                </span>

                            </div>

                        </div>


                        <div class="button-row">

                            <?php if (
                                $activeOrder['completion_status'] === 'In Transit'
                                || $activeOrder['completion_status'] === 'Assigned'
                                || $activeOrder['completion_status'] === 'Accepted'
                            ): ?>

                                <a
                                    href="<?= BASE_URL ?>customer/order_details.php?id=<?= (int)$activeOrder['order_id'] ?>"
                                    class="dashboard-button primary"
                                >
                                    Track Delivery
                                </a>

                            <?php else: ?>

                                <a
                                    href="<?= BASE_URL ?>customer/order_details.php?id=<?= (int)$activeOrder['order_id'] ?>"
                                    class="dashboard-button"
                                >
                                    View Order Details
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">

                            <p>
                                No active delivery to display.
                            </p>

                        </div>

                    <?php endif; ?>

                </section>

            </div>


            <!-- ==================================================
                 ORDER STATISTICS
            =================================================== -->

            <section class="dashboard-stats">


                <div class="stat-card">

                    <span class="stat-label">
                        Total Orders
                    </span>

                    <div class="stat-value">
                        <?= $totalOrders ?>
                    </div>

                </div>


                <div class="stat-card">

                    <span class="stat-label">
                        Pending
                    </span>

                    <div class="stat-value">
                        <?= $pendingOrders ?>
                    </div>

                </div>


                <div class="stat-card">

                    <span class="stat-label">
                        In Transit
                    </span>

                    <div class="stat-value">
                        <?= $inTransitOrders ?>
                    </div>

                </div>


                <div class="stat-card">

                    <span class="stat-label">
                        Completed
                    </span>

                    <div class="stat-value">
                        <?= $completedOrders ?>
                    </div>

                </div>

            </section>


            <!-- ==================================================
                 RECENT ORDERS
            =================================================== -->

            <section class="dashboard-card recent-orders-card">

                <div class="dashboard-card-header">

                    <div>

                        <h2>
                            Recent Orders
                        </h2>

                        <p>
                            Your latest orders
                        </p>

                    </div>


                    <a
                        href="<?= BASE_URL ?>customer/order_history.php"
                        class="dashboard-button"
                    >
                        View All
                    </a>

                </div>


                <?php if (empty($recentOrders)): ?>

                    <div class="empty-state">

                        <p>
                            You haven't placed any orders yet.
                        </p>

                        <a
                            href="<?= BASE_URL ?>customer/products.php"
                            class="dashboard-button primary"
                        >
                            Browse Products
                        </a>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table class="recent-orders-table">

                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Total
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($recentOrders as $order): ?>

                                    <tr>

                                        <td>

                                            <a
                                                href="<?= BASE_URL ?>customer/order_details.php?id=<?= (int)$order['order_id'] ?>"
                                                class="order-number-link"
                                            >
                                                <?= htmlspecialchars(
                                                    $order['order_number']
                                                ) ?>
                                            </a>

                                        </td>


                                        <td>

                                            <?= date(
                                                'M d, Y',
                                                strtotime(
                                                    $order['order_date']
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <span class="status-badge <?= dashboardStatusClass(
                                                $order['completion_status']
                                            ) ?>">

                                                <?= htmlspecialchars(
                                                    $order['completion_status']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            ₱<?= number_format(
                                                (float)$order['total_amount'],
                                                2
                                            ) ?>

                                        </td>


                                        <td>

                                            <a
                                                href="<?= BASE_URL ?>customer/order_details.php?id=<?= (int)$order['order_id'] ?>"
                                                class="dashboard-button"
                                            >
                                                View
                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>


            <!-- ==================================================
                 QUICK ACTIONS
            =================================================== -->

            <section class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>

                        <h2>
                            Quick Actions
                        </h2>

                        <p>
                            Common customer actions
                        </p>

                    </div>

                </div>


                <div class="quick-actions">


                    <a
                        href="<?= BASE_URL ?>customer/products.php"
                        class="quick-action"
                    >

                        <span class="quick-action-icon">
                            🛍️
                        </span>

                        <span class="quick-action-title">
                            Browse Products
                        </span>

                        <span class="quick-action-description">
                            Find products and start an order.
                        </span>

                    </a>


                    <a
                        href="<?= BASE_URL ?>customer/cart.php"
                        class="quick-action"
                    >

                        <span class="quick-action-icon">
                            🛒
                        </span>

                        <span class="quick-action-title">
                            View Cart
                        </span>

                        <span class="quick-action-description">

                            <?= $cartCount > 0
                                ? $cartCount . ' item' .
                                    ($cartCount !== 1 ? 's' : '') .
                                    ' in your cart.'
                                : 'Your cart is currently empty.'
                            ?>

                        </span>

                    </a>


                    <a
                        href="<?= BASE_URL ?>customer/order_history.php"
                        class="quick-action"
                    >

                        <span class="quick-action-icon">
                            📦
                        </span>

                        <span class="quick-action-title">
                            My Orders
                        </span>

                        <span class="quick-action-description">
                            View and manage your orders.
                        </span>

                    </a>

                </div>

            </section>


        </div>

    </main>

</div>

</body>

</html>