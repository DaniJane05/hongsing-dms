<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Send an email using Gmail SMTP through PHPMailer.
 */
function sendSystemEmail(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $htmlMessage,
    string $plainMessage = ''
): array {

    $recipientEmail = trim($recipientEmail);
    $recipientName  = trim($recipientName);

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid recipient email address.'
        ];
    }

    if (
        !defined('MAIL_HOST') ||
        !defined('MAIL_PORT') ||
        !defined('MAIL_USERNAME') ||
        !defined('MAIL_PASSWORD') ||
        !defined('MAIL_FROM_EMAIL') ||
        !defined('MAIL_FROM_NAME')
    ) {
        return [
            'success' => false,
            'message' => 'Email configuration is incomplete.'
        ];
    }

    if (
        MAIL_USERNAME === '' ||
        MAIL_PASSWORD === '' ||
        MAIL_USERNAME === 'YOUR_GMAIL_ADDRESS@gmail.com' ||
        MAIL_PASSWORD === 'YOUR_16_CHARACTER_APP_PASSWORD'
    ) {
        return [
            'success' => false,
            'message' => 'Gmail SMTP credentials have not been configured.'
        ];
    }

    $mail = new PHPMailer(true);

    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP Configuration
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host = MAIL_HOST;

        $mail->SMTPAuth = true;

        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = MAIL_PORT;

        $mail->CharSet = 'UTF-8';


        /*
        |--------------------------------------------------------------------------
        | Sender / Recipient
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            MAIL_FROM_EMAIL,
            MAIL_FROM_NAME
        );

        $mail->addAddress(
            $recipientEmail,
            $recipientName
        );


        /*
        |--------------------------------------------------------------------------
        | Email Content
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        $mail->Subject = $subject;
        $mail->Body    = $htmlMessage;

        if ($plainMessage !== '') {
            $mail->AltBody = $plainMessage;
        } else {
            $mail->AltBody =
                trim(
                    html_entity_decode(
                        strip_tags($htmlMessage),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    )
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Send
        |--------------------------------------------------------------------------
        */

        $mail->send();

        return [
            'success' => true,
            'message' => 'Email sent successfully.'
        ];

    } catch (Exception $e) {

        return [
            'success' => false,
            'message' =>
                'Unable to send email: ' .
                $mail->ErrorInfo
        ];
    }
}