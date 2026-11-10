<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

/*
|--------------------------------------------------------------------------
| INITIALIZE CART
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function redirectToCart(): void
{
    header('Location: ' . BASE_URL . 'customer/cart.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| REMOVE ITEM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['remove_item'])
) {
    $productId = filter_input(
        INPUT_POST,
        'product_id',
        FILTER_VALIDATE_INT
    );

    if ($productId && isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);

        /*
        | Remove it from any pending checkout selection too.
        */
        if (
            isset($_SESSION['checkout_selected_products']) &&
            is_array($_SESSION['checkout_selected_products'])
        ) {
            $_SESSION['checkout_selected_products'] = array_values(
                array_filter(
                    $_SESSION['checkout_selected_products'],
                    fn($id) => (int) $id !== (int) $productId
                )
            );
        }

        $_SESSION['cart_message'] = 'Product removed from your cart.';
    }

    redirectToCart();
}

/*
|--------------------------------------------------------------------------
| UPDATE QUANTITY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_cart'])
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

    if (!$productId || !$quantity) {
        $_SESSION['cart_error'] = 'Invalid product or quantity.';
        redirectToCart();
    }

    /*
    |--------------------------------------------------------------------------
    | GET CURRENT PRODUCT DATA
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            product_id,
            product_code,
            product_name,
            description,
            product_image,
            price,
            weight_kg,
            stock_quantity,
            status
        FROM product
        WHERE product_id = ?
        LIMIT 1
    ");

    $stmt->execute([$productId]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        unset($_SESSION['cart'][$productId]);

        $_SESSION['cart_error'] = 'Product no longer exists.';
        redirectToCart();
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT AVAILABILITY
    |--------------------------------------------------------------------------
    */

    if (
        $product['status'] !== 'Available' ||
        (int) $product['stock_quantity'] <= 0
    ) {
        unset($_SESSION['cart'][$productId]);

        $_SESSION['cart_error'] =
            $product['product_name'] . ' is currently unavailable.';

        redirectToCart();
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($quantity < 1) {
        $_SESSION['cart_error'] = 'Quantity must be at least 1.';
        redirectToCart();
    }

    if ($quantity > (int) $product['stock_quantity']) {
        $_SESSION['cart_error'] =
            'Only ' .
            (int) $product['stock_quantity'] .
            ' unit(s) of ' .
            $product['product_name'] .
            ' are currently available.';

        redirectToCart();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE SESSION CART
    |--------------------------------------------------------------------------
    */

    $_SESSION['cart'][$productId] = [
        'product_id'     => (int) $product['product_id'],
        'product_code'   => $product['product_code'],
        'product_name'   => $product['product_name'],
        'description'    => $product['description'],
        'price'          => (float) $product['price'],
        'weight_kg'      => (float) $product['weight_kg'],
        'stock_quantity' => (int) $product['stock_quantity'],
        'product_image'  => $product['product_image'],
        'quantity'       => $quantity
    ];

    $_SESSION['cart_message'] = 'Cart updated successfully.';

    redirectToCart();
}

/*
|--------------------------------------------------------------------------
| CLEAR CART
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['clear_cart'])
) {
    $_SESSION['cart'] = [];
    $_SESSION['checkout_selected_products'] = [];

    $_SESSION['cart_message'] = 'Your cart has been cleared.';

    redirectToCart();
}

/*
|--------------------------------------------------------------------------
| CHECKOUT SELECTED ITEMS
|--------------------------------------------------------------------------
|
| Customer can select only the products they want to checkout.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['checkout_selected'])
) {
    $selectedProducts = $_POST['selected_products'] ?? [];

    if (!is_array($selectedProducts)) {
        $selectedProducts = [];
    }

    $selectedIds = [];

    foreach ($selectedProducts as $selectedId) {
        $selectedId = filter_var(
            $selectedId,
            FILTER_VALIDATE_INT
        );

        if (
            $selectedId &&
            isset($_SESSION['cart'][$selectedId])
        ) {
            $selectedIds[] = (int) $selectedId;
        }
    }

    $selectedIds = array_values(
        array_unique($selectedIds)
    );

    if (empty($selectedIds)) {
        $_SESSION['cart_error'] =
            'Please select at least one product to checkout.';

        redirectToCart();
    }

    /*
    | Store selected product IDs temporarily.
    | checkout.php will read this session value.
    */
    $_SESSION['checkout_selected_products'] = $selectedIds;

    header(
        'Location: ' .
        BASE_URL .
        'customer/checkout.php'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['cart_message'])) {
    $message = $_SESSION['cart_message'];
    unset($_SESSION['cart_message']);
}

