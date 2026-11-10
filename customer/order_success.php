<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

$customerId = currentUserId();

/*
|--------------------------------------------------------------------------
| CHECK ORDER SUCCESS SESSION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['order_success']) ||
    !is_array($_SESSION['order_success'])
) {
    header(
        'Location: ' .
        BASE_URL .
        'customer/dashboard.php'
    );

    exit;
}

$successData = $_SESSION['order_success'];

$orderId = (int) (
    $successData['order_id'] ?? 0
);

$orderNumber = $successData['order_number'] ?? '';

$totalAmount = (float) (
    $successData['total_amount'] ?? 0
);

$totalWeight = (float) (
    $successData['total_weight'] ?? 0
);


/*
|--------------------------------------------------------------------------
| VALIDATE ORDER ID
|--------------------------------------------------------------------------
*/

if ($orderId <= 0) {

    unset($_SESSION['order_success']);

    header(
        'Location: ' .
        BASE_URL .
        'customer/dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GET ORDER INFORMATION
|--------------------------------------------------------------------------
|
| Retrieve the order from the database instead of relying entirely
| on session data.
|
*/

$orderStmt = $db->prepare("
    SELECT
        o.order_id,
        o.order_number,
        o.customer_id,
        o.item_description,
        o.total_amount,
        o.total_weight,
        o.total_weight_kg,
        o.order_date,
        o.expected_delivery_date,
        o.priority_level,
        o.completion_status,
        o.created_at
    FROM `order` o
    WHERE o.order_id = ?
      AND o.customer_id = ?
    LIMIT 1
");

$orderStmt->execute([
    $orderId,
    $customerId
]);

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ORDER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$order) {

    unset($_SESSION['order_success']);

    header(
        'Location: ' .
        BASE_URL .
        'customer/order_history.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GET DELIVERY LOCATION
|--------------------------------------------------------------------------
*/

$locationStmt = $db->prepare("
    SELECT
        location_id,
        full_address,
        city,
        province,
        latitude,
        longitude,
        contact_person,
        contact_number
    FROM delivery_location
    WHERE order_id = ?
    ORDER BY location_id DESC
    LIMIT 1
");

$locationStmt->execute([
    $orderId
]);

$deliveryLocation =
    $locationStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET ORDER ITEMS
|--------------------------------------------------------------------------
*/

$itemStmt = $db->prepare("
    SELECT
        oi.order_item_id,
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.unit_weight_kg,
        oi.total_weight_kg,
        oi.subtotal,
        p.product_name,
        p.product_code,
        p.product_image
    FROM order_item oi
    LEFT JOIN product p
        ON p.product_id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id ASC
");

$itemStmt->execute([
    $orderId
]);

$orderItems =
    $itemStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET CURRENT DELIVERY STATUS
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
    ORDER BY timestamp DESC, status_id DESC
    LIMIT 1
");

$statusStmt->execute([
    $orderId
]);

$currentStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$displayStatus =
    $order['completion_status']
    ?? 'Pending';

$displayOrderNumber =
    $order['order_number'] ?? $orderNumber;

$displayTotalAmount =
    (float) $order['total_amount'];

$displayTotalWeight =
    (float) (
        $order['total_weight_kg']
        ?? $order['total_weight']
        ?? $totalWeight
    );

$displayExpectedDate =
    $order['expected_delivery_date'] ?? '';

$displayPriority =
    $order['priority_level'] ?? 'Normal';

$displayAddress =
    $deliveryLocation['full_address'] ?? '';

$displayCity =
    $deliveryLocation['city'] ?? '';

$displayProvince =
    $deliveryLocation['province'] ?? '';

$displayLatitude =
    $deliveryLocation['latitude'] ?? '';

$displayLongitude =
    $deliveryLocation['longitude'] ?? '';


/*
|--------------------------------------------------------------------------
| FORMAT EXPECTED DELIVERY DATE
|--------------------------------------------------------------------------
*/

$formattedDeliveryDate = '—';

if (!empty($displayExpectedDate)) {

    try {

        $date =
            new DateTime(
                $displayExpectedDate
            );

        $formattedDeliveryDate =
            $date->format(
                'F d, Y'
            );

    } catch (Throwable $e) {

        $formattedDeliveryDate =
            htmlspecialchars(
                $displayExpectedDate
            );
    }
}


/*
|--------------------------------------------------------------------------
| BUILD NAVIGATION
|--------------------------------------------------------------------------
*/

$navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL .
            'customer/dashboard.php'
    ],

    [
        'label' => 'Cart',
        'url' =>
            BASE_URL .
            'customer/cart.php'
    ],

    [
        'label' => 'My Orders',
        'url' =>
            BASE_URL .
            'customer/order_history.php'
    ],

    [
        'label' => 'Track Delivery',
        'url' =>
            BASE_URL .
            'customer/track_delivery.php'
    ],

    [
        'label' => 'Feedback',
        'url' =>
            BASE_URL .
            'customer/feedback.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL .
            'customer/notifications.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'customer/profile.php'
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
        Order Confirmed · Hong Sing LKRR Commercial
    </title>

    

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        /* ======================================================
           PAGE
        ====================================================== */

        .success-page {

            max-width: 1050px;

            margin: 0 auto;

        }


        /* ======================================================
           SUCCESS HEADER
        ====================================================== */

        .success-header {

            text-align: center;

            padding: 35px 20px 25px;

        }

        .success-icon {

            width: 72px;

            height: 72px;

            margin: 0 auto 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background:
                rgba(34, 197, 94, .12);

            border:
                1px solid
                rgba(34, 197, 94, .25);

            color:
                #16a34a;

            font-size: 34px;

            font-weight: 700;

        }

        .success-header h1 {

            margin: 0 0 8px;

            font-size: 30px;

        }

        .success-header p {

            margin: 0;

            color:
                var(--color-text-secondary);

            font-size: 14px;

        }


        /* ======================================================
           ORDER NUMBER
        ====================================================== */

        .order-number-box {

            max-width: 500px;

            margin: 20px auto 0;

            padding: 14px 18px;

            border:
                1px solid
                var(--color-border);

            border-radius: 9px;

            background:
                var(--color-surface);

        }

        .order-number-label {

            display: block;

            margin-bottom: 5px;

            font-size: 11px;

            color:
                var(--color-text-muted);

            text-transform: uppercase;

            letter-spacing: .5px;

        }

        .order-number {

            font-size: 20px;

            font-weight: 700;

        }


        /* ======================================================
           LAYOUT
        ====================================================== */

        .success-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                330px;

            gap: 20px;

            align-items: start;

        }


        /* ======================================================
           CARD
        ====================================================== */

        .success-card {

            padding: 20px;

            margin-bottom: 18px;

        }

        .success-card h2 {

            margin: 0 0 5px;

            font-size: 18px;

        }

        .card-description {

            margin: 0 0 18px;

            color:
                var(--color-text-secondary);

            font-size: 13px;

        }


        /* ======================================================
           STATUS
        ====================================================== */

        .status-box {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            padding: 14px;

            border-radius: 9px;

            background:
                rgba(245, 158, 11, .08);

            border:
                1px solid
                rgba(245, 158, 11, .20);

        }

        .status-icon {

            font-size: 22px;

        }

        .status-title {

            font-weight: 700;

            margin-bottom: 3px;

        }

        .status-text {

            font-size: 12px;

            line-height: 1.5;

            color:
                var(--color-text-secondary);

        }


        /* ======================================================
           ORDER DETAILS
        ====================================================== */

        .detail-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 12px;

        }

        .detail-item {

            padding: 12px;

            border:
                1px solid
                var(--color-border);

            border-radius: 8px;

        }

        .detail-label {

            display: block;

            margin-bottom: 4px;

            font-size: 11px;

            color:
                var(--color-text-muted);

        }

        .detail-value {

            font-size: 14px;

            font-weight: 600;

            word-break: break-word;

        }


        /* ======================================================
           DELIVERY ADDRESS
        ====================================================== */

        .delivery-address {

            padding: 14px;

            border:
                1px solid
                var(--color-border);

            border-radius: 8px;

        }

        .delivery-address strong {

            display: block;

            margin-bottom: 6px;

        }

        .delivery-address-text {

            font-size: 13px;

            line-height: 1.5;

        }

        .coordinates {

            margin-top: 9px;

            font-size: 11px;

            color:
                var(--color-text-secondary);

        }


        /* ======================================================
           ITEMS
        ====================================================== */

        .order-items {

            display: flex;

            flex-direction: column;

            gap: 10px;

        }

        .order-item {

            display: grid;

            grid-template-columns:
                55px
                minmax(0, 1fr)
                auto;

            gap: 12px;

            align-items: center;

            padding: 10px;

            border:
                1px solid
                var(--color-border);

            border-radius: 8px;

        }

        .item-image {

            width: 55px;

            height: 55px;

            object-fit: cover;

            border-radius: 6px;

            background: #f3f3f3;

        }

        .item-placeholder {

            width: 55px;

            height: 55px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 6px;

            background: #f3f3f3;

            color:
                var(--color-text-secondary);

            font-size: 9px;

        }

        .item-name {

            font-weight: 600;

        }

        .item-meta {

            margin-top: 3px;

            font-size: 12px;

            color:
                var(--color-text-secondary);

        }

        .item-total {

            font-weight: 700;

            white-space: nowrap;

        }


        /* ======================================================
           SUMMARY
        ====================================================== */

        .summary-card {

            position: sticky;

            top: 20px;

            padding: 20px;

        }

        .summary-row {

            display: flex;

            justify-content: space-between;

            gap: 15px;

            padding: 11px 0;

            border-bottom:
                1px solid
                var(--color-border);

        }

        .summary-label {

            color:
                var(--color-text-secondary);

        }

        .summary-value {

            font-weight: 600;

            text-align: right;

        }

        .summary-total {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            padding-top: 16px;

        }

        .summary-total strong {

            font-size: 21px;

        }


        /* ======================================================
           BUTTONS
        ====================================================== */

        .action-buttons {

            display: flex;

            flex-direction: column;

            gap: 9px;

            margin-top: 20px;

        }

        .action-button {

            display: block;

            width: 100%;

            box-sizing: border-box;

            padding: 12px 15px;

            border-radius: 7px;

            text-align: center;

            text-decoration: none;

            font-weight: 600;

        }

        .primary-button {

            background:
                var(--color-accent);

            color: white;

        }

        .secondary-button {

            border:
                1px solid
                var(--color-border);

            background:
                var(--color-surface);

            color:
                var(--color-text-primary);

        }


        /* ======================================================
           NOTICE
        ====================================================== */

        .notice {

            margin-top: 15px;

            padding: 12px;

            border-radius: 8px;

            background:
                rgba(59, 130, 246, .08);

            border:
                1px solid
                rgba(59, 130, 246, .18);

            font-size: 12px;

            line-height: 1.5;

        }


        /* ======================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 850px) {

            .success-grid {

                grid-template-columns: 1fr;

            }

            .summary-card {

                position: static;

            }

        }


        @media (max-width: 600px) {

            .success-header {

                padding:
                    25px
                    10px
                    20px;

            }

            .success-header h1 {

                font-size: 25px;

            }

            .detail-grid {

                grid-template-columns: 1fr;

            }

            .order-item {

                grid-template-columns:
                    50px
                    minmax(0, 1fr);

            }

            .item-image,
            .item-placeholder {

                width: 50px;

                height: 50px;

            }

            .item-total {

                grid-column: 2;

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

        <div class="success-page">


            <!-- ==================================================
                 SUCCESS HEADER
            =================================================== -->

            <section class="success-header">

                <div class="success-icon">
                    ✓
                </div>

                <h1>
                    Order Placed Successfully!
                </h1>

                <p>
                    Thank you for your order.
                    Your order has been received and is now
                    waiting for administrator approval.
                </p>


                <div class="order-number-box">

                    <span class="order-number-label">
                        Order Number
                    </span>

                    <div class="order-number">

                        <?= htmlspecialchars(
                            $displayOrderNumber
                        ) ?>

                    </div>

                </div>

            </section>


            <div class="success-grid">


                <!-- ==================================================
                     LEFT COLUMN
                =================================================== -->

                <section>


                    <!-- ==================================================
                         STATUS
                    =================================================== -->

                    <div class="card success-card">

                        <h2>
                            Order Status
                        </h2>

                        <p class="card-description">
                            Current status of your order.
                        </p>


                        <div class="status-box">

                            <div class="status-icon">
                                ⏳
                            </div>

                            <div>

                                <div class="status-title">

                                    <?= htmlspecialchars(
                                        $displayStatus
                                    ) ?>

                                </div>

                                <div class="status-text">

                                    <?php if (
                                        $displayStatus === 'Pending'
                                    ): ?>

                                        Your order has been submitted
                                        successfully and is waiting for
                                        administrator approval.

                                    <?php elseif (
                                        $displayStatus === 'Approved'
                                    ): ?>

                                        Your order has been approved
                                        and will proceed to driver
                                        assignment.

                                    <?php elseif (
                                        $displayStatus === 'Assigned'
                                    ): ?>

                                        A driver and truck have been
                                        assigned to your order.

                                    <?php elseif (
                                        $displayStatus === 'Accepted'
                                    ): ?>

                                        The assigned driver has accepted
                                        your delivery.

                                    <?php elseif (
                                        $displayStatus === 'In Transit'
                                    ): ?>

                                        Your order is currently
                                        being delivered.

                                    <?php elseif (
                                        $displayStatus === 'Completed'
                                    ): ?>

                                        Your order has been successfully
                                        completed.

                                    <?php elseif (
                                        $displayStatus === 'Cancelled'
                                    ): ?>

                                        This order has been cancelled.

                                    <?php else: ?>

                                        Your order is currently being
                                        processed.

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- ==================================================
                         ORDER DETAILS
                    =================================================== -->

                    <div class="card success-card">

                        <h2>
                            Order Details
                        </h2>

                        <p class="card-description">
                            Important information about your order.
                        </p>


                        <div class="detail-grid">


                            <div class="detail-item">

                                <span class="detail-label">
                                    Order Date
                                </span>

                                <span class="detail-value">

                                    <?= htmlspecialchars(
                                        date(
                                            'F d, Y',
                                            strtotime(
                                                $order['order_date']
                                            )
                                        )
                                    ) ?>

                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Expected Delivery
                                </span>

                                <span class="detail-value">

                                    <?= htmlspecialchars(
                                        $formattedDeliveryDate
                                    ) ?>

                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Priority
                                </span>

                                <span class="detail-value">

                                    <?= htmlspecialchars(
                                        $displayPriority
                                    ) ?>

                                </span>

                            </div>


                            <div class="detail-item">

                                <span class="detail-label">
                                    Current Status
                                </span>

                                <span class="detail-value">

                                    <?= htmlspecialchars(
                                        $displayStatus
                                    ) ?>

                                </span>

                            </div>


                        </div>

                    </div>


                    <!-- ==================================================
                         DELIVERY LOCATION
                    =================================================== -->

                    <div class="card success-card">

                        <h2>
                            Delivery Location
                        </h2>

                        <p class="card-description">
                            The location you selected during checkout.
                        </p>


                        <?php if ($deliveryLocation): ?>

                            <div class="delivery-address">

                                <strong>
                                    📍 Delivery Address
                                </strong>

                                <div class="delivery-address-text">

                                    <?= htmlspecialchars(
                                        $displayAddress
                                    ) ?>

                                    <br>

                                    <?= htmlspecialchars(
                                        $displayCity
                                    ) ?>

                                    <?php if (
                                        $displayProvince
                                    ): ?>

                                        ,
                                        <?= htmlspecialchars(
                                            $displayProvince
                                        ) ?>

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    $displayLatitude !== '' &&
                                    $displayLongitude !== ''
                                ): ?>

                                    <div class="coordinates">

                                        Coordinates:
                                        <?= htmlspecialchars(
                                            $displayLatitude
                                        ) ?>,
                                        <?= htmlspecialchars(
                                            $displayLongitude
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php else: ?>

                            <div class="delivery-address">

                                Delivery location information
                                is not available.

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- ==================================================
                         ORDER ITEMS
                    =================================================== -->

                    <div class="card success-card">

                        <h2>
                            Order Items
                        </h2>

                        <p class="card-description">
                            Products included in your order.
                        </p>


                        <div class="order-items">


                            <?php foreach (
                                $orderItems
                                as $item
                            ): ?>


                                <div class="order-item">


                                    <?php if (
                                        !empty(
                                            $item['product_image']
                                        )
                                    ): ?>

                                        <img
                                            src="<?= BASE_URL ?>assets/uploads/products/<?= htmlspecialchars($item['product_image']) ?>"
                                            alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>"
                                            class="item-image"
                                        >

                                    <?php else: ?>

                                        <div class="item-placeholder">
                                            No Image
                                        </div>

                                    <?php endif; ?>


                                    <div>

                                        <div class="item-name">

                                            <?= htmlspecialchars(
                                                $item['product_name']
                                                ?? 'Product'
                                            ) ?>

                                        </div>

                                        <div class="item-meta">

                                            <?= (int)
                                                $item['quantity'] ?>

                                            ×

                                            ₱<?= number_format(
                                                (float)
                                                $item['unit_price'],
                                                2
                                            ) ?>

                                            ·

                                            <?= number_format(
                                                (float)
                                                $item['unit_weight_kg'],
                                                2
                                            ) ?>

                                            kg/unit

                                        </div>

                                    </div>


                                    <div class="item-total">

                                        ₱<?= number_format(
                                            (float)
                                            $item['subtotal'],
                                            2
                                        ) ?>

                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>

                    </div>


                </section>


                <!-- ==================================================
                     RIGHT COLUMN
                =================================================== -->

                <aside>


                    <div class="card summary-card">

                        <h2>
                            Order Summary
                        </h2>


                        <div class="summary-row">

                            <span class="summary-label">
                                Products
                            </span>

                            <span class="summary-value">

                                <?= count(
                                    $orderItems
                                ) ?>

                            </span>

                        </div>


                        <div class="summary-row">

                            <span class="summary-label">
                                Total Items
                            </span>

                            <span class="summary-value">

                                <?php

                                $successTotalItems = 0;

                                foreach (
                                    $orderItems
                                    as $item
                                ) {

                                    $successTotalItems +=
                                        (int)
                                        $item['quantity'];
                                }

                                echo $successTotalItems;

                                ?>

                            </span>

                        </div>


                        <div class="summary-row">

                            <span class="summary-label">
                                Total Weight
                            </span>

                            <span class="summary-value">

                                <?= number_format(
                                    $displayTotalWeight,
                                    2
                                ) ?>

                                kg

                            </span>

                        </div>


                        <div class="summary-total">

                            <span>
                                Total Amount
                            </span>

                            <strong>

                                ₱<?= number_format(
                                    $displayTotalAmount,
                                    2
                                ) ?>

                            </strong>

                        </div>


                        <div class="action-buttons">

                            <a
                                href="<?= BASE_URL ?>customer/order_details.php?order_id=<?= $orderId ?>"
                                class="action-button primary-button"
                            >
                                View Order Details
                            </a>

                            <a
                                href="<?= BASE_URL ?>customer/order_history.php"
                                class="action-button secondary-button"
                            >
                                My Orders
                            </a>

                            <a
                                href="<?= BASE_URL ?>customer/dashboard.php"
                                class="action-button secondary-button"
                            >
                                Back to Dashboard
                            </a>

                        </div>


                        <div class="notice">

                            <strong>Next Step:</strong>

                            Your order is currently

                            <strong>
                                Pending
                            </strong>.

                            The administrator will review your order
                            before assigning a driver, truck, and route.

                        </div>

                    </div>


                </aside>


            </div>

        </div>

    </main>

</div>


<?php

/*
|--------------------------------------------------------------------------
| CLEAR SUCCESS SESSION
|--------------------------------------------------------------------------
|
| We clear this after the page has loaded so refreshing the page
| does not recreate the success screen indefinitely.
|
*/

unset(
    $_SESSION['order_success']
);

?>

</body>

</html>