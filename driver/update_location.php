<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_DRIVER);

header('Content-Type: application/json');

$db = Database::getConnection();

$driverId = currentUserId();

if (!$driverId) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Driver not authenticated.'
    ]);

    exit;
}


/* ==========================================================
   GET GPS DATA
========================================================== */

$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;


/* ==========================================================
   VALIDATE GPS DATA
========================================================== */

if (
    $latitude === null ||
    $longitude === null ||
    !is_numeric($latitude) ||
    !is_numeric($longitude)
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid GPS coordinates.'
    ]);

    exit;
}


$latitude = (float)$latitude;
$longitude = (float)$longitude;


/* ==========================================================
   VALIDATE COORDINATE RANGE
========================================================== */

if (
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'GPS coordinates are out of range.'
    ]);

    exit;
}


/* ==========================================================
   UPDATE DRIVER LOCATION
========================================================== */

try {

    $stmt = $db->prepare("
        UPDATE driver
        SET
            current_latitude = ?,
            current_longitude = ?,
            location_updated_at = NOW()
        WHERE driver_id = ?
    ");

    $stmt->execute([
        $latitude,
        $longitude,
        $driverId
    ]);


    /* ======================================================
       VERIFY DRIVER EXISTS
    ====================================================== */

    $checkStmt = $db->prepare("
        SELECT
            driver_id,
            current_latitude,
            current_longitude,
            location_updated_at
        FROM driver
        WHERE driver_id = ?
        LIMIT 1
    ");

    $checkStmt->execute([$driverId]);

    $driver = $checkStmt->fetch(PDO::FETCH_ASSOC);


    if (!$driver) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Driver record not found.'
        ]);

        exit;
    }


    /* ======================================================
       SUCCESS RESPONSE
    ====================================================== */

    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully.',
        'latitude' => (float)$driver['current_latitude'],
        'longitude' => (float)$driver['current_longitude'],
        'updated_at' => $driver['location_updated_at']
    ]);

    exit;


} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update driver location.'
    ]);

    exit;
}