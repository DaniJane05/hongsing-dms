<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/constants.php';

require_once __DIR__ . '/../includes/auth.php';



requireRole(ROLE_ADMIN);



$db = Database::getConnection();





/*

\|--------------------------------------------------------------------------

\| GET ORDER ID

\|--------------------------------------------------------------------------

*/



$orderId = isset($_GET['id'])

    ? (int) $_GET['id']

    : 0;



if ($orderId <= 0) {



    header(

        'Location: ' .

        BASE_URL .

        'admin/orders.php?error=invalid_order'

    );



    exit;

}





/*

\|--------------------------------------------------------------------------

\| GET DELIVERY

\|--------------------------------------------------------------------------

*/



$stmt = $db->prepare("

    SELECT



        o.order_id,

        o.order_number,



        o.customer_id,

        o.driver_id,

        o.truck_id,

        o.route_id,



        o.total_weight,

        o.total_weight_kg,



        o.order_date,

        o.expected_delivery_date,



        o.priority_level,

        o.completion_status,



        o.assigned_at,

        o.accepted_at,

        o.started_at,

        o.completed_at,



        c.full_name AS customer_name,

        c.phone_number AS customer_phone,



        d.full_name AS driver_name,

        d.phone_number AS driver_phone,

        d.status AS driver_status,



        d.current_latitude AS driver_latitude,

        d.current_longitude AS driver_longitude,

        d.location_updated_at,



        t.plate_number,

        t.model AS truck_model,

        t.capacity AS truck_capacity,

        t.current_load AS truck_current_load,

        t.status AS truck_status,



        r.route_code,

        r.estimated_distance,

        r.estimated_time,



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



    LEFT JOIN driver d

        ON d.driver_id = o.driver_id



    LEFT JOIN truck t

        ON t.truck_id = o.truck_id



    LEFT JOIN route r

        ON r.route_id = o.route_id



    LEFT JOIN delivery_location dl

        ON dl.location_id = (

            SELECT MAX(dl2.location_id)

            FROM delivery_location dl2

            WHERE dl2.order_id = o.order_id

        )



    WHERE o.order_id = ?



    LIMIT 1

");



$stmt->execute([

    $orderId

]);



$order = $stmt->fetch(PDO::FETCH_ASSOC);





/*

\|--------------------------------------------------------------------------

\| ORDER NOT FOUND

\|--------------------------------------------------------------------------

*/



if (!$order) {



    header(

        'Location: ' .

        BASE_URL .

        'admin/orders.php?error=order_not_found'

    );



    exit;

}





/*

\|--------------------------------------------------------------------------

\| TRACKABLE STATUS

\|--------------------------------------------------------------------------

*/



$trackableStatuses = [

    'Assigned',

    'Accepted',

    'In Transit'

];



if (

    !in_array(

        $order['completion_status'],

        $trackableStatuses,

        true

    )

) {



    /*

     * Completed deliveries go to the

     * permanent delivery record.

     */



    if (

        $order['completion_status']

        === 'Completed'

    ) {



        header(

            'Location: ' .

            BASE_URL .

            'admin/order_details.php?id=' .

            $orderId

        );



        exit;

    }





    /*

     * Approved orders still need assignment.

     */



    if (

        $order['completion_status']

        === 'Approved'

    ) {



        header(

            'Location: ' .

            BASE_URL .

            'admin/assign_delivery.php?order_id=' .

            $orderId

        );



        exit;

    }





    header(

        'Location: ' .

        BASE_URL .

        'admin/orders.php?error=not_trackable'

    );



    exit;

}





/*

\|--------------------------------------------------------------------------

\| DELIVERY HISTORY

\|--------------------------------------------------------------------------

*/



$historyStmt = $db->prepare("

    SELECT

        status_id,

        status,

        latitude,

        longitude,

        timestamp,

        notes

    FROM delivery_status

    WHERE order_id = ?

    ORDER BY timestamp ASC, status_id ASC

");



$historyStmt->execute([

    $orderId

]);



$statusHistory =

    $historyStmt->fetchAll(

        PDO::FETCH_ASSOC

    );





/*

\|--------------------------------------------------------------------------

\| WEIGHT

\|--------------------------------------------------------------------------

*/



$orderWeight =

    (float) (

        $order['total_weight_kg']

        ??

        $order['total_weight']

        ??

        0

    );





/*

\|--------------------------------------------------------------------------

\| TRUCK CAPACITY

\|--------------------------------------------------------------------------

*/



$truckCapacity =

    (float) (

        $order['truck_capacity']

        ?? 0

    );



$truckCurrentLoad =

    (float) (

        $order['truck_current_load']

        ?? 0

    );



$truckRemaining =

    max(

        0,

        $truckCapacity - $truckCurrentLoad

    );





/*

\|--------------------------------------------------------------------------

\| GPS

\|--------------------------------------------------------------------------

*/



$hasDriverGps =

    $order['driver_latitude'] !== null

    &&

    $order['driver_longitude'] !== null;



$hasDestination =

    $order['destination_latitude'] !== null

    &&

    $order['destination_longitude'] !== null;





/*

\|--------------------------------------------------------------------------

\| DELIVERY ADDRESS

\|--------------------------------------------------------------------------

*/



$addressParts = array_filter([

    trim(

        (string) (

            $order['delivery_address']

            ?? ''

        )

    ),



    trim(

        (string) (

            $order['delivery_city']

            ?? ''

        )

    ),



    trim(

        (string) (

            $order['delivery_province']

            ?? ''

        )

    )

]);



$deliveryAddress =

    implode(

        ', ',

        array_unique(

            $addressParts

        )

    );





/*

\|--------------------------------------------------------------------------

\| WORKFLOW

\|--------------------------------------------------------------------------

*/



$workflowStatuses = [

    'Assigned',

    'Accepted',

    'In Transit',

    'Completed'

];



$statusIndex =

    array_search(

        $order['completion_status'],

        $workflowStatuses,

        true

    );



if ($statusIndex === false) {

    $statusIndex = 0;

}





/*

\|--------------------------------------------------------------------------

\| STATUS CLASS

\|--------------------------------------------------------------------------

*/



$statusClass = match (

    $order['completion_status']

) {



    'Assigned'

        => 'status-pending',



    'Accepted'

        => 'status-delivered',



    'In Transit'

        => 'status-transit',



    'Completed'

        => 'status-delivered',



    default

        => 'status-pending'

};





/*

\|--------------------------------------------------------------------------

\| NAVIGATION

\|--------------------------------------------------------------------------

*/



$navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL .
            'admin/dashboard.php'
    ],

    [
        'label' => 'Orders',
        'url' =>
            BASE_URL .
            'admin/orders.php'
    ],

    [
        'label' => 'Route Optimization',
        'url' =>
            BASE_URL .
            'admin/route_optimization.php'
    ],

    [
        'label' => 'Delivery Incidents',
        'url' =>
            BASE_URL .
            'admin/delivery_incidents.php'
    ],

    [
        'label' => 'Delay Analytics',
        'url' =>
            BASE_URL .
            'admin/delay_analytics.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL .
            'admin/notifications.php'
    ],

    [
        'label' => 'Products',
        'url' =>
            BASE_URL .
            'admin/products.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'admin/profile.php'
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

    Track Delivery —

    <?= htmlspecialchars(

        $order['order_number']

    ) ?>

</title>



<?php

if (

    file_exists(

        __DIR__ .

        '/../includes/theme_init.php'

    )

) {

    require __DIR__ .

        '/../includes/theme_init.php';

}

?>



<link

    rel="stylesheet"

    href="<?= BASE_URL ?>assets/css/theme.css"

>



<link

    rel="stylesheet"

    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"

>



<style>



.track-page {

    width: 100%;

    max-width: 1400px;

    margin: 0 auto;

}





/* ==========================================================

   HEADER

\========================================================== */



.track-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

    margin-bottom: 24px;

}



.track-header h1 {

    margin: 7px 0 5px;

    color: var(--color-text-primary);

    font-size: 28px;

}



.track-header p {

    margin: 0;

    color: var(--color-text-secondary);

    font-size: 13px;

}



.back-link {

    color: var(--color-text-secondary);

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

}



.back-link:hover {

    color: var(--color-accent);

}





/* ==========================================================

   GRID

\========================================================== */



.track-layout {

    display: grid;

    grid-template-columns:

        minmax(0, .85fr)

        minmax(420px, 1.15fr);

    gap: 20px;

    align-items: start;

}



.track-column {

    display: grid;

    gap: 20px;

}





/* ==========================================================

   CARD

\========================================================== */



.track-card {

    border:

        1px solid

        var(--color-border);



    border-radius:

        var(--radius-lg);



    background:

        var(--color-surface);



    box-shadow:

        var(--shadow-card);



    overflow: hidden;

}



.track-card-head {

    padding: 18px 20px;

    border-bottom:

        1px solid

        var(--color-border);

}



.track-card-head h2 {

    margin: 0 0 4px;

    color:

        var(--color-text-primary);

    font-size: 16px;

}



.track-card-head p {

    margin: 0;

    color:

        var(--color-text-muted);

    font-size: 12px;

}



.track-card-body {

    padding: 20px;

}





/* ==========================================================

   INFO

\========================================================== */



.info-grid {

    display: grid;

    grid-template-columns:

        repeat(

            2,

            minmax(0, 1fr)

        );

    gap: 20px;

}



.info-full {

    grid-column: 1 / -1;

}



.info-label {

    display: block;

    margin-bottom: 5px;



    color:

        var(--color-text-muted);



    font-size: 10px;

    font-weight: 800;



    text-transform: uppercase;

    letter-spacing: .05em;

}



.info-value {

    display: block;



    color:

        var(--color-text-primary);



    font-size: 13px;

    font-weight: 600;



    line-height: 1.5;

}





/* ==========================================================

   WORKFLOW

\========================================================== */



.workflow {

    display: grid;



    grid-template-columns:

        repeat(4, 1fr);



    gap: 10px;

}



.workflow-step {

    padding: 15px 10px;



    border:

        1px solid

        var(--color-border);



    border-radius:

        var(--radius-md);



    text-align: center;

}



.workflow-step.active {

    border-color:

        var(--color-accent);



    background:

        var(--color-accent-soft);

}



.workflow-number {

    width: 30px;

    height: 30px;



    display: flex;

    align-items: center;

    justify-content: center;



    margin: 0 auto 8px;



    border-radius: 50%;



    /*

     * Explicit colors are used so the

     * number remains visible in both

     * light and dark modes.

     */

    background: #e5e7eb;

    color: #111827;



    font-size: 12px;

    font-weight: 800;

}



.workflow-step.active

.workflow-number {

    background:

        var(--color-accent);



    color: #ffffff;

}



.workflow-label {

    color:

        var(--color-text-primary);



    font-size: 11px;

    font-weight: 700;

}





/* ==========================================================

   CAPACITY

\========================================================== */



.capacity-summary {

    margin-top: 18px;

}



.capacity-bar {

    width: 100%;

    height: 10px;



    border-radius: 999px;



    background:

        var(--color-border);



    overflow: hidden;

}



.capacity-used {

    height: 100%;



    border-radius: inherit;



    background:

        var(--color-accent);

}



.capacity-text {

    display: flex;

    justify-content: space-between;

    gap: 15px;



    margin-top: 8px;



    color:

        var(--color-text-secondary);



    font-size: 11px;

}





/* ==========================================================

   MAP

\========================================================== */



#delivery-map {

    width: 100%;

    height: 520px;



    border-radius:

        var(--radius-md);



    border:

        1px solid

        var(--color-border);



    overflow: hidden;

}