if (isset($_SESSION['cart_error'])) {
    $error = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
}

/*
|--------------------------------------------------------------------------
| REFRESH CART FROM DATABASE
|--------------------------------------------------------------------------
|
| Makes sure price, weight, stock and availability are current.
|
*/

foreach (array_keys($_SESSION['cart']) as $productId) {

    $stmt = $db->prepare("
        SELECT
            product_id,
            product_code,
            product_name,
            description,
            product_image,
            price,
            weight_kg,
            stock_quantity,
            status
        FROM product
        WHERE product_id = ?
        LIMIT 1
    ");

    $stmt->execute([$productId]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | PRODUCT REMOVED
    |--------------------------------------------------------------------------
    */

    if (!$product) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT UNAVAILABLE
    |--------------------------------------------------------------------------
    */

    if (
        $product['status'] !== 'Available' ||
        (int) $product['stock_quantity'] <= 0
    ) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | KEEP QUANTITY WITHIN CURRENT STOCK
    |--------------------------------------------------------------------------
    */

    $currentQuantity = (int) (
        $_SESSION['cart'][$productId]['quantity'] ?? 1
    );

    if (
        $currentQuantity >
        (int) $product['stock_quantity']
    ) {
        $currentQuantity =
            (int) $product['stock_quantity'];
    }

    if ($currentQuantity < 1) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | REFRESH PRODUCT DATA
    |--------------------------------------------------------------------------
    */

    $_SESSION['cart'][$productId] = [
        'product_id'     => (int) $product['product_id'],
        'product_code'   => $product['product_code'],
        'product_name'   => $product['product_name'],
        'description'    => $product['description'],
        'price'          => (float) $product['price'],
        'weight_kg'      => (float) $product['weight_kg'],
        'stock_quantity' => (int) $product['stock_quantity'],
        'product_image'  => $product['product_image'],
        'quantity'       => $currentQuantity
    ];
}

/*
|--------------------------------------------------------------------------
| CLEAN OLD CHECKOUT SELECTION
|--------------------------------------------------------------------------
|
| If products were removed from the cart, remove them from the
| stored checkout selection as well.
|
*/

if (
    isset($_SESSION['checkout_selected_products']) &&
    is_array($_SESSION['checkout_selected_products'])
) {
    $_SESSION['checkout_selected_products'] = array_values(
        array_filter(
            $_SESSION['checkout_selected_products'],
            function ($productId) {
                return isset($_SESSION['cart'][$productId]);
            }
        )
    );
}

/*
|--------------------------------------------------------------------------
| CALCULATE CART TOTALS
|--------------------------------------------------------------------------
*/

$totalItems = 0;
$totalAmount = 0;
$totalWeight = 0;
$cartItems = [];

foreach ($_SESSION['cart'] as $productId => $item) {

    $quantity = (int) $item['quantity'];
    $price = (float) $item['price'];
    $weight = (float) $item['weight_kg'];

    $subtotal = $price * $quantity;
    $itemWeight = $weight * $quantity;

    $totalItems += $quantity;
    $totalAmount += $subtotal;
    $totalWeight += $itemWeight;

    $item['subtotal'] = $subtotal;
    $item['total_weight'] = $itemWeight;

    $cartItems[] = $item;
}

/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
|
| The shared customer header handles the actual customer sidebar.
| This remains here for compatibility with the existing header.
|
*/

$navItems = [
    [
        'label' => 'Dashboard',
        'url' => BASE_URL . 'customer/dashboard.php'
    ],
    [
        'label' => 'Products',
        'url' => BASE_URL . 'customer/products.php'
    ],
    [
        'label' => 'Cart',
        'url' => BASE_URL . 'customer/cart.php'
    ],
    [
        'label' => 'My Orders',
        'url' => BASE_URL . 'customer/order_history.php'
    ],
    [
        'label' => 'Notifications',
        'url' => BASE_URL . 'customer/notifications.php'
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
        My Cart · Hong Sing LKRR Commercial
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | PAGE HEADER
        |--------------------------------------------------------------------------
        */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 0 0 5px;
        }

        .page-header p {
            margin: 0;
            color: var(--color-text-secondary);
        }

        /*
        |--------------------------------------------------------------------------
        | ALERTS
        |--------------------------------------------------------------------------
        */

        .cart-alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .cart-alert.success {
            background: rgba(34, 197, 94, 0.10);
            border: 1px solid rgba(34, 197, 94, 0.25);
        }

        .cart-alert.error {
            background: rgba(239, 68, 68, 0.10);
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        /*
        |--------------------------------------------------------------------------
        | CART LAYOUT
        |--------------------------------------------------------------------------
        */

        .cart-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            gap: 22px;
            align-items: start;
        }

        /*
        |--------------------------------------------------------------------------
        | SECTION HEADING
        |--------------------------------------------------------------------------
        */

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 14px;
        }

        .section-heading h2 {
            margin: 0 0 4px;
        }

        .section-heading p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 13px;
        }

        /*
        |--------------------------------------------------------------------------
        | SELECT ALL
        |--------------------------------------------------------------------------
        */

        .select-all-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            padding: 11px 14px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 8px;
        }

        .select-all-wrapper input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--color-accent);
        }

        .select-all-wrapper label {
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: var(--color-text-primary);
        }

        /*
        |--------------------------------------------------------------------------
        | CART ITEMS
        |--------------------------------------------------------------------------
        */

        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 28px 90px minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
            padding: 16px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 10px;
        }

        .cart-item-select {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--color-accent);
        }

        .cart-item-image {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
            background: #f3f3f3;
        }

        .cart-item-placeholder {
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #f3f3f3;
            color: var(--color-text-secondary);
            font-size: 12px;
        }

        .cart-item-code {
            font-size: 11px;
            color: var(--color-text-muted);
            margin-bottom: 4px;
        }

        .cart-item-name {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .cart-item-description {
            font-size: 12px;
            color: var(--color-text-secondary);
            max-width: 600px;
        }

        .cart-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 8px;
            font-size: 12px;
            color: var(--color-text-secondary);
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--color-text-primary);
        }

        /*
        |--------------------------------------------------------------------------
        | ITEM ACTIONS
        |--------------------------------------------------------------------------
        */

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .quantity-form {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .quantity-input {
            width: 65px;
            padding: 8px;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            text-align: center;
        }

        .update-button {
            padding: 8px 10px;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            background: var(--color-surface);
            color: var(--color-text-primary);
            cursor: pointer;
        }

        .remove-button {
            border: none;
            background: transparent;
            color: #dc2626;
            cursor: pointer;
            font-size: 12px;
        }

        .item-subtotal {
            font-size: 17px;
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .cart-summary {
            position: sticky;
            top: 20px;
            padding: 20px;
        }

        .cart-summary h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 11px 0;
            border-bottom: 1px solid var(--color-border);
        }

        .summary-row:last-of-type {
            border-bottom: none;
        }

        .summary-label {
            color: var(--color-text-secondary);
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
            margin-top: 12px;
            padding-top: 15px;
            border-top: 2px solid var(--color-border);
        }

        .summary-total strong {
            font-size: 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | SELECTION SUMMARY
        |--------------------------------------------------------------------------
        */

        .selection-summary {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px dashed var(--color-border);
        }

        .selection-summary-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        .cart-buttons {
            display: flex;
            flex-direction: column;
            gap: 9px;
            margin-top: 20px;
        }

        .primary-button,
        .secondary-button {
            width: 100%;
            padding: 11px 15px;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            box-sizing: border-box;
        }

        .primary-button {
            border: none;
            background: var(--color-accent);
            color: white;
        }

        .primary-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .secondary-button {
            border: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text-primary);
        }

        /*
        |--------------------------------------------------------------------------
        | EMPTY CART
        |--------------------------------------------------------------------------
        */

        .empty-cart {
            padding: 65px 25px;
            text-align: center;
        }

        .empty-cart-icon {
            font-size: 45px;
            margin-bottom: 12px;
        }

        .empty-cart h2 {
            margin-bottom: 8px;
        }

        .empty-cart p {
            color: var(--color-text-secondary);
            margin-bottom: 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }

        }

        @media (max-width: 700px) {

            .cart-item {
                grid-template-columns: 28px 70px minmax(0, 1fr);
            }

            .cart-item-image,
            .cart-item-placeholder {
                width: 70px;
                height: 70px;
            }

            .cart-item-actions {
                grid-column: 1 / -1;
                align-items: stretch;
                flex-direction: row;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .item-subtotal {
                margin-left: auto;
            }

        }

        @media (max-width: 480px) {

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .cart-item {
                grid-template-columns: 26px 60px minmax(0, 1fr);
                gap: 10px;
                padding: 12px;
            }

            .cart-item-image,
            .cart-item-placeholder {
                width: 60px;
                height: 60px;
            }

            .cart-item-name {
                font-size: 15px;
            }

            .cart-item-description {
                font-size: 11px;
            }

            .cart-item-meta {
                gap: 8px;
            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <?php require __DIR__ . '/../includes/header.php'; ?>

    <main class="app-main">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h1>My Cart</h1>

                <p>
                    Select the products you want to checkout.
                </p>

            </div>

        </div>

        <!-- ALERTS -->

        <?php if ($message): ?>

            <div class="cart-alert success">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="cart-alert error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <!-- EMPTY CART -->

        <?php if (empty($cartItems)): ?>

            <div class="card empty-cart">

                <div class="empty-cart-icon">
                    🛒
                </div>

                <h2>
                    Your cart is empty
                </h2>

                <p>
                    Browse our available products and add
                    something to your cart.
                </p>

                <a
                    href="<?= BASE_URL ?>customer/products.php"
                    class="primary-button"
                    style="display:inline-block;width:auto;"
                >
                    Browse Products
                </a>

            </div>

        <?php else: ?>

            <!-- CART CONTENT -->

            <div class="cart-layout">

                <!-- LEFT SIDE -->

                <section>

                    <div class="section-heading">

                        <div>

                            <h2>
                                Cart Items
                            </h2>

                            <p>
                                <?= count($cartItems) ?>
                                product(s) ·
                                <?= $totalItems ?>
                                total item(s)
                            </p>

                        </div>

                        <form
                            method="POST"
                            onsubmit="return confirm('Are you sure you want to clear your cart?');"
                        >

                            <button
                                type="submit"
                                name="clear_cart"
                                class="remove-button"
                            >
                                Clear Cart
                            </button>

                        </form>

                    </div>

                    <!-- SELECT ALL -->

                    <div class="select-all-wrapper">

                        <input
                            type="checkbox"
                            id="selectAllProducts"
                        >

                        <label for="selectAllProducts">
                            Select All Products
                        </label>

                    </div>

                    <!-- CART ITEMS -->

                    <div class="cart-items">

                        <?php foreach ($cartItems as $item): ?>

                            <article class="cart-item">

                                <!-- CHECKBOX -->

                                <div>

                                    <input
                                        type="checkbox"
                                        class="cart-item-select"
                                        value="<?= (int) $item['product_id'] ?>"
                                        data-price="<?= htmlspecialchars((string) $item['price'], ENT_QUOTES) ?>"
                                        data-weight="<?= htmlspecialchars((string) $item['weight_kg'], ENT_QUOTES) ?>"
                                        data-quantity="<?= (int) $item['quantity'] ?>"
                                        aria-label="Select <?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?> for checkout"
                                    >

                                </div>

                                <!-- IMAGE -->

                                <div>

                                    <?php if (!empty($item['product_image'])): ?>

                                        <img
                                            src="<?= BASE_URL ?>assets/uploads/products/<?= htmlspecialchars($item['product_image']) ?>"
                                            alt="<?= htmlspecialchars($item['product_name']) ?>"
                                            class="cart-item-image"
                                        >

                                    <?php else: ?>

                                        <div class="cart-item-placeholder">
                                            No Image
                                        </div>

                                    <?php endif; ?>

                                </div>

                                <!-- PRODUCT INFORMATION -->

                                <div>

                                    <div class="cart-item-code">
                                        <?= htmlspecialchars($item['product_code']) ?>
                                    </div>

                                    <div class="cart-item-name">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </div>

                                    <div class="cart-item-description">

                                        <?= htmlspecialchars(
                                            $item['description']
                                            ?: 'No description available.'
                                        ) ?>

                                    </div>

                                    <div class="cart-item-meta">

                                        <span>
                                            Unit Price:
                                            <strong>
                                                ₱<?= number_format(
                                                    (float) $item['price'],
                                                    2
                                                ) ?>
                                            </strong>
                                        </span>

                                        <span>
                                            Weight:
                                            <strong>
                                                <?= number_format(
                                                    (float) $item['weight_kg'],
                                                    2
                                                ) ?>
                                                kg/unit
                                            </strong>
                                        </span>

                                        <span>
                                            Available:
                                            <strong>
                                                <?= (int) $item['stock_quantity'] ?>
                                            </strong>
                                        </span>

                                    </div>

                                </div>

                                <!-- ACTIONS -->

                                <div class="cart-item-actions">

                                    <!-- QUANTITY -->

                                    <form
                                        method="POST"
                                        class="quantity-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value="<?= (int) $item['product_id'] ?>"
                                        >

                                        <input
                                            type="number"
                                            name="quantity"
                                            class="quantity-input"
                                            min="1"
                                            max="<?= (int) $item['stock_quantity'] ?>"
                                            value="<?= (int) $item['quantity'] ?>"
                                            required
                                        >

                                        <button
                                            type="submit"
                                            name="update_cart"
                                            class="update-button"
                                        >
                                            Update
                                        </button>

                                    </form>

                                    <!-- SUBTOTAL -->

                                    <div class="item-subtotal">

                                        ₱<?= number_format(
                                            (float) $item['subtotal'],
                                            2
                                        ) ?>

                                    </div>

                                    <!-- REMOVE -->

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Remove this product from your cart?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value="<?= (int) $item['product_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="remove_item"
                                            class="remove-button"
                                        >
                                            Remove
                                        </button>

                                    </form>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

                <!-- RIGHT SIDE -->

                <aside class="card cart-summary">

                    <h2>
                        Order Summary
                    </h2>

                    <!-- SELECTED PRODUCTS -->

                    <div class="selection-summary">

                        <div class="selection-summary-title">
                            Selected for Checkout
                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Products
                            </span>

                            <span
                                class="summary-value"
                                id="selectedProductCount"
                            >
                                0
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Total Items
                            </span>

                            <span
                                class="summary-value"
                                id="selectedTotalItems"
                            >
                                0
                            </span>

                        </div>

                        <div class="summary-row">

                            <span class="summary-label">
                                Total Weight
                            </span>

                            <span
                                class="summary-value"
                                id="selectedTotalWeight"
                            >
                                0.00 kg
                            </span>

                        </div>

                    </div>

                    <!-- SELECTED TOTAL -->

                    <div class="summary-total">

                        <span>
                            Selected Amount
                        </span>

                        <strong id="selectedTotalAmount">
                            ₱0.00
                        </strong>

                    </div>

                    <!-- BUTTONS -->

                    <div class="cart-buttons">

                        <!-- CHECKOUT SELECTED -->

                        <form
                            method="POST"
                            id="selectedCheckoutForm"
                        >

                            <div id="selectedProductsInputs"></div>

                            <button
                                type="submit"
                                name="checkout_selected"
                                class="primary-button"
                                id="checkoutSelectedButton"
                                disabled
                            >
                                Proceed to Checkout
                            </button>

                        </form>

                        <!-- CONTINUE SHOPPING -->

                        <a
                            href="<?= BASE_URL ?>customer/products.php"
                            class="secondary-button"
                        >
                            Continue Shopping
                        </a>

                    </div>

                    <p
                        style="
                            font-size:12px;
                            color:var(--color-text-secondary);
                            margin-top:15px;
                            line-height:1.5;
                        "
                    >
                        Select only the products you want to purchase.
                        Unselected products will remain in your cart.
                    </p>

                </aside>

            </div>

        <?php endif; ?>

    </main>

</div>

<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | ELEMENTS
    |--------------------------------------------------------------------------
    */

    const checkboxes = Array.from(
        document.querySelectorAll('.cart-item-select')
    );

    const selectAll = document.getElementById(
        'selectAllProducts'
    );

    const checkoutForm = document.getElementById(
        'selectedCheckoutForm'
    );

    const checkoutButton = document.getElementById(
        'checkoutSelectedButton'
    );

    const selectedProductsInputs =
        document.getElementById(
            'selectedProductsInputs'
        );

    const selectedProductCount =
        document.getElementById(
            'selectedProductCount'
        );

    const selectedTotalItems =
        document.getElementById(
            'selectedTotalItems'
        );

    const selectedTotalWeight =
        document.getElementById(
            'selectedTotalWeight'
        );

    const selectedTotalAmount =
        document.getElementById(
            'selectedTotalAmount'
        );

    /*
    |--------------------------------------------------------------------------
    | UPDATE SELECTION SUMMARY
    |--------------------------------------------------------------------------
    */

    function updateSelection() {

        let productCount = 0;
        let itemCount = 0;
        let totalWeight = 0;
        let totalAmount = 0;

        checkboxes.forEach(function (checkbox) {

            if (!checkbox.checked) {
                return;
            }

            const price =
                parseFloat(
                    checkbox.dataset.price
                ) || 0;

            const weight =
                parseFloat(
                    checkbox.dataset.weight
                ) || 0;

            const quantity =
                parseInt(
                    checkbox.dataset.quantity,
                    10
                ) || 0;

            productCount++;

            itemCount += quantity;

            totalWeight +=
                weight * quantity;

            totalAmount +=
                price * quantity;

        });

        /*
        |--------------------------------------------------------------------------
        | UPDATE DISPLAY
        |--------------------------------------------------------------------------
        */

        if (selectedProductCount) {
            selectedProductCount.textContent =
                productCount;
        }

        if (selectedTotalItems) {
            selectedTotalItems.textContent =
                itemCount;
        }

        if (selectedTotalWeight) {
            selectedTotalWeight.textContent =
                totalWeight.toFixed(2) + ' kg';
        }

        if (selectedTotalAmount) {
            selectedTotalAmount.textContent =
                '₱' + totalAmount.toFixed(2);
        }

        /*
        |--------------------------------------------------------------------------
        | ENABLE / DISABLE CHECKOUT
        |--------------------------------------------------------------------------
        */

        if (checkoutButton) {
            checkoutButton.disabled =
                productCount === 0;
        }

        /*
        |--------------------------------------------------------------------------
        | SELECT ALL STATE
        |--------------------------------------------------------------------------
        */

        if (selectAll) {

            selectAll.checked =
                productCount > 0 &&
                productCount === checkboxes.length;

            selectAll.indeterminate =
                productCount > 0 &&
                productCount < checkboxes.length;

        }

    }

    /*
    |--------------------------------------------------------------------------
    | INDIVIDUAL CHECKBOXES
    |--------------------------------------------------------------------------
    */

    checkboxes.forEach(function (checkbox) {

        checkbox.addEventListener(
            'change',
            updateSelection
        );

    });

    /*
    |--------------------------------------------------------------------------
    | SELECT ALL
    |--------------------------------------------------------------------------
    */

    if (selectAll) {

        selectAll.addEventListener(
            'change',
            function () {

                checkboxes.forEach(
                    function (checkbox) {

                        checkbox.checked =
                            selectAll.checked;

                    }
                );

                updateSelection();

            }
        );

    }

    /*
    |--------------------------------------------------------------------------
    | CHECKOUT FORM
    |--------------------------------------------------------------------------
    |
    | We create hidden inputs here because each cart item already has
    | its own Update and Remove forms. This avoids nesting forms.
    |
    */

    if (checkoutForm) {

        checkoutForm.addEventListener(
            'submit',
            function (event) {

                const selected =
                    checkboxes.filter(
                        function (checkbox) {
                            return checkbox.checked;
                        }
                    );

                /*
                | No selection
                */

                if (selected.length === 0) {

                    event.preventDefault();

                    alert(
                        'Please select at least one product to checkout.'
                    );

                    return;
                }

                /*
                | Remove old hidden inputs.
                */

                if (selectedProductsInputs) {

                    selectedProductsInputs.innerHTML = '';

                    /*
                    | Create:
                    |
                    | selected_products[]
                    |
                    | for every selected product.
                    */

                    selected.forEach(
                        function (checkbox) {

                            const input =
                                document.createElement(
                                    'input'
                                );

                            input.type = 'hidden';

                            input.name =
                                'selected_products[]';

                            input.value =
                                checkbox.value;

                            selectedProductsInputs.appendChild(
                                input
                            );

                        }
                    );

                }

            }
        );

    }

    /*
    |--------------------------------------------------------------------------
    | INITIALIZE
    |--------------------------------------------------------------------------
    */

    updateSelection();

});

</script>

</body>

</html>