<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_log.php';

requireRole(ROLE_DRIVER);

$db = Database::getConnection();

$driverId = (int) currentUserId();

if ($driverId <= 0) {
    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php?error=session_expired'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$success = '';
$errors = [];


/*
|--------------------------------------------------------------------------
| GET CURRENT DRIVER
|--------------------------------------------------------------------------
*/

$driverStmt = $db->prepare("
    SELECT
        d.driver_id,
        d.license_number,
        d.full_name,
        d.phone_number,
        d.email,
        d.password_hash,
        d.status,
        d.current_truck_id,
        d.total_deliveries,
        d.rating,
        d.created_at,
        d.last_location,
        d.current_latitude,
        d.current_longitude,
        d.location_updated_at,

        t.plate_number,
        t.model AS truck_model,
        t.capacity AS truck_capacity,
        t.status AS truck_status

    FROM driver d

    LEFT JOIN truck t
        ON t.truck_id = d.current_truck_id

    WHERE d.driver_id = ?

    LIMIT 1
");

$driverStmt->execute([
    $driverId
]);

$driver = $driverStmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php?error=driver_not_found'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| UPDATE CONTACT INFORMATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_contact'])
) {

    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    }

    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    /*
    |--------------------------------------------------------------------------
    | PHONE VALIDATION
    |--------------------------------------------------------------------------
    |
    | Allows Philippine-style mobile numbers:
    |
    | 09XXXXXXXXX
    | +639XXXXXXXXX
    |
    */

    if ($phone !== '') {

        $normalizedPhone = preg_replace(
            '/[\s\-\(\)]/',
            '',
            $phone
        );

        if (
            !preg_match(
                '/^(09\d{9}|\+639\d{9})$/',
                $normalizedPhone
            )
        ) {
            $errors[] =
                'Please enter a valid Philippine mobile number.';
        } else {
            $phone = $normalizedPhone;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK EMAIL DUPLICATE
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        /*
        |--------------------------------------------------------------------------
        | DRIVER EMAIL
        |--------------------------------------------------------------------------
        */

        $checkDriver = $db->prepare("
            SELECT COUNT(*)
            FROM driver
            WHERE email = ?
              AND driver_id <> ?
        ");

        $checkDriver->execute([
            $email,
            $driverId
        ]);

        if ((int) $checkDriver->fetchColumn() > 0) {
            $errors[] =
                'This email address is already being used by another driver.';
        }


        /*
        |--------------------------------------------------------------------------
        | SYSTEM USER EMAIL
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $checkAdmin = $db->prepare("
                SELECT COUNT(*)
                FROM system_user
                WHERE email = ?
            ");

            $checkAdmin->execute([
                $email
            ]);

            if ((int) $checkAdmin->fetchColumn() > 0) {
                $errors[] =
                    'This email address is already being used by another system account.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CUSTOMER EMAIL
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $checkCustomer = $db->prepare("
                SELECT COUNT(*)
                FROM customer
                WHERE email = ?
            ");

            $checkCustomer->execute([
                $email
            ]);

            if ((int) $checkCustomer->fetchColumn() > 0) {
                $errors[] =
                    'This email address is already being used by a customer account.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE CONTACT INFORMATION
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $stmt = $db->prepare("
                UPDATE driver

                SET
                    phone_number = ?,
                    email = ?

                WHERE driver_id = ?
            ");

            $stmt->execute([
                $phone,
                $email,
                $driverId
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            logAudit(
                $driverId,
                'UPDATE',
                'driver',
                $driverId,
                'Driver updated contact information.'
            );


            $success =
                'Your contact information has been updated successfully.';


            /*
            |--------------------------------------------------------------------------
            | REFRESH DRIVER DATA
            |--------------------------------------------------------------------------
            */

            $driver['phone_number'] = $phone;
            $driver['email'] = $email;

        } catch (PDOException $e) {

            $errors[] =
                'Unable to update your contact information. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_password'])
) {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($currentPassword === '') {

        $errors[] =
            'Please enter your current password.';
    }


    if ($newPassword === '') {

        $errors[] =
            'Please enter a new password.';
    }


    if ($confirmPassword === '') {

        $errors[] =
            'Please confirm your new password.';
    }


    if (
        $newPassword !== '' &&
        strlen($newPassword) < 8
    ) {

        $errors[] =
            'Your new password must be at least 8 characters long.';
    }


    if (
        $newPassword !== '' &&
        $confirmPassword !== '' &&
        $newPassword !== $confirmPassword
    ) {

        $errors[] =
            'The new password and confirmation password do not match.';
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK CURRENT PASSWORD
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        !password_verify(
            $currentPassword,
            $driver['password_hash']
        )
    ) {

        $errors[] =
            'Your current password is incorrect.';
    }


    /*
    |--------------------------------------------------------------------------
    | PREVENT SAME PASSWORD
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        password_verify(
            $newPassword,
            $driver['password_hash']
        )
    ) {

        $errors[] =
            'Your new password must be different from your current password.';
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PASSWORD
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $newPasswordHash =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            $stmt = $db->prepare("
                UPDATE driver

                SET password_hash = ?

                WHERE driver_id = ?
            ");

            $stmt->execute([
                $newPasswordHash,
                $driverId
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            logAudit(
                $driverId,
                'UPDATE',
                'driver',
                $driverId,
                'Driver changed account password.'
            );


            $success =
                'Your password has been changed successfully.';

        } catch (PDOException $e) {

            $errors[] =
                'Unable to change your password. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

$navItems = [

    [
        'label' => 'Dashboard',
        'url' => BASE_URL . 'driver/dashboard.php'
    ],

    [
        'label' => 'My Deliveries',
        'url' => BASE_URL . 'driver/deliveries.php'
    ],

    [
        'label' => 'Profile',
        'url' => BASE_URL . 'driver/profile.php'
    ],

    [
        'label' => 'Notifications',
        'url' => BASE_URL . 'driver/notifications.php'
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
        Profile · Hong Sing LKRR Commercial
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        .profile-page {
            max-width: 1100px;
            margin: 0 auto;
        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .profile-header {
            margin-bottom: 24px;
        }

        .profile-header h1 {
            margin: 0 0 6px;
        }

        .profile-header p {
            margin: 0;
            color: var(--color-text-secondary);
        }


        /*
        |--------------------------------------------------------------------------
        | GRID
        |--------------------------------------------------------------------------
        */

        .profile-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 1fr);

            gap: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .profile-card {
            padding: 24px;
        }

        .profile-card h2 {
            margin: 0 0 6px;
            font-size: 1.1rem;
        }

        .profile-card-description {
            margin: 0 0 20px;

            color: var(--color-text-secondary);

            font-size: 0.88rem;
            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .field-group {
            margin-bottom: 17px;
        }

        .form-label {
            display: block;

            margin-bottom: 7px;

            font-size: 13px;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
        }


        /*
        |--------------------------------------------------------------------------
        | READ ONLY
        |--------------------------------------------------------------------------
        */

        .readonly-field {
            background: var(--color-surface-hover);
            cursor: not-allowed;
        }


        /*
        |--------------------------------------------------------------------------
        | TWO COLUMNS
        |--------------------------------------------------------------------------
        */

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | DRIVER INFORMATION
        |--------------------------------------------------------------------------
        */

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;

            padding: 13px 0;

            border-bottom:
                1px solid var(--color-border);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--color-text-secondary);
            font-size: 13px;
        }

        .info-value {
            text-align: right;

            color: var(--color-text-primary);

            font-size: 13px;
            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | SECURITY NOTE
        |--------------------------------------------------------------------------
        */

        .security-note {
            margin-top: 16px;
            padding: 12px 14px;

            border-radius: 8px;

            background: var(--color-surface-hover);

            color: var(--color-text-secondary);

            font-size: 12px;
            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .driver-status {
            display: inline-flex;

            align-items: center;

            padding: 5px 10px;

            border-radius: 999px;

            font-size: 11px;
            font-weight: 700;
        }

        .driver-status.available {
            color: #16803c;
            background: #eaf8ef;
        }

        .driver-status.delivery {
            color: #1f5fbf;
            background: #eaf2ff;
        }

        .driver-status.absent {
            color: #9a6500;
            background: #fff5d9;
        }

        .driver-status.inactive {
            color: #b42318;
            background: #fdecec;
        }


        /*
        |--------------------------------------------------------------------------
        | GPS
        |--------------------------------------------------------------------------
        */

        .gps-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .gps-dot {
            width: 8px;
            height: 8px;

            border-radius: 50%;

            background: #b42318;
        }

        .gps-dot.active {
            background: #16803c;
        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD STRENGTH
        |--------------------------------------------------------------------------
        */

        .password-strength {
            margin-top: 7px;

            font-size: 12px;

            color: var(--color-text-secondary);
        }


        /*
        |--------------------------------------------------------------------------
        | ACTION
        |--------------------------------------------------------------------------
        */

        .form-actions {
            display: flex;
            justify-content: flex-end;

            margin-top: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 800px) {

            .profile-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 600px) {

            .field-row {
                grid-template-columns: 1fr;
            }

            .info-row {
                flex-direction: column;
                gap: 4px;
            }

            .info-value {
                text-align: left;
            }

        }

    </style>

</head>


<body>

<div class="app-layout">


    <?php
    require __DIR__ . '/../includes/header.php';
    ?>


    <main class="app-main">

        <div class="profile-page">


            <!-- =====================================================
                 HEADER
                 ===================================================== -->

            <div class="profile-header">

                <h1>
                    My Profile
                </h1>

                <p>
                    Manage your contact information, account security,
                    and driver information.
                </p>

            </div>


            <!-- =====================================================
                 ALERTS
                 ===================================================== -->

            <?php if (!empty($errors)): ?>

                <div class="alert-error">

                    <ul style="margin: 0; padding-left: 18px;">

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= htmlspecialchars($error) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <?php if ($success !== ''): ?>

                <div class="alert-success">

                    <?= htmlspecialchars($success) ?>

                </div>

            <?php endif; ?>


            <div class="profile-grid">


                <!-- =================================================
                     PERSONAL INFORMATION
                     ================================================= -->

                <section class="card profile-card">

                    <h2>
                        Personal Information
                    </h2>

                    <p class="profile-card-description">
                        Keep your contact information updated so the
                        company can reach you when needed.
                    </p>


                    <form
                        method="POST"
                        action=""
                    >

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
                                class="form-input readonly-field"
                                value="<?= htmlspecialchars(
                                    $driver['full_name']
                                ) ?>"
                                readonly
                            >

                        </div>


                        <div class="field-group">

                            <label
                                class="form-label"
                                for="license_number"
                            >
                                License Number
                            </label>

                            <input
                                type="text"
                                id="license_number"
                                class="form-input readonly-field"
                                value="<?= htmlspecialchars(
                                    $driver['license_number']
                                ) ?>"
                                readonly
                            >

                        </div>


                        <div class="field-group">

                            <label
                                class="form-label"
                                for="phone_number"
                            >
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone_number"
                                name="phone_number"
                                class="form-input"
                                value="<?= htmlspecialchars(
                                    $driver['phone_number']
                                ) ?>"
                                placeholder="09XXXXXXXXX"
                                required
                            >

                        </div>


                        <div class="field-group">

                            <label
                                class="form-label"
                                for="email"
                            >
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-input"
                                value="<?= htmlspecialchars(
                                    $driver['email']
                                ) ?>"
                                placeholder="you@company.com"
                                required
                            >

                        </div>


                        <div class="form-actions">

                            <button
                                type="submit"
                                name="update_contact"
                                class="btn-primary"
                            >
                                Save Contact Information
                            </button>

                        </div>

                    </form>

                </section>



                <!-- =================================================
                     ACCOUNT SECURITY
                     ================================================= -->

                <section class="card profile-card">

                    <h2>
                        Account Security
                    </h2>

                    <p class="profile-card-description">
                        Change your password regularly to keep your
                        account secure.
                    </p>


                    <form
                        method="POST"
                        action=""
                    >

                        <div class="field-group">

                            <label
                                class="form-label"
                                for="current_password"
                            >
                                Current Password
                            </label>

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                class="form-input"
                                autocomplete="current-password"
                                required
                            >

                        </div>


                        <div class="field-group">

                            <label
                                class="form-label"
                                for="new_password"
                            >
                                New Password
                            </label>

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                class="form-input"
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="At least 8 characters"
                                required
                            >

                            <div
                                id="passwordStrength"
                                class="password-strength"
                            >
                                Minimum 8 characters.
                            </div>

                        </div>


                        <div class="field-group">

                            <label
                                class="form-label"
                                for="confirm_password"
                            >
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-input"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >

                        </div>


                        <div class="form-actions">

                            <button
                                type="submit"
                                name="change_password"
                                class="btn-primary"
                            >
                                Change Password
                            </button>

                        </div>

                    </form>


                    <div class="security-note">

                        Your password is stored securely using a
                        password hash. Never share your password with
                        another person.

                    </div>

                </section>



                <!-- =================================================
                     DRIVER INFORMATION
                     ================================================= -->

                <section class="card profile-card">

                    <h2>
                        Driver Information
                    </h2>

                    <p class="profile-card-description">
                        Operational information assigned to your driver
                        account.
                    </p>


                    <div class="info-list">


                        <!-- STATUS -->

                        <div class="info-row">

                            <span class="info-label">
                                Driver Status
                            </span>

                            <span class="info-value">

                                <?php

                                $statusClass = match (
                                    $driver['status']
                                ) {

                                    'Available' =>
                                        'available',

                                    'On Delivery' =>
                                        'delivery',

                                    'Absent' =>
                                        'absent',

                                    'Inactive' =>
                                        'inactive',

                                    default =>
                                        'inactive'
                                };

                                ?>

                                <span
                                    class="driver-status <?= $statusClass ?>"
                                >
                                    <?= htmlspecialchars(
                                        $driver['status']
                                    ) ?>
                                </span>

                            </span>

                        </div>


                        <!-- TRUCK -->

                        <div class="info-row">

                            <span class="info-label">
                                Assigned Truck
                            </span>

                            <span class="info-value">

                                <?php if (
                                    !empty(
                                        $driver['plate_number']
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $driver['plate_number']
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $driver['truck_model']
                                        )
                                    ): ?>

                                        —
                                        <?= htmlspecialchars(
                                            $driver['truck_model']
                                        ) ?>

                                    <?php endif; ?>

                                <?php else: ?>

                                    No truck assigned

                                <?php endif; ?>

                            </span>

                        </div>


                        <!-- CAPACITY -->

                        <div class="info-row">

                            <span class="info-label">
                                Truck Capacity
                            </span>

                            <span class="info-value">

                                <?php if (
                                    $driver['truck_capacity']
                                    !== null
                                ): ?>

                                    <?= number_format(
                                        (float)
                                        $driver['truck_capacity'],
                                        2
                                    ) ?>

                                    kg

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </span>

                        </div>


                        <!-- TOTAL DELIVERIES -->

                        <div class="info-row">

                            <span class="info-label">
                                Total Deliveries
                            </span>

                            <span class="info-value">

                                <?= number_format(
                                    (int)
                                    $driver['total_deliveries']
                                ) ?>

                            </span>

                        </div>


                        <!-- RATING -->

                        <div class="info-row">

                            <span class="info-label">
                                Driver Rating
                            </span>

                            <span class="info-value">

                                <?php if (
                                    $driver['rating'] !== null
                                ): ?>

                                    <?= number_format(
                                        (float)
                                        $driver['rating'],
                                        2
                                    ) ?>

                                    / 5.00

                                <?php else: ?>

                                    No rating yet

                                <?php endif; ?>

                            </span>

                        </div>


                        <!-- GPS -->

                        <div class="info-row">

                            <span class="info-label">
                                GPS Location
                            </span>

                            <span class="info-value">

                                <?php

                                $hasGps =
                                    $driver['current_latitude']
                                    !== null
                                    &&
                                    $driver['current_longitude']
                                    !== null;

                                ?>

                                <span class="gps-status">

                                    <span
                                        class="gps-dot <?= $hasGps ? 'active' : '' ?>"
                                    ></span>

                                    <?= $hasGps
                                        ? 'Location Available'
                                        : 'Location Not Available'
                                    ?>

                                </span>

                            </span>

                        </div>


                        <!-- LAST UPDATE -->

                        <div class="info-row">

                            <span class="info-label">
                                Last Location Update
                            </span>

                            <span class="info-value">

                                <?php if (
                                    !empty(
                                        $driver['location_updated_at']
                                    )
                                ): ?>

                                    <?= date(
                                        'M d, Y h:i A',
                                        strtotime(
                                            $driver[
                                                'location_updated_at'
                                            ]
                                        )
                                    ) ?>

                                <?php else: ?>

                                    No location update

                                <?php endif; ?>

                            </span>

                        </div>


                    </div>

                </section>



                <!-- =================================================
                     ACCOUNT DETAILS
                     ================================================= -->

                <section class="card profile-card">

                    <h2>
                        Account Details
                    </h2>

                    <p class="profile-card-description">
                        Basic information about your driver account.
                    </p>


                    <div class="info-list">


                        <div class="info-row">

                            <span class="info-label">
                                Account Role
                            </span>

                            <span class="info-value">
                                Driver
                            </span>

                        </div>


                        <div class="info-row">

                            <span class="info-label">
                                Driver ID
                            </span>

                            <span class="info-value">

                                #<?= (int)
                                    $driver['driver_id']
                                ?>

                            </span>

                        </div>


                        <div class="info-row">

                            <span class="info-label">
                                Account Created
                            </span>

                            <span class="info-value">

                                <?= !empty(
                                    $driver['created_at']
                                )
                                    ? date(
                                        'M d, Y',
                                        strtotime(
                                            $driver['created_at']
                                        )
                                    )
                                    : '—'
                                ?>

                            </span>

                        </div>


                        <div class="info-row">

                            <span class="info-label">
                                Account Email
                            </span>

                            <span class="info-value">

                                <?= htmlspecialchars(
                                    $driver['email']
                                ) ?>

                            </span>

                        </div>


                        <div class="info-row">

                            <span class="info-label">
                                Contact Number
                            </span>

                            <span class="info-value">

                                <?= htmlspecialchars(
                                    $driver['phone_number']
                                ) ?>

                            </span>

                        </div>


                    </div>


                    <div class="security-note">

                        <strong>Need to change operational information?</strong>
                        <br>
                        Contact the administrator if your name,
                        license information, driver status, or assigned
                        truck needs to be changed.

                    </div>

                </section>


            </div>

        </div>

    </main>

