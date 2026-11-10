<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = currentUserId();

// ============================================================
// SEARCH / FILTER
// ============================================================

$search = trim($_GET['search'] ?? '');

$statusFilter = trim(
    $_GET['status'] ?? ''
);

$priorityFilter = trim(
    $_GET['priority'] ?? ''
);

// ============================================================
// ALLOWED FILTER VALUES
// ============================================================

$allowedStatuses = [
    'Assigned',
    'Accepted',
    'In Transit',
    'Completed',
    'Cancelled'
];

$allowedPriorities = [
    'Normal',
    'Urgent'
];

if (
    $statusFilter !== '' &&
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = '';
}

if (
    $priorityFilter !== '' &&
    !in_array(
        $priorityFilter,
        $allowedPriorities,
        true
    )
) {
    $priorityFilter = '';
}

// ============================================================
// DELIVERY QUERY
// ============================================================

$sql = "

    SELECT

        o.order_id,
        o.order_number,
        o.order_date,
        o.priority_level,
        o.completion_status,

        o.total_weight,
        o.total_weight_kg,

        c.full_name AS customer_name,
        c.phone_number AS customer_phone,

        dl.full_address AS delivery_address,
        dl.city AS delivery_city,
        dl.province AS delivery_province,

        dl.latitude AS delivery_latitude,
        dl.longitude AS delivery_longitude,

        dl.contact_person,
        dl.contact_number

    FROM `order` o

    LEFT JOIN customer c
        ON c.customer_id = o.customer_id

    LEFT JOIN delivery_location dl
        ON dl.location_id = (

            SELECT MAX(dl2.location_id)

            FROM delivery_location dl2

            WHERE dl2.order_id = o.order_id

        )

    WHERE o.driver_id = ?
      AND o.completion_status IN ('Assigned', 'Accepted', 'In Transit', 'Completed', 'Cancelled')

";

$params = [
    $driverId
];

// ============================================================
// SEARCH
// ============================================================

