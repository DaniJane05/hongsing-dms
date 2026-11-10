<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

/* =========================================================
   HONG SING LOCATION
========================================================= */

const HONG_SING_LAT  = 14.6749623;
const HONG_SING_LNG  = 120.9874442;
const HONG_SING_NAME = 'Hong Sing LKRR Commercial';


/* =========================================================
   HELPERS
========================================================= */



function formatMoney(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function getCart(): array
{
    if (
        !isset($_SESSION['cart']) ||
        !is_array($_SESSION['cart'])
    ) {
        $_SESSION['cart'] = [];
    }

    return $_SESSION['cart'];
}

function normalizeCartQuantity($quantity): int
{
    return max(0, (int) $quantity);
}

function parseCoordinate($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (float) $value;
}

function formatRouteTime(float $minutes): string
{
    $minutes = max(
        1,
        (int) round($minutes)
    );

    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    if ($hours > 0 && $remaining > 0) {
        return $hours . ' hr ' . $remaining . ' min';
    }

    if ($hours > 0) {
        return $hours . ' hr';
    }

    return $remaining . ' min';
}


/* =========================================================
   URGENT DELIVERY FEE
========================================================= */

function getUrgentDeliveryFee(
    PDO $db,
    float $distanceKm
): float {

    if ($distanceKm <= 0) {
        return 0.0;
    }

    $stmt = $db->prepare("
        SELECT fee
        FROM urgent_delivery_rate
        WHERE is_active = 1
          AND :distance >= min_distance_km
          AND (
              max_distance_km IS NULL
              OR :distance2 <= max_distance_km
          )
        ORDER BY min_distance_km DESC
        LIMIT 1
    ");

    $stmt->execute([
        'distance'  => $distanceKm,
        'distance2' => $distanceKm
    ]);

    $fee = $stmt->fetchColumn();

    if ($fee === false) {
        return 0.0;
    }

    return max(
        0,
        (float) $fee
    );
}


/* =========================================================
   LOAD URGENT RATES
========================================================= */

$urgentDeliveryRates = [];

try {

    $rateStmt = $db->query("
        SELECT
            min_distance_km,
            max_distance_km,
            fee
        FROM urgent_delivery_rate
        WHERE is_active = 1
        ORDER BY min_distance_km ASC
    ");

    $urgentDeliveryRates =
        $rateStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'Urgent delivery rates: '
        . $e->getMessage()
    );
}


/* =========================================================
   GENERATORS
========================================================= */

function generateOrderNumber(PDO $db): string
{
    do {

        $number =
            'HS-' .
            date('Ymd') .
            '-' .
            strtoupper(
                bin2hex(random_bytes(3))
            );

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM `order`
            WHERE order_number = ?
        ");

        $stmt->execute([$number]);

        $exists =
            (int) $stmt->fetchColumn() > 0;

    } while ($exists);

    return $number;
}

function generateRouteCode(PDO $db): string
{
    do {

        $code =
            'RT-' .
            date('Ymd') .
            '-' .
            strtoupper(
                bin2hex(random_bytes(3))
            );

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM route
            WHERE route_code = ?
        ");

        $stmt->execute([$code]);

        $exists =
            (int) $stmt->fetchColumn() > 0;

    } while ($exists);

    return $code;
}


/* =========================================================
   CUSTOMER
========================================================= */

$customerId = currentUserId();

if (!$customerId) {

    header(
        'Location: '
        . BASE_URL
        . 'auth/login.php'
    );

    exit;
}

$customerStmt = $db->prepare("
    SELECT
        customer_id,
        full_name,
        phone_number,
        email,
        address,
        city,
        province,
        latitude,
        longitude
    FROM customer
    WHERE customer_id = ?
    LIMIT 1
");

$customerStmt->execute([$customerId]);

$customer =
    $customerStmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die('Customer account could not be found.');
}


/* =========================================================
   CART
========================================================= */

$cart = getCart();

$errors = [];
$warnings = [];

$checkoutItems = [];
$selectedProductIds = [];


/* =========================================================
   SELECTED PRODUCTS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted =
        $_POST['selected_products']
        ?? [];

    if (is_array($posted)) {

        foreach ($posted as $id) {

            $id = (int) $id;

            if ($id > 0) {
                $selectedProductIds[$id] = $id;
            }
        }
    }
}


/* =========================================================
   LOAD CART PRODUCTS
========================================================= */

if (!empty($cart)) {

    $cartProductIds = [];

    foreach ($cart as $productId => $cartItem) {

        $productId = (int) $productId;

        // cart.php stores each cart entry as an array, so read the
        // quantity from the cart item instead of casting the whole array.
        if (!is_array($cartItem)) {
            continue;
        }

        $quantity = normalizeCartQuantity(
            $cartItem['quantity'] ?? 0
        );

        if ($productId > 0 && $quantity > 0) {
            $cartProductIds[$productId] = $quantity;
        }
    }

    if (!empty($cartProductIds)) {

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($cartProductIds),
                    '?'
                )
            );

        $productStmt = $db->prepare("
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
            WHERE product_id IN ($placeholders)
            ORDER BY product_name ASC
        ");

        $productStmt->execute(
            array_keys($cartProductIds)
        );

        $products =
            $productStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $product) {

            $productId =
                (int) $product['product_id'];

            $quantity =
                $cartProductIds[$productId] ?? 0;

            if (
                ($product['status'] ?? 'Unavailable')
                !== 'Available'
            ) {
                continue;
            }

            if (
                (int) $product['stock_quantity']
                <= 0
            ) {
                continue;
            }

            if (
                $_SERVER['REQUEST_METHOD'] === 'POST' &&
                !isset($selectedProductIds[$productId])
            ) {
                continue;
            }

            $quantity =
                min(
                    $quantity,
                    (int) $product['stock_quantity']
                );

            if ($quantity <= 0) {
                continue;
            }

            $price =
                (float) $product['price'];

            $weight =
                (float) $product['weight_kg'];

            $checkoutItems[] = [

                'product_id' =>
                    $productId,

                'product_code' =>
                    $product['product_code'],

                'product_name' =>
                    $product['product_name'],

                'description' =>
                    $product['description'],

                'product_image' =>
                    $product['product_image'],

                'price' =>
                    $price,

                'weight_kg' =>
                    $weight,

                'stock_quantity' =>
                    (int) $product['stock_quantity'],

                'quantity' =>
                    $quantity,

                'subtotal' =>
                    $price * $quantity,

                'total_weight' =>
                    $weight * $quantity
            ];
        }
    }
}


/* =========================================================
   DEFAULT SELECTION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    foreach ($checkoutItems as $item) {

        $id =
            (int) $item['product_id'];

        $selectedProductIds[$id] = $id;
    }
}


/* =========================================================
   FORM VALUES
========================================================= */

$fullAddress =
    trim(
        $_POST['full_address']
        ??
        ($customer['address'] ?? '')
    );

$city =
    trim(
        $_POST['city']
        ??
        ($customer['city'] ?? '')
    );

$province =
    trim(
        $_POST['province']
        ??
        ($customer['province'] ?? '')
    );

$contactPerson =
    trim(
        $_POST['contact_person']
        ??
        ($customer['full_name'] ?? '')
    );

$contactNumber =
    trim(
        $_POST['contact_number']
        ??
        ($customer['phone_number'] ?? '')
    );

$priorityLevel =
    trim(
        $_POST['priority_level']
        ?? 'Normal'
    );