.map-empty {

    min-height: 420px;



    display: flex;

    flex-direction: column;



    justify-content: center;

    align-items: center;



    padding: 30px;



    border:

        1px dashed

        var(--color-border);



    border-radius:

        var(--radius-md);



    color:

        var(--color-text-secondary);



    text-align: center;

}





/* ==========================================================

   GPS

\========================================================== */



.gps-panel {

    display: flex;

    justify-content: space-between;

    align-items: center;



    gap: 15px;



    margin-top: 15px;



    padding: 14px;



    border:

        1px solid

        var(--color-border);



    border-radius:

        var(--radius-md);



    background:

        var(--color-bg);

}



.gps-status {

    font-size: 12px;

    font-weight: 700;

}



.gps-active {

    color: #15803d;

}



.gps-waiting {

    color: #b45309;

}



.gps-time {

    color:

        var(--color-text-muted);



    font-size: 11px;



    text-align: right;

}





/* ==========================================================

   TIMELINE

\========================================================== */



.timeline {

    display: grid;

}



.timeline-row {

    display: grid;



    grid-template-columns:

        16px 1fr;



    gap: 12px;



    padding-bottom: 20px;

}



.timeline-row:last-child {

    padding-bottom: 0;

}



.timeline-dot {

    position: relative;



    width: 11px;

    height: 11px;



    margin-top: 3px;



    border-radius: 50%;



    background:

        var(--color-accent);

}



