<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email_sender.php';


/* ==========================================================
   ALREADY LOGGED IN
========================================================== */

if (isLoggedIn()) {

    $role = currentRole();

    if (
        $role !== null &&
        isset(ROLE_DASHBOARDS[$role])
    ) {
        header('Location: ' . ROLE_DASHBOARDS[$role]);
        exit;
    }

    logoutUser();

    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php'
    );

    exit;
}


/* ==========================================================
   REQUIRE PENDING REGISTRATION
========================================================== */

if (
    empty($_SESSION['pending_registration']) ||
    !is_array($_SESSION['pending_registration'])
) {

    header(
        'Location: ' .
        BASE_URL .
        'auth/register_customer.php'
    );

    exit;
}


$pending = &$_SESSION['pending_registration'];


/* ==========================================================
   VALIDATE SESSION DATA
========================================================== */

$requiredFields = [
    'full_name',
    'email',
    'phone_number',
    'address',
    'city',
    'province',
    'password_hash',
    'otp_hash',
    'otp_expires_at',
    'otp_attempts',
    'last_otp_sent_at'
];


foreach ($requiredFields as $field) {

    if (!array_key_exists($field, $pending)) {

        unset(
            $_SESSION['pending_registration']
        );

        header(
            'Location: ' .
            BASE_URL .
            'auth/register_customer.php'
        );

        exit;
    }
}


/* ==========================================================
   VARIABLES
========================================================== */

$error = '';
$success = '';


/* ==========================================================
   MASK EMAIL
========================================================== */

function maskVerificationEmail(
    string $email
): string {

    $parts = explode('@', $email, 2);

    if (count($parts) !== 2) {
        return $email;
    }

    $local  = $parts[0];
    $domain = $parts[1];

    $length = strlen($local);

    if ($length <= 2) {

        $masked =
            substr($local, 0, 1) .
            '*';

    } else {

        $masked =
            substr($local, 0, 2) .
            str_repeat(
                '*',
                max(3, $length - 2)
            );
    }

    return $masked . '@' . $domain;
}


/* ==========================================================
   CHECK EXISTING EMAIL
========================================================== */

