<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

/*
|--------------------------------------------------------------------------
| UPLOAD CONFIGURATION
|--------------------------------------------------------------------------
*/

$uploadDirectory = __DIR__ . '/../assets/uploads/products/';
$uploadWebPath   = BASE_URL . 'assets/uploads/products/';

if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0755, true);
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function redirectProducts(string $message): void
{
    header(
        'Location: ' .
        BASE_URL .
        'admin/products.php?success=' .
        urlencode($message)
    );

    exit;
}

function redirectProductsError(string $message): void
{
    header(
        'Location: ' .
        BASE_URL .
        'admin/products.php?error=' .
        urlencode($message)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| UPLOAD PRODUCT IMAGE
|--------------------------------------------------------------------------
*/

function uploadProductImage(
    string $fieldName,
    string $uploadDirectory
): ?string {

    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'Failed to upload product image.'
        );
    }

    $file = $_FILES[$fieldName];

    // Maximum 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException(
            'Product image must not exceed 5 MB.'
        );
    }

    // Detect MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException(
            'Only JPG, PNG, and WEBP images are allowed.'
        );
    }

    // Generate unique filename
    $fileName =
        'product_' .
        bin2hex(random_bytes(8)) .
        '.' .
        $allowedTypes[$mimeType];

    $destination =
        rtrim($uploadDirectory, '/\\') .
        DIRECTORY_SEPARATOR .
        $fileName;

    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        throw new RuntimeException(
            'Unable to save product image.'
        );
    }

    return $fileName;
}


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT IMAGE
|--------------------------------------------------------------------------
*/

function deleteProductImage(
    ?string $fileName,
    string $uploadDirectory
): void {

    if (empty($fileName)) {
        return;
    }

    $filePath =
        rtrim($uploadDirectory, '/\\') .
        DIRECTORY_SEPARATOR .
        basename($fileName);

    if (is_file($filePath)) {
        @unlink($filePath);
    }
}


/*
|--------------------------------------------------------------------------
| ADD PRODUCT
|--------------------------------------------------------------------------
*/

