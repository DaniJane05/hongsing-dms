<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = (int) currentUserId();

$orderId = (int) (
    $_POST['order_id']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| VALIDATE ORDER ID
|--------------------------------------------------------------------------
*/

if ($orderId <= 0) {

    header(
        "Location: deliveries.php?error=invalid_order"
    );

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $db->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK ORDER
    |--------------------------------------------------------------------------
    */

    $orderStmt = $db->prepare("
        SELECT
            order_id,
            order_number,
            driver_id,
            truck_id,
            completion_status,
            total_weight_kg

        FROM `order`

        WHERE order_id = ?

        FOR UPDATE
    ");

    $orderStmt->execute([
        $orderId
    ]);

    $order = $orderStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$order) {

        throw new RuntimeException(
            'Delivery not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MAKE SURE THIS DELIVERY BELONGS TO DRIVER
    |--------------------------------------------------------------------------
    */

    if (
        (int) $order['driver_id']
        !==
        $driverId
    ) {

        throw new RuntimeException(
            'This delivery is not assigned to you.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ONLY ASSIGNED CAN BE ACCEPTED
    |--------------------------------------------------------------------------
    */

    if (
        $order['completion_status']
        !== 'Assigned'
    ) {

        throw new RuntimeException(
            'This delivery is no longer waiting for acceptance.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOCK DRIVER
    |--------------------------------------------------------------------------
    */

    $driverStmt = $db->prepare("
        SELECT
            driver_id,
            full_name,
            status,
            current_truck_id

        FROM driver

        WHERE driver_id = ?

        FOR UPDATE
    ");

    $driverStmt->execute([
        $driverId
    ]);

    $driver = $driverStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$driver) {

        throw new RuntimeException(
            'Driver account not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DRIVER MUST NOT BE ON ANOTHER ACTIVE DELIVERY
    |--------------------------------------------------------------------------
    */

    $activeStmt = $db->prepare("
        SELECT COUNT(*)

        FROM `order`

        WHERE driver_id = ?

        AND completion_status = 'In Transit'

        AND order_id <> ?
    ");

    $activeStmt->execute([
        $driverId,
        $orderId
    ]);

    $activeDeliveries =
        (int) $activeStmt->fetchColumn();


    if ($activeDeliveries > 0) {

        throw new RuntimeException(
            'You already have a delivery in progress.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We ONLY change:
    |
    | Assigned → Accepted
    |
    | We DO NOT change driver.status here.
    |
    */

    $updateOrderStmt = $db->prepare("
        UPDATE `order`

        SET completion_status = 'Accepted'

        WHERE order_id = ?

        AND driver_id = ?

        AND completion_status = 'Assigned'
    ");

    $updateOrderStmt->execute([
        $orderId,
        $driverId
    ]);


    if (
        $updateOrderStmt->rowCount() !== 1
    ) {

        throw new RuntimeException(
            'The delivery could not be accepted.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DELIVERY STATUS HISTORY
    |--------------------------------------------------------------------------
    |
    | delivery_status does not have "Accepted",
    | so we keep the delivery-status history at
    | Assigned while order.completion_status becomes Accepted.
    |
    */

    $statusStmt = $db->prepare("
        INSERT INTO delivery_status (
            order_id,
            status,
            notes
        )

        VALUES (
            ?,
            'Assigned',
            ?
        )
    ");

    $statusStmt->execute([
        $orderId,
        'Driver accepted the assigned delivery.'
    ]);


    /*
    |--------------------------------------------------------------------------
    | DRIVER NOTIFICATION / SYSTEM NOTIFICATION
    |--------------------------------------------------------------------------
    |
    | Optional notification for Admin.
    |
    */

    $adminStmt = $db->query("
        SELECT user_id

        FROM system_user

        WHERE role = 'Admin'
    ");

    $admins = $adminStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    $notificationStmt = $db->prepare("
        INSERT INTO notification (
            order_id,
            status_id,
            notification_type,
            recipient_role,
            recipient_id,
            recipient_phone,
            is_read,
            delivery_status,
            message
        )

        VALUES (
            ?,
            NULL,
            'In-App',
            'Admin',
            ?,
            NULL,
            0,
            'Sent',
            ?
        )
    ");


    foreach ($admins as $admin) {

        $notificationStmt->execute([
            $orderId,
            (int) $admin['user_id'],

            "Driver {$driver['full_name']} accepted delivery "
            . "{$order['order_number']}."
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $db->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    header(
        "Location: delivery_details.php?id="
        . $orderId
        . "&success=accepted"
    );

    exit;


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        $db->inTransaction()
    ) {

        $db->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | ERROR
    |--------------------------------------------------------------------------
    */

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