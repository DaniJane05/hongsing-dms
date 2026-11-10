<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

/* ==========================================================
   CURRENT ADMIN
========================================================== */

$adminId = $_SESSION['user_id']
    ?? $_SESSION['system_user_id']
    ?? null;

if (!$adminId) {
    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php?error=session_expired'
    );
    exit;
}

$adminId = (int) $adminId;


/* ==========================================================
   MARK SINGLE NOTIFICATION AS READ
========================================================== */

if (isset($_GET['read'])) {

    $notificationId = (int) $_GET['read'];

    if ($notificationId > 0) {

        $stmt = $db->prepare("
            UPDATE notification
            SET is_read = 1
            WHERE notification_id = ?
              AND recipient_role = 'Admin'
              AND recipient_id = ?
        ");

        $stmt->execute([
            $notificationId,
            $adminId
        ]);
    }

    header(
        'Location: ' .
        BASE_URL .
        'admin/notifications.php'
    );
    exit;
}


/* ==========================================================
   MARK ALL NOTIFICATIONS AS READ
========================================================== */

if (isset($_GET['mark_all_read'])) {

    $stmt = $db->prepare("
        UPDATE notification
        SET is_read = 1
        WHERE recipient_role = 'Admin'
          AND recipient_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        $adminId
    ]);

    header(
        'Location: ' .
        BASE_URL .
        'admin/notifications.php'
    );
    exit;
}


/* ==========================================================
   GET UNREAD COUNT
========================================================== */

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM notification
    WHERE recipient_role = 'Admin'
      AND recipient_id = ?
      AND is_read = 0
");

$stmt->execute([
    $adminId
]);

$unreadCount = (int) $stmt->fetchColumn();


/* ==========================================================
   GET ADMIN NOTIFICATIONS
========================================================== */

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
        c.full_name AS customer_name
    FROM notification n
    LEFT JOIN `order` o
        ON o.order_id = n.order_id
    LEFT JOIN customer c
        ON c.customer_id = o.customer_id
    WHERE n.recipient_role = 'Admin'
      AND n.recipient_id = ?
    ORDER BY
        n.is_read ASC,
        n.sent_at DESC
    LIMIT 100
");