if ($search !== '') {

    $sql .= "

        AND (

            o.order_number LIKE ?

            OR c.full_name LIKE ?

            OR c.phone_number LIKE ?

            OR dl.full_address LIKE ?

            OR dl.city LIKE ?

            OR dl.province LIKE ?

        )

    ";

    $searchValue =
        '%' .
        $search .
        '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

// ============================================================
// STATUS FILTER
// ============================================================

if ($statusFilter !== '') {

    $sql .= "

        AND o.completion_status = ?

    ";

    $params[] =
        $statusFilter;
}

// ============================================================
// PRIORITY FILTER
// ============================================================

if ($priorityFilter !== '') {

    $sql .= "

        AND o.priority_level = ?

    ";

    $params[] =
        $priorityFilter;
}

// ============================================================
// ORDERING
// ============================================================

$sql .= "

    ORDER BY

        CASE
            WHEN o.completion_status = 'In Transit'
                THEN 1

            WHEN o.completion_status = 'Accepted'
                THEN 2

            WHEN o.completion_status = 'Assigned'
                THEN 3

            WHEN o.completion_status = 'Approved'
                THEN 4

            WHEN o.completion_status = 'Pending'
                THEN 5

            WHEN o.completion_status = 'Completed'
                THEN 6

            WHEN o.completion_status = 'Cancelled'
                THEN 7

            ELSE 8

        END,

        CASE
            WHEN o.priority_level = 'Urgent'
                THEN 1
            ELSE 2
        END,

        o.order_date ASC,

        o.order_id DESC

";

// ============================================================
// EXECUTE
// ============================================================

$stmt =
    $db->prepare($sql);

$stmt->execute(
    $params
);

$deliveries =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

// ============================================================
// DELIVERY COUNTS
// ============================================================

$countStmt = $db->prepare("

    SELECT

        COUNT(*) AS total,

        SUM(
            CASE
                WHEN completion_status IN (
                    'Assigned',
                    'Accepted',
                    'In Transit'
                )
                THEN 1
                ELSE 0
            END
        ) AS active,

        SUM(
            CASE
                WHEN completion_status = 'Assigned'
                THEN 1
                ELSE 0
            END
        ) AS assigned,

        SUM(
            CASE
                WHEN completion_status = 'Accepted'
                THEN 1
                ELSE 0
            END
        ) AS accepted,

        SUM(
            CASE
                WHEN completion_status = 'In Transit'
                THEN 1
                ELSE 0
            END
        ) AS in_transit,

        SUM(
            CASE
                WHEN completion_status = 'Completed'
                THEN 1
                ELSE 0
            END
        ) AS completed,

        SUM(
            CASE
                WHEN completion_status = 'Cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelled

    FROM `order`

    WHERE driver_id = ?

");

$countStmt->execute([
    $driverId
]);

$counts =
    $countStmt->fetch(
        PDO::FETCH_ASSOC
    );

// ============================================================
// NAVIGATION
// ============================================================

$navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL .
            'driver/dashboard.php'
    ],

    [
        'label' => 'My Deliveries',
        'url' =>
            BASE_URL .
            'driver/deliveries.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL .
            'driver/notifications.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'driver/profile.php'
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
        My Deliveries
    </title>

    

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        .delivery-page {

            max-width: 1250px;

            margin: 0 auto;

        }

        /* ======================================================
           FILTER
        ====================================================== */

        .delivery-filter {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            align-items: center;

        }

        .delivery-filter .form-input {

            min-width: 150px;

        }

        .delivery-search {

            flex: 1;

            min-width: 220px;

        }

        /* ======================================================
           TABLE
        ====================================================== */

        .delivery-table-wrapper {

            overflow-x: auto;

        }

        .delivery-table {

            width: 100%;

            border-collapse: collapse;

        }

        .delivery-table th,
        .delivery-table td {

            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid
                var(--color-border);

            vertical-align: middle;

        }

        .delivery-table th {

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: .4px;

            color:
                var(--color-text-secondary);

        }

        .delivery-table tbody tr:hover {

            background:
                var(--color-surface-hover);

        }

        .order-number {

            font-weight: 700;

        }

        .customer-name {

            font-weight: 600;

        }

        .customer-phone {

            font-size: 12px;

            color:
                var(--color-text-secondary);

            margin-top: 3px;

        }

        .delivery-address {

            max-width: 260px;

            line-height: 1.4;

        }

        .delivery-city {

            font-size: 12px;

            color:
                var(--color-text-secondary);

            margin-top: 3px;

        }

        /* ======================================================
           STATUS
        ====================================================== */

        .status-pill {

            white-space: nowrap;

        }

        /* ======================================================
           EMPTY
        ====================================================== */

        .empty-state {

            text-align: center;

            padding: 50px 20px;

            color:
                var(--color-text-secondary);

        }

        /* ======================================================
           MOBILE
        ====================================================== */

        @media (
            max-width: 700px
        ) {

            .delivery-filter {

                flex-direction: column;

                align-items: stretch;

            }

            .delivery-filter
            .form-input,
            .delivery-search {

                width: 100%;

                min-width: 0;

            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php
    require __DIR__ .
        '/../includes/header.php';
    ?>

    <main class="app-main">

        <div class="delivery-page">

            <!-- ==================================================
                 PAGE HEADER
            ================================================== -->

            <div class="page-header">

                <div>

                    <h1>
                        My Deliveries
                    </h1>

                    <p>

                        View and manage all deliveries
                        assigned to you.

                    </p>

                </div>

            </div>

            <!-- ==================================================
                 SUMMARY
            ================================================== -->

            <div class="stat-grid">

                <!-- TOTAL -->

                <div
                    class="card stat-card accent"
                >

                    <span class="stat-label">
                        Total Deliveries
                    </span>

                    <div class="stat-value">

                        <?= (int)(
                            $counts['total']
                            ?? 0
                        ) ?>

                    </div>

                </div>

                <!-- PENDING -->

                <div
                    class="card stat-card"
                >

                    <span class="stat-label">
                        Pending
                    </span>

                    <div class="stat-value">

                        <?= (int)(
                            $counts['active']
                            ?? 0
                        ) ?>

                    </div>

                </div>

                <!-- ASSIGNED -->

                <div
                    class="card stat-card"
                >

                    <span class="stat-label">
                        Assigned
                    </span>

                    <div class="stat-value">

                        <?= (int)(
                            $counts['assigned']
                            ?? 0
                        ) ?>

                    </div>

                </div>

                <!-- ACCEPTED -->

                <div
                    class="card stat-card"
                >

                    <span class="stat-label">
                        Accepted
                    </span>

                    <div class="stat-value">

                        <?= (int)(
                            $counts['accepted']
                            ?? 0
                        ) ?>

                    </div>

                </div>

                <!-- IN TRANSIT -->

                <div
                    class="card stat-card"
                >

                    <span class="stat-label">
                        In Transit
                    </span>

                    <div class="stat-value">

                        <?= (int)(
                            $counts['in_transit']
                            ?? 0
                        ) ?>

                    </div>

                </div>

                <!-- COMPLETED -->

                <div
                    class="card stat-card"
                >

                    <span class="stat-label">
                        Completed
                    </span>

                    <div class="stat-value">

                        <?= (int)(
                            $counts['completed']
                            ?? 0
                        ) ?>

                    </div>

                </div>

            </div>

            <!-- ==================================================
                 FILTERS
            ================================================== -->

            <div
                class="card panel"
                style="margin-top:20px;"
            >

                <form
                    method="GET"
                    class="delivery-filter"
                >

                    <!-- SEARCH -->

                    <input
                        type="text"
                        name="search"
                        class="
                            form-input
                            delivery-search
                        "
                        placeholder="
                            Search order,
                            customer, city or address...
                        "
                        value="<?= htmlspecialchars(
                            $search
                        ) ?>"
                    >

                    <!-- STATUS -->

                    <select
                        name="status"
                        class="form-input"
                    >

                        <option value="">
                            All Status
                        </option>

                        <?php foreach (
                            $allowedStatuses
                            as $status
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $status
                                ) ?>"
                                <?= $statusFilter === $status
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $status
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <!-- PRIORITY -->

                    <select
                        name="priority"
                        class="form-input"
                    >

                        <option value="">
                            All Priority
                        </option>

                        <?php foreach (
                            $allowedPriorities
                            as $priority
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $priority
                                ) ?>"
                                <?= $priorityFilter === $priority
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $priority
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <!-- SEARCH -->

                    <button
                        type="submit"
                        class="btn-primary"
                    >

                        Search

                    </button>

                    <!-- RESET -->

                    <a
                        href="<?= BASE_URL ?>driver/deliveries.php"
                        class="btn-secondary"
                    >

                        Reset

                    </a>

                </form>

            </div>

            <!-- ==================================================
                 DELIVERY TABLE
            ================================================== -->

            <div
                class="card panel"
                style="margin-top:20px;"
            >

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                        gap:10px;
                        margin-bottom:20px;
                    "
                >

                    <div>

                        <h2>
                            Assigned Deliveries
                        </h2>

                        <p
                            style="
                                margin:5px 0 0;
                                color:
                                var(--color-text-secondary);
                                font-size:13px;
                            "
                        >

                            <?= count(
                                $deliveries
                            ) ?>

                            delivery record(s) found.

                        </p>

                    </div>

                </div>

                <div
                    class="delivery-table-wrapper"
                >

                    <?php if (
                        empty($deliveries)
                    ): ?>

                        <div class="empty-state">

                            <div
                                style="
                                    font-size:40px;
                                    margin-bottom:10px;
                                "
                            >
                                📦
                            </div>

                            <strong>
                                No deliveries found.
                            </strong>

                            <p>

                                Try changing your
                                search or filters.

                            </p>

                        </div>

                    <?php else: ?>

                        <table
                            class="delivery-table"
                        >

                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Delivery Address
                                    </th>

                                    <th>
                                        Order Date
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

                                <?php foreach (
                                    $deliveries
                                    as $delivery
                                ): ?>

                                    <tr>

                                        <!-- =================================
                                             ORDER
                                        ================================== -->

                                        <td>

                                            <div
                                                class="order-number"
                                            >

                                                <?= htmlspecialchars(
                                                    $delivery[
                                                        'order_number'
                                                    ]
                                                ) ?>

                                            </div>

                                            <small
                                                style="
                                                    color:
                                                    var(--color-text-secondary);
                                                "
                                            >

                                                Order date:

                                                <?= !empty(
                                                    $delivery[
                                                        'order_date'
                                                    ]
                                                )
                                                    ? date(
                                                        'M d, Y',
                                                        strtotime(
                                                            $delivery[
                                                                'order_date'
                                                            ]
                                                        )
                                                    )
                                                    : '—'
                                                ?>

                                            </small>

                                        </td>

                                        <!-- =================================
                                             CUSTOMER
                                        ================================== -->

                                        <td>

                                            <div
                                                class="customer-name"
                                            >

                                                <?= htmlspecialchars(
                                                    $delivery[
                                                        'customer_name'
                                                    ]
                                                    ?? '-'
                                                ) ?>

                                            </div>

                                            <?php if (
                                                !empty(
                                                    $delivery[
                                                        'customer_phone'
                                                    ]
                                                )
                                            ): ?>

                                                <div
                                                    class="
                                                        customer-phone
                                                    "
                                                >

                                                    📞

                                                    <?= htmlspecialchars(
                                                        $delivery[
                                                            'customer_phone'
                                                        ]
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>

                                        </td>

                                        <!-- =================================
                                             DELIVERY ADDRESS
                                        ================================== -->

                                        <td>

                                            <div
                                                class="
                                                    delivery-address
                                                "
                                            >

                                                <?= htmlspecialchars(
                                                    $delivery[
                                                        'delivery_address'
                                                    ]
                                                    ??
                                                    'Address not available'
                                                ) ?>

                                            </div>

                                            <?php if (
                                                !empty(
                                                    $delivery[
                                                        'delivery_city'
                                                    ]
                                                ) ||
                                                !empty(
                                                    $delivery[
                                                        'delivery_province'
                                                    ]
                                                )
                                            ): ?>

                                                <div
                                                    class="
                                                        delivery-city
                                                    "
                                                >

                                                    <?= htmlspecialchars(
                                                        $delivery[
                                                            'delivery_city'
                                                        ]
                                                        ?? ''
                                                    ) ?>

                                                    <?php if (
                                                        !empty(
                                                            $delivery[
                                                                'delivery_city'
                                                            ]
                                                        ) &&
                                                        !empty(
                                                            $delivery[
                                                                'delivery_province'
                                                            ]
                                                        )
                                                    ): ?>

                                                        ,

                                                    <?php endif; ?>

                                                    <?= htmlspecialchars(
                                                        $delivery[
                                                            'delivery_province'
                                                        ]
                                                        ?? ''
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>

                                            <?php if (
                                                $delivery[
                                                    'delivery_latitude'
                                                ] !== null &&
                                                $delivery[
                                                    'delivery_longitude'
                                                ] !== null
                                            ): ?>

                                                <div
                                                    style="
                                                        font-size:11px;
                                                        margin-top:5px;
                                                        color:#15803d;
                                                    "
                                                >

                                                    📍 Location pinned

                                                </div>

                                            <?php else: ?>

                                                <div
                                                    style="
                                                        font-size:11px;
                                                        margin-top:5px;
                                                        color:
                                                        var(--color-text-muted);
                                                    "
                                                >

                                                    Location not pinned

                                                </div>

                                            <?php endif; ?>

                                        </td>

                                        <!-- =================================
                                             DATE
                                        ================================== -->

                                        <td>

                                            <?= !empty($delivery['order_date'])
                                                ? date('M d, Y', strtotime($delivery['order_date']))
                                                : '—' ?>

                                        </td>

                                        <!-- =================================
                                             PRIORITY
                                        ================================== -->

                                        <td>

                                            <?php if (
                                                $delivery[
                                                    'priority_level'
                                                ]
                                                === 'Urgent'
                                            ): ?>

                                                <span
                                                    class="
                                                        status-pill
                                                        status-failed
                                                    "
                                                >

                                                    Urgent

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="
                                                        status-pill
                                                        status-pending
                                                    "
                                                >

                                                    Normal

                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <!-- =================================
                                             STATUS
                                        ================================== -->

                                        <td>

                                            <?php

                                            $deliveryStatus =
                                                $delivery[
                                                    'completion_status'
                                                ];

                                            $statusClass =
                                                match (
                                                    $deliveryStatus
                                                ) {

                                                    'Completed'
                                                        => 'status-delivered',

                                                    'In Transit'
                                                        => 'status-transit',

                                                    'Cancelled'
                                                        => 'status-failed',

                                                    'Accepted'
                                                        => 'status-delivered',

                                                    default
                                                        => 'status-pending'
                                                };

                                            ?>

                                            <span
                                                class="
                                                    status-pill
                                                    <?= $statusClass ?>
                                                "
                                            >

                                                <?= htmlspecialchars(
                                                    $deliveryStatus
                                                ) ?>

                                            </span>

                                        </td>

                                        <!-- =================================
                                             ACTION
                                        ================================== -->

                                        <td>

                                            <a
                                                href="<?= BASE_URL ?>driver/delivery_details.php?id=<?= (int)$delivery['order_id'] ?>"
                                                class="btn-primary"
                                            >

                                                View

                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>

</body>

</html>