$latitude =
    parseCoordinate(
        $_POST['latitude']
        ??
        ($customer['latitude'] ?? null)
    );

$longitude =
    parseCoordinate(
        $_POST['longitude']
        ??
        ($customer['longitude'] ?? null)
    );

$routeDistanceKm =
    isset($_POST['route_distance_km'])
    &&
    is_numeric($_POST['route_distance_km'])
        ?
        (float) $_POST['route_distance_km']
        :
        0;

$routeDurationMinutes =
    isset($_POST['route_duration_minutes'])
    &&
    is_numeric($_POST['route_duration_minutes'])
        ?
        (float) $_POST['route_duration_minutes']
        :
        0;


/* =========================================================
   TOTALS
========================================================= */

$subtotal = 0;
$totalWeightKg = 0;
$totalQuantity = 0;

foreach ($checkoutItems as $item) {

    $subtotal +=
        (float) $item['subtotal'];

    $totalWeightKg +=
        (float) $item['total_weight'];

    $totalQuantity +=
        (int) $item['quantity'];
}

$urgentFee = 0;

if (
    $priorityLevel === 'Urgent' &&
    $routeDistanceKm > 0
) {

    try {

        $urgentFee =
            getUrgentDeliveryFee(
                $db,
                $routeDistanceKm
            );

    } catch (Throwable $e) {

        error_log(
            'Urgent fee calculation: '
            . $e->getMessage()
        );
    }
}

$totalAmount =
    $subtotal + $urgentFee;