$stmt->execute([
    $adminId
]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================================
   HELPER FUNCTIONS
========================================================== */

function notificationTypeClass(string $type): string
{
    $type = strtolower(trim($type));

    if (
        str_contains($type, 'delivery') ||
        str_contains($type, 'transit')
    ) {
        return 'notification-type-delivery';
    }

    if (
        str_contains($type, 'order')
    ) {
        return 'notification-type-order';
    }

    if (
        str_contains($type, 'complete') ||
        str_contains($type, 'delivered')
    ) {
        return 'notification-type-success';
    }

    if (
        str_contains($type, 'cancel') ||
        str_contains($type, 'fail')
    ) {
        return 'notification-type-danger';
    }

    if (
        str_contains($type, 'urgent') ||
        str_contains($type, 'warning')
    ) {
        return 'notification-type-warning';
    }

    return 'notification-type-default';
}


function notificationIcon(string $type): string
{
    $type = strtolower(trim($type));

    if (
        str_contains($type, 'delivery') ||
        str_contains($type, 'transit')
    ) {
        return '🚚';
    }

    if (
        str_contains($type, 'order')
    ) {
        return '📦';
    }

    if (
        str_contains($type, 'complete') ||
        str_contains($type, 'delivered')
    ) {
        return '✓';
    }

    if (
        str_contains($type, 'cancel') ||
        str_contains($type, 'fail')
    ) {
        return '!';
    }

    if (
        str_contains($type, 'urgent') ||
        str_contains($type, 'warning')
    ) {
        return '⚠';
    }

    return '🔔';
}


function deliveryStatusClass(?string $status): string
{
    $status = strtolower(trim((string) $status));

    return match ($status) {

        'pending' =>
            'notification-status-pending',

        'assigned' =>
            'notification-status-assigned',

        'in transit' =>
            'notification-status-transit',

        'arrived' =>
            'notification-status-arrived',

        'delivered' =>
            'notification-status-delivered',

        'failed' =>
            'notification-status-failed',

        'cancelled' =>
            'notification-status-cancelled',

        default =>
            'notification-status-default'
    };
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

    <title>Admin Notifications</title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        /* =====================================================
           NOTIFICATIONS PAGE
        ===================================================== */

        .notifications-page {
            width: 100%;
        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .notifications-page .page-header {
            width: 100%;
            margin-bottom: 28px;
        }

        .notification-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }


        /* =====================================================
           SUMMARY CARDS

           Increased spacing so the two cards do not look
           cramped or pushed against each other.
        ===================================================== */

        .notification-summary-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 32px;
            margin: 0 0 32px;
        }

        .notification-stat-card {
            position: relative;
            width: 100%;
            min-width: 0;
            min-height: 142px;

            padding: 28px 32px;

            box-sizing: border-box;

            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);

            background: var(--color-surface);
            box-shadow: var(--shadow-card);

            overflow: hidden;
        }

        .notification-stat-card.unread {
            border-left: 4px solid var(--color-accent);
            padding-left: 29px;
        }

        .notification-stat-content {
            width: 100%;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 28px;
        }

        .notification-stat-text {
            flex: 1;
            min-width: 0;
        }

        .notification-stat-label {
            display: block;

            margin: 0 0 9px;

            color: var(--color-text-secondary);

            font-size: 13px;
            line-height: 1.4;
            font-weight: 700;
        }

        .notification-stat-value {
            display: block;

            margin: 0;

            color: var(--color-text-primary);

            font-size: 32px;
            line-height: 1.1;
            font-weight: 800;
        }

        .notification-stat-description {
            margin-top: 9px;

            color: var(--color-text-muted);

            font-size: 12px;
            line-height: 1.5;
        }

        .notification-stat-icon {
            width: 54px;
            height: 54px;

            flex: 0 0 54px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: var(--color-accent-soft);
            color: var(--color-accent);

            font-size: 23px;
            font-weight: 800;
        }


        /* =====================================================
           MAIN NOTIFICATION CARD
        ===================================================== */

        .notifications-card {
            width: 100%;

            overflow: hidden;

            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);

            background: var(--color-surface);
            box-shadow: var(--shadow-card);
        }

        .notifications-card-header {
            width: 100%;

            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 24px;

            padding: 26px 30px;

            box-sizing: border-box;

            border-bottom: 1px solid var(--color-border);
        }

        .notifications-card-title {
            margin: 0;

            color: var(--color-text-primary);

            font-size: 20px;
            line-height: 1.3;
            font-weight: 800;
        }

        .notifications-card-subtitle {
            margin: 6px 0 0;

            color: var(--color-text-secondary);

            font-size: 13px;
            line-height: 1.5;
        }


        /* =====================================================
           NOTIFICATION LIST
        ===================================================== */

        .notification-list {
            width: 100%;
        }

        .notification-item {
            position: relative;

            width: 100%;

            display: flex;
            align-items: flex-start;

            gap: 20px;

            padding: 24px 30px;

            box-sizing: border-box;

            border-bottom: 1px solid var(--color-border);

            background: var(--color-surface);

            transition: background 0.15s ease;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--color-surface-alt);
        }

        .notification-item.unread {
            background: var(--color-accent-soft);
        }

        .notification-item.unread:hover {
            background: var(--color-accent-soft);
        }


        /* =====================================================
           NOTIFICATION ICON
        ===================================================== */

        .notification-icon {
            width: 48px;
            height: 48px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex: 0 0 48px;

            border-radius: 50%;

            background: var(--color-surface-alt);

            border: 1px solid var(--color-border);

            color: var(--color-accent);

            font-size: 18px;
            font-weight: 800;
        }

        .notification-item.unread .notification-icon {
            background: var(--color-accent);

            border-color: var(--color-accent);

            color: #ffffff;
        }


        /* =====================================================
           NOTIFICATION CONTENT
        ===================================================== */

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-topline {
            display: flex;
            align-items: center;
            flex-wrap: wrap;

            gap: 8px;

            margin-bottom: 10px;
        }

        .notification-type {
            display: inline-flex;
            align-items: center;

            padding: 5px 10px;

            border-radius: 999px;

            font-size: 10px;
            line-height: 1;
            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 0.04em;
        }

        .notification-type-default {
            background: var(--color-surface-alt);
            color: var(--color-text-secondary);
        }

        .notification-type-delivery {
            background: rgba(59, 130, 246, 0.10);
            color: var(--color-info);
        }

        .notification-type-order {
            background: var(--color-accent-soft);
            color: var(--color-accent);
        }

        .notification-type-success {
            background: rgba(22, 163, 74, 0.10);
            color: var(--color-success);
        }

        .notification-type-danger {
            background: rgba(220, 38, 38, 0.10);
            color: var(--color-danger);
        }

        .notification-type-warning {
            background: rgba(245, 158, 11, 0.12);
            color: var(--color-warning);
        }


        /* =====================================================
           NEW BADGE
        ===================================================== */

        .notification-new {
            display: inline-flex;
            align-items: center;

            gap: 6px;

            padding: 6px 10px;

            border-radius: 999px;

            background: rgba(220, 38, 38, 0.10);

            color: var(--color-danger);

            font-size: 10px;
            line-height: 1;
            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 0.04em;
        }

        .notification-new::before {
            content: "";

            width: 6px;
            height: 6px;

            flex: 0 0 6px;

            border-radius: 50%;

            background: currentColor;
        }


        /* =====================================================
           MESSAGE
        ===================================================== */

        .notification-message {
            margin: 0 0 11px;

            color: var(--color-text-primary);

            font-size: 14px;
            line-height: 1.65;

            overflow-wrap: anywhere;
        }

        .notification-item.unread .notification-message {
            font-weight: 650;
        }


        /* =====================================================
           ORDER INFORMATION
        ===================================================== */

        .notification-order {
            display: flex;
            align-items: center;
            flex-wrap: wrap;

            gap: 7px;

            margin-top: 9px;

            color: var(--color-text-secondary);

            font-size: 12px;
            line-height: 1.5;
        }

        .notification-order-label {
            color: var(--color-text-muted);

            font-weight: 600;
        }

        .notification-order-number {
            display: inline-flex;
            align-items: center;

            padding: 4px 9px;

            border-radius: var(--radius-sm);

            background: var(--color-surface-alt);

            border: 1px solid var(--color-border);

            color: var(--color-text-primary);

            font-family: monospace;

            font-size: 11px;
            font-weight: 800;
        }

        .notification-customer {
            color: var(--color-text-secondary);

            font-weight: 650;
        }


        /* =====================================================
           DELIVERY STATUS
        ===================================================== */

        .notification-delivery-status {
            display: inline-flex;
            align-items: center;

            padding: 4px 9px;

            border-radius: 999px;

            font-size: 10px;
            line-height: 1;
            font-weight: 800;
        }

        .notification-status-pending {
            background: rgba(245, 158, 11, 0.10);
            color: var(--color-warning);
        }

        .notification-status-assigned {
            background: rgba(59, 130, 246, 0.10);
            color: var(--color-info);
        }

        .notification-status-transit {
            background: rgba(59, 130, 246, 0.10);
            color: var(--color-info);
        }

        .notification-status-arrived {
            background: rgba(139, 92, 246, 0.10);
            color: #7c3aed;
        }

        .notification-status-delivered {
            background: rgba(22, 163, 74, 0.10);
            color: var(--color-success);
        }

        .notification-status-failed {
            background: rgba(220, 38, 38, 0.10);
            color: var(--color-danger);
        }

        .notification-status-cancelled {
            background: rgba(220, 38, 38, 0.10);
            color: var(--color-danger);
        }

        .notification-status-default {
            background: var(--color-surface-alt);
            color: var(--color-text-secondary);
        }


        /* =====================================================
           DATE
        ===================================================== */

        .notification-date {
            margin-top: 10px;

            color: var(--color-text-muted);

            font-size: 11px;
            line-height: 1.5;
        }


        /* =====================================================
           ACTION
        ===================================================== */

        .notification-action {
            display: flex;
            align-items: center;
            justify-content: flex-end;

            flex: 0 0 auto;

            min-width: 145px;

            padding-top: 3px;
        }

        .notification-action .btn-secondary {
            white-space: nowrap;
        }

        .notification-read-label {
            display: inline-flex;
            align-items: center;

            gap: 7px;

            color: var(--color-text-muted);

            font-size: 12px;
            font-weight: 650;
        }

        .notification-read-label::before {
            content: "✓";

            display: flex;
            align-items: center;
            justify-content: center;

            width: 20px;
            height: 20px;

            border-radius: 50%;

            background: var(--color-surface-alt);

            border: 1px solid var(--color-border);

            font-size: 10px;
        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .notification-empty {
            display: flex;
            flex-direction: column;

            align-items: center;
            justify-content: center;

            min-height: 320px;

            padding: 60px 30px;

            text-align: center;
        }

        .notification-empty-icon {
            width: 70px;
            height: 70px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin-bottom: 17px;

            border-radius: 50%;

            background: var(--color-accent-soft);

            color: var(--color-accent);

            font-size: 28px;
        }

        .notification-empty-title {
            margin: 0;

            color: var(--color-text-primary);

            font-size: 17px;
            font-weight: 800;
        }

        .notification-empty-text {
            max-width: 500px;

            margin: 8px 0 0;

            color: var(--color-text-secondary);

            font-size: 13px;
            line-height: 1.6;
        }


        /* =====================================================
           RESPONSIVE - TABLET
        ===================================================== */

        @media (max-width: 1100px) {

            .notification-summary-grid {
                gap: 24px;
            }

            .notification-stat-card {
                padding: 25px 27px;
            }

            .notification-stat-card.unread {
                padding-left: 24px;
            }

            .notification-stat-content {
                gap: 20px;
            }

            .notifications-card-header {
                padding: 24px 26px;
            }

            .notification-item {
                padding: 23px 26px;
            }

            .notification-action {
                min-width: 130px;
            }
        }


        /* =====================================================
           RESPONSIVE - SMALL TABLET
        ===================================================== */

        @media (max-width: 900px) {

            .notification-summary-grid {
                grid-template-columns: 1fr;

                gap: 20px;

                margin-bottom: 26px;
            }

            .notification-stat-card {
                min-height: 132px;
                padding: 24px 26px;
            }

            .notification-stat-card.unread {
                padding-left: 23px;
            }

            .notification-item {
                padding: 21px 22px;

                gap: 17px;
            }

            .notifications-card-header {
                padding: 22px;
            }

            .notification-action {
                min-width: 120px;
            }
        }


        /* =====================================================
           RESPONSIVE - MOBILE
        ===================================================== */

        @media (max-width: 700px) {

            .notifications-page .page-header {
                margin-bottom: 22px;
            }

            .notification-summary-grid {
                gap: 16px;
            }

            .notification-stat-card {
                min-height: 120px;
                padding: 22px;
            }

            .notification-stat-card.unread {
                padding-left: 19px;
            }

            .notification-stat-content {
                gap: 16px;
            }

            .notification-stat-value {
                font-size: 29px;
            }

            .notification-stat-icon {
                width: 48px;
                height: 48px;

                flex-basis: 48px;

                font-size: 20px;
            }

            .notifications-card-header {
                flex-direction: column;
                align-items: stretch;

                gap: 15px;

                padding: 21px;
            }

            .notification-header-actions {
                width: 100%;
            }

            .notification-header-actions .btn-secondary {
                width: 100%;

                text-align: center;
            }

            .notification-item {
                flex-direction: column;

                gap: 13px;

                padding: 20px;
            }

            .notification-icon {
                width: 42px;
                height: 42px;

                flex-basis: 42px;
            }

            .notification-action {
                width: 100%;

                min-width: 0;

                justify-content: flex-start;

                padding-top: 0;
            }
        }


        /* =====================================================
           RESPONSIVE - SMALL MOBILE
        ===================================================== */

        @media (max-width: 480px) {

            .notification-summary-grid {
                gap: 14px;
            }

            .notification-stat-card {
                min-height: 112px;

                padding: 19px;
            }

            .notification-stat-card.unread {
                padding-left: 16px;
            }

            .notification-stat-label {
                font-size: 12px;
            }

            .notification-stat-value {
                font-size: 26px;
            }

            .notification-stat-description {
                font-size: 11px;
            }

            .notification-stat-icon {
                width: 44px;
                height: 44px;

                flex-basis: 44px;

                font-size: 18px;
            }

            .notifications-card-header {
                padding: 19px;
            }

            .notifications-card-title {
                font-size: 18px;
            }

            .notifications-card-subtitle {
                font-size: 12px;
            }

            .notification-item {
                padding: 17px;
            }

            .notification-message {
                font-size: 13px;
            }
        }

    </style>

</head>


<body>

<?php

/*
|--------------------------------------------------------------------------
| SHARED HEADER / SIDEBAR
|--------------------------------------------------------------------------
|
| header.php already creates .app-layout.
| Do not create another .app-layout here.
|
*/

require __DIR__ . '/../includes/header.php';

?>

<main class="app-main">

    <div class="notifications-page">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="page-header">

            <div>

                <h1>
                    Notifications
                </h1>

                <p>
                    View important order and delivery updates.
                </p>

            </div>


            <?php if ($unreadCount > 0): ?>

                <div class="notification-header-actions">

                    <a
                        href="<?= BASE_URL ?>admin/notifications.php?mark_all_read=1"
                        class="btn-secondary"
                    >
                        Mark All as Read
                    </a>

                </div>

            <?php endif; ?>

        </div>


        <!-- =====================================================
             SUMMARY CARDS
        ====================================================== -->

        <div class="notification-summary-grid">


            <!-- =================================================
                 UNREAD NOTIFICATIONS
            ================================================== -->

            <div class="card notification-stat-card unread">

                <div class="notification-stat-content">

                    <div class="notification-stat-text">

                        <span class="notification-stat-label">
                            Unread Notifications
                        </span>

                        <span class="notification-stat-value">
                            <?= $unreadCount ?>
                        </span>

                        <div class="notification-stat-description">

                            <?php if ($unreadCount > 0): ?>

                                Notifications that need your attention.

                            <?php else: ?>

                                You're all caught up.

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="notification-stat-icon">
                        🔔
                    </div>

                </div>

            </div>


            <!-- =================================================
                 TOTAL NOTIFICATIONS
            ================================================== -->

            <div class="card notification-stat-card">

                <div class="notification-stat-content">

                    <div class="notification-stat-text">

                        <span class="notification-stat-label">
                            Total Notifications
                        </span>

                        <span class="notification-stat-value">
                            <?= count($notifications) ?>
                        </span>

                        <div class="notification-stat-description">
                            Latest 100 notifications for this account.
                        </div>

                    </div>


                    <div class="notification-stat-icon">
                        ▤
                    </div>

                </div>

            </div>


        </div>


        <!-- =====================================================
             NOTIFICATION LIST
        ====================================================== -->

        <section class="card notifications-card">


            <!-- =================================================
                 CARD HEADER
            ================================================== -->

            <div class="notifications-card-header">

                <div>

                    <h2 class="notifications-card-title">
                        Recent Notifications
                    </h2>

                    <p class="notifications-card-subtitle">
                        System notifications received by this admin account.
                    </p>

                </div>


                <?php if ($unreadCount > 0): ?>

                    <div class="notification-header-actions">

                        <span class="notification-new">
                            <?= $unreadCount ?>
                            New
                        </span>

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 NOTIFICATION LIST
            ================================================== -->

            <?php if (empty($notifications)): ?>

                <div class="notification-empty">

                    <div class="notification-empty-icon">
                        🔔
                    </div>

                    <h3 class="notification-empty-title">
                        No notifications yet
                    </h3>

                    <p class="notification-empty-text">
                        Important order and delivery updates
                        will appear here when they are received.
                    </p>

                </div>

            <?php else: ?>

                <div class="notification-list">


                    <?php foreach ($notifications as $notification): ?>

                        <?php

                        $isUnread =
                            (int) $notification['is_read'] === 0;

                        $message =
                            $notification['message'] ?? '';

                        $notificationType =
                            $notification['notification_type']
                            ?? 'In-App';

                        $deliveryStatus =
                            $notification['delivery_status']
                            ?? null;

                        $typeClass =
                            notificationTypeClass(
                                $notificationType
                            );

                        $icon =
                            notificationIcon(
                                $notificationType
                            );

                        ?>


                        <article
                            class="
                                notification-item
                                <?= $isUnread ? 'unread' : '' ?>
                            "
                        >


                            <!-- =================================
                                 ICON
                            ================================== -->

                            <div class="notification-icon">
                                <?= $icon ?>
                            </div>


                            <!-- =================================
                                 CONTENT
                            ================================== -->

                            <div class="notification-content">


                                <!-- =============================
                                     TOPLINE
                                ============================== -->

                                <div class="notification-topline">

                                    <span
                                        class="
                                            notification-type
                                            <?= $typeClass ?>
                                        "
                                    >
                                        <?= htmlspecialchars(
                                            $notificationType
                                        ) ?>
                                    </span>


                                    <?php if ($isUnread): ?>

                                        <span class="notification-new">
                                            New
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- =============================
                                     MESSAGE
                                ============================== -->

                                <div class="notification-message">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $message
                                        )
                                    ) ?>

                                </div>


                                <!-- =============================
                                     ORDER INFORMATION
                                ============================== -->

                                <?php if (
                                    !empty(
                                        $notification['order_number']
                                    )
                                ): ?>

                                    <div class="notification-order">

                                        <span
                                            class="
                                                notification-order-label
                                            "
                                        >
                                            Order
                                        </span>


                                        <span
                                            class="
                                                notification-order-number
                                            "
                                        >
                                            <?= htmlspecialchars(
                                                $notification['order_number']
                                            ) ?>
                                        </span>


                                        <?php if (
                                            !empty(
                                                $notification['customer_name']
                                            )
                                        ): ?>

                                            <span>
                                                —
                                            </span>

                                            <span
                                                class="
                                                    notification-customer
                                                "
                                            >
                                                <?= htmlspecialchars(
                                                    $notification['customer_name']
                                                ) ?>
                                            </span>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty($deliveryStatus)
                                        ): ?>

                                            <span>
                                                •
                                            </span>

                                            <span
                                                class="
                                                    notification-delivery-status
                                                    <?= deliveryStatusClass(
                                                        $deliveryStatus
                                                    ) ?>
                                                "
                                            >
                                                <?= htmlspecialchars(
                                                    $deliveryStatus
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>


                                <!-- =============================
                                     DATE
                                ============================== -->

                                <div class="notification-date">

                                    <?php if (
                                        !empty(
                                            $notification['sent_at']
                                        )
                                    ): ?>

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $notification['sent_at']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </div>


                            </div>


                            <!-- =================================
                                 ACTION
                            ================================== -->

                            <div class="notification-action">

                                <?php if ($isUnread): ?>

                                    <a
                                        href="<?= BASE_URL ?>admin/notifications.php?read=<?= (int) $notification['notification_id'] ?>"
                                        class="btn-secondary"
                                    >
                                        Mark as Read
                                    </a>

                                <?php else: ?>

                                    <span class="notification-read-label">
                                        Read
                                    </span>

                                <?php endif; ?>

                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>

            <?php endif; ?>


        </section>


    </div>

</main>


</body>

</html>