<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

$customerId = currentUserId();


// ==========================================================
// HELPERS
// ==========================================================

function redirectOrder(string $message, string $type = 'success'): void
{
    header(
        "Location: " .
        BASE_URL .
        "customer/order.php?" .
        $type .
        "=" .
        urlencode($message)
    );

    exit;
}


// ==========================================================
// PLACE ORDER
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['place_order'])) {

    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    $expectedDeliveryDate =
        $_POST['expected_delivery_date'] ?? '';

    $priorityLevel =
        $_POST['priority_level'] ?? 'Normal';


    // ------------------------------------------------------
    // BASIC VALIDATION
    // ------------------------------------------------------

    if (!is_array($productIds) || !is_array($quantities)) {

        redirectOrder(
            'Please select at least one product.',
            'error'
        );
    }


    if (empty($productIds)) {

        redirectOrder(
            'Please select at least one product.',
            'error'
        );
    }


    if (
        !in_array(
            $priorityLevel,
            ['Normal', 'Urgent'],
            true
        )
    ) {

        $priorityLevel = 'Normal';
    }


    if ($expectedDeliveryDate === '') {

        redirectOrder(
            'Please select an expected delivery date.',
            'error'
        );
    }


    // ------------------------------------------------------
    // VALIDATE DATE
    // ------------------------------------------------------

    $today = date('Y-m-d');

    if ($expectedDeliveryDate < $today) {

        redirectOrder(
            'Delivery date cannot be in the past.',
            'error'
        );
    }


    // ------------------------------------------------------
    // BUILD CART
    // ------------------------------------------------------

    $cart = [];

    foreach ($productIds as $index => $productId) {

        $productId = (int)$productId;

        $quantity = isset($quantities[$index])
            ? (int)$quantities[$index]
            : 0;


        if ($productId <= 0) {
            continue;
        }


        if ($quantity <= 0) {
            continue;
        }


        /*
         * If the same product appears more than once,
         * combine the quantities.
         */

        if (isset($cart[$productId])) {

            $cart[$productId] += $quantity;

        } else {

            $cart[$productId] = $quantity;
        }
    }


    if (empty($cart)) {

        redirectOrder(
            'Please select at least one product with a valid quantity.',
            'error'
        );
    }


    // ------------------------------------------------------
    // GET PRODUCTS FROM DATABASE
    // ------------------------------------------------------

    $productDetails = [];

    try {

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($cart),
                '?'
            )
        );


        $stmt = $db->prepare("
            SELECT
                product_id,
                product_code,
                product_name,
                description,
                price,
                weight_kg,
                stock_quantity,
                status

            FROM product

            WHERE product_id IN ($placeholders)

            FOR UPDATE
        ");


        $stmt->execute(
            array_keys($cart)
        );


        $products = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


        foreach ($products as $product) {

            $productDetails[
                (int)$product['product_id']
            ] = $product;
        }


        // --------------------------------------------------
        // CHECK EVERY PRODUCT
        // --------------------------------------------------

        foreach ($cart as $productId => $quantity) {

            if (!isset($productDetails[$productId])) {

                throw new Exception(
                    'One of the selected products no longer exists.'
                );
            }


            $product =
                $productDetails[$productId];


            // Product availability

            if ($product['status'] !== 'Available') {

                throw new Exception(
                    $product['product_name'] .
                    ' is currently unavailable.'
                );
            }


            // Stock validation

            if (
                $quantity >
                (int)$product['stock_quantity']
            ) {

                throw new Exception(
                    'Not enough stock for ' .
                    $product['product_name'] .
                    '. Available stock: ' .
                    (int)$product['stock_quantity']
                );
            }


            // Weight validation

            if (
                (float)$product['weight_kg'] <= 0
            ) {

                throw new Exception(
                    'Invalid weight configured for ' .
                    $product['product_name'] .
                    '.'
                );
            }
        }


        // --------------------------------------------------
        // CALCULATE ORDER TOTALS
        // --------------------------------------------------

        $totalAmount = 0;
        $totalWeightKg = 0;

        $itemDescriptions = [];


        foreach ($cart as $productId => $quantity) {

            $product =
                $productDetails[$productId];


            $unitPrice =
                (float)$product['price'];

            $unitWeight =
                (float)$product['weight_kg'];


            $subtotal =
                $unitPrice * $quantity;

            $itemTotalWeight =
                $unitWeight * $quantity;


            $totalAmount += $subtotal;

            $totalWeightKg += $itemTotalWeight;


            $itemDescriptions[] =
                $product['product_name'] .
                ' x ' .
                $quantity;
        }


        // --------------------------------------------------
        // GENERATE ORDER NUMBER
        // --------------------------------------------------

        $orderNumber =
            'ORD-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                substr(
                    bin2hex(
                        random_bytes(3)
                    ),
                    0,
                    6
                )
            );


        // --------------------------------------------------
        // START TRANSACTION
        // --------------------------------------------------

        $db->beginTransaction();


        // --------------------------------------------------
        // INSERT MAIN ORDER
        // --------------------------------------------------

        $stmt = $db->prepare("
            INSERT INTO `order` (
                order_number,
                customer_id,
                driver_id,
                truck_id,
                route_id,
                item_description,
                total_amount,
                total_weight,
                total_weight_kg,
                order_date,
                expected_delivery_date,
                priority_level,
                completion_status
            )

            VALUES (
                ?,
                ?,
                NULL,
                NULL,
                NULL,
                ?,
                ?,
                ?,
                ?,
                CURDATE(),
                ?,
                ?,
                'Pending'
            )
        ");


        $itemDescription =
            implode(
                ', ',
                $itemDescriptions
            );


        $stmt->execute([

            $orderNumber,

            $customerId,

            $itemDescription,

            $totalAmount,

            $totalWeightKg,

            $totalWeightKg,

            $expectedDeliveryDate,

            $priorityLevel

        ]);


        $orderId =
            (int)$db->lastInsertId();


        // --------------------------------------------------
        // INSERT ORDER ITEMS
        // --------------------------------------------------

        $itemStmt = $db->prepare("
            INSERT INTO order_item (
                order_id,
                product_id,
                quantity,
                unit_price,
                unit_weight_kg,
                total_weight_kg,
                subtotal
            )

            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");


        // --------------------------------------------------
        // UPDATE PRODUCT STOCK
        // --------------------------------------------------

        $stockStmt = $db->prepare("
            UPDATE product

            SET
                stock_quantity =
                    stock_quantity - ?

            WHERE product_id = ?

            AND stock_quantity >= ?
        ");


        foreach ($cart as $productId => $quantity) {

            $product =
                $productDetails[$productId];


            $unitPrice =
                (float)$product['price'];

            $unitWeight =
                (float)$product['weight_kg'];


            $subtotal =
                $unitPrice * $quantity;

            $itemTotalWeight =
                $unitWeight * $quantity;


            // Insert order item

            $itemStmt->execute([

                $orderId,

                $productId,

                $quantity,

                $unitPrice,

                $unitWeight,

                $itemTotalWeight,

                $subtotal

            ]);


            // Deduct stock

            $stockStmt->execute([

                $quantity,

                $productId,

                $quantity

            ]);


            if ($stockStmt->rowCount() === 0) {

                throw new Exception(
                    'Stock changed while processing your order. Please try again.'
                );
            }
        }


        // --------------------------------------------------
        // COMMIT
        // --------------------------------------------------

        $db->commit();


        redirectOrder(
            'Order ' .
            $orderNumber .
            ' placed successfully.'
        );


    } catch (Throwable $e) {

        if ($db->inTransaction()) {

            $db->rollBack();
        }


        redirectOrder(
            $e->getMessage(),
            'error'
        );
    }
}


// ==========================================================
// FETCH AVAILABLE PRODUCTS
// ==========================================================

$stmt = $db->query("
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

    WHERE status = 'Available'

    AND stock_quantity > 0

    ORDER BY product_name ASC
");


$products = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


// ==========================================================
// NAVIGATION
// ==========================================================

$navItems = [

    [
        'label' => 'Dashboard',
        'url' => BASE_URL . 'customer/dashboard.php'
    ],

    [
        'label' => 'Place Order',
        'url' => BASE_URL . 'customer/order.php'
    ],

    [
        'label' => 'My Orders',
        'url' => BASE_URL . 'customer/orders.php'
    ],

    [
        'label' => 'Notifications',
        'url' => BASE_URL . 'customer/notifications.php'
    ],

    [
        'label' => 'Profile',
        'url' => BASE_URL . 'customer/profile.php'
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

<title>Place Order</title>

<?php require __DIR__ . '/../includes/theme_init.php'; ?>

<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>


<style>

.product-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(240px, 1fr)
        );

    gap: 20px;
}


.product-card {

    border: 1px solid
        var(--color-border);

    border-radius: 12px;

    padding: 18px;

    background:
        var(--color-surface);

    transition:
        transform .15s ease,
        box-shadow .15s ease;
}


.product-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 5px 15px rgba(0,0,0,.08);
}


.product-image {

    width: 100%;

    height: 180px;

    object-fit: cover;

    border-radius: 10px;

    margin-bottom: 15px;

    background: #f3f3f3;
}


.product-placeholder {

    width: 100%;

    height: 180px;

    border-radius: 10px;

    background: #f3f3f3;

    display: flex;

    align-items: center;

    justify-content: center;

    color:
        var(--color-text-secondary);

    margin-bottom: 15px;
}


.product-name {

    font-size: 18px;

    font-weight: 700;

    margin-bottom: 5px;
}


.product-description {

    color:
        var(--color-text-secondary);

    font-size: 14px;

    min-height: 40px;

    margin-bottom: 10px;
}


.product-price {

    font-size: 18px;

    font-weight: 700;

    margin-bottom: 5px;
}


.product-weight {

    font-size: 13px;

    color:
        var(--color-text-secondary);

    margin-bottom: 12px;
}


.quantity-row {

    display: flex;

    align-items: center;

    gap: 10px;
}


.quantity-input {

    width: 90px;
}


.order-summary {

    position: sticky;

    bottom: 15px;

    margin-top: 25px;

    z-index: 10;
}


.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(180px, 1fr)
        );

    gap: 20px;
}