.timeline-row:not(:last-child)

.timeline-dot::after {

    content: "";



    position: absolute;



    left: 5px;

    top: 13px;



    width: 1px;

    height:

        calc(100% + 20px);



    background:

        var(--color-border);

}



.timeline-status {

    color:

        var(--color-text-primary);



    font-size: 13px;

    font-weight: 700;

}



.timeline-time {

    display: block;



    margin-top: 3px;



    color:

        var(--color-text-muted);



    font-size: 11px;

}



.timeline-note {

    margin-top: 5px;



    color:

        var(--color-text-secondary);



    font-size: 12px;

}





/* ==========================================================

   EMPTY

\========================================================== */



.empty-state-box {

    padding: 25px;



    border:

        1px dashed

        var(--color-border);



    border-radius:

        var(--radius-md);



    color:

        var(--color-text-muted);



    text-align: center;



    font-size: 12px;

}





/* ==========================================================

   RESPONSIVE

\========================================================== */



@media (

    max-width: 1050px

) {



    .track-layout {

        grid-template-columns: 1fr;

    }



}



@media (

    max-width: 650px

) {



    .track-header {

        flex-direction: column;

    }



    .info-grid {

        grid-template-columns: 1fr;

    }



    .info-full {

        grid-column: auto;

    }



    .workflow {

        grid-template-columns:

            repeat(2, 1fr);

    }



    #delivery-map {

        height: 380px;

    }



}




