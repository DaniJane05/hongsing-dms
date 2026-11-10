<?php

/**
 * Hong Sing LKRR Commercial
 * Common System Helper Functions
 *
 * Centralized notification functions for:
 * - Customer
 * - Driver
 * - Admin
 * - Super Admin
 */


/*
|--------------------------------------------------------------------------
| CREATE SINGLE IN-APP NOTIFICATION
|--------------------------------------------------------------------------
*/

function createNotification(
    PDO $db,
    int $orderId,
    string $recipientRole,
    int $recipientId,
    string $message,
    ?int $statusId = null,
    ?string $recipientPhone = null
): bool {

    $allowedRoles = [
        'Customer',
        'Driver',
        'Admin',
        'Super Admin'
    ];

    if (!in_array($recipientRole, $allowedRoles, true)) {
        return false;
    }

    if ($orderId <= 0 || $recipientId <= 0) {
        return false;
    }

    $message = trim($message);

    if ($message === '') {
        return false;
    }

    $stmt = $db->prepare("
        INSERT INTO notification (
            order_id,
            status_id,
            notification_type,
            recipient_role,
            recipient_id,
            recipient_phone,
            is_read,
            read_at,
            sent_at,
            delivery_status,
            message
        )
        VALUES (
            ?,
            ?,
            'In-App',
            ?,
            ?,
            ?,
            0,
            NULL,
            NOW(),
            'Sent',
            ?
        )
    ");

    return $stmt->execute([
        $orderId,
        $statusId,
        $recipientRole,
        $recipientId,
        $recipientPhone,
        $message
    ]);
}


/*
|--------------------------------------------------------------------------
| NOTIFY CUSTOMER
|--------------------------------------------------------------------------
*/

function notifyCustomer(
    PDO $db,
    int $orderId,
    int $customerId,
    string $message,
    ?int $statusId = null
): bool {

    if ($customerId <= 0) {
        return false;
    }

    $stmt = $db->prepare("
        SELECT phone_number
        FROM customer
        WHERE customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([$customerId]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        return false;
    }

    return createNotification(
        $db,
        $orderId,
        'Customer',
        $customerId,
        $message,
        $statusId,
        $customer['phone_number'] ?? null
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFY DRIVER
|--------------------------------------------------------------------------
*/

function notifyDriver(
    PDO $db,
    int $orderId,
    int $driverId,
    string $message,
    ?int $statusId = null
): bool {

    if ($driverId <= 0) {
        return false;
    }

    $stmt = $db->prepare("
        SELECT phone_number
        FROM driver
        WHERE driver_id = ?
        LIMIT 1
    ");

    $stmt->execute([$driverId]);

    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        return false;
    }

    return createNotification(
        $db,
        $orderId,
        'Driver',
        $driverId,
        $message,
        $statusId,
        $driver['phone_number'] ?? null
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFY ALL ADMINS
|--------------------------------------------------------------------------
*/

function notifyAdmins(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null,
    ?int $excludeUserId = null
): int {

    $sql = "
        SELECT
            user_id,
            phone_number
        FROM system_user
        WHERE role = 'Admin'
          AND status = 'Active'
    ";

    $params = [];

    if ($excludeUserId !== null && $excludeUserId > 0) {
        $sql .= " AND user_id <> ?";
        $params[] = $excludeUserId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $created = 0;

    foreach ($admins as $admin) {

        $success = createNotification(
            $db,
            $orderId,
            'Admin',
            (int) $admin['user_id'],
            $message,
            $statusId,
            $admin['phone_number'] ?? null
        );

        if ($success) {
            $created++;
        }
    }

    return $created;
}


/*
|--------------------------------------------------------------------------
| NOTIFY ALL SUPER ADMINS
|--------------------------------------------------------------------------
*/

function notifySuperAdmins(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null,
    ?int $excludeUserId = null
): int {

    $sql = "
        SELECT
            user_id,
            phone_number
        FROM system_user
        WHERE role = 'Super Admin'
          AND status = 'Active'
    ";

    $params = [];

    if ($excludeUserId !== null && $excludeUserId > 0) {
        $sql .= " AND user_id <> ?";
        $params[] = $excludeUserId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $superAdmins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $created = 0;

    foreach ($superAdmins as $superAdmin) {

        $success = createNotification(
            $db,
            $orderId,
            'Super Admin',
            (int) $superAdmin['user_id'],
            $message,
            $statusId,
            $superAdmin['phone_number'] ?? null
        );

        if ($success) {
            $created++;
        }
    }

    return $created;
}


/*
|--------------------------------------------------------------------------
| GET BASIC ORDER INFORMATION
|--------------------------------------------------------------------------
|
| Useful when an operation only has the order_id.
|
*/

function getNotificationOrder(
    PDO $db,
    int $orderId
): ?array {

    if ($orderId <= 0) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT
            order_id,
            order_number,
            customer_id,
            driver_id,
            truck_id,
            completion_status
        FROM `order`
        WHERE order_id = ?
        LIMIT 1
    ");

    $stmt->execute([$orderId]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    return $order ?: null;
}


/*
|--------------------------------------------------------------------------
| NOTIFY ORDER CUSTOMER
|--------------------------------------------------------------------------
|
| Convenience helper so we don't need to manually retrieve customer_id.
|
*/

function notifyOrderCustomer(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $order = getNotificationOrder(
        $db,
        $orderId
    );

    if (!$order) {
        return false;
    }

    return notifyCustomer(
        $db,
        $orderId,
        (int) $order['customer_id'],
        $message,
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFY ASSIGNED DRIVER
|--------------------------------------------------------------------------
*/

function notifyOrderDriver(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $order = getNotificationOrder(
        $db,
        $orderId
    );

    if (!$order || empty($order['driver_id'])) {
        return false;
    }

    return notifyDriver(
        $db,
        $orderId,
        (int) $order['driver_id'],
        $message,
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| CREATE ROLE-BASED ORDER NOTIFICATIONS
|--------------------------------------------------------------------------
|
| Example:
|
| notifyOrderRoles(
|     $db,
|     $orderId,
|     [
|         'Customer' => 'Your order has been approved.',
|         'Admin' => 'Order ORD-001 has been approved.',
|         'Super Admin' => 'Order ORD-001 has been approved.'
|     ]
| );
|
*/

function notifyOrderRoles(
    PDO $db,
    int $orderId,
    array $messages,
    ?int $statusId = null,
    ?int $excludeSystemUserId = null
): void {

    $order = getNotificationOrder(
        $db,
        $orderId
    );

    if (!$order) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER
    |--------------------------------------------------------------------------
    */

    if (
        isset($messages['Customer']) &&
        trim((string) $messages['Customer']) !== ''
    ) {

        notifyCustomer(
            $db,
            $orderId,
            (int) $order['customer_id'],
            (string) $messages['Customer'],
            $statusId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DRIVER
    |--------------------------------------------------------------------------
    */

    if (
        isset($messages['Driver']) &&
        trim((string) $messages['Driver']) !== '' &&
        !empty($order['driver_id'])
    ) {

        notifyDriver(
            $db,
            $orderId,
            (int) $order['driver_id'],
            (string) $messages['Driver'],
            $statusId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */

    if (
        isset($messages['Admin']) &&
        trim((string) $messages['Admin']) !== ''
    ) {

        notifyAdmins(
            $db,
            $orderId,
            (string) $messages['Admin'],
            $statusId,
            $excludeSystemUserId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN
    |--------------------------------------------------------------------------
    */

    if (
        isset($messages['Super Admin']) &&
        trim((string) $messages['Super Admin']) !== ''
    ) {

        notifySuperAdmins(
            $db,
            $orderId,
            (string) $messages['Super Admin'],
            $statusId,
            $excludeSystemUserId
        );
    }
}


/*
|--------------------------------------------------------------------------
| GET LATEST DELIVERY STATUS ID
|--------------------------------------------------------------------------
*/

function getLatestDeliveryStatusId(
    PDO $db,
    int $orderId
): ?int {

    if ($orderId <= 0) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT status_id
        FROM delivery_status
        WHERE order_id = ?
        ORDER BY timestamp DESC, status_id DESC
        LIMIT 1
    ");

    $stmt->execute([$orderId]);

    $statusId = $stmt->fetchColumn();

    return $statusId !== false
        ? (int) $statusId
        : null;
}