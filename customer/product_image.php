<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId) {
    http_response_code(400);
    exit('Invalid product ID.');
}

try {

    $db = Database::getConnection();

    $stmt = $db->prepare("
        SELECT
            product_image,
            product_image_type
        FROM product
        WHERE product_id = ?
        LIMIT 1
    ");

    $stmt->execute([$productId]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$product ||
        empty($product['product_image'])
    ) {
        http_response_code(404);
        exit;
    }

    $image = $product['product_image'];

    $mime = trim(
        (string) ($product['product_image_type'] ?? '')
    );

    /*
     * If the MIME type was not stored,
     * detect it from the binary image.
     */
    if ($mime === '') {

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mime = $finfo->buffer($image);
    }

    /*
     * Only allow image formats that can
     * safely be displayed by the browser.
     */
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    if (!in_array($mime, $allowedTypes, true)) {
        http_response_code(415);
        exit('Unsupported image type.');
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($image));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');

    echo $image;
    exit;

} catch (Throwable $e) {

    error_log(
        'Customer product image error: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to load product image.');
}