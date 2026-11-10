<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

/* ==========================================================
   AUDIT STATISTICS
========================================================== */

$totalLogs = $db->query("
SELECT COUNT(*)
FROM audit_log
")->fetchColumn();

$todayLogins = $db->query("
SELECT COUNT(*)
FROM audit_log
WHERE action='LOGIN'
AND DATE(created_at)=CURDATE()
")->fetchColumn();

$todayLogouts = $db->query("
SELECT COUNT(*)
FROM audit_log
WHERE action='LOGOUT'
AND DATE(created_at)=CURDATE()
")->fetchColumn();

$todayCreates = $db->query("
SELECT COUNT(*)
FROM audit_log
WHERE action='CREATE'
AND DATE(created_at)=CURDATE()
")->fetchColumn();

$todayUpdates = $db->query("
SELECT COUNT(*)
FROM audit_log
WHERE action='UPDATE'
AND DATE(created_at)=CURDATE()
")->fetchColumn();

$todayDeletes = $db->query("
SELECT COUNT(*)
FROM audit_log
WHERE action='DELETE'
AND DATE(created_at)=CURDATE()
")->fetchColumn();

/* ==========================================================
   RECENT AUDIT LOGS
========================================================== */

$recentLogs = $db->query("
SELECT
    full_name,
    role,
    action,
    module,
    description,
    created_at
FROM audit_log
ORDER BY created_at DESC
LIMIT 10
")->fetchAll();

// System-wide stats
$totalOrders   = $db->query("SELECT COUNT(*) FROM `order`")->fetchColumn();
$totalCustomers = $db->query("SELECT COUNT(*) FROM customer")->fetchColumn();

$driverStats = $db->query(
    "SELECT
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status = 'On Delivery' THEN 1 ELSE 0 END) AS on_delivery,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent,
        COUNT(*) AS total
     FROM driver"
)->fetch();

$truckStats = $db->query(
    "SELECT
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status = 'In Use' THEN 1 ELSE 0 END) AS in_use,
        SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) AS maintenance,
        COUNT(*) AS total
     FROM truck"
)->fetch();

// Orders needing attention (pending approval)
$pendingOrdersStmt = $db->query(
    "SELECT o.order_id, o.order_number, c.full_name AS customer_name,
            o.order_date, o.priority_level, o.completion_status
     FROM `order` o
     JOIN customer c ON c.customer_id = o.customer_id
     WHERE o.completion_status = 'Pending'
     ORDER BY o.order_date ASC
     LIMIT 8"
);
$pendingOrders = $pendingOrdersStmt->fetchAll();

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
]
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard · Super Admin · Hong Sing LKRR Commercial</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
</head>
<body>

<div class="app-layout">
    <?php require __DIR__ . '/../includes/header.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>System Overview</h1>
                <p>Full visibility into orders, fleet, and personnel.</p>
            </div>
            <div class="quick-actions">
                <a href="<?= BASE_URL ?>super_admin/manage_drivers.php" class="btn-primary" style="text-decoration:none;">Manage Drivers</a>
                <a href="<?= BASE_URL ?>super_admin/manage_trucks.php" class="btn-secondary">Manage Trucks</a>
            </div>
        </div>

        <div class="stat-grid">
            <div class="card stat-card accent">
                <span class="stat-label">Total Orders</span>
                <div class="stat-value"><?= (int) $totalOrders ?></div>
            </div>
        <div class="card stat-card">
    <span class="stat-label">Today's Logins</span>
    <div class="stat-value"><?= $todayLogins ?></div>
</div>

<div class="card stat-card">
    <span class="stat-label">Today's Logouts</span>
    <div class="stat-value"><?= $todayLogouts ?></div>
</div>

<div class="card stat-card">
    <span class="stat-label">Today's Changes</span>
    <div class="stat-value">
        <?= $todayCreates + $todayUpdates + $todayDeletes ?>
    </div>
</div>

<div class="card stat-card accent">
    <span class="stat-label">Total Audit Logs</span>
    <div class="stat-value"><?= number_format($totalLogs) ?></div>
</div>    
            <div class="card stat-card">
                <span class="stat-label">Total Customers</span>
                <div class="stat-value"><?= (int) $totalCustomers ?></div>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Drivers Available</span>
                <div class="stat-value"><?= (int) ($driverStats['available'] ?? 0) ?> / <?= (int) ($driverStats['total'] ?? 0) ?></div>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Trucks Available</span>
                <div class="stat-value"><?= (int) ($truckStats['available'] ?? 0) ?> / <?= (int) ($truckStats['total'] ?? 0) ?></div>
            </div>
        </div>

        <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
            <div class="card panel">
                <h2>Driver Status</h2>
                <table class="data-table">
                    <tbody>
                        <tr><td>Available</td><td><span class="status-pill status-delivered"><?= (int) ($driverStats['available'] ?? 0) ?></span></td></tr>
                        <tr><td>On Delivery</td><td><span class="status-pill status-transit"><?= (int) ($driverStats['on_delivery'] ?? 0) ?></span></td></tr>
                        <tr><td>Absent</td><td><span class="status-pill status-failed"><?= (int) ($driverStats['absent'] ?? 0) ?></span></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card panel">
                <h2>Truck Status</h2>
                <table class="data-table">
                    <tbody>
                        <tr><td>Available</td><td><span class="status-pill status-delivered"><?= (int) ($truckStats['available'] ?? 0) ?></span></td></tr>
                        <tr><td>In Use</td><td><span class="status-pill status-transit"><?= (int) ($truckStats['in_use'] ?? 0) ?></span></td></tr>
                        <tr><td>Maintenance</td><td><span class="status-pill status-pending"><?= (int) ($truckStats['maintenance'] ?? 0) ?></span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card panel">
            <h2>Orders Awaiting Approval</h2>

            <?php if (empty($pendingOrders)): ?>
                <div class="empty-state">No pending orders right now — all caught up.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Order Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingOrders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                <td><?= htmlspecialchars($order['priority_level']) ?></td>
                                <td><span class="status-pill status-pending"><?= htmlspecialchars($order['completion_status']) ?></span></td>
                                <td>
                                    <a href="<?= BASE_URL ?>admin/orders/view.php?order_id=<?= (int) $order['order_id'] ?>">Review</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card panel">

<h2>Recent System Activity</h2>

<?php if(empty($recentLogs)): ?>

<div class="empty-state">
No activities recorded.
</div>

<?php else: ?>

<table class="data-table">

<thead>

<tr>

<th>Time</th>
<th>User</th>
<th>Role</th>
<th>Action</th>
<th>Module</th>
<th>Description</th>

</tr>

</thead>

<tbody>

<?php foreach($recentLogs as $log): ?>

<?php

$class = "status-pending";

switch($log['action']){

    case "LOGIN":
    case "CREATE":
        $class="status-delivered";
        break;

    case "LOGOUT":
    case "UPDATE":
        $class="status-transit";
        break;

    case "DELETE":
        $class="status-failed";
        break;

}

?>

<tr>

<td>

<?= date("M d, Y h:i A",strtotime($log['created_at'])) ?>

</td>

<td>

<?= htmlspecialchars($log['full_name']) ?>

</td>

<td>

<?= htmlspecialchars($log['role']) ?>

</td>

<td>

<span class="status-pill <?= $class ?>">

<?= htmlspecialchars($log['action']) ?>

</span>

</td>

<td>

<?= htmlspecialchars($log['module']) ?>

</td>

<td>

<?= htmlspecialchars($log['description']) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

</div>
    </main>
</div>



</body>
</html>