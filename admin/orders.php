<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();
$adminId = currentUserId();
$adminId = $adminId !== null ? (int) $adminId : null;

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| APPROVE ORDER
|--------------------------------------------------------------------------
*/

if (isset($_POST['approve_order'])) {

    $orderId = (int) ($_POST['order_id'] ?? 0);

    if ($orderId <= 0) {
        header('Location: orders.php?error=invalid_order');
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT
                order_id,
                order_number,
                customer_id,
                completion_status
            FROM `order`
            WHERE order_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $db->rollBack();
            header('Location: orders.php?error=order_not_found');
            exit;
        }

        if ($order['completion_status'] !== 'Pending') {
            $db->rollBack();
            header('Location: orders.php?error=not_pending');
            exit;
        }

        $updateStmt = $db->prepare("
            UPDATE `order`
            SET
                completion_status = 'Approved',
                approved_at = NOW()
            WHERE order_id = ?
              AND completion_status = 'Pending'
        ");
        $updateStmt->execute([$orderId]);

        if ($updateStmt->rowCount() !== 1) {
            $db->rollBack();
            header('Location: orders.php?error=update_failed');
            exit;
        }

        notifyOrderRoles(
            $db,
            $orderId,
            [
                'Customer' =>
                    "Your order {$order['order_number']} has been approved. "
                    . "It is now being prepared for delivery assignment.",
                'Admin' =>
                    "Order {$order['order_number']} has been approved "
                    . "and is ready for driver and truck assignment.",
                'Super Admin' =>
                    "Order {$order['order_number']} has been approved "
                    . "and is ready for delivery assignment."
            ],
            null,
            $adminId
        );

        $db->commit();
        header('Location: orders.php?success=approved');
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log('Approve order failed: ' . $e->getMessage());
        header('Location: orders.php?error=action_failed');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CANCEL ORDER
|--------------------------------------------------------------------------
*/

if (isset($_POST['cancel_order'])) {

    $orderId = (int) ($_POST['order_id'] ?? 0);

    if ($orderId <= 0) {
        header('Location: orders.php?error=invalid_order');
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT
                order_id,
                order_number,
                customer_id,
                completion_status
            FROM `order`
            WHERE order_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $db->rollBack();
            header('Location: orders.php?error=order_not_found');
            exit;
        }

        if (!in_array($order['completion_status'], ['Pending', 'Approved'], true)) {
            $db->rollBack();
            header('Location: orders.php?error=not_cancellable');
            exit;
        }

        $updateStmt = $db->prepare("
            UPDATE `order`
            SET
                completion_status = 'Cancelled',
                cancelled_at = NOW()
            WHERE order_id = ?
              AND completion_status IN ('Pending', 'Approved')
        ");
        $updateStmt->execute([$orderId]);

        if ($updateStmt->rowCount() !== 1) {
            $db->rollBack();
            header('Location: orders.php?error=update_failed');
            exit;
        }

        notifyOrderRoles(
            $db,
            $orderId,
            [
                'Customer' =>
                    "Your order {$order['order_number']} has been cancelled.",
                'Admin' =>
                    "Order {$order['order_number']} has been cancelled.",
                'Super Admin' =>
                    "Order {$order['order_number']} has been cancelled."
            ],
            null,
            $adminId
        );

        $db->commit();
        header('Location: orders.php?success=cancelled');
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log('Cancel order failed: ' . $e->getMessage());
        header('Location: orders.php?error=action_failed');
        exit;
    }
}


/*

|--------------------------------------------------------------------------

| FILTERS

|--------------------------------------------------------------------------

*/



$search = trim($_GET['search'] ?? '');

$priorityFilter = trim($_GET['priority'] ?? '');

$category = trim($_GET['category'] ?? 'new');

$page = max(1, (int) ($_GET['page'] ?? 1));

$perPage = 10;



$validPriorities = [

    'Normal',

    'Urgent'

];



if (!in_array($priorityFilter, $validPriorities, true)) {

    $priorityFilter = '';

}



/*

|--------------------------------------------------------------------------

| ORDER CATEGORIES

|--------------------------------------------------------------------------

|

| New Orders are always the default/first category.

| Pending + Approved = New Orders

| Assigned + Accepted = Assigned Orders

*/



$orderCategories = [

    'new' => [

        'label' => 'New Orders',

        'statuses' => ['Pending', 'Approved']

    ],

    'assigned' => [

        'label' => 'Assigned Orders',

        'statuses' => ['Assigned', 'Accepted']

    ],

    'transit' => [

        'label' => 'In Transit',

        'statuses' => ['In Transit']

    ],

    'completed' => [

        'label' => 'Completed Orders',

        'statuses' => ['Completed']

    ],

    'cancelled' => [

        'label' => 'Cancelled',

        'statuses' => ['Cancelled']

    ]

];



if (!isset($orderCategories[$category])) {

    $category = 'new';

}



$activeCategory = $orderCategories[$category];



/*

|--------------------------------------------------------------------------

| CATEGORY COUNTS

|--------------------------------------------------------------------------

*/



$categoryCounts = [];



foreach ($orderCategories as $key => $categoryConfig) {

    $placeholders = implode(',', array_fill(0, count($categoryConfig['statuses']), '?'));



    $countStmt = $db->prepare("

        SELECT COUNT(*)

        FROM `order`

        WHERE completion_status IN ($placeholders)

    ");



    $countStmt->execute($categoryConfig['statuses']);

    $categoryCounts[$key] = (int) $countStmt->fetchColumn();

}



/*

|--------------------------------------------------------------------------

| BUILD FILTERED ORDER QUERY

|--------------------------------------------------------------------------

*/



$where = [];

$params = [];



$statusPlaceholders = implode(

    ',',

    array_fill(0, count($activeCategory['statuses']), '?')

);



$where[] = "o.completion_status IN ($statusPlaceholders)";



foreach ($activeCategory['statuses'] as $status) {

    $params[] = $status;

}



if ($search !== '') {

    $where[] = "(

        o.order_number LIKE ?

        OR c.full_name LIKE ?

        OR d.full_name LIKE ?

        OR t.plate_number LIKE ?

    )";



    $searchValue = "%{$search}%";



    $params[] = $searchValue;

    $params[] = $searchValue;

    $params[] = $searchValue;

    $params[] = $searchValue;

}



if ($priorityFilter !== '') {

    $where[] = "o.priority_level = ?";

    $params[] = $priorityFilter;

}



$whereSql = implode(' AND ', $where);



/*

|--------------------------------------------------------------------------

| COUNT FILTERED RESULTS FOR PAGINATION

|--------------------------------------------------------------------------

*/



$countSql = "

    SELECT COUNT(*)

    FROM `order` o

    LEFT JOIN customer c ON c.customer_id = o.customer_id

    LEFT JOIN driver d ON d.driver_id = o.driver_id

    LEFT JOIN truck t ON t.truck_id = o.truck_id

    WHERE $whereSql

";



$countStmt = $db->prepare($countSql);

$countStmt->execute($params);



$filteredTotal = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($filteredTotal / $perPage));



if ($page > $totalPages) {

    $page = $totalPages;

}



$offset = ($page - 1) * $perPage;



/*

|--------------------------------------------------------------------------

| FETCH CURRENT PAGE

|--------------------------------------------------------------------------

*/



$sql = "

    SELECT

        o.order_id,

        o.order_number,

        o.item_description,

        o.subtotal,

        o.urgent_fee,

        o.total_amount,

        o.order_date,

        o.priority_level,

        o.completion_status,

        o.driver_id,

        o.truck_id,

        c.full_name AS customer_name,

        d.full_name AS driver_name,

        t.plate_number,

        t.model

    FROM `order` o

    LEFT JOIN customer c ON c.customer_id = o.customer_id

    LEFT JOIN driver d ON d.driver_id = o.driver_id

    LEFT JOIN truck t ON t.truck_id = o.truck_id

    WHERE $whereSql

    ORDER BY

        CASE

            WHEN o.completion_status = 'Pending' THEN 1

            WHEN o.completion_status = 'Approved' THEN 2

            WHEN o.completion_status = 'Assigned' THEN 3

            WHEN o.completion_status = 'Accepted' THEN 4

            WHEN o.completion_status = 'In Transit' THEN 5

            WHEN o.completion_status = 'Completed' THEN 6

            WHEN o.completion_status = 'Cancelled' THEN 7

            ELSE 8

        END,

        o.order_id DESC

    LIMIT $perPage OFFSET $offset

";



$stmt = $db->prepare($sql);

$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);