/* Custom map pins */
.map-pin-wrapper { background: transparent !important; border: 0 !important; }
.map-pin {
    width: 42px; height: 42px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    border: 3px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,.28);
    display: flex; align-items: center; justify-content: center;
}
.map-pin > span { transform: rotate(45deg); font-size: 20px; line-height: 1; }
.map-pin-driver { background: #2563eb; }
.map-pin-destination { background: #dc2626; }

</style>



</head>



<body>



<div class="app-layout">



<?php

require __DIR__ .

    '/../includes/header.php';

?>



<main class="app-main">



<div class="track-page">





<!-- ==========================================================

     HEADER

\========================================================== -->



<header class="track-header">



    <div>



        <a

            class="back-link"

            href="<?= BASE_URL ?>admin/orders.php?category=assigned"

        >

            ← Back to Orders

        </a>



        <h1>

            <?= htmlspecialchars(

                $order['order_number']

            ) ?>

        </h1>



        <p>

            Monitor the assigned driver,

            truck and delivery progress.

        </p>



    </div>



    <span

        class="

            status-pill

            <?= $statusClass ?>

        "

    >

        <?= htmlspecialchars(

            $order['completion_status']

        ) ?>

    </span>



</header>





<!-- ==========================================================

     PROGRESS

\========================================================== -->



<section

    class="track-card"

    style="margin-bottom:20px;"

>



<div class="track-card-head">



    <h2>Delivery Progress</h2>



    <p>

        Current stage of this delivery.

    </p>



</div>



<div class="track-card-body">



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



        <div class="workflow-number">

            <?= $index + 1 ?>

        </div>



        <div class="workflow-label">



            <?= htmlspecialchars(

                $workflowStatus

            ) ?>



        </div>



    </div>



<?php endforeach; ?>



</div>



</div>



</section>





<!-- ==========================================================

     MAIN GRID

\========================================================== -->



<div class="track-layout">





<!-- ==========================================================

     LEFT

\========================================================== -->



<div class="track-column">





<!-- ASSIGNMENT -->



<section class="track-card">



<div class="track-card-head">



    <h2>Delivery Assignment</h2>



    <p>

        Driver and truck assigned to

        this order.

    </p>



</div>



<div class="track-card-body">



<div class="info-grid">



    <div>



        <span class="info-label">

            Driver

        </span>



        <span class="info-value">

            <?= htmlspecialchars(

                $order['driver_name']

                ?? '—'

            ) ?>

        </span>



    </div>



    <div>



        <span class="info-label">

            Driver Status

        </span>



        <span class="info-value">

            <?= htmlspecialchars(

                $order['driver_status']

                ?? '—'

            ) ?>

        </span>



    </div>



    <div>



        <span class="info-label">

            Driver Phone

        </span>



        <span class="info-value">

            <?= htmlspecialchars(

                $order['driver_phone']

                ?? '—'

            ) ?>

        </span>



    </div>



    <div>



        <span class="info-label">

            Truck

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['plate_number']

                ?? '—'

            ) ?>



            <?php if (

                !empty(

                    $order['truck_model']

                )

            ): ?>



                —

                <?= htmlspecialchars(

                    $order['truck_model']

                ) ?>



            <?php endif; ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Order Weight

        </span>



        <span class="info-value">



            <?= number_format(

                $orderWeight,

                2

            ) ?>



            kg



        </span>



    </div>



    <div>



        <span class="info-label">

            Truck Status

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['truck_status']

                ?? '—'

            ) ?>



        </span>



    </div>