</div>


<script>

    /*
    |--------------------------------------------------------------------------
    | PASSWORD STRENGTH INDICATOR
    |--------------------------------------------------------------------------
    */

    const newPassword =
        document.getElementById('new_password');

    const passwordStrength =
        document.getElementById('passwordStrength');


    if (newPassword && passwordStrength) {

        newPassword.addEventListener(
            'input',
            function () {

                const password =
                    this.value;

                if (password.length === 0) {

                    passwordStrength.textContent =
                        'Minimum 8 characters.';

                    return;
                }


                if (password.length < 8) {

                    passwordStrength.textContent =
                        'Password is too short.';

                    return;
                }


                let score = 0;


                if (/[a-z]/.test(password)) {
                    score++;
                }

                if (/[A-Z]/.test(password)) {
                    score++;
                }

                if (/[0-9]/.test(password)) {
                    score++;
                }

                if (/[^A-Za-z0-9]/.test(password)) {
                    score++;
                }


                if (score <= 1) {

                    passwordStrength.textContent =
                        'Password strength: Weak';

                } else if (score === 2) {

                    passwordStrength.textContent =
                        'Password strength: Fair';

                } else if (score === 3) {

                    passwordStrength.textContent =
                        'Password strength: Good';

                } else {

                    passwordStrength.textContent =
                        'Password strength: Strong';

                }

            }
        );

    }

</script>

</body>

</html>