.summary-value {

    font-size: 22px;

    font-weight: 700;
}


</style>

</head>


<body>

<div class="app-layout">

<?php require __DIR__ . '/../includes/header.php'; ?>


<main class="app-main">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="page-header">

    <div>

        <h1>
            Place an Order
        </h1>

        <p>
            Select the products and quantities you want to order.
        </p>

    </div>

</div>


<!-- ==========================================================
     SUCCESS
========================================================== -->

<?php if (!empty($_GET['success'])): ?>

    <div class="alert-success">

        <?= htmlspecialchars(
            $_GET['success']
        ) ?>

    </div>

<?php endif; ?>


<!-- ==========================================================
     ERROR
========================================================== -->

<?php if (!empty($_GET['error'])): ?>

    <div class="alert-error">

        <?= htmlspecialchars(
            $_GET['error']
        ) ?>

    </div>

<?php endif; ?>


<?php if (empty($products)): ?>

    <div class="card panel">

        <div class="empty-state">

            <strong>
                No products are currently available.
            </strong>

            <br>

            Please check again later.

        </div>

    </div>

<?php else: ?>


<form
    method="POST"
    id="orderForm"
>


<!-- ==========================================================
     PRODUCTS
========================================================== -->

<div class="card panel">

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            flex-wrap:wrap;
            margin-bottom:20px;
        "
    >

        <div>

            <h2>
                Available Products
            </h2>

            <p
                style="
                    color:
                    var(--color-text-secondary);
                "
            >
                Choose the products you need.
            </p>

        </div>

    </div>


    <div class="product-grid">


    <?php foreach ($products as $product): ?>

        <?php

        $productId =
            (int)$product['product_id'];

        $stock =
            (int)$product['stock_quantity'];

        $weight =
            (float)$product['weight_kg'];

        $price =
            (float)$product['price'];

        ?>

        <div
            class="product-card"
            data-product-id="<?= $productId ?>"
            data-price="<?= htmlspecialchars(
                $price
            ) ?>"
            data-weight="<?= htmlspecialchars(
                $weight
            ) ?>"
        >


            <!-- PRODUCT IMAGE -->

            <?php if (
                !empty(
                    $product['product_image']
                )
            ): ?>

                <img
                    src="<?= BASE_URL ?>assets/uploads/products/<?= htmlspecialchars(
                        $product['product_image']
                    ) ?>"
                    alt="<?= htmlspecialchars(
                        $product['product_name']
                    ) ?>"
                    class="product-image"
                >

            <?php else: ?>

                <div class="product-placeholder">

                    No Photo

                </div>

            <?php endif; ?>


            <!-- PRODUCT NAME -->

            <div class="product-name">

                <?= htmlspecialchars(
                    $product['product_name']
                ) ?>

            </div>


            <!-- PRODUCT DESCRIPTION -->

            <div class="product-description">

                <?= !empty(
                    $product['description']
                )

                    ? htmlspecialchars(
                        $product['description']
                    )

                    : 'No description available.'
                ?>

            </div>


            <!-- PRICE -->

            <div class="product-price">

                ₱<?= number_format(
                    $price,
                    2
                ) ?>

                / unit

            </div>


            <!-- WEIGHT -->

            <div class="product-weight">

                Weight:
                <?= number_format(
                    $weight,
                    2
                ) ?>
                kg / unit

                <br>

                Available stock:
                <?= $stock ?>

            </div>


            <!-- QUANTITY -->

            <div class="quantity-row">

                <label
                    for="quantity_<?= $productId ?>"
                >

                    Quantity

                </label>


                <input
                    type="number"
                    id="quantity_<?= $productId ?>"
                    name="quantity[]"
                    class="form-input quantity-input"
                    min="0"
                    max="<?= $stock ?>"
                    value="0"
                    data-product-id="<?= $productId ?>"
                >


                <input
                    type="hidden"
                    name="product_id[]"
                    value="<?= $productId ?>"
                >

            </div>


            <!-- ITEM TOTAL -->

            <div
                style="
                    margin-top:12px;
                    font-size:14px;
                "
            >

                Item Total:

                <strong
                    class="item-total"
                    data-product-id="<?= $productId ?>"
                >
                    ₱0.00
                </strong>

                <br>

                Item Weight:

                <strong
                    class="item-weight"
                    data-product-id="<?= $productId ?>"
                >
                    0.00 kg
                </strong>

            </div>


        </div>

    <?php endforeach; ?>


    </div>