/* =========================================================
   VALIDATION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($selectedProductIds)) {

        $errors[] =
            'Please select at least one product.';
    }

    if (empty($checkoutItems)) {

        $errors[] =
            'No valid products were selected.';
    }

    if ($fullAddress === '') {

        $errors[] =
            'Please enter the complete delivery address.';
    }

    if ($city === '') {

        $errors[] =
            'Please enter the city.';
    }

    if ($province === '') {

        $errors[] =
            'Please enter the province.';
    }

    if ($contactPerson === '') {

        $errors[] =
            'Please enter the contact person.';
    }

    if ($contactNumber === '') {

        $errors[] =
            'Please enter the contact number.';
    }

    if (
        !in_array(
            $priorityLevel,
            ['Normal', 'Urgent'],
            true
        )
    ) {

        $errors[] =
            'Invalid delivery priority.';
    }

    if (
        $latitude === null ||
        $longitude === null
    ) {

        $errors[] =
            'Please select your delivery location on the map.';
    }

    if (
        $latitude !== null &&
        $longitude !== null &&
        (
            $latitude < 4 ||
            $latitude > 22 ||
            $longitude < 116 ||
            $longitude > 127
        )
    ) {

        $errors[] =
            'Invalid delivery coordinates.';
    }

    if ($routeDistanceKm <= 0) {

        $errors[] =
            'Please calculate the delivery route.';
    }

    if ($routeDurationMinutes <= 0) {

        $errors[] =
            'Please calculate the estimated travel time.';
    }

    if ($routeDistanceKm > 1000) {

        $errors[] =
            'Invalid route distance.';
    }

    if ($routeDurationMinutes > 1440) {

        $errors[] =
            'Invalid route duration.';
    }
}


/* =========================================================
   CREATE ORDER
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    empty($errors)
) {

    try {

        $db->beginTransaction();

        $lockedItems = [];

        foreach ($checkoutItems as $item) {

            $productId =
                (int) $item['product_id'];

            $quantity =
                (int) $item['quantity'];

            $lockStmt = $db->prepare("
                SELECT
                    product_id,
                    product_code,
                    product_name,
                    price,
                    weight_kg,
                    stock_quantity,
                    status
                FROM product
                WHERE product_id = ?
                FOR UPDATE
            ");

            $lockStmt->execute([$productId]);

            $lockedProduct =
                $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedProduct) {

                throw new RuntimeException(
                    'One of the selected products could not be found.'
                );
            }

            if (
                ($lockedProduct['status'] ?? '')
                !== 'Available'
            ) {

                throw new RuntimeException(
                    $lockedProduct['product_name']
                    . ' is no longer available.'
                );
            }

            if (
                (int) $lockedProduct['stock_quantity']
                <
                $quantity
            ) {

                throw new RuntimeException(
                    $lockedProduct['product_name']
                    . ' has insufficient stock.'
                );
            }

            $price =
                (float) $lockedProduct['price'];

            $weight =
                (float) $lockedProduct['weight_kg'];

            $lockedItems[] = [

                'product_id' =>
                    (int) $lockedProduct['product_id'],

                'product_code' =>
                    $lockedProduct['product_code'],

                'product_name' =>
                    $lockedProduct['product_name'],

                'price' =>
                    $price,

                'weight_kg' =>
                    $weight,

                'quantity' =>
                    $quantity,

                'subtotal' =>
                    $price * $quantity,

                'total_weight' =>
                    $weight * $quantity
            ];
        }


        /* =====================================================
           FINAL TOTALS
        ===================================================== */

        $finalSubtotal = 0;
        $finalWeightKg = 0;

        foreach ($lockedItems as $item) {

            $finalSubtotal +=
                $item['subtotal'];

            $finalWeightKg +=
                $item['total_weight'];
        }

        $finalUrgentFee = 0;

        if ($priorityLevel === 'Urgent') {

            $finalUrgentFee =
                getUrgentDeliveryFee(
                    $db,
                    $routeDistanceKm
                );
        }

        $finalTotalAmount =
            $finalSubtotal
            +
            $finalUrgentFee;


        /* =====================================================
           ORDER DESCRIPTION
        ===================================================== */

        $descriptionParts = [];

        foreach ($lockedItems as $item) {

            $descriptionParts[] =
                $item['product_name']
                . ' x '
                . $item['quantity'];
        }

        $itemDescription =
            implode(
                ', ',
                $descriptionParts
            );


        /* =====================================================
           ROUTE
        ===================================================== */

        $routeCode =
            generateRouteCode($db);

        $routeTimeText =
            formatRouteTime(
                $routeDurationMinutes
            );

        $pathDetails =
            HONG_SING_NAME
            . ' → '
            . $fullAddress
            . ', '
            . $city
            . ', '
            . $province
            . PHP_EOL
            . 'Origin coordinates: '
            . HONG_SING_LAT
            . ', '
            . HONG_SING_LNG
            . PHP_EOL
            . 'Destination coordinates: '
            . $latitude
            . ', '
            . $longitude
            . PHP_EOL
            . 'Road distance: '
            . number_format(
                $routeDistanceKm,
                2
            )
            . ' km'
            . PHP_EOL
            . 'Estimated travel time: '
            . $routeTimeText
            . PHP_EOL
            . 'Routing provider: OSRM';

        $routeStmt = $db->prepare("
            INSERT INTO route (
                route_code,
                path_details,
                estimated_distance,
                estimated_time,
                waypoints
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $routeStmt->execute([
            $routeCode,
            $pathDetails,
            $routeDistanceKm,
            $routeTimeText,
            2
        ]);

        $routeId =
            (int) $db->lastInsertId();

        if ($routeId <= 0) {

            throw new RuntimeException(
                'Failed to create route.'
            );
        }


        /* =====================================================
           ORDER
        ===================================================== */

        $orderNumber =
            generateOrderNumber($db);

        $orderStmt = $db->prepare("
            INSERT INTO `order` (
                order_number,
                customer_id,
                driver_id,
                truck_id,
                route_id,
                item_description,
                subtotal,
                urgent_fee,
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
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                CURDATE(),
                NULL,
                ?,
                'Pending'
            )
        ");

        $orderStmt->execute([
            $orderNumber,
            $customerId,
            $routeId,
            $itemDescription,
            $finalSubtotal,
            $finalUrgentFee,
            $finalTotalAmount,
            $finalWeightKg,
            $finalWeightKg,
            $priorityLevel
        ]);

        $orderId =
            (int) $db->lastInsertId();

        if ($orderId <= 0) {

            throw new RuntimeException(
                'Failed to create order.'
            );
        }


        /* =====================================================
           DELIVERY LOCATION
        ===================================================== */

        $locationStmt = $db->prepare("
            INSERT INTO delivery_location (
                order_id,
                full_address,
                city,
                province,
                latitude,
                longitude,
                contact_person,
                contact_number
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $locationStmt->execute([
            $orderId,
            $fullAddress,
            $city,
            $province,
            $latitude,
            $longitude,
            $contactPerson,
            $contactNumber
        ]);


        /* =====================================================
           ORDER ITEMS
           Uses your actual current order_item structure.
        ===================================================== */

        $orderItemStmt = $db->prepare("
            INSERT INTO order_item (
                order_id,
                product_id,
                quantity,
                unit_price,
                subtotal
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stockStmt = $db->prepare("
            UPDATE product
            SET stock_quantity =
                stock_quantity - ?
            WHERE product_id = ?
              AND stock_quantity >= ?
        ");

        foreach ($lockedItems as $item) {

            $orderItemStmt->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            ]);

            $stockStmt->execute([
                $item['quantity'],
                $item['product_id'],
                $item['quantity']
            ]);

            if ($stockStmt->rowCount() !== 1) {

                throw new RuntimeException(
                    'Stock could not be updated for '
                    . $item['product_name']
                    . '.'
                );
            }
        }


        /* =====================================================
           INITIAL STATUS
        ===================================================== */

        $statusStmt = $db->prepare("
            INSERT INTO delivery_status (
                order_id,
                status,
                timestamp,
                notes
            )
            VALUES (
                ?,
                'Pending',
                NOW(),
                ?
            )
        ");

        $statusNote =
            $priorityLevel === 'Urgent'
                ?
                'Urgent order placed by customer. Waiting for admin approval.'
                :
                'Order placed by customer. Waiting for admin approval.';

        $statusStmt->execute([
            $orderId,
            $statusNote
        ]);


        /* =====================================================
           OPTIONAL NOTIFICATION
        ===================================================== */

        try {

            $notificationStmt = $db->prepare("
                INSERT INTO notification (
                    user_id,
                    recipient_type,
                    recipient_id,
                    channel,
                    status,
                    message
                )
                VALUES (
                    ?,
                    'Customer',
                    ?,
                    'In-App',
                    'Sent',
                    ?
                )
            ");

            $message =
                'Your order '
                . $orderNumber
                . ' has been placed successfully and is waiting for approval.';

            if ($priorityLevel === 'Urgent') {

                $message .=
                    ' Urgent delivery fee: '
                    . formatMoney($finalUrgentFee)
                    . '.';
            }

            $notificationStmt->execute([
                $customerId,
                $customerId,
                $message
            ]);

        } catch (Throwable $notificationError) {

            error_log(
                'Checkout notification: '
                . $notificationError->getMessage()
            );
        }


        /* =====================================================
           COMMIT
        ===================================================== */

        $db->commit();


        /* =====================================================
           REMOVE PURCHASED ITEMS
        ===================================================== */

        foreach ($selectedProductIds as $id) {

            unset(
                $_SESSION['cart'][$id]
            );
        }

        if (empty($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }


        /* =====================================================
           SUCCESS SESSION
        ===================================================== */

        $_SESSION['order_success'] = [

            'order_id' =>
                $orderId,

            'order_number' =>
                $orderNumber,

            'route_id' =>
                $routeId,

            'route_code' =>
                $routeCode,

            'route_distance_km' =>
                $routeDistanceKm,

            'route_duration_minutes' =>
                $routeDurationMinutes,

            'subtotal' =>
                $finalSubtotal,

            'urgent_fee' =>
                $finalUrgentFee,

            'total_amount' =>
                $finalTotalAmount,

            'total_weight_kg' =>
                $finalWeightKg,

            'priority_level' =>
                $priorityLevel
        ];

        header(
            'Location: '
            . BASE_URL
            . 'customer/order_success.php'
        );

        exit;

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $errors[] =
            $e->getMessage();
    }
}


/* =========================================================
   NAVIGATION
========================================================= */

$navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL
            . 'customer/dashboard.php'
    ],

    [
        'label' => 'Cart',
        'url' =>
            BASE_URL
            . 'customer/cart.php'
    ],

    [
        'label' => 'My Orders',
        'url' =>
            BASE_URL
            . 'customer/orders.php'
    ],

    [
        'label' => 'Track Delivery',
        'url' =>
            BASE_URL
            . 'customer/track_delivery.php'
    ],

    [
        'label' => 'Feedback',
        'url' =>
            BASE_URL
            . 'customer/feedback.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL
            . 'customer/notifications.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL
            . 'customer/profile.php'
    ]
];

require_once __DIR__ . '/../includes/header.php';

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.checkout-page {
    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
}

.checkout-header {
    margin-bottom: 25px;
}

.checkout-header h1 {
    margin: 0 0 7px;
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text-primary);
}

.checkout-header p {
    margin: 0;
    color: var(--color-text-secondary);
}

.checkout-layout {
    display: grid;
    grid-template-columns:
        minmax(0, 1.6fr)
        minmax(330px, .8fr);
    gap: 24px;
    align-items: start;
}

.checkout-left,
.checkout-right {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.checkout-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 24px;
}

.checkout-card h2 {
    margin: 0 0 18px;
    font-size: 20px;
    color: var(--color-text-primary);
}


/* =========================================================
   ALERTS
========================================================= */

.checkout-alert {
    padding: 14px 16px;
    margin-bottom: 20px;
    border-radius: var(--radius-md);
}

.checkout-alert ul {
    margin: 0;
    padding-left: 20px;
}

.checkout-alert-error {
    color: var(--color-danger);
    background: rgba(220,53,69,.08);
    border: 1px solid rgba(220,53,69,.25);
}


/* =========================================================
   PRODUCTS
========================================================= */

.product-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.checkout-product {
    display: grid;
    grid-template-columns:
        72px
        minmax(0,1fr)
        auto;
    gap: 14px;
    align-items: center;
    padding: 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface-alt);
}

.checkout-product-image {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid var(--color-border);
}

.product-placeholder {
    width: 72px;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    font-size: 11px;
}

.checkout-product-name {
    font-weight: 800;
    color: var(--color-text-primary);
    margin-bottom: 5px;
}

.checkout-product-code {
    font-size: 12px;
    color: var(--color-text-muted);
    margin-bottom: 6px;
}

.checkout-product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 12px;
    color: var(--color-text-secondary);
}

.checkout-product-price {
    text-align: right;
    white-space: nowrap;
}

.checkout-product-price strong {
    display: block;
    color: var(--color-text-primary);
}

.checkout-product-price span {
    font-size: 12px;
    color: var(--color-text-muted);
}


/* =========================================================
   FORM
========================================================= */

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text-primary);
}

