<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/delivery_capacity.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = currentUserId();

$orderId = (int) ($_POST['order_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| REDIRECT HELPER
|--------------------------------------------------------------------------
*/

function redirectDelivery(
    int $orderId,
    string $type,
    string $message
): never {

    header(
        'Location: delivery_details.php?id=' .
        $orderId .
        '&' .
        $type .
        '=' .
        urlencode($message)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE ORDER ID
|--------------------------------------------------------------------------
*/

if ($orderId <= 0) {

    header(
        'Location: deliveries.php?error=invalid_order'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE PROOF OF DELIVERY PHOTO
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES['proof_photo']) ||
    !is_array($_FILES['proof_photo'])
) {

    redirectDelivery(
        $orderId,
        'error',
        'Proof of delivery photo is required.'
    );
}


$proofPhoto = $_FILES['proof_photo'];


if (
    ($proofPhoto['error'] ?? UPLOAD_ERR_NO_FILE)
    !== UPLOAD_ERR_OK
) {

    $uploadError =
        $proofPhoto['error']
        ?? UPLOAD_ERR_NO_FILE;


    $message =
        'Unable to upload proof of delivery photo.';


    if ($uploadError === UPLOAD_ERR_NO_FILE) {

        $message =
            'Please take or select a proof of delivery photo.';

    } elseif (
        $uploadError === UPLOAD_ERR_INI_SIZE ||
        $uploadError === UPLOAD_ERR_FORM_SIZE
    ) {

        $message =
            'The proof of delivery photo is too large.';
    }


    redirectDelivery(
        $orderId,
        'error',
        $message
    );
}


/*
|--------------------------------------------------------------------------
| MAXIMUM FILE SIZE — 8 MB
|--------------------------------------------------------------------------
*/

$maxFileSize =
    8 * 1024 * 1024;


if (
    (int) $proofPhoto['size']
    > $maxFileSize
) {

    redirectDelivery(
        $orderId,
        'error',
        'Proof of delivery photo must not exceed 8 MB.'
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY ACTUAL IMAGE MIME TYPE
|--------------------------------------------------------------------------
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$mimeType =
    $finfo->file(
        $proofPhoto['tmp_name']
    );


$allowedTypes = [

    'image/jpeg' => 'jpg',

    'image/png' => 'png',

    'image/webp' => 'webp'

];


if (
    !isset(
        $allowedTypes[$mimeType]
    )
) {

    redirectDelivery(
        $orderId,
        'error',
        'Only JPG, PNG, and WebP proof photos are allowed.'
    );
}


$fileExtension =
    $allowedTypes[$mimeType];


/*
|--------------------------------------------------------------------------
| PREPARE UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$uploadDirectory =
    UPLOAD_DIR_POD;


if (
    !is_dir(
        $uploadDirectory
    )
) {

    if (
        !mkdir(
            $uploadDirectory,
            0755,
            true
        )
    ) {

        redirectDelivery(
            $orderId,
            'error',
            'Proof of delivery upload directory could not be created.'
        );
    }
}


if (
    !is_writable(
        $uploadDirectory
    )
) {

    redirectDelivery(
        $orderId,
        'error',
        'Proof of delivery upload directory is not writable.'
    );
}


/*
|--------------------------------------------------------------------------
| GENERATE SAFE FILE NAME
|--------------------------------------------------------------------------
*/

try {

    $randomName =
        bin2hex(
            random_bytes(16)
        );

} catch (Throwable $e) {

    redirectDelivery(
        $orderId,
        'error',
        'Unable to prepare the proof of delivery photo.'
    );
}


$fileName =
    'pod_order_' .
    $orderId .
    '_' .
    $randomName .
    '.' .
    $fileExtension;


$absolutePath =
    $uploadDirectory .
    $fileName;


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

$uploadedFileSaved = false;


try {

    $db->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK AND VALIDATE ORDER
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            SELECT
                order_id,
                order_number,
                customer_id,
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
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$order) {

        throw new RuntimeException(
            'Order not found.'
        );
    }


    if (
        (int) $order['driver_id']
        !==
        (int) $driverId
    ) {

        throw new RuntimeException(
            'This delivery is not assigned to you.'
        );
    }


    if (
        $order['completion_status']
        !== 'In Transit'
    ) {

        throw new RuntimeException(
            'Only an In Transit delivery can be completed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE PROOF PHOTO
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $proofPhoto['tmp_name'],
            $absolutePath
        )
    ) {

        throw new RuntimeException(
            'Unable to save the proof of delivery photo.'
        );
    }


    $uploadedFileSaved = true;


    /*
    |--------------------------------------------------------------------------
    | SAVE / UPDATE RECEIPT
    |--------------------------------------------------------------------------
    */

    $receiptStmt =
        $db->prepare("
            SELECT
                receipt_id
            FROM receipt
            WHERE order_id = ?
            LIMIT 1
            FOR UPDATE
        ");


    $receiptStmt->execute([
        $orderId
    ]);


    $receipt =
        $receiptStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($receipt) {

        $updateReceiptStmt =
            $db->prepare("
                UPDATE receipt
                SET
                    photo_proof_path = ?,
                    delivery_time = NOW(),
                    delivery_notes = ?
                WHERE receipt_id = ?
            ");


        $updateReceiptStmt->execute([
            $fileName,
            'Proof of delivery uploaded by driver.',
            (int) $receipt['receipt_id']
        ]);

    } else {

        /*
         * Generate a receipt number because the current
         * receipt schema requires one.
         */

        $receiptNumber =
            'POD-' .
            date('YmdHis') .
            '-' .
            $orderId;


        $insertReceiptStmt =
            $db->prepare("
                INSERT INTO receipt (
                    order_id,
                    receipt_number,
                    delivery_time,
                    photo_proof_path,
                    delivery_notes
                )
                VALUES (
                    ?,
                    ?,
                    NOW(),
                    ?,
                    ?
                )
            ");


        $insertReceiptStmt->execute([
            $orderId,
            $receiptNumber,
            $fileName,
            'Proof of delivery uploaded by driver.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | COMPLETE ORDER
    |--------------------------------------------------------------------------
    */

    $updateStmt =
        $db->prepare("
            UPDATE `order`
            SET completion_status = 'Completed'
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
                'Delivered',
                current_latitude,
                current_longitude,
                ?
            FROM driver
            WHERE driver_id = ?
        ");


    $statusStmt->execute([
        $orderId,
        'Delivery completed by driver with proof of delivery photo.',
        $driverId
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE TRUCK LOAD
    |--------------------------------------------------------------------------
    */

    if (
        !empty(
            $order['truck_id']
        )
    ) {

        syncTruckLoad(
            $db,
            (int) $order['truck_id']
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DRIVER STATUS
    |--------------------------------------------------------------------------
    */

    syncDriverStatus(
        $db,
        $driverId
    );


    /*
    |--------------------------------------------------------------------------
    | TOTAL DELIVERIES
    |--------------------------------------------------------------------------
    */

    $deliveryCountStmt =
        $db->prepare("
            UPDATE driver
            SET total_deliveries =
                total_deliveries + 1
            WHERE driver_id = ?
        ");


    $deliveryCountStmt->execute([
        $driverId
    ]);


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $notificationCreated =
        createNotification(
            $db,
            $orderId,
            ROLE_CUSTOMER,
            (int) $order['customer_id'],
            'Your order ' .
                $order['order_number'] .
                ' has been successfully delivered.',
            null,
            'In-App'
        );


    if (!$notificationCreated) {

        throw new RuntimeException(
            'Customer notification could not be created.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $db->commit();


    header(
        'Location: delivery_details.php?id=' .
        $orderId .
        '&success=completed'
    );

    exit;


} catch (Throwable $e) {

    if (
        $db->inTransaction()
    ) {

        $db->rollBack();
    }


    /*
     * If DB processing failed after the image was saved,
     * remove the orphaned image.
     */

    if (
        $uploadedFileSaved &&
        is_file($absolutePath)
    ) {

        @unlink(
            $absolutePath
        );
    }


    redirectDelivery(
        $orderId,
        'error',
        $e->getMessage()
    );
}