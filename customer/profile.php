<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/phone_otp.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();
$customerId = currentUserId();
$message = '';
$error = '';

/* GET CUSTOMER */
$stmt = $db->prepare("\n    SELECT\n        customer_id, full_name, email, phone_number, address, city, province,\n        password_hash, created_at, phone_verified, phone_verified_at\n    FROM customer\n    WHERE customer_id = ?\n    LIMIT 1\n");
$stmt->execute([$customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die('Customer account not found.');
}

/* UPDATE PERSONAL INFORMATION */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');

    if ($fullName === '' || $email === '' || $phoneNumber === '' || $address === '' || $city === '' || $province === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (mb_strlen($fullName) > 100) {
        $error = 'Full name must not exceed 100 characters.';
    } elseif (mb_strlen($email) > 100) {
        $error = 'Email must not exceed 100 characters.';
    } elseif (mb_strlen($phoneNumber) > 20) {
        $error = 'Phone number must not exceed 20 characters.';
    } elseif (normalizePhilippinePhone($phoneNumber) === null) {
        $error = 'Please enter a valid Philippine mobile number (for example, 09XXXXXXXXX).';
    } elseif (mb_strlen($address) > 255) {
        $error = 'Address must not exceed 255 characters.';
    } elseif (mb_strlen($city) > 100) {
        $error = 'City must not exceed 100 characters.';
    } elseif (mb_strlen($province) > 100) {
        $error = 'Province must not exceed 100 characters.';
    }

    if ($error === '') {
        $emailStmt = $db->prepare("SELECT customer_id FROM customer WHERE email = ? AND customer_id != ? LIMIT 1");
        $emailStmt->execute([$email, $customerId]);
        if ($emailStmt->fetch()) {
            $error = 'That email address is already being used by another customer.';
        }
    }

    if ($error === '') {
        try {
            $oldNormalized = normalizePhilippinePhone((string) $customer['phone_number']);
            $newNormalized = normalizePhilippinePhone($phoneNumber);
            $phoneChanged = ($oldNormalized !== $newNormalized);

            $db->beginTransaction();

            if ($phoneChanged) {
                $updateStmt = $db->prepare("\n                    UPDATE customer\n                    SET full_name = ?, email = ?, phone_number = ?, address = ?, city = ?, province = ?,\n                        phone_verified = 0, phone_verified_at = NULL\n                    WHERE customer_id = ?\n                ");
            } else {
                $updateStmt = $db->prepare("\n                    UPDATE customer\n                    SET full_name = ?, email = ?, phone_number = ?, address = ?, city = ?, province = ?\n                    WHERE customer_id = ?\n                ");
            }

            $updateStmt->execute([$fullName, $email, $phoneNumber, $address, $city, $province, $customerId]);

            if ($phoneChanged) {
                $deleteOtpStmt = $db->prepare("DELETE FROM phone_verification_otp WHERE customer_id = ?");
                $deleteOtpStmt->execute([$customerId]);
            }

            $db->commit();
            $_SESSION['full_name'] = $fullName;
            $message = $phoneChanged
                ? 'Your profile has been updated. Because your phone number changed, please verify the new number.'
                : 'Your profile has been updated successfully.';

            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = ($e instanceof PDOException && $e->getCode() === '23000')
                ? 'That email address is already being used.'
                : 'Unable to update your profile. Please try again.';
        }
    }
}

/* SEND / RESEND PHONE OTP */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['send_phone_otp']) || isset($_POST['resend_phone_otp']))) {
    if ((int) $customer['phone_verified'] === 1) {
        $message = 'Your phone number is already verified.';
    } else {
        $result = sendPhoneVerificationOtp($db, $customerId, (string) $customer['phone_number']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

/* VERIFY PHONE OTP */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_phone_otp'])) {
    $enteredOtp = trim($_POST['phone_otp'] ?? '');
    $result = verifyPhoneOtp($db, $customerId, $enteredOtp);

    if ($result['success']) {
        $message = $result['message'];
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = $result['message'];
    }
}

/* CHANGE PASSWORD */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Please complete all password fields.';
    } elseif (!password_verify($currentPassword, $customer['password_hash'] ?? '')) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation password do not match.';
    }

    if ($error === '') {
        try {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordUpdateStmt = $db->prepare("UPDATE customer SET password_hash = ? WHERE customer_id = ?");
            $passwordUpdateStmt->execute([$newPasswordHash, $customerId]);
            $customer['password_hash'] = $newPasswordHash;
            $message = 'Your password has been changed successfully.';
        } catch (Throwable $e) {
            $error = 'Unable to change your password. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$phoneVerified =
    (int) $customer['phone_verified'] === 1;

$memberSince =
    !empty($customer['created_at'])
        ? date(
            'M d, Y',
            strtotime(
                $customer['created_at']
            )
        )
        : '—';


        $navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL .
            'customer/dashboard.php'
    ],

    [
        'label' => 'Cart',
        'url' =>
            BASE_URL .
            'customer/cart.php'
    ],

    [
        'label' => 'My Orders',
        'url' =>
            BASE_URL .
            'customer/order_history.php'
    ],

    [
        'label' => 'Track Delivery',
        'url' =>
            BASE_URL .
            'customer/track_delivery.php'
    ],

    [
        'label' => 'Feedback',
        'url' =>
            BASE_URL .
            'customer/feedback.php'
    ],

    [
        'label' => 'Notifications',
        'url' =>
            BASE_URL .
            'customer/notifications.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'customer/profile.php'
    ]

];

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
    My Profile · Hong Sing LKRR Commercial