.form-control {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 13px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    color: var(--color-text-primary);
    font: inherit;
    outline: none;
}

.form-control:focus {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px var(--color-accent-soft);
}

textarea.form-control {
    min-height: 90px;
    resize: vertical;
}


/* =========================================================
   PRIORITY
========================================================= */

.priority-options {
    display: grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap: 14px;
}

.priority-option {
    position: relative;
    cursor: pointer;
}

.priority-option input {
    position: absolute;
    opacity: 0;
}

.priority-card {
    height: 100%;
    box-sizing: border-box;
    padding: 18px;
    border: 2px solid var(--color-border);
    border-radius: 12px;
    transition: .2s ease;
}

.priority-option input:checked + .priority-card {
    border-color: var(--color-accent);
    background: var(--color-accent-soft);
}

.priority-title {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-weight: 800;
    color: var(--color-text-primary);
}

.priority-description {
    margin: 10px 0 0;
    font-size: 12px;
    line-height: 1.6;
    color: var(--color-text-secondary);
}

.priority-fee {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--color-border);
    font-weight: 700;
    font-size: 13px;
    color: var(--color-text-primary);
}

.badge-normal,
.badge-urgent {
    font-size: 10px;
    padding: 4px 7px;
    border-radius: 999px;
}

.badge-normal {
    color: var(--color-accent);
    background: rgba(59,130,246,.12);
}

.badge-urgent {
    color: #b45309;
    background: rgba(245,158,11,.14);
}


/* =========================================================
   LOCATION SEARCH
========================================================= */

.location-search {
    display: grid;
    grid-template-columns:
        minmax(0,1fr)
        auto;
    gap: 10px;
    margin-bottom: 12px;
}

.search-results {
    display: none;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    margin-bottom: 12px;
    overflow: hidden;
}

.search-results.show {
    display: block;
}

.search-result {
    width: 100%;
    border: 0;
    border-bottom: 1px solid var(--color-border);
    padding: 11px;
    text-align: left;
    cursor: pointer;
    background: var(--color-surface);
    color: var(--color-text-primary);
}

.search-result:last-child {
    border-bottom: 0;
}

.search-result:hover {
    background: var(--color-surface-alt);
}


/* =========================================================
   MAP
========================================================= */

#deliveryMap {
    width: 100%;
    height: 450px;
    min-height: 450px;
    border-radius: 14px;
    border: 1px solid var(--color-border);
    overflow: hidden;
    background: #eef2f7;
    z-index: 1;
}


/* =========================================================
   CUSTOM MAP PINS
========================================================= */

.custom-map-icon {
    background: transparent !important;
    border: none !important;
}

.map-pin {
    position: relative;
    width: 40px;
    height: 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50% 50% 50% 0;

    transform: rotate(-45deg);

    border: 3px solid white;

    box-shadow:
        0 4px 12px rgba(0,0,0,.28);
}

.map-pin-inner {
    transform: rotate(45deg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.map-pin-inner svg {
    width: 21px;
    height: 21px;
}

/* Warehouse */
.pin-warehouse {
    background: #16a34a;
}

/* Customer */
.pin-customer {
    background: #dc3545;
}

/* Driver - for tracking page */
.pin-driver {
    background: #2563eb;
}


/* =========================================================
   MAP LEGEND
========================================================= */

.map-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 12px;
    padding: 11px 13px;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-surface-alt);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: var(--color-text-secondary);
}

.legend-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
}

.legend-green {
    background: #16a34a;
}

.legend-red {
    background: #dc3545;
}

.legend-blue {
    background: #2563eb;
}


/* =========================================================
   ROUTE
========================================================= */

.route-status {
    margin-top: 12px;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid var(--color-border);
    background: var(--color-surface-alt);
    color: var(--color-text-secondary);
    font-size: 13px;
}

.route-status.success {
    color: var(--color-success);
}

.route-status.error {
    color: var(--color-danger);
}

.route-metrics {
    display: none;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap: 12px;
    margin-top: 12px;
}

.route-metric {
    padding: 13px;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-surface-alt);
}

.route-metric span {
    display: block;
    font-size: 11px;
    color: var(--color-text-muted);
    margin-bottom: 5px;
}

.route-metric strong {
    color: var(--color-text-primary);
}


/* =========================================================
   SUMMARY
========================================================= */

.checkout-right {
    position: sticky;
    top: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 8px 0;
    color: var(--color-text-secondary);
    font-size: 13px;
}

.summary-value {
    font-weight: 700;
    color: var(--color-text-primary);
    text-align: right;
}

.summary-divider {
    height: 1px;
    background: var(--color-border);
    margin: 12px 0;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-top: 10px;
    padding-top: 14px;
    border-top: 1px solid var(--color-border);
}

.summary-total span {
    font-weight: 800;
    color: var(--color-text-primary);
}

.summary-total strong {
    font-size: 22px;
    color: var(--color-text-primary);
}

.checkout-submit {
    width: 100%;
    margin-top: 18px;
}

.checkout-note {
    margin-top: 12px;
    padding: 11px;
    border-radius: 9px;
    background: var(--color-surface-alt);
    font-size: 11px;
    line-height: 1.6;
    color: var(--color-text-secondary);
}

.empty-checkout {
    text-align: center;
    padding: 35px;
}


/* =========================================================
   BUTTON
========================================================= */

.checkout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: var(--radius-md);
    padding: 11px 15px;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    background: var(--color-accent);
    color: white;
}

.checkout-btn:hover {
    background: var(--color-accent-hover);
}

.checkout-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .checkout-layout {
        grid-template-columns: 1fr;
    }

    .checkout-right {
        position: static;
    }
}

@media (max-width: 700px) {

    .checkout-card {
        padding: 17px;
    }

    .form-grid,
    .priority-options,
    .route-metrics {
        grid-template-columns: 1fr;
    }

    .checkout-product {
        grid-template-columns:
            60px minmax(0,1fr);
    }

    .checkout-product-image,
    .product-placeholder {
        width: 60px;
        height: 60px;
    }

    .checkout-product-price {
        grid-column: 2;
        text-align: left;
    }

    .location-search {
        grid-template-columns: 1fr;
    }

    #deliveryMap {
        height: 350px;
        min-height: 350px;
    }
}

</style>


<main class="app-main">

