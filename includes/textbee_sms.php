<?php

/**
 * Hong Sing LKRR Commercial
 * TextBee SMS Helper
 */


/**
 * Convert a Philippine mobile number to E.164 format.
 *
 * Examples:
 * 09171234567  -> +639171234567
 * 639171234567 -> +639171234567
 * +639171234567 remains unchanged
 */
function normalizePhilippinePhone(string $phone): ?string
{
    $phone = trim($phone);

    // Remove spaces, hyphens, parentheses, etc.
    $phone = preg_replace('/[^\d+]/', '', $phone);

    if ($phone === null || $phone === '') {
        return null;
    }

    // 09XXXXXXXXX
    if (preg_match('/^09\d{9}$/', $phone)) {
        return '+63' . substr($phone, 1);
    }

    // 639XXXXXXXXX
    if (preg_match('/^639\d{9}$/', $phone)) {
        return '+' . $phone;
    }

    // +639XXXXXXXXX
    if (preg_match('/^\+639\d{9}$/', $phone)) {
        return $phone;
    }

    return null;
}


/**
 * Send SMS using TextBee.
 *
 * @return array{
 *     success: bool,
 *     message: string,
 *     http_code?: int
 * }
 */
function sendTextBeeSms(
    string $phoneNumber,
    string $message
): array {

    /*
    |--------------------------------------------------------------------------
    | Check configuration
    |--------------------------------------------------------------------------
    */

    if (
        !defined('TEXTBEE_API_URL') ||
        !defined('TEXTBEE_API_KEY')
    ) {
        return [
            'success' => false,
            'message' => 'TextBee configuration is missing.'
        ];
    }

    if (
        TEXTBEE_API_KEY === '' ||
        TEXTBEE_API_KEY === 'YOUR_TEXTBEE_API_KEY'
    ) {
        return [
            'success' => false,
            'message' => 'TextBee API key has not been configured.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize phone number
    |--------------------------------------------------------------------------
    */

    $normalizedPhone =
        normalizePhilippinePhone($phoneNumber);

    if ($normalizedPhone === null) {
        return [
            'success' => false,
            'message' => 'Invalid Philippine mobile number.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Validate message
    |--------------------------------------------------------------------------
    */

    $message = trim($message);

    if ($message === '') {
        return [
            'success' => false,
            'message' => 'SMS message cannot be empty.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Prepare request
    |--------------------------------------------------------------------------
    */

    $payload = [
        'recipients' => [
            $normalizedPhone
        ],
        'message' => $message
    ];

    $jsonPayload =
        json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($jsonPayload === false) {
        return [
            'success' => false,
            'message' => 'Unable to prepare SMS request.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Send request
    |--------------------------------------------------------------------------
    */

    $ch = curl_init(TEXTBEE_API_URL);

    curl_setopt_array($ch, [

        CURLOPT_POST => true,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . TEXTBEE_API_KEY
        ],

        CURLOPT_POSTFIELDS => $jsonPayload,

        CURLOPT_CONNECTTIMEOUT => 5,

        CURLOPT_TIMEOUT => 15

    ]);


    $response = curl_exec($ch);

    $curlError = curl_error($ch);

    $httpCode =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | Connection error
    |--------------------------------------------------------------------------
    */

    if ($response === false) {
        return [
            'success' => false,
            'message' =>
                $curlError !== ''
                    ? 'TextBee connection error: ' . $curlError
                    : 'Unable to connect to TextBee.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Decode TextBee response
    |--------------------------------------------------------------------------
    */

    $decoded =
        json_decode(
            $response,
            true
        );


    /*
    |--------------------------------------------------------------------------
    | Successful HTTP response
    |--------------------------------------------------------------------------
    */

    if (
        $httpCode >= 200 &&
        $httpCode < 300
    ) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully.',
            'http_code' => $httpCode
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | TextBee returned an error
    |--------------------------------------------------------------------------
    */

    $apiMessage = null;

    if (is_array($decoded)) {

        $apiMessage =
            $decoded['message'] ??
            $decoded['error'] ??
            null;
    }

    return [
        'success' => false,
        'message' =>
            $apiMessage
                ? 'TextBee error: ' . $apiMessage
                : 'TextBee returned HTTP ' . $httpCode . '.',
        'http_code' => $httpCode
    ];
}