</title>

<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>


<style>

/* ==========================================================
   PROFILE PAGE
   ========================================================== */

.profile-page {
    width: 100%;
    max-width: none;
}


/* ==========================================================
   PAGE HEADER
   ========================================================== */

.profile-header {
    margin-bottom: 28px;
}

.profile-header h1 {
    margin: 0 0 6px;

    font-size: 1.6rem;
    line-height: 1.25;
    font-weight: 700;

    color: var(--color-text-primary);
}

.profile-header p {
    margin: 0;

    color: var(--color-text-secondary);

    font-size: 0.9rem;
    line-height: 1.5;
}


/* ==========================================================
   ALERTS
   ========================================================== */

.profile-alert {
    display: flex;
    align-items: center;

    width: 100%;

    padding: 13px 16px;

    margin-bottom: 20px;

    border-radius: 10px;

    font-size: 0.88rem;
    line-height: 1.4;
}

.profile-alert.success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
}

.profile-alert.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
}


/* ==========================================================
   MAIN PROFILE LAYOUT
   ========================================================== */

.profile-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.8fr)
        minmax(340px, 0.9fr);

    gap: 22px;

    width: 100%;

    align-items: stretch;

    margin-bottom: 22px;
}


/* ==========================================================
   PROFILE CARD
   ========================================================== */

.profile-card {
    width: 100%;

    background: var(--color-surface);

    border: 1px solid var(--color-border);

    border-radius: var(--radius-lg);

    padding: 26px;

    box-shadow:
        0 8px 28px rgba(30, 58, 110, 0.06);

    animation: fadeUp 0.25s ease;
}

.profile-card h2 {
    margin: 0 0 6px;

    font-size: 1.15rem;
    line-height: 1.3;

    font-weight: 700;

    color: var(--color-text-primary);
}

.profile-card-description {
    margin: 0 0 24px;

    color: var(--color-text-secondary);

    font-size: 0.84rem;
    line-height: 1.5;
}


/* ==========================================================
   PERSONAL INFORMATION FORM
   ========================================================== */

.form-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;

    min-width: 0;

    gap: 7px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;

    margin: 0;

    color: var(--color-text-primary);

    font-size: 0.82rem;
    font-weight: 600;
}