</div>


<br>


<!-- ==========================================================
     DELIVERY INFORMATION
========================================================== -->

<div class="card panel">

    <h2>
        Delivery Information
    </h2>

    <br>


    <div
        style="
            display:grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px,1fr)
                );
            gap:20px;
        "
    >


        <!-- DELIVERY DATE -->

        <div>

            <label
                for="expected_delivery_date"
            >
                Expected Delivery Date
            </label>

            <input
                type="date"
                name="expected_delivery_date"
                id="expected_delivery_date"
                class="form-input"
                min="<?= date('Y-m-d') ?>"
                required
            >

        </div>


        <!-- PRIORITY -->

        <div>

            <label
                for="priority_level"
            >
                Priority
            </label>

            <select
                name="priority_level"
                id="priority_level"
                class="form-input"
            >

                <option value="Normal">
                    Normal
                </option>

                <option value="Urgent">
                    Urgent
                </option>

            </select>

        </div>


    </div>

</div>


<!-- ==========================================================
     ORDER SUMMARY
========================================================== -->

<div class="card panel order-summary">

    <h2>
        Order Summary
    </h2>

    <br>


    <div class="summary-grid">


        <div>

            <span class="stat-label">
                Total Items
            </span>

            <div
                class="summary-value"
                id="summaryItems"
            >
                0
            </div>

        </div>


        <div>

            <span class="stat-label">
                Total Weight
            </span>

            <div
                class="summary-value"
                id="summaryWeight"
            >
                0.00 kg
            </div>

        </div>


        <div>

            <span class="stat-label">
                Total Amount
            </span>

            <div
                class="summary-value"
                id="summaryAmount"
            >
                ₱0.00
            </div>

        </div>


    </div>


    <br>


    <button
        type="submit"
        name="place_order"
        class="btn-primary"
        id="placeOrderButton"
    >

        Place Order

    </button>