</div>





<?php if (

    $truckCapacity > 0

): ?>



<?php



$capacityPercentage =

    min(

        100,

        max(

            0,

            (

                $truckCurrentLoad /

                $truckCapacity

            ) * 100

        )

    );



?>



<div class="capacity-summary">



    <div class="capacity-bar">



        <div

            class="capacity-used"

            style="

                width:

                <?= number_format(

                    $capacityPercentage,

                    2,

                    '.',

                    ''

                ) ?>%;

            "

        ></div>



    </div>



    <div class="capacity-text">



        <span>



            Current load:



            <strong>



                <?= number_format(

                    $truckCurrentLoad,

                    2

                ) ?>



                kg



            </strong>



        </span>



        <span>



            Remaining:



            <strong>



                <?= number_format(

                    $truckRemaining,

                    2

                ) ?>



                kg



            </strong>



        </span>



    </div>



    <div

        class="capacity-text"

        style="margin-top:4px;"

    >



        <span>



            Capacity:



            <?= number_format(

                $truckCapacity,

                2

            ) ?>



            kg



        </span>



        <span>



            <?= number_format(

                $capacityPercentage,

                1

            ) ?>%



            used



        </span>



    </div>



</div>



<?php endif; ?>



</div>



</section>





<!-- CUSTOMER -->



