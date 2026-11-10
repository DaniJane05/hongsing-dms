<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = (int) currentUserId();

if ($driverId <= 0) {
    header('Location: ../login.php?error=session_expired');
    exit;
}


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
              AND recipient_role = 'Driver'
              AND recipient_id = ?
        ");

        $stmt->execute([
            $notificationId,
            $driverId
        ]);
    }

    header('Location: notifications.php');
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
        WHERE recipient_role = 'Driver'
          AND recipient_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        $driverId
    ]);

    header('Location: notifications.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET UNREAD COUNT
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM notification
    WHERE recipient_role = 'Driver'
      AND recipient_id = ?
      AND is_read = 0
");

$stmt->execute([
    $driverId
]);

$unreadCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| GET DRIVER NOTIFICATIONS
|--------------------------------------------------------------------------
|
| Only notifications belonging to the currently logged-in driver
| are displayed.
|
*/

$stmt = $db->prepare("
    SELECT
        n.notification_id,
        n.order_id,
        n.status_id,
        n.notification_type,
        n.is_read,
        n.sent_at,
        n.delivery_status,
        n.message,

        o.order_number,
        o.completion_status,
        o.expected_delivery_date

    FROM notification n

    LEFT JOIN `order` o
        ON o.order_id = n.order_id

    WHERE n.recipient_role = 'Driver'
      AND n.recipient_id = ?

    ORDER BY
        n.is_read ASC,
        n.sent_at DESC

    LIMIT 100
");

$stmt->execute([
    $driverId
]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
        Notifications · Hong Sing LKRR Commercial
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATIONS PAGE
        |--------------------------------------------------------------------------
        */

        .notifications-page {
            max-width: 1100px;
            margin: 0 auto;
        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .notifications-header h1 {
            margin: 0 0 6px;
        }

        .notifications-header p {
            margin: 0;
            color: var(--color-text-secondary);
        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .notification-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
            padding: 18px 20px;
        }

        .notification-summary-text {
            color: var(--color-text-secondary);
            font-size: 0.9rem;
        }

        .notification-summary-text strong {
            color: var(--color-text-primary);
        }


        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION LIST
        |--------------------------------------------------------------------------
        */

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION ITEM
        |--------------------------------------------------------------------------
        */

        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;

            padding: 20px;

            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);

            background: var(--color-surface);

            transition:
                background 0.15s ease,
                border-color 0.15s ease;
        }

        .notification-item:hover {
            background: var(--color-surface-hover);
        }


        /*
        |--------------------------------------------------------------------------
        | UNREAD
        |--------------------------------------------------------------------------
        */

        .notification-item.unread {
            border-left: 4px solid var(--color-accent);
        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-top {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .notification-type {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;

            color: var(--color-accent);
        }

        .notification-status {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 999px;

            background: var(--color-accent-soft);
            color: var(--color-accent);
        }


        /*
        |--------------------------------------------------------------------------
        | MESSAGE
        |--------------------------------------------------------------------------
        */

        .notification-message {
            font-size: 0.95rem;
            line-height: 1.55;

            color: var(--color-text-primary);

            white-space: normal;
            overflow-wrap: anywhere;
        }


        /*
        |--------------------------------------------------------------------------
        | ORDER INFORMATION
        |--------------------------------------------------------------------------
        */

        .notification-order {
            margin-top: 10px;

            color: var(--color-text-secondary);
            font-size: 13px;
        }

        .notification-order strong {
            color: var(--color-text-primary);
        }


        /*
        |--------------------------------------------------------------------------
        | DATE
        |--------------------------------------------------------------------------
        */

        .notification-date {
            margin-top: 7px;

            color: var(--color-text-muted);
            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | ACTION
        |--------------------------------------------------------------------------
        */

        .notification-action {
            flex-shrink: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .notification-empty {
            text-align: center;

            padding: 60px 25px;

            color: var(--color-text-secondary);
        }

        .notification-empty strong {
            display: block;

            margin-bottom: 6px;

            color: var(--color-text-primary);
            font-size: 1rem;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .notification-summary {
                align-items: stretch;
                flex-direction: column;
            }

            .notification-item {
                flex-direction: column;
            }

            .notification-action {
                width: 100%;
            }

            .notification-action .btn-secondary {
                width: 100%;
                text-align: center;
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

        <div class="notifications-page">


            <!-- =====================================================
                 PAGE HEADER
                 ===================================================== -->

            <div class="notifications-header">

                <div>

                    <h1>
                        Notifications
                    </h1>

                    <p>
                        View your latest delivery and system updates.
                    </p>

                </div>

            </div>


            <!-- =====================================================
                 SUMMARY
                 ===================================================== -->

            <div class="card notification-summary">

                <div class="notification-summary-text">

                    <?php if ($unreadCount > 0): ?>

                        You have
                        <strong>
                            <?= $unreadCount ?>
                        </strong>
                        unread notification<?= $unreadCount === 1 ? '' : 's' ?>.

                    <?php else: ?>

                        You have no unread notifications.

                    <?php endif; ?>

                </div>


                <?php if ($unreadCount > 0): ?>

                    <a
                        href="notifications.php?mark_all_read=1"
                        class="btn-secondary"
                    >
                        Mark All as Read
                    </a>

                <?php endif; ?>

            </div>


            <!-- =====================================================
                 NOTIFICATION LIST
                 ===================================================== -->

            <div class="notification-list">

                <?php if (empty($notifications)): ?>

                    <div class="card notification-empty">

                        <strong>
                            No notifications
                        </strong>

                        <span>
                            You currently have no delivery or system notifications.
                        </span>

                    </div>

                <?php else: ?>


                    <?php foreach ($notifications as $notification): ?>

                        <?php

                        $isUnread =
                            (int) $notification['is_read'] === 0;

                        ?>

                        <div
                            class="notification-item <?= $isUnread ? 'unread' : '' ?>"
                        >

                            <div class="notification-content">


                                <!-- TYPE -->

                                <div class="notification-top">

                                    <span class="notification-type">
                                        <?= htmlspecialchars(
                                            $notification['notification_type'] ?? 'In-App'
                                        ) ?>
                                    </span>


                                    <?php if ($isUnread): ?>

                                        <span class="notification-status">
                                            Unread
                                        </span>

                                    <?php else: ?>

                                        <span class="notification-status">
                                            Read
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- MESSAGE -->

                                <div class="notification-message">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $notification['message'] ?? ''
                                        )
                                    ) ?>

                                </div>


                                <!-- ORDER -->

                                <?php if (!empty($notification['order_number'])): ?>

                                    <div class="notification-order">

                                        Order:

                                        <strong>
                                            <?= htmlspecialchars(
                                                $notification['order_number']
                                            ) ?>
                                        </strong>

                                        <?php if (
                                            !empty(
                                                $notification['completion_status']
                                            )
                                        ): ?>

                                            —
                                            <?= htmlspecialchars(
                                                $notification['completion_status']
                                            ) ?>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>


                                <!-- DATE -->

                                <div class="notification-date">

                                    <?= !empty($notification['sent_at'])
                                        ? date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $notification['sent_at']
                                            )
                                        )
                                        : '-'
                                    ?>

                                </div>

                            </div>


                            <!-- ACTION -->

                            <div class="notification-action">

                                <?php if ($isUnread): ?>

                                    <a
                                        href="notifications.php?read=<?= (int) $notification['notification_id'] ?>"
                                        class="btn-secondary"
                                    >
                                        Mark as Read
                                    </a>

                                <?php else: ?>

                                    <span
                                        style="
                                            color: var(--color-text-muted);
                                            font-size: 13px;
                                        "
                                    >
                                        Read
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>


                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

</body>

</html>