<div class="checkout-page">

    <div class="checkout-header">

        <h1>Checkout</h1>

        <p>
            Confirm your products, delivery information,
            priority and exact delivery location.
        </p>

    </div>


    <?php if (!empty($errors)): ?>

        <div class="checkout-alert checkout-alert-error">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <?php if (!empty($checkoutItems)): ?>

        <form
            method="POST"
            id="checkoutForm"
        >

            <?php foreach ($checkoutItems as $item): ?>

                <input
                    type="hidden"
                    name="selected_products[]"
                    value="<?= (int) $item['product_id'] ?>"
                >

            <?php endforeach; ?>


            <input
                type="hidden"
                name="latitude"
                id="latitude"
                value="<?= e(
                    $latitude !== null
                        ? $latitude
                        : ''
                ) ?>"
            >

            <input
                type="hidden"
                name="longitude"
                id="longitude"
                value="<?= e(
                    $longitude !== null
                        ? $longitude
                        : ''
                ) ?>"
            >

            <input
                type="hidden"
                name="route_distance_km"
                id="route_distance_km"
                value="<?= e(
                    $routeDistanceKm > 0
                        ? $routeDistanceKm
                        : ''
                ) ?>"
            >

            <input
                type="hidden"
                name="route_duration_minutes"
                id="route_duration_minutes"
                value="<?= e(
                    $routeDurationMinutes > 0
                        ? $routeDurationMinutes
                        : ''
                ) ?>"
            >


            <div class="checkout-layout">


                <!-- ==========================================
                     LEFT
                =========================================== -->

                <div class="checkout-left">


                    <!-- PRODUCTS -->

                    <section class="checkout-card">

                        <h2>Products</h2>

                        <div class="product-list">

                            <?php foreach ($checkoutItems as $item): ?>

                                <div class="checkout-product">


                                    <?php if (!empty($item['product_image'])): ?>

                                        <img
                                            src="<?= e(
                                                BASE_URL
                                                . 'assets/uploads/products/'
                                                . rawurlencode(basename((string) $item['product_image']))
                                            ) ?>"
                                            class="checkout-product-image"
                                            alt="<?= e($item['product_name']) ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        >
                                        <div
                                            class="product-placeholder"
                                            style="display:none;"
                                        >
                                            No Image
                                        </div>

                                    <?php else: ?>

                                        <div class="product-placeholder">
                                            No Image
                                        </div>

                                    <?php endif; ?>


                                    <div>

                                        <div class="checkout-product-name">

                                            <?= e(
                                                $item['product_name']
                                            ) ?>

                                        </div>

                                        <div class="checkout-product-code">

                                            <?= e(
                                                $item['product_code']
                                            ) ?>

                                        </div>

                                        <div class="checkout-product-meta">

                                            <span>
                                                Qty:
                                                <?= (int) $item['quantity'] ?>
                                            </span>

                                            <span>
                                                <?= formatMoney(
                                                    (float) $item['price']
                                                ) ?>
                                                each
                                            </span>

                                            <span>
                                                <?= number_format(
                                                    (float) $item['total_weight'],
                                                    2
                                                ) ?>
                                                kg
                                            </span>

                                        </div>

                                    </div>


                                    <div class="checkout-product-price">

                                        <strong>
                                            <?= formatMoney(
                                                (float) $item['subtotal']
                                            ) ?>
                                        </strong>

                                        <span>
                                            Item subtotal
                                        </span>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </section>


                    <!-- DELIVERY INFORMATION -->

                    <section class="checkout-card">

                        <h2>Delivery Information</h2>

                        <div class="form-grid">

                            <div class="form-group">

                                <label for="contact_person">
                                    Contact Person
                                </label>

                                <input
                                    type="text"
                                    name="contact_person"
                                    id="contact_person"
                                    class="form-control"
                                    value="<?= e($contactPerson) ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="contact_number">
                                    Contact Number
                                </label>

                                <input
                                    type="text"
                                    name="contact_number"
                                    id="contact_number"
                                    class="form-control"
                                    value="<?= e($contactNumber) ?>"
                                    required
                                >

                            </div>


                            <div class="form-group full">

                                <label for="full_address">
                                    Complete Address
                                </label>

                                <textarea
                                    name="full_address"
                                    id="full_address"
                                    class="form-control"
                                    required
                                ><?= e($fullAddress) ?></textarea>

                            </div>


                            <div class="form-group">

                                <label for="city">
                                    City
                                </label>

                                <input
                                    type="text"
                                    name="city"
                                    id="city"
                                    class="form-control"
                                    value="<?= e($city) ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="province">
                                    Province
                                </label>

                                <input
                                    type="text"
                                    name="province"
                                    id="province"
                                    class="form-control"
                                    value="<?= e($province) ?>"
                                    required
                                >

                            </div>

                        </div>

                    </section>


                    <!-- PRIORITY -->

                    <section class="checkout-card">

                        <h2>Delivery Priority</h2>

                        <div class="priority-options">


                            <label class="priority-option">

                                <input
                                    type="radio"
                                    name="priority_level"
                                    value="Normal"
                                    <?= $priorityLevel === 'Normal'
                                        ? 'checked'
                                        : '' ?>
                                >

                                <div class="priority-card">

                                    <div class="priority-title">

                                        <span>
                                            Normal Delivery
                                        </span>

                                        <span class="badge-normal">
                                            Standard
                                        </span>

                                    </div>

                                    <p class="priority-description">

                                        Standard processing based on
                                        the available delivery schedule.

                                    </p>

                                    <div class="priority-fee">
                                        Additional Fee: ₱0.00
                                    </div>

                                </div>

                            </label>


                            <label class="priority-option">

                                <input
                                    type="radio"
                                    name="priority_level"
                                    value="Urgent"
                                    <?= $priorityLevel === 'Urgent'
                                        ? 'checked'
                                        : '' ?>
                                >

                                <div class="priority-card">

                                    <div class="priority-title">

                                        <span>
                                            Urgent Delivery
                                        </span>

                                        <span class="badge-urgent">
                                            Priority
                                        </span>

                                    </div>

                                    <p class="priority-description">

                                        Prioritizes the order during
                                        scheduling and assignment.
                                        The fee depends on road distance.

                                    </p>

                                    <div class="priority-fee">

                                        Urgent Fee:

                                        <span id="urgentOptionFee">
                                            Calculate route first
                                        </span>

                                    </div>

                                </div>

                            </label>

                        </div>

                    </section>


                    <!-- LOCATION -->

                    <section class="checkout-card">

                        <h2>Delivery Location</h2>

                        <div class="location-search">

                            <input
                                type="text"
                                id="mapSearch"
                                class="form-control"
                                placeholder="Search delivery address..."
                            >

                            <button
                                type="button"
                                id="searchLocationBtn"
                                class="checkout-btn"
                            >
                                Search
                            </button>

                        </div>


                        <div
                            id="searchResults"
                            class="search-results"
                        ></div>


                        <div id="deliveryMap"></div>


                        <!-- MAP LEGEND -->

                        <div class="map-legend">

                            <div class="legend-item">

                                <span
                                    class="legend-dot legend-green"
                                ></span>

                                Hong Sing / Warehouse

                            </div>

                            <div class="legend-item">

                                <span
                                    class="legend-dot legend-red"
                                ></span>

                                Customer Location

                            </div>

                        </div>


                        <div
                            class="route-status"
                            id="routeStatus"
                        >

                            Select or search your exact delivery
                            location to calculate the road route.

                        </div>


                        <div
                            class="route-metrics"
                            id="routeMetrics"
                        >

                            <div class="route-metric">

                                <span>
                                    Road Distance
                                </span>

                                <strong id="routeDistanceDisplay">
                                    —
                                </strong>

                            </div>


                            <div class="route-metric">

                                <span>
                                    Estimated Travel Time
                                </span>

                                <strong id="routeDurationDisplay">
                                    —
                                </strong>

                            </div>

                        </div>

                    </section>

                </div>


                <!-- ==========================================
                     RIGHT / SUMMARY
                =========================================== -->

                <div class="checkout-right">

                    <section class="checkout-card">

                        <h2>Order Summary</h2>


                        <div class="summary-row">

                            <span>
                                Products
                            </span>

                            <span class="summary-value">
                                <?= count($checkoutItems) ?>
                            </span>

                        </div>


                        <div class="summary-row">

                            <span>
                                Total Quantity
                            </span>

                            <span class="summary-value">
                                <?= (int) $totalQuantity ?>
                            </span>

                        </div>


                        <div class="summary-row">

                            <span>
                                Total Weight
                            </span>

                            <span class="summary-value">
                                <?= number_format(
                                    $totalWeightKg,
                                    2
                                ) ?>
                                kg
                            </span>

                        </div>


                        <div class="summary-row">

                            <span>
                                Road Distance
                            </span>

                            <span
                                class="summary-value"
                                id="summaryDistance"
                            >
                                <?= $routeDistanceKm > 0
                                    ?
                                    number_format(
                                        $routeDistanceKm,
                                        2
                                    ) . ' km'
                                    :
                                    '—' ?>
                            </span>

                        </div>


                        <div class="summary-row">

                            <span>
                                Estimated Travel Time
                            </span>

                            <span
                                class="summary-value"
                                id="summaryDuration"
                            >

                                <?= $routeDurationMinutes > 0
                                    ?
                                    e(
                                        formatRouteTime(
                                            $routeDurationMinutes
                                        )
                                    )
                                    :
                                    '—' ?>

                            </span>

                        </div>


                        <div class="summary-divider"></div>


                        <div class="summary-row">

                            <span>
                                Subtotal
                            </span>

                            <span class="summary-value">

                                <?= formatMoney(
                                    $subtotal
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="summary-row"
                            id="urgentFeeRow"
                            style="<?= $priorityLevel === 'Urgent'
                                ? ''
                                : 'display:none;' ?>"
                        >

                            <span>
                                Urgent Delivery Fee
                            </span>

                            <span
                                class="summary-value"
                                id="summaryUrgentFee"
                            >

                                <?= formatMoney(
                                    $urgentFee
                                ) ?>

                            </span>

                        </div>


                        <div class="summary-total">

                            <span>
                                Total
                            </span>

                            <strong id="summaryTotal">

                                <?= formatMoney(
                                    $totalAmount
                                ) ?>

                            </strong>

                        </div>


                        <button
                            type="submit"
                            id="placeOrderBtn"
                            class="checkout-btn checkout-submit"
                        >
                            Place Order
                        </button>


                        <div
                            class="checkout-note"
                            id="priorityNote"
                        >

                            <?php if ($priorityLevel === 'Urgent'): ?>

                                Urgent delivery gives your order
                                priority handling but does not
                                guarantee an exact delivery date
                                or time.

                            <?php else: ?>

                                Your order will follow the normal
                                scheduling and assignment process.

                            <?php endif; ?>

                        </div>

                    </section>

                </div>

            </div>

        </form>


    <?php else: ?>

        <section class="checkout-card">

            <div class="empty-checkout">

                <h2>
                    No products selected
                </h2>

                <p>
                    Return to your cart and select
                    products before checking out.
                </p>

                <a
                    href="<?= BASE_URL ?>customer/cart.php"
                    class="checkout-btn"
                >
                    Back to Cart
                </a>

            </div>

        </section>

    <?php endif; ?>

</div>

</main>


<!-- =========================================================
     LEAFLET
========================================================= -->

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


/* =========================================================
   PHP DATA
========================================================= */

const HONG_SING_LAT =
    <?= json_encode(HONG_SING_LAT) ?>;

const HONG_SING_LNG =
    <?= json_encode(HONG_SING_LNG) ?>;

const PRODUCT_SUBTOTAL =
    <?= json_encode(
        round($subtotal, 2)
    ) ?>;

const URGENT_RATES =
    <?= json_encode(
        array_map(
            static function ($rate) {

                return [

                    'min' =>
                        (float)
                        $rate['min_distance_km'],

                    'max' =>
                        $rate['max_distance_km'] === null
                            ?
                            null
                            :
                            (float)
                            $rate['max_distance_km'],

                    'fee' =>
                        (float)
                        $rate['fee']
                ];
            },
            $urgentDeliveryRates
        ),
        JSON_UNESCAPED_UNICODE
        |
        JSON_UNESCAPED_SLASHES
    ) ?>;


/* =========================================================
   ELEMENTS
========================================================= */

const form =
    document.getElementById(
        'checkoutForm'
    );

if (!form) {
    return;
}

const latitudeInput =
    document.getElementById(
        'latitude'
    );

const longitudeInput =
    document.getElementById(
        'longitude'
    );

const routeDistanceInput =
    document.getElementById(
        'route_distance_km'
    );

const routeDurationInput =
    document.getElementById(
        'route_duration_minutes'
    );

const routeStatus =
    document.getElementById(
        'routeStatus'
    );

const routeMetrics =
    document.getElementById(
        'routeMetrics'
    );

const routeDistanceDisplay =
    document.getElementById(
        'routeDistanceDisplay'
    );

const routeDurationDisplay =
    document.getElementById(
        'routeDurationDisplay'
    );

const summaryDistance =
    document.getElementById(
        'summaryDistance'
    );

const summaryDuration =
    document.getElementById(
        'summaryDuration'
    );

const summaryUrgentFee =
    document.getElementById(
        'summaryUrgentFee'
    );

const summaryTotal =
    document.getElementById(
        'summaryTotal'
    );

const urgentFeeRow =
    document.getElementById(
        'urgentFeeRow'
    );

const urgentOptionFee =
    document.getElementById(
        'urgentOptionFee'
    );

const priorityNote =
    document.getElementById(
        'priorityNote'
    );

const mapSearch =
    document.getElementById(
        'mapSearch'
    );

const searchLocationBtn =
    document.getElementById(
        'searchLocationBtn'
    );

const searchResults =
    document.getElementById(
        'searchResults'
    );

const fullAddress =
    document.getElementById(
        'full_address'
    );

const city =
    document.getElementById(
        'city'
    );

const province =
    document.getElementById(
        'province'
    );

const placeOrderBtn =
    document.getElementById(
        'placeOrderBtn'
    );


/* =========================================================
   MONEY
========================================================= */

function peso(value) {

    return new Intl.NumberFormat(
        'en-PH',
        {
            style: 'currency',
            currency: 'PHP'
        }
    ).format(
        Number(value) || 0
    );
}


/* =========================================================
   PRIORITY
========================================================= */

function getPriority() {

    const selected =
        document.querySelector(
            'input[name="priority_level"]:checked'
        );

    return selected
        ? selected.value
        : 'Normal';
}


/* =========================================================
   URGENT FEE
========================================================= */

function getUrgentFee(distance) {

    distance =
        Number(distance);

    if (
        !Number.isFinite(distance)
        ||
        distance <= 0
    ) {
        return 0;
    }

    for (const rate of URGENT_RATES) {

        const min =
            Number(rate.min);

        const max =
            rate.max === null
                ? null
                : Number(rate.max);

        if (
            distance >= min
            &&
            (
                max === null
                ||
                distance <= max
            )
        ) {

            return Number(rate.fee) || 0;
        }
    }

    return 0;
}


/* =========================================================
   UPDATE SUMMARY
========================================================= */

function updateSummary() {

    const priority =
        getPriority();

    const distance =
        Number(
            routeDistanceInput.value
            || 0
        );

    let urgentFee = 0;

    if (
        priority === 'Urgent'
        &&
        distance > 0
    ) {

        urgentFee =
            getUrgentFee(distance);
    }

    summaryTotal.textContent =
        peso(
            PRODUCT_SUBTOTAL
            +
            urgentFee
        );

    summaryUrgentFee.textContent =
        peso(urgentFee);

    urgentFeeRow.style.display =
        priority === 'Urgent'
            ? 'flex'
            : 'none';

    if (distance > 0) {

        urgentOptionFee.textContent =
            peso(
                getUrgentFee(distance)
            );

    } else {

        urgentOptionFee.textContent =
            'Calculate route first';
    }

    priorityNote.textContent =
        priority === 'Urgent'
            ?
            'Urgent delivery gives your order priority handling but does not guarantee an exact delivery date or time.'
            :
            'Your order will follow the normal scheduling and assignment process.';
}

document
    .querySelectorAll(
        'input[name="priority_level"]'
    )
    .forEach(
        radio => {

            radio.addEventListener(
                'change',
                updateSummary
            );
        }
    );


/* =========================================================
   SVG ICONS
========================================================= */

const warehouseSvg = `
<svg
    viewBox="0 0 24 24"
    fill="currentColor"
>
    <path
        d="M3 10.5 12 4l9 6.5V21h-6v-6H9v6H3V10.5Zm2 1.04V19h2v-6h10v6h2v-7.46L12 6.5l-7 5.04Z"
    />
</svg>
`;

const customerSvg = `
<svg
    viewBox="0 0 24 24"
    fill="currentColor"
>
    <path
        d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"
    />
</svg>
`;


/* =========================================================
   CUSTOM ICONS
========================================================= */

const warehouseIcon =
    L.divIcon({

        className:
            'custom-map-icon',

        html:
            `
            <div class="map-pin pin-warehouse">

                <div class="map-pin-inner">
                    ${warehouseSvg}
                </div>

            </div>
            `,

        iconSize:
            [46,46],

        iconAnchor:
            [23,44],

        popupAnchor:
            [0,-42]
    });


const customerIcon =
    L.divIcon({

        className:
            'custom-map-icon',

        html:
            `
            <div class="map-pin pin-customer">

                <div class="map-pin-inner">
                    ${customerSvg}
                </div>

            </div>
            `,

        iconSize:
            [46,46],

        iconAnchor:
            [23,44],

        popupAnchor:
            [0,-42]
    });


/* =========================================================
   MAP
========================================================= */

const map =
    L.map(
        'deliveryMap',
        {
            zoomControl: true,
            scrollWheelZoom: true
        }
    );


L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        maxZoom: 19,

        attribution:
            '&copy; OpenStreetMap contributors'
    }
).addTo(map);


