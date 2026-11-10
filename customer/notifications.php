<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

$customerId = currentUserId();


/*
|--------------------------------------------------------------------------
| MARK SINGLE NOTIFICATION AS READ
|--------------------------------------------------------------------------
*/

if (isset($_GET['read'])) {

    $notificationId = (int) $_GET['read'];

    if ($notificationId > 0) {

        $stmt = $db->prepare("
            UPDATE notification
            SET is_read = 1
            WHERE notification_id = ?
              AND recipient_role = ?
              AND recipient_id = ?
        ");

        $stmt->execute([
            $notificationId,
            ROLE_CUSTOMER,
            $customerId
        ]);
    }

    header(
        'Location: ' .
        BASE_URL .
        'customer/notifications.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MARK ALL NOTIFICATIONS AS READ
|--------------------------------------------------------------------------
*/

if (isset($_GET['mark_all_read'])) {

    $stmt = $db->prepare("
        UPDATE notification
        SET is_read = 1
        WHERE recipient_role = ?
          AND recipient_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        ROLE_CUSTOMER,
        $customerId
    ]);

    header(
        'Location: ' .
        BASE_URL .
        'customer/notifications.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| UNREAD COUNT
|--------------------------------------------------------------------------
*/

$unreadStmt = $db->prepare("
    SELECT COUNT(*)
    FROM notification
    WHERE recipient_role = ?
      AND recipient_id = ?
      AND is_read = 0
");

$unreadStmt->execute([
    ROLE_CUSTOMER,
    $customerId
]);

$unreadCount =
    (int) $unreadStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| GET NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$notificationStmt = $db->prepare("
    SELECT

        n.notification_id,
        n.order_id,
        n.status_id,
        n.notification_type,
        n.is_read,
        n.sent_at,
        n.delivery_status,
        n.message,

        o.order_number

    FROM notification n

    LEFT JOIN `order` o
        ON o.order_id = n.order_id

    WHERE n.recipient_role = ?
      AND n.recipient_id = ?

    ORDER BY

        n.is_read ASC,
        n.sent_at DESC

    LIMIT 100
");

$notificationStmt->execute([
    ROLE_CUSTOMER,
    $customerId
]);

$notifications =
    $notificationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


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
    Notifications · Hong Sing LKRR Commercial
</title>


<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>


<style>

/* ==========================================================
   NOTIFICATIONS PAGE
========================================================== */

.notifications-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 24px;

}


.notifications-title h1 {

    margin: 0 0 6px;

}


.notifications-title p {

    margin: 0;

    color:
        var(--color-text-secondary);

}


/* ==========================================================
   HEADER ACTIONS
========================================================== */

.notifications-actions {

    display: flex;

    align-items: center;

    gap: 10px;

}


.unread-badge {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 30px;

    height: 30px;

    padding: 0 10px;

    border-radius: 20px;

    background:
        var(--color-accent);

    color: white;

    font-size: 12px;

    font-weight: 700;

}


.mark-all-button {

    display: inline-flex;

    align-items: center;

    padding: 9px 14px;

    border-radius: 7px;

    border: 1px solid
        var(--color-border);

    background:
        var(--color-surface);

    color:
        var(--color-text-primary);

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

}


.mark-all-button:hover {

    border-color:
        var(--color-accent);

    color:
        var(--color-accent);

}


/* ==========================================================
   NOTIFICATION LIST
========================================================== */

.notifications-list {

    display: flex;

    flex-direction: column;

    gap: 12px;

}


.notification-card {

    background:
        var(--color-surface);

    border: 1px solid
        var(--color-border);

    border-radius: 10px;

    padding: 18px 20px;

    transition:
        box-shadow .15s ease,
        transform .15s ease;

}


.notification-card:hover {

    box-shadow:
        0 5px 18px rgba(0,0,0,.06);

}


.notification-card.unread {

    border-left:
        4px solid
        var(--color-accent);

}


.notification-row {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

}


.notification-main {

    flex: 1;

    min-width: 0;

}


.notification-heading {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 8px;

}


.notification-type {

    margin: 0;

    font-size: 16px;

    font-weight: 700;

}


.new-label {

    display: inline-flex;

    align-items: center;

    padding: 3px 7px;

    border-radius: 4px;

    background:
        var(--color-accent);

    color: white;

    font-size: 10px;

    font-weight: 700;

}


.notification-message {

    margin: 0;

    line-height: 1.5;

    color:
        var(--color-text-secondary);

}


.notification-order {

    margin-top: 10px;

    font-size: 13px;

    color:
        var(--color-text-secondary);

}


.notification-order strong {

    color:
        var(--color-text-primary);

}


.notification-meta {

    margin-top: 8px;

    font-size: 12px;

    color:
        var(--color-text-muted);

}


/* ==========================================================
   READ ACTION
========================================================== */

.notification-action {

    flex-shrink: 0;

}


.read-button {

    display: inline-flex;

    align-items: center;

    padding: 7px 11px;

    border-radius: 6px;

    border: 1px solid
        var(--color-border);

    background:
        var(--color-surface);

    color:
        var(--color-text-primary);

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

}


.read-button:hover {

    border-color:
        var(--color-accent);

    color:
        var(--color-accent);

}


.read-label {

    font-size: 12px;

    color:
        var(--color-text-muted);

}


/* ==========================================================
   EMPTY STATE
========================================================== */

.notifications-empty {

    padding: 60px 20px;

    text-align: center;

}


.notifications-empty-icon {

    font-size: 42px;

    margin-bottom: 12px;

}


.notifications-empty h3 {

    margin: 0 0 6px;

}


.notifications-empty p {

    margin: 0;

    color:
        var(--color-text-secondary);

}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 700px) {

    .notifications-header {

        flex-direction: column;

        align-items: flex-start;

    }


    .notification-row {

        flex-direction: column;

    }


    .notification-action {

        width: 100%;

    }

}

</style>

</head>


<body>

<div class="app-layout">


<?php
/*
|--------------------------------------------------------------------------
| EXISTING CUSTOMER SIDEBAR / HEADER
|--------------------------------------------------------------------------
|
| DO NOT REMOVE THIS.
| This is the same header used by customer/dashboard.php.
|
*/

require __DIR__ .
    '/../includes/header.php';

?>


<main class="app-main">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="notifications-header">


    <div class="notifications-title">

        <h1>
            Notifications
        </h1>

        <p>
            Stay updated with your orders and deliveries.
        </p>

    </div>


    <?php if ($unreadCount > 0): ?>

        <div class="notifications-actions">


            <span class="unread-badge">

                <?= $unreadCount ?>

                unread

            </span>


            <a
                href="<?= BASE_URL ?>customer/notifications.php?mark_all_read=1"
                class="mark-all-button"
            >

                Mark All as Read

            </a>


        </div>

    <?php endif; ?>


</div>


<!-- ==========================================================
     NOTIFICATIONS
========================================================== -->

<?php if (empty($notifications)): ?>


    <div class="card">

        <div class="notifications-empty">

            <div class="notifications-empty-icon">
                🔔
            </div>


            <h3>
                No Notifications
            </h3>


            <p>
                You don't have any notifications yet.
            </p>

        </div>

    </div>


<?php else: ?>


    <div class="notifications-list">


        <?php foreach (
            $notifications
            as $notification
        ): ?>


            <?php

            $isUnread =
                (int)
                $notification['is_read']
                === 0;

            ?>


            <div
                class="
                    notification-card
                    <?= $isUnread ? 'unread' : '' ?>
                "
            >


                <div class="notification-row">


                    <!-- ==========================================
                         NOTIFICATION CONTENT
                    =========================================== -->

                    <div class="notification-main">


                        <div class="notification-heading">


                            <?php if ($isUnread): ?>

                                <span class="new-label">
                                    NEW
                                </span>

                            <?php endif; ?>


                            <h3 class="notification-type">

                                <?= htmlspecialchars(
                                    $notification[
                                        'notification_type'
                                    ]
                                    ?: 'Notification'
                                ) ?>

                            </h3>


                        </div>


                        <p class="notification-message">

                            <?= nl2br(
                                htmlspecialchars(
                                    $notification[
                                        'message'
                                    ]
                                    ?? ''
                                )
                            ) ?>

                        </p>


                        <?php if (
                            !empty(
                                $notification[
                                    'order_number'
                                ]
                            )
                        ): ?>


                            <div class="notification-order">

                                Order:

                                <strong>

                                    <?= htmlspecialchars(
                                        $notification[
                                            'order_number'
                                        ]
                                    ) ?>

                                </strong>

                            </div>


                        <?php endif; ?>


                        <div class="notification-meta">

                            <?php if (
                                !empty(
                                    $notification['sent_at']
                                )
                            ): ?>

                                <?= date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $notification[
                                            'sent_at'
                                        ]
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>


                        </div>


                    </div>


                    <!-- ==========================================
                         READ BUTTON
                    =========================================== -->

                    <div class="notification-action">


                        <?php if ($isUnread): ?>


                            <a
                                href="<?= BASE_URL ?>customer/notifications.php?read=<?= (int) $notification['notification_id'] ?>"
                                class="read-button"
                            >

                                Mark as Read

                            </a>


                        <?php else: ?>


                            <span class="read-label">

                                Read

                            </span>


                        <?php endif; ?>


                    </div>


                </div>


            </div>


        <?php endforeach; ?>


    </div>


<?php endif; ?>


</main>


</div>


</body>

</html>