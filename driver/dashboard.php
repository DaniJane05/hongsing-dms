<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = currentUserId();

/*
|--------------------------------------------------------------------------
| DRIVER FEEDBACK
|--------------------------------------------------------------------------
*/

$feedbackStmt = $db->prepare("
    SELECT

        f.feedback_id,
        f.order_id,
        f.rating,
        f.comments,
        f.created_at,

        o.order_number,

        c.full_name AS customer_name

    FROM feedback f

    INNER JOIN `order` o
        ON o.order_id = f.order_id

    INNER JOIN customer c
        ON c.customer_id = f.customer_id

    WHERE f.driver_id = ?

    ORDER BY
        f.created_at DESC,
        f.feedback_id DESC

    LIMIT 10
");

$feedbackStmt->execute([
    $driverId
]);

$driverFeedback =
    $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FEEDBACK SUMMARY
|--------------------------------------------------------------------------
*/

$feedbackSummaryStmt = $db->prepare("
    SELECT

        COUNT(*) AS total_feedback,

        COALESCE(
            AVG(rating),
            0
        ) AS average_rating

    FROM feedback

    WHERE driver_id = ?
");

$feedbackSummaryStmt->execute([
    $driverId
]);

$feedbackSummary =
    $feedbackSummaryStmt->fetch(PDO::FETCH_ASSOC);

$totalFeedback =
    (int) (
        $feedbackSummary['total_feedback']
        ?? 0
    );

$averageRating =
    (float) (
        $feedbackSummary['average_rating']
        ?? 0
    );

// ============================================================
// GET CURRENT DRIVER
// ============================================================

$driverStmt = $db->prepare("
    SELECT
        d.driver_id,
        d.license_number,
        d.full_name,
        d.phone_number,
        d.email,
        d.status,
        d.current_truck_id,
        d.total_deliveries,
        d.rating,
        d.last_location,
        d.current_latitude,
        d.current_longitude,
        d.location_updated_at,

        t.plate_number,
        t.model,
        t.capacity,
        t.status AS truck_status

    FROM driver d

    LEFT JOIN truck t
        ON t.truck_id = d.current_truck_id

    WHERE d.driver_id = ?

    LIMIT 1
");

$driverStmt->execute([
    $driverId
]);

$driver = $driverStmt->fetch(PDO::FETCH_ASSOC);

// ============================================================
// DRIVER NOT FOUND
// ============================================================

if (!$driver) {

    session_destroy();

    header(
        "Location: " .
        BASE_URL .
        "login.php?error=driver_not_found"
    );

    exit;
}

// ============================================================
// TODAY'S DELIVERIES
// ============================================================

$todayStmt = $db->prepare("
    SELECT COUNT(*)

    FROM `order` o

    WHERE o.driver_id = ?

      AND o.completion_status != 'Cancelled'

      AND EXISTS (
          SELECT 1
          FROM delivery_status ds
          WHERE ds.order_id = o.order_id
            AND DATE(ds.timestamp) = CURDATE()
      )
");

$todayStmt->execute([
    $driverId
]);

$todayDeliveries =
    (int) $todayStmt->fetchColumn();

// ============================================================
// PENDING / UNSTARTED DELIVERIES
// ============================================================

$pendingStmt = $db->prepare("
    SELECT COUNT(*)

    FROM `order`

    WHERE driver_id = ?

      AND completion_status IN (
          'Pending',
          'Approved',
          'Assigned',
          'Accepted'
      )
");

$pendingStmt->execute([
    $driverId
]);

$pendingDeliveries =
    (int) $pendingStmt->fetchColumn();

// ============================================================
// IN TRANSIT DELIVERIES
// ============================================================

$transitStmt = $db->prepare("
    SELECT COUNT(*)

    FROM `order`

    WHERE driver_id = ?

      AND completion_status = 'In Transit'
");

$transitStmt->execute([
    $driverId
]);

$inTransitDeliveries =
    (int) $transitStmt->fetchColumn();

// ============================================================
// COMPLETED DELIVERIES
// ============================================================

$completedStmt = $db->prepare("
    SELECT COUNT(*)

    FROM `order`

    WHERE driver_id = ?

      AND completion_status = 'Completed'
");

$completedStmt->execute([
    $driverId
]);

$completedDeliveries =
    (int) $completedStmt->fetchColumn();

// ============================================================
// CURRENT ACTIVE DELIVERY
// ============================================================

$currentStmt = $db->prepare("
    SELECT

        o.order_id,
        o.order_number,
        o.total_amount,
        o.total_weight,
        o.total_weight_kg,
        o.order_date,
        o.priority_level,
        o.completion_status,

        c.full_name AS customer_name,
        c.phone_number AS customer_phone,

        dl.full_address AS delivery_address,
        dl.city AS delivery_city,
        dl.province AS delivery_province,

        dl.latitude AS destination_latitude,
        dl.longitude AS destination_longitude,

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

      AND o.completion_status IN (
          'Assigned',
          'Accepted',
          'In Transit'
      )

    ORDER BY

        CASE
            WHEN o.completion_status = 'In Transit'
                THEN 1

            WHEN o.completion_status = 'Accepted'
                THEN 2

            WHEN o.completion_status = 'Assigned'
                THEN 3

            ELSE 4
        END,

        CASE
            WHEN o.priority_level = 'Urgent'
                THEN 1
            ELSE 2
        END,

        o.order_id ASC

    LIMIT 1
");

$currentStmt->execute([
    $driverId
]);

$currentDelivery =
    $currentStmt->fetch(PDO::FETCH_ASSOC);

// ============================================================
// RECENT DELIVERIES
// ============================================================

$recentStmt = $db->prepare("
    SELECT

        o.order_id,
        o.order_number,
        o.order_date,
        o.priority_level,
        o.completion_status,

        c.full_name AS customer_name

    FROM `order` o

    LEFT JOIN customer c
        ON c.customer_id = o.customer_id

    WHERE o.driver_id = ?

    ORDER BY
        o.created_at DESC,
        o.order_id DESC

    LIMIT 5
");

$recentStmt->execute([
    $driverId
]);

$recentDeliveries =
    $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// DRIVER STATUS CLASS
// ============================================================

$driverStatusClass = match (
    $driver['status']
) {

    'Available'
        => 'status-delivered',

    'On Delivery'
        => 'status-transit',

    'Absent'
        => 'status-failed',

    default
        => 'status-pending'
};

// ============================================================
// GPS STATUS
// ============================================================

$hasGps =
    $driver['current_latitude'] !== null &&
    $driver['current_longitude'] !== null;

$gpsLastUpdated =
    $driver['location_updated_at']
    ?? null;

// ============================================================
// CHECK IF GPS UPDATE IS RECENT
// ============================================================

$gpsIsRecent = false;

if ($gpsLastUpdated) {

    $gpsTimestamp =
        strtotime($gpsLastUpdated);

    if ($gpsTimestamp !== false) {

        $gpsIsRecent =
            (time() - $gpsTimestamp) <= 300;
    }
}

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
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'driver/profile.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL .
            'driver/notifications.php'
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
        Driver Dashboard
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <!-- Leaflet -->

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>

        /* ==========================================================
   DRIVER FEEDBACK
========================================================== */

.feedback-summary {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}

.feedback-summary-card {

    padding: 18px;

}

.feedback-summary-label {

    display: block;

    color:
        var(--color-text-secondary);

    font-size: 13px;

    margin-bottom: 6px;

}

.feedback-summary-value {

    font-size: 28px;

    font-weight: 700;

}

.feedback-stars {

    color: #f59e0b;

    letter-spacing: 2px;

}

.driver-feedback-list {

    display: flex;

    flex-direction: column;

    gap: 15px;

}

.driver-feedback-item {

    padding: 17px;

    border:
        1px solid
        var(--color-border);

    border-radius: 10px;

    background:
        var(--color-surface);

}

.driver-feedback-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;

}

.feedback-customer {

    font-weight: 700;

}

.feedback-order {

    font-size: 12px;

    color:
        var(--color-text-secondary);

    margin-top: 3px;

}

.feedback-rating {

    white-space: nowrap;

}

.feedback-comment {

    margin-top: 10px;

    line-height: 1.5;

}

.feedback-date {

    margin-top: 10px;

    font-size: 12px;

    color:
        var(--color-text-secondary);

}

.no-feedback {

    padding: 30px;

    text-align: center;

    color:
        var(--color-text-secondary);

    border:
        1px dashed
        var(--color-border);

    border-radius: 10px;

}

@media (max-width: 600px) {

    .feedback-summary {

        grid-template-columns: 1fr;

    }

    .driver-feedback-header {

        flex-direction: column;

    }

}

        /* ========================================================
           DASHBOARD
        ======================================================== */

        .driver-dashboard {

            max-width: 1200px;

            margin: 0 auto;

        }

        /* ========================================================
           DRIVER HEADER
        ======================================================== */

        .driver-welcome {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 20px;

        }

        .driver-welcome h1 {

            margin: 0 0 5px;

        }

        .driver-welcome p {

            margin: 0;

            color:
                var(--color-text-secondary);

        }

        /* ========================================================
           DRIVER INFO
        ======================================================== */

        .driver-info-grid {

            display: grid;

            grid-template-columns:
                1.4fr
                1fr
                1fr;

            gap: 15px;

        }

        .driver-info-item {

            padding: 5px 0;

        }

        .driver-info-label {

            display: block;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .4px;

            color:
                var(--color-text-muted);

            margin-bottom: 5px;

        }

        .driver-info-value {

            font-weight: 600;

        }

        /* ========================================================
           STATISTICS
        ======================================================== */

        .driver-stat-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-top: 20px;

        }

        .driver-stat-card {

            padding: 20px;

        }

        .driver-stat-label {

            display: block;

            color:
                var(--color-text-secondary);

            font-size: 12px;

            margin-bottom: 8px;

        }

        .driver-stat-value {

            font-size: 30px;

            font-weight: 700;

        }

        /* ========================================================
           ACTIVE DELIVERY
        ======================================================== */

        .active-delivery-grid {

            display: grid;

            grid-template-columns:
                1fr
                1fr;

            gap: 20px;

        }

        .delivery-detail-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

        }

        .delivery-detail-label {

            display: block;

            color:
                var(--color-text-muted);

            font-size: 11px;

            text-transform: uppercase;

            margin-bottom: 4px;

        }

        .delivery-detail-value {

            font-size: 14px;

            font-weight: 600;

        }

        .delivery-address {

            margin-top: 20px;

            padding: 15px;

            border:
                1px solid
                var(--color-border);

            border-radius: 8px;

        }

        /* ========================================================
           MAP
        ======================================================== */

        #driver-route-map {

            width: 100%;

            height: 400px;

            border-radius: 10px;

            overflow: hidden;

        }

        .map-empty {

            height: 300px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            align-items: center;

            text-align: center;

            border:
                1px dashed
                var(--color-border);

            border-radius: 10px;

            color:
                var(--color-text-secondary);

            padding: 20px;

        }

        .map-empty-icon {

            font-size: 38px;

            margin-bottom: 10px;

        }

        /* ========================================================
           GPS STATUS
        ======================================================== */

        .gps-online {

            color: #15803d;

            font-weight: 600;

        }

        .gps-offline {

            color: #b45309;

            font-weight: 600;

        }

        .gps-none {

            color:
                var(--color-text-muted);

        }

        /* ========================================================
           ACTIONS
        ======================================================== */

        .delivery-actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 20px;

        }

        .delivery-actions a {

            text-decoration: none;

        }

        /* ========================================================
           RECENT TABLE
        ======================================================== */

        .recent-wrapper {

            overflow-x: auto;

        }

        /* ========================================================
           RESPONSIVE
        ======================================================== */

        @media (max-width: 900px) {

            .driver-info-grid {

                grid-template-columns: 1fr;

            }

            .driver-stat-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

            .active-delivery-grid {

                grid-template-columns: 1fr;

            }

        }

        @media (max-width: 600px) {

            .driver-welcome {

                align-items: flex-start;

                flex-direction: column;

            }

            .driver-stat-grid {

                grid-template-columns: 1fr;

            }

            .delivery-detail-grid {

                grid-template-columns: 1fr;

            }

            #driver-route-map {

                height: 320px;

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

        <div class="driver-dashboard">

            <!-- ==================================================
                 WELCOME
            ================================================== -->

            <div class="driver-welcome">

                <div>

                    <h1>

                        Welcome,
                        <?= htmlspecialchars(
                            $driver['full_name']
                        ) ?>!

                    </h1>

                    <p>

                        Here's your delivery activity
                        and current assignment.

                    </p>

                </div>

            </div>

            <!-- ==================================================
                 DRIVER INFORMATION
            ================================================== -->

            <div class="card panel">

                <div class="driver-info-grid">

                    <!-- DRIVER STATUS -->

                    <div class="driver-info-item">

                        <span class="driver-info-label">
                            Driver Status
                        </span>

                        <span
                            class="status-pill
                            <?= $driverStatusClass ?>"
                        >

                            <?= htmlspecialchars(
                                $driver['status']
                            ) ?>

                        </span>

                    </div>

                    <!-- TRUCK -->

                    <div class="driver-info-item">

                        <span class="driver-info-label">
                            Assigned Truck
                        </span>

                        <div class="driver-info-value">

                            <?php if (
                                !empty(
                                    $driver['plate_number']
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $driver['plate_number']
                                ) ?>

                                <?php if (
                                    !empty(
                                        $driver['model']
                                    )
                                ): ?>

                                    —
                                    <?= htmlspecialchars(
                                        $driver['model']
                                    ) ?>

                                <?php endif; ?>

                            <?php else: ?>

                                <span
                                    style="
                                        color:
                                        var(--color-text-muted);
                                    "
                                >
                                    No truck assigned
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- CAPACITY -->

                    <div class="driver-info-item">

                        <span class="driver-info-label">
                            Truck Capacity
                        </span>

                        <div class="driver-info-value">

                            <?php if (
                                $driver['capacity'] !== null
                            ): ?>

                                <?= number_format(
                                    (float)
                                    $driver['capacity'],
                                    2
                                ) ?>

                                kg

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

            <!-- ==================================================
                 STATISTICS
            ================================================== -->

            <div class="driver-stat-grid">

                <div class="card driver-stat-card">

                    <span class="driver-stat-label">
                        Today's Deliveries
                    </span>

                    <div class="driver-stat-value">

                        <?= $todayDeliveries ?>

                    </div>

                </div>

                <div class="card driver-stat-card">

                    <span class="driver-stat-label">
                        Pending
                    </span>

                    <div class="driver-stat-value">

                        <?= $pendingDeliveries ?>

                    </div>

                </div>

                <div class="card driver-stat-card">

                    <span class="driver-stat-label">
                        In Transit
                    </span>

                    <div class="driver-stat-value">

                        <?= $inTransitDeliveries ?>

                    </div>

                </div>

                <div class="card driver-stat-card">

                    <span class="driver-stat-label">
                        Completed
                    </span>

                    <div class="driver-stat-value">

                        <?= $completedDeliveries ?>

                    </div>

                </div>

            </div>

            <!-- ==================================================
                 CURRENT DELIVERY
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
                        gap:15px;
                        flex-wrap:wrap;
                        margin-bottom:20px;
                    "
                >

                    <div>

                        <h2>
                            Current Delivery
                        </h2>

                        <p
                            style="
                                color:
                                var(--color-text-secondary);
                                margin:5px 0 0;
                            "
                        >
                            Your highest-priority active delivery.
                        </p>

                    </div>

                    <a
                        href="<?= BASE_URL ?>driver/deliveries.php"
                        class="btn-primary"
                    >
                        My Deliveries
                    </a>

                </div>

                <?php if (!$currentDelivery): ?>

                    <div class="empty-state">

                        <strong>
                            No active delivery.
                        </strong>

                        <br>

                        You currently have no
                        assigned delivery to complete.

                    </div>

                <?php else: ?>

                    <div class="active-delivery-grid">

                        <!-- ==========================================
                             DELIVERY INFORMATION
                        =========================================== -->

                        <div>

                            <div class="delivery-detail-grid">

                                <div>

                                    <span
                                        class="delivery-detail-label"
                                    >
                                        Order Number
                                    </span>

                                    <div
                                        class="delivery-detail-value"
                                    >

                                        <?= htmlspecialchars(
                                            $currentDelivery[
                                                'order_number'
                                            ]
                                        ) ?>

                                    </div>

                                </div>

                                <div>

                                    <span
                                        class="delivery-detail-label"
                                    >
                                        Customer
                                    </span>

                                    <div
                                        class="delivery-detail-value"
                                    >

                                        <?= htmlspecialchars(
                                            $currentDelivery[
                                                'customer_name'
                                            ]
                                            ?? '-'
                                        ) ?>

                                    </div>

                                </div>

                                <div>

                                    <span
                                        class="delivery-detail-label"
                                    >
                                        Order Date
                                    </span>

                                    <div
                                        class="delivery-detail-value"
                                    >
                                        <?= !empty($currentDelivery['order_date'])
                                            ? date('M d, Y', strtotime($currentDelivery['order_date']))
                                            : '—' ?>
                                    </div>

                                </div>

                                <div>

                                    <span
                                        class="delivery-detail-label"
                                    >
                                        Priority
                                    </span>

                                    <div
                                        class="delivery-detail-value"
                                    >

                                        <?php if (
                                            $currentDelivery[
                                                'priority_level'
                                            ] === 'Urgent'
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

                                    </div>

                                </div>

                                <div>

                                    <span
                                        class="delivery-detail-label"
                                    >
                                        Status
                                    </span>

                                    <div
                                        class="delivery-detail-value"
                                    >

                                        <?php

                                        $activeStatus =
                                            $currentDelivery[
                                                'completion_status'
                                            ];

                                        $activeStatusClass =
                                            match (
                                                $activeStatus
                                            ) {

                                                'In Transit'
                                                    => 'status-transit',

                                                'Completed'
                                                    => 'status-delivered',

                                                'Cancelled'
                                                    => 'status-failed',

                                                default
                                                    => 'status-pending'
                                            };

                                        ?>

                                        <span
                                            class="
                                                status-pill
                                                <?= $activeStatusClass ?>
                                            "
                                        >

                                            <?= htmlspecialchars(
                                                $activeStatus
                                            ) ?>

                                        </span>

                                    </div>

                                </div>

                                <div>

                                    <span
                                        class="delivery-detail-label"
                                    >
                                        Total Weight
                                    </span>

                                    <div
                                        class="delivery-detail-value"
                                    >

                                        <?= number_format(
                                            (float)
                                            (
                                                $currentDelivery[
                                                    'total_weight_kg'
                                                ]
                                                ??
                                                $currentDelivery[
                                                    'total_weight'
                                                ]
                                                ??
                                                0
                                            ),
                                            2
                                        ) ?>

                                        kg

                                    </div>

                                </div>

                            </div>

                            <!-- DELIVERY ADDRESS -->

                            <div class="delivery-address">

                                <span
                                    class="delivery-detail-label"
                                >
                                    Delivery Address
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        $currentDelivery[
                                            'delivery_address'
                                        ]
                                        ??
                                        'Address not available'
                                    ) ?>

                                </strong>

                                <br>

                                <span
                                    style="
                                        color:
                                        var(--color-text-secondary);
                                    "
                                >

                                    <?= htmlspecialchars(
                                        $currentDelivery[
                                            'delivery_city'
                                        ]
                                        ?? ''
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $currentDelivery[
                                                'delivery_province'
                                            ]
                                        )
                                    ): ?>

                                        ,

                                        <?= htmlspecialchars(
                                            $currentDelivery[
                                                'delivery_province'
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </span>

                            </div>

                            <!-- CONTACT -->

                            <?php if (
                                !empty(
                                    $currentDelivery[
                                        'contact_person'
                                    ]
                                ) ||
                                !empty(
                                    $currentDelivery[
                                        'contact_number'
                                    ]
                                )
                            ): ?>

                                <div
                                    class="delivery-address"
                                    style="margin-top:10px;"
                                >

                                    <span
                                        class="delivery-detail-label"
                                    >
                                        Delivery Contact
                                    </span>

                                    <?php if (
                                        !empty(
                                            $currentDelivery[
                                                'contact_person'
                                            ]
                                        )
                                    ): ?>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $currentDelivery[
                                                    'contact_person'
                                                ]
                                            ) ?>

                                        </strong>

                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $currentDelivery[
                                                'contact_number'
                                            ]
                                        )
                                    ): ?>

                                        <br>

                                        <span>

                                            📞

                                            <?= htmlspecialchars(
                                                $currentDelivery[
                                                    'contact_number'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                            <!-- ACTION -->

                            <div class="delivery-actions">

                                <a
                                    href="<?= BASE_URL ?>driver/delivery_details.php?id=<?= (int)$currentDelivery['order_id'] ?>"
                                    class="btn-success"
                                >
                                    View Delivery Details
                                </a>

                            </div>

                        </div>

                        <!-- ==========================================
                             MAP
                        =========================================== -->

                        <div>

                            <div
                                style="
                                    display:flex;
                                    justify-content:space-between;
                                    align-items:center;
                                    gap:10px;
                                    margin-bottom:10px;
                                "
                            >

                                <div>

                                    <strong>
                                        Delivery Route
                                    </strong>

                                    <p
                                        style="
                                            margin:4px 0 0;
                                            color:
                                            var(--color-text-secondary);
                                            font-size:12px;
                                        "
                                    >

                                        Live driver location and actual road route
                                        to the delivery destination.

                                    </p>

                                </div>

                                <span
                                    id="route-status"
                                    class="
                                        status-pill
                                        status-pending
                                    "
                                >
                                    Loading map...
                                </span>

                            </div>

                            <?php

                            $destinationLatitude =
                                $currentDelivery[
                                    'destination_latitude'
                                ]
                                ?? null;

                            $destinationLongitude =
                                $currentDelivery[
                                    'destination_longitude'
                                ]
                                ?? null;

                            $hasDestination =
                                $destinationLatitude !== null &&
                                $destinationLongitude !== null;

                            ?>

                            <?php if (
                                $hasDestination
                            ): ?>

                                <div
                                    id="driver-route-map"
                                ></div>

                            <?php else: ?>

                                <div class="map-empty">

                                    <div
                                        class="map-empty-icon"
                                    >
                                        📍
                                    </div>

                                    <strong>
                                        Destination coordinates unavailable
                                    </strong>

                                    <span>
                                        The customer did not provide
                                        a pinned delivery location.
                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

            <!-- ==================================================
                 DRIVER PERFORMANCE / GPS
            ================================================== -->

            <div
                class="card panel"
                style="margin-top:20px;"
            >

                <h2>
                    Driver Performance & GPS
                </h2>

                <div
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(auto-fit,minmax(180px,1fr));
                        gap:20px;
                        margin-top:20px;
                    "
                >

                    <!-- TOTAL DELIVERIES -->

                    <div>

                        <span class="driver-stat-label">
                            Total Deliveries
                        </span>

                        <div class="driver-stat-value">

                            <?= (int)
                                $driver[
                                    'total_deliveries'
                                ] ?>

                        </div>

                    </div>

                    <!-- RATING -->

                    <div>

                        <span class="driver-stat-label">
                            Rating
                        </span>

                        <div class="driver-stat-value">

                            <?php if (
                                $driver['rating'] !== null
                            ): ?>

                                <?= number_format(
                                    (float)
                                    $driver['rating'],
                                    1
                                ) ?>/5

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- GPS STATUS -->

                    <div>

                        <span class="driver-stat-label">
                            GPS Status
                        </span>

                        <div
                            id="gps-status"
                            style="margin-top:8px;"
                        >

                            <?php if (
                                !$hasGps
                            ): ?>

                                <span
                                    class="gps-none"
                                >
                                    No location recorded
                                </span>

                            <?php elseif (
                                $gpsIsRecent
                            ): ?>

                                <span
                                    class="gps-online"
                                >
                                    ● GPS Active
                                </span>

                            <?php else: ?>

                                <span
                                    class="gps-offline"
                                >
                                    ● Location needs update
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- CURRENT COORDINATES -->

                    <div>

                        <span class="driver-stat-label">
                            Current Coordinates
                        </span>

                        <div
                            id="gps-coordinates"
                            style="margin-top:8px;"
                        >

                            <?php if (
                                $hasGps
                            ): ?>

                                <?= number_format(
                                    (float)
                                    $driver[
                                        'current_latitude'
                                    ],
                                    7
                                ) ?>,

                                <?= number_format(
                                    (float)
                                    $driver[
                                        'current_longitude'
                                    ],
                                    7
                                ) ?>

                            <?php else: ?>

                                No location recorded

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- LAST UPDATE -->

                    <div>

                        <span class="driver-stat-label">
                            Last GPS Update
                        </span>

                        <div
                            id="gps-updated"
                            style="margin-top:8px;"
                        >

                            <?php if (
                                $gpsLastUpdated
                            ): ?>

                                <?= htmlspecialchars(
                                    $gpsLastUpdated
                                ) ?>

                            <?php else: ?>

                                No GPS update yet.

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

            <!-- ==================================================
                 RECENT DELIVERIES
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
                        gap:15px;
                        margin-bottom:20px;
                    "
                >

                    <h2>
                        Recent Deliveries
                    </h2>

                    <a
                        href="<?= BASE_URL ?>driver/deliveries.php"
                        class="btn-secondary"
                    >
                        View All
                    </a>

                </div>

                <?php if (
                    empty($recentDeliveries)
                ): ?>

                    <div class="empty-state">

                        No delivery records found.

                    </div>

                <?php else: ?>

                    <div class="recent-wrapper">

                        <table class="data-table">

                            <thead>

                                <tr>

                                    <th>
                                        Order #
                                    </th>

                                    <th>
                                        Customer
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
                                    $recentDeliveries
                                    as $delivery
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $delivery[
                                                        'order_number'
                                                    ]
                                                ) ?>

                                            </strong>

                                        </td>

                                        <td>

                                            <?= htmlspecialchars(
                                                $delivery[
                                                    'customer_name'
                                                ]
                                                ?? '-'
                                            ) ?>

                                        </td>

                                        <td>

                                            <?= !empty($delivery['order_date'])
                                                ? date('M d, Y', strtotime($delivery['order_date']))
                                                : '—' ?>

                                        </td>

                                        <td>

                                            <?php if (
                                                $delivery[
                                                    'priority_level'
                                                ] === 'Urgent'
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

                                        <td>

                                            <?php

                                            $recentStatus =
                                                $delivery[
                                                    'completion_status'
                                                ];

                                            $recentStatusClass =
                                                match (
                                                    $recentStatus
                                                ) {

                                                    'Completed'
                                                        => 'status-delivered',

                                                    'In Transit'
                                                        => 'status-transit',

                                                    'Cancelled'
                                                        => 'status-failed',

                                                    default
                                                        => 'status-pending'
                                                };

                                            ?>

                                            <span
                                                class="
                                                    status-pill
                                                    <?= $recentStatusClass ?>
                                                "
                                            >

                                                <?= htmlspecialchars(
                                                    $recentStatus
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <a
                                                href="<?= BASE_URL ?>driver/delivery_details.php?id=<?= (int)$delivery['order_id'] ?>"
                                                class="btn-secondary"
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

            </div>

        </div>

        <!-- ==========================================================
     CUSTOMER FEEDBACK
========================================================== -->

<section style="margin-top:30px;">

    <div class="section-heading">

        <div>

            <h2>
                Customer Feedback
            </h2>

            <p>
                See what customers think about your delivery performance.
            </p>

        </div>

    </div>


    <!-- FEEDBACK SUMMARY -->

    <div class="feedback-summary">


        <div class="card feedback-summary-card">

            <span class="feedback-summary-label">
                Average Rating
            </span>

            <div class="feedback-summary-value">

                <?= number_format(
                    $averageRating,
                    1
                ) ?>

                <span
                    class="feedback-stars"
                    style="font-size:18px;"
                >
                    ★
                </span>

            </div>

        </div>


        <div class="card feedback-summary-card">

            <span class="feedback-summary-label">
                Total Feedback
            </span>

            <div class="feedback-summary-value">

                <?= $totalFeedback ?>

            </div>

        </div>


    </div>


    <!-- FEEDBACK LIST -->

    <div class="card panel">

        <?php if (
            empty($driverFeedback)
        ): ?>

            <div class="no-feedback">

                <div style="font-size:32px;margin-bottom:8px;">
                    ⭐
                </div>

                <strong>
                    No customer feedback yet
                </strong>

                <p style="margin:6px 0 0;">
                    Customer feedback will appear here
                    after you complete deliveries.
                </p>

            </div>


        <?php else: ?>


            <div class="driver-feedback-list">


                <?php foreach (
                    $driverFeedback
                    as $feedback
                ): ?>


                    <div class="driver-feedback-item">


                        <div class="driver-feedback-header">


                            <div>

                                <div class="feedback-customer">

                                    <?= htmlspecialchars(
                                        $feedback['customer_name']
                                    ) ?>

                                </div>


                                <div class="feedback-order">

                                    Order #

                                    <?= htmlspecialchars(
                                        $feedback['order_number']
                                    ) ?>

                                </div>

                            </div>


                            <div class="feedback-rating">

                                <?php for (
                                    $i = 1;
                                    $i <= 5;
                                    $i++
                                ): ?>

                                    <span
                                        style="
                                            color:
                                            <?= $i <= (int)$feedback['rating']
                                                ? '#f59e0b'
                                                : '#d1d5db' ?>;
                                        "
                                    >
                                        ★
                                    </span>

                                <?php endfor; ?>

                            </div>


                        </div>


                        <?php if (
                            !empty(
                                $feedback['comments']
                            )
                        ): ?>

                            <div class="feedback-comment">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $feedback['comments']
                                    )
                                ) ?>

                            </div>

                        <?php else: ?>

                            <div
                                class="feedback-comment"
                                style="
                                    color:
                                    var(--color-text-secondary);
                                    font-style:italic;
                                "
                            >

                                No comment provided.

                            </div>

                        <?php endif; ?>


                        <div class="feedback-date">

                            <?= date(
                                'M d, Y h:i A',
                                strtotime(
                                    $feedback['created_at']
                                )
                            ) ?>

                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>

    </div>

</section>

    </main>

</div>

<!-- ============================================================
     GPS UPDATE CONFIGURATION
============================================================ -->

<script>

    window.DRIVER_GPS_UPDATE_URL =
        <?= json_encode(
            BASE_URL .
            'driver/update_location.php'
        ) ?>;

</script>

<!-- Existing GPS updater -->

<script
    src="<?= BASE_URL ?>assets/js/driver-gps.js"
></script>

<!-- ============================================================
     LEAFLET
============================================================ -->

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>

<?php if (
    $currentDelivery &&
    $hasDestination
): ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const destinationLat = <?= json_encode((float) $destinationLatitude) ?>;
    const destinationLng = <?= json_encode((float) $destinationLongitude) ?>;
    const initialDriverLat = <?= $hasGps ? json_encode((float) $driver['current_latitude']) : 'null' ?>;
    const initialDriverLng = <?= $hasGps ? json_encode((float) $driver['current_longitude']) : 'null' ?>;

    const statusEl = document.getElementById('route-status');
    const map = L.map('driver-route-map', { zoomControl: true });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const destinationIcon = L.divIcon({
        className: '',
        html: '<div style="width:30px;height:30px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#dc2626;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35)"><span style="display:block;transform:rotate(45deg);text-align:center;line-height:24px;font-size:14px;color:#fff">●</span></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0, -30]
    });

    const driverIcon = L.divIcon({
        className: '',
        html: '<div style="width:30px;height:30px;border-radius:50%;background:#2563eb;border:4px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35)"></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -18]
    });

    const destinationMarker = L.marker(
        [destinationLat, destinationLng],
        { icon: destinationIcon }
    ).addTo(map).bindPopup(
        '<strong>Delivery Destination</strong><br>' +
        <?= json_encode($currentDelivery['delivery_address'] ?? 'Delivery address') ?>
    );

    let driverMarker = null;
    let routeLayer = null;
    let fallbackLine = null;
    let lastRouteAt = 0;
    let lastRouteLat = null;
    let lastRouteLng = null;

    function setStatus(text, className) {
        statusEl.textContent = text;
        statusEl.className = 'status-pill ' + (className || 'status-pending');
    }

    function distanceMovedMeters(lat1, lng1, lat2, lng2) {
        if (lat2 === null || lng2 === null) return Infinity;
        const R = 6371000;
        const p1 = lat1 * Math.PI / 180;
        const p2 = lat2 * Math.PI / 180;
        const dp = (lat2 - lat1) * Math.PI / 180;
        const dl = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dp / 2) ** 2 +
            Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) ** 2;
        return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function showFallback(lat, lng) {
        if (fallbackLine) map.removeLayer(fallbackLine);
        fallbackLine = L.polyline(
            [[lat, lng], [destinationLat, destinationLng]],
            { dashArray: '7 8', weight: 4, opacity: 0.65 }
        ).addTo(map);
        map.fitBounds(
            L.latLngBounds([[lat, lng], [destinationLat, destinationLng]]),
            { padding: [45, 45], maxZoom: 16 }
        );
    }

    async function drawRoadRoute(lat, lng, force = false) {
        const now = Date.now();
        const moved = distanceMovedMeters(lat, lng, lastRouteLat, lastRouteLng);

        // Avoid hammering the public routing service while GPS is updating.
        if (!force && now - lastRouteAt < 20000 && moved < 80) return;

        lastRouteAt = now;
        lastRouteLat = lat;
        lastRouteLng = lng;
        setStatus('Calculating route...', 'status-pending');

        try {
            const url =
                'https://router.project-osrm.org/route/v1/driving/' +
                encodeURIComponent(lng) + ',' + encodeURIComponent(lat) + ';' +
                encodeURIComponent(destinationLng) + ',' + encodeURIComponent(destinationLat) +
                '?overview=full&geometries=geojson&steps=false';

            const response = await fetch(url);
            if (!response.ok) throw new Error('Routing service unavailable');

            const data = await response.json();
            if (!data.routes || !data.routes.length) throw new Error('No road route found');

            const route = data.routes[0];
            const latLngs = route.geometry.coordinates.map(function (point) {
                return [point[1], point[0]];
            });

            if (routeLayer) map.removeLayer(routeLayer);
            if (fallbackLine) {
                map.removeLayer(fallbackLine);
                fallbackLine = null;
            }

            routeLayer = L.polyline(latLngs, { weight: 6, opacity: 0.85 }).addTo(map);
            map.fitBounds(routeLayer.getBounds(), { padding: [45, 45], maxZoom: 16 });

            const km = route.distance / 1000;
            const mins = Math.max(1, Math.round(route.duration / 60));
            setStatus(km.toFixed(1) + ' km · ~' + mins + ' min', 'status-transit');
        } catch (error) {
            showFallback(lat, lng);
            setStatus('Road route unavailable', 'status-pending');
        }
    }

    function updateDriverOnMap(lat, lng, forceRoute = false) {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        if (!driverMarker) {
            driverMarker = L.marker([lat, lng], { icon: driverIcon })
                .addTo(map)
                .bindPopup('<strong>Your Current Location</strong>');
        } else {
            driverMarker.setLatLng([lat, lng]);
        }

        drawRoadRoute(lat, lng, forceRoute);
    }

    if (initialDriverLat !== null && initialDriverLng !== null) {
        updateDriverOnMap(initialDriverLat, initialDriverLng, true);
    } else {
        map.setView([destinationLat, destinationLng], 15);
        setStatus('Waiting for Driver GPS', 'status-pending');
    }

    // Keep the marker/route visually updated from the phone/browser GPS.
    if ('geolocation' in navigator) {
        navigator.geolocation.watchPosition(
            function (position) {
                const lat = Number(position.coords.latitude);
                const lng = Number(position.coords.longitude);
                updateDriverOnMap(lat, lng, false);
            },
            function () {
                if (!driverMarker) {
                    setStatus('Enable location access', 'status-pending');
                }
            },
            {
                enableHighAccuracy: true,
                maximumAge: 10000,
                timeout: 15000
            }
        );
    }

    setTimeout(function () {
        map.invalidateSize();
    }, 150);
});

</script>

<?php endif; ?>

</body>

</html>