<section class="track-card">



<div class="track-card-head">



    <h2>Customer & Destination</h2>



    <p>

        Delivery recipient information.

    </p>



</div>



<div class="track-card-body">



<div class="info-grid">



    <div>



        <span class="info-label">

            Customer

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['customer_name']

                ?? '—'

            ) ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Customer Phone

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['customer_phone']

                ?? '—'

            ) ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Delivery Contact

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['contact_person']

                ??

                $order['customer_name']

                ??

                '—'

            ) ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Contact Number

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['contact_number']

                ??

                $order['customer_phone']

                ??

                '—'

            ) ?>



        </span>



    </div>



    <div class="info-full">



        <span class="info-label">

            Delivery Address

        </span>



        <span class="info-value">



            <?= $deliveryAddress !== ''

                ? htmlspecialchars(

                    $deliveryAddress

                )

                : '—'

            ?>



        </span>



    </div>



</div>



</div>



</section>





<!-- ROUTE -->



<section class="track-card">



<div class="track-card-head">



    <h2>Route Information</h2>



    <p>

        Route assigned to this order.

    </p>



</div>



<div class="track-card-body">



<div class="info-grid">



    <div>



        <span class="info-label">

            Route

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['route_code']

                ?? '—'

            ) ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Distance

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['estimated_distance']

                ?? '—'

            ) ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Estimated Time

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['estimated_time']

                ?? '—'

            ) ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Priority

        </span>



        <span class="info-value">



            <?= htmlspecialchars(

                $order['priority_level']

                ?? 'Normal'

            ) ?>



        </span>



    </div>



</div>



</div>



</section>





<!-- HISTORY -->



<section class="track-card">



<div class="track-card-head">



    <h2>Delivery History</h2>



    <p>

        Recorded status updates for

        this delivery.

    </p>



</div>



<div class="track-card-body">



<?php if (

    empty($statusHistory)

): ?>



    <div class="empty-state-box">



        No delivery status history yet.



    </div>



<?php else: ?>



<div class="timeline">



<?php foreach (

    array_reverse(

        $statusHistory

    )

    as $history

): ?>



