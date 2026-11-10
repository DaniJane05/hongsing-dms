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
| NOTE:
| e() is already declared in includes/auth.php.
| Do NOT redeclare e() in this file.
|
*/

function customerOrderStatus(string $status): string
{
    return match ($status) {
        'Completed' => 'Delivered',
        default     => $status !== '' ? $status : 'Pending',
    };
}


function statusClass(string $status): string
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

        'Delivered',
        'Completed' =>
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
| Pagination URL Helper
|--------------------------------------------------------------------------
*/

function orderHistoryUrl(
    string $status,
    string $search = '',
    int $page = 1
): string {

    $query = [
        'status' => $status
    ];

    if ($search !== '') {
        $query['search'] = $search;
    }

    if ($page > 1) {
        $query['page'] = $page;
    }

    return BASE_URL
        . 'customer/order_history.php?'
        . http_build_query($query);
}


/*
|--------------------------------------------------------------------------
| Selected Tab
|--------------------------------------------------------------------------
*/

$allowedTabs = [
    'all',
    'pending',
    'approved',
    'in_transit',
    'delivered',
    'cancelled'
];

$activeTab = strtolower(
    trim($_GET['status'] ?? 'all')
);

if (
    !in_array(
        $activeTab,
        $allowedTabs,
        true
    )
) {
    $activeTab = 'all';
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Pagination Settings
|--------------------------------------------------------------------------
*/

$ordersPerPage = 10;

$currentPage = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

if (
    !$currentPage ||
    $currentPage < 1
) {
    $currentPage = 1;
}


/*
|--------------------------------------------------------------------------
| Base WHERE Query
|--------------------------------------------------------------------------
*/

$whereSql = "
    FROM `order` o
    WHERE o.customer_id = :customer_id
";

$params = [
    ':customer_id' => $customerId
];


/*
|--------------------------------------------------------------------------
| Tab Filters
|--------------------------------------------------------------------------
*/

switch ($activeTab) {

    case 'pending':

        $whereSql .= "
            AND o.completion_status = 'Pending'
        ";

        break;


    case 'approved':

        $whereSql .= "
            AND o.completion_status IN (
                'Approved',
                'Assigned',
                'Accepted'
            )
        ";

        break;


    case 'in_transit':

        $whereSql .= "
            AND o.completion_status IN (
                'In Transit',
                'Arrived'
            )
        ";

        break;


    case 'delivered':

        $whereSql .= "
            AND o.completion_status IN (
                'Delivered',
                'Completed'
            )
        ";

        break;


    case 'cancelled':

        $whereSql .= "
            AND o.completion_status = 'Cancelled'
        ";

        break;


    case 'all':
    default:

        break;
}


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $whereSql .= "
        AND (
            o.order_number LIKE :search
            OR o.item_description LIKE :search
        )
    ";

    $params[':search'] =
        '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| Count Filtered Orders
|--------------------------------------------------------------------------
|
| We count the filtered records first so we know how many
| pagination pages should be displayed.
|
*/

$countFilteredSql = "
    SELECT COUNT(*)
    {$whereSql}
";

$countFilteredStmt =
    $db->prepare($countFilteredSql);


/*
|--------------------------------------------------------------------------
| Bind Count Parameters
|--------------------------------------------------------------------------
*/

$countFilteredStmt->bindValue(
    ':customer_id',
    $customerId,
    PDO::PARAM_INT
);

if ($search !== '') {

    $countFilteredStmt->bindValue(
        ':search',
        '%' . $search . '%',
        PDO::PARAM_STR
    );
}

$countFilteredStmt->execute();

$totalFilteredOrders =
    (int) $countFilteredStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Calculate Pagination
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int) ceil(
        $totalFilteredOrders /
        $ordersPerPage
    )
);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset =
    ($currentPage - 1)
    * $ordersPerPage;


