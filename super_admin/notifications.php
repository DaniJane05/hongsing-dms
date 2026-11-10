<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

$superAdminId = currentUserId();

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| MARK SINGLE NOTIFICATION AS READ
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_read'])
) {

    $notificationId =
        isset($_POST['notification_id'])
            ? (int) $_POST['notification_id']
            : 0;


    if ($notificationId > 0) {

        $stmt = $db->prepare("
            UPDATE notification

            SET
                is_read = 1,
                read_at = NOW()

            WHERE notification_id = ?

              AND recipient_role = 'Super Admin'

              AND recipient_id = ?
        ");


        $stmt->execute([
            $notificationId,
            $superAdminId
        ]);

    }


    header(
        'Location: ' .
        BASE_URL .
        'super_admin/notifications.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MARK ALL AS READ
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_all_read'])
) {

    $stmt = $db->prepare("
        UPDATE notification

        SET
            is_read = 1,
            read_at = NOW()

        WHERE recipient_role = 'Super Admin'

          AND recipient_id = ?

          AND is_read = 0
    ");


    $stmt->execute([
        $superAdminId
    ]);


    header(
        'Location: ' .
        BASE_URL .
        'super_admin/notifications.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$filter =
    $_GET['filter']
    ?? 'all';


$allowedFilters = [
    'all',
    'unread',
    'read'
];


if (
    !in_array(
        $filter,
        $allowedFilters,
        true
    )
) {

    $filter = 'all';

}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$countStmt = $db->prepare("
    SELECT

        COUNT(*) AS total_count,

        SUM(
            CASE
                WHEN is_read = 0
                THEN 1
                ELSE 0
            END
        ) AS unread_count,

        SUM(
            CASE
                WHEN is_read = 1
                THEN 1
                ELSE 0
            END
        ) AS read_count

    FROM notification

    WHERE recipient_role = 'Super Admin'

      AND recipient_id = ?
");


$countStmt->execute([
    $superAdminId
]);


$counts =
    $countStmt->fetch(
        PDO::FETCH_ASSOC
    );


$totalCount =
    (int) (
        $counts['total_count']
        ?? 0
    );


$unreadCount =
    (int) (
        $counts['unread_count']
        ?? 0
    );


$readCount =
    (int) (
        $counts['read_count']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| GET NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        n.notification_id,
        n.order_id,
        n.status_id,
        n.notification_type,
        n.recipient_role,
        n.recipient_id,
        n.recipient_phone,
        n.is_read,
        n.read_at,
        n.sent_at,
        n.delivery_status,
        n.message,

        o.order_number,
        o.completion_status,
        o.priority_level,

        c.full_name AS customer_name,

        d.full_name AS driver_name

    FROM notification n

    LEFT JOIN `order` o
        ON o.order_id = n.order_id

    LEFT JOIN customer c
        ON c.customer_id = o.customer_id

    LEFT JOIN driver d
        ON d.driver_id = o.driver_id

    WHERE n.recipient_role = 'Super Admin'

      AND n.recipient_id = ?
";


$params = [
    $superAdminId
];


if ($filter === 'unread') {

    $sql .= "
        AND n.is_read = 0
    ";

} elseif ($filter === 'read') {

    $sql .= "
        AND n.is_read = 1
    ";

}


$sql .= "
    ORDER BY
        n.sent_at DESC,
        n.notification_id DESC
";


$stmt = $db->prepare($sql);

$stmt->execute($params);


$notifications =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
|
| Adjust the URLs here if your current Super Admin file names
| are different.
|
*/

$navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL .
            'super_admin/dashboard.php'
    ],

    [
        'label' => 'Users',
        'url' =>
            BASE_URL .
            'super_admin/users.php'
    ],

    [
        'label' => 'Fleet',
        'url' =>
            BASE_URL .
            'super_admin/fleet.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL .
            'super_admin/notifications.php'
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


    <?php
    require __DIR__ .
        '/../includes/theme_init.php';
    ?>


    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        .notifications-page {

            max-width: 1150px;

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

            flex-wrap: wrap;

            margin-bottom: 22px;

        }


        .notifications-header h1 {

            margin: 0 0 5px;

        }


        .notifications-header p {

            margin: 0;

            color:
                var(--color-text-secondary);

        }


        /*
        |--------------------------------------------------------------------------
        | MARK ALL
        |--------------------------------------------------------------------------
        */

        .mark-all-button {

            border: none;

            border-radius: 7px;

            padding: 10px 15px;

            background:
                var(--color-accent);

            color: #ffffff;

            font-weight: 600;

            cursor: pointer;

        }


        .mark-all-button:disabled {

            opacity: .55;

            cursor: not-allowed;

        }


        /*
        |--------------------------------------------------------------------------
        | STATISTICS
        |--------------------------------------------------------------------------
        */

        .notification-stats {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 15px;

            margin-bottom: 20px;

        }


        .notification-stat {

            padding: 18px;

        }


        .notification-stat-label {

            display: block;

            font-size: 12px;

            color:
                var(--color-text-secondary);

            margin-bottom: 7px;

        }


        .notification-stat-value {

            font-size: 25px;

            font-weight: 700;

        }


        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        .notification-filters {

            display: flex;

            gap: 8px;

            flex-wrap: wrap;

            margin-bottom: 18px;

        }


        .filter-link {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 8px 13px;

            border:
                1px solid
                var(--color-border);

            border-radius: 7px;

            background:
                var(--color-surface);

            color:
                var(--color-text-primary);

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

        }


        .filter-link.active {

            background:
                var(--color-accent);

            border-color:
                var(--color-accent);

            color: #ffffff;

        }


        .filter-count {

            display: inline-flex;

            min-width: 20px;

            height: 20px;

            padding: 0 5px;

            align-items: center;

            justify-content: center;

            border-radius: 999px;

            background:
                rgba(127,127,127,.15);

            font-size: 11px;

        }


        .filter-link.active
        .filter-count {

            background:
                rgba(255,255,255,.20);

        }


        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION LIST
        |--------------------------------------------------------------------------
        */

        .notification-list {

            display: flex;

            flex-direction: column;

            gap: 11px;

        }


        .notification-item {

            position: relative;

            display: grid;

            grid-template-columns:
                auto
                minmax(0, 1fr)
                auto;

            gap: 14px;

            align-items: flex-start;

            padding: 17px;

            border:
                1px solid
                var(--color-border);

            border-radius: 10px;

            background:
                var(--color-surface);

        }


        .notification-item.unread {

            border-left:
                4px solid
                var(--color-accent);

            background:
                rgba(59,130,246,.045);

        }


        /*
        |--------------------------------------------------------------------------
        | ICON
        |--------------------------------------------------------------------------
        */

        .notification-icon {

            width: 42px;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            border-radius: 50%;

            background:
                rgba(59,130,246,.10);

            font-size: 19px;

        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .notification-message {

            font-size: 14px;

            line-height: 1.55;

            color:
                var(--color-text-primary);

        }


        .notification-item.unread
        .notification-message {

            font-weight: 600;

        }


        .notification-meta {

            display: flex;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;

            margin-top: 8px;

            font-size: 11px;

            color:
                var(--color-text-muted);

        }


        .notification-order {

            font-weight: 600;

            color:
                var(--color-accent);

            text-decoration: none;

        }


        .notification-order:hover {

            text-decoration: underline;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS TAG
        |--------------------------------------------------------------------------
        */

        .notification-status {

            display: inline-flex;

            align-items: center;

            padding: 3px 7px;

            border-radius: 999px;

            background:
                rgba(127,127,127,.10);

            font-size: 10px;

            font-weight: 600;

        }


        /*
        |--------------------------------------------------------------------------
        | UNREAD DOT
        |--------------------------------------------------------------------------
        */

        .unread-dot {

            width: 9px;

            height: 9px;

            border-radius: 50%;

            background:
                var(--color-accent);

            margin-top: 8px;

        }


        /*
        |--------------------------------------------------------------------------
        | READ BUTTON
        |--------------------------------------------------------------------------
        */

        .read-button {

            border:
                1px solid
                var(--color-border);

            border-radius: 6px;

            background:
                var(--color-surface);

            color:
                var(--color-text-primary);

            padding: 7px 10px;

            font-size: 11px;

            font-weight: 600;

            cursor: pointer;

            white-space: nowrap;

        }


        .read-button:hover {

            border-color:
                var(--color-accent);

        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .notifications-empty {

            padding: 55px 20px;

            text-align: center;

        }


        .notifications-empty-icon {

            font-size: 45px;

            margin-bottom: 12px;

        }


        .notifications-empty h3 {

            margin:
                0
                0
                6px;

        }


        .notifications-empty p {

            margin: 0;

            color:
                var(--color-text-secondary);

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 700px
        ) {

            .notification-stats {

                grid-template-columns: 1fr;

            }


            .notification-item {

                grid-template-columns:
                    auto
                    minmax(0, 1fr);

            }


            .notification-actions {

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


        <div class="notifications-page">


            <!-- ==================================================
                 HEADER
            ================================================== -->

            <div class="notifications-header">


                <div>

                    <h1>
                        Notifications
                    </h1>

                    <p>
                        Monitor important system and delivery
                        activities across the organization.
                    </p>

                </div>


                <form method="POST">

                    <button
                        type="submit"
                        name="mark_all_read"
                        class="mark-all-button"
                        <?= $unreadCount <= 0
                            ? 'disabled'
                            : ''
                        ?>
                    >
                        Mark All as Read
                    </button>

                </form>


            </div>


            <!-- ==================================================
                 STATISTICS
            ================================================== -->

            <div class="notification-stats">


                <div class="card notification-stat">

                    <span
                        class="notification-stat-label"
                    >
                        Total Notifications
                    </span>

                    <div
                        class="notification-stat-value"
                    >
                        <?= $totalCount ?>
                    </div>

                </div>


                <div class="card notification-stat">

                    <span
                        class="notification-stat-label"
                    >
                        Unread
                    </span>

                    <div
                        class="notification-stat-value"
                    >
                        <?= $unreadCount ?>
                    </div>

                </div>


                <div class="card notification-stat">

                    <span
                        class="notification-stat-label"
                    >
                        Read
                    </span>

                    <div
                        class="notification-stat-value"
                    >
                        <?= $readCount ?>
                    </div>

                </div>


            </div>


            <!-- ==================================================
                 FILTERS
            ================================================== -->

            <div class="notification-filters">


                <a
                    href="<?= BASE_URL ?>super_admin/notifications.php?filter=all"
                    class="
                        filter-link
                        <?= $filter === 'all'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    All

                    <span class="filter-count">
                        <?= $totalCount ?>
                    </span>

                </a>


                <a
                    href="<?= BASE_URL ?>super_admin/notifications.php?filter=unread"
                    class="
                        filter-link
                        <?= $filter === 'unread'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    Unread

                    <span class="filter-count">
                        <?= $unreadCount ?>
                    </span>

                </a>


                <a
                    href="<?= BASE_URL ?>super_admin/notifications.php?filter=read"
                    class="
                        filter-link
                        <?= $filter === 'read'
                            ? 'active'
                            : ''
                        ?>
                    "
                >

                    Read

                    <span class="filter-count">
                        <?= $readCount ?>
                    </span>

                </a>


            </div>


            <!-- ==================================================
                 NOTIFICATIONS
            ================================================== -->

            <div class="card">


                <?php if (
                    empty($notifications)
                ): ?>


                    <div class="notifications-empty">

                        <div
                            class="notifications-empty-icon"
                        >
                            🔔
                        </div>

                        <h3>
                            No notifications
                        </h3>

                        <p>

                            <?php if (
                                $filter === 'unread'
                            ): ?>

                                You currently have no unread
                                notifications.

                            <?php elseif (
                                $filter === 'read'
                            ): ?>

                                You currently have no read
                                notifications.

                            <?php else: ?>

                                Important system activities will
                                appear here.

                            <?php endif; ?>

                        </p>

                    </div>


                <?php else: ?>


                    <div class="notification-list">


                        <?php foreach (
                            $notifications
                            as $notification
                        ): ?>


                            <?php

                            /*
                            |--------------------------------------------------------------------------
                            | ICON
                            |--------------------------------------------------------------------------
                            */

                            $notificationMessage =
                                strtolower(
                                    $notification['message']
                                    ?? ''
                                );


                            $icon = '🔔';


                            if (
                                str_contains(
                                    $notificationMessage,
                                    'new order'
                                )
                            ) {

                                $icon = '📦';

                            } elseif (
                                str_contains(
                                    $notificationMessage,
                                    'assigned'
                                )
                            ) {

                                $icon = '🚚';

                            } elseif (
                                str_contains(
                                    $notificationMessage,
                                    'accepted'
                                )
                            ) {

                                $icon = '✓';

                            } elseif (
                                str_contains(
                                    $notificationMessage,
                                    'in transit'
                                ) ||
                                str_contains(
                                    $notificationMessage,
                                    'started'
                                )
                            ) {

                                $icon = '📍';

                            } elseif (
                                str_contains(
                                    $notificationMessage,
                                    'completed'
                                ) ||
                                str_contains(
                                    $notificationMessage,
                                    'delivered'
                                )
                            ) {

                                $icon = '✅';

                            } elseif (
                                str_contains(
                                    $notificationMessage,
                                    'cancelled'
                                ) ||
                                str_contains(
                                    $notificationMessage,
                                    'failed'
                                )
                            ) {

                                $icon = '⚠️';

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | ORDER LINK
                            |--------------------------------------------------------------------------
                            |
                            | Change this URL later if your
                            | Super Admin order details page
                            | uses another file name.
                            |
                            */

                            $orderUrl = '';

                            if (
                                !empty(
                                    $notification['order_id']
                                )
                            ) {

                                $orderUrl =
                                    BASE_URL .
                                    'super_admin/order_details.php?id=' .
                                    (int)
                                    $notification['order_id'];

                            }

                            ?>


                            <div
                                class="
                                    notification-item
                                    <?= (int)
                                        $notification['is_read']
                                        === 0
                                            ? 'unread'
                                            : ''
                                    ?>
                                "
                            >


                                <div
                                    class="notification-icon"
                                >
                                    <?= $icon ?>
                                </div>


                                <div>


                                    <div
                                        class="notification-message"
                                    >

                                        <?= htmlspecialchars(
                                            $notification[
                                                'message'
                                            ]
                                        ) ?>

                                    </div>


                                    <div
                                        class="notification-meta"
                                    >


                                        <?php if (
                                            !empty(
                                                $notification[
                                                    'order_number'
                                                ]
                                            )
                                        ): ?>


                                            <?php if (
                                                $orderUrl !== ''
                                            ): ?>

                                                <a
                                                    href="<?= htmlspecialchars(
                                                        $orderUrl
                                                    ) ?>"
                                                    class="notification-order"
                                                >

                                                    <?= htmlspecialchars(
                                                        $notification[
                                                            'order_number'
                                                        ]
                                                    ) ?>

                                                </a>

                                            <?php else: ?>

                                                <span
                                                    class="notification-order"
                                                >

                                                    <?= htmlspecialchars(
                                                        $notification[
                                                            'order_number'
                                                        ]
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $notification[
                                                    'completion_status'
                                                ]
                                            )
                                        ): ?>

                                            <span
                                                class="notification-status"
                                            >

                                                <?= htmlspecialchars(
                                                    $notification[
                                                        'completion_status'
                                                    ]
                                                ) ?>

                                            </span>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $notification[
                                                    'customer_name'
                                                ]
                                            )
                                        ): ?>

                                            <span>

                                                Customer:

                                                <?= htmlspecialchars(
                                                    $notification[
                                                        'customer_name'
                                                    ]
                                                ) ?>

                                            </span>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $notification[
                                                    'driver_name'
                                                ]
                                            )
                                        ): ?>

                                            <span>

                                                Driver:

                                                <?= htmlspecialchars(
                                                    $notification[
                                                        'driver_name'
                                                    ]
                                                ) ?>

                                            </span>

                                        <?php endif; ?>


                                        <span>

                                            <?= !empty(
                                                $notification[
                                                    'sent_at'
                                                ]
                                            )
                                                ? date(
                                                    'M d, Y h:i A',
                                                    strtotime(
                                                        $notification[
                                                            'sent_at'
                                                        ]
                                                    )
                                                )
                                                : '—'
                                            ?>

                                        </span>


                                    </div>


                                </div>


                                <div
                                    class="notification-actions"
                                >


                                    <?php if (
                                        (int)
                                        $notification['is_read']
                                        === 0
                                    ): ?>


                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="notification_id"
                                                value="<?= (int)
                                                    $notification[
                                                        'notification_id'
                                                    ]
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="mark_read"
                                                class="read-button"
                                            >
                                                Mark as Read
                                            </button>

                                        </form>


                                    <?php else: ?>


                                        <span
                                            style="
                                                font-size:11px;
                                                color:
                                                var(--color-text-muted);
                                            "
                                        >
                                            Read
                                        </span>


                                    <?php endif; ?>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php endif; ?>


            </div>


        </div>


    </main>


</div>


</body>

</html>