</div>


</form>


<?php endif; ?>


</main>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const quantityInputs =
            document.querySelectorAll(
                '.quantity-input'
            );


        const summaryItems =
            document.getElementById(
                'summaryItems'
            );

        const summaryWeight =
            document.getElementById(
                'summaryWeight'
            );

        const summaryAmount =
            document.getElementById(
                'summaryAmount'
            );


        function updateSummary() {

            let totalItems = 0;

            let totalWeight = 0;

            let totalAmount = 0;


            quantityInputs.forEach(
                function (input) {

                    const quantity =
                        parseInt(
                            input.value,
                            10
                        ) || 0;


                    const productCard =
                        input.closest(
                            '.product-card'
                        );


                    if (!productCard) {
                        return;
                    }


                    const price =
                        parseFloat(
                            productCard.dataset.price
                        ) || 0;


                    const weight =
                        parseFloat(
                            productCard.dataset.weight
                        ) || 0;


                    const productTotal =
                        price * quantity;

                    const productWeight =
                        weight * quantity;


                    totalItems += quantity;

                    totalAmount +=
                        productTotal;

                    totalWeight +=
                        productWeight;


                    const productId =
                        input.dataset.productId;


                    const itemTotal =
                        document.querySelector(
                            '.item-total[data-product-id="' +
                            productId +
                            '"]'
                        );


                    const itemWeight =
                        document.querySelector(
                            '.item-weight[data-product-id="' +
                            productId +
                            '"]'
                        );


                    if (itemTotal) {

                        itemTotal.textContent =
                            '₱' +
                            productTotal.toLocaleString(
                                'en-PH',
                                {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }
                            );
                    }


                    if (itemWeight) {

                        itemWeight.textContent =
                            productWeight.toFixed(2) +
                            ' kg';
                    }

                }
            );


            summaryItems.textContent =
                totalItems;


            summaryWeight.textContent =
                totalWeight.toFixed(2) +
                ' kg';


            summaryAmount.textContent =
                '₱' +
                totalAmount.toLocaleString(
                    'en-PH',
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );

        }


        quantityInputs.forEach(
            function (input) {

                input.addEventListener(
                    'input',
                    updateSummary
                );

            }
        );


        const orderForm =
            document.getElementById(
                'orderForm'
            );


        if (orderForm) {

            orderForm.addEventListener(
                'submit',
                function (event) {

                    let hasItem = false;


                    quantityInputs.forEach(
                        function (input) {

                            if (
                                parseInt(
                                    input.value,
                                    10
                                ) > 0
                            ) {

                                hasItem = true;

                            }

                        }
                    );


                    if (!hasItem) {

                        event.preventDefault();

                        alert(
                            'Please select at least one product.'
                        );

                        return;

                    }


                    const confirmed =
                        confirm(
                            'Are you sure you want to place this order?'
                        );


                    if (!confirmed) {

                        event.preventDefault();

                    }

                }
            );

        }


        updateSummary();

    }
);

</script>


</body>

</html>