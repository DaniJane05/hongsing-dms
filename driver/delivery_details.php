<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = currentUserId();

$orderId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($orderId <= 0) {
    header(
        'Location: ' .
        BASE_URL .
        'driver/deliveries.php'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| GET DRIVER
|--------------------------------------------------------------------------
*/

$driverStmt = $db->prepare("
    SELECT
        driver_id,
        full_name,
        phone_number,
        email,
        status,
        current_truck_id,
        current_latitude,
        current_longitude,
        location_updated_at
    FROM driver
    WHERE driver_id = ?
    LIMIT 1
");

$driverStmt->execute([
    $driverId
]);

$driver = $driverStmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    header(
        'Location: ' .
        BASE_URL .
        'login.php?error=driver_not_found'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| GET DELIVERY
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Driver can only open deliveries assigned to the
| currently logged-in driver.
|
| Product/item description is intentionally NOT selected.
|
*/

$orderStmt = $db->prepare("
    SELECT

        o.order_id,
        o.order_number,
        o.total_amount,
        o.total_weight,
        o.total_weight_kg,
        o.order_date,
        o.priority_level,
        o.completion_status,

        c.customer_id,
        c.full_name AS customer_name,
        c.phone_number AS customer_phone,
        c.email AS customer_email,

        dl.location_id,
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

    WHERE o.order_id = ?

      AND o.driver_id = ?

    LIMIT 1
");

$orderStmt->execute([
    $orderId,
    $driverId
]);

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header(
        'Location: ' .
        BASE_URL .
        'driver/deliveries.php?error=delivery_not_found'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| GET TRUCK
|--------------------------------------------------------------------------
*/

$truck = null;

if (!empty($driver['current_truck_id'])) {

    $truckStmt = $db->prepare("
        SELECT
            truck_id,
            plate_number,
            model,
            capacity,
            status
        FROM truck
        WHERE truck_id = ?
        LIMIT 1
    ");

    $truckStmt->execute([
        $driver['current_truck_id']
    ]);

    $truck = $truckStmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| GET DELIVERY STATUS HISTORY
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

$statusHistory = $statusStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| LATEST DELIVERY STATUS
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
| DISPLAY STATUS
|--------------------------------------------------------------------------
*/

$orderStatus =
    $order['completion_status']
    ?? 'Pending';


$statusClass = match ($orderStatus) {

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


/*
|--------------------------------------------------------------------------
| ACTION AVAILABILITY
|--------------------------------------------------------------------------
*/

$canAccept =
    $orderStatus === 'Assigned';

$canStart =
    $orderStatus === 'Accepted';

$canComplete =
    $orderStatus === 'In Transit';


/*
|--------------------------------------------------------------------------
| GPS
|--------------------------------------------------------------------------
*/

$hasDriverGps =
    $driver['current_latitude'] !== null &&
    $driver['current_longitude'] !== null;


$hasDestination =
    $order['destination_latitude'] !== null &&
    $order['destination_longitude'] !== null;


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

$navItems = [
    [
        'label' => 'Dashboard',
        'url' => BASE_URL . 'driver/dashboard.php'
    ],
    [
        'label' => 'My Deliveries',
        'url' => BASE_URL . 'driver/deliveries.php'
    ],
    [
        'label' => 'Profile',
        'url' => BASE_URL . 'driver/profile.php'
    ],
    [
        'label' => 'Notifications',
        'url' => BASE_URL . 'driver/notifications.php'
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
        Delivery Details —
        <?= htmlspecialchars(
            $order['order_number']
        ) ?>
    </title>


    


    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >


    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        .delivery-details-page {

            width: 100%;

            max-width: 1400px;

            margin: 0 auto;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .delivery-header {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 20px;

            flex-wrap: wrap;

            margin-bottom: 20px;

        }


        .delivery-header h1 {

            margin: 0 0 6px;

        }


        .delivery-header p {

            margin: 0;

            color:
                var(--color-text-secondary);

        }


        .action-panel {

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN GRID
        |--------------------------------------------------------------------------
        */

        .details-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1.15fr)
                minmax(360px, .85fr);

            gap: 20px;

            align-items: start;

        }


        .details-left,
        .details-right {

            min-width: 0;

        }


        .details-right {

            position: sticky;

            top: 20px;

        }


        /* ==========================================================
   APP LAYOUT
========================================================== */

.app-layout {
    display: flex;
    min-height: 100vh;
    width: 100%;
}

.app-sidebar {
    flex: 0 0 240px;
    width: 240px;
}

.app-main {
    flex: 1 1 auto;
    min-width: 0;
}

/* ==========================================================
   DELIVERY DETAILS
========================================================== */

.delivery-details-page {
    max-width: 1200px;
    margin: 0 auto;
}

        /*
        |--------------------------------------------------------------------------
        | WORKFLOW
        |--------------------------------------------------------------------------
        */

        /* ==========================================================
   DELIVERY PROGRESS / WORKFLOW
========================================================== */

.workflow {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 20px;
}

.workflow-step {
    padding: 18px 12px;

    border: 1px solid var(--color-border);

    border-radius: 10px;

    text-align: center;

    background: #ffffff;

    transition:
        border-color 0.2s ease,
        background 0.2s ease,
        box-shadow 0.2s ease;
}

/* Completed / current workflow step */
.workflow-step.active {
    border-color: #2563eb;

    background: #eff6ff;

    box-shadow:
        0 2px 8px rgba(37, 99, 235, 0.08);
}

/* Number circle */
.workflow-number {
    width: 38px;
    height: 38px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin: 0 auto 10px;

    border-radius: 50%;

    background: #e2e8f0;

    border: 2px solid #cbd5e1;

    color: #0f1b30;

    font-size: 15px;

    font-weight: 700;

    line-height: 1;
}

/* Number for completed/current steps */
.workflow-step.active .workflow-number {
    background: #2563eb;

    border-color: #2563eb;

    color: #ffffff;
}

/* Workflow label */
.workflow-label {
    font-size: 13px;

    font-weight: 600;

    color: #334155;
}

/* Label for active steps */
.workflow-step.active .workflow-label {
    color: #1d4ed8;
}

/* ==========================================================
   WORKFLOW RESPONSIVE
========================================================== */

@media (max-width: 650px) {

    .workflow {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 420px) {

    .workflow {
        grid-template-columns: 1fr;
    }

}


        /*
        |--------------------------------------------------------------------------
        | INFO
        |--------------------------------------------------------------------------
        */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

        }


        .info-label {

            display: block;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .4px;

            color:
                var(--color-text-muted);

            margin-bottom: 5px;

        }


        .info-value {

            font-weight: 600;

            line-height: 1.45;

            color:
                var(--color-text-primary);

        }


        /*
        |--------------------------------------------------------------------------
        | ADDRESS
        |--------------------------------------------------------------------------
        */

        .address-box {

            margin-top: 20px;

            padding: 15px;

            border:
                1px solid
                var(--color-border);

            border-radius: 8px;

            background:
                var(--color-surface);

        }


        /*
        |--------------------------------------------------------------------------
        | MAP
        |--------------------------------------------------------------------------
        */

        #delivery-map {

            width: 100%;

            height: 430px;

            border-radius: 10px;

            overflow: hidden;

            border:
                1px solid
                var(--color-border);

        }


        .map-empty {

            min-height: 300px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            align-items: center;

            text-align: center;

            padding: 25px;

            border:
                1px dashed
                var(--color-border);

            border-radius: 10px;

            color:
                var(--color-text-secondary);

        }


        /*
        |--------------------------------------------------------------------------
        | GPS
        |--------------------------------------------------------------------------
        */

        .gps-box {

            padding: 15px;

            border:
                1px solid
                var(--color-border);

            border-radius: 8px;

            margin-top: 20px;

            background:
                var(--color-surface);

        }


        .gps-status {

            font-weight: 600;

        }


        .gps-active {

            color:
                #15803d;

        }


        .gps-waiting {

            color:
                #b45309;

        }


        /*
        |--------------------------------------------------------------------------
        | TIMELINE
        |--------------------------------------------------------------------------
        */

        .timeline {

            position: relative;

            margin-top: 10px;

        }


        .timeline-item {

            position: relative;

            padding-left: 30px;

            padding-bottom: 22px;

        }


        .timeline-item:last-child {

            padding-bottom: 0;

        }


        .timeline-item::before {

            content: '';

            position: absolute;

            left: 7px;

            top: 8px;

            bottom: -2px;

            width: 2px;

            background:
                var(--color-border);

        }


        .timeline-item:last-child::before {

            display: none;

        }


        .timeline-dot {

            position: absolute;

            left: 0;

            top: 5px;

            width: 16px;

            height: 16px;

            border-radius: 50%;

            background:
                var(--color-primary);

            border:
                3px solid
                var(--color-surface);

            box-shadow:
                0 0 0 1px
                var(--color-primary);

        }


        .timeline-status {

            font-weight: 700;

            color:
                var(--color-text-primary);

        }


        .timeline-time {

            font-size: 11px;

            color:
                var(--color-text-muted);

            margin-top: 3px;

        }


        .timeline-notes {

            font-size: 12px;

            color:
                var(--color-text-secondary);

            margin-top: 5px;

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 900px
        ) {

            .details-grid {

                grid-template-columns: 1fr;

            }


            .details-right {

                position: static;

            }

        }


        @media (
            max-width: 650px
        ) {

            .info-grid {

                grid-template-columns: 1fr;

            }


            .workflow {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            #delivery-map {

                height: 350px;

            }

        }


        /* Customer-style Leaflet map pins */
        .custom-map-pin-wrap { background: transparent; border: 0; }
        .map-pin {
            width: 38px; height: 38px; border-radius: 50% 50% 50% 8px;
            transform: rotate(-45deg); display: flex; align-items: center; justify-content: center;
            border: 3px solid #fff; box-shadow: 0 3px 10px rgba(15,23,42,.28);
        }
        .map-pin svg { width: 21px; height: 21px; fill: #fff; transform: rotate(45deg); }
        .map-pin-customer { background: #dc2626; }
        .map-pin-driver { background: #2563eb; }

    
        /* ==========================================================
           PROOF OF DELIVERY MODAL
        ========================================================== */
        .pod-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.55);
        }

        .pod-modal.is-open {
            display: flex;
        }

        .pod-modal-card {
            width: min(520px, 100%);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
        }

        .pod-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--color-border);
        }

        .pod-modal-header h2 {
            margin: 0 0 5px;
            font-size: 19px;
        }

        .pod-modal-header p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 12px;
            line-height: 1.5;
        }

        .pod-close {
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            background: var(--color-surface);
            color: var(--color-text-primary);
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .pod-modal-body {
            padding: 22px;
        }

        .pod-upload-box {
            padding: 18px;
            border: 1px dashed var(--color-border);
            border-radius: 10px;
            background: #f8fafc;
        }

        .pod-upload-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--color-text-primary);
        }

        .pod-file-input {
            display: block;
            width: 100%;
        }

        .pod-help {
            margin-top: 8px;
            font-size: 11px;
            color: var(--color-text-muted);
        }

        .pod-preview-wrap {
            display: none;
            margin-top: 16px;
        }

        .pod-preview-title {
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--color-text-secondary);
        }

        .pod-preview {
            display: block;
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border: 1px solid var(--color-border);
            border-radius: 10px;
            background: #fff;
        }

        .pod-error {
            display: none;
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 12px;
        }

        .pod-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 22px 20px;
            border-top: 1px solid var(--color-border);
        }

        .pod-submit:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        @media (max-width: 520px) {
            .pod-modal {
                padding: 12px;
            }

            .pod-modal-header,
            .pod-modal-body {
                padding: 16px;
            }

            .pod-modal-actions {
                padding: 14px 16px 16px;
                flex-direction: column-reverse;
            }

            .pod-modal-actions button {
                width: 100%;
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


        <div class="delivery-details-page">


            <!-- ==================================================
                 HEADER
            ================================================== -->

            <div class="delivery-header">

                <div>

                    <a
                        href="<?= BASE_URL ?>driver/deliveries.php"
                        style="
                            display:inline-block;
                            margin-bottom:10px;
                            text-decoration:none;
                        "
                    >

                        ← Back to My Deliveries

                    </a>


                    <h1>

                        <?= htmlspecialchars(
                            $order['order_number']
                        ) ?>

                    </h1>


                    <p>

                        Delivery details and
                        delivery controls.

                    </p>

                </div>


                <div class="action-panel">


                    <span
                        class="
                            status-pill
                            <?= $statusClass ?>
                        "
                    >

                        <?= htmlspecialchars(
                            $orderStatus
                        ) ?>

                    </span>


                    <?php if ($canAccept): ?>

                        <form
                            method="POST"
                            action="<?= BASE_URL ?>driver/accept_delivery.php"
                            onsubmit="
                                return confirm(
                                    'Accept this delivery?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= (int)$orderId ?>"
                            >

                            <button
                                type="submit"
                                class="btn-primary"
                            >
                                Accept Delivery
                            </button>

                        </form>


                    <?php elseif ($canStart): ?>

                        <form
                            method="POST"
                            action="<?= BASE_URL ?>driver/start_delivery.php"
                            onsubmit="
                                return confirm(
                                    'Start this delivery? Your GPS location will be used for tracking.'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= (int)$orderId ?>"
                            >

                            <button
                                type="submit"
                                class="btn-success"
                            >
                                Start Delivery
                            </button>

                        </form>


                    <?php elseif ($canComplete): ?>

    <!-- REPORT DELAY / INCIDENT -->
    <a
        href="<?= BASE_URL ?>driver/report_delay.php?id=<?= (int)$orderId ?>"
        class="btn-secondary"
        style="
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        "
    >
        Report Delay / Incident
    </a>


    <!-- COMPLETE DELIVERY / PROOF OF DELIVERY -->
    <button
        type="button"
        class="btn-success"
        id="open-pod-modal"
    >
        Complete Delivery
    </button>

<?php endif; ?>


                </div>

            </div>


            <!-- ==================================================
                 WORKFLOW
            ================================================== -->

            <div
                class="card panel"
                style="margin-bottom:20px;"
            >

                <h2>
                    Delivery Progress
                </h2>


                <?php

                $workflowStatuses = [
                    'Assigned',
                    'Accepted',
                    'In Transit',
                    'Completed'
                ];


                $statusIndex =
                    array_search(
                        $orderStatus,
                        $workflowStatuses,
                        true
                    );


                if ($statusIndex === false) {

                    $statusIndex = 0;

                }

                ?>


                <div class="workflow">


                    <?php foreach (
                        $workflowStatuses
                        as $index => $workflowStatus
                    ): ?>


                        <div
                            class="
                                workflow-step
                                <?= $index <= $statusIndex
                                    ? 'active'
                                    : ''
                                ?>
                            "
                        >


                            <div
                                class="workflow-number"
                            >

                                <?= $index + 1 ?>

                            </div>


                            <div
                                class="workflow-label"
                            >

                                <?= htmlspecialchars(
                                    $workflowStatus
                                ) ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>

            </div>


            <!-- ==================================================
                 MAIN GRID
            ================================================== -->

            <div class="details-grid">


                <!-- =================================================
                     LEFT COLUMN
                ================================================= -->

                <div class="details-left">


                    <!-- ORDER INFORMATION -->

                    <div class="card panel">

                        <h2>
                            Order Information
                        </h2>


                        <div
                            class="info-grid"
                            style="margin-top:20px;"
                        >


                            <div>

                                <span class="info-label">
                                    Order Number
                                </span>

                                <div class="info-value">

                                    <?= htmlspecialchars(
                                        $order['order_number']
                                    ) ?>

                                </div>

                            </div>


                            <div>

                                <span class="info-label">
                                    Order Date
                                </span>

                                <div class="info-value">

                                    <?= !empty(
                                        $order['order_date']
                                    )
                                        ? date(
                                            'M d, Y',
                                            strtotime(
                                                $order['order_date']
                                            )
                                        )
                                        : '—'
                                    ?>

                                </div>

                            </div>


                            <div>

                                <span class="info-label">
                                    Priority
                                </span>

                                <div class="info-value">

                                    <?php if (
                                        $order[
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

                                <span class="info-label">
                                    Total Weight
                                </span>

                                <div class="info-value">

                                    <?= number_format(
                                        (float)(
                                            $order[
                                                'total_weight_kg'
                                            ]
                                            ??
                                            $order[
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

                    </div>


                    <!-- CUSTOMER INFORMATION -->

                    <div
                        class="card panel"
                        style="margin-top:20px;"
                    >

                        <h2>
                            Customer Information
                        </h2>


                        <div
                            class="info-grid"
                            style="margin-top:20px;"
                        >


                            <div>

                                <span class="info-label">
                                    Customer Name
                                </span>

                                <div class="info-value">

                                    <?= htmlspecialchars(
                                        $order[
                                            'customer_name'
                                        ]
                                        ?? '-'
                                    ) ?>

                                </div>

                            </div>


                            <div>

                                <span class="info-label">
                                    Phone
                                </span>

                                <div class="info-value">

                                    <?= htmlspecialchars(
                                        $order[
                                            'customer_phone'
                                        ]
                                        ?? '-'
                                    ) ?>

                                </div>

                            </div>


                            <div>

                                <span class="info-label">
                                    Email
                                </span>

                                <div class="info-value">

                                    <?= htmlspecialchars(
                                        $order[
                                            'customer_email'
                                        ]
                                        ?? '-'
                                    ) ?>

                                </div>

                            </div>


                        </div>


                        <div class="address-box">


                            <span class="info-label">
                                Delivery Contact
                            </span>


                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $order[
                                        'contact_person'
                                    ]
                                    ??
                                    $order[
                                        'customer_name'
                                    ]
                                    ??
                                    '-'
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $order[
                                        'contact_number'
                                    ]
                                )
                            ): ?>

                                <div
                                    style="
                                        margin-top:5px;
                                    "
                                >

                                    📞

                                    <?= htmlspecialchars(
                                        $order[
                                            'contact_number'
                                        ]
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        </div>

                    </div>


                    <!-- DELIVERY ADDRESS -->

                    <div
                        class="card panel"
                        style="margin-top:20px;"
                    >

                        <h2>
                            Delivery Location
                        </h2>


                        <div
                            class="address-box"
                            style="
                                margin-top:20px;
                            "
                        >


                            <span class="info-label">
                                Address
                            </span>


                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $order[
                                        'delivery_address'
                                    ]
                                    ??
                                    'Address not available'
                                ) ?>

                            </div>


                            <div
                                style="
                                    margin-top:5px;
                                    color:
                                    var(--color-text-secondary);
                                "
                            >

                                <?= htmlspecialchars(
                                    $order[
                                        'delivery_city'
                                    ]
                                    ?? ''
                                ) ?>


                                <?php if (
                                    !empty(
                                        $order[
                                            'delivery_province'
                                        ]
                                    )
                                ): ?>

                                    ,

                                    <?= htmlspecialchars(
                                        $order[
                                            'delivery_province'
                                        ]
                                    ) ?>

                                <?php endif; ?>

                            </div>


                            <?php if ($hasDestination): ?>

                                <div
                                    style="
                                        margin-top:10px;
                                        font-size:11px;
                                        color:#15803d;
                                    "
                                >

                                    📍 GPS coordinates available

                                </div>

                            <?php else: ?>

                                <div
                                    style="
                                        margin-top:10px;
                                        font-size:11px;
                                        color:#b45309;
                                    "
                                >

                                    ⚠️ No pinned GPS coordinates

                                </div>

                            <?php endif; ?>


                        </div>

                    </div>


                </div>


                <!-- =================================================
                     RIGHT COLUMN
                ================================================= -->

                <div class="details-right">


                    <!-- MAP -->

                    <div class="card panel">

                        <h2>
                            Intelligent Route Suggestion
                        </h2>

                        <p id="route-summary" style="margin:6px 0 0;color:var(--color-text-secondary);font-size:13px;">
                            Calculating the recommended road route...
                        </p>


                        <div
                            style="
                                margin-top:15px;
                            "
                        >


                            <?php if ($hasDestination): ?>

                                <div
                                    id="delivery-map"
                                ></div>

                            <?php else: ?>

                                <div class="map-empty">

                                    <div
                                        style="
                                            font-size:40px;
                                            margin-bottom:10px;
                                        "
                                    >
                                        📍
                                    </div>


                                    <strong>
                                        No destination coordinates
                                    </strong>


                                    <p>
                                        This order does not have
                                        a pinned delivery location.
                                    </p>

                                </div>

                            <?php endif; ?>


                        </div>


                        <!-- GPS INFO -->

                        <div class="gps-box">


                            <span class="info-label">
                                Driver GPS
                            </span>


                            <div
                                id="driver-gps-status"
                                class="
                                    gps-status
                                    <?= $hasDriverGps
                                        ? 'gps-active'
                                        : 'gps-waiting'
                                    ?>
                                "
                            >

                                <?php if (
                                    $hasDriverGps
                                ): ?>

                                    ● Location available

                                <?php else: ?>

                                    ● Waiting for GPS location

                                <?php endif; ?>

                            </div>


                            <div
                                id="driver-gps-coordinates"
                                style="
                                    margin-top:6px;
                                    font-size:12px;
                                "
                            >

                                <?php if (
                                    $hasDriverGps
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

                                    No current coordinates.

                                <?php endif; ?>

                            </div>


                        </div>


                    </div>


                    <!-- TRUCK -->

                    <div
                        class="card panel"
                        style="margin-top:20px;"
                    >

                        <h2>
                            Assigned Truck
                        </h2>


                        <?php if ($truck): ?>


                            <div
                                class="info-grid"
                                style="
                                    margin-top:20px;
                                "
                            >


                                <div>

                                    <span class="info-label">
                                        Plate Number
                                    </span>

                                    <div class="info-value">

                                        <?= htmlspecialchars(
                                            $truck[
                                                'plate_number'
                                            ]
                                        ) ?>

                                    </div>

                                </div>


                                <div>

                                    <span class="info-label">
                                        Model
                                    </span>

                                    <div class="info-value">

                                        <?= htmlspecialchars(
                                            $truck[
                                                'model'
                                            ]
                                            ?? '-'
                                        ) ?>

                                    </div>

                                </div>


                                <div>

                                    <span class="info-label">
                                        Capacity
                                    </span>

                                    <div class="info-value">

                                        <?= number_format(
                                            (float)(
                                                $truck[
                                                    'capacity'
                                                ]
                                                ?? 0
                                            ),
                                            2
                                        ) ?>

                                        kg

                                    </div>

                                </div>


                                <div>

                                    <span class="info-label">
                                        Truck Status
                                    </span>

                                    <div class="info-value">

                                        <?= htmlspecialchars(
                                            $truck[
                                                'status'
                                            ]
                                            ?? '-'
                                        ) ?>

                                    </div>

                                </div>


                            </div>


                        <?php else: ?>


                            <div class="empty-state">

                                No truck currently assigned.

                            </div>


                        <?php endif; ?>


                    </div>


                    <!-- STATUS HISTORY -->

                    <div
                        class="card panel"
                        style="margin-top:20px;"
                    >

                        <h2>
                            Delivery History
                        </h2>


                        <div
                            class="timeline"
                            style="
                                margin-top:20px;
                            "
                        >


                            <?php if (
                                empty($statusHistory)
                            ): ?>

                                <div class="empty-state">

                                    No delivery status history yet.

                                </div>


                            <?php else: ?>


                                <?php foreach (
                                    array_reverse(
                                        $statusHistory
                                    )
                                    as $history
                                ): ?>


                                    <div
                                        class="timeline-item"
                                    >


                                        <span
                                            class="timeline-dot"
                                        ></span>


                                        <div
                                            class="timeline-status"
                                        >

                                            <?= htmlspecialchars(
                                                $history[
                                                    'status'
                                                ]
                                            ) ?>

                                        </div>


                                        <div
                                            class="timeline-time"
                                        >

                                            <?= !empty(
                                                $history[
                                                    'timestamp'
                                                ]
                                            )
                                                ? date(
                                                    'M d, Y h:i A',
                                                    strtotime(
                                                        $history[
                                                            'timestamp'
                                                        ]
                                                    )
                                                )
                                                : '—'
                                            ?>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $history[
                                                    'notes'
                                                ]
                                            )
                                        ): ?>

                                            <div
                                                class="timeline-notes"
                                            >

                                                <?= htmlspecialchars(
                                                    $history[
                                                        'notes'
                                                    ]
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                    </div>


                                <?php endforeach; ?>


                            <?php endif; ?>


                        </div>

                    </div>


                </div>


            </div>


        </div>

    </main>


</div>


<!-- ============================================================
     GPS CONFIG
============================================================ -->

<script>

window.DRIVER_GPS_UPDATE_URL =
    <?= json_encode(
        BASE_URL .
        'driver/update_location.php'
    ) ?>;

</script>


<script
    src="<?= BASE_URL ?>assets/js/driver-gps.js"
></script>


<!-- ============================================================
     LEAFLET
============================================================ -->

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>


<?php if ($hasDestination): ?>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const destinationLat =
            <?= json_encode(
                (float)
                $order[
                    'destination_latitude'
                ]
            ) ?>;


        const destinationLng =
            <?= json_encode(
                (float)
                $order[
                    'destination_longitude'
                ]
            ) ?>;


        let driverLat =
            <?= $hasDriverGps
                ? json_encode(
                    (float)
                    $driver[
                        'current_latitude'
                    ]
                )
                : 'null'
            ?>;


        let driverLng =
            <?= $hasDriverGps
                ? json_encode(
                    (float)
                    $driver[
                        'current_longitude'
                    ]
                )
                : 'null'
            ?>;


        const map =
            L.map(
                'delivery-map'
            );


        /*
        |--------------------------------------------------------------------------
        | TILE LAYER
        |--------------------------------------------------------------------------
        */

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {

                maxZoom: 19,

                attribution:
                    '&copy; OpenStreetMap contributors'

            }
        ).addTo(map);


        /* Customer-style custom map pins */
        const customerIcon = L.divIcon({
            className: 'custom-map-pin-wrap',
            html: '<div class="map-pin map-pin-customer" title="Delivery Destination">' +
                  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg>' +
                  '</div>',
            iconSize: [38, 48],
            iconAnchor: [19, 46],
            popupAnchor: [0, -42]
        });

        const driverIcon = L.divIcon({
            className: 'custom-map-pin-wrap',
            html: '<div class="map-pin map-pin-driver" title="Driver Location">' +
                  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h9A2.5 2.5 0 0 1 17 6.5V8h1.6a2 2 0 0 1 1.7.95l1.42 2.3c.18.3.28.65.28 1V17a2 2 0 0 1-2 2h-.55a2.75 2.75 0 0 1-5.4 0H9.95a2.75 2.75 0 0 1-5.4 0H4a2 2 0 0 1-2-2V8.5A2 2 0 0 1 3 6.77V6.5ZM5.5 6a.5.5 0 0 0-.5.5V12h10V6.5a.5.5 0 0 0-.5-.5h-9ZM17 10v2h2.73l-1.23-2H17ZM7.25 17.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Zm9.5 0a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Z"/></svg>' +
                  '</div>',
            iconSize: [42, 48],
            iconAnchor: [21, 44],
            popupAnchor: [0, -40]
        });

        /*
        |--------------------------------------------------------------------------
        | DESTINATION MARKER
        |--------------------------------------------------------------------------
        */

        const destinationMarker =
            L.marker(
                [
                    destinationLat,
                    destinationLng
                ],
                { icon: customerIcon }
            )
            .addTo(map);


        destinationMarker.bindPopup(
            '<strong>Delivery Destination</strong><br>' +
            <?= json_encode(
                $order[
                    'delivery_address'
                ]
                ??
                'Delivery destination'
            ) ?>
        );


        /*
        |--------------------------------------------------------------------------
        | DRIVER MARKER
        |--------------------------------------------------------------------------
        */

        let driverMarker = null;


        if (
            driverLat !== null &&
            driverLng !== null
        ) {

            driverMarker =
                L.marker(
                    [
                        driverLat,
                        driverLng
                    ],
                    { icon: driverIcon }
                )
                .addTo(map);


            driverMarker.bindPopup(
                '<strong>Driver Location</strong>'
            );


            const bounds =
                L.latLngBounds(
                    [
                        driverLat,
                        driverLng
                    ],
                    [
                        destinationLat,
                        destinationLng
                    ]
                );


            map.fitBounds(
                bounds,
                {
                    padding: [
                        40,
                        40
                    ]
                }
            );


        } else {

            map.setView(
                [
                    destinationLat,
                    destinationLng
                ],
                15
            );

        }


        /*
        |--------------------------------------------------------------------------
        | ROUTE LINE
        |--------------------------------------------------------------------------
        */

        let routeLine = null;
        let lastRouteOrigin = null;
        let routeRequestRunning = false;

        const routeSummary = document.getElementById('route-summary');

        function distanceMeters(lat1, lng1, lat2, lng2) {
            const R = 6371000;
            const toRad = value => value * Math.PI / 180;
            const dLat = toRad(lat2 - lat1);
            const dLng = toRad(lng2 - lng1);
            const a = Math.sin(dLat / 2) ** 2 +
                Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                Math.sin(dLng / 2) ** 2;
            return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        async function drawRoadRoute(latitude, longitude, force = false) {
            if (routeRequestRunning) return;

            if (!force && lastRouteOrigin) {
                const moved = distanceMeters(
                    lastRouteOrigin.lat,
                    lastRouteOrigin.lng,
                    latitude,
                    longitude
                );
                if (moved < 75) return;
            }

            routeRequestRunning = true;

            try {
                const url =
                    'https://router.project-osrm.org/route/v1/driving/' +
                    encodeURIComponent(longitude + ',' + latitude + ';' + destinationLng + ',' + destinationLat) +
                    '?overview=full&geometries=geojson&steps=false';

                const response = await fetch(url);
                if (!response.ok) throw new Error('Routing service unavailable');

                const data = await response.json();
                if (!data.routes || !data.routes.length) throw new Error('No road route found');

                const route = data.routes[0];
                const latLngs = route.geometry.coordinates.map(function (coord) {
                    return [coord[1], coord[0]];
                });

                if (routeLine) map.removeLayer(routeLine);
                routeLine = L.polyline(latLngs).addTo(map);

                const distanceKm = route.distance / 1000;
                const durationMin = Math.max(1, Math.round(route.duration / 60));

                if (routeSummary) {
                    routeSummary.textContent =
                        'Recommended road route: ' + distanceKm.toFixed(2) +
                        ' km · approximately ' + durationMin + ' min (base routing estimate).';
                }

                map.fitBounds(routeLine.getBounds(), {padding: [40, 40]});
                lastRouteOrigin = {lat: latitude, lng: longitude};
            } catch (error) {
                if (routeLine) map.removeLayer(routeLine);
                routeLine = L.polyline([
                    [latitude, longitude],
                    [destinationLat, destinationLng]
                ]).addTo(map);

                if (routeSummary) {
                    routeSummary.textContent =
                        'Road routing is temporarily unavailable. Showing the direct connection to the destination.';
                }
            } finally {
                routeRequestRunning = false;
            }
        }

        function updateDriverMarker(latitude, longitude) {
            driverLat = latitude;
            driverLng = longitude;

            if (!driverMarker) {
                driverMarker = L.marker([latitude, longitude], { icon: driverIcon }).addTo(map);
                driverMarker.bindPopup('<strong>Driver Location</strong>');
            } else {
                driverMarker.setLatLng([latitude, longitude]);
            }

            const gpsStatus = document.getElementById('driver-gps-status');
            const gpsCoordinates = document.getElementById('driver-gps-coordinates');

            if (gpsStatus) {
                gpsStatus.textContent = '● GPS Active';
                gpsStatus.className = 'gps-status gps-active';
            }

            if (gpsCoordinates) {
                gpsCoordinates.textContent =
                    latitude.toFixed(7) + ', ' + longitude.toFixed(7);
            }

            drawRoadRoute(latitude, longitude);
        }

        if (driverLat !== null && driverLng !== null) {
            drawRoadRoute(driverLat, driverLng, true);
        } else if (routeSummary) {
            routeSummary.textContent =
                'Waiting for the driver GPS location before calculating the road route.';
        }

        /*
        |--------------------------------------------------------------------------
        | BROWSER GPS
        |--------------------------------------------------------------------------
        */

        const deliveryStatus =
            <?= json_encode(
                $orderStatus
            ) ?>;

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    updateDriverMarker(
                        position.coords.latitude,
                        position.coords.longitude
                    );
                },
                function () {},
                {enableHighAccuracy: true, maximumAge: 10000, timeout: 15000}
            );
        }

        if (
            deliveryStatus === 'In Transit' &&
            navigator.geolocation
        ) {


            navigator.geolocation.watchPosition(

                function (position) {

                    const latitude =
                        position.coords.latitude;

                    const longitude =
                        position.coords.longitude;


                    updateDriverMarker(
                        latitude,
                        longitude
                    );


                    fetch(
                        window.DRIVER_GPS_UPDATE_URL,
                        {

                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded'
                            },

                            body:
                                new URLSearchParams({

                                    latitude:
                                        latitude,

                                    longitude:
                                        longitude

                                })

                        }
                    )
                    .catch(
                        function () {

                            // GPS update failure
                            // should not break the map.

                        }
                    );

                },


                function () {

                    const gpsStatus =
                        document.getElementById(
                            'driver-gps-status'
                        );


                    if (gpsStatus) {

                        gpsStatus.textContent =
                            '● GPS permission unavailable';

                        gpsStatus.className =
                            'gps-status gps-waiting';

                    }

                },


                {

                    enableHighAccuracy: true,

                    maximumAge: 10000,

                    timeout: 15000

                }

            );

        }

    }
);

</script>

<?php endif; ?>




<?php if ($canComplete): ?>
<div
    class="pod-modal"
    id="pod-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pod-modal-title"
>
    <div class="pod-modal-card">
        <form
            method="POST"
            action="<?= BASE_URL ?>driver/complete_delivery.php"
            enctype="multipart/form-data"
            id="complete-delivery-form"
        >
            <input
                type="hidden"
                name="order_id"
                value="<?= (int)$orderId ?>"
            >

            <div class="pod-modal-header">
                <div>
                    <h2 id="pod-modal-title">Proof of Delivery</h2>
                    <p>
                        Upload a clear photo confirming that the customer's
                        delivery has been completed.
                    </p>
                </div>

                <button
                    type="button"
                    class="pod-close"
                    id="close-pod-modal"
                    aria-label="Close"
                >
                    ×
                </button>
            </div>

            <div class="pod-modal-body">
                <div class="pod-upload-box">
                    <label
                        for="proof-photo"
                        class="pod-upload-label"
                    >
                        Delivery Photo
                        <span style="color:#dc2626;">*</span>
                    </label>

                    <input
                        type="file"
                        id="proof-photo"
                        name="proof_photo"
                        class="pod-file-input"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                        required
                    >

                    <div class="pod-help">
                        JPG, PNG or WebP · Maximum 8 MB
                    </div>
                </div>

                <div
                    class="pod-preview-wrap"
                    id="proof-preview-container"
                >
                    <div class="pod-preview-title">
                        Photo Preview
                    </div>

                    <img
                        id="proof-preview"
                        class="pod-preview"
                        src=""
                        alt="Proof of Delivery Preview"
                    >
                </div>

                <div
                    class="pod-error"
                    id="proof-file-error"
                ></div>
            </div>

            <div class="pod-modal-actions">
                <button
                    type="button"
                    class="btn-secondary"
                    id="cancel-pod-modal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-success pod-submit"
                    id="complete-delivery-button"
                    disabled
                >
                    Confirm Complete Delivery
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<?php if ($canComplete): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('pod-modal');
    const openButton = document.getElementById('open-pod-modal');
    const closeButton = document.getElementById('close-pod-modal');
    const cancelButton = document.getElementById('cancel-pod-modal');
    const form = document.getElementById('complete-delivery-form');
    const proofInput = document.getElementById('proof-photo');
    const previewContainer = document.getElementById('proof-preview-container');
    const preview = document.getElementById('proof-preview');
    const errorBox = document.getElementById('proof-file-error');
    const submitButton = document.getElementById('complete-delivery-button');

    if (!modal || !openButton || !form || !proofInput || !submitButton) {
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxSize = 8 * 1024 * 1024;

    function openModal() {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function resetPreview() {
        previewContainer.style.display = 'none';
        preview.removeAttribute('src');
        errorBox.style.display = 'none';
        errorBox.textContent = '';
        submitButton.disabled = true;
    }

    openButton.addEventListener('click', openModal);

    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    proofInput.addEventListener('change', function () {
        resetPreview();

        const file =
            this.files && this.files.length
                ? this.files[0]
                : null;

        if (!file) {
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            errorBox.textContent =
                'Please select a JPG, PNG, or WebP image.';
            errorBox.style.display = 'block';
            this.value = '';
            return;
        }

        if (file.size > maxSize) {
            errorBox.textContent =
                'The proof photo must not exceed 8 MB.';
            errorBox.style.display = 'block';
            this.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            preview.src = event.target.result;
            previewContainer.style.display = 'block';
            submitButton.disabled = false;
        };

        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', function (event) {
        if (!proofInput.files || !proofInput.files.length) {
            event.preventDefault();
            errorBox.textContent =
                'Please take or select a proof of delivery photo.';
            errorBox.style.display = 'block';
            return;
        }

        if (!confirm('Complete this delivery using the selected proof photo?')) {
            event.preventDefault();
        }
    });
});
</script>
<?php endif; ?>

</body>

</html>