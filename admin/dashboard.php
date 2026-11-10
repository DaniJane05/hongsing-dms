<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();


/*
|--------------------------------------------------------------------------
| SUMMARY STATISTICS
|--------------------------------------------------------------------------
*/

/* Total Orders */
$totalOrders = $db->query("
    SELECT COUNT(*)
    FROM `order`
")->fetchColumn();

/* Pending Orders */
$totalPending = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'Pending'
")->fetchColumn();

/* Approved Orders */
$totalApproved = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'Approved'
")->fetchColumn();

/* Assigned Orders */
$totalAssigned = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'Assigned'
")->fetchColumn();

/* In Transit */
$totalInTransit = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'In Transit'
")->fetchColumn();

/* Completed */
$totalCompleted = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'Completed'
")->fetchColumn();

/* Available Drivers */
$availableDrivers = $db->query("
    SELECT COUNT(*)
    FROM driver
    WHERE status = 'Available'
")->fetchColumn();

/* Available Trucks */
$availableTrucks = $db->query("
    SELECT COUNT(*)
    FROM truck
    WHERE status = 'Available'
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| ORDERS NEEDING ASSIGNMENT
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        o.order_id,
        o.order_number,
        o.order_date,
        o.expected_delivery_date,
        o.priority_level,
        o.completion_status,
        c.full_name AS customer_name
    FROM `order` o
    LEFT JOIN customer c
        ON c.customer_id = o.customer_id
    WHERE o.completion_status IN (
        'Pending',
        'Approved'
    )
    ORDER BY
        CASE
            WHEN o.priority_level = 'Urgent' THEN 1
            ELSE 2
        END,
        o.expected_delivery_date ASC,
        o.order_date ASC
    LIMIT 10
");

$ordersForAssignment = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        o.order_number,
        o.order_date,
        o.expected_delivery_date,
        o.priority_level,
        o.completion_status,
        c.full_name AS customer_name
    FROM `order` o
    LEFT JOIN customer c
        ON c.customer_id = o.customer_id
    ORDER BY o.created_at DESC
    LIMIT 5
");

$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard · Hong Sing LKRR Commercial</title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >


    <style>

        /* =========================================================
           ADMIN DASHBOARD
        ========================================================= */

        .admin-dashboard {
            width: 100%;
        }


        /* =========================================================
           DASHBOARD HEADER
        ========================================================= */

        .dashboard-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }

        .dashboard-header-content h1 {
            margin: 0 0 8px;
            color: var(--color-text-primary);
            font-size: 30px;
            line-height: 1.2;
            font-weight: 700;
        }

        .dashboard-header-content p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 14px;
        }

        .dashboard-date {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text-secondary);
            font-size: 13px;
            white-space: nowrap;
        }


        /* =========================================================
           STATISTICS
        ========================================================= */

        .dashboard-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .dashboard-stat-card {
            position: relative;
            min-height: 118px;
            padding: 20px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            box-sizing: border-box;
        }

        .dashboard-stat-card::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3px;
            background: var(--color-border);
        }

        .dashboard-stat-card.stat-total::after {
            background: var(--color-accent);
        }

        .dashboard-stat-card.stat-pending::after {
            background: var(--color-warning);
        }

        .dashboard-stat-card.stat-approved::after {
            background: var(--color-info);
        }

        .dashboard-stat-card.stat-assigned::after {
            background: var(--color-accent);
        }

        .dashboard-stat-card.stat-transit::after {
            background: var(--color-info);
        }

        .dashboard-stat-card.stat-completed::after {
            background: var(--color-success);
        }

        .dashboard-stat-card.stat-driver::after {
            background: var(--color-success);
        }

        .dashboard-stat-card.stat-truck::after {
            background: var(--color-accent);
        }

        .dashboard-stat-label {
            display: block;
            margin-bottom: 13px;
            color: var(--color-text-secondary);
            font-size: 13px;
            font-weight: 500;
        }

        .dashboard-stat-value {
            display: block;
            color: var(--color-text-primary);
            font-size: 30px;
            line-height: 1;
            font-weight: 700;
        }


        /* =========================================================
           QUICK ACTIONS
        ========================================================= */

        .dashboard-quick-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .quick-action-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            min-height: 108px;
            padding: 20px 22px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-card);
            color: inherit;
            text-decoration: none;
            box-sizing: border-box;
            transition:
                transform .15s ease,
                border-color .15s ease,
                box-shadow .15s ease;
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            border-color: var(--color-accent);
            box-shadow: var(--shadow-card);
        }

        .quick-action-content {
            min-width: 0;
        }

        .quick-action-label {
            display: block;
            margin-bottom: 5px;
            color: var(--color-text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .quick-action-title {
            display: block;
            color: var(--color-text-primary);
            font-size: 16px;
            font-weight: 700;
        }

        .quick-action-description {
            margin-top: 5px;
            color: var(--color-text-secondary);
            font-size: 12px;
            line-height: 1.4;
        }

        .quick-action-arrow {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--color-accent-soft);
            color: var(--color-accent);
            font-size: 18px;
            font-weight: 700;
        }

        .feedback-action .quick-action-arrow {
            color: var(--color-info);
        }


        /* =========================================================
           SECTIONS
        ========================================================= */

        .dashboard-section {
            margin-bottom: 24px;
        }

        .dashboard-section:last-child {
            margin-bottom: 0;
        }

        .dashboard-panel {
            width: 100%;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .dashboard-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 24px;
            border-bottom: 1px solid var(--color-border);
        }

        .dashboard-section-title h2 {
            margin: 0 0 5px;
            color: var(--color-text-primary);
            font-size: 18px;
        }

        .dashboard-section-title p {
            margin: 0;
            color: var(--color-text-muted);
            font-size: 13px;
        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .dashboard-btn {
            min-height: 38px;
            padding: 8px 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            box-sizing: border-box;
            transition:
                background .15s ease,
                transform .15s ease;
        }

        .dashboard-btn:hover {
            transform: translateY(-1px);
        }

        .dashboard-btn-primary {
            background: var(--color-accent);
            color: #fff;
        }

        .dashboard-btn-primary:hover {
            background: var(--color-accent-hover);
        }

        .dashboard-btn-secondary {
            background: var(--color-surface);
            color: var(--color-text-secondary);
            border-color: var(--color-border);
        }

        .dashboard-btn-secondary:hover {
            background: var(--color-surface-alt);
            color: var(--color-text-primary);
        }

        .dashboard-btn-success {
            background: var(--color-success);
            color: #fff;
        }

        .dashboard-btn-success:hover {
            opacity: .9;
        }


        /* =========================================================
           TABLE
        ========================================================= */

        .dashboard-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .dashboard-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .dashboard-table th {
            padding: 13px 16px;
            background: var(--color-surface-alt);
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            white-space: nowrap;
        }

        .dashboard-table td {
            padding: 15px 16px;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text-primary);
            font-size: 13px;
            vertical-align: middle;
        }

        .dashboard-table tbody tr {
            transition: background .15s ease;
        }

        .dashboard-table tbody tr:hover {
            background: var(--color-surface-alt);
        }

        .dashboard-table tbody tr:last-child td {
            border-bottom: none;
        }


        /* =========================================================
           ORDER CELL
        ========================================================= */

        .dashboard-order-number {
            display: block;
            color: var(--color-text-primary);
            font-weight: 700;
            white-space: nowrap;
        }

        .dashboard-customer {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .dashboard-avatar {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--color-accent-soft);
            color: var(--color-accent);
            font-size: 11px;
            font-weight: 700;
        }

        .dashboard-customer-name {
            color: var(--color-text-primary);
            font-weight: 600;
        }


        /* =========================================================
           DATE
        ========================================================= */

        .dashboard-date-main {
            display: block;
            color: var(--color-text-primary);
            font-weight: 600;
            white-space: nowrap;
        }

        .dashboard-date-label {
            display: block;
            margin-top: 3px;
            color: var(--color-text-muted);
            font-size: 10px;
        }


        /* =========================================================
           BADGES
        ========================================================= */

        .dashboard-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 27px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            box-sizing: border-box;
        }

        .badge-urgent {
            background: var(--color-accent-soft);
            color: var(--color-danger);
        }

        .badge-normal {
            background: var(--color-surface-alt);
            color: var(--color-text-secondary);
        }

        .badge-pending {
            background: var(--color-accent-soft);
            color: var(--color-warning);
        }

        .badge-approved {
            background: var(--color-accent-soft);
            color: var(--color-info);
        }

        .badge-assigned {
            background: var(--color-accent-soft);
            color: var(--color-accent);
        }

        .badge-transit {
            background: var(--color-accent-soft);
            color: var(--color-info);
        }

        .badge-completed {
            background: var(--color-accent-soft);
            color: var(--color-success);
        }

        .badge-cancelled {
            background: var(--color-accent-soft);
            color: var(--color-danger);
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .dashboard-empty {
            padding: 55px 24px;
            text-align: center;
        }

        .dashboard-empty-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--color-surface-alt);
            color: var(--color-text-muted);
            font-size: 20px;
        }

        .dashboard-empty strong {
            display: block;
            margin-bottom: 6px;
            color: var(--color-text-primary);
            font-size: 15px;
        }

        .dashboard-empty p {
            margin: 0;
            color: var(--color-text-muted);
            font-size: 13px;
        }


        /* =========================================================
           SECTION FOOTER
        ========================================================= */

        .dashboard-section-footer {
            display: flex;
            justify-content: flex-end;
            padding: 14px 24px;
            border-top: 1px solid var(--color-border);
            background: var(--color-surface-alt);
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1200px) {

            .dashboard-stat-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .dashboard-quick-actions {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 900px) {

            .dashboard-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-header {
                align-items: flex-start;
                flex-direction: column;
            }

        }


        @media (max-width: 650px) {

            .dashboard-stat-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-section-header {
                align-items: flex-start;
                flex-direction: column;
                padding: 18px;
            }

            .dashboard-section-header .dashboard-btn {
                width: 100%;
            }

            .dashboard-section-footer {
                padding: 12px 18px;
            }

            .dashboard-section-footer .dashboard-btn {
                width: 100%;
            }

        }

    </style>

</head>


<body>

<?php
/*
|--------------------------------------------------------------------------
| SHARED HEADER / SIDEBAR
|--------------------------------------------------------------------------
|
| header.php already creates the .app-layout wrapper.
|
*/

require __DIR__ . '/../includes/header.php';
?>


<main class="app-main">

    <div class="admin-dashboard">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <header class="dashboard-header">

            <div class="dashboard-header-content">

                <h1>
                    Admin Dashboard
                </h1>

                <p>
                    Monitor orders, deliveries, drivers, and trucks.
                </p>

            </div>


            <div class="dashboard-date">

                <?= date('F d, Y') ?>

            </div>

        </header>


        <!-- =====================================================
             STATISTICS
        ====================================================== -->

        <section class="dashboard-stat-grid">


            <div class="dashboard-stat-card stat-total">

                <span class="dashboard-stat-label">
                    Total Orders
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $totalOrders ?>
                </strong>

            </div>


            <div class="dashboard-stat-card stat-pending">

                <span class="dashboard-stat-label">
                    Pending
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $totalPending ?>
                </strong>

            </div>


            <div class="dashboard-stat-card stat-approved">

                <span class="dashboard-stat-label">
                    Approved
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $totalApproved ?>
                </strong>

            </div>


            <div class="dashboard-stat-card stat-assigned">

                <span class="dashboard-stat-label">
                    Assigned
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $totalAssigned ?>
                </strong>

            </div>


            <div class="dashboard-stat-card stat-transit">

                <span class="dashboard-stat-label">
                    In Transit
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $totalInTransit ?>
                </strong>

            </div>


            <div class="dashboard-stat-card stat-completed">

                <span class="dashboard-stat-label">
                    Completed
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $totalCompleted ?>
                </strong>

            </div>


            <div class="dashboard-stat-card stat-driver">

                <span class="dashboard-stat-label">
                    Available Drivers
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $availableDrivers ?>
                </strong>

            </div>


            <div class="dashboard-stat-card stat-truck">

                <span class="dashboard-stat-label">
                    Available Trucks
                </span>

                <strong class="dashboard-stat-value">
                    <?= (int) $availableTrucks ?>
                </strong>

            </div>


        </section>


        <!-- =====================================================
             QUICK ACTIONS
        ====================================================== -->

        <section class="dashboard-quick-actions">


            <!-- Orders -->

            <a
                href="<?= BASE_URL ?>admin/orders.php"
                class="quick-action-card"
            >

                <div class="quick-action-content">

                    <span class="quick-action-label">
                        Order Management
                    </span>

                    <span class="quick-action-title">
                        View All Orders
                    </span>

                    <div class="quick-action-description">
                        Review, approve, cancel, and manage delivery orders.
                    </div>

                </div>

                <span class="quick-action-arrow">
                    →
                </span>

            </a>


            <!-- Notifications -->

            <a
                href="<?= BASE_URL ?>admin/notifications.php"
                class="quick-action-card"
            >

                <div class="quick-action-content">

                    <span class="quick-action-label">
                        Notifications
                    </span>

                    <span class="quick-action-title">
                        View Notifications
                    </span>

                    <div class="quick-action-description">
                        Check important order and delivery updates.
                    </div>

                </div>

                <span class="quick-action-arrow">
                    →
                </span>

            </a>


            <!-- Customer Feedback -->

            <a
                href="<?= BASE_URL ?>admin/feedback.php"
                class="quick-action-card feedback-action"
            >

                <div class="quick-action-content">

                    <span class="quick-action-label">
                        Customer Feedback
                    </span>

                    <span class="quick-action-title">
                        View Customer Feedback
                    </span>

                    <div class="quick-action-description">
                        Review customer ratings and feedback from deliveries.
                    </div>

                </div>

                <span class="quick-action-arrow">
                    →
                </span>

            </a>


        </section>


        <!-- =====================================================
             ORDERS NEEDING ASSIGNMENT
        ====================================================== -->

        <section class="dashboard-section">

            <div class="dashboard-panel">


                <div class="dashboard-section-header">

                    <div class="dashboard-section-title">

                        <h2>
                            Orders Needing Assignment
                        </h2>

                        <p>
                            Pending and approved orders that require a driver and truck.
                        </p>

                    </div>


                    <a
                        href="<?= BASE_URL ?>admin/orders.php"
                        class="dashboard-btn dashboard-btn-secondary"
                    >
                        View All Orders
                    </a>

                </div>


                <?php if (empty($ordersForAssignment)): ?>


                    <div class="dashboard-empty">

                        <div class="dashboard-empty-icon">
                            ✓
                        </div>

                        <strong>
                            No orders need assignment
                        </strong>

                        <p>
                            All pending and approved orders are currently handled.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="dashboard-table-wrapper">

                        <table class="dashboard-table">

                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Order Date
                                    </th>

                                    <th>
                                        Expected Delivery
                                    </th>

                                    <th>
                                        Priority
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($ordersForAssignment as $order): ?>


                                <?php

                                $customerName =
                                    trim($order['customer_name'] ?? '');

                                $customerInitial =
                                    $customerName !== ''
                                        ? strtoupper(
                                            substr($customerName, 0, 1)
                                        )
                                        : '?';

                                $assignmentStatus =
                                    $order['completion_status'];

                                $assignmentStatusClass =
                                    $assignmentStatus === 'Approved'
                                        ? 'badge-approved'
                                        : 'badge-pending';

                                ?>


                                <tr>


                                    <!-- ORDER -->

                                    <td>

                                        <span class="dashboard-order-number">
                                            <?= htmlspecialchars(
                                                $order['order_number']
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- CUSTOMER -->

                                    <td>

                                        <div class="dashboard-customer">

                                            <span class="dashboard-avatar">
                                                <?= htmlspecialchars(
                                                    $customerInitial
                                                ) ?>
                                            </span>

                                            <span class="dashboard-customer-name">
                                                <?= htmlspecialchars(
                                                    $customerName !== ''
                                                        ? $customerName
                                                        : 'Unknown Customer'
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- ORDER DATE -->

                                    <td>

                                        <?php if (!empty($order['order_date'])): ?>

                                            <span class="dashboard-date-main">

                                                <?= date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $order['order_date']
                                                    )
                                                ) ?>

                                            </span>

                                            <span class="dashboard-date-label">
                                                Order date
                                            </span>

                                        <?php else: ?>

                                            <span>
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- EXPECTED DELIVERY -->

                                    <td>

                                        <?php if (
                                            !empty(
                                                $order[
                                                    'expected_delivery_date'
                                                ]
                                            )
                                        ): ?>

                                            <span class="dashboard-date-main">

                                                <?= date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $order[
                                                            'expected_delivery_date'
                                                        ]
                                                    )
                                                ) ?>

                                            </span>

                                            <span class="dashboard-date-label">
                                                Expected
                                            </span>

                                        <?php else: ?>

                                            <span>
                                                Not set
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- PRIORITY -->

                                    <td>

                                        <?php if (
                                            $order['priority_level'] === 'Urgent'
                                        ): ?>

                                            <span class="dashboard-badge badge-urgent">
                                                Urgent
                                            </span>

                                        <?php else: ?>

                                            <span class="dashboard-badge badge-normal">
                                                Normal
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="dashboard-badge <?= $assignmentStatusClass ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $assignmentStatus
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- ACTION -->

                                    <td>

                                        <a
                                            href="<?= BASE_URL ?>admin/assign_delivery.php?order_id=<?= (int) $order['order_id'] ?>"
                                            class="dashboard-btn dashboard-btn-success"
                                        >
                                            Assign
                                        </a>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>


            </div>

        </section>


        <!-- =====================================================
             RECENT ORDERS
        ====================================================== -->

        <section class="dashboard-section">

            <div class="dashboard-panel">


                <div class="dashboard-section-header">

                    <div class="dashboard-section-title">

                        <h2>
                            Recent Orders
                        </h2>

                        <p>
                            Latest order activity in the system.
                        </p>

                    </div>


                    <a
                        href="<?= BASE_URL ?>admin/orders.php"
                        class="dashboard-btn dashboard-btn-secondary"
                    >
                        View All
                    </a>

                </div>


                <?php if (empty($recentOrders)): ?>


                    <div class="dashboard-empty">

                        <div class="dashboard-empty-icon">
                            —
                        </div>

                        <strong>
                            No orders found
                        </strong>

                        <p>
                            Recent order activity will appear here.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="dashboard-table-wrapper">

                        <table class="dashboard-table">

                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Order Date
                                    </th>

                                    <th>
                                        Expected Delivery
                                    </th>

                                    <th>
                                        Priority
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($recentOrders as $order): ?>


                                <?php

                                $customerName =
                                    trim($order['customer_name'] ?? '');

                                $customerInitial =
                                    $customerName !== ''
                                        ? strtoupper(
                                            substr($customerName, 0, 1)
                                        )
                                        : '?';


                                $status =
                                    $order['completion_status'];

                                switch ($status) {

                                    case 'Approved':
                                        $statusClass = 'badge-approved';
                                        break;

                                    case 'Assigned':
                                        $statusClass = 'badge-assigned';
                                        break;

                                    case 'Accepted':
                                    case 'In Transit':
                                        $statusClass = 'badge-transit';
                                        break;

                                    case 'Completed':
                                        $statusClass = 'badge-completed';
                                        break;

                                    case 'Cancelled':
                                        $statusClass = 'badge-cancelled';
                                        break;

                                    case 'Pending':
                                    default:
                                        $statusClass = 'badge-pending';
                                        break;
                                }

                                ?>


                                <tr>


                                    <!-- ORDER -->

                                    <td>

                                        <span class="dashboard-order-number">
                                            <?= htmlspecialchars(
                                                $order['order_number']
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- CUSTOMER -->

                                    <td>

                                        <div class="dashboard-customer">

                                            <span class="dashboard-avatar">
                                                <?= htmlspecialchars(
                                                    $customerInitial
                                                ) ?>
                                            </span>

                                            <span class="dashboard-customer-name">
                                                <?= htmlspecialchars(
                                                    $customerName !== ''
                                                        ? $customerName
                                                        : 'Unknown Customer'
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- ORDER DATE -->

                                    <td>

                                        <?php if (!empty($order['order_date'])): ?>

                                            <span class="dashboard-date-main">

                                                <?= date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $order['order_date']
                                                    )
                                                ) ?>

                                            </span>

                                            <span class="dashboard-date-label">
                                                Order date
                                            </span>

                                        <?php else: ?>

                                            <span>
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- EXPECTED DELIVERY -->

                                    <td>

                                        <?php if (
                                            !empty(
                                                $order[
                                                    'expected_delivery_date'
                                                ]
                                            )
                                        ): ?>

                                            <span class="dashboard-date-main">

                                                <?= date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $order[
                                                            'expected_delivery_date'
                                                        ]
                                                    )
                                                ) ?>

                                            </span>

                                            <span class="dashboard-date-label">
                                                Expected
                                            </span>

                                        <?php else: ?>

                                            <span>
                                                Not set
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- PRIORITY -->

                                    <td>

                                        <?php if (
                                            $order['priority_level'] === 'Urgent'
                                        ): ?>

                                            <span class="dashboard-badge badge-urgent">
                                                Urgent
                                            </span>

                                        <?php else: ?>

                                            <span class="dashboard-badge badge-normal">
                                                Normal
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="dashboard-badge <?= $statusClass ?>"
                                        >
                                            <?= htmlspecialchars($status) ?>
                                        </span>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>


                <div class="dashboard-section-footer">

                    <a
                        href="<?= BASE_URL ?>admin/orders.php"
                        class="dashboard-btn dashboard-btn-secondary"
                    >
                        Open Order Management →
                    </a>

                </div>


            </div>

        </section>


    </div>

</main>


</div>

</body>

</html>