/* =========================================================
   WAREHOUSE
========================================================= */

const warehouseMarker =
    L.marker(
        [
            HONG_SING_LAT,
            HONG_SING_LNG
        ],
        {
            icon:
                warehouseIcon
        }
    )
    .addTo(map)
    .bindPopup(
        '<strong>Hong Sing LKRR Commercial</strong><br>Delivery Origin / Warehouse'
    );


/* =========================================================
   DESTINATION
========================================================= */

let customerMarker = null;
let routeLine = null;
let routeRequestId = 0;


/* =========================================================
   FORMAT TIME
========================================================= */

function formatMinutes(minutes) {

    minutes =
        Math.max(
            1,
            Math.round(
                Number(minutes)
            )
        );

    const hours =
        Math.floor(
            minutes / 60
        );

    const mins =
        minutes % 60;

    if (
        hours > 0
        &&
        mins > 0
    ) {

        return (
            hours
            +
            ' hr '
            +
            mins
            +
            ' min'
        );
    }

    if (hours > 0) {

        return (
            hours
            +
            ' hr'
        );
    }

    return mins + ' min';
}


/* =========================================================
   STATUS
========================================================= */

function setStatus(
    text,
    type = ''
) {

    routeStatus.textContent =
        text;

    routeStatus.classList.remove(
        'success',
        'error'
    );

    if (type) {

        routeStatus
            .classList
            .add(type);
    }
}


