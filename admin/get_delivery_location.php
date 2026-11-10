<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$db = Database::getConnection();

$orderId = isset($_GET['order_id'])
    ? (int) $_GET['order_id']
    : 0;

if ($orderId <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid order ID.'
    ]);

    exit;
}

try {

    $stmt = $db->prepare("
        SELECT
            o.order_id,
            o.order_number,
            o.completion_status,
            o.driver_id,

            d.full_name AS driver_name,
            d.status AS driver_status,
            d.current_latitude,
            d.current_longitude,
            d.location_updated_at

        FROM `order` o

        LEFT JOIN driver d
            ON d.driver_id = o.driver_id

        WHERE o.order_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $orderId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Delivery not found.'
        ]);

        exit;
    }

    $hasLocation =
        $row['current_latitude'] !== null &&
        $row['current_longitude'] !== null;

    echo json_encode([
        'success' => true,

        'order_id' =>
            (int) $row['order_id'],

        'order_number' =>
            $row['order_number'],

        'order_status' =>
            $row['completion_status'],

        'driver_id' =>
            $row['driver_id'] !== null
                ? (int) $row['driver_id']
                : null,

        'driver_name' =>
            $row['driver_name'],

        'driver_status' =>
            $row['driver_status'],

        'has_location' =>
            $hasLocation,

        'latitude' =>
            $hasLocation
                ? (float) $row['current_latitude']
                : null,

        'longitude' =>
            $hasLocation
                ? (float) $row['current_longitude']
                : null,

        'updated_at' =>
            $row['location_updated_at']
    ]);

    exit;

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Unable to retrieve the driver location.'
    ]);

    exit;
}