.form-group input,
.form-group textarea {
    width: 100%;

    box-sizing: border-box;

    padding: 12px 13px;

    border: 1px solid var(--color-border);

    border-radius: 8px;

    background: #ffffff;

    color: var(--color-text-primary);

    font-family: var(--font-base);

    font-size: 0.88rem;

    outline: none;

    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
}

.form-group input {
    min-height: 44px;
}

.form-group textarea {
    min-height: 92px;

    resize: vertical;

    line-height: 1.5;
}

.form-group input:hover,
.form-group textarea:hover {
    border-color: #b8c8e3;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: var(--color-accent);

    background: #ffffff;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, 0.10);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: var(--color-text-muted);
}

.form-hint {
    color: var(--color-text-muted);

    font-size: 0.72rem;

    line-height: 1.4;
}


/* ==========================================================
   PHONE VERIFICATION
   ========================================================== */

.verification-container {
    display: flex;

    align-items: center;

    min-height: 44px;
}

.verification-badge {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-height: 30px;

    padding: 5px 11px;

    border-radius: 999px;

    font-size: 0.72rem;

    font-weight: 700;

    white-space: nowrap;
}

.verification-badge.verified {
    background: rgba(22, 163, 74, 0.12);

    color: #15803d;
}

.verification-badge.unverified {
    background: rgba(217, 119, 6, 0.12);

    color: #a16207;
}

.otp-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.otp-actions form {
    margin: 0;
}

.otp-button {
    min-height: 38px;
    padding: 0 14px;
    border: none;
    border-radius: 8px;
    background: var(--color-accent);
    color: #fff;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
}

.otp-button.secondary {
    background: var(--color-surface-alt);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border);
}

.otp-verify-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
}

.otp-input {
    width: 160px !important;
    min-height: 40px !important;
    text-align: center;
    letter-spacing: 0.18em;
    font-weight: 700;
}


/* ==========================================================
   PROFILE BUTTON
   ========================================================== */

.profile-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-height: 44px;

    margin-top: 22px;

    padding: 0 22px;

    border: none;

    border-radius: 8px;

    background: var(--color-accent);

    color: #ffffff;

    font-family: var(--font-base);

    font-size: 0.86rem;

    font-weight: 700;

    cursor: pointer;

    transition:
        background 0.15s ease,
        transform 0.1s ease,
        box-shadow 0.15s ease;
}

.profile-button:hover {
    background: var(--color-accent-hover);

    box-shadow:
        0 6px 16px rgba(37, 99, 235, 0.20);
}

.profile-button:active {
    transform: translateY(1px);
}


/* ==========================================================
   RIGHT COLUMN
   ========================================================== */

.profile-side {
    display: flex;

    flex-direction: column;

    gap: 22px;

    min-width: 0;
}

.profile-side .profile-card {
    height: auto;
}


/* ==========================================================
   ACCOUNT INFORMATION
   ========================================================== */

.account-info {
    display: flex;

    flex-direction: column;

    gap: 0;
}

.account-row {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 20px;

    min-height: 52px;

    padding: 13px 0;

    border-bottom: 1px solid var(--color-border);
}

.account-row:first-child {
    padding-top: 4px;
}

.account-row:last-child {
    border-bottom: none;

    padding-bottom: 4px;
}

.account-label {
    color: var(--color-text-secondary);

    font-size: 0.8rem;

    flex-shrink: 0;
}

.account-value {
    color: var(--color-text-primary);

    font-size: 0.82rem;

    font-weight: 600;

    text-align: right;

    overflow-wrap: anywhere;
}


/* ==========================================================
   DELIVERY SUMMARY
   ========================================================== */

.delivery-summary {
    margin-top: 22px;

    padding-top: 20px;

    border-top: 1px solid var(--color-border);
}

.delivery-summary-title {
    margin-bottom: 12px;

    color: var(--color-text-primary);

    font-size: 0.82rem;

    font-weight: 700;
}

.delivery-summary-box {
    padding: 14px;

    background: var(--color-surface-alt);

    border: 1px solid var(--color-border);

    border-radius: 9px;
}

