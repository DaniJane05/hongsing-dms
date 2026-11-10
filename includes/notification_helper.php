<?php

/**
 * ============================================================================
 * HONG SING LKRR COMMERCIAL
 * Notification Helper
 * ============================================================================
 *
 * Centralized notification functions for:
 *
 * - Customer
 * - Driver
 * - Admin
 * - Super Admin
 *
 * IMPORTANT:
 * The notification table is expected to contain:
 *
 * notification_id
 * order_id
 * status_id
 * notification_type
 * recipient_role
 * recipient_id
 * is_read
 * delivery_status
 * message
 * created_at
 *
 * ============================================================================
 */


/*
|--------------------------------------------------------------------------
| Create Notification
|--------------------------------------------------------------------------
|
| Base function used by all notification helpers.
|
*/

function createNotification(
    PDO $db,
    int $orderId,
    string $recipientRole,
    int $recipientId,
    string $message,
    ?int $statusId = null,
    string $notificationType = 'In-App'
): bool {

    if ($recipientId <= 0) {
        return false;
    }

    $message = trim($message);

    if ($message === '') {
        return false;
    }

    $allowedRoles = [
        'Customer',
        'Driver',
        'Admin',
        'Super Admin'
    ];

    if (
        !in_array(
            $recipientRole,
            $allowedRoles,
            true
        )
    ) {
        return false;
    }


    $stmt = $db->prepare("
        INSERT INTO notification
        (
            order_id,
            status_id,
            notification_type,
            recipient_role,
            recipient_id,
            is_read,
            delivery_status,
            message
        )
        VALUES
        (
            :order_id,
            :status_id,
            :notification_type,
            :recipient_role,
            :recipient_id,
            0,
            'Sent',
            :message
        )
    ");


    return $stmt->execute([
        ':order_id'         => $orderId,
        ':status_id'        => $statusId,
        ':notification_type'=> $notificationType,
        ':recipient_role'   => $recipientRole,
        ':recipient_id'     => $recipientId,
        ':message'          => $message
    ]);
}


/*
|--------------------------------------------------------------------------
| Get Order Information
|--------------------------------------------------------------------------
|
| Used internally so every notification function does not need to repeat
| the same order query.
|
*/

function getNotificationOrder(
    PDO $db,
    int $orderId
): ?array {

    $stmt = $db->prepare("
        SELECT
            o.order_id,
            o.order_number,
            o.customer_id,
            o.driver_id,
            o.truck_id,
            o.completion_status

        FROM `order` o

        WHERE o.order_id = ?

        LIMIT 1
    ");


    $stmt->execute([
        $orderId
    ]);


    $order =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    return $order ?: null;
}


/*
|--------------------------------------------------------------------------
| Notify Customer
|--------------------------------------------------------------------------
*/

function notifyCustomer(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (
        !$order ||
        empty($order['customer_id'])
    ) {
        return false;
    }


    return createNotification(
        $db,
        $orderId,
        'Customer',
        (int) $order['customer_id'],
        $message,
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| Notify Driver
|--------------------------------------------------------------------------
*/

function notifyDriver(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (
        !$order ||
        empty($order['driver_id'])
    ) {
        return false;
    }


    return createNotification(
        $db,
        $orderId,
        'Driver',
        (int) $order['driver_id'],
        $message,
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| Get Admin IDs
|--------------------------------------------------------------------------
|
| This assumes Admin accounts are stored in system_user.
|
| If your actual login/account table is different, this query must match
| that table.
|
*/

function getAdminNotificationRecipients(
    PDO $db
): array {

    $stmt = $db->prepare("
        SELECT
            user_id

        FROM system_user

        WHERE role = 'Admin'
    ");

    $stmt->execute();


    return array_map(
        'intval',
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
    );
}


/*
|--------------------------------------------------------------------------
| Get Super Admin IDs
|--------------------------------------------------------------------------
*/

function getSuperAdminNotificationRecipients(
    PDO $db
): array {

    $stmt = $db->prepare("
        SELECT
            user_id

        FROM system_user

        WHERE role = 'Super Admin'
    ");

    $stmt->execute();


    return array_map(
        'intval',
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
    );
}


/*
|--------------------------------------------------------------------------
| Notify All Admins
|--------------------------------------------------------------------------
*/

function notifyAdmins(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $adminIds =
        getAdminNotificationRecipients(
            $db
        );


    if (empty($adminIds)) {
        return true;
    }


    $success = true;


    foreach ($adminIds as $adminId) {

        $created =
            createNotification(
                $db,
                $orderId,
                'Admin',
                $adminId,
                $message,
                $statusId
            );


        if (!$created) {
            $success = false;
        }
    }


    return $success;
}


/*
|--------------------------------------------------------------------------
| Notify All Super Admins
|--------------------------------------------------------------------------
*/

function notifySuperAdmins(
    PDO $db,
    int $orderId,
    string $message,
    ?int $statusId = null
): bool {

    $superAdminIds =
        getSuperAdminNotificationRecipients(
            $db
        );


    if (empty($superAdminIds)) {
        return true;
    }


    $success = true;


    foreach (
        $superAdminIds
        as $superAdminId
    ) {

        $created =
            createNotification(
                $db,
                $orderId,
                'Super Admin',
                $superAdminId,
                $message,
                $statusId
            );


        if (!$created) {
            $success = false;
        }
    }


    return $success;
}


/*
|--------------------------------------------------------------------------
| EVENT: New Order
|--------------------------------------------------------------------------
|
| Customer places an order.
|
| Customer:
|   Confirmation
|
| Admin:
|   New order received
|
| Super Admin:
|   New order recorded
|
*/

function notifyNewOrder(
    PDO $db,
    int $orderId
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    notifyCustomer(
        $db,
        $orderId,
        "Your order {$orderNumber} has been submitted successfully and is waiting for administrator approval."
    );


    notifyAdmins(
        $db,
        $orderId,
        "New order {$orderNumber} has been submitted and is waiting for approval."
    );


    notifySuperAdmins(
        $db,
        $orderId,
        "New order {$orderNumber} has been recorded in the system."
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Order Approved
|--------------------------------------------------------------------------
*/

function notifyOrderApproved(
    PDO $db,
    int $orderId
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    notifyCustomer(
        $db,
        $orderId,
        "Your order {$orderNumber} has been approved and is now waiting for delivery assignment."
    );


    notifySuperAdmins(
        $db,
        $orderId,
        "Order {$orderNumber} has been approved by an administrator."
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Driver Assigned
|--------------------------------------------------------------------------
*/

function notifyDriverAssigned(
    PDO $db,
    int $orderId
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    /*
     * Customer
     */

    notifyCustomer(
        $db,
        $orderId,
        "A driver has been assigned to your order {$orderNumber}."
    );


    /*
     * Driver
     */

    notifyDriver(
        $db,
        $orderId,
        "You have been assigned a new delivery for order {$orderNumber}. Please review the delivery details."
    );


    /*
     * Super Admin
     */

    notifySuperAdmins(
        $db,
        $orderId,
        "A driver has been assigned to order {$orderNumber}."
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Driver Accepted Delivery
|--------------------------------------------------------------------------
*/

function notifyDriverAccepted(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    notifyCustomer(
        $db,
        $orderId,
        "The assigned driver has accepted the delivery for your order {$orderNumber}.",
        $statusId
    );


    notifyAdmins(
        $db,
        $orderId,
        "The assigned driver has accepted delivery for order {$orderNumber}.",
        $statusId
    );


    notifySuperAdmins(
        $db,
        $orderId,
        "The assigned driver has accepted delivery for order {$orderNumber}.",
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Delivery Started
|--------------------------------------------------------------------------
*/

function notifyDeliveryStarted(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    notifyCustomer(
        $db,
        $orderId,
        "Your order {$orderNumber} is now in transit and is on the way to the delivery location.",
        $statusId
    );


    notifyAdmins(
        $db,
        $orderId,
        "Delivery for order {$orderNumber} has started.",
        $statusId
    );


    notifySuperAdmins(
        $db,
        $orderId,
        "Delivery for order {$orderNumber} is now in transit.",
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Driver Arrived
|--------------------------------------------------------------------------
*/

function notifyDriverArrived(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    notifyCustomer(
        $db,
        $orderId,
        "The driver has arrived at the delivery location for your order {$orderNumber}.",
        $statusId
    );


    notifyAdmins(
        $db,
        $orderId,
        "The driver has arrived at the delivery location for order {$orderNumber}.",
        $statusId
    );


    notifySuperAdmins(
        $db,
        $orderId,
        "The driver has arrived for order {$orderNumber}.",
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Delivery Completed
|--------------------------------------------------------------------------
*/

function notifyDeliveryCompleted(
    PDO $db,
    int $orderId,
    ?int $statusId = null
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    /*
     * Customer
     *
     * The customer's notifications page can provide a
     * "View Receipt" button when the order is delivered.
     */

    notifyCustomer(
        $db,
        $orderId,
        "Your order {$orderNumber} has been successfully delivered. Your delivery receipt and proof of delivery are now available.",
        $statusId
    );


    /*
     * Admin
     */

    notifyAdmins(
        $db,
        $orderId,
        "Delivery for order {$orderNumber} has been completed. The proof of delivery and delivery receipt are now available.",
        $statusId
    );


    /*
     * Super Admin
     */

    notifySuperAdmins(
        $db,
        $orderId,
        "Order {$orderNumber} has been successfully delivered.",
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Delivery Failed
|--------------------------------------------------------------------------
*/

function notifyDeliveryFailed(
    PDO $db,
    int $orderId,
    ?string $reason = null,
    ?int $statusId = null
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    $reason =
        trim(
            (string) $reason
        );


    $customerMessage =
        "The delivery attempt for your order {$orderNumber} could not be completed.";


    $adminMessage =
        "Delivery for order {$orderNumber} was marked as failed.";


    if ($reason !== '') {

        $customerMessage .=
            " Reason: {$reason}";


        $adminMessage .=
            " Reason: {$reason}";
    }


    notifyCustomer(
        $db,
        $orderId,
        $customerMessage,
        $statusId
    );


    notifyAdmins(
        $db,
        $orderId,
        $adminMessage,
        $statusId
    );


    notifySuperAdmins(
        $db,
        $orderId,
        $adminMessage,
        $statusId
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Order Cancelled
|--------------------------------------------------------------------------
*/

function notifyOrderCancelled(
    PDO $db,
    int $orderId,
    ?string $reason = null
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    $reason =
        trim(
            (string) $reason
        );


    $message =
        "Order {$orderNumber} has been cancelled.";


    if ($reason !== '') {

        $message .=
            " Reason: {$reason}";
    }


    notifyCustomer(
        $db,
        $orderId,
        $message
    );


    notifyAdmins(
        $db,
        $orderId,
        $message
    );


    notifySuperAdmins(
        $db,
        $orderId,
        $message
    );
}


/*
|--------------------------------------------------------------------------
| EVENT: Customer Feedback
|--------------------------------------------------------------------------
*/

function notifyFeedbackSubmitted(
    PDO $db,
    int $orderId
): void {

    $order =
        getNotificationOrder(
            $db,
            $orderId
        );


    if (!$order) {
        return;
    }


    $orderNumber =
        $order['order_number'];


    notifyAdmins(
        $db,
        $orderId,
        "New customer feedback has been submitted for order {$orderNumber}."
    );


    notifySuperAdmins(
        $db,
        $orderId,
        "New customer feedback has been submitted for order {$orderNumber}."
    );
}


/*
|--------------------------------------------------------------------------
| Mark Notification As Read
|--------------------------------------------------------------------------
*/

function markNotificationAsRead(
    PDO $db,
    int $notificationId,
    string $recipientRole,
    int $recipientId
): bool {

    $stmt = $db->prepare("
        UPDATE notification

        SET is_read = 1

        WHERE notification_id = ?
          AND recipient_role = ?
          AND recipient_id = ?
    ");


    return $stmt->execute([
        $notificationId,
        $recipientRole,
        $recipientId
    ]);
}


/*
|--------------------------------------------------------------------------
| Mark All Notifications As Read
|--------------------------------------------------------------------------
*/

function markAllNotificationsAsRead(
    PDO $db,
    string $recipientRole,
    int $recipientId
): bool {

    $stmt = $db->prepare("
        UPDATE notification

        SET is_read = 1

        WHERE recipient_role = ?
          AND recipient_id = ?
          AND is_read = 0
    ");


    return $stmt->execute([
        $recipientRole,
        $recipientId
    ]);
}


/*
|--------------------------------------------------------------------------
| Count Unread Notifications
|--------------------------------------------------------------------------
*/

function countUnreadNotifications(
    PDO $db,
    string $recipientRole,
    int $recipientId
): int {

    $stmt = $db->prepare("
        SELECT COUNT(*)

        FROM notification

        WHERE recipient_role = ?
          AND recipient_id = ?
          AND is_read = 0
    ");


    $stmt->execute([
        $recipientRole,
        $recipientId
    ]);


    return (int)
        $stmt->fetchColumn();
}