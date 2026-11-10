<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

/*
|--------------------------------------------------------------------------
| SESSION CART
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = $_SESSION['cart_message'] ?? '';
$error   = $_SESSION['cart_error'] ?? '';

unset(
    $_SESSION['cart_message'],
    $_SESSION['cart_error']
);

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| ADD TO CART
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_to_cart'])
) {
    $productId = filter_input(
        INPUT_POST,
        'product_id',
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );

    if (!$productId || !$quantity || $quantity < 1) {
        $error = 'Invalid product or quantity.';
    } else {
        try {
            $stmt = $db->prepare("
                SELECT
                    product_id,
                    product_code,
                    product_name,
                    description,
                    price,
                    weight_kg,
                    stock_quantity,
                    status,
                    product_image
                FROM product
                WHERE product_id = ?
                  AND status = 'Available'
                LIMIT 1
            ");

            $stmt->execute([$productId]);

            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $error = 'Product is no longer available.';
            } elseif ((int)$product['stock_quantity'] <= 0) {
                $error = 'This product is currently out of stock.';
            } elseif (
                $quantity > (int)$product['stock_quantity']
            ) {
                $error = 'Requested quantity exceeds available stock.';
            } else {

                $existingQuantity =
                    (int)(
                        $_SESSION['cart'][$productId]['quantity']
                        ?? 0
                    );

                $newQuantity =
                    $existingQuantity + $quantity;

                if (
                    $newQuantity >
                    (int)$product['stock_quantity']
                ) {
                    $error =
                        'You cannot add more than the available stock.';
                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | SAVE TO SESSION CART
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['cart'][$productId] = [
                        'product_id'     => (int)$product['product_id'],
                        'product_code'   => $product['product_code'],
                        'product_name'   => $product['product_name'],
                        'description'    => $product['description'],
                        'price'          => (float)$product['price'],
                        'weight_kg'      => (float)$product['weight_kg'],
                        'stock_quantity' => (int)$product['stock_quantity'],
                        'product_image'  => $product['product_image'],
                        'quantity'       => $newQuantity
                    ];

                    $_SESSION['cart_message'] =
                        'Product added to cart successfully.';

                    /*
                    |--------------------------------------------------------------------------
                    | KEEP SEARCH AFTER ADDING
                    |--------------------------------------------------------------------------
                    */

                    $redirectUrl =
                        BASE_URL .
                        'customer/products.php';

                    if ($search !== '') {
                        $redirectUrl .=
                            '?search=' .
                            urlencode($search);
                    }

                    header(
                        'Location: ' .
                        $redirectUrl
                    );

                    exit;
                }
            }
        } catch (PDOException $e) {
            $error =
                'Unable to add product to cart.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET PRODUCTS
|--------------------------------------------------------------------------
*/

try {

    if ($search !== '') {

        $stmt = $db->prepare("
            SELECT
                product_id,
                product_code,
                product_name,
                description,
                price,
                weight_kg,
                stock_quantity,
                status,
                product_image
            FROM product
            WHERE status = 'Available'
              AND (
                    product_name LIKE ?
                    OR product_code LIKE ?
                    OR description LIKE ?
              )
            ORDER BY product_name ASC
        ");

        $searchTerm =
            '%' . $search . '%';

        $stmt->execute([
            $searchTerm,
            $searchTerm,
            $searchTerm
        ]);

    } else {

        $stmt = $db->query("
            SELECT
                product_id,
                product_code,
                product_name,
                description,
                price,
                weight_kg,
                stock_quantity,
                status,
                product_image
            FROM product
            WHERE status = 'Available'
            ORDER BY product_name ASC
        ");
    }

    $products =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $products = [];

    if ($error === '') {
        $error =
            'Unable to load products.';
    }
}

/*
|--------------------------------------------------------------------------
| CART COUNT
|--------------------------------------------------------------------------
*/

$cartCount = 0;

foreach ($_SESSION['cart'] as $cartItem) {

    $cartCount +=
        (int)(
            $cartItem['quantity']
            ?? 0
        );
}

/*
|--------------------------------------------------------------------------
| CUSTOMER NAVIGATION
|--------------------------------------------------------------------------
|
| The shared header handles the actual customer sidebar.
| This remains here for compatibility.
|
*/

$navItems = [
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

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Products';

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
        Products · Customer · Hong Sing LKRR Commercial
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        /* ==========================================================
           PRODUCTS PAGE
        ========================================================== */

        .products-page {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }


        /* ==========================================================
           PAGE HEADER
        ========================================================== */

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 24px;
        }

        .products-header h1 {
            margin: 0 0 6px;
            font-size: 28px;
            color: var(--color-text-primary);
        }

        .products-header p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 14px;
        }


        /* ==========================================================
           CART BUTTON
        ========================================================== */

        .cart-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 16px;
            border-radius: 8px;
            background: var(--color-accent);
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            transition:
                opacity .15s ease,
                transform .15s ease;
        }

        .cart-button:hover {
            opacity: .9;
            transform: translateY(-1px);
        }

        .cart-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            background: white;
            color: var(--color-accent);
            font-size: 12px;
            font-weight: 700;
        }


        /* ==========================================================
           TOOLBAR
        ========================================================== */

        .products-toolbar {
            display: flex;
            align-items: stretch;
            gap: 10px;
            margin-bottom: 22px;
        }

        .search-form {
            display: flex;
            flex: 1;
            max-width: 700px;
        }

        .search-form input {
            flex: 1;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid var(--color-border);
            border-right: none;
            border-radius: 8px 0 0 8px;
            background: var(--color-surface);
            color: var(--color-text-primary);
            font-size: 14px;
            outline: none;
        }

        .search-form input::placeholder {
            color: var(--color-text-muted);
        }

        .search-form input:focus {
            border-color: var(--color-accent);
        }

        .search-button {
            padding: 12px 18px;
            border: none;
            border-radius: 0 8px 8px 0;
            background: var(--color-accent);
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .search-button:hover {
            opacity: .9;
        }

        .clear-search {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            background: var(--color-surface);
            color: var(--color-text-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .clear-search:hover {
            background: var(--color-background);
        }


        /* ==========================================================
           ALERTS
        ========================================================== */

        .products-alert {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .products-alert-success {
            background: rgba(34, 197, 94, .10);
            border: 1px solid rgba(34, 197, 94, .25);
            color: #15803d;
        }

        .products-alert-error {
            background: rgba(239, 68, 68, .10);
            border: 1px solid rgba(239, 68, 68, .25);
            color: #b91c1c;
        }


        /* ==========================================================
           RESULT INFO
        ========================================================== */

        .results-info {
            margin-bottom: 18px;
            color: var(--color-text-secondary);
            font-size: 13px;
        }

        .results-info strong {
            color: var(--color-text-primary);
        }


        /* ==========================================================
           PRODUCT GRID
        ========================================================== */

        .products-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(240px, 1fr)
                );
            gap: 22px;
        }


        /* ==========================================================
           PRODUCT CARD
        ========================================================== */

        .product-card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            box-shadow:
                0 2px 8px rgba(0, 0, 0, .04);
            transition:
                transform .15s ease,
                box-shadow .15s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 8px 24px rgba(0, 0, 0, .08);
        }


        /* ==========================================================
           CLICKABLE PRODUCT AREA
        ========================================================== */

        .product-card-trigger {
            border: none;
            padding: 0;
            margin: 0;
            width: 100%;
            background: transparent;
            color: inherit;
            text-align: left;
            cursor: pointer;
            font: inherit;
        }

        .product-card-trigger:focus-visible {
            outline: 3px solid var(--color-accent);
            outline-offset: -3px;
        }


        /* ==========================================================
           PRODUCT IMAGE
        ========================================================== */

        .product-image-wrapper {
    width: 100%;
    height: 220px;
    background: var(--color-background);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    box-sizing: border-box;
}

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            padding: 18px;
            box-sizing: border-box;
        }

        .product-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: var(--color-text-muted);
            font-size: 14px;
        }


        /* ==========================================================
           PRODUCT CONTENT
        ========================================================== */

        .product-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 18px;
        }

        .product-code {
            margin-bottom: 5px;
            color: var(--color-text-secondary);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .product-name {
            margin-bottom: 8px;
            color: var(--color-text-primary);
            font-size: 18px;
            font-weight: 700;
            line-height: 1.3;
        }

        .product-description {
            margin-bottom: 16px;
            color: var(--color-text-secondary);
            font-size: 13px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 59px;
        }


        /* ==========================================================
           PRICE / WEIGHT
        ========================================================== */

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: auto;
            margin-bottom: 14px;
        }

        .product-price {
            color: var(--color-accent);
            font-size: 19px;
            font-weight: 800;
        }

        .product-weight {
            color: var(--color-text-secondary);
            font-size: 12px;
            white-space: nowrap;
        }


        /* ==========================================================
           STOCK
        ========================================================== */

        .stock-status {
            margin-bottom: 14px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-available {
            color: #15803d;
        }

        .stock-low {
            color: #b45309;
        }

        .stock-out {
            color: #b91c1c;
        }


        /* ==========================================================
           ACTIONS
        ========================================================== */

        .product-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            padding: 0 18px 18px;
        }

        .view-details-btn,
        .add-cart-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            box-sizing: border-box;
            padding: 10px 14px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            transition:
                opacity .15s ease,
                background .15s ease;
        }

        .view-details-btn {
            border: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text-primary);
        }

        .view-details-btn:hover {
            background: var(--color-background);
        }

        .add-cart-btn {
            border: none;
            background: var(--color-accent);
            color: white;
        }

        .add-cart-btn:hover {
            opacity: .9;
        }

        .add-cart-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            opacity: 1;
        }


        /* ==========================================================
           EMPTY STATE
        ========================================================== */

        .empty-products {
            padding: 60px 25px;
            text-align: center;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 12px;
        }

        .empty-products h3 {
            margin: 0 0 8px;
            color: var(--color-text-primary);
            font-size: 18px;
        }

        .empty-products p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 14px;
        }

        .empty-action {
            display: inline-flex;
            margin-top: 16px;
            padding: 10px 15px;
            border-radius: 7px;
            background: var(--color-accent);
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }


        /* ==========================================================
           PRODUCT DETAILS MODAL
        ========================================================== */

        .product-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .product-modal.is-open {
            display: flex;
        }

        .product-modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            backdrop-filter: blur(2px);
        }

        .product-modal-dialog {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 850px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            box-shadow:
                0 20px 60px rgba(0, 0, 0, .25);
            animation: productModalIn .18s ease-out;
        }

        @keyframes productModalIn {
            from {
                opacity: 0;
                transform: translateY(12px) scale(.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .product-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 3;
            width: 38px;
            height: 38px;
            border: 1px solid var(--color-border);
            border-radius: 50%;
            background: var(--color-surface);
            color: var(--color-text-primary);
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-modal-close:hover {
            background: var(--color-background);
        }

        .product-modal-body {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) minmax(320px, 1fr);
            gap: 0;
        }

        .product-modal-image-section {
            min-height: 420px;
            background: var(--color-background);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 16px 0 0 16px;
        }

        .product-modal-image {
    width: 100%;
    height: 100%;
    min-height: 420px;
    object-fit: contain;
    padding: 20px;
    box-sizing: border-box;
    display: block;
}

        .product-modal-image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 420px;
            width: 100%;
            color: var(--color-text-muted);
            font-size: 14px;
        }

        .product-modal-info {
            padding: 34px 30px 30px;
        }

        .modal-product-code {
            margin-bottom: 7px;
            color: var(--color-text-secondary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .modal-product-name {
            margin: 0 45px 12px 0;
            color: var(--color-text-primary);
            font-size: 26px;
            line-height: 1.25;
        }

        .modal-product-price {
            margin-bottom: 22px;
            color: var(--color-accent);
            font-size: 25px;
            font-weight: 800;
        }

        .modal-section-title {
            margin: 0 0 8px;
            color: var(--color-text-primary);
            font-size: 14px;
            font-weight: 700;
        }

        .modal-product-description {
            margin: 0 0 22px;
            color: var(--color-text-secondary);
            font-size: 14px;
            line-height: 1.65;
            white-space: pre-line;
        }

        .modal-product-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 22px;
        }

        .modal-detail-item {
            padding: 12px;
            border: 1px solid var(--color-border);
            border-radius: 9px;
            background: var(--color-background);
        }

        .modal-detail-label {
            display: block;
            margin-bottom: 4px;
            color: var(--color-text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .modal-detail-value {
            color: var(--color-text-primary);
            font-size: 14px;
            font-weight: 700;
        }

        .modal-stock-available {
            color: #15803d;
        }

        .modal-stock-low {
            color: #b45309;
        }

        .modal-stock-out {
            color: #b91c1c;
        }


        /* ==========================================================
           MODAL CART FORM
        ========================================================== */

        .modal-cart-form {
            margin-top: 4px;
        }

        .modal-quantity-label {
            display: block;
            margin-bottom: 8px;
            color: var(--color-text-primary);
            font-size: 13px;
            font-weight: 700;
        }

        .modal-quantity-row {
            display: flex;
            gap: 10px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            overflow: hidden;
            background: var(--color-surface);
        }

        .quantity-button {
            width: 42px;
            height: 44px;
            border: none;
            background: var(--color-background);
            color: var(--color-text-primary);
            font-size: 20px;
            cursor: pointer;
        }

        .quantity-button:hover {
            background: var(--color-border);
        }

        .modal-quantity-input {
            width: 55px;
            height: 44px;
            border: none;
            border-left: 1px solid var(--color-border);
            border-right: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text-primary);
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            outline: none;
        }

        .modal-add-cart-btn {
            flex: 1;
            height: 44px;
            border: none;
            border-radius: 8px;
            background: var(--color-accent);
            color: white;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .modal-add-cart-btn:hover {
            opacity: .9;
        }

        .modal-add-cart-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            opacity: 1;
        }


        /* ==========================================================
           BODY LOCK
        ========================================================== */

        body.modal-open {
            overflow: hidden;
        }


        /* ==========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 900px) {

            .products-grid {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

            .product-modal-body {
                grid-template-columns: 1fr;
            }

            .product-modal-image-section {
                min-height: 280px;
                max-height: 320px;
                border-radius: 16px 16px 0 0;
            }

            .product-modal-image {
                min-height: 280px;
                max-height: 320px;
            }

            .product-modal-image-placeholder {
                min-height: 280px;
            }
        }

        @media (max-width: 700px) {

            .products-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .cart-button {
                width: 100%;
            }

            .products-toolbar {
                flex-direction: column;
            }

            .search-form {
                width: 100%;
                max-width: none;
            }

            .clear-search {
                min-height: 42px;
            }

            .product-modal {
                padding: 10px;
            }

            .product-modal-dialog {
                max-height: calc(100vh - 20px);
            }

            .product-modal-info {
                padding: 28px 20px 22px;
            }

            .modal-product-name {
                font-size: 22px;
            }

            .modal-product-price {
                font-size: 22px;
            }
        }

        @media (max-width: 520px) {

            .products-grid {
                grid-template-columns: 1fr;
            }

            .product-image-wrapper {
                height: 230px;
            }

            .modal-product-details {
                grid-template-columns: 1fr;
            }

            .modal-quantity-row {
                flex-direction: column;
            }

            .quantity-control {
                width: 100%;
                justify-content: center;
            }

            .modal-add-cart-btn {
                width: 100%;
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

        <div class="products-page">

            <!-- ==================================================
                 PAGE HEADER
            ================================================== -->

            <div class="products-header">

                <div>

                    <h1>
                        Products
                    </h1>

                    <p>
                        Browse available products and add the items
                        you need to your cart.
                    </p>

                </div>

                <a
                    href="<?= BASE_URL ?>customer/cart.php"
                    class="cart-button"
                >

                    🛒 View Cart

                    <?php if ($cartCount > 0): ?>

                        <span class="cart-count">
                            <?= $cartCount ?>
                        </span>

                    <?php endif; ?>

                </a>

            </div>


            <!-- ==================================================
                 ALERTS
            ================================================== -->

            <?php if ($message !== ''): ?>

                <div
                    class="products-alert products-alert-success"
                >
                    <?= htmlspecialchars($message) ?>
                </div>

            <?php endif; ?>


            <?php if ($error !== ''): ?>

                <div
                    class="products-alert products-alert-error"
                >
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <!-- ==================================================
                 SEARCH
            ================================================== -->

            <div class="products-toolbar">

                <form
                    method="GET"
                    action="<?= BASE_URL ?>customer/products.php"
                    class="search-form"
                >

                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search products by name, code, or description..."
                        autocomplete="off"
                    >

                    <button
                        type="submit"
                        class="search-button"
                    >
                        Search
                    </button>

                </form>


                <?php if ($search !== ''): ?>

                    <a
                        href="<?= BASE_URL ?>customer/products.php"
                        class="clear-search"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </div>


            <!-- ==================================================
                 RESULT COUNT
            ================================================== -->

            <div class="results-info">

                <?php if ($search !== ''): ?>

                    Showing

                    <strong>
                        <?= count($products) ?>
                    </strong>

                    result(s) for

                    <strong>
                        "<?= htmlspecialchars($search) ?>"
                    </strong>

                <?php else: ?>

                    <strong>
                        <?= count($products) ?>
                    </strong>

                    product(s) available

                <?php endif; ?>

            </div>


            <!-- ==================================================
                 PRODUCTS
            ================================================== -->

            <?php if (empty($products)): ?>

                <div class="empty-products">

                    <h3>
                        No products found
                    </h3>

                    <?php if ($search !== ''): ?>

                        <p>
                            We couldn't find any available products
                            matching your search.
                        </p>

                        <a
                            href="<?= BASE_URL ?>customer/products.php"
                            class="empty-action"
                        >
                            View All Products
                        </a>

                    <?php else: ?>

                        <p>
                            There are currently no available products.
                        </p>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="products-grid">

                    <?php foreach ($products as $product): ?>

                        <?php

                        $productId =
                            (int)$product['product_id'];

                        $stock =
                            (int)$product['stock_quantity'];

                        $image =
                            trim(
                                (string)(
                                    $product['product_image']
                                    ?? ''
                                )
                            );

                        $description =
                            trim(
                                (string)(
                                    $product['description']
                                    ?? ''
                                )
                            );

                        $imageUrl = '';

                        if ($image !== '') {
                            $imageUrl =
                                BASE_URL .
                                'assets/uploads/products/' .
                                rawurlencode($image);
                        }

                        ?>

                        <article class="product-card">

                            <!-- ==================================
                                 CLICKABLE PRODUCT AREA
                            =================================== -->

                            <button
                                type="button"
                                class="product-card-trigger"
                                data-product-id="<?= $productId ?>"
                                data-product-code="<?= htmlspecialchars($product['product_code'], ENT_QUOTES, 'UTF-8') ?>"
                                data-product-name="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-product-description="<?= htmlspecialchars(
                                    $description !== ''
                                        ? $description
                                        : 'No description available.',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                data-product-price="<?= number_format((float)$product['price'], 2, '.', '') ?>"
                                data-product-weight="<?= number_format((float)$product['weight_kg'], 2, '.', '') ?>"
                                data-product-stock="<?= $stock ?>"
                                data-product-image="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="View details for <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                            >

                                <!-- ==============================
                                     PRODUCT IMAGE
                                =============================== -->

                                <div
                                    class="product-image-wrapper"
                                >

                                    <?php if ($image !== ''): ?>

                                        <img
                                            src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($product['product_name']) ?>"
                                            class="product-image"
                                        >

                                    <?php else: ?>

                                        <div
                                            class="product-placeholder"
                                        >
                                            No Image Available
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- ==============================
                                     PRODUCT CONTENT
                                =============================== -->

                                <div class="product-content">

                                    <div class="product-code">

                                        <?= htmlspecialchars(
                                            $product['product_code']
                                        ) ?>

                                    </div>


                                    <div class="product-name">

                                        <?= htmlspecialchars(
                                            $product['product_name']
                                        ) ?>

                                    </div>


                                    <div class="product-description">

                                        <?= htmlspecialchars(
                                            $description !== ''
                                                ? $description
                                                : 'No description available.'
                                        ) ?>

                                    </div>


                                    <!-- PRICE / WEIGHT -->

                                    <div class="product-meta">

                                        <span class="product-price">

                                            ₱<?= number_format(
                                                (float)$product['price'],
                                                2
                                            ) ?>

                                        </span>

                                        <span class="product-weight">

                                            <?= number_format(
                                                (float)$product['weight_kg'],
                                                2
                                            ) ?>

                                            kg/unit

                                        </span>

                                    </div>


                                    <!-- STOCK -->

                                    <div class="stock-status">

                                        <?php if ($stock <= 0): ?>

                                            <span class="stock-out">
                                                Out of stock
                                            </span>

                                        <?php elseif ($stock <= 10): ?>

                                            <span class="stock-low">

                                                Only
                                                <?= $stock ?>
                                                left

                                            </span>

                                        <?php else: ?>

                                            <span class="stock-available">

                                                <?= $stock ?>
                                                available

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </button>


                            <!-- ==================================
                                 ACTIONS
                            =================================== -->

                            <div class="product-actions">

                                <!-- VIEW DETAILS -->

                                <button
                                    type="button"
                                    class="view-details-btn"
                                    data-product-id="<?= $productId ?>"
                                    data-product-code="<?= htmlspecialchars($product['product_code'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-product-name="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-product-description="<?= htmlspecialchars(
                                        $description !== ''
                                            ? $description
                                            : 'No description available.',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-product-price="<?= number_format((float)$product['price'], 2, '.', '') ?>"
                                    data-product-weight="<?= number_format((float)$product['weight_kg'], 2, '.', '') ?>"
                                    data-product-stock="<?= $stock ?>"
                                    data-product-image="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    View Details
                                </button>


                                <!-- ADD TO CART -->

                                <?php if ($stock > 0): ?>

                                    <form
                                        method="POST"
                                        action="<?= BASE_URL ?>customer/products.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>"
                                    >

                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value="<?= $productId ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="quantity"
                                            value="1"
                                        >

                                        <button
                                            type="submit"
                                            name="add_to_cart"
                                            class="add-cart-btn"
                                        >
                                            🛒 Add to Cart
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="add-cart-btn"
                                        disabled
                                    >
                                        Out of Stock
                                    </button>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<!-- ==============================================================
     PRODUCT DETAILS MODAL
================================================================ -->

<div
    class="product-modal"
    id="productModal"
    aria-hidden="true"
>

    <div
        class="product-modal-overlay"
        id="productModalOverlay"
    ></div>


    <div
        class="product-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalProductName"
    >

        <!-- CLOSE -->

        <button
            type="button"
            class="product-modal-close"
            id="productModalClose"
            aria-label="Close product details"
        >
            &times;
        </button>


        <div class="product-modal-body">

            <!-- ==================================================
                 MODAL IMAGE
            ================================================== -->

            <div class="product-modal-image-section">

                <img
                    src=""
                    alt=""
                    class="product-modal-image"
                    id="modalProductImage"
                    style="display: none;"
                >

                <div
                    class="product-modal-image-placeholder"
                    id="modalProductImagePlaceholder"
                >
                    No Image Available
                </div>

            </div>


            <!-- ==================================================
                 MODAL INFORMATION
            ================================================== -->

            <div class="product-modal-info">

                <div
                    class="modal-product-code"
                    id="modalProductCode"
                ></div>


                <h2
                    class="modal-product-name"
                    id="modalProductName"
                ></h2>


                <div
                    class="modal-product-price"
                    id="modalProductPrice"
                ></div>


                <h3 class="modal-section-title">
                    Description
                </h3>

                <p
                    class="modal-product-description"
                    id="modalProductDescription"
                ></p>


                <!-- PRODUCT DETAILS -->

                <div class="modal-product-details">

                    <div class="modal-detail-item">

                        <span class="modal-detail-label">
                            Weight
                        </span>

                        <span
                            class="modal-detail-value"
                            id="modalProductWeight"
                        ></span>

                    </div>


                    <div class="modal-detail-item">

                        <span class="modal-detail-label">
                            Available Stock
                        </span>

                        <span
                            class="modal-detail-value"
                            id="modalProductStock"
                        ></span>

                    </div>

                </div>


                <!-- ==================================================
                     ADD TO CART FORM
                ================================================== -->

                <form
                    method="POST"
                    class="modal-cart-form"
                    id="modalCartForm"
                >

                    <input
                        type="hidden"
                        name="product_id"
                        id="modalProductId"
                        value=""
                    >


                    <label
                        for="modalQuantity"
                        class="modal-quantity-label"
                    >
                        Quantity
                    </label>


                    <div class="modal-quantity-row">

                        <div class="quantity-control">

                            <button
                                type="button"
                                class="quantity-button"
                                id="modalQuantityMinus"
                            >
                                −
                            </button>


                            <input
                                type="number"
                                name="quantity"
                                id="modalQuantity"
                                class="modal-quantity-input"
                                value="1"
                                min="1"
                                max="1"
                            >


                            <button
                                type="button"
                                class="quantity-button"
                                id="modalQuantityPlus"
                            >
                                +
                            </button>

                        </div>


                        <button
                            type="submit"
                            name="add_to_cart"
                            class="modal-add-cart-btn"
                            id="modalAddCartButton"
                        >
                            🛒 Add to Cart
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const modal =
        document.getElementById('productModal');

    const overlay =
        document.getElementById('productModalOverlay');

    const closeButton =
        document.getElementById('productModalClose');

    const modalImage =
        document.getElementById('modalProductImage');

    const modalImagePlaceholder =
        document.getElementById(
            'modalProductImagePlaceholder'
        );

    const modalProductId =
        document.getElementById('modalProductId');

    const modalProductCode =
        document.getElementById('modalProductCode');

    const modalProductName =
        document.getElementById('modalProductName');

    const modalProductDescription =
        document.getElementById(
            'modalProductDescription'
        );

    const modalProductPrice =
        document.getElementById(
            'modalProductPrice'
        );

    const modalProductWeight =
        document.getElementById(
            'modalProductWeight'
        );

    const modalProductStock =
        document.getElementById(
            'modalProductStock'
        );

    const modalQuantity =
        document.getElementById(
            'modalQuantity'
        );

    const modalQuantityMinus =
        document.getElementById(
            'modalQuantityMinus'
        );

    const modalQuantityPlus =
        document.getElementById(
            'modalQuantityPlus'
        );

    const modalAddCartButton =
        document.getElementById(
            'modalAddCartButton'
        );


    /*
    |--------------------------------------------------------------------------
    | OPEN MODAL
    |--------------------------------------------------------------------------
    */

    function openProductModal(data) {

        modalProductId.value =
            data.productId;

        modalProductCode.textContent =
            data.productCode;

        modalProductName.textContent =
            data.productName;

        modalProductDescription.textContent =
            data.productDescription;

        modalProductPrice.textContent =
            '₱' +
            Number(data.productPrice)
                .toLocaleString(
                    'en-PH',
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );

        modalProductWeight.textContent =
            Number(data.productWeight)
                .toFixed(2) +
            ' kg/unit';


        const stock =
            parseInt(
                data.productStock,
                10
            ) || 0;


        /*
        |--------------------------------------------------------------------------
        | STOCK DISPLAY
        |--------------------------------------------------------------------------
        */

        modalProductStock.className =
            'modal-detail-value';

        if (stock <= 0) {

            modalProductStock.textContent =
                'Out of stock';

            modalProductStock.classList.add(
                'modal-stock-out'
            );

        } else if (stock <= 10) {

            modalProductStock.textContent =
                stock + ' left';

            modalProductStock.classList.add(
                'modal-stock-low'
            );

        } else {

            modalProductStock.textContent =
                stock + ' available';

            modalProductStock.classList.add(
                'modal-stock-available'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | IMAGE
        |--------------------------------------------------------------------------
        */

        if (data.productImage) {

            modalImage.src =
                data.productImage;

            modalImage.alt =
                data.productName;

            modalImage.style.display =
                'block';

            modalImagePlaceholder.style.display =
                'none';

        } else {

            modalImage.src = '';

            modalImage.alt = '';

            modalImage.style.display =
                'none';

            modalImagePlaceholder.style.display =
                'flex';
        }


        /*
        |--------------------------------------------------------------------------
        | QUANTITY
        |--------------------------------------------------------------------------
        */

        modalQuantity.min = 1;

        modalQuantity.max =
            Math.max(stock, 1);

        modalQuantity.value =
            stock > 0 ? 1 : 0;


        /*
        |--------------------------------------------------------------------------
        | ADD TO CART BUTTON
        |--------------------------------------------------------------------------
        */

        if (stock > 0) {

            modalAddCartButton.disabled =
                false;

            modalAddCartButton.textContent =
                '🛒 Add to Cart';

        } else {

            modalAddCartButton.disabled =
                true;

            modalAddCartButton.textContent =
                'Out of Stock';
        }


        /*
        |--------------------------------------------------------------------------
        | SHOW MODAL
        |--------------------------------------------------------------------------
        */

        modal.classList.add('is-open');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'modal-open'
        );

        closeButton.focus();
    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE MODAL
    |--------------------------------------------------------------------------
    */

    function closeProductModal() {

        modal.classList.remove(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'modal-open'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GET PRODUCT DATA
    |--------------------------------------------------------------------------
    */

    function getProductData(element) {

        return {
            productId:
                element.dataset.productId || '',

            productCode:
                element.dataset.productCode || '',

            productName:
                element.dataset.productName || '',

            productDescription:
                element.dataset.productDescription ||
                'No description available.',

            productPrice:
                element.dataset.productPrice || '0',

            productWeight:
                element.dataset.productWeight || '0',

            productStock:
                element.dataset.productStock || '0',

            productImage:
                element.dataset.productImage || ''
        };
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT CARD CLICK
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.product-card-trigger'
        )
        .forEach(function (card) {

            card.addEventListener(
                'click',
                function () {

                    const data =
                        getProductData(card);

                    openProductModal(data);
                }
            );


            /*
            | Keyboard accessibility
            */

            card.addEventListener(
                'keydown',
                function (event) {

                    if (
                        event.key === 'Enter' ||
                        event.key === ' '
                    ) {

                        event.preventDefault();

                        const data =
                            getProductData(card);

                        openProductModal(data);
                    }
                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | VIEW DETAILS BUTTON
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.view-details-btn'
        )
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const data =
                        getProductData(button);

                    openProductModal(data);
                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | QUANTITY MINUS
    |--------------------------------------------------------------------------
    */

    modalQuantityMinus.addEventListener(
        'click',
        function () {

            const current =
                parseInt(
                    modalQuantity.value,
                    10
                ) || 1;

            const minimum =
                parseInt(
                    modalQuantity.min,
                    10
                ) || 1;

            if (current > minimum) {

                modalQuantity.value =
                    current - 1;
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | QUANTITY PLUS
    |--------------------------------------------------------------------------
    */

    modalQuantityPlus.addEventListener(
        'click',
        function () {

            const current =
                parseInt(
                    modalQuantity.value,
                    10
                ) || 1;

            const maximum =
                parseInt(
                    modalQuantity.max,
                    10
                ) || 1;

            if (current < maximum) {

                modalQuantity.value =
                    current + 1;
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATE MANUAL QUANTITY
    |--------------------------------------------------------------------------
    */

    modalQuantity.addEventListener(
        'change',
        function () {

            let value =
                parseInt(
                    modalQuantity.value,
                    10
                ) || 1;

            const minimum =
                parseInt(
                    modalQuantity.min,
                    10
                ) || 1;

            const maximum =
                parseInt(
                    modalQuantity.max,
                    10
                ) || 1;

            if (value < minimum) {
                value = minimum;
            }

            if (value > maximum) {
                value = maximum;
            }

            modalQuantity.value =
                value;
        }
    );


    /*
    |--------------------------------------------------------------------------
    | CLOSE BUTTON
    |--------------------------------------------------------------------------
    */

    closeButton.addEventListener(
        'click',
        closeProductModal
    );


    /*
    |--------------------------------------------------------------------------
    | CLICK OUTSIDE MODAL
    |--------------------------------------------------------------------------
    */

    overlay.addEventListener(
        'click',
        closeProductModal
    );


    /*
    |--------------------------------------------------------------------------
    | ESC KEY
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains(
                    'is-open'
                )
            ) {

                closeProductModal();
            }
        }
    );

});

</script>

</body>
</html>