if (isset($_POST['add_product'])) {

    $productCode = trim(
        $_POST['product_code'] ?? ''
    );

    $productName = trim(
        $_POST['product_name'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $price = $_POST['price'] ?? 0;

    $weightKg = $_POST['weight_kg'] ?? 0;

    $stockQuantity =
        $_POST['stock_quantity'] ?? 0;

    $status =
        $_POST['status'] ?? 'Available';


    if (
        $productCode === '' ||
        $productName === ''
    ) {
        redirectProductsError(
            'Product code and product name are required.'
        );
    }


    if (
        !is_numeric($weightKg) ||
        (float)$weightKg <= 0
    ) {
        redirectProductsError(
            'Weight must be greater than 0.'
        );
    }


    if (
        !is_numeric($price) ||
        (float)$price < 0
    ) {
        redirectProductsError(
            'Invalid product price.'
        );
    }


    if (
        !is_numeric($stockQuantity) ||
        (int)$stockQuantity < 0
    ) {
        redirectProductsError(
            'Invalid stock quantity.'
        );
    }


    if (
        !in_array(
            $status,
            ['Available', 'Unavailable'],
            true
        )
    ) {
        $status = 'Available';
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE PRODUCT CODE
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT product_id
            FROM product
            WHERE product_code = ?
            LIMIT 1
        ");

        $stmt->execute([
            $productCode
        ]);

        if ($stmt->fetch()) {
            redirectProductsError(
                'Product code already exists.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | UPLOAD IMAGE
        |--------------------------------------------------------------------------
        */

        $productImage = uploadProductImage(
            'product_image',
            $uploadDirectory
        );


        /*
        |--------------------------------------------------------------------------
        | INSERT PRODUCT
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            INSERT INTO product (
                product_code,
                product_name,
                description,
                price,
                weight_kg,
                stock_quantity,
                status,
                product_image
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $productCode,
            $productName,
            $description !== '' ? $description : null,
            (float)$price,
            (float)$weightKg,
            (int)$stockQuantity,
            $status,
            $productImage
        ]);

        redirectProducts(
            'Product added successfully.'
        );

    } catch (RuntimeException $e) {

        redirectProductsError(
            $e->getMessage()
        );

    } catch (PDOException $e) {

        redirectProductsError(
            'Failed to add product.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE PRODUCT
|--------------------------------------------------------------------------
*/

if (isset($_POST['update_product'])) {

    $productId = (int)(
        $_POST['product_id'] ?? 0
    );

    $productCode = trim(
        $_POST['product_code'] ?? ''
    );

    $productName = trim(
        $_POST['product_name'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $price = $_POST['price'] ?? 0;

    $weightKg = $_POST['weight_kg'] ?? 0;

    $stockQuantity =
        $_POST['stock_quantity'] ?? 0;

    $status =
        $_POST['status'] ?? 'Available';


    if ($productId <= 0) {
        redirectProductsError(
            'Invalid product.'
        );
    }


    if (
        $productCode === '' ||
        $productName === ''
    ) {
        redirectProductsError(
            'Product code and product name are required.'
        );
    }


    if (
        !is_numeric($weightKg) ||
        (float)$weightKg <= 0
    ) {
        redirectProductsError(
            'Weight must be greater than 0.'
        );
    }


    if (
        !is_numeric($price) ||
        (float)$price < 0
    ) {
        redirectProductsError(
            'Invalid product price.'
        );
    }


    if (
        !is_numeric($stockQuantity) ||
        (int)$stockQuantity < 0
    ) {
        redirectProductsError(
            'Invalid stock quantity.'
        );
    }


    if (
        !in_array(
            $status,
            ['Available', 'Unavailable'],
            true
        )
    ) {
        $status = 'Available';
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | GET CURRENT PRODUCT
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT
                product_id,
                product_image
            FROM product
            WHERE product_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $productId
        ]);

        $currentProduct =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$currentProduct) {
            redirectProductsError(
                'Product not found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE PRODUCT CODE
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT product_id
            FROM product
            WHERE product_code = ?
            AND product_id <> ?
            LIMIT 1
        ");

        $stmt->execute([
            $productCode,
            $productId
        ]);

        if ($stmt->fetch()) {
            redirectProductsError(
                'Product code already exists.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | IMAGE
        |--------------------------------------------------------------------------
        */

        $newProductImage =
            uploadProductImage(
                'product_image',
                $uploadDirectory
            );

        $productImage =
            $currentProduct['product_image'];


        if ($newProductImage !== null) {
            $productImage =
                $newProductImage;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE PRODUCT
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            UPDATE product
            SET
                product_code = ?,
                product_name = ?,
                description = ?,
                price = ?,
                weight_kg = ?,
                stock_quantity = ?,
                status = ?,
                product_image = ?
            WHERE product_id = ?
        ");

        $stmt->execute([
            $productCode,
            $productName,
            $description !== '' ? $description : null,
            (float)$price,
            (float)$weightKg,
            (int)$stockQuantity,
            $status,
            $productImage,
            $productId
        ]);


        /*
        |--------------------------------------------------------------------------
        | DELETE OLD IMAGE AFTER SUCCESSFUL UPDATE
        |--------------------------------------------------------------------------
        */

        if (
            $newProductImage !== null &&
            !empty($currentProduct['product_image'])
        ) {

            deleteProductImage(
                $currentProduct['product_image'],
                $uploadDirectory
            );
        }


        redirectProducts(
            'Product updated successfully.'
        );

    } catch (RuntimeException $e) {

        redirectProductsError(
            $e->getMessage()
        );

    } catch (PDOException $e) {

        redirectProductsError(
            'Failed to update product.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DELETE PRODUCT
|--------------------------------------------------------------------------
*/

if (isset($_POST['delete_product'])) {

    $productId = (int)(
        $_POST['product_id'] ?? 0
    );


    if ($productId <= 0) {
        redirectProductsError(
            'Invalid product.'
        );
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | CHECK ORDER REFERENCE
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT order_item_id
            FROM order_item
            WHERE product_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $productId
        ]);


        if ($stmt->fetch()) {
            redirectProductsError(
                'This product is already used in an order. Set it to Unavailable instead.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GET PRODUCT IMAGE
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            SELECT product_image
            FROM product
            WHERE product_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $productId
        ]);

        $product =
            $stmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | DELETE PRODUCT
        |--------------------------------------------------------------------------
        */

        $stmt = $db->prepare("
            DELETE FROM product
            WHERE product_id = ?
        ");

        $stmt->execute([
            $productId
        ]);


        /*
        |--------------------------------------------------------------------------
        | DELETE IMAGE
        |--------------------------------------------------------------------------
        */

        if (
            $product &&
            !empty($product['product_image'])
        ) {

            deleteProductImage(
                $product['product_image'],
                $uploadDirectory
            );
        }


        redirectProducts(
            'Product deleted successfully.'
        );

    } catch (PDOException $e) {

        redirectProductsError(
            'Unable to delete this product.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

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
        status,
        created_at,
        updated_at
    FROM product
    ORDER BY product_name ASC
");

$products =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Product Management</title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <style>

        /* =========================================================
           PAGE
        ========================================================= */

        .products-page {
            width: 100%;
        }

        .products-page .page-header {
            margin-bottom: 24px;
        }


        /* =========================================================
           ALERTS
        ========================================================= */

        .product-alert {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            margin-bottom: 22px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 600;
            box-sizing: border-box;
        }

        .product-alert-success {
            background: var(--color-accent-soft);
            color: var(--color-success);
            border-color: var(--color-success);
        }

        .product-alert-error {
            background: rgba(220, 38, 38, 0.08);
            color: var(--color-danger);
            border-color: rgba(220, 38, 38, 0.25);
        }


        /* =========================================================
           SECTION CARD
        ========================================================= */

        .product-section {
            width: 100%;
            margin-bottom: 24px;
        }

        .product-section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 22px;
        }

        .product-section-title {
            margin: 0;
            font-size: 20px;
            font-weight: 750;
            color: var(--color-text-primary);
        }

        .product-section-subtitle {
            margin: 5px 0 0;
            color: var(--color-text-secondary);
            font-size: 13px;
        }


        /* =========================================================
           ADD PRODUCT FORM
        ========================================================= */

        .add-product-card {
            padding: 28px;
        }

        .product-form-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 20px;
        }

        .product-field {
            min-width: 0;
        }

        .field-code {
            grid-column: span 3;
        }

        .field-name {
            grid-column: span 5;
        }

        .field-weight {
            grid-column: span 2;
        }

        .field-price {
            grid-column: span 2;
        }

        .field-stock {
            grid-column: span 3;
        }

        .field-status {
            grid-column: span 3;
        }

        .field-image {
            grid-column: span 6;
        }

        .field-description {
            grid-column: span 6;
        }

        .product-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--color-text-primary);
        }

        .product-field label .required {
            color: var(--color-danger);
        }

        .product-field small {
            display: block;
            margin-top: 7px;
            color: var(--color-text-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .product-field .form-input {
            width: 100%;
            box-sizing: border-box;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 116px;
        }


        /* =========================================================
           IMAGE UPLOAD
        ========================================================= */

        .image-upload-box {
            width: 100%;
            min-height: 116px;
            padding: 16px;
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface-alt);
            box-sizing: border-box;
        }

        .image-upload-box input[type="file"] {
            width: 100%;
            box-sizing: border-box;
        }

        .image-preview-wrapper {
            margin-top: 14px;
        }

        .product-image-preview {
            width: 130px;
            height: 130px;
            object-fit: cover;
            display: block;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
            background: var(--color-surface-alt);
        }


        /* =========================================================
           FORM ACTIONS
        ========================================================= */

        .product-form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            margin-top: 24px;
            padding-top: 22px;
            border-top: 1px solid var(--color-border);
        }

        .product-form-actions .btn-primary {
            min-width: 140px;
        }


        /* =========================================================
           PRODUCT LIST HEADER
        ========================================================= */

        .product-list-card {
            padding: 28px;
        }

        .product-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: var(--color-accent-soft);
            color: var(--color-accent);
            font-size: 12px;
            font-weight: 800;
        }

        .product-list-summary {
            display: flex;
            align-items: center;
            gap: 10px;
        }


        /* =========================================================
           PRODUCT TABLE
        ========================================================= */

        .product-table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
        }

        .product-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .product-table th {
            background: var(--color-surface-alt);
            color: var(--color-text-secondary);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 14px 16px;
            border-bottom: 1px solid var(--color-border);
            text-align: left;
            white-space: nowrap;
        }

        .product-table td {
            padding: 16px;
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
            color: var(--color-text-primary);
            font-size: 14px;
        }

        .product-table tbody tr:last-child td {
            border-bottom: none;
        }

        .product-table tbody tr {
            transition: background .15s ease;
        }

        .product-table tbody tr:hover {
            background: var(--color-surface-alt);
        }


        /* Column widths */

        .product-table th:nth-child(1),
        .product-table td:nth-child(1) {
            width: 90px;
        }

        .product-table th:nth-child(2),
        .product-table td:nth-child(2) {
            width: 110px;
        }

        .product-table th:nth-child(3),
        .product-table td:nth-child(3) {
            width: 330px;
        }

        .product-table th:nth-child(4),
        .product-table td:nth-child(4) {
            width: 110px;
        }

        .product-table th:nth-child(5),
        .product-table td:nth-child(5) {
            width: 120px;
        }

        .product-table th:nth-child(6),
        .product-table td:nth-child(6) {
            width: 90px;
        }

        .product-table th:nth-child(7),
        .product-table td:nth-child(7) {
            width: 120px;
        }

        .product-table th:nth-child(8),
        .product-table td:nth-child(8) {
            width: 170px;
        }


        /* =========================================================
           PRODUCT IMAGE
        ========================================================= */

        .product-list-image {
            width: 64px;
            height: 64px;
            object-fit: cover;
            display: block;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
            background: var(--color-surface-alt);
        }

        .image-placeholder {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface-alt);
            color: var(--color-text-muted);
            font-size: 11px;
            text-align: center;
        }


        /* =========================================================
           PRODUCT INFO
        ========================================================= */

        .product-info {
            min-width: 0;
        }

        .product-name {
            display: block;
            font-weight: 750;
            color: var(--color-text-primary);
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .product-description {
            margin-top: 7px;
        }

        .product-description summary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: var(--color-accent);
            font-size: 12px;
            font-weight: 700;
            user-select: none;
        }

        .product-description summary::marker {
            display: none;
        }

        .product-description summary::-webkit-details-marker {
            display: none;
        }

        .product-description summary::before {
            content: "▶";
            font-size: 8px;
            transition: transform .2s ease;
        }

        .product-description[open] summary::before {
            transform: rotate(90deg);
        }

        .description-content {
            margin-top: 9px;
            padding: 11px 12px;
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            color: var(--color-text-secondary);
            font-size: 12px;
            line-height: 1.55;
            max-height: 140px;
            overflow-y: auto;
            overflow-wrap: anywhere;
        }


        /* =========================================================
           PRODUCT CODE
        ========================================================= */

        .product-code {
            display: inline-flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: var(--radius-sm);
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
        }


        /* =========================================================
           PRICE / WEIGHT / STOCK
        ========================================================= */

        .product-price {
            font-weight: 800;
            white-space: nowrap;
        }

        .product-weight {
            font-weight: 700;
            white-space: nowrap;
        }

        .product-unit {
            display: block;
            margin-top: 3px;
            color: var(--color-text-muted);
            font-size: 11px;
        }

        .stock-number {
            font-weight: 800;
        }

        .stock-low {
            color: var(--color-warning);
        }

        .stock-out {
            color: var(--color-danger);
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .product-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .product-status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .product-status-available {
            background: rgba(22, 163, 74, .10);
            color: var(--color-success);
        }

        .product-status-unavailable {
            background: rgba(220, 38, 38, .10);
            color: var(--color-danger);
        }


        /* =========================================================
           ACTIONS
        ========================================================= */

        .product-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .product-actions form {
            margin: 0;
        }

        .product-actions button {
            white-space: nowrap;
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .product-empty {
            padding: 60px 24px;
            text-align: center;
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface-alt);
        }

        .product-empty-icon {
            width: 54px;
            height: 54px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--color-accent-soft);
            color: var(--color-accent);
            font-size: 24px;
        }

        .product-empty-title {
            margin: 0;
            font-size: 16px;
            font-weight: 750;
            color: var(--color-text-primary);
        }

        .product-empty-text {
            margin: 6px 0 0;
            color: var(--color-text-secondary);
            font-size: 13px;
        }


        /* =========================================================
           EDIT MODAL
        ========================================================= */

        .edit-product-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(3px);
        }

        .edit-product-dialog {
            width: min(820px, 100%);
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
        }

        .edit-product-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 26px;
            border-bottom: 1px solid var(--color-border);
            position: sticky;
            top: 0;
            background: var(--color-surface);
            z-index: 2;
        }

        .edit-product-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--color-text-primary);
        }

        .edit-product-subtitle {
            margin: 4px 0 0;
            color: var(--color-text-secondary);
            font-size: 12px;
        }

        .modal-close {
            width: 38px;
            height: 38px;
            padding: 0;
            border-radius: 50%;
            font-size: 22px;
            line-height: 1;
        }

        .edit-product-body {
            padding: 26px;
        }

        .edit-image-section {
            display: flex;
            align-items: flex-start;
            gap: 22px;
            padding: 18px;
            margin-bottom: 24px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface-alt);
        }

        .edit-image-preview {
            width: 150px;
            height: 150px;
            flex: 0 0 150px;
            object-fit: cover;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
            background: var(--color-surface);
        }

        .edit-image-details {
            flex: 1;
            min-width: 0;
        }

        .edit-image-details label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 750;
        }

        .edit-image-details small {
            display: block;
            margin-top: 8px;
            color: var(--color-text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .edit-form-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 18px;
        }

        .edit-field {
            grid-column: span 6;
            min-width: 0;
        }

        .edit-field label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
        }

        .edit-field-full {
            grid-column: 1 / -1;
        }

        .edit-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px 26px;
            border-top: 1px solid var(--color-border);
            background: var(--color-surface-alt);
            position: sticky;
            bottom: 0;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

            .field-code,
            .field-name,
            .field-weight,
            .field-price,
            .field-stock,
            .field-status,
            .field-image,
            .field-description {
                grid-column: span 6;
            }

        }


        @media (max-width: 760px) {

            .add-product-card,
            .product-list-card {
                padding: 20px;
            }

            .product-section-header {
                flex-direction: column;
            }

            .product-form-grid {
                grid-template-columns: 1fr;
            }

            .field-code,
            .field-name,
            .field-weight,
            .field-price,
            .field-stock,
            .field-status,
            .field-image,
            .field-description {
                grid-column: 1;
            }

            .product-form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .product-form-actions button {
                width: 100%;
            }

            .edit-product-modal {
                padding: 10px;
            }

            .edit-product-dialog {
                max-height: calc(100vh - 20px);
            }

            .edit-product-header,
            .edit-product-body {
                padding: 18px;
            }

            .edit-image-section {
                flex-direction: column;
            }

            .edit-image-preview {
                width: 130px;
                height: 130px;
                flex-basis: 130px;
            }

            .edit-form-grid {
                grid-template-columns: 1fr;
            }

            .edit-field,
            .edit-field-full {
                grid-column: 1;
            }

            .edit-form-actions {
                padding: 16px 18px;
                flex-direction: column-reverse;
            }

            .edit-form-actions button {
                width: 100%;
            }

        }


        @media (max-width: 520px) {

            .product-section-title {
                font-size: 18px;
            }

            .product-table th,
            .product-table td {
                padding: 12px;
            }

            .product-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .product-actions button,
            .product-actions form,
            .product-actions form button {
                width: 100%;
            }

        }

    </style>

</head>


<body>

<?php
/*
|--------------------------------------------------------------------------
| SHARED ADMIN HEADER / SIDEBAR
|--------------------------------------------------------------------------
|
| header.php already creates .app-layout.
| Do NOT create another .app-layout here.
|
*/
require __DIR__ . '/../includes/header.php';
?>


<main class="app-main">

    <div class="products-page">

        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="page-header">

            <div>

                <h1>
                    Product Management
                </h1>

                <p>
                    Manage products, pricing, stock, availability,
                    and product images.
                </p>

            </div>

        </div>


        <!-- =====================================================
             ALERTS
        ====================================================== -->

        <?php if (!empty($_GET['success'])): ?>

            <div class="product-alert product-alert-success">

                <span>✓</span>

                <span>
                    <?= htmlspecialchars($_GET['success']) ?>
                </span>

            </div>

        <?php endif; ?>


        <?php if (!empty($_GET['error'])): ?>

            <div class="product-alert product-alert-error">

                <span>!</span>

                <span>
                    <?= htmlspecialchars($_GET['error']) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             ADD PRODUCT
        ====================================================== -->

        <section class="card product-section add-product-card">

            <div class="product-section-header">

                <div>

                    <h2 class="product-section-title">
                        Add New Product
                    </h2>

                    <p class="product-section-subtitle">
                        Enter the product information below.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <div class="product-form-grid">


                    <!-- PRODUCT CODE -->

                    <div class="product-field field-code">

                        <label for="product_code">
                            Product Code
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="product_code"
                            id="product_code"
                            class="form-input"
                            placeholder="e.g. PLA-001"
                            required
                        >

                    </div>


                    <!-- PRODUCT NAME -->

                    <div class="product-field field-name">

                        <label for="product_name">
                            Product Name
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="product_name"
                            id="product_name"
                            class="form-input"
                            placeholder="e.g. Plastic Fruit Tray"
                            required
                        >

                    </div>


                    <!-- WEIGHT -->

                    <div class="product-field field-weight">

                        <label for="weight_kg">
                            Weight per Unit
                            <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            name="weight_kg"
                            id="weight_kg"
                            class="form-input"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            required
                        >

                        <small>
                            Unit: kg
                        </small>

                    </div>


                    <!-- PRICE -->

                    <div class="product-field field-price">

                        <label for="price">
                            Price
                            <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            name="price"
                            id="price"
                            class="form-input"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            required
                        >

                    </div>


                    <!-- STOCK -->

                    <div class="product-field field-stock">

                        <label for="stock_quantity">
                            Stock Quantity
                            <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            name="stock_quantity"
                            id="stock_quantity"
                            class="form-input"
                            min="0"
                            value="0"
                            required
                        >

                    </div>


                    <!-- STATUS -->

                    <div class="product-field field-status">

                        <label for="status">
                            Status
                        </label>

                        <select
                            name="status"
                            id="status"
                            class="form-input"
                        >

                            <option value="Available">
                                Available
                            </option>

                            <option value="Unavailable">
                                Unavailable
                            </option>

                        </select>

                    </div>


                    <!-- IMAGE -->

                    <div class="product-field field-image">

                        <label for="product_image">
                            Product Photo
                        </label>

                        <div class="image-upload-box">

                            <input
                                type="file"
                                name="product_image"
                                id="product_image"
                                class="form-input"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <small>
                                JPG, PNG, or WEBP.
                                Maximum file size: 5 MB.
                            </small>

                            <div class="image-preview-wrapper">

                                <img
                                    id="addImagePreview"
                                    class="product-image-preview"
                                    src=""
                                    alt="Product preview"
                                    style="display:none;"
                                >

                            </div>

                        </div>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="product-field field-description">

                        <label for="description">
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="description"
                            class="form-input"
                            rows="5"
                            placeholder="Enter product description..."
                        ></textarea>

                    </div>


                </div>


                <div class="product-form-actions">

                    <button
                        type="submit"
                        name="add_product"
                        class="btn-primary"
                    >
                        + Add Product
                    </button>

                </div>

            </form>

        </section>


        <!-- =====================================================
             PRODUCT LIST
        ====================================================== -->

        <section class="card product-section product-list-card">

            <div class="product-section-header">

                <div>

                    <h2 class="product-section-title">
                        Product List
                    </h2>

                    <p class="product-section-subtitle">
                        All products currently registered in the system.
                    </p>

                </div>


                <div class="product-list-summary">

                    <span class="product-count">
                        <?= count($products) ?>
                    </span>

                    <span class="product-section-subtitle">
                        product<?= count($products) === 1 ? '' : 's' ?>
                    </span>

                </div>

            </div>


            <?php if (empty($products)): ?>

                <div class="product-empty">

                    <div class="product-empty-icon">
                        +
                    </div>

                    <h3 class="product-empty-title">
                        No products yet
                    </h3>

                    <p class="product-empty-text">
                        Add your first product using the form above.
                    </p>

                </div>

            <?php else: ?>

                <div class="product-table-wrapper">

                    <table class="product-table">

                        <thead>

                            <tr>

                                <th>
                                    Photo
                                </th>

                                <th>
                                    Code
                                </th>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Weight
                                </th>

                                <th>
                                    Price
                                </th>

                                <th>
                                    Stock
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($products as $product): ?>

                            <tr>


                                <!-- PHOTO -->

                                <td>

                                    <?php if (
                                        !empty($product['product_image'])
                                    ): ?>

                                        <img
                                            src="<?= $uploadWebPath . htmlspecialchars(
                                                $product['product_image']
                                            ) ?>"
                                            alt="<?= htmlspecialchars(
                                                $product['product_name']
                                            ) ?>"
                                            class="product-list-image"
                                        >

                                    <?php else: ?>

                                        <div class="image-placeholder">
                                            No Photo
                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- CODE -->

                                <td>

                                    <span class="product-code">

                                        <?= htmlspecialchars(
                                            $product['product_code']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- PRODUCT -->

                                <td>

                                    <div class="product-info">

                                        <span class="product-name">

                                            <?= htmlspecialchars(
                                                $product['product_name']
                                            ) ?>

                                        </span>


                                        <?php if (
                                            !empty($product['description'])
                                        ): ?>

                                            <details class="product-description">

                                                <summary>
                                                    View description
                                                </summary>

                                                <div class="description-content">

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $product['description']
                                                        )
                                                    ) ?>

                                                </div>

                                            </details>

                                        <?php endif; ?>

                                    </div>

                                </td>


                                <!-- WEIGHT -->

                                <td>

                                    <span class="product-weight">

                                        <?= number_format(
                                            (float)$product['weight_kg'],
                                            2
                                        ) ?>
                                        kg

                                    </span>

                                    <span class="product-unit">
                                        per unit
                                    </span>

                                </td>


                                <!-- PRICE -->

                                <td>

                                    <span class="product-price">

                                        ₱<?= number_format(
                                            (float)$product['price'],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STOCK -->

                                <td>

                                    <?php
                                    $stock = (int)$product['stock_quantity'];

                                    $stockClass = '';

                                    if ($stock === 0) {
                                        $stockClass = 'stock-out';
                                    } elseif ($stock <= 5) {
                                        $stockClass = 'stock-low';
                                    }
                                    ?>

                                    <span
                                        class="stock-number <?= $stockClass ?>"
                                    >
                                        <?= $stock ?>
                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if (
                                        $product['status'] === 'Available'
                                    ): ?>

                                        <span
                                            class="
                                                product-status
                                                product-status-available
                                            "
                                        >
                                            Available
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                product-status
                                                product-status-unavailable
                                            "
                                        >
                                            Unavailable
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="product-actions">


                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="btn-secondary"
                                            onclick='editProduct(
                                                <?= json_encode(
                                                    $product,
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_AMP |
                                                    JSON_HEX_QUOT
                                                ) ?>
                                            )'
                                        >
                                            Edit
                                        </button>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Delete this product?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= (int)$product['product_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_product"
                                                class="btn-danger"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </div>

</main>


<!-- =============================================================
     EDIT PRODUCT MODAL
============================================================== -->

<div
    id="editProductModal"
    class="edit-product-modal"
>

    <div class="edit-product-dialog">


        <!-- MODAL HEADER -->

        <div class="edit-product-header">

            <div>

                <h2 class="edit-product-title">
                    Edit Product
                </h2>

                <p class="edit-product-subtitle">
                    Update the product information below.
                </p>

            </div>

            <button
                type="button"
                class="btn-secondary modal-close"
                onclick="closeEditProduct()"
                aria-label="Close"
            >
                ×
            </button>

        </div>


        <!-- MODAL BODY -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="edit-product-body">

                <input
                    type="hidden"
                    name="product_id"
                    id="edit_product_id"
                >


                <!-- CURRENT IMAGE -->

                <div class="edit-image-section">

                    <div>

                        <img
                            id="editImagePreview"
                            class="edit-image-preview"
                            src=""
                            alt="Product image"
                            style="display:none;"
                        >

                        <div
                            id="editNoImage"
                            class="image-placeholder"
                            style="
                                width:150px;
                                height:150px;
                            "
                        >
                            No Photo
                        </div>

                    </div>


                    <div class="edit-image-details">

                        <label for="edit_product_image">
                            Product Photo
                        </label>

                        <input
                            type="file"
                            name="product_image"
                            id="edit_product_image"
                            class="form-input"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <small>
                            Select a new image only if you want to replace
                            the current product photo.
                            JPG, PNG, or WEBP only, maximum 5 MB.
                        </small>

                    </div>

                </div>


                <!-- EDIT FIELDS -->

                <div class="edit-form-grid">


                    <!-- CODE -->

                    <div class="edit-field">

                        <label for="edit_product_code">
                            Product Code
                        </label>

                        <input
                            type="text"
                            name="product_code"
                            id="edit_product_code"
                            class="form-input"
                            required
                        >

                    </div>


                    <!-- NAME -->

                    <div class="edit-field">

                        <label for="edit_product_name">
                            Product Name
                        </label>

                        <input
                            type="text"
                            name="product_name"
                            id="edit_product_name"
                            class="form-input"
                            required
                        >

                    </div>


                    <!-- WEIGHT -->

                    <div class="edit-field">

                        <label for="edit_weight">
                            Weight per Unit (kg)
                        </label>

                        <input
                            type="number"
                            name="weight_kg"
                            id="edit_weight"
                            class="form-input"
                            step="0.01"
                            min="0.01"
                            required
                        >

                    </div>


                    <!-- PRICE -->

                    <div class="edit-field">

                        <label for="edit_price">
                            Price
                        </label>

                        <input
                            type="number"
                            name="price"
                            id="edit_price"
                            class="form-input"
                            step="0.01"
                            min="0"
                            required
                        >

                    </div>


                    <!-- STOCK -->

                    <div class="edit-field">

                        <label for="edit_stock">
                            Stock Quantity
                        </label>

                        <input
                            type="number"
                            name="stock_quantity"
                            id="edit_stock"
                            class="form-input"
                            min="0"
                            required
                        >

                    </div>


                    <!-- STATUS -->

                    <div class="edit-field">

                        <label for="edit_status">
                            Status
                        </label>

                        <select
                            name="status"
                            id="edit_status"
                            class="form-input"
                        >

                            <option value="Available">
                                Available
                            </option>

                            <option value="Unavailable">
                                Unavailable
                            </option>

                        </select>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="edit-field edit-field-full">

                        <label for="edit_description">
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="edit_description"
                            class="form-input"
                            rows="6"
                            placeholder="Enter product description..."
                        ></textarea>

                    </div>


                </div>

            </div>


            <!-- MODAL ACTIONS -->

            <div class="edit-form-actions">

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeEditProduct()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="update_product"
                    class="btn-primary"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =============================================================
   ADD PRODUCT IMAGE PREVIEW
============================================================= */

const addImageInput =
    document.getElementById('product_image');

const addImagePreview =
    document.getElementById('addImagePreview');


if (addImageInput) {

    addImageInput.addEventListener(
        'change',
        function () {

            const file = this.files[0];

            if (!file) {

                addImagePreview.style.display = 'none';
                addImagePreview.src = '';

                return;
            }


            const reader = new FileReader();


            reader.onload = function (event) {

                addImagePreview.src =
                    event.target.result;

                addImagePreview.style.display =
                    'block';

            };


            reader.readAsDataURL(file);

        }
    );

}


/* =============================================================
   EDIT PRODUCT
============================================================= */

function editProduct(product) {

    document.getElementById(
        'edit_product_id'
    ).value =
        product.product_id;


    document.getElementById(
        'edit_product_code'
    ).value =
        product.product_code || '';


    document.getElementById(
        'edit_product_name'
    ).value =
        product.product_name || '';


    document.getElementById(
        'edit_weight'
    ).value =
        product.weight_kg || '';


    document.getElementById(
        'edit_price'
    ).value =
        product.price || '';


    document.getElementById(
        'edit_stock'
    ).value =
        product.stock_quantity || 0;


    document.getElementById(
        'edit_status'
    ).value =
        product.status || 'Available';


    document.getElementById(
        'edit_description'
    ).value =
        product.description || '';


    const preview =
        document.getElementById(
            'editImagePreview'
        );

    const noImage =
        document.getElementById(
            'editNoImage'
        );


    document.getElementById(
        'edit_product_image'
    ).value = '';


    if (product.product_image) {

        preview.src =
            '<?= $uploadWebPath ?>' +
            product.product_image;

        preview.style.display =
            'block';

        noImage.style.display =
            'none';

    } else {

        preview.src = '';

        preview.style.display =
            'none';

        noImage.style.display =
            'flex';

    }


    document.getElementById(
        'editProductModal'
    ).style.display =
        'flex';


    document.body.style.overflow =
        'hidden';
}


/* =============================================================
   EDIT IMAGE PREVIEW
============================================================= */

const editImageInput =
    document.getElementById(
        'edit_product_image'
    );


if (editImageInput) {

    editImageInput.addEventListener(
        'change',
        function () {

            const file = this.files[0];

            if (!file) {
                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    const preview =
                        document.getElementById(
                            'editImagePreview'
                        );

                    const noImage =
                        document.getElementById(
                            'editNoImage'
                        );


                    preview.src =
                        event.target.result;

                    preview.style.display =
                        'block';

                    noImage.style.display =
                        'none';

                };


            reader.readAsDataURL(file);

        }
    );

}


/* =============================================================
   CLOSE EDIT MODAL
============================================================= */

function closeEditProduct() {

    document.getElementById(
        'editProductModal'
    ).style.display =
        'none';

    document.body.style.overflow =
        '';
}


/* =============================================================
   CLICK OUTSIDE MODAL
============================================================= */

const editModal =
    document.getElementById(
        'editProductModal'
    );


if (editModal) {

    editModal.addEventListener(
        'click',
        function (event) {

            if (event.target === this) {

                closeEditProduct();

            }

        }
    );

}


/* =============================================================
   ESC KEY TO CLOSE MODAL
============================================================= */

document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {

            const modal =
                document.getElementById(
                    'editProductModal'
                );

            if (
                modal &&
                modal.style.display === 'flex'
            ) {

                closeEditProduct();

            }

        }

    }
);

</script>


</body>
</html>