<div class="timeline-row">



    <div class="timeline-dot"></div>



    <div>



        <div class="timeline-status">



            <?= htmlspecialchars(

                $history['status']

            ) ?>



        </div>



        <span class="timeline-time">



            <?= !empty(

                $history['timestamp']

            )

                ? date(

                    'M d, Y · h:i A',

                    strtotime(

                        $history[

                            'timestamp'

                        ]

                    )

                )

                : '—'

            ?>



        </span>



        <?php if (

            !empty(

                $history['notes']

            )

        ): ?>



            <div class="timeline-note">



                <?= htmlspecialchars(

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





</div>





<!-- ==========================================================

     RIGHT

\========================================================== -->



<div class="track-column">





<!-- MAP -->



<section class="track-card">



<div class="track-card-head">



    <h2>Delivery Tracking Map</h2>



    <p>

        Driver and delivery destination

        locations.

    </p>



</div>



<div class="track-card-body">



<?php if (

    $hasDestination

): ?>



    <div id="delivery-map"></div>



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

            Destination coordinates unavailable

        </strong>



        <p>

            This order does not have

            saved GPS coordinates for

            the delivery destination.

        </p>



    </div>



<?php endif; ?>





<div class="gps-panel">



    <div>



        <span class="info-label">

            Driver GPS

        </span>



        <div

            id="gps-status"

            class="

                gps-status

                <?= $hasDriverGps

                    ? 'gps-active'

                    : 'gps-waiting'

                ?>

            "

        >



            <?= $hasDriverGps

                ? '● Location available'

                : '● Waiting for driver GPS'

            ?>



        </div>



    </div>



    <div

        id="gps-time"

        class="gps-time"

    >



        <?php if (

            !empty(

                $order[

                    'location_updated_at'

                ]

            )

        ): ?>



            Last updated<br>



            <?= date(

                'M d, Y h:i A',

                strtotime(

                    $order[

                        'location_updated_at'

                    ]

                )

            ) ?>



        <?php else: ?>



            No GPS update yet



        <?php endif; ?>



    </div>



</div>



</div>



</section>





<!-- DELIVERY TIMESTAMPS -->



<section class="track-card">



<div class="track-card-head">



    <h2>Delivery Activity</h2>



    <p>

        Important delivery timestamps.

    </p>



</div>



<div class="track-card-body">



<div class="info-grid">



    <div>



        <span class="info-label">

            Assigned

        </span>



        <span class="info-value">



            <?= !empty(

                $order['assigned_at']

            )

                ? date(

                    'M d, Y h:i A',

                    strtotime(

                        $order[

                            'assigned_at'

                        ]

                    )

                )

                : '—'

            ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Accepted

        </span>



        <span class="info-value">



            <?= !empty(

                $order['accepted_at']

            )

                ? date(

                    'M d, Y h:i A',

                    strtotime(

                        $order[

                            'accepted_at'

                        ]

                    )

                )

                : '—'

            ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Started

        </span>



        <span class="info-value">



            <?= !empty(

                $order['started_at']

            )

                ? date(

                    'M d, Y h:i A',

                    strtotime(

                        $order[

                            'started_at'

                        ]

                    )

                )

                : '—'

            ?>



        </span>



    </div>



    <div>



        <span class="info-label">

            Expected Delivery

        </span>



        <span class="info-value">



            <?= !empty(

                $order[

                    'expected_delivery_date'

                ]

            )

                ? date(

                    'M d, Y',

                    strtotime(

                        $order[

                            'expected_delivery_date'

                        ]

                    )

                )

                : '—'

            ?>



        </span>



    </div>



</div>



</div>



</section>





</div>



</div>



</div>



</main>



</div>





<!-- ==========================================================

     LEAFLET

\========================================================== -->



<script

    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"

></script>





<?php if ($hasDestination): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const destinationLat = <?= json_encode((float)$order['destination_latitude']) ?>;
    const destinationLng = <?= json_encode((float)$order['destination_longitude']) ?>;
    let driverLat = <?= $hasDriverGps ? json_encode((float)$order['driver_latitude']) : 'null' ?>;
    let driverLng = <?= $hasDriverGps ? json_encode((float)$order['driver_longitude']) : 'null' ?>;

    const map = L.map('delivery-map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const destinationIcon = L.divIcon({
        className: 'map-pin-wrapper',
        html: '<div class="map-pin map-pin-destination"><span>📍</span></div>',
        iconSize: [42,42], iconAnchor: [21,42], popupAnchor: [0,-42]
    });

    const driverIcon = L.divIcon({
        className: 'map-pin-wrapper',
        html: '<div class="map-pin map-pin-driver"><span>🚚</span></div>',
        iconSize: [42,42], iconAnchor: [21,42], popupAnchor: [0,-42]
    });

    L.marker([destinationLat, destinationLng], {
        icon: destinationIcon, zIndexOffset: 500
    }).addTo(map).bindPopup(
        '<strong>Delivery Destination</strong><br>' +
        <?= json_encode($deliveryAddress !== '' ? $deliveryAddress : 'Delivery destination') ?>
    );

    let driverMarker = null;
    let routeLayer = null;
    let fallbackLine = null;
    let lastRouteLat = null;
    let lastRouteLng = null;

    function fitMap() {
        if (driverLat !== null && driverLng !== null) {
            map.fitBounds(
                L.latLngBounds([driverLat, driverLng], [destinationLat, destinationLng]),
                {padding:[60,60], maxZoom:16}
            );
        } else {
            map.setView([destinationLat, destinationLng], 15);
        }
    }

    function fallbackRoute(lat, lng) {
        if (routeLayer) { map.removeLayer(routeLayer); routeLayer = null; }
        if (fallbackLine) map.removeLayer(fallbackLine);

        fallbackLine = L.polyline(
            [[lat,lng],[destinationLat,destinationLng]],
            {weight:4, opacity:.65, dashArray:'8,10'}
        ).addTo(map);
    }

    async function updateRoadRoute(lat, lng) {
        if (lastRouteLat !== null) {
            const moved = Math.abs(lat-lastRouteLat) + Math.abs(lng-lastRouteLng);
            if (moved < 0.00045) return;
        }

        lastRouteLat = lat;
        lastRouteLng = lng;

        const url =
            'https://router.project-osrm.org/route/v1/driving/' +
            lng + ',' + lat + ';' +
            destinationLng + ',' + destinationLat +
            '?overview=full&geometries=geojson';

        try {
            const response = await fetch(url);
            const data = await response.json();

            if (!response.ok || !data.routes || !data.routes[0]) {
                throw new Error('No road route');
            }

            if (fallbackLine) {
                map.removeLayer(fallbackLine);
                fallbackLine = null;
            }

            if (routeLayer) map.removeLayer(routeLayer);

            routeLayer = L.geoJSON(data.routes[0].geometry, {
                style: {weight:5, opacity:.8}
            }).addTo(map);

        } catch (error) {
            fallbackRoute(lat, lng);
        }
    }

    function updateGpsText(data) {
        const status = document.getElementById('gps-status');
        const time = document.getElementById('gps-time');

        if (status) {
            status.textContent = data.has_location
                ? '● Live location available'
                : '● Waiting for driver GPS';

            status.className = data.has_location
                ? 'gps-status gps-active'
                : 'gps-status gps-waiting';
        }

        if (time) {
            time.innerHTML = data.updated_at
                ? 'Last updated<br>' + data.updated_at
                : 'No GPS update yet';
        }
    }

    function setDriver(lat, lng, updatedAt) {
        driverLat = Number(lat);
        driverLng = Number(lng);

        if (!Number.isFinite(driverLat) || !Number.isFinite(driverLng)) return;

        if (!driverMarker) {
            driverMarker = L.marker([driverLat,driverLng], {
                icon: driverIcon,
                zIndexOffset:1000
            }).addTo(map).bindPopup(
                '<strong>Driver Location</strong><br>' +
                <?= json_encode($order['driver_name'] ?? 'Assigned Driver') ?>
            );

            fitMap();
        } else {
            driverMarker.setLatLng([driverLat,driverLng]);
        }

        updateRoadRoute(driverLat, driverLng);
        updateGpsText({has_location:true, updated_at:updatedAt});
    }

    async function pollLocation() {
        try {
            const response = await fetch(
                <?= json_encode(BASE_URL . 'admin/get_delivery_location.php') ?> +
                '?order_id=<?= (int)$orderId ?>&t=' + Date.now(),
                {credentials:'same-origin', cache:'no-store'}
            );

            const data = await response.json();
            if (!response.ok || !data.success) return;

            updateGpsText(data);

            if (data.has_location) {
                setDriver(data.latitude, data.longitude, data.updated_at);
            }

            if (data.order_status === 'Completed') {
                window.location.href =
                    <?= json_encode(BASE_URL . 'admin/order_details.php?id=') ?> +
                    <?= (int)$orderId ?>;
            }

        } catch (error) {
            console.error('Tracking update failed:', error);
        }
    }

    if (driverLat !== null && driverLng !== null) {
        setDriver(
            driverLat,
            driverLng,
            <?= json_encode($order['location_updated_at'] ?? null) ?>
        );
    } else {
        fitMap();
    }

    setTimeout(function () {
        map.invalidateSize();
        fitMap();
    }, 200);

    pollLocation();
    setInterval(pollLocation, 5000);
});
</script>
<?php endif; ?>






</body>
</html>