/*
|--------------------------------------------------------------------------
| Build Paginated Order Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        o.order_id,
        o.order_number,
        o.item_description,
        o.total_amount,
        o.total_weight,
        o.total_weight_kg,
        o.order_date,
        o.expected_delivery_date,
        o.priority_level,
        o.completion_status,
        o.created_at

    {$whereSql}

    ORDER BY
        o.order_date DESC,
        o.order_id DESC

    LIMIT :limit
    OFFSET :offset
";


$stmt = $db->prepare($sql);


/*
|--------------------------------------------------------------------------
| Bind Main Query Parameters
|--------------------------------------------------------------------------
*/

$stmt->bindValue(
    ':customer_id',
    $customerId,
    PDO::PARAM_INT
);

if ($search !== '') {

    $stmt->bindValue(
        ':search',
        '%' . $search . '%',
        PDO::PARAM_STR
    );
}

$stmt->bindValue(
    ':limit',
    $ordersPerPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$orders =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Count Orders Per Tab
|--------------------------------------------------------------------------
|
| These counts are NOT affected by pagination.
| They represent all customer orders.
|
*/

$countStmt = $db->prepare("

    SELECT

        COUNT(*) AS all_count,

        SUM(
            CASE
                WHEN completion_status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_count,

        SUM(
            CASE
                WHEN completion_status IN (
                    'Approved',
                    'Assigned',
                    'Accepted'
                )
                THEN 1
                ELSE 0
            END
        ) AS approved_count,

        SUM(
            CASE
                WHEN completion_status IN (
                    'In Transit',
                    'Arrived'
                )
                THEN 1
                ELSE 0
            END
        ) AS transit_count,

        SUM(
            CASE
                WHEN completion_status IN (
                    'Delivered',
                    'Completed'
                )
                THEN 1
                ELSE 0
            END
        ) AS delivered_count,

        SUM(
            CASE
                WHEN completion_status = 'Cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelled_count

    FROM `order`

    WHERE customer_id = ?
");


$countStmt->execute([
    $customerId
]);


$orderCounts =
    $countStmt->fetch(
        PDO::FETCH_ASSOC
    ) ?: [];


$allCount =
    (int) (
        $orderCounts['all_count']
        ?? 0
    );

$pendingCount =
    (int) (
        $orderCounts['pending_count']
        ?? 0
    );

$approvedCount =
    (int) (
        $orderCounts['approved_count']
        ?? 0
    );

$transitCount =
    (int) (
        $orderCounts['transit_count']
        ?? 0
    );

$deliveredCount =
    (int) (
        $orderCounts['delivered_count']
        ?? 0
    );

$cancelledCount =
    (int) (
        $orderCounts['cancelled_count']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| Pagination Display Values
|--------------------------------------------------------------------------
*/

if ($totalFilteredOrders > 0) {

    $showingFrom =
        $offset + 1;

    $showingTo =
        min(
            $offset + count($orders),
            $totalFilteredOrders
        );

} else {

    $showingFrom = 0;
    $showingTo   = 0;
}


/*
|--------------------------------------------------------------------------
| Page Number Range
|--------------------------------------------------------------------------
|
| Example:
|
| 1 ... 4 5 [6] 7 8 ... 20
|
*/

$paginationStart =
    max(
        1,
        $currentPage - 2
    );

$paginationEnd =
    min(
        $totalPages,
        $currentPage + 2
    );


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = 'My Orders';


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

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
    My Orders · Hong Sing LKRR Commercial
</title>

<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>

<style>

/* ==========================================================
   PAGE
========================================================== */

.orders-page {
    max-width: 1200px;
    margin: 0 auto;
}

.orders-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;
}

.orders-header h1 {
    margin: 0 0 6px;

    font-size: 28px;
}

.orders-header p {
    margin: 0;

    color:
        var(--color-text-secondary);

    font-size: 13px;
}

.new-order-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-height: 42px;

    padding: 10px 17px;

    border-radius: 8px;

    text-decoration: none;

    background:
        var(--color-accent);

    color: #ffffff;

    font-size: 13px;
    font-weight: 700;

    white-space: nowrap;
}

.new-order-button:hover {
    color: #ffffff;

    opacity: 0.92;
}


/* ==========================================================
   TABS
========================================================== */

.order-tabs-wrapper {
    margin-bottom: 18px;
}

.order-tabs {
    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    padding: 6px;

    background:
        var(--color-surface);

    border:
        1px solid
        var(--color-border);

    border-radius: 10px;
}

.order-tab {
    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 10px 13px;

    border-radius: 7px;

    color:
        var(--color-text-secondary);

    text-decoration: none;

    font-size: 12px;
    font-weight: 600;

    transition: 0.15s ease;
}

.order-tab:hover {
    background:
        var(--color-background);

    color:
        var(--color-text-primary);
}

.order-tab.active {
    background:
        var(--color-accent);

    color: #ffffff;
}

.order-tab-count {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-width: 20px;
    height: 20px;

    padding: 0 5px;

    border-radius: 999px;

    background:
        rgba(0, 0, 0, 0.08);

    font-size: 10px;
}

.order-tab.active
.order-tab-count {

    background:
        rgba(255, 255, 255, 0.20);

    color: #ffffff;
}


/* ==========================================================
   SEARCH
========================================================== */

.orders-tools {
    display: flex;

    gap: 10px;

    margin-bottom: 18px;
}

.orders-search {
    flex: 1;

    min-width: 0;

    height: 42px;

    padding: 0 13px;

    border:
        1px solid
        var(--color-border);

    border-radius: 8px;

    background:
        var(--color-surface);

    color:
        var(--color-text-primary);

    outline: none;
}

.orders-search:focus {
    border-color:
        var(--color-accent);

    box-shadow:
        0 0 0 3px
        rgba(37, 99, 235, 0.08);
}

.search-button,
.clear-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    height: 42px;

    padding: 0 15px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 12px;
    font-weight: 700;
}

.search-button {
    border: 0;

    background:
        var(--color-accent);

    color: #ffffff;

    cursor: pointer;
}

.clear-button {
    border:
        1px solid
        var(--color-border);

    background:
        var(--color-surface);

    color:
        var(--color-text-primary);
}


/* ==========================================================
   ORDERS CARD
========================================================== */

.orders-card {
    overflow: hidden;
}

.orders-table-wrapper {
    width: 100%;

    overflow-x: auto;

    -webkit-overflow-scrolling: touch;
}

.orders-table {
    width: 100%;

    border-collapse: collapse;

    min-width: 820px;
}

.orders-table th {
    padding: 13px 14px;

    text-align: left;

    border-bottom:
        1px solid
        var(--color-border);

    background: #f8fafc;

    color:
        var(--color-text-secondary);

    font-size: 11px;
    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.3px;
}

.orders-table td {
    padding: 15px 14px;

    border-bottom:
        1px solid
        var(--color-border);

    color:
        var(--color-text-primary);

    font-size: 12px;

    vertical-align: middle;
}

.orders-table
tbody
tr:last-child
td {
    border-bottom: 0;
}

.orders-table
tbody
tr:hover {

    background:
        var(--color-background);
}

.order-number {
    font-weight: 700;
}

.order-items-text {
    max-width: 230px;

    color:
        var(--color-text-secondary);

    line-height: 1.4;
}

.amount {
    font-weight: 700;

    white-space: nowrap;
}

.date-text {
    white-space: nowrap;
}


/* ==========================================================
   STATUS
========================================================== */

.status-pill {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 5px 9px;

    border-radius: 999px;

    font-size: 10px;
    font-weight: 700;

    white-space: nowrap;
}

.status-pending {
    background:
        rgba(245, 158, 11, 0.12);

    color: #b45309;
}

.status-approved {
    background:
        rgba(59, 130, 246, 0.12);

    color: #2563eb;
}

.status-transit {
    background:
        rgba(14, 165, 233, 0.12);

    color: #0284c7;
}

.status-delivered {
    background:
        rgba(16, 185, 129, 0.12);

    color: #059669;
}

.status-failed {
    background:
        rgba(239, 68, 68, 0.12);

    color: #dc2626;
}


/* ==========================================================
   ACTIONS
========================================================== */

.order-actions {
    display: flex;

    flex-wrap: wrap;

    gap: 7px;
}

.order-action {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 7px 10px;

    border-radius: 6px;

    text-decoration: none;

    font-size: 11px;
    font-weight: 700;

    white-space: nowrap;
}

.order-action.primary {
    background:
        var(--color-accent);

    color: #ffffff;
}

.order-action.secondary {
    border:
        1px solid
        var(--color-border);

    background:
        var(--color-surface);

    color:
        var(--color-text-primary);
}

.order-action:hover {
    opacity: 0.9;
}


/* ==========================================================
   PAGINATION
========================================================== */

.orders-pagination {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 18px;

    padding: 16px 18px;

    background: #ffffff;

    border-top:
        1px solid
        var(--color-border);
}

.pagination-summary {
    flex-shrink: 0;

    color:
        var(--color-text-secondary);

    font-size: 12px;
}

.pagination-summary strong {
    color:
        var(--color-text-primary);

    font-weight: 700;
}

.order-pagination-links {
    display: flex;

    align-items: center;

    gap: 6px;

    flex-wrap: wrap;
}

.order-page-link,
.order-page-disabled {
    min-width: 36px;
    height: 36px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 0 10px;

    border:
        1px solid
        var(--color-border);

    border-radius: 7px;

    font-size: 12px;
    font-weight: 650;

    white-space: nowrap;
}

.order-page-link {
    background: #ffffff;

    color:
        var(--color-text-secondary);

    text-decoration: none;

    transition:
        0.15s ease;
}

.order-page-link:hover {
    background:
        var(--color-primary-light);

    border-color: #bfdbfe;

    color:
        var(--color-primary);
}

.order-page-link.active {
    background:
        var(--color-primary);

    border-color:
        var(--color-primary);

    color: #ffffff;

    pointer-events: none;
}

.order-page-disabled {
    background: #f8fafc;

    color:
        var(--color-text-muted);

    cursor: not-allowed;
}

.order-page-dots {
    min-width: 26px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    color:
        var(--color-text-muted);

    font-size: 13px;
}


/* ==========================================================
   EMPTY STATE
========================================================== */

.empty-orders {
    padding: 55px 25px;

    text-align: center;
}

.empty-orders-icon {
    font-size: 38px;

    margin-bottom: 12px;
}

.empty-orders h3 {
    margin: 0 0 6px;

    font-size: 17px;
}

.empty-orders p {
    margin: 0 0 18px;

    color:
        var(--color-text-secondary);

    font-size: 13px;
}


/* ==========================================================
   MOBILE
========================================================== */

@media (max-width: 760px) {

    .orders-page {
        width: 100%;
    }

    .orders-header {
        flex-direction: column;
    }

    .orders-header h1 {
        font-size: 23px;
    }

    .new-order-button {
        width: 100%;

        box-sizing: border-box;
    }

    .order-tabs {
        overflow-x: auto;

        flex-wrap: nowrap;

        scrollbar-width: thin;
    }

    .order-tab {
        flex: 0 0 auto;
    }

    .orders-tools {
        flex-wrap: wrap;
    }

    .orders-search {
        flex: 1 1 100%;
    }

    .search-button,
    .clear-button {
        flex: 1;
    }

    .orders-pagination {
        flex-direction: column;

        align-items: flex-start;

        gap: 12px;

        padding: 14px;
    }

    .order-pagination-links {
        width: 100%;

        flex-wrap: nowrap;

        overflow-x: auto;

        padding-bottom: 4px;

        scrollbar-width: thin;
    }

    .order-page-link,
    .order-page-disabled {
        flex: 0 0 auto;
    }
}


/* ==========================================================
   VERY SMALL MOBILE
========================================================== */

@media (max-width: 420px) {

    .pagination-summary {
        font-size: 11px;
    }

    .order-page-link,
    .order-page-disabled {
        min-width: 34px;
        height: 34px;

        padding: 0 9px;

        font-size: 11px;
    }
}

</style>

</head>


<body>


<div class="app-layout">


<?php
require __DIR__ . '/../includes/header.php';
?>


<main class="app-main">


<div class="orders-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="orders-header">

        <div>

            <h1>
                My Orders
            </h1>

            <p>
                View your orders, delivery status,
                and order details.
            </p>

        </div>


        <a
            href="<?= BASE_URL ?>customer/products.php"
            class="new-order-button"
        >
            Browse Products
        </a>

    </div>


    <!-- =====================================================
         STATUS TABS
    ====================================================== -->

    <div class="order-tabs-wrapper">

        <nav class="order-tabs">


            <a
                href="<?= e(
                    orderHistoryUrl(
                        'all'
                    )
                ) ?>"
                class="order-tab <?= $activeTab === 'all' ? 'active' : '' ?>"
            >
                All

                <span class="order-tab-count">
                    <?= $allCount ?>
                </span>
            </a>


            <a
                href="<?= e(
                    orderHistoryUrl(
                        'pending'
                    )
                ) ?>"
                class="order-tab <?= $activeTab === 'pending' ? 'active' : '' ?>"
            >
                Pending

                <span class="order-tab-count">
                    <?= $pendingCount ?>
                </span>
            </a>


            <a
                href="<?= e(
                    orderHistoryUrl(
                        'approved'
                    )
                ) ?>"
                class="order-tab <?= $activeTab === 'approved' ? 'active' : '' ?>"
            >
                Approved

                <span class="order-tab-count">
                    <?= $approvedCount ?>
                </span>
            </a>


            <a
                href="<?= e(
                    orderHistoryUrl(
                        'in_transit'
                    )
                ) ?>"
                class="order-tab <?= $activeTab === 'in_transit' ? 'active' : '' ?>"
            >
                In Transit

                <span class="order-tab-count">
                    <?= $transitCount ?>
                </span>
            </a>


            <a
                href="<?= e(
                    orderHistoryUrl(
                        'delivered'
                    )
                ) ?>"
                class="order-tab <?= $activeTab === 'delivered' ? 'active' : '' ?>"
            >
                Delivered

                <span class="order-tab-count">
                    <?= $deliveredCount ?>
                </span>
            </a>


            <a
                href="<?= e(
                    orderHistoryUrl(
                        'cancelled'
                    )
                ) ?>"
                class="order-tab <?= $activeTab === 'cancelled' ? 'active' : '' ?>"
            >
                Cancelled

                <span class="order-tab-count">
                    <?= $cancelledCount ?>
                </span>
            </a>


        </nav>

    </div>


    <!-- =====================================================
         SEARCH
    ====================================================== -->

    <form
        method="GET"
        action="<?= BASE_URL ?>customer/order_history.php"
        class="orders-tools"
    >

        <input
            type="hidden"
            name="status"
            value="<?= e($activeTab) ?>"
        >


        <input
            type="text"
            name="search"
            class="orders-search"
            placeholder="Search order number or item..."
            value="<?= e($search) ?>"
            autocomplete="off"
        >


        <button
            type="submit"
            class="search-button"
        >
            Search
        </button>


        <?php if ($search !== ''): ?>

            <a
                href="<?= e(
                    orderHistoryUrl(
                        $activeTab
                    )
                ) ?>"
                class="clear-button"
            >
                Clear
            </a>

        <?php endif; ?>


    </form>


    <!-- =====================================================
         ORDERS
    ====================================================== -->

    <div class="card orders-card">


        <?php if (empty($orders)): ?>


            <div class="empty-orders">


                <div class="empty-orders-icon">
                    📦
                </div>


                <h3>
                    No orders found
                </h3>


                <p>

                    <?php if ($search !== ''): ?>

                        No orders match your search.

                    <?php elseif ($activeTab !== 'all'): ?>

                        You don't have any orders under
                        this status yet.

                    <?php else: ?>

                        You haven't placed any orders yet.

                    <?php endif; ?>

                </p>


                <?php if (
                    $activeTab === 'all' &&
                    $search === ''
                ): ?>

                    <a
                        href="<?= BASE_URL ?>customer/products.php"
                        class="new-order-button"
                    >
                        Start Shopping
                    </a>

                <?php endif; ?>


            </div>


        <?php else: ?>


            <div class="orders-table-wrapper">


                <table class="orders-table">


                    <thead>

                    <tr>

                        <th>
                            Order #
                        </th>

                        <th>
                            Items
                        </th>

                        <th>
                            Amount
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
                            Actions
                        </th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($orders as $order): ?>


                        <?php

                        $databaseStatus =
                            (string) (
                                $order['completion_status']
                                ?? 'Pending'
                            );


                        $displayStatus =
                            customerOrderStatus(
                                $databaseStatus
                            );


                        $itemDescription =
                            trim(
                                (string) (
                                    $order['item_description']
                                    ?? ''
                                )
                            );


                        if ($itemDescription === '') {

                            $itemDescription =
                                'Order items';
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Order Date
                        |--------------------------------------------------------------------------
                        */

                        $orderDate = '—';

                        if (
                            !empty(
                                $order['order_date']
                            )
                        ) {

                            $timestamp =
                                strtotime(
                                    $order['order_date']
                                );

                            if (
                                $timestamp !== false
                            ) {

                                $orderDate =
                                    date(
                                        'M d, Y',
                                        $timestamp
                                    );
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Expected Delivery
                        |--------------------------------------------------------------------------
                        */

                        $expectedDate = '—';

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

                            if (
                                $timestamp !== false
                            ) {

                                $expectedDate =
                                    date(
                                        'M d, Y',
                                        $timestamp
                                    );
                            }
                        }

                        ?>


                        <tr>


                            <!-- ORDER NUMBER -->

                            <td>

                                <div class="order-number">

                                    <?= e(
                                        $order['order_number']
                                    ) ?>

                                </div>

                            </td>


                            <!-- ITEMS -->

                            <td>

                                <div class="order-items-text">

                                    <?= e(
                                        mb_strimwidth(
                                            $itemDescription,
                                            0,
                                            45,
                                            '…'
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <span class="amount">

                                    ₱<?= number_format(
                                        (float) (
                                            $order['total_amount']
                                            ?? 0
                                        ),
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <!-- ORDER DATE -->

                            <td>

                                <span class="date-text">
                                    <?= e($orderDate) ?>
                                </span>

                            </td>


                            <!-- EXPECTED DELIVERY -->

                            <td>

                                <span class="date-text">
                                    <?= e($expectedDate) ?>
                                </span>

                            </td>


                            <!-- PRIORITY -->

                            <td>

                                <?= e(
                                    $order['priority_level']
                                    ?? 'Normal'
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status-pill <?= e(
                                        statusClass(
                                            $databaseStatus
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        $displayStatus
                                    ) ?>
                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="order-actions">


                                    <a
                                        href="<?= BASE_URL ?>customer/order_details.php?order_id=<?= (int) $order['order_id'] ?>"
                                        class="order-action primary"
                                    >
                                        View
                                    </a>


                                    <?php if (
                                        !in_array(
                                            $databaseStatus,
                                            [
                                                'Cancelled',
                                                'Failed'
                                            ],
                                            true
                                        )
                                    ): ?>

                                        <a
                                            href="<?= BASE_URL ?>customer/track_delivery.php?order_id=<?= (int) $order['order_id'] ?>"
                                            class="order-action secondary"
                                        >
                                            Track
                                        </a>

                                    <?php endif; ?>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


            <!-- =================================================
                 PAGINATION
            ================================================== -->

            <div class="orders-pagination">


                <div class="pagination-summary">

                    Showing

                    <strong>
                        <?= $showingFrom ?>
                    </strong>

                    –

                    <strong>
                        <?= $showingTo ?>
                    </strong>

                    of

                    <strong>
                        <?= $totalFilteredOrders ?>
                    </strong>

                    <?= $totalFilteredOrders === 1 ? 'order' : 'orders' ?>

                </div>


                <?php if ($totalPages > 1): ?>


                    <nav
                        class="order-pagination-links"
                        aria-label="Order pagination"
                    >


                        <!-- PREVIOUS -->

                        <?php if ($currentPage > 1): ?>

                            <a
                                href="<?= e(
                                    orderHistoryUrl(
                                        $activeTab,
                                        $search,
                                        $currentPage - 1
                                    )
                                ) ?>"
                                class="order-page-link"
                            >
                                ← Previous
                            </a>

                        <?php else: ?>

                            <span
                                class="order-page-disabled"
                                aria-disabled="true"
                            >
                                ← Previous
                            </span>

                        <?php endif; ?>


                        <!-- FIRST PAGE -->

                        <?php if ($paginationStart > 1): ?>

                            <a
                                href="<?= e(
                                    orderHistoryUrl(
                                        $activeTab,
                                        $search,
                                        1
                                    )
                                ) ?>"
                                class="order-page-link"
                            >
                                1
                            </a>


                            <?php if (
                                $paginationStart > 2
                            ): ?>

                                <span class="order-page-dots">
                                    …
                                </span>

                            <?php endif; ?>

                        <?php endif; ?>


                        <!-- NEARBY PAGE NUMBERS -->

                        <?php for (
                            $pageNumber = $paginationStart;
                            $pageNumber <= $paginationEnd;
                            $pageNumber++
                        ): ?>


                            <a
                                href="<?= e(
                                    orderHistoryUrl(
                                        $activeTab,
                                        $search,
                                        $pageNumber
                                    )
                                ) ?>"
                                class="order-page-link <?= $pageNumber === $currentPage ? 'active' : '' ?>"
                                <?= $pageNumber === $currentPage ? 'aria-current="page"' : '' ?>
                            >
                                <?= $pageNumber ?>
                            </a>


                        <?php endfor; ?>


                        <!-- LAST PAGE -->

                        <?php if (
                            $paginationEnd < $totalPages
                        ): ?>


                            <?php if (
                                $paginationEnd <
                                $totalPages - 1
                            ): ?>

                                <span class="order-page-dots">
                                    …
                                </span>

                            <?php endif; ?>


                            <a
                                href="<?= e(
                                    orderHistoryUrl(
                                        $activeTab,
                                        $search,
                                        $totalPages
                                    )
                                ) ?>"
                                class="order-page-link"
                            >
                                <?= $totalPages ?>
                            </a>


                        <?php endif; ?>


                        <!-- NEXT -->

                        <?php if (
                            $currentPage < $totalPages
                        ): ?>

                            <a
                                href="<?= e(
                                    orderHistoryUrl(
                                        $activeTab,
                                        $search,
                                        $currentPage + 1
                                    )
                                ) ?>"
                                class="order-page-link"
                            >
                                Next →
                            </a>

                        <?php else: ?>

                            <span
                                class="order-page-disabled"
                                aria-disabled="true"
                            >
                                Next →
                            </span>

                        <?php endif; ?>


                    </nav>


                <?php endif; ?>


            </div>


        <?php endif; ?>


    </div>


</div>


</main>


</div>


</body>

</html>