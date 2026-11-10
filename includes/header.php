<?php

/*
|--------------------------------------------------------------------------
| SHARED HEADER / SIDEBAR
|--------------------------------------------------------------------------
| Hong Sing LKRR Commercial
|
| Customer and Admin navigation are handled here so their pages
| always use the same sidebar structure.
|--------------------------------------------------------------------------
*/

$currentPage = basename($_SERVER['PHP_SELF']);


/*
|--------------------------------------------------------------------------
| CUSTOMER NAVIGATION
|--------------------------------------------------------------------------
*/

$customerNavItems = [
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

$accountPages = [
    [
        'label' => 'Profile',
        'url'   => BASE_URL . 'customer/profile.php'
    ]
];


/*
|--------------------------------------------------------------------------
| ADMIN NAVIGATION
|--------------------------------------------------------------------------
|
| The Admin sidebar is organized by the main functions of the system.
|
*/

$adminNavItems = [
    [
        'label' => 'Dashboard',
        'url'   => BASE_URL . 'admin/dashboard.php'
    ],
    [
        'label' => 'Orders',
        'url'   => BASE_URL . 'admin/orders.php'
    ],
    [
        'label' => 'Route Optimization',
        'url'   => BASE_URL . 'admin/route_optimization.php'
    ],
    [
        'label' => 'Delivery Incidents',
        'url'   => BASE_URL . 'admin/delivery_incidents.php'
    ],
    [
        'label' => 'Delay Analytics',
        'url'   => BASE_URL . 'admin/delay_analytics.php'
    ],
    [
        'label' => 'Products',
        'url'   => BASE_URL . 'admin/products.php'
    ],
    [
        'label' => 'Notifications',
        'url'   => BASE_URL . 'admin/notifications.php'
    ],
    [
        'label' => 'Customer Feedback',
        'url'   => BASE_URL . 'admin/feedback.php'
    ],
    [
        'label' => 'Profile',
        'url'   => BASE_URL . 'admin/profile.php'
    ]
];


/*
|--------------------------------------------------------------------------
| OTHER USER ROLES
|--------------------------------------------------------------------------
*/

$navItems = $navItems ?? [];

?>

<!-- Load the main application stylesheet -->
<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>


<!--
|--------------------------------------------------------------------------
| APPLICATION LAYOUT
|--------------------------------------------------------------------------
-->

<div class="app-layout">


    <!--
    |--------------------------------------------------------------------------
    | SIDEBAR
    |--------------------------------------------------------------------------
    -->

    <aside class="app-sidebar">


        <!-- ==========================================================
             BRAND
        =========================================================== -->

        <div class="sidebar-brand">

            <img
                src="<?= BASE_URL ?>assets/images/logo.jpg"
                alt="Hong Sing LKRR"
                class="sidebar-logo"
            >

            <div class="sidebar-brand-text">

                <strong>
                    Hong Sing LKRR
                </strong>

                <span>
                    Commercial
                </span>

            </div>

        </div>


        <!-- ==========================================================
             USER INFORMATION
        =========================================================== -->

        <div class="sidebar-user">

            <span class="sidebar-role">
                <?= htmlspecialchars(
                    currentRole() ?? ''
                ) ?>
            </span>

            <span class="sidebar-name">
                <?= htmlspecialchars(
                    $_SESSION['full_name'] ?? ''
                ) ?>
            </span>

        </div>


        <!-- ==========================================================
             NAVIGATION
        =========================================================== -->

        <nav class="sidebar-nav">


            <?php if (
                function_exists('currentRole') &&
                currentRole() === ROLE_CUSTOMER
            ): ?>


                <!-- ==================================================
                     CUSTOMER NAVIGATION
                =================================================== -->

                <?php foreach ($customerNavItems as $item): ?>

                    <?php

                    $isActive =
                        $currentPage === basename($item['url']);

                    ?>

                    <a
                        href="<?= htmlspecialchars($item['url']) ?>"
                        class="sidebar-link <?= $isActive ? 'active' : '' ?>"
                    >

                        <?= htmlspecialchars(
                            $item['label']
                        ) ?>

                    </a>

                <?php endforeach; ?>


                <!-- ==================================================
                     CUSTOMER ACCOUNT
                =================================================== -->

                <?php

                $accountIsActive = (
                    $currentPage === 'profile.php'
                );

                ?>

                <div
                    class="sidebar-account <?= $accountIsActive ? 'active-parent' : '' ?>"
                >

                    <div class="sidebar-account-label">

                        <span>
                            Account
                        </span>

                        <span class="account-arrow">
                            ▾
                        </span>

                    </div>


                    <div class="sidebar-subnav">

                        <?php foreach ($accountPages as $item): ?>

                            <?php

                            $isActive =
                                $currentPage === basename($item['url']);

                            ?>

                            <a
                                href="<?= htmlspecialchars($item['url']) ?>"
                                class="sidebar-sublink <?= $isActive ? 'active' : '' ?>"
                            >

                                <?= htmlspecialchars(
                                    $item['label']
                                ) ?>

                            </a>

                        <?php endforeach; ?>

                    </div>

                </div>


            <?php elseif (
                function_exists('currentRole') &&
                currentRole() === ROLE_ADMIN
            ): ?>


                <!-- ==================================================
                     ADMIN NAVIGATION
                =================================================== -->

                <?php foreach ($adminNavItems as $item): ?>

                    <?php

                    $isActive =
                        $currentPage === basename($item['url']);

                    ?>

                    <a
                        href="<?= htmlspecialchars($item['url']) ?>"
                        class="sidebar-link <?= $isActive ? 'active' : '' ?>"
                    >

                        <?= htmlspecialchars(
                            $item['label']
                        ) ?>

                    </a>

                <?php endforeach; ?>


            <?php else: ?>


                <!-- ==================================================
                     OTHER USER ROLES
                =================================================== -->

                <?php foreach ($navItems as $item): ?>

                    <?php

                    $isActive =
                        $currentPage === basename($item['url']);

                    ?>

                    <a
                        href="<?= htmlspecialchars($item['url']) ?>"
                        class="sidebar-link <?= $isActive ? 'active' : '' ?>"
                    >

                        <?= htmlspecialchars(
                            $item['label']
                        ) ?>

                    </a>

                <?php endforeach; ?>


            <?php endif; ?>

        </nav>


        <!-- ==========================================================
             SIDEBAR BOTTOM
        =========================================================== -->

        <div class="sidebar-bottom">

            <a
                href="<?= BASE_URL ?>auth/logout.php"
                class="sidebar-logout"
            >
                Log out
            </a>

        </div>

        


    </aside>