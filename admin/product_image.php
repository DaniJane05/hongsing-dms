<?php

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId) {
    http_response_code(400);
    exit('Invalid product ID.');
}

try {
    $db = Database::getConnection();

    $stmt = $db->prepare("
        SELECT product_image, product_image_type
        FROM product
        WHERE product_id = ?
        LIMIT 1
    ");

    $stmt->execute([$productId]);

    $product = $stmt->fetch();

    if (!$product || empty($product['product_image'])) {
        http_response_code(404);
        exit;
    }

    $image = $product['product_image'];
    $mime  = $product['product_image_type'] ?? '';

    /*
     * If product_image_type is empty, detect the image type
     * automatically from the stored binary data.
     */
    if (!$mime) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($image);
    }

    /*
     * Only allow common web image formats.
     */
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml'
    ];

    if (!in_array($mime, $allowedTypes, true)) {
        http_response_code(415);
        exit('Unsupported image type.');
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($image));
    header('Cache-Control: public, max-age=86400');

    echo $image;
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to load product image.');
}