/* =========================================================
   CLEAR ROUTE
========================================================= */

function clearRoute() {

    if (routeLine) {

        map.removeLayer(routeLine);

        routeLine = null;
    }

    routeDistanceInput.value = '';
    routeDurationInput.value = '';

    routeDistanceDisplay.textContent =
        '—';

    routeDurationDisplay.textContent =
        '—';

    summaryDistance.textContent =
        '—';

    summaryDuration.textContent =
        '—';

    routeMetrics.style.display =
        'none';

    updateSummary();
}


/* =========================================================
   CUSTOMER MARKER
========================================================= */

function setCustomerMarker(
    lat,
    lng,
    pan = true
) {

    if (customerMarker) {

        customerMarker.setLatLng([
            lat,
            lng
        ]);

    } else {

        customerMarker =
            L.marker(
                [
                    lat,
                    lng
                ],
                {
                    icon:
                        customerIcon,

                    draggable:
                        true
                }
            )
            .addTo(map)
            .bindPopup(
                '<strong>Customer Delivery Location</strong><br>Drag the red pin to adjust the exact location.'
            );

        customerMarker.on(
            'dragend',
            async function () {

                const pos =
                    customerMarker.getLatLng();

                await selectDestination(
                    pos.lat,
                    pos.lng,
                    true
                );
            }
        );
    }

    if (pan) {

        map.panTo([
            lat,
            lng
        ]);
    }
}


/* =========================================================
   ROAD ROUTE - OSRM
========================================================= */

async function calculateRoute(
    lat,
    lng
) {

    const requestId =
        ++routeRequestId;

    clearRoute();

    setStatus(
        'Calculating road route...'
    );

    placeOrderBtn.disabled = true;

    try {

        const url =
            'https://router.project-osrm.org/route/v1/driving/'
            +
            HONG_SING_LNG
            +
            ','
            +
            HONG_SING_LAT
            +
            ';'
            +
            lng
            +
            ','
            +
            lat
            +
            '?overview=full&geometries=geojson&steps=false';


        const response =
            await fetch(url);

        if (!response.ok) {

            throw new Error(
                'Route service unavailable.'
            );
        }

        const data =
            await response.json();

        if (
            requestId !== routeRequestId
        ) {
            return;
        }

        if (
            data.code !== 'Ok'
            ||
            !data.routes
            ||
            !data.routes.length
        ) {

            throw new Error(
                'No route found.'
            );
        }

        const route =
            data.routes[0];

        const distance =
            Number(route.distance)
            / 1000;

        const duration =
            Number(route.duration)
            / 60;

        if (
            !Number.isFinite(distance)
            ||
            distance <= 0
            ||
            !Number.isFinite(duration)
            ||
            duration <= 0
        ) {

            throw new Error(
                'Invalid route.'
            );
        }


        /* Save */

        routeDistanceInput.value =
            distance.toFixed(2);

        routeDurationInput.value =
            duration.toFixed(2);


        /* Display */

        const distanceText =
            distance.toFixed(2)
            + ' km';

        const durationText =
            formatMinutes(duration);

        routeDistanceDisplay.textContent =
            distanceText;

        routeDurationDisplay.textContent =
            durationText;

        summaryDistance.textContent =
            distanceText;

        summaryDuration.textContent =
            durationText;

        routeMetrics.style.display =
            'grid';


        /* Draw route */

        routeLine =
            L.geoJSON(
                route.geometry,
                {
                    style: {

                        color:
                            '#2563eb',

                        weight:
                            6,

                        opacity:
                            .85,

                        lineCap:
                            'round',

                        lineJoin:
                            'round'
                    }
                }
            )
            .addTo(map);


        /* Fit entire route */

        const bounds =
            routeLine.getBounds();

        if (
            bounds
            &&
            bounds.isValid()
        ) {

            map.fitBounds(
                bounds,
                {
                    padding:
                        [50,50],

                    maxZoom:
                        16
                }
            );
        }


        setStatus(
            'Road route calculated successfully.',
            'success'
        );

        updateSummary();

        placeOrderBtn.disabled =
            false;

    } catch (error) {

        console.error(
            'OSRM:',
            error
        );

        clearRoute();

        setStatus(
            'Unable to calculate the road route. Please choose another location or try again.',
            'error'
        );

        placeOrderBtn.disabled =
            true;
    }
}


