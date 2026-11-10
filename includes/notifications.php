<?php

/**
 * Hong Sing LKRR Commercial
 * Central Notification Helper
 *
 * File:
 * includes/notifications.php
 *
 * Purpose:
 * - Create role-based in-app notifications
 * - Notify customers
 * - Notify drivers
 * - Notify admins
 * - Notify super admins
 * - Notify all management users
 * - Notify all relevant users of an order
 */


/*
|--------------------------------------------------------------------------
| PREVENT DIRECT ACCESS
|--------------------------------------------------------------------------
|
| This file is intended to be included by other PHP files.
|
*/


/*
|--------------------------------------------------------------------------
| CREATE SINGLE NOTIFICATION
|--------------------------------------------------------------------------
|
| Creates one notification for one specific user.
|
| Example:
|
| createNotification(
|     $db,
|     15,
|     null,
|     'Customer',
|     4,
|     'Your order has been approved.'
| );
|
*/

function createNotification(
    PDO $db,
    int $orderId,
    ?int $statusId,
    string $recipientRole,
    int $recipientId,
    string $message,
    string $notificationType = 'In-App',
    ?string $recipientPhone = null
): bool {

    /*
    |--------------------------------------------------------------------------
    | VALIDATE ROLE
    |--------------------------------------------------------------------------
    */

    $allowedRoles = [
        'Customer',
        'Driver',
        'Admin',
        'Super Admin'
    ];

    if (!in_array(
        $recipientRole,
        $allowedRoles,
        true
    )) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE USER
    |--------------------------------------------------------------------------
    */

    if ($recipientId <= 0) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE ORDER
    |--------------------------------------------------------------------------
    */

    if ($orderId <= 0) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE MESSAGE
    |--------------------------------------------------------------------------
    */

    $message = trim($message);

    if ($message === '') {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE NOTIFICATION TYPE
    |--------------------------------------------------------------------------
    */

    $allowedTypes = [
        'In-App',
        'SMS'
    ];

    if (!in_array(
        $notificationType,
        $allowedTypes,
        true
    )) {
        $notificationType = 'In-App';
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        INSERT INTO notification
        (
            order_id,
            status_id,
            notification_type,
            recipient_role,
            recipient_id,
            recipient_phone,
            is_read,
            sent_at,
            delivery_status,
            message
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            0,
            NOW(),
            'Sent',
            ?
        )
    ");


    return $stmt->execute([
        $orderId,
        $statusId,
        $notificationType,
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


    /*
    |--------------------------------------------------------------------------
    | GET CUSTOMER PHONE
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT phone_number
        FROM customer
        WHERE customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $customerId
    ]);

    $customer =
        $stmt->fetch(PDO::FETCH_ASSOC);


    $phone =
        $customer['phone_number']
        ?? null;


    /*
    |--------------------------------------------------------------------------
    | CREATE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    return createNotification(
        $db,
        $orderId,
        $statusId,
        'Customer',
        $customerId,
        $message,
        'In-App',
        $phone
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


    /*
    |--------------------------------------------------------------------------
    | GET DRIVER PHONE
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT phone_number
        FROM driver
        WHERE driver_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $driverId
    ]);

    $driver =
        $stmt->fetch(PDO::FETCH_ASSOC);


    $phone =
        $driver['phone_number']
        ?? null;


    /*
    |--------------------------------------------------------------------------
    | CREATE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    return createNotification(
        $db,
        $orderId,
        $statusId,
        'Driver',
        $driverId,
        $message,
        'In-App',
        $phone
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
    ?int $statusId = null
): int {

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE ADMINS
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            user_id,
            phone_number
        FROM system_user
        WHERE role = 'Admin'
          AND status = 'Active'
    ");

    $stmt->execute();

    $admins =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    $created = 0;


    foreach ($admins as $admin) {

        $adminId =
            (int) $admin['user_id'];


        if ($adminId <= 0) {
            continue;
        }


        $success =
            createNotification(
                $db,
                $orderId,
                $statusId,
                'Admin',
                $adminId,
                $message,
                'In-App',
                $admin['phone_number']
                    ?? null
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
    ?int $statusId = null
): int {

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE SUPER ADMINS
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            user_id,
            phone_number
        FROM system_user
        WHERE role = 'Super Admin'
          AND status = 'Active'
    ");

    $stmt->execute();

    $superAdmins =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    $created = 0;


    foreach ($superAdmins as $superAdmin) {

        $superAdminId =
            (int) $superAdmin['user_id'];


        if ($superAdminId <= 0) {
            continue;
        }


        $success =
            createNotification(
                $db,
                $orderId,
                $statusId,
                'Super Admin',
                $superAdminId,
                $message,
                'In-App',
                $superAdmin['phone_number']
                    ?? null
            );


        if ($success) {
            $created++;
        }

    }


    return $created;
}


/*
|--------------------------------------------------------------------------
| NOTIFY MANAGEMENT
|--------------------------------------------------------------------------
|
| Sends notification to:
|
| - Admin
| - Super Admin
|
*/

function notifyManagement(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): array {

    $adminCount =
        notifyAdmins(
            $db,
            $orderId,
            $message,
            $statusId
        );


    $superAdminCount =
        notifySuperAdmins(
            $db,
            $orderId,
            $message,
            $statusId
        );


    return [
        'admins' =>
            $adminCount,

        'super_admins' =>
            $superAdminCount,

        'total' =>
            $adminCount +
            $superAdminCount
    ];
}


/*
|--------------------------------------------------------------------------
| GET ORDER NOTIFICATION INFORMATION
|--------------------------------------------------------------------------
|
| Returns:
|
| order_id
| order_number
| customer_id
| driver_id
| completion_status
| customer_name
| driver_name
|
*/

function getOrderNotificationData(
    PDO $db,
    int $orderId
): ?array {

    if ($orderId <= 0) {
        return null;
    }


    $stmt = $db->prepare("
        SELECT

            o.order_id,
            o.order_number,
            o.customer_id,
            o.driver_id,
            o.truck_id,
            o.completion_status,
            o.priority_level,

            c.full_name
                AS customer_name,

            d.full_name
                AS driver_name

        FROM `order` o

        LEFT JOIN customer c
            ON c.customer_id =
               o.customer_id

        LEFT JOIN driver d
            ON d.driver_id =
               o.driver_id

        WHERE o.order_id = ?

        LIMIT 1
    ");


    $stmt->execute([
        $orderId
    ]);


    $order =
        $stmt->fetch(PDO::FETCH_ASSOC);


    return $order ?: null;
}


/*
|--------------------------------------------------------------------------
| NOTIFY ORDER CUSTOMER
|--------------------------------------------------------------------------
|
| Automatically gets the customer_id from the order.
|
*/

function notifyOrderCustomer(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return false;
    }


    $customerId =
        (int) (
            $order['customer_id']
            ?? 0
        );


    if ($customerId <= 0) {
        return false;
    }


    return notifyCustomer(
        $db,
        $orderId,
        $customerId,
        $message,
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFY ORDER DRIVER
|--------------------------------------------------------------------------
|
| Automatically gets the currently assigned driver.
|
*/

function notifyOrderDriver(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return false;
    }


    $driverId =
        (int) (
            $order['driver_id']
            ?? 0
        );


    if ($driverId <= 0) {
        return false;
    }


    return notifyDriver(
        $db,
        $orderId,
        $driverId,
        $message,
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFY EVERYONE INVOLVED IN ORDER
|--------------------------------------------------------------------------
|
| Sends notifications to:
|
| Customer
| Assigned Driver
| All active Admins
| All active Super Admins
|
| Different messages may be supplied for each role.
|
*/

function notifyOrderUsers(
    PDO $db,
    int $orderId,
    array $messages,
    ?int $statusId = null
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {

        return [
            'customer' => false,
            'driver' => false,
            'admins' => 0,
            'super_admins' => 0
        ];

    }


    $results = [

        'customer' => false,

        'driver' => false,

        'admins' => 0,

        'super_admins' => 0

    ];


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $messages['customer']
        )
    ) {

        $customerId =
            (int) (
                $order['customer_id']
                ?? 0
            );


        if ($customerId > 0) {

            $results['customer'] =
                notifyCustomer(
                    $db,
                    $orderId,
                    $customerId,
                    $messages['customer'],
                    $statusId
                );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DRIVER
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $messages['driver']
        )
    ) {

        $driverId =
            (int) (
                $order['driver_id']
                ?? 0
            );


        if ($driverId > 0) {

            $results['driver'] =
                notifyDriver(
                    $db,
                    $orderId,
                    $driverId,
                    $messages['driver'],
                    $statusId
                );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $messages['admin']
        )
    ) {

        $results['admins'] =
            notifyAdmins(
                $db,
                $orderId,
                $messages['admin'],
                $statusId
            );

    }


    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $messages['super_admin']
        )
    ) {

        $results['super_admins'] =
            notifySuperAdmins(
                $db,
                $orderId,
                $messages['super_admin'],
                $statusId
            );

    }


    return $results;
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: NEW ORDER
|--------------------------------------------------------------------------
*/

function notifyNewOrder(
    PDO $db,
    int $orderId
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    $customerName =
        $order['customer_name']
        ?? 'Customer';


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "Your order {$orderNumber} has been submitted successfully and is waiting for approval.",

            'admin' =>
                "New order {$orderNumber} was placed by {$customerName} and requires review.",

            'super_admin' =>
                "A new customer order {$orderNumber} was created by {$customerName}."

        ]
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: ORDER APPROVED
|--------------------------------------------------------------------------
*/

function notifyOrderApproved(
    PDO $db,
    int $orderId
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "Your order {$orderNumber} has been approved and is ready for delivery assignment.",

            'admin' =>
                "Order {$orderNumber} has been approved.",

            'super_admin' =>
                "Order {$orderNumber} has been approved and is awaiting driver assignment."

        ]
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: DRIVER ASSIGNED
|--------------------------------------------------------------------------
*/

function notifyDriverAssigned(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    $driverName =
        $order['driver_name']
        ?? 'the assigned driver';


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "A driver has been assigned to your order {$orderNumber}.",

            'driver' =>
                "You have been assigned delivery {$orderNumber}. Please review and accept the delivery.",

            'admin' =>
                "Order {$orderNumber} was successfully assigned to {$driverName}.",

            'super_admin' =>
                "Delivery {$orderNumber} was assigned to {$driverName}."

        ],
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: DRIVER ACCEPTED DELIVERY
|--------------------------------------------------------------------------
*/

function notifyDeliveryAccepted(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    $driverName =
        $order['driver_name']
        ?? 'The assigned driver';


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "{$driverName} has accepted your delivery for order {$orderNumber}.",

            'driver' =>
                "You accepted delivery {$orderNumber}.",

            'admin' =>
                "{$driverName} accepted delivery {$orderNumber}.",

            'super_admin' =>
                "{$driverName} accepted delivery {$orderNumber}."

        ],
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: DELIVERY STARTED
|--------------------------------------------------------------------------
*/

function notifyDeliveryStarted(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    $driverName =
        $order['driver_name']
        ?? 'The assigned driver';


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "Your order {$orderNumber} is now in transit.",

            'driver' =>
                "Delivery {$orderNumber} has started. GPS tracking is now active.",

            'admin' =>
                "{$driverName} started delivery {$orderNumber}.",

            'super_admin' =>
                "Delivery {$orderNumber} is now in transit with {$driverName}."

        ],
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: DELIVERY COMPLETED
|--------------------------------------------------------------------------
*/

function notifyDeliveryCompleted(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    $driverName =
        $order['driver_name']
        ?? 'The assigned driver';


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "Your order {$orderNumber} has been delivered successfully.",

            'driver' =>
                "Delivery {$orderNumber} has been completed successfully.",

            'admin' =>
                "{$driverName} completed delivery {$orderNumber}.",

            'super_admin' =>
                "Delivery {$orderNumber} has been completed successfully."

        ],
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: ORDER CANCELLED
|--------------------------------------------------------------------------
*/

function notifyOrderCancelled(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "Order {$orderNumber} has been cancelled.",

            'driver' =>
                "Delivery {$orderNumber} has been cancelled.",

            'admin' =>
                "Order {$orderNumber} has been cancelled.",

            'super_admin' =>
                "Order {$orderNumber} has been cancelled."

        ],
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION: DELIVERY FAILED
|--------------------------------------------------------------------------
*/

function notifyDeliveryFailed(
    PDO $db,
    int $orderId,
    string $reason = '',
    ?int $statusId = null
): array {

    $order =
        getOrderNotificationData(
            $db,
            $orderId
        );


    if (!$order) {
        return [];
    }


    $orderNumber =
        $order['order_number']
        ?? ('#' . $orderId);


    $driverName =
        $order['driver_name']
        ?? 'The assigned driver';


    $reason =
        trim($reason);


    $reasonText =
        $reason !== ''
            ? " Reason: {$reason}"
            : '';


    return notifyOrderUsers(
        $db,
        $orderId,
        [

            'customer' =>
                "There was an issue completing delivery {$orderNumber}.{$reasonText}",

            'driver' =>
                "Delivery {$orderNumber} has been recorded as failed.{$reasonText}",

            'admin' =>
                "{$driverName} reported delivery {$orderNumber} as failed.{$reasonText}",

            'super_admin' =>
                "Delivery {$orderNumber} was reported as failed.{$reasonText}"

        ],
        $statusId
    );
}