function verificationEmailExists(
    PDO $db,
    string $email
): bool {

    $stmt = $db->prepare("
        SELECT email
        FROM customer
        WHERE LOWER(email) = LOWER(:email1)

        UNION

        SELECT email
        FROM driver
        WHERE LOWER(email) = LOWER(:email2)

        UNION

        SELECT email
        FROM system_user
        WHERE LOWER(email) = LOWER(:email3)

        LIMIT 1
    ");

    $stmt->execute([
        'email1' => $email,
        'email2' => $email,
        'email3' => $email
    ]);

    return (bool) $stmt->fetch();
}


/* ==========================================================
   SEND NEW OTP
========================================================== */

function sendNewEmailOtp(
    array &$pending
): array {

    $otp = (string) random_int(
        100000,
        999999
    );


    /*
     * Store HASH only.
     */
    $pending['otp_hash'] =
        password_hash(
            $otp,
            PASSWORD_DEFAULT
        );


    $pending['otp_expires_at'] =
        time() +
        (
            EMAIL_OTP_EXPIRY_MINUTES
            * 60
        );


    $pending['otp_attempts'] = 0;

    $pending['last_otp_sent_at'] =
        time();


    /* ------------------------------------------------------
       EMAIL CONTENT
    ------------------------------------------------------ */

    $safeName =
        htmlspecialchars(
            (string) $pending['full_name'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );


    $safeOtp =
        htmlspecialchars(
            $otp,
            ENT_QUOTES,
            'UTF-8'
        );


    $subject =
        'New Verification Code - Hong Sing LKRR Commercial';


    $htmlMessage = '
        <div style="
            font-family: Arial, sans-serif;
            max-width: 560px;
            margin: 0 auto;
            padding: 24px;
            color: #222;
        ">

            <h2>
                Email Verification
            </h2>

            <p>
                Hello ' . $safeName . ',
            </p>

            <p>
                Your new verification code is:
            </p>

            <div style="
                margin: 28px 0;
                padding: 18px;
                text-align: center;
                background: #f4f4f4;
                border-radius: 8px;
                font-size: 30px;
                font-weight: bold;
                letter-spacing: 8px;
            ">
                ' . $safeOtp . '
            </div>

            <p>
                This code expires in
                <strong>' .
                EMAIL_OTP_EXPIRY_MINUTES .
                ' minutes</strong>.
            </p>

            <p>
                Your previous verification code
                is no longer valid.
            </p>

            <p>
                Hong Sing LKRR Commercial
            </p>

        </div>
    ';


    $plainMessage =
        "Hello " .
        $pending['full_name'] .
        ",\n\n" .

        "Your new verification code is: " .
        $otp .
        "\n\n" .

        "This code expires in " .
        EMAIL_OTP_EXPIRY_MINUTES .
        " minutes.";


    return sendSystemEmail(
        (string) $pending['email'],
        (string) $pending['full_name'],
        $subject,
        $htmlMessage,
        $plainMessage
    );
}


/* ==========================================================
   HANDLE FORM SUBMISSION
========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ------------------------------------------------------
       CSRF
    ------------------------------------------------------ */

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Your session expired. Please refresh the page and try again.';

    } else {

        $action =
            (string) (
                $_POST['action']
                ?? 'verify'
            );


        /* ==================================================
           VERIFY OTP
        ================================================== */

        if ($action === 'verify') {

            $otp =
                trim(
                    (string) (
                        $_POST['otp']
                        ?? ''
                    )
                );


            /*
             * Must be exactly 6 digits.
             */
            if (
                !preg_match(
                    '/^\d{6}$/',
                    $otp
                )
            ) {

                $error =
                    'Please enter the 6-digit verification code.';


            /*
             * Check attempt limit.
             */
            } elseif (
                (int) $pending['otp_attempts']
                >= EMAIL_OTP_MAX_ATTEMPTS
            ) {

                $error =
                    'Too many incorrect attempts. Please request a new verification code.';


            /*
             * Check expiration.
             */
            } elseif (
                time() >
                (int) $pending['otp_expires_at']
            ) {

                $error =
                    'Your verification code has expired. Please request a new code.';


            /*
             * Verify OTP.
             */
            } elseif (
                !password_verify(
                    $otp,
                    (string) $pending['otp_hash']
                )
            ) {

                $pending['otp_attempts'] =
                    (int) $pending['otp_attempts']
                    + 1;


                $remaining =
                    max(
                        0,
                        EMAIL_OTP_MAX_ATTEMPTS
                        -
                        (int) $pending['otp_attempts']
                    );


                if ($remaining <= 0) {

                    $error =
                        'Too many incorrect attempts. Please request a new verification code.';

                } else {

                    $error =
                        'Incorrect verification code. ' .
                        $remaining .
                        ' attempt' .
                        ($remaining === 1 ? '' : 's') .
                        ' remaining.';
                }


            /*
             * Correct OTP.
             */
            } else {

                try {

                    $db =
                        Database::getConnection();


                    /*
                     * Recheck duplicate email before INSERT.
                     */
                    if (
                        verificationEmailExists(
                            $db,
                            (string) $pending['email']
                        )
                    ) {

                        unset(
                            $_SESSION['pending_registration']
                        );

                        $error =
                            'An account with this email already exists. Please sign in instead.';

                    } else {

                        /* ==================================
                           CREATE CUSTOMER
                        ================================== */

                        $stmt = $db->prepare("
                            INSERT INTO customer
                            (
                                full_name,
                                email,
                                phone_number,
                                address,
                                city,
                                province,
                                password_hash,
                                created_at
                            )
                            VALUES
                            (
                                :full_name,
                                :email,
                                :phone_number,
                                :address,
                                :city,
                                :province,
                                :password_hash,
                                NOW()
                            )
                        ");


                        $stmt->execute([

                            'full_name' =>
                                $pending['full_name'],

                            'email' =>
                                $pending['email'],

                            'phone_number' =>
                                $pending['phone_number'],

                            'address' =>
                                $pending['address'],

                            'city' =>
                                $pending['city'],

                            'province' =>
                                $pending['province'],

                            'password_hash' =>
                                $pending['password_hash']
                        ]);


                        /*
                         * Registration finished.
                         */
                        unset(
                            $_SESSION['pending_registration']
                        );


                        /*
                         * Rotate session ID.
                         */
                        session_regenerate_id(true);


                        header(
                            'Location: ' .
                            BASE_URL .
                            'auth/login.php?registered=1'
                        );

                        exit;
                    }

                } catch (Throwable $e) {

                    error_log(
                        'Customer registration completion error: ' .
                        $e->getMessage()
                    );

                    $error =
                        'Unable to complete your registration. Please try again.';
                }
            }


        /* ==================================================
           RESEND OTP
        ================================================== */

        } elseif ($action === 'resend') {

            $lastSent =
                (int) (
                    $pending['last_otp_sent_at']
                    ?? 0
                );


            $elapsed =
                time() - $lastSent;


            if (
                $elapsed <
                EMAIL_OTP_RESEND_SECONDS
            ) {

                $remaining =
                    EMAIL_OTP_RESEND_SECONDS
                    - $elapsed;


                $error =
                    'Please wait ' .
                    $remaining .
                    ' second' .
                    ($remaining === 1 ? '' : 's') .
                    ' before requesting another code.';

            } else {

                try {

                    $result =
                        sendNewEmailOtp(
                            $pending
                        );


                    if (
                        $result['success']
                        ?? false
                    ) {

                        $success =
                            'A new verification code has been sent to your email.';

                    } else {

                        error_log(
                            'Verification OTP resend failed: ' .
                            (
                                $result['message']
                                ?? 'Unknown error'
                            )
                        );


                        $error =
                            'Unable to send a new verification code. Please try again.';
                    }

                } catch (Throwable $e) {

                    error_log(
                        'Verification resend error: ' .
                        $e->getMessage()
                    );


                    $error =
                        'Unable to send a new verification code. Please try again.';
                }
            }

        } else {

            $error =
                'Invalid verification request.';
        }
    }
}


/* ==========================================================
   DISPLAY VALUES
========================================================== */

$maskedEmail =
    maskVerificationEmail(
        (string) $pending['email']
    );


$secondsUntilExpiry =
    max(
        0,
        (int) $pending['otp_expires_at']
        - time()
    );


$attemptsRemaining =
    max(
        0,
        EMAIL_OTP_MAX_ATTEMPTS
        -
        (int) $pending['otp_attempts']
    );


$resendAvailableIn =
    max(
        0,
        EMAIL_OTP_RESEND_SECONDS
        -
        (
            time()
            -
            (int) $pending['last_otp_sent_at']
        )
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Verify Email · Hong Sing LKRR Commercial
</title>

<link
    rel="stylesheet"
    href="<?= e(BASE_URL) ?>assets/css/theme.css"
>


<style>

/* ==========================================================
   PAGE
========================================================== */

.verify-wrapper {

    position: relative;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 30px 20px;

    overflow: hidden;
}


.verify-bg {

    position: absolute;

    inset: 0;

    background-image:
        url('<?= e(BASE_URL) ?>assets/images/login-truck.png');

    background-size: cover;

    background-position: center;

    filter:
        brightness(0.55)
        saturate(0.9);

    z-index: 0;
}


.verify-bg::after {

    content: '';

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            90deg,
            rgba(0, 0, 0, 0.25),
            rgba(0, 0, 0, 0.55)
        );
}


/* ==========================================================
   CARD
========================================================== */

.verify-card {

    position: relative;

    z-index: 1;

    width: 100%;

    max-width: 440px;

    padding: 38px 34px;

    text-align: center;
}


.verify-card h1 {

    margin:
        18px
        0
        8px;

    font-size: 1.8rem;
}


.verify-subtitle {

    margin:
        0
        0
        24px;

    font-size: 0.9rem;

    line-height: 1.6;

    color: #666;
}


.email-display {

    display: inline-block;

    margin-top: 4px;

    font-weight: 700;

    color: #222;

    word-break: break-word;
}


/* ==========================================================
   OTP
========================================================== */

.otp-group {

    margin:
        20px
        0
        16px;

    text-align: left;
}


.otp-label {

    display: block;

    margin-bottom: 7px;

    font-size: 0.88rem;

    font-weight: 700;

    color: #222;
}


.otp-help {

    margin:
        0
        0
        10px;

    font-size: 0.78rem;

    color: #666;
}


.otp-input {

    display: block !important;

    width: 100% !important;

    height: 58px;

    box-sizing: border-box;

    padding:
        10px
        14px;

    border:
        1px solid
        #cbd5e1 !important;

    border-radius:
        8px !important;

    background:
        #fff !important;

    color:
        #111827 !important;

    font-family:
        inherit;

    font-size:
        1.55rem !important;

    font-weight:
        700;

    letter-spacing:
        0.4rem;

    text-align:
        center;

    opacity:
        1 !important;

    visibility:
        visible !important;

    outline:
        none;
}


.otp-input:focus {

    border-color:
        #333 !important;

    box-shadow:
        0 0 0 3px
        rgba(0, 0, 0, 0.08);
}


.otp-input::placeholder {

    color:
        #aaa;

    opacity:
        1;
}


/* ==========================================================
   INFORMATION
========================================================== */

.verify-info {

    margin:
        16px
        0;

    padding:
        12px
        14px;

    border-radius:
        8px;

    background:
        #f5f7fa;

    font-size:
        0.8rem;

    line-height:
        1.7;

    text-align:
        left;
}


.verify-info-row {

    display: flex;

    justify-content:
        space-between;

    gap:
        15px;
}


.verify-info strong {

    color:
        #222;
}


/* ==========================================================
   BUTTONS
========================================================== */

.verify-button {

    width:
        100%;
}


.resend-form {

    margin-top:
        12px;
}


.resend-button {

    width:
        100%;
}


.back-link {

    display:
        inline-block;

    margin-top:
        20px;

    font-size:
        0.84rem;
}


/* ==========================================================
   ALERTS
========================================================== */

.verify-card .alert-error,
.verify-card .alert-success {

    margin-bottom:
        16px;

    text-align:
        left;
}


/* ==========================================================
   MOBILE
========================================================== */

@media (max-width: 600px) {

    .verify-wrapper {

        padding:
            18px
            14px;
    }


    .verify-card {

        padding:
            28px
            20px;
    }


    .verify-card h1 {

        font-size:
            1.55rem;
    }


    .otp-input {

        height:
            54px;

        font-size:
            1.3rem !important;

        letter-spacing:
            0.3rem;
    }
}

</style>

</head>


<body>


<div class="verify-wrapper">


    <div class="verify-bg"></div>


    <div class="verify-card card">


        <img
            src="<?= e(BASE_URL) ?>assets/images/logo.jpg"
            alt="Hong Sing LKRR Commercial"
            class="brand-logo"
        >


        <h1>
            Verify your email
        </h1>


        <p class="verify-subtitle">

            We sent a 6-digit verification code to

            <br>

            <span class="email-display">
                <?= e($maskedEmail) ?>
            </span>

        </p>


        <?php if ($error !== ''): ?>

            <div class="alert-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <?php if ($success !== ''): ?>

            <div class="alert-success">
                <?= e($success) ?>
            </div>

        <?php endif; ?>


        <!-- ==================================================
             OTP VERIFICATION
        =================================================== -->

        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <?= csrfField() ?>

            <input
                type="hidden"
                name="action"
                value="verify"
            >


            <div class="otp-group">

                <label
                    for="otp"
                    class="otp-label"
                >
                    Verification Code
                </label>


                <p class="otp-help">
                    Enter the 6-digit code from your email.
                </p>


                <input
                    type="text"
                    id="otp"
                    name="otp"
                    class="otp-input"
                    placeholder="000000"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    autofocus
                    <?= (
                        $attemptsRemaining <= 0 ||
                        $secondsUntilExpiry <= 0
                    )
                        ? 'disabled'
                        : ''
                    ?>
                >

            </div>


            <div class="verify-info">

                <div class="verify-info-row">

                    <span>
                        Code validity
                    </span>

                    <strong>
                        <?= EMAIL_OTP_EXPIRY_MINUTES ?>
                        minutes
                    </strong>

                </div>


                <div class="verify-info-row">

                    <span>
                        Attempts remaining
                    </span>

                    <strong>
                        <?= $attemptsRemaining ?>
                    </strong>

                </div>


                <div class="verify-info-row">

                    <span>
                        Status
                    </span>

                    <strong>

                        <?php if (
                            $secondsUntilExpiry <= 0
                        ): ?>

                            Expired

                        <?php else: ?>

                            Active

                        <?php endif; ?>

                    </strong>

                </div>

            </div>


            <button
                type="submit"
                class="btn-primary verify-button"
                <?= (
                    $attemptsRemaining <= 0 ||
                    $secondsUntilExpiry <= 0
                )
                    ? 'disabled'
                    : ''
                ?>
            >
                Verify Email
            </button>

        </form>


        <!-- ==================================================
             RESEND OTP
        =================================================== -->

        <form
            method="POST"
            action=""
            class="resend-form"
        >

            <?= csrfField() ?>

            <input
                type="hidden"
                name="action"
                value="resend"
            >


            <button
                type="submit"
                class="btn-secondary resend-button"
                <?= $resendAvailableIn > 0
                    ? 'disabled'
                    : ''
                ?>
            >

                <?php if (
                    $resendAvailableIn > 0
                ): ?>

                    Resend Code in
                    <?= $resendAvailableIn ?>s

                <?php else: ?>

                    Resend Verification Code

                <?php endif; ?>

            </button>

        </form>


        <a
            href="<?= e(BASE_URL) ?>auth/register_customer.php"
            class="back-link"
        >
            ← Change registration details
        </a>


    </div>


</div>


<script>

/*
 * Allow digits only in OTP field.
 */
const otpInput =
    document.getElementById('otp');


if (otpInput) {

    otpInput.addEventListener(
        'input',
        function () {

            this.value =
                this.value
                    .replace(/\D/g, '')
                    .slice(0, 6);
        }
    );
}

</script>


</body>

</html>