/*

|--------------------------------------------------------------------------

| SUMMARY COUNTS

|--------------------------------------------------------------------------

*/



$totalOrders = array_sum($categoryCounts);

$totalPending = (int) $db->query("

    SELECT COUNT(*) FROM `order` WHERE completion_status = 'Pending'

")->fetchColumn();



$totalApproved = (int) $db->query("

    SELECT COUNT(*) FROM `order` WHERE completion_status = 'Approved'

")->fetchColumn();



$totalAssigned = (int) $db->query("

    SELECT COUNT(*) FROM `order` WHERE completion_status IN ('Assigned', 'Accepted')

")->fetchColumn();



$totalInTransit = $categoryCounts['transit'];

$totalCompleted = $categoryCounts['completed'];



/*

|--------------------------------------------------------------------------

| URL HELPER

|--------------------------------------------------------------------------

*/



function ordersPageUrl(array $changes = []): string

{

    global $category, $search, $priorityFilter;



    $query = [

        'category' => $category

    ];



    if ($search !== '') {

        $query['search'] = $search;

    }



    if ($priorityFilter !== '') {

        $query['priority'] = $priorityFilter;

    }



    foreach ($changes as $key => $value) {

        if ($value === null || $value === '') {

            unset($query[$key]);

        } else {

            $query[$key] = $value;

        }

    }



    return BASE_URL . 'admin/orders.php?' . http_build_query($query);

}



?>



<!DOCTYPE html>

<html lang="en">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Manage Orders</title>



    <link

        rel="stylesheet"

        href="<?= BASE_URL ?>assets/css/theme.css"

    >



    <style>



        /* =========================================================

           ORDERS PAGE

        ========================================================= */



        .orders-page {

            width: 100%;

        }



        /* =========================================================

           PAGE HEADER

        ========================================================= */



        .orders-header {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 24px;

            margin-bottom: 28px;

        }



        .orders-header-content h1 {

            margin: 0 0 8px;

            font-size: 30px;

            line-height: 1.2;

            color: var(--color-text-primary);

            font-weight: 700;

        }



        .orders-header-content p {

            margin: 0;

            color: var(--color-text-secondary);

            font-size: 14px;

        }



        .orders-count {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 10px 15px;

            border: 1px solid var(--color-border);

            background: var(--color-surface);

            border-radius: var(--radius-md);

            color: var(--color-text-secondary);

            font-size: 13px;

            white-space: nowrap;

        }



        .orders-count strong {

            color: var(--color-text-primary);

            font-size: 15px;

        }



        /* =========================================================

           STAT CARDS

        ========================================================= */



        .orders-stat-grid {

            display: grid;

            grid-template-columns: repeat(6, minmax(0, 1fr));

            gap: 16px;

            margin-bottom: 24px;

        }



        .orders-stat-card {

            position: relative;

            min-height: 118px;

            padding: 20px;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            background: var(--color-surface);

            box-shadow: var(--shadow-card);

            overflow: hidden;

        }



        .orders-stat-card::after {

            content: "";

            position: absolute;

            left: 0;

            right: 0;

            bottom: 0;

            height: 3px;

            background: var(--color-border);

        }



        .orders-stat-card.stat-total::after {

            background: var(--color-accent);

        }



        .orders-stat-card.stat-pending::after {

            background: var(--color-warning);

        }



        .orders-stat-card.stat-approved::after {

            background: var(--color-info);

        }



        .orders-stat-card.stat-assigned::after {

            background: var(--color-accent);

        }



        .orders-stat-card.stat-transit::after {

            background: var(--color-info);

        }



        .orders-stat-card.stat-completed::after {

            background: var(--color-success);

        }



        .orders-stat-label {

            display: block;

            margin-bottom: 12px;

            color: var(--color-text-secondary);

            font-size: 13px;

            font-weight: 500;

        }



        .orders-stat-value {

            display: block;

            color: var(--color-text-primary);

            font-size: 30px;

            line-height: 1;

            font-weight: 700;

        }



        /* =========================================================

           ALERTS

        ========================================================= */



        .orders-alert {

            display: flex;

            align-items: center;

            gap: 12px;

            width: 100%;

            padding: 14px 16px;

            margin-bottom: 20px;

            border-radius: var(--radius-md);

            border: 1px solid;

            font-size: 14px;

            box-sizing: border-box;

        }



        .orders-alert-success {

            background: var(--color-accent-soft);

            border-color: var(--color-success);

            color: var(--color-text-primary);

        }



        .orders-alert-error {

            background: var(--color-accent-soft);

            border-color: var(--color-danger);

            color: var(--color-text-primary);

        }



        .alert-symbol {

            width: 28px;

            height: 28px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            flex: 0 0 28px;

            border-radius: 50%;

            font-weight: 700;

            background: var(--color-surface);

        }



        /* =========================================================

           FILTER CARD

        ========================================================= */



        .orders-filter-card {

            width: 100%;

            padding: 22px;

            margin-bottom: 24px;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            background: var(--color-surface);

            box-shadow: var(--shadow-card);

            box-sizing: border-box;

        }



        .orders-filter-header {

            margin-bottom: 18px;

        }



        .orders-filter-header h2 {

            margin: 0 0 5px;

            font-size: 17px;

            color: var(--color-text-primary);

        }



        .orders-filter-header p {

            margin: 0;

            color: var(--color-text-muted);

            font-size: 13px;

        }



        .orders-filter-form {

            display: grid;

            grid-template-columns: minmax(280px, 1fr) 190px 180px auto auto;

            gap: 12px;

            align-items: center;

        }



        .orders-input,

        .orders-select {

            width: 100%;

            height: 44px;

            box-sizing: border-box;

            padding: 0 13px;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-md);

            background: var(--color-bg);

            color: var(--color-text-primary);

            font-family: inherit;

            font-size: 14px;

            outline: none;

            transition:

                border-color .15s ease,

                box-shadow .15s ease,

                background .15s ease;

        }



        .orders-input:focus,

        .orders-select:focus {

            border-color: var(--color-accent);

            box-shadow: 0 0 0 3px var(--color-accent-soft);

        }



        .orders-input::placeholder {

            color: var(--color-text-muted);

        }



        .orders-btn {

            height: 44px;

            padding: 0 17px;

            border-radius: var(--radius-md);

            border: 1px solid transparent;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            font-family: inherit;

            font-size: 13px;

            font-weight: 600;

            text-decoration: none;

            cursor: pointer;

            white-space: nowrap;

            box-sizing: border-box;

            transition:

                transform .15s ease,

                box-shadow .15s ease,

                background .15s ease;

        }



        .orders-btn:hover {

            transform: translateY(-1px);

        }



        .orders-btn-primary {

            background: var(--color-accent);

            color: #fff;

        }



        .orders-btn-primary:hover {

            background: var(--color-accent-hover);

            box-shadow: var(--shadow-card);

        }



        .orders-btn-secondary {

            background: var(--color-surface);

            color: var(--color-text-secondary);

            border-color: var(--color-border);

        }



        .orders-btn-secondary:hover {

            background: var(--color-surface-alt);

            color: var(--color-text-primary);

        }



        /* =========================================================

           TABLE CARD

        ========================================================= */





        .order-category-tabs {

            display: flex;

            gap: 10px;

            margin: 0 0 20px;

            padding: 6px;

            overflow-x: auto;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            background: var(--color-surface);

        }



        .order-category-tab {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            min-width: max-content;

            padding: 11px 15px;

            border-radius: var(--radius-md);

            color: var(--color-text-secondary);

            text-decoration: none;

            font-size: 13px;

            font-weight: 700;

            transition: .18s ease;

        }



        .order-category-tab:hover {

            background: var(--color-surface-alt);

            color: var(--color-text-primary);

        }



        .order-category-tab.active {

            background: var(--color-accent);

            color: #fff;

        }



        .category-count {

            min-width: 23px;

            padding: 3px 7px;

            border-radius: 999px;

            background: rgba(255,255,255,.16);

            text-align: center;

            font-size: 11px;

            font-weight: 800;

        }



        .order-category-tab:not(.active) .category-count {

            background: var(--color-surface-alt);

            color: var(--color-text-muted);

        }



        .orders-pagination-wrap {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 14px;

            padding: 18px 20px;

            border-top: 1px solid var(--color-border);

            flex-wrap: wrap;

        }



        .orders-pagination-info {

            color: var(--color-text-muted);

            font-size: 12px;

        }



        .orders-pagination {

            display: flex;

            align-items: center;

            gap: 6px;

            flex-wrap: wrap;

        }



        .page-link {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 36px;

            height: 36px;

            padding: 0 10px;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-sm);

            background: var(--color-surface);

            color: var(--color-text-secondary);

            text-decoration: none;

            font-size: 12px;

            font-weight: 700;

        }



        .page-link:hover {

            border-color: var(--color-accent);

            color: var(--color-accent);

        }



        .page-link.active {

            border-color: var(--color-accent);

            background: var(--color-accent);

            color: #fff;

        }



        .page-link.disabled {

            opacity: .45;

            pointer-events: none;

        }



        .orders-table-card {

            width: 100%;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            background: var(--color-surface);

            box-shadow: var(--shadow-card);

            overflow: hidden;

        }



        .orders-table-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            padding: 22px 24px;

            border-bottom: 1px solid var(--color-border);

        }



        .orders-table-title h2 {

            margin: 0 0 5px;

            font-size: 18px;

            color: var(--color-text-primary);

        }



        .orders-table-title p {

            margin: 0;

            color: var(--color-text-muted);

            font-size: 13px;

        }



        .orders-results {

            display: inline-flex;

            align-items: center;

            padding: 8px 12px;

            border-radius: 999px;

            background: var(--color-surface-alt);

            color: var(--color-text-secondary);

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;

        }



        .orders-table-scroll {

            width: 100%;

            overflow-x: auto;

        }



        .orders-table {

            width: 100%;

            min-width: 1180px;

            border-collapse: collapse;

            table-layout: auto;

        }



        .orders-table th {

            padding: 13px 16px;

            text-align: left;

            background: var(--color-surface-alt);

            border-bottom: 1px solid var(--color-border);

            color: var(--color-text-secondary);

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .05em;

            white-space: nowrap;

        }



        .orders-table td {

            padding: 16px;

            border-bottom: 1px solid var(--color-border);

            color: var(--color-text-primary);

            font-size: 13px;

            vertical-align: middle;

        }



        .orders-table tbody tr {

            transition: background .15s ease;

        }



        .orders-table tbody tr:hover {

            background: var(--color-surface-alt);

        }



        .orders-table tbody tr:last-child td {

            border-bottom: none;

        }



        /* =========================================================

           ORDER CELL

        ========================================================= */



        .order-number {

            display: block;

            margin-bottom: 5px;

            color: var(--color-text-primary);

            font-size: 13px;

            font-weight: 700;

            white-space: nowrap;

        }



        .order-description {

            display: block;

            max-width: 180px;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

            color: var(--color-text-muted);

            font-size: 11px;

        }



        /* =========================================================

           CUSTOMER

        ========================================================= */



        .customer-cell {

            display: flex;

            align-items: center;

            gap: 10px;

            min-width: 150px;

        }



        .customer-avatar {

            width: 34px;

            height: 34px;

            flex: 0 0 34px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: var(--color-accent-soft);

            color: var(--color-accent);

            font-size: 12px;

            font-weight: 700;

        }



        .customer-name {

            font-weight: 600;

            color: var(--color-text-primary);

        }



        /* =========================================================

           DATES

        ========================================================= */



        .date-main {

            display: block;

            color: var(--color-text-primary);

            font-weight: 600;

            white-space: nowrap;

        }



        .date-label {

            display: block;

            margin-top: 4px;

            color: var(--color-text-muted);

            font-size: 11px;

        }



        /* =========================================================

           ASSIGNMENT

        ========================================================= */



        .assignment-main {

            display: block;

            color: var(--color-text-primary);

            font-weight: 600;

        }



        .assignment-sub {

            display: block;

            margin-top: 4px;

            color: var(--color-text-muted);

            font-size: 11px;

        }



        .unassigned {

            color: var(--color-text-muted);

            font-size: 12px;

            font-style: italic;

        }



        /* =========================================================

           BADGES

        ========================================================= */



        .orders-badge {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 28px;

            padding: 4px 10px;

            border-radius: 999px;

            box-sizing: border-box;

            font-size: 11px;

            font-weight: 700;

            white-space: nowrap;

        }



        .badge-urgent {

            background: var(--color-accent-soft);

            color: var(--color-danger);

        }



        .badge-normal {

            background: var(--color-surface-alt);

            color: var(--color-text-secondary);

        }



        .status-pending {

            background: var(--color-accent-soft);

            color: var(--color-warning);

        }



        .status-approved {

            background: var(--color-accent-soft);

            color: var(--color-info);

        }



        .status-assigned {

            background: var(--color-accent-soft);

            color: var(--color-accent);

        }



        .status-transit {

            background: var(--color-accent-soft);

            color: var(--color-info);

        }



        .status-delivered {

            background: var(--color-accent-soft);

            color: var(--color-success);

        }



        .status-failed {

            background: var(--color-accent-soft);

            color: var(--color-danger);

        }



        /* =========================================================

           ACTIONS

        ========================================================= */



        .orders-actions {

            display: flex;

            align-items: center;

            gap: 7px;

            flex-wrap: wrap;

            min-width: 145px;

        }



        .action-btn {

            min-height: 34px;

            padding: 7px 11px;

            border-radius: var(--radius-sm);

            border: 1px solid transparent;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            font-family: inherit;

            font-size: 11px;

            font-weight: 700;

            cursor: pointer;

            text-decoration: none;

            white-space: nowrap;

            box-sizing: border-box;

            transition:

                background .15s ease,

                transform .15s ease;

        }



        .action-btn:hover {

            transform: translateY(-1px);

        }



        .action-approve {

            background: var(--color-success);

            color: #fff;

        }



        .action-approve:hover {

            opacity: .9;

        }



        .action-cancel {

            background: var(--color-danger);

            color: #fff;

        }



        .action-cancel:hover {

            opacity: .9;

        }



        .action-assign {

            background: var(--color-accent);

            color: #fff;

        }



        .action-assign:hover {

            background: var(--color-accent-hover);

        }



        .action-view {

            background: var(--color-warning);

            color: #fff;

        }



        .no-action {

            color: var(--color-text-muted);

            font-size: 11px;

        }



        /* =========================================================

           EMPTY STATE

        ========================================================= */



        .orders-empty {

            padding: 70px 24px;

            text-align: center;

        }



        .orders-empty-icon {

            width: 52px;

            height: 52px;

            margin: 0 auto 16px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: var(--color-surface-alt);

            color: var(--color-text-muted);

            font-size: 22px;

        }



        .orders-empty h3 {

            margin: 0 0 7px;

            color: var(--color-text-primary);

            font-size: 16px;

        }



        .orders-empty p {

            margin: 0;

            color: var(--color-text-muted);

            font-size: 13px;

        }



        /* =========================================================

           RESPONSIVE

        ========================================================= */



        @media (max-width: 1250px) {



            .orders-stat-grid {

                grid-template-columns: repeat(3, minmax(0, 1fr));

            }



            .orders-filter-form {

                grid-template-columns: 1fr 1fr;

            }



            .orders-filter-form .orders-input {

                grid-column: 1 / -1;

            }



        }



        @media (max-width: 800px) {



            .orders-header {

                align-items: flex-start;

                flex-direction: column;

            }



            .orders-stat-grid {

                grid-template-columns: repeat(2, minmax(0, 1fr));

            }



            .orders-filter-form {

                grid-template-columns: 1fr;

            }



            .orders-filter-form .orders-input {

                grid-column: auto;

            }



            .orders-btn {

                width: 100%;

            }



            .orders-table-header {

                align-items: flex-start;

                flex-direction: column;

            }



        }



        @media (max-width: 520px) {



            .orders-stat-grid {

                grid-template-columns: 1fr;

            }



            .orders-stat-card {

                min-height: 100px;

            }



            .orders-filter-card {

                padding: 16px;

            }



            .orders-table-header {

                padding: 18px;

            }



        }



    </style>



