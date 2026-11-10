<?php

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/textbee_sms.php';

/**
 * Generate and send a phone verification OTP.
 */
function sendPhoneVerificationOtp(
    PDO $db,
    int $customerId,
    string $phoneNumber
): array {

    /*
    |--------------------------------------------------------------------------
    | Validate phone number
    |--------------------------------------------------------------------------
    */

    $normalizedPhone = normalizePhilippinePhone($phoneNumber);

    if ($normalizedPhone === null) {
        return [
            'success' => false,
            'message' => 'Please enter a valid Philippine mobile number.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check resend cooldown
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            otp_id,
            last_sent_at
        FROM phone_verification_otp
        WHERE customer_id = ?
        ORDER BY otp_id DESC
        LIMIT 1
    ");

    $stmt->execute([$customerId]);

    $existingOtp = $stmt->fetch(PDO::FETCH_ASSOC);


    if (
        $existingOtp &&
        !empty($existingOtp['last_sent_at'])
    ) {

        $lastSent = strtotime($existingOtp['last_sent_at']);
        $secondsPassed = time() - $lastSent;

        if ($secondsPassed < PHONE_OTP_RESEND_SECONDS) {

            $remaining =
                PHONE_OTP_RESEND_SECONDS - $secondsPassed;

            return [
                'success' => false,
                'message' =>
                    'Please wait ' .
                    $remaining .
                    ' seconds before requesting another OTP.'
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Generate secure 6-digit OTP
    |--------------------------------------------------------------------------
    */

    $otp = (string) random_int(100000, 999999);

    $otpHash = password_hash(
        $otp,
        PASSWORD_DEFAULT
    );

    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + (PHONE_OTP_EXPIRY_MINUTES * 60)
    );


    /*
    |--------------------------------------------------------------------------
    | Send SMS first
    |--------------------------------------------------------------------------
    */

    $smsMessage =
        'Hong Sing LKRR: Your verification code is ' .
        $otp .
        '. It expires in ' .
        PHONE_OTP_EXPIRY_MINUTES .
        ' minutes. Do not share this code.';


    $smsResult = sendTextBeeSms(
        $normalizedPhone,
        $smsMessage
    );


    if (!$smsResult['success']) {

        return [
            'success' => false,
            'message' =>
                'Unable to send verification code. ' .
                ($smsResult['message'] ?? '')
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Remove previous OTP
    |--------------------------------------------------------------------------
    */

    $deleteStmt = $db->prepare("
        DELETE FROM phone_verification_otp
        WHERE customer_id = ?
    ");

    $deleteStmt->execute([
        $customerId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Save new OTP hash
    |--------------------------------------------------------------------------
    */

    $insertStmt = $db->prepare("
        INSERT INTO phone_verification_otp (
            customer_id,
            phone_number,
            otp_hash,
            expires_at,
            attempt_count,
            last_sent_at
        )
        VALUES (?, ?, ?, ?, 0, NOW())
    ");

    $insertStmt->execute([
        $customerId,
        $normalizedPhone,
        $otpHash,
        $expiresAt
    ]);


    return [
        'success' => true,
        'message' =>
            'A 6-digit verification code was sent to your phone.'
    ];
}

/**
 * Verify the OTP entered by the customer.
 */
function verifyPhoneOtp(
    PDO $db,
    int $customerId,
    string $enteredOtp
): array {

    $enteredOtp = trim($enteredOtp);

    /*
    |--------------------------------------------------------------------------
    | Validate OTP format
    |--------------------------------------------------------------------------
    */

    if (!preg_match('/^\d{6}$/', $enteredOtp)) {
        return [
            'success' => false,
            'message' => 'Please enter a valid 6-digit verification code.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Get latest OTP
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            otp_id,
            phone_number,
            otp_hash,
            expires_at,
            attempt_count
        FROM phone_verification_otp
        WHERE customer_id = ?
        ORDER BY otp_id DESC
        LIMIT 1
    ");

    $stmt->execute([$customerId]);

    $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$otpRecord) {
        return [
            'success' => false,
            'message' => 'No verification code was found. Please request a new OTP.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check attempt limit
    |--------------------------------------------------------------------------
    */

    if (
        (int) $otpRecord['attempt_count'] >=
        PHONE_OTP_MAX_ATTEMPTS
    ) {
        return [
            'success' => false,
            'message' =>
                'Too many incorrect attempts. Please request a new OTP.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check expiration
    |--------------------------------------------------------------------------
    */

    if (
        strtotime($otpRecord['expires_at']) <
        time()
    ) {

        $deleteStmt = $db->prepare("
            DELETE FROM phone_verification_otp
            WHERE otp_id = ?
        ");

        $deleteStmt->execute([
            $otpRecord['otp_id']
        ]);

        return [
            'success' => false,
            'message' =>
                'The verification code has expired. Please request a new OTP.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Verify OTP
    |--------------------------------------------------------------------------
    */

    if (
        !password_verify(
            $enteredOtp,
            $otpRecord['otp_hash']
        )
    ) {

        $updateAttemptStmt = $db->prepare("
            UPDATE phone_verification_otp
            SET attempt_count = attempt_count + 1
            WHERE otp_id = ?
        ");

        $updateAttemptStmt->execute([
            $otpRecord['otp_id']
        ]);

        $newAttemptCount =
            (int) $otpRecord['attempt_count'] + 1;

        $remaining =
            max(
                0,
                PHONE_OTP_MAX_ATTEMPTS - $newAttemptCount
            );

        if ($remaining === 0) {
            return [
                'success' => false,
                'message' =>
                    'Too many incorrect attempts. Please request a new OTP.'
            ];
        }

        return [
            'success' => false,
            'message' =>
                'Incorrect verification code. ' .
                $remaining .
                ' attempt(s) remaining.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Make sure OTP belongs to customer's current phone
    |--------------------------------------------------------------------------
    */

    $customerStmt = $db->prepare("
        SELECT phone_number
        FROM customer
        WHERE customer_id = ?
        LIMIT 1
    ");

    $customerStmt->execute([
        $customerId
    ]);

    $customer =
        $customerStmt->fetch(PDO::FETCH_ASSOC);


    if (!$customer) {
        return [
            'success' => false,
            'message' => 'Customer account could not be found.'
        ];
    }


    $currentPhone =
        normalizePhilippinePhone(
            $customer['phone_number']
        );


    if (
        $currentPhone === null ||
        $currentPhone !== $otpRecord['phone_number']
    ) {
        return [
            'success' => false,
            'message' =>
                'Your phone number has changed. Please request a new verification code.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Mark phone as verified
    |--------------------------------------------------------------------------
    */

    try {

        $db->beginTransaction();


        $verifyStmt = $db->prepare("
            UPDATE customer
            SET
                phone_verified = 1,
                phone_verified_at = NOW()
            WHERE customer_id = ?
        ");

        $verifyStmt->execute([
            $customerId
        ]);


        /*
         * OTP is no longer needed after successful verification.
         */

        $deleteStmt = $db->prepare("
            DELETE FROM phone_verification_otp
            WHERE customer_id = ?
        ");

        $deleteStmt->execute([
            $customerId
        ]);


        $db->commit();


        return [
            'success' => true,
            'message' =>
                'Your phone number has been verified successfully.'
        ];


    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return [
            'success' => false,
            'message' =>
                'Unable to verify your phone number. Please try again.'
        ];
    }
}