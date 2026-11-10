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
        header(
            'Location: ' .
            ROLE_DASHBOARDS[$role]
        );

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
   VARIABLES
========================================================== */

$errors = [];

$old = [
    'full_name'    => '',
    'email'        => '',
    'phone_number' => '',
    'address'      => '',
    'city'         => '',
    'province'     => '',
];


/* ==========================================================
   REGISTRATION SUBMISSION
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
        $errors[] =
            'Your session expired or the request was invalid. Please refresh the page and try again.';
    }


    /* ------------------------------------------------------
       INPUT
    ------------------------------------------------------ */

    $old['full_name'] =
        cleanInput(
            (string) ($_POST['full_name'] ?? '')
        );

    $old['email'] =
        strtolower(
            cleanInput(
                (string) ($_POST['email'] ?? '')
            )
        );

    $old['phone_number'] =
        cleanInput(
            (string) ($_POST['phone_number'] ?? '')
        );

    $old['address'] =
        cleanInput(
            (string) ($_POST['address'] ?? '')
        );

    $old['city'] =
        cleanInput(
            (string) ($_POST['city'] ?? '')
        );

    $old['province'] =
        cleanInput(
            (string) ($_POST['province'] ?? '')
        );

    $password =
        (string) ($_POST['password'] ?? '');

    $confirmPassword =
        (string) ($_POST['confirm_password'] ?? '');


    /* ======================================================
       VALIDATION
    ====================================================== */

    if ($old['full_name'] === '') {
        $errors[] =
            'Full name is required.';
    }

    if (
        strlen($old['full_name']) > 150
    ) {
        $errors[] =
            'Full name is too long.';
    }


    if (
        !filter_var(
            $old['email'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errors[] =
            'Please enter a valid email address.';
    }


    if (
        strlen($old['email']) > 254
    ) {
        $errors[] =
            'Email address is too long.';
    }


    if ($old['phone_number'] === '') {

        $errors[] =
            'Phone number is required.';

    } elseif (
        !preg_match(
            '/^(09|\+639)\d{9}$/',
            preg_replace(
                '/[\s\-]/',
                '',
                $old['phone_number']
            )
        )
    ) {

        $errors[] =
            'Please enter a valid Philippine mobile number.';
    }


    if (
        $old['address'] === '' ||
        $old['city'] === '' ||
        $old['province'] === ''
    ) {
        $errors[] =
            'Complete address, city, and province are required.';
    }


    if (strlen($password) < 8) {
        $errors[] =
            'Password must be at least 8 characters.';
    }


    if (strlen($password) > 255) {
        $errors[] =
            'Password is too long.';
    }


    if (
        !preg_match(
            '/[A-Za-z]/',
            $password
        ) ||
        !preg_match(
            '/\d/',
            $password
        )
    ) {
        $errors[] =
            'Password must contain at least one letter and one number.';
    }


    if (
        $password !==
        $confirmPassword
    ) {
        $errors[] =
            'Passwords do not match.';
    }


    /* ======================================================
       CHECK DUPLICATE EMAIL
    ====================================================== */

    if (empty($errors)) {

        try {

            $db =
                Database::getConnection();

            /*
             * Check all account types.
             */
            $checkStmt = $db->prepare("
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

            $checkStmt->execute([
                'email1' => $old['email'],
                'email2' => $old['email'],
                'email3' => $old['email'],
            ]);

            if ($checkStmt->fetch()) {

                $errors[] =
                    'An account with this email already exists.';
            }

        } catch (Throwable $e) {

            error_log(
                'Registration email check error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to process your registration at the moment. Please try again.';
        }
    }


    /* ======================================================
       CREATE PENDING REGISTRATION + SEND OTP
    ====================================================== */

    if (empty($errors)) {

        try {

            /*
             * Generate cryptographically secure
             * six-digit OTP.
             */
            $otp =
                (string) random_int(
                    100000,
                    999999
                );


            /*
             * Store registration temporarily.
             *
             * IMPORTANT:
             * Customer does NOT exist in the database yet.
             */
            $_SESSION['pending_registration'] = [

                'full_name' =>
                    $old['full_name'],

                'email' =>
                    $old['email'],

                'phone_number' =>
                    $old['phone_number'],

                'address' =>
                    $old['address'],

                'city' =>
                    $old['city'],

                'province' =>
                    $old['province'],

                /*
                 * Never store raw password.
                 */
                'password_hash' =>
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    ),

                /*
                 * Never store raw OTP.
                 */
                'otp_hash' =>
                    password_hash(
                        $otp,
                        PASSWORD_DEFAULT
                    ),

                'otp_expires_at' =>
                    time() +
                    (
                        EMAIL_OTP_EXPIRY_MINUTES
                        * 60
                    ),

                'otp_attempts' =>
                    0,

                'last_otp_sent_at' =>
                    time(),

                'created_at' =>
                    time(),
            ];


            /* ==================================================
               EMAIL CONTENT
            ================================================== */

            $safeName =
                htmlspecialchars(
                    $old['full_name'],
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
                'Verify Your Email - Hong Sing LKRR Commercial';


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
                        Thank you for registering with
                        Hong Sing LKRR Commercial.
                    </p>

                    <p>
                        Use the verification code below
                        to complete your registration:
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
                        This code will expire in
                        <strong>' .
                        EMAIL_OTP_EXPIRY_MINUTES .
                        ' minutes</strong>.
                    </p>

                    <p>
                        If you did not request this registration,
                        you may ignore this email.
                    </p>

                    <p>
                        Hong Sing LKRR Commercial
                    </p>

                </div>
            ';


            $plainMessage =
                "Hello " .
                $old['full_name'] .
                ",\n\n" .

                "Your Hong Sing LKRR Commercial email verification code is: " .
                $otp .
                "\n\n" .

                "This code expires in " .
                EMAIL_OTP_EXPIRY_MINUTES .
                " minutes.\n\n" .

                "If you did not request this registration, you may ignore this email.";


            /* ==================================================
               SEND EMAIL
            ================================================== */

            $emailResult =
                sendSystemEmail(
                    $old['email'],
                    $old['full_name'],
                    $subject,
                    $htmlMessage,
                    $plainMessage
                );


            /*
             * Only proceed to verification if
             * the email was actually sent.
             */
            if (
    !($emailResult['success'] ?? false)
) {

    unset(
        $_SESSION['pending_registration']
    );

    $actualEmailError =
        $emailResult['message']
        ?? 'Unknown email error';

    error_log(
        'Registration OTP email failed: ' .
        $actualEmailError
    );

    /*
     * DEVELOPMENT ONLY:
     * Show the real SMTP error while testing locally.
     */
    $errors[] =
        'Email sending failed: ' .
        $actualEmailError;
} else {

                header(
                    'Location: ' .
                    BASE_URL .
                    'auth/verify_email.php'
                );

                exit;
            }

        } catch (Throwable $e) {

            unset(
                $_SESSION['pending_registration']
            );

            error_log(
                'Registration OTP error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to start email verification. Please try again.';
        }
    }
}

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
    Sign Up · Hong Sing LKRR Commercial
</title>

<link
    rel="stylesheet"
    href="<?= e(BASE_URL) ?>assets/css/theme.css"
>

<style>

.signup-wrapper {

    position: relative;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: flex-end;

    padding: 60px 8vw;

    overflow: hidden;
}


.signup-bg {

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


.signup-bg::after {

    content: '';

    position: absolute;

    inset: 0;

    background:
        var(--overlay-gradient);
}


.signup-card {

    position: relative;

    z-index: 1;

    width: 100%;

    max-width: 480px;

    padding: 40px 36px;

    max-height: 90vh;

    overflow-y: auto;
}


.signup-card h1 {

    font-size: 1.9rem;

    margin: 18px 0 8px;
}


.signup-card p.subtitle {

    color:
        var(--color-text-secondary);

    font-size: 0.9rem;

    line-height: 1.5;

    margin-bottom: 24px;
}


.field-group {

    margin-bottom: 16px;
}


.field-row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 14px;
}


.login-line {

    text-align: center;

    font-size: 0.88rem;

    color:
        var(--color-text-secondary);

    margin-top: 20px;
}


.alert-error ul {

    margin: 0;

    padding-left: 18px;
}


.verification-note {

    padding: 12px 14px;

    margin-bottom: 18px;

    border-radius: 8px;

    background: #f5f7fa;

    font-size: 0.82rem;

    line-height: 1.5;

    color: #555;
}


@media (max-width: 768px) {

    .signup-wrapper {

        justify-content: center;

        padding: 24px 16px;
    }


    .signup-card {

        padding: 30px 22px;
    }


    .field-row {

        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<div class="signup-wrapper">


    <div class="signup-bg"></div>


    <div class="signup-card card">


        <img
            src="<?= e(BASE_URL) ?>assets/images/logo.jpg"
            alt="Hong Sing LKRR Commercial"
            class="brand-logo"
        >


        <h1>
            Create your account
        </h1>


        <p class="subtitle">
            Sign up to place orders, track deliveries,
            and manage your account.
        </p>


        <div class="verification-note">

            Your email address must be verified before
            your customer account can be created.

        </div>


        <?php if (!empty($errors)): ?>

            <div class="alert-error">

                <ul>

                    <?php foreach ($errors as $err): ?>

                        <li>
                            <?= e($err) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            autocomplete="on"
        >


            <?= csrfField() ?>


            <!-- FULL NAME -->

            <div class="field-group">

                <label
                    class="form-label"
                    for="full_name"
                >
                    Full Name
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    class="form-input"
                    placeholder="Juan Dela Cruz"
                    value="<?= e($old['full_name']) ?>"
                    maxlength="150"
                    autocomplete="name"
                    required
                >

            </div>


            <!-- EMAIL -->

            <div class="field-group">

                <label
                    class="form-label"
                    for="email"
                >
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="you@example.com"
                    value="<?= e($old['email']) ?>"
                    maxlength="254"
                    autocomplete="email"
                    required
                >

            </div>


            <!-- PHONE -->

            <div class="field-group">

                <label
                    class="form-label"
                    for="phone_number"
                >
                    Phone Number
                </label>

                <input
                    type="tel"
                    id="phone_number"
                    name="phone_number"
                    class="form-input"
                    placeholder="09XX XXX XXXX"
                    value="<?= e($old['phone_number']) ?>"
                    autocomplete="tel"
                    required
                >

            </div>


            <!-- ADDRESS -->

            <div class="field-group">

                <label
                    class="form-label"
                    for="address"
                >
                    Street Address
                </label>

                <input
                    type="text"
                    id="address"
                    name="address"
                    class="form-input"
                    placeholder="123 Sample St., Barangay..."
                    value="<?= e($old['address']) ?>"
                    autocomplete="street-address"
                    required
                >

            </div>


            <!-- CITY / PROVINCE -->

            <div class="field-row field-group">


                <div>

                    <label
                        class="form-label"
                        for="city"
                    >
                        City
                    </label>

                    <input
                        type="text"
                        id="city"
                        name="city"
                        class="form-input"
                        value="<?= e($old['city']) ?>"
                        autocomplete="address-level2"
                        required
                    >

                </div>


                <div>

                    <label
                        class="form-label"
                        for="province"
                    >
                        Province
                    </label>

                    <input
                        type="text"
                        id="province"
                        name="province"
                        class="form-input"
                        value="<?= e($old['province']) ?>"
                        autocomplete="address-level1"
                        required
                    >

                </div>


            </div>


            <!-- PASSWORDS -->

            <div class="field-row field-group">


                <div>

                    <label
                        class="form-label"
                        for="password"
                    >
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="At least 8 characters"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <div>

                    <label
                        class="form-label"
                        for="confirm_password"
                    >
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-input"
                        placeholder="Re-enter password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>


            </div>


            <button
                type="submit"
                class="btn-primary"
            >
                Continue to Email Verification
            </button>


        </form>


        <p class="login-line">

            Already have an account?

            <a
                href="<?= e(BASE_URL) ?>auth/login.php"
            >
                Sign in
            </a>

        </p>


    </div>


</div>


</body>

</html>