/* =========================================================
   REVERSE GEOCODE
========================================================= */

async function reverseGeocode(
    lat,
    lng
) {

    try {

        const url =
            'https://nominatim.openstreetmap.org/reverse'
            +
            '?format=jsonv2'
            +
            '&addressdetails=1'
            +
            '&lat='
            +
            encodeURIComponent(lat)
            +
            '&lon='
            +
            encodeURIComponent(lng);

        const response =
            await fetch(url);

        if (!response.ok) {
            return null;
        }

        return await response.json();

    } catch (error) {

        console.error(
            'Reverse geocode:',
            error
        );

        return null;
    }
}


/* =========================================================
   APPLY ADDRESS
========================================================= */

function applyAddress(data) {

    if (!data) {
        return;
    }

    const address =
        data.address || {};

    if (data.display_name) {

        fullAddress.value =
            data.display_name;
    }

    const cityValue =
        address.city
        ||
        address.municipality
        ||
        address.town
        ||
        address.village
        ||
        address.city_district
        ||
        '';

    const provinceValue =
        address.state
        ||
        address.region
        ||
        address.province
        ||
        '';

    if (cityValue) {
        city.value = cityValue;
    }

    if (provinceValue) {
        province.value =
            provinceValue;
    }
}


/* =========================================================
   SELECT LOCATION
========================================================= */

async function selectDestination(
    lat,
    lng,
    updateAddress = true
) {

    lat = Number(lat);
    lng = Number(lng);

    if (
        !Number.isFinite(lat)
        ||
        !Number.isFinite(lng)
    ) {
        return;
    }

    if (
        lat < 4
        ||
        lat > 22
        ||
        lng < 116
        ||
        lng > 127
    ) {

        setStatus(
            'Please select a location within the Philippines.',
            'error'
        );

        return;
    }

    latitudeInput.value =
        lat.toFixed(7);

    longitudeInput.value =
        lng.toFixed(7);

    setCustomerMarker(
        lat,
        lng
    );

    const routePromise =
        calculateRoute(
            lat,
            lng
        );

    if (updateAddress) {

        const address =
            await reverseGeocode(
                lat,
                lng
            );

        applyAddress(address);
    }

    await routePromise;
}


/* =========================================================
   CLICK MAP
========================================================= */

map.on(
    'click',
    async function (event) {

        await selectDestination(
            event.latlng.lat,
            event.latlng.lng,
            true
        );
    }
);


/* =========================================================
   SEARCH LOCATION
========================================================= */

async function searchLocation() {

    const query =
        mapSearch.value.trim();

    if (!query) {
        return;
    }

    searchLocationBtn.disabled =
        true;

    searchLocationBtn.textContent =
        'Searching...';

    try {

        const url =
            'https://nominatim.openstreetmap.org/search'
            +
            '?format=jsonv2'
            +
            '&addressdetails=1'
            +
            '&limit=5'
            +
            '&countrycodes=ph'
            +
            '&q='
            +
            encodeURIComponent(query);

        const response =
            await fetch(url);

        if (!response.ok) {

            throw new Error(
                'Search failed.'
            );
        }

        const results =
            await response.json();

        renderSearchResults(
            Array.isArray(results)
                ? results
                : []
        );

    } catch (error) {

        console.error(
            'Search:',
            error
        );

        searchResults.innerHTML =
            '<div style="padding:12px;color:var(--color-danger);">Unable to search location.</div>';

        searchResults.classList.add(
            'show'
        );

    } finally {

        searchLocationBtn.disabled =
            false;

        searchLocationBtn.textContent =
            'Search';
    }
}


/* =========================================================
   SEARCH RESULTS
========================================================= */

function renderSearchResults(results) {

    searchResults.innerHTML = '';

    if (!results.length) {

        searchResults.innerHTML =
            '<div style="padding:12px;">No location found.</div>';

        searchResults.classList.add(
            'show'
        );

        return;
    }

    results.forEach(
        function (result) {

            const button =
                document.createElement(
                    'button'
                );

            button.type =
                'button';

            button.className =
                'search-result';

            button.textContent =
                result.display_name;

            button.addEventListener(
                'click',
                async function () {

                    const lat =
                        Number(result.lat);

                    const lng =
                        Number(result.lon);

                    mapSearch.value =
                        result.display_name;

                    searchResults
                        .classList
                        .remove('show');

                    applyAddress(result);

                    latitudeInput.value =
                        lat.toFixed(7);

                    longitudeInput.value =
                        lng.toFixed(7);

                    setCustomerMarker(
                        lat,
                        lng
                    );

                    await calculateRoute(
                        lat,
                        lng
                    );
                }
            );

            searchResults.appendChild(
                button
            );
        }
    );

    searchResults.classList.add(
        'show'
    );
}


searchLocationBtn.addEventListener(
    'click',
    searchLocation
);

mapSearch.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Enter') {

            event.preventDefault();

            searchLocation();
        }
    }
);


/* =========================================================
   INITIAL LOCATION
========================================================= */

const initialLat =
    parseFloat(
        latitudeInput.value
    );

const initialLng =
    parseFloat(
        longitudeInput.value
    );

if (
    Number.isFinite(initialLat)
    &&
    Number.isFinite(initialLng)
) {

    setCustomerMarker(
        initialLat,
        initialLng,
        false
    );

    map.fitBounds(
        L.latLngBounds([
            [
                HONG_SING_LAT,
                HONG_SING_LNG
            ],
            [
                initialLat,
                initialLng
            ]
        ]),
        {
            padding:
                [50,50],

            maxZoom:
                15
        }
    );

    calculateRoute(
        initialLat,
        initialLng
    );

} else {

    map.setView(
        [
            HONG_SING_LAT,
            HONG_SING_LNG
        ],
        13
    );
}


/* =========================================================
   LEAFLET SIZE FIX
========================================================= */

setTimeout(
    function () {

        map.invalidateSize();

    },
    150
);

setTimeout(
    function () {

        map.invalidateSize();

    },
    500
);


/* =========================================================
   SUBMIT VALIDATION
========================================================= */

form.addEventListener(
    'submit',
    function (event) {

        const lat =
            Number(
                latitudeInput.value
            );

        const lng =
            Number(
                longitudeInput.value
            );

        const distance =
            Number(
                routeDistanceInput.value
            );

        const duration =
            Number(
                routeDurationInput.value
            );

        if (
            !Number.isFinite(lat)
            ||
            !Number.isFinite(lng)
        ) {

            event.preventDefault();

            alert(
                'Please select your delivery location.'
            );

            return;
        }

        if (
            !Number.isFinite(distance)
            ||
            distance <= 0
            ||
            !Number.isFinite(duration)
            ||
            duration <= 0
        ) {

            event.preventDefault();

            alert(
                'Please wait for the road route to finish calculating.'
            );

            return;
        }

        placeOrderBtn.disabled =
            true;

        placeOrderBtn.textContent =
            'Placing Order...';
    }
);


/* =========================================================
   INITIAL SUMMARY
========================================================= */

updateSummary();

});
</script>