.delivery-summary-address {
    margin: 0 0 6px;

    color: var(--color-text-primary);

    font-size: 0.82rem;

    line-height: 1.5;

    overflow-wrap: anywhere;
}

.delivery-summary-location {
    margin: 0;

    color: var(--color-text-secondary);

    font-size: 0.75rem;
}


/* ==========================================================
   CHANGE PASSWORD
   ========================================================== */

.password-card {
    width: 100%;

    margin-bottom: 22px;
}

.password-grid {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 18px;

    width: 100%;
}

.password-card .profile-button {
    margin-top: 22px;
}


/* ==========================================================
   PASSWORD SECTION
   ========================================================== */

.password-card .profile-card-description {
    margin-bottom: 22px;
}


/* ==========================================================
   ANIMATION
   ========================================================== */

@keyframes fadeUp {

    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }

}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 1100px) {

    .profile-grid {
        grid-template-columns:
            minmax(0, 1.5fr)
            minmax(300px, 0.9fr);
    }

    .password-grid {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

}


@media (max-width: 900px) {

    .profile-grid {
        grid-template-columns: 1fr;
    }

    .profile-side {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 700px) {

    .profile-side {
        display: flex;

        flex-direction: column;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .password-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 500px) {

    .app-main {
        padding: 20px;
    }

    .profile-card {
        padding: 20px;
    }

    .profile-header h1 {
        font-size: 1.4rem;
    }

    .account-row {
        align-items: flex-start;

        flex-direction: column;

        gap: 5px;
    }

    .account-value {
        text-align: left;
    }

    .profile-button {
        width: 100%;
    }

}

</style>

</head>


<body>

<?php
require __DIR__ .
    '/../includes/header.php';
?>


<main class="app-main">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="profile-header">

    <h1>
        My Profile
    </h1>

    <p>
        Manage your personal information and account security.
    </p>

</div>


<!-- ==========================================================
     ALERTS
========================================================== -->

<?php if ($message): ?>

    <div class="profile-alert success">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="profile-alert error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<!-- ==========================================================
     PROFILE CONTENT
========================================================== -->

<div class="profile-grid">

    <!-- ======================================================
         PERSONAL INFORMATION
    ======================================================= -->

    <section class="profile-card">

        <h2>
            Personal Information
        </h2>

        <p class="profile-card-description">
            Update your personal, contact, and delivery information.
        </p>

        <form method="POST">

            <div class="form-grid">

                <!-- FULL NAME -->

                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars(
                            $customer['full_name']
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $customer['email']
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label for="phone_number">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone_number"
                        name="phone_number"
                        value="<?= htmlspecialchars(
                            $customer['phone_number']
                        ) ?>"
                        maxlength="20"
                        required
                    >

                    <span class="form-hint">
                        Use an active phone number.
                    </span>

                </div>


                <!-- PHONE VERIFICATION -->

                <div class="form-group">

                    <label>
                        Phone Verification
                    </label>

                    <div class="verification-container">

                        <?php if ($phoneVerified): ?>

                            <span class="verification-badge verified">
                                ✓ Verified
                            </span>

                        <?php else: ?>

                            <span class="verification-badge unverified">
                                Not Verified
                            </span>

                        <?php endif; ?>

                    </div>

                    <?php if (!$phoneVerified): ?>

                        <span class="form-hint">
                            Verify this number to receive delivery and account SMS notifications.
                        </span>

                        <div class="otp-actions">
                            <form method="POST">
                                <button type="submit" name="send_phone_otp" class="otp-button">
                                    Send Verification Code
                                </button>
                            </form>

                            <form method="POST">
                                <button type="submit" name="resend_phone_otp" class="otp-button secondary">
                                    Resend Code
                                </button>
                            </form>
                        </div>

                        <form method="POST" class="otp-verify-form">
                            <input
                                type="text"
                                name="phone_otp"
                                class="otp-input"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                placeholder="000000"
                                autocomplete="one-time-code"
                                required
                            >
                            <button type="submit" name="verify_phone_otp" class="otp-button">
                                Verify Phone
                            </button>
                        </form>

                    <?php endif; ?>

                </div>


                <!-- DELIVERY ADDRESS -->

                <div class="form-group full">

                    <label for="address">
                        Delivery Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        maxlength="255"
                        rows="3"
                        required
                    ><?= htmlspecialchars(
                        $customer['address']
                    ) ?></textarea>

                    <span class="form-hint">
                        Enter the complete address where your orders should be delivered.
                    </span>

                </div>


                <!-- CITY -->

                <div class="form-group">

                    <label for="city">
                        City
                    </label>

                    <input
                        type="text"
                        id="city"
                        name="city"
                        value="<?= htmlspecialchars(
                            $customer['city']
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- PROVINCE -->

                <div class="form-group">

                    <label for="province">
                        Province
                    </label>

                    <input
                        type="text"
                        id="province"
                        name="province"
                        value="<?= htmlspecialchars(
                            $customer['province']
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                name="update_profile"
                class="profile-button"
            >
                Save Changes
            </button>

        </form>

    </section>


    <!-- ======================================================
         RIGHT COLUMN
    ======================================================= -->

    <div class="profile-side">

        <!-- ==================================================
             ACCOUNT INFORMATION
        =================================================== -->

        <section class="profile-card">

            <h2>
                Account Information
            </h2>

            <p class="profile-card-description">
                Basic information about your account.
            </p>


            <div class="account-info">

                <div class="account-row">

                    <span class="account-label">
                        Customer ID
                    </span>

                    <span class="account-value">
                        #<?= (int) $customer['customer_id'] ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Account Type
                    </span>

                    <span class="account-value">
                        Customer
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Member Since
                    </span>

                    <span class="account-value">
                        <?= htmlspecialchars(
                            $memberSince
                        ) ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Phone Status
                    </span>

                    <span class="account-value">

                        <?php if ($phoneVerified): ?>

                            <span class="verification-badge verified">
                                ✓ Verified
                            </span>

                        <?php else: ?>

                            <span class="verification-badge unverified">
                                Not Verified
                            </span>

                        <?php endif; ?>

                    </span>

                </div>


                <?php if (
                    $phoneVerified &&
                    !empty(
                        $customer['phone_verified_at']
                    )
                ): ?>

                    <div class="account-row">

                        <span class="account-label">
                            Verified On
                        </span>

                        <span class="account-value">

                            <?= htmlspecialchars(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $customer[
                                            'phone_verified_at'
                                        ]
                                    )
                                )
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>

            </div>


            <!-- DELIVERY SUMMARY -->

            <div class="delivery-summary">

                <div class="delivery-summary-title">
                    Current Delivery Address
                </div>

                <div class="delivery-summary-box">

                    <p class="delivery-summary-address">
                        <?= htmlspecialchars(
                            $customer['address']
                        ) ?>
                    </p>

                    <p class="delivery-summary-location">

                        <?= htmlspecialchars(
                            $customer['city']
                        ) ?>,

                        <?= htmlspecialchars(
                            $customer['province']
                        ) ?>

                    </p>

                </div>

            </div>

        </section>

    </div>

</div>


<!-- ==========================================================
     CHANGE PASSWORD
========================================================== -->

<section class="profile-card password-card">

    <h2>
        Change Password
    </h2>

    <p class="profile-card-description">
        Change your password to keep your account secure.
    </p>

    <form method="POST">

        <div class="password-grid">

            <div class="form-group">

                <label for="current_password">
                    Current Password
                </label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    required
                >

            </div>


            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    minlength="8"
                    required
                >

                <span class="form-hint">
                    Password must contain at least 8 characters.
                </span>

            </div>


            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    required
                >

            </div>

        </div>


        <button
            type="submit"
            name="change_password"
            class="profile-button"
        >
            Change Password
        </button>

    </form>

</section>


</main>

</div>

</body>

</html>