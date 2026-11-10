<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

/* ==========================================================
   REPORT FILTERS
========================================================== */

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'orders';

$reportGenerated = isset($_GET['generate']);

$validReportTypes = [
    'orders',
    'drivers',
    'trucks',
    'customers',
    'audit'
];

if (!in_array($reportType, $validReportTypes, true)) {
    $reportType = 'orders';
}

$reportData = [];
$reportTitle = '';

$error = '';

/* ==========================================================
   VALIDATE DATE RANGE
========================================================== */

if ($dateFrom !== '' && $dateTo !== '') {

    if ($dateFrom > $dateTo) {

        $error = "Date From cannot be later than Date To.";

        $reportGenerated = false;
    }
}

/* ==========================================================
   SUMMARY STATISTICS
========================================================== */

$totalOrders = $db->query("
    SELECT COUNT(*)
    FROM `order`
")->fetchColumn();

$totalCustomers = $db->query("
    SELECT COUNT(*)
    FROM customer
")->fetchColumn();

$totalDrivers = $db->query("
    SELECT COUNT(*)
    FROM driver
")->fetchColumn();

$totalTrucks = $db->query("
    SELECT COUNT(*)
    FROM truck
")->fetchColumn();

$totalCompleted = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'Completed'
")->fetchColumn();

$totalPending = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'Pending'
")->fetchColumn();

$totalCancelled = $db->query("
    SELECT COUNT(*)
    FROM `order`
    WHERE completion_status = 'Cancelled'
")->fetchColumn();

/* ==========================================================
   GENERATE REPORT
========================================================== */

if ($reportGenerated && $error === '') {

    switch ($reportType) {

        /* ==================================================
           ORDERS REPORT
        ================================================== */

        case 'orders':

            $reportTitle = 'Orders Report';

            $sql = "
                SELECT
                    o.order_number,
                    c.full_name AS customer_name,
                    d.full_name AS driver_name,
                    t.plate_number,
                    o.order_date,
                    o.expected_delivery_date,
                    o.priority_level,
                    o.completion_status,
                    o.total_amount
                FROM `order` o

                LEFT JOIN customer c
                    ON c.customer_id = o.customer_id

                LEFT JOIN driver d
                    ON d.driver_id = o.driver_id

                LEFT JOIN truck t
                    ON t.truck_id = o.truck_id

                WHERE 1
            ";

            $params = [];

            if ($dateFrom !== '') {

                $sql .= " AND o.order_date >= ?";

                $params[] = $dateFrom;
            }

            if ($dateTo !== '') {

                $sql .= " AND o.order_date <= ?";

                $params[] = $dateTo;
            }

            $sql .= "
                ORDER BY o.order_date DESC
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute($params);

            $reportData = $stmt->fetchAll();

            break;


        /* ==================================================
           DRIVERS REPORT
        ================================================== */

        case 'drivers':

            $reportTitle = 'Drivers Report';

            $sql = "
                SELECT
                    driver_id,
                    full_name,
                    license_number,
                    phone_number,
                    email,
                    status,
                    total_deliveries,
                    rating,
                    last_location,
                    created_at
                FROM driver
                ORDER BY full_name ASC
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute();

            $reportData = $stmt->fetchAll();

            break;


        /* ==================================================
           TRUCKS REPORT
        ================================================== */

        case 'trucks':

            $reportTitle = 'Trucks Report';

            $sql = "
                SELECT
                    t.truck_id,
                    t.plate_number,
                    t.model,
                    t.capacity,
                    t.status,
                    t.last_maintenance,
                    d.full_name AS driver_name
                FROM truck t

                LEFT JOIN driver d
                    ON d.current_truck_id = t.truck_id

                ORDER BY t.plate_number ASC
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute();

            $reportData = $stmt->fetchAll();

            break;


        /* ==================================================
           CUSTOMERS REPORT
        ================================================== */

        case 'customers':

            $reportTitle = 'Customers Report';

            $sql = "
                SELECT
                    customer_id,
                    full_name,
                    email,
                    phone_number,
                    address,
                    city,
                    province,
                    created_at
                FROM customer
                ORDER BY full_name ASC
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute();

            $reportData = $stmt->fetchAll();

            break;


        /* ==================================================
           AUDIT LOG REPORT
        ================================================== */

        case 'audit':

            $reportTitle = 'Audit Logs Report';

            $sql = "
                SELECT
                    created_at,
                    full_name,
                    role,
                    action,
                    module,
                    description,
                    ip_address
                FROM audit_log
                WHERE 1
            ";

            $params = [];

            if ($dateFrom !== '') {

                $sql .= " AND DATE(created_at) >= ?";

                $params[] = $dateFrom;
            }

            if ($dateTo !== '') {

                $sql .= " AND DATE(created_at) <= ?";

                $params[] = $dateTo;
            }

            $sql .= "
                ORDER BY created_at DESC
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute($params);

            $reportData = $stmt->fetchAll();

            break;
    }
}

/* ==========================================================
   NAVIGATION
========================================================== */

$navItems = [

    ['label' => 'Dashboard', 'url' => BASE_URL . 'super_admin/dashboard.php'],
    ['label' => 'Operations Map', 'url' => BASE_URL . 'super_admin/operations_map.php'],
    ['label' => 'Manage Admins', 'url' => BASE_URL . 'super_admin/manage_admins.php'],
    ['label' => 'Manage Drivers', 'url' => BASE_URL . 'super_admin/manage_drivers.php'],
    ['label' => 'Manage Trucks', 'url' => BASE_URL . 'super_admin/manage_trucks.php'],
    ['label' => 'Reports', 'url' => BASE_URL . 'super_admin/reports.php'],
    ['label' => 'System Logs', 'url' => BASE_URL . 'super_admin/system_logs.php'],
    ['label' => 'System Configuration', 'url' => BASE_URL . 'super_admin/system_configuration.php'],
    ['label' => 'Profile', 'url' => BASE_URL . 'super_admin/profile.php'],

];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Reports</title>

<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css">

</head>

<body>

<div class="app-layout">

<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="app-main">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="page-header">

    <div>

        <h1>Reports</h1>

        <p>
            View and generate system reports for deliveries,
            drivers, trucks, customers, and system activities.
        </p>

    </div>

</div>


<!-- ==========================================================
     ERROR MESSAGE
========================================================== -->

<?php if ($error !== ''): ?>

    <div class="alert-error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<!-- ==========================================================
     SUMMARY CARDS
========================================================== -->

<div class="stat-grid">

    <div class="card stat-card accent">

        <span class="stat-label">
            Total Orders
        </span>

        <div class="stat-value">

            <?= (int)$totalOrders ?>

        </div>

    </div>


    <div class="card stat-card">

        <span class="stat-label">
            Customers
        </span>

        <div class="stat-value">

            <?= (int)$totalCustomers ?>

        </div>

    </div>


    <div class="card stat-card">

        <span class="stat-label">
            Drivers
        </span>

        <div class="stat-value">

            <?= (int)$totalDrivers ?>

        </div>

    </div>


    <div class="card stat-card">

        <span class="stat-label">
            Trucks
        </span>

        <div class="stat-value">

            <?= (int)$totalTrucks ?>

        </div>

    </div>

</div>


<br>


<div class="stat-grid">

    <div class="card stat-card">

        <span class="stat-label">
            Completed Orders
        </span>

        <div class="stat-value">

            <?= (int)$totalCompleted ?>

        </div>

    </div>


    <div class="card stat-card">

        <span class="stat-label">
            Pending Orders
        </span>

        <div class="stat-value">

            <?= (int)$totalPending ?>

        </div>

    </div>


    <div class="card stat-card">

        <span class="stat-label">
            Cancelled Orders
        </span>

        <div class="stat-value">

            <?= (int)$totalCancelled ?>

        </div>

    </div>

</div>


<br>


<!-- ==========================================================
     REPORT FILTER
========================================================== -->

<div class="card panel">

    <h2>Generate Report</h2>

    <form
        method="GET"
        class="toolbar">

        <div class="toolbar-left">


            <!-- DATE FROM -->

            <label class="form-label">
                Date From
            </label>

            <input
                type="date"
                name="date_from"
                class="form-input"
                value="<?= htmlspecialchars($dateFrom) ?>">


            <!-- DATE TO -->

            <label class="form-label">
                Date To
            </label>

            <input
                type="date"
                name="date_to"
                class="form-input"
                value="<?= htmlspecialchars($dateTo) ?>">


            <!-- REPORT TYPE -->

            <label class="form-label">
                Report Type
            </label>

            <select
                name="report_type"
                class="form-input">

                <option
                    value="orders"
                    <?= $reportType === 'orders'
                        ? 'selected'
                        : '' ?>>

                    Orders

                </option>

                <option
                    value="drivers"
                    <?= $reportType === 'drivers'
                        ? 'selected'
                        : '' ?>>

                    Drivers

                </option>

                <option
                    value="trucks"
                    <?= $reportType === 'trucks'
                        ? 'selected'
                        : '' ?>>

                    Trucks

                </option>

                <option
                    value="customers"
                    <?= $reportType === 'customers'
                        ? 'selected'
                        : '' ?>>

                    Customers

                </option>

                <option
                    value="audit"
                    <?= $reportType === 'audit'
                        ? 'selected'
                        : '' ?>>

                    Audit Logs

                </option>

            </select>


            <!-- GENERATE -->

            <button
                type="submit"
                name="generate"
                value="1"
                class="btn-primary">

                Generate

            </button>


            <!-- RESET -->

            <a
                href="reports.php"
                class="btn-secondary">

                Reset

            </a>

        </div>

    </form>

</div>


<br>


<!-- ==========================================================
     REPORT RESULT
========================================================== -->

<div class="card panel">


    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:20px;
    ">

        <div>

            <h2>

                <?= $reportGenerated
                    ? htmlspecialchars($reportTitle)
                    : 'Report Result' ?>

            </h2>


            <?php if ($reportGenerated): ?>

                <p style="
                    color:var(--color-text-secondary);
                ">

                    Date Range:

                    <?= date(
                        'M d, Y',
                        strtotime($dateFrom)
                    ) ?>

                    –

                    <?= date(
                        'M d, Y',
                        strtotime($dateTo)
                    ) ?>

                </p>

            <?php endif; ?>

        </div>


        <?php if (
            $reportGenerated &&
            !empty($reportData)
        ): ?>

            <button
                type="button"
                class="btn-secondary"
                onclick="window.print()">

                Print Report

            </button>

        <?php endif; ?>

    </div>


    <!-- ======================================================
         NO REPORT YET
    ======================================================= -->

    <?php if (!$reportGenerated): ?>

        <div class="empty-state">

            No report generated yet.

        </div>


    <!-- ======================================================
         NO RESULTS
    ======================================================= -->

    <?php elseif (empty($reportData)): ?>

        <div class="empty-state">

            No records found for the selected criteria.

        </div>


    <?php else: ?>


        <!-- ==================================================
             ORDERS REPORT
        ================================================== -->

        <?php if ($reportType === 'orders'): ?>

            <table class="data-table">

                <thead>

                    <tr>

                        <th>Order #</th>

                        <th>Customer</th>

                        <th>Driver</th>

                        <th>Truck</th>

                        <th>Order Date</th>

                        <th>Expected Delivery</th>

                        <th>Priority</th>

                        <th>Status</th>

                        <th>Total Amount</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($reportData as $row): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $row['order_number']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['customer_name'] ?? '-'
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['driver_name'] ?? '-'
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['plate_number'] ?? '-'
                            ) ?>
                        </td>


                        <td>
                            <?= date(
                                'M d, Y',
                                strtotime($row['order_date'])
                            ) ?>
                        </td>


                        <td>
                            <?= !empty(
                                $row['expected_delivery_date']
                            )
                                ? date(
                                    'M d, Y',
                                    strtotime(
                                        $row['expected_delivery_date']
                                    )
                                )
                                : '-'
                            ?>
                        </td>


                        <td>

                            <span class="status-pill">

                                <?= htmlspecialchars(
                                    $row['priority_level']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span class="status-pill">

                                <?= htmlspecialchars(
                                    $row['completion_status']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            ₱<?= number_format(
                                (float)$row['total_amount'],
                                2
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>


        <!-- ==================================================
             DRIVERS REPORT
        ================================================== -->

        <?php elseif ($reportType === 'drivers'): ?>

            <table class="data-table">

                <thead>

                    <tr>

                        <th>Driver</th>

                        <th>License</th>

                        <th>Phone</th>

                        <th>Email</th>

                        <th>Status</th>

                        <th>Total Deliveries</th>

                        <th>Rating</th>

                        <th>Last Location</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($reportData as $row): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $row['full_name']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['license_number']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['phone_number']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['email']
                            ) ?>
                        </td>


                        <td>

                            <span class="status-pill">

                                <?= htmlspecialchars(
                                    $row['status']
                                ) ?>

                            </span>

                        </td>


                        <td>
                            <?= (int)$row['total_deliveries'] ?>
                        </td>


                        <td>

                            <?= $row['rating'] !== null
                                ? number_format(
                                    (float)$row['rating'],
                                    1
                                ) . '/5'
                                : '-'
                            ?>

                        </td>


                        <td>

                            <?= !empty($row['last_location'])
                                ? htmlspecialchars(
                                    $row['last_location']
                                )
                                : '-'
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>


        <!-- ==================================================
             TRUCKS REPORT
        ================================================== -->

        <?php elseif ($reportType === 'trucks'): ?>

            <table class="data-table">

                <thead>

                    <tr>

                        <th>Plate Number</th>

                        <th>Truck Model</th>

                        <th>Capacity (kg)</th>

                        <th>Status</th>

                        <th>Assigned Driver</th>

                        <th>Last Maintenance</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($reportData as $row): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $row['plate_number']
                                ) ?>
                            </strong>

                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['model']
                            ) ?>
                        </td>


                        <td>

                            <?= number_format(
                                (float)$row['capacity'],
                                2
                            ) ?>

                            kg

                        </td>


                        <td>

                            <span class="status-pill">

                                <?= htmlspecialchars(
                                    $row['status']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= !empty($row['driver_name'])
                                ? htmlspecialchars(
                                    $row['driver_name']
                                )
                                : 'Unassigned'
                            ?>

                        </td>


                        <td>

                            <?= !empty(
                                $row['last_maintenance']
                            )
                                ? date(
                                    'M d, Y',
                                    strtotime(
                                        $row['last_maintenance']
                                    )
                                )
                                : '-'
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>


        <!-- ==================================================
             CUSTOMERS REPORT
        ================================================== -->

        <?php elseif ($reportType === 'customers'): ?>

            <table class="data-table">

                <thead>

                    <tr>

                        <th>Customer</th>

                        <th>Email</th>

                        <th>Phone</th>

                        <th>Address</th>

                        <th>City</th>

                        <th>Province</th>

                        <th>Date Registered</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($reportData as $row): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $row['full_name']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['email']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['phone_number']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['address']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['city']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['province']
                            ) ?>
                        </td>


                        <td>

                            <?= !empty($row['created_at'])
                                ? date(
                                    'M d, Y',
                                    strtotime(
                                        $row['created_at']
                                    )
                                )
                                : '-'
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>


        <!-- ==================================================
             AUDIT LOG REPORT
        ================================================== -->

        <?php elseif ($reportType === 'audit'): ?>

            <table class="data-table">

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>User</th>

                        <th>Role</th>

                        <th>Action</th>

                        <th>Module</th>

                        <th>Description</th>

                        <th>IP Address</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($reportData as $row): ?>

                    <tr>

                        <td>

                            <?= date(
                                'M d, Y h:i A',
                                strtotime(
                                    $row['created_at']
                                )
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['full_name'] ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['role'] ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <span class="status-pill">

                                <?= htmlspecialchars(
                                    $row['action'] ?? '-'
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['module'] ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['description'] ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['ip_address'] ?? '-'
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>


    <?php endif; ?>

</div>


</main>

</div>

</body>

</html>