<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/delivery_capacity.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = currentUserId();

$orderId =
    (int) (
        $_POST['order_id']
        ?? 0
    );


if ($orderId <= 0) {

    header(
        "Location: deliveries.php?error=invalid_order"
    );

    exit;
}


try {

    $db->beginTransaction();


    $stmt = $db->prepare("
        SELECT
            order_id,
            order_number,
            driver_id,
            truck_id,
            completion_status
        FROM `order`
        WHERE order_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $orderId
    ]);

    $order =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$order) {

        throw new RuntimeException(
            'Order not found.'
        );
    }


    if (
        (int)$order['driver_id']
        !==
        (int)$driverId
    ) {

        throw new RuntimeException(
            'This delivery is not assigned to you.'
        );
    }


    if (
        !in_array(
            $order['completion_status'],
            [
                'Accepted',
                'Assigned'
            ],
            true
        )
    ) {

        throw new RuntimeException(
            'This delivery cannot be started.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | START DELIVERY
    |--------------------------------------------------------------------------
    */

    $updateStmt =
        $db->prepare("
            UPDATE `order`
            SET completion_status = 'In Transit'
            WHERE order_id = ?
        ");

    $updateStmt->execute([
        $orderId
    ]);


    /*
    |--------------------------------------------------------------------------
    | DELIVERY STATUS
    |--------------------------------------------------------------------------
    */

    $statusStmt =
        $db->prepare("
            INSERT INTO delivery_status (
                order_id,
                status,
                latitude,
                longitude,
                notes
            )
            SELECT
                ?,
                'In Transit',
                current_latitude,
                current_longitude,
                ?
            FROM driver
            WHERE driver_id = ?
        ");

    $statusStmt->execute([
        $orderId,
        'Delivery started by driver.',
        $driverId
    ]);


    /*
    |--------------------------------------------------------------------------
    | DRIVER = ON DELIVERY
    |--------------------------------------------------------------------------
    */

    syncDriverStatus(
        $db,
        $driverId
    );


    /*
    |--------------------------------------------------------------------------
    | TRUCK LOAD REMAINS OCCUPIED
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $order['truck_id']
        )
    ) {

        syncTruckLoad(
            $db,
            (int)
            $order['truck_id']
        );
    }


    $db->commit();


    header(
        "Location: delivery_details.php?id="
        . $orderId
        . "&success=started"
    );

    exit;


} catch (Throwable $e) {

    if (
        $db->inTransaction()
    ) {

        $db->rollBack();
    }


    header(
        "Location: delivery_details.php?id="
        . $orderId
        . "&error="
        . urlencode(
            $e->getMessage()
        )
    );

    exit;
}