</head>





<body>



<?php

require __DIR__ . '/../includes/header.php';

?>





<main class="app-main">



    <div class="orders-page">





        <!-- =====================================================

             PAGE HEADER

        ====================================================== -->



        <header class="orders-header">



            <div class="orders-header-content">



                <h1>Orders</h1>



                <p>

                    Review, approve, assign, and manage delivery orders.

                </p>



            </div>



            <div class="orders-count">

                <span>Total</span>

                <strong><?= (int) $totalOrders ?></strong>

                <span>orders</span>

            </div>



        </header>





        <!-- =====================================================

             ALERTS

        ====================================================== -->



        <?php if (isset($_GET['success'])): ?>



            <div class="orders-alert orders-alert-success">



                <span class="alert-symbol">✓</span>



                <span>



                    <?php



                    switch ($_GET['success']) {



                        case 'approved':

                            echo 'Order approved successfully.';

                            break;



                        case 'cancelled':

                            echo 'Order cancelled successfully.';

                            break;



                        case 'assigned':

                            echo 'Driver and truck assigned successfully.';

                            break;



                        default:

                            echo 'Action completed successfully.';

                    }



                    ?>



                </span>



            </div>



        <?php endif; ?>





        <?php if (isset($_GET['error'])): ?>



            <div class="orders-alert orders-alert-error">



                <span class="alert-symbol">!</span>



                <span>



                    <?php



                    switch ($_GET['error']) {



                        case 'invalid_order':

                            echo 'Invalid order selected.';

                            break;



                        case 'order_not_found':

                            echo 'The selected order could not be found.';

                            break;



                        case 'not_pending':

                            echo 'Only pending orders can be approved.';

                            break;



                        case 'not_cancellable':

                            echo 'This order can no longer be cancelled.';

                            break;



                        case 'not_assignable':

                            echo 'This order cannot be assigned yet. Only Approved or already Assigned orders can open the assignment page.';

                            break;



                        case 'update_failed':

                            echo 'The order could not be updated.';

                            break;



                        case 'action_failed':

                            echo 'The action failed. Please try again.';

                            break;



                        default:

                            echo 'An error occurred.';

                    }



                    ?>



                </span>



            </div>



        <?php endif; ?>





        <!-- =====================================================

             SUMMARY

        ====================================================== -->



        <section class="orders-stat-grid">





            <div class="orders-stat-card stat-total">



                <span class="orders-stat-label">

                    Total Orders

                </span>



                <strong class="orders-stat-value">

                    <?= (int) $totalOrders ?>

                </strong>



            </div>





            <div class="orders-stat-card stat-pending">



                <span class="orders-stat-label">

                    Pending

                </span>



                <strong class="orders-stat-value">

                    <?= (int) $totalPending ?>

                </strong>



            </div>





            <div class="orders-stat-card stat-approved">



                <span class="orders-stat-label">

                    Approved

                </span>



                <strong class="orders-stat-value">

                    <?= (int) $totalApproved ?>

                </strong>



            </div>





            <div class="orders-stat-card stat-assigned">



                <span class="orders-stat-label">

                    Assigned

                </span>



                <strong class="orders-stat-value">

                    <?= (int) $totalAssigned ?>

                </strong>



            </div>





            <div class="orders-stat-card stat-transit">



                <span class="orders-stat-label">

                    In Transit

                </span>



                <strong class="orders-stat-value">

                    <?= (int) $totalInTransit ?>

                </strong>



            </div>





            <div class="orders-stat-card stat-completed">



                <span class="orders-stat-label">

                    Completed

                </span>



                <strong class="orders-stat-value">

                    <?= (int) $totalCompleted ?>

                </strong>



            </div>





        </section>





        <!-- =====================================================

             FILTERS

        ====================================================== -->



        <section class="orders-filter-card">



            <div class="orders-filter-header">



                <h2>Search & Filter Orders</h2>



                <p>

                    Search by order number, customer, driver, or truck plate.

                </p>



            </div>





            <form

                method="GET"

                class="orders-filter-form"

            >



                <input

                    type="text"

                    name="search"

                    class="orders-input"

                    placeholder="Search order, customer, driver, plate..."

                    value="<?= htmlspecialchars($search) ?>"

                >





                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">





                <select

                    name="priority"

                    class="orders-select"

                >



                    <option value="">

                        All Priority

                    </option>



                    <?php foreach ($validPriorities as $priority): ?>



                        <option

                            value="<?= htmlspecialchars($priority) ?>"

                            <?= $priorityFilter === $priority ? 'selected' : '' ?>

                        >

                            <?= htmlspecialchars($priority) ?>

                        </option>



                    <?php endforeach; ?>



                </select>





                <button

                    type="submit"

                    class="orders-btn orders-btn-primary"

                >

                    Search

                </button>





                <a

                    href="<?= BASE_URL ?>admin/orders.php?category=<?= urlencode($category) ?>"

                    class="orders-btn orders-btn-secondary"

                >

                    Reset

                </a>



            </form>



        </section>







        <!-- =====================================================

             ORDER CATEGORIES

        ====================================================== -->



        <nav class="order-category-tabs" aria-label="Order categories">



            <?php foreach ($orderCategories as $categoryKey => $categoryConfig): ?>



                <a

                    href="<?= htmlspecialchars(ordersPageUrl([

                        'category' => $categoryKey,

                        'page' => 1

                    ])) ?>"

                    class="order-category-tab <?= $category === $categoryKey ? 'active' : '' ?>"

                >

                    <?= htmlspecialchars($categoryConfig['label']) ?>



                    <span class="category-count">

                        <?= (int) ($categoryCounts[$categoryKey] ?? 0) ?>

                    </span>

                </a>



            <?php endforeach; ?>



        </nav>





        <!-- =====================================================

             ORDER TABLE

        ====================================================== -->



        <section class="orders-table-card">





            <div class="orders-table-header">



                <div class="orders-table-title">



                    <h2><?= htmlspecialchars($activeCategory['label']) ?></h2>



                    <p>

                        Newest orders appear first in this section.

                    </p>



                </div>



                <div class="orders-results">

                    <?= $filteredTotal ?> result<?= $filteredTotal === 1 ? '' : 's' ?>

                </div>



            </div>





            <?php if (empty($orders)): ?>





                <div class="orders-empty">



                    <div class="orders-empty-icon">

                        ⌕

                    </div>



                    <h3>

                        No orders found

                    </h3>



                    <p>

                        Try changing your search or filter criteria.

                    </p>



                </div>





            <?php else: ?>





                <div class="orders-table-scroll">



                    <table class="orders-table">



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

                                    Amount

                                </th>



                                <th>

                                    Priority

                                </th>



                                <th>

                                    Driver

                                </th>



                                <th>

                                    Truck

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





                        <?php foreach ($orders as $order): ?>





                            <?php



                            $status = $order['completion_status'];



                            $statusClass = 'status-pending';



                            switch ($status) {



                                case 'Approved':

                                    $statusClass = 'status-approved';

                                    break;



                                case 'Assigned':

                                    $statusClass = 'status-assigned';

                                    break;



                                case 'Accepted':

                                case 'In Transit':

                                    $statusClass = 'status-transit';

                                    break;



                                case 'Completed':

                                    $statusClass = 'status-delivered';

                                    break;



                                case 'Cancelled':

                                    $statusClass = 'status-failed';

                                    break;



                                case 'Pending':

                                default:

                                    $statusClass = 'status-pending';

                                    break;

                            }





                            $customerName =

                                trim($order['customer_name'] ?? '');



                            $customerInitial =

                                $customerName !== ''

                                    ? strtoupper(substr($customerName, 0, 1))

                                    : '?';



                            ?>





                            <tr>





                                <!-- ORDER -->



                                <td>



                                    <span class="order-number">

                                        <?= htmlspecialchars(

                                            $order['order_number']

                                        ) ?>

                                    </span>



                                    <?php if (!empty($order['item_description'])): ?>



                                        <span class="order-description">

                                            <?= htmlspecialchars(

                                                $order['item_description']

                                            ) ?>

                                        </span>



                                    <?php endif; ?>



                                </td>





                                <!-- CUSTOMER -->



                                <td>



                                    <div class="customer-cell">



                                        <span class="customer-avatar">

                                            <?= htmlspecialchars(

                                                $customerInitial

                                            ) ?>

                                        </span>



                                        <span class="customer-name">

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



                                        <span class="date-main">



                                            <?= date(

                                                'M d, Y',

                                                strtotime($order['order_date'])

                                            ) ?>



                                        </span>



                                        <span class="date-label">

                                            Order date

                                        </span>



                                    <?php else: ?>



                                        <span class="unassigned">

                                            -

                                        </span>



                                    <?php endif; ?>



                                </td>





                                <!-- AMOUNT -->



                                <td>



                                    <span class="assignment-main">

                                        ₱<?= number_format((float) $order['total_amount'], 2) ?>

                                    </span>



                                    <span class="assignment-sub">

                                        Subtotal: ₱<?= number_format((float) ($order['subtotal'] ?? $order['total_amount']), 2) ?>

                                        <?php if ((float) ($order['urgent_fee'] ?? 0) > 0): ?>

                                            · Urgent fee: ₱<?= number_format((float) $order['urgent_fee'], 2) ?>

                                        <?php endif; ?>

                                    </span>



                                </td>





                                <!-- PRIORITY -->



                                <td>



                                    <?php if (

                                        $order['priority_level'] === 'Urgent'

                                    ): ?>



                                        <span class="orders-badge badge-urgent">

                                            ⚡ Urgent

                                        </span>



                                    <?php else: ?>



                                        <span class="orders-badge badge-normal">

                                            Normal

                                        </span>



                                    <?php endif; ?>



                                </td>





                                <!-- DRIVER -->



                                <td>



                                    <?php if (!empty($order['driver_name'])): ?>



                                        <span class="assignment-main">

                                            <?= htmlspecialchars(

                                                $order['driver_name']

                                            ) ?>

                                        </span>



                                        <span class="assignment-sub">

                                            Assigned driver

                                        </span>



                                    <?php else: ?>



                                        <span class="unassigned">

                                            Unassigned

                                        </span>



                                    <?php endif; ?>



                                </td>





                                <!-- TRUCK -->



                                <td>



                                    <?php if (!empty($order['plate_number'])): ?>



                                        <span class="assignment-main">

                                            <?= htmlspecialchars(

                                                $order['plate_number']

                                            ) ?>

                                        </span>



                                        <?php if (!empty($order['model'])): ?>



                                            <span class="assignment-sub">

                                                <?= htmlspecialchars(

                                                    $order['model']

                                                ) ?>

                                            </span>



                                        <?php endif; ?>



                                    <?php else: ?>



                                        <span class="unassigned">

                                            Unassigned

                                        </span>



                                    <?php endif; ?>



                                </td>





                                <!-- STATUS -->



                                <td>



                                    <span

                                        class="orders-badge <?= $statusClass ?>"

                                    >

                                        <?= htmlspecialchars($status) ?>

                                    </span>



                                </td>





                                <!-- ACTION -->



                                <td>



                                    <?php if ($status === 'Pending'): ?>





                                        <div class="orders-actions">





                                            <form

                                                method="POST"

                                                style="margin:0;"

                                            >



                                                <input

                                                    type="hidden"

                                                    name="order_id"

                                                    value="<?= (int) $order['order_id'] ?>"

                                                >



                                                <button

                                                    type="submit"

                                                    name="approve_order"

                                                    class="action-btn action-approve"

                                                    onclick="return confirm('Approve this order?');"

                                                >

                                                    Approve

                                                </button>



                                            </form>





                                            <form

                                                method="POST"

                                                style="margin:0;"

                                            >



                                                <input

                                                    type="hidden"

                                                    name="order_id"

                                                    value="<?= (int) $order['order_id'] ?>"

                                                >



                                                <button

                                                    type="submit"

                                                    name="cancel_order"

                                                    class="action-btn action-cancel"

                                                    onclick="return confirm('Cancel this order?');"

                                                >

                                                    Cancel

                                                </button>



                                            </form>





                                        </div>





                                    <?php elseif ($status === 'Approved'): ?>





                                        <a

                                            href="<?= BASE_URL ?>admin/assign_delivery.php?order_id=<?= (int) $order['order_id'] ?>"

                                            class="action-btn action-assign"

                                        >

                                            Assign

                                        </a>





                                    <?php elseif (in_array($status, ['Assigned', 'Accepted', 'In Transit'], true)): ?>

                                        <a
                                            href="<?= BASE_URL ?>admin/track_delivery.php?id=<?= (int) $order['order_id'] ?>"
                                            class="action-btn action-view"
                                        >
                                            Track Delivery
                                        </a>


                                    <?php elseif ($status === 'Completed'): ?>





                                        <a

                                            href="<?= BASE_URL ?>admin/order_details.php?id=<?= (int) $order['order_id'] ?>"

                                            class="action-btn action-view"

                                        >

                                            View Details

                                        </a>





                                    <?php else: ?>





                                        <span class="no-action">

                                            No action

                                        </span>





                                    <?php endif; ?>



                                </td>





                            </tr>





                        <?php endforeach; ?>





                        </tbody>



                    </table>



                </div>



                <?php if ($totalPages > 1): ?>



                    <div class="orders-pagination-wrap">



                        <div class="orders-pagination-info">

                            Showing

                            <?= $filteredTotal > 0 ? ($offset + 1) : 0 ?>

                            –

                            <?= min($offset + $perPage, $filteredTotal) ?>

                            of

                            <?= $filteredTotal ?>

                        </div>



                        <nav class="orders-pagination" aria-label="Order pages">



                            <a

                                class="page-link <?= $page <= 1 ? 'disabled' : '' ?>"

                                href="<?= htmlspecialchars(ordersPageUrl([

                                    'page' => max(1, $page - 1)

                                ])) ?>"

                            >

                                ‹ Prev

                            </a>



                            <?php

                            $startPage = max(1, $page - 2);

                            $endPage = min($totalPages, $page + 2);

                            ?>



                            <?php if ($startPage > 1): ?>

                                <a class="page-link" href="<?= htmlspecialchars(ordersPageUrl(['page' => 1])) ?>">1</a>

                                <?php if ($startPage > 2): ?>

                                    <span class="page-link disabled">…</span>

                                <?php endif; ?>

                            <?php endif; ?>



                            <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>



                                <a

                                    class="page-link <?= $pageNumber === $page ? 'active' : '' ?>"

                                    href="<?= htmlspecialchars(ordersPageUrl([

                                        'page' => $pageNumber

                                    ])) ?>"

                                >

                                    <?= $pageNumber ?>

                                </a>



                            <?php endfor; ?>



                            <?php if ($endPage < $totalPages): ?>

                                <?php if ($endPage < $totalPages - 1): ?>

                                    <span class="page-link disabled">…</span>

                                <?php endif; ?>

                                <a class="page-link" href="<?= htmlspecialchars(ordersPageUrl(['page' => $totalPages])) ?>"><?= $totalPages ?></a>

                            <?php endif; ?>



                            <a

                                class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>"

                                href="<?= htmlspecialchars(ordersPageUrl([

                                    'page' => min($totalPages, $page + 1)

                                ])) ?>"

                            >

                                Next ›

                            </a>



                        </nav>



                    </div>



                <?php endif; ?>





            <?php endif; ?>





        </section>





    </div>



</main>





</div>



</body>



</html>