<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_log.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

$userId = currentUserId();


/*
|--------------------------------------------------------------------------
| PASSWORD REMINDER
|--------------------------------------------------------------------------
|
| Advisory only.
| This does not lock the Super Admin account.
|
*/

const PASSWORD_REMINDER_DAYS = 90;


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}


$success = '';
$error = '';


/*
|--------------------------------------------------------------------------
| LOAD SUPER ADMIN PROFILE
|--------------------------------------------------------------------------
*/

function loadSuperAdminProfile(
    PDO $db,
    int $userId
): ?array {

    $stmt = $db->prepare("
        SELECT
            user_id,
            full_name,
            email,
            password_hash,
            password_changed_at,
            role,
            status
        FROM system_user
        WHERE user_id = ?
          AND role = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId,
        ROLE_SUPER_ADMIN
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $row ?: null;
}


/*
|--------------------------------------------------------------------------
| GET PROFILE
|--------------------------------------------------------------------------
*/

$profile =
    loadSuperAdminProfile(
        $db,
        $userId
    );


if (!$profile) {

    logoutUser();

    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php?error=account_verification_failed'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $csrf =
        $_POST['csrf_token']
        ?? '';

    $currentPassword =
        (string) (
            $_POST['current_password']
            ?? ''
        );

    $newPassword =
        (string) (
            $_POST['new_password']
            ?? ''
        );

    $confirmPassword =
        (string) (
            $_POST['confirm_password']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrf
        )
    ) {

        $error =
            'Your request could not be verified. '
            . 'Please refresh the page and try again.';

    } elseif (
        $currentPassword === ''
        ||
        $newPassword === ''
        ||
        $confirmPassword === ''
    ) {

        $error =
            'Please complete all password fields.';

    } elseif (
        !password_verify(
            $currentPassword,
            $profile['password_hash']
        )
    ) {

        $error =
            'The current password you entered is incorrect.';

    } elseif (
        $newPassword !==
        $confirmPassword
    ) {

        $error =
            'The new password and confirmation do not match.';

    } elseif (
        strlen($newPassword) < 12
    ) {

        $error =
            'The new password must contain at least 12 characters.';

    } elseif (
        !preg_match(
            '/[A-Z]/',
            $newPassword
        )
    ) {

        $error =
            'The new password must contain at least one uppercase letter.';

    } elseif (
        !preg_match(
            '/[a-z]/',
            $newPassword
        )
    ) {

        $error =
            'The new password must contain at least one lowercase letter.';

    } elseif (
        !preg_match(
            '/[0-9]/',
            $newPassword
        )
    ) {

        $error =
            'The new password must contain at least one number.';

    } elseif (
        !preg_match(
            '/[^A-Za-z0-9]/',
            $newPassword
        )
    ) {

        $error =
            'The new password must contain at least one special character.';

    } elseif (
        password_verify(
            $newPassword,
            $profile['password_hash']
        )
    ) {

        $error =
            'Your new password must be different from your current password.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | HASH PASSWORD
            |--------------------------------------------------------------------------
            */

            $newHash =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            if ($newHash === false) {

                throw new RuntimeException(
                    'Unable to secure the new password.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE PASSWORD
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                UPDATE system_user
                SET
                    password_hash = ?,
                    password_changed_at = NOW()
                WHERE user_id = ?
                  AND role = ?
            ");

            $stmt->execute([
                $newHash,
                $userId,
                ROLE_SUPER_ADMIN
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            |
            | Password values must never be stored in audit logs.
            |
            */

            try {

                logAudit(
                    $userId,
                    'UPDATE',
                    'Profile Security',
                    $userId,
                    currentUserName()
                    . ' changed the Super Admin account password.'
                );

            } catch (
                Throwable $auditError
            ) {

                /*
                 * Password change remains successful
                 * even if audit logging is unavailable.
                 */

            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $success =
                'Password changed successfully.';


            /*
            |--------------------------------------------------------------------------
            | REFRESH CSRF TOKEN
            |--------------------------------------------------------------------------
            */

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );


            /*
            |--------------------------------------------------------------------------
            | RELOAD PROFILE
            |--------------------------------------------------------------------------
            */

            $profile =
                loadSuperAdminProfile(
                    $db,
                    $userId
                );


        } catch (
            Throwable $e
        ) {

            $error =
                'Unable to change the password right now. '
                . 'Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| PASSWORD SECURITY INFORMATION
|--------------------------------------------------------------------------
*/

$passwordChangedAt =
    $profile['password_changed_at']
    ?? null;

$passwordAgeDays = null;

$nextReminderDate = null;

$passwordReminderDue = false;


if (!empty($passwordChangedAt)) {

    try {

        $changed =
            new DateTime(
                $passwordChangedAt
            );

        $today =
            new DateTime(
                'today'
            );


        $passwordAgeDays =
            max(
                0,
                (int) $changed
                    ->diff($today)
                    ->format('%r%a')
            );


        $nextReminder =
            clone $changed;


        $nextReminder->modify(
            '+' .
            PASSWORD_REMINDER_DAYS .
            ' days'
        );


        $nextReminderDate =
            $nextReminder->format(
                'M d, Y'
            );


        $passwordReminderDue =
            $passwordAgeDays >=
            PASSWORD_REMINDER_DAYS;


    } catch (
        Throwable $e
    ) {

        $passwordChangedAt = null;
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
        'url' =>
            BASE_URL .
            'super_admin/dashboard.php'
    ],

    [
        'label' => 'Operations Map',
        'url' =>
            BASE_URL .
            'super_admin/operations_map.php'
    ],

    [
        'label' => 'Manage Admins',
        'url' =>
            BASE_URL .
            'super_admin/manage_admins.php'
    ],

    [
        'label' => 'Manage Drivers',
        'url' =>
            BASE_URL .
            'super_admin/manage_drivers.php'
    ],

    [
        'label' => 'Manage Trucks',
        'url' =>
            BASE_URL .
            'super_admin/manage_trucks.php'
    ],

    [
        'label' => 'Reports',
        'url' =>
            BASE_URL .
            'super_admin/reports.php'
    ],

    [
        'label' => 'System Logs',
        'url' =>
            BASE_URL .
            'super_admin/system_logs.php'
    ],

    [
        'label' => 'System Configuration',
        'url' =>
            BASE_URL .
            'super_admin/system_configuration.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'super_admin/profile.php'
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
    Profile & Security · Super Admin
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

    max-width: 1100px;

    margin: 0 auto;

}


/* ==========================================================
   PROFILE GRID
========================================================== */

.profile-grid {

    display: grid;

    grid-template-columns:
        minmax(0, .85fr)
        minmax(0, 1.15fr);

    gap: 20px;

    align-items: start;

}


/* ==========================================================
   CARD
========================================================== */

.profile-card {

    padding: 24px;

}

.profile-card h2 {

    margin-top: 0;

}


/* ==========================================================
   PROFILE INFORMATION
========================================================== */

.profile-row {

    padding: 14px 0;

    border-bottom:
        1px solid
        var(--color-border);

}

.profile-row:last-child {

    border-bottom: 0;

}


.profile-label {

    display: block;

    margin-bottom: 5px;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .45px;

    color:
        var(--color-text-muted);

}


.profile-value {

    font-weight: 600;

    color:
        var(--color-text-primary);

    overflow-wrap: anywhere;

}


/* ==========================================================
   SECURITY STATUS
========================================================== */

.security-box {

    padding: 16px;

    border:
        1px solid
        var(--color-border);

    border-radius: 10px;

    margin-bottom: 18px;

}


.security-warning {

    border-color: #f59e0b;

    background: #fffbeb;

    color: #92400e;

}


.security-good {

    border-color: #86efac;

    background: #f0fdf4;

    color: #166534;

}


.security-neutral {

    background:
        var(--color-surface);

}


/* ==========================================================
   SECURITY META
========================================================== */

.security-meta {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 12px;

    margin-top: 14px;

}


.security-meta > div {

    padding: 12px;

    border:
        1px solid
        var(--color-border);

    border-radius: 8px;

}


/* ==========================================================
   FORM
========================================================== */

.field-group {

    margin-bottom: 16px;

}


.field-group .form-label {

    display: block;

    margin-bottom: 7px;

}


/* ==========================================================
   PASSWORD INPUT
========================================================== */

/* ==========================================================
   PASSWORD FIELD
========================================================== */

.password-wrap {
    position: relative;
    width: 100%;
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

.password-wrap .form-input {
    width: 100%;

    box-sizing: border-box;

    /*
     * Reserve space for our Show/Hide button.
     */
    padding-right: 85px !important;
}


/*
|--------------------------------------------------------------------------
| SHOW / HIDE BUTTON
|--------------------------------------------------------------------------
*/

.password-wrap .toggle-password {
    position: absolute !important;

    right: 12px !important;
    top: 50% !important;

    transform: translateY(-50%) !important;

    width: auto !important;
    min-width: auto !important;
    height: auto !important;

    padding: 4px 6px !important;

    margin: 0 !important;

    border: 0 !important;

    background: transparent !important;

    color: var(--color-text-secondary) !important;

    font-family: inherit;
    font-size: 12px;
    font-weight: 700;

    line-height: 1;

    cursor: pointer;

    z-index: 10;
}


.password-wrap .toggle-password:hover {
    color: var(--color-accent) !important;
}


/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| Prevent pseudo-elements/styles from theme.css from generating
| another Show/Hide label.
|--------------------------------------------------------------------------
*/

.password-wrap::before,
.password-wrap::after,
.password-wrap .form-input::before,
.password-wrap .form-input::after,
.password-wrap .toggle-password::before,
.password-wrap .toggle-password::after {
    content: none !important;
    display: none !important;
}


/*
|--------------------------------------------------------------------------
| BROWSER PASSWORD REVEAL
|--------------------------------------------------------------------------
*/

.password-wrap input::-ms-reveal,
.password-wrap input::-ms-clear {
    display: none;
}


/*
|--------------------------------------------------------------------------
| FOCUS
|--------------------------------------------------------------------------
*/

.password-wrap:focus-within {

    border-color:
        var(--color-accent);

    box-shadow:
        0 0 0 2px
        rgba(59, 130, 246, .10);

}


/*
|--------------------------------------------------------------------------
| PASSWORD FIELD
|--------------------------------------------------------------------------
*/

.password-wrap .form-input {

    width: 100%;

    min-width: 0;

    box-sizing: border-box;

    /*
     * The wrapper itself provides the border.
     */

    border: 0 !important;

    border-radius: 0 !important;

    outline: none !important;

    box-shadow: none !important;

    background:
        var(--color-surface);

    color:
        var(--color-text-primary);

    padding:
        11px 12px;

}


/*
|--------------------------------------------------------------------------
| SHOW / HIDE BUTTON
|--------------------------------------------------------------------------
*/

.toggle-password {

    /*
     * This is now a separate grid column.
     * It no longer floats above the password field.
     */

    position: static;

    transform: none;

    min-width: 72px;

    padding:
        0 15px;

    border: 0;

    border-left:
        1px solid
        var(--color-border);

    background:
        var(--color-surface);

    color:
        var(--color-text-secondary);

    font-family: inherit;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;

    transition:
        color .15s ease,
        background .15s ease;

}


.toggle-password:hover {

    color:
        var(--color-accent);

    background:
        rgba(59, 130, 246, .06);

}


.toggle-password:focus {

    outline: none;

    color:
        var(--color-accent);

}


/*
|--------------------------------------------------------------------------
| REMOVE MICROSOFT PASSWORD REVEAL BUTTON
|--------------------------------------------------------------------------
|
| Prevents the browser's own password icon from
| conflicting with our Show / Hide control.
|
*/

.password-wrap input::-ms-reveal,
.password-wrap input::-ms-clear {

    display: none;

}


/* ==========================================================
   PASSWORD REQUIREMENTS
========================================================== */

.requirements {

    margin:
        8px
        0
        20px;

    padding-left: 20px;

    color:
        var(--color-text-secondary);

    font-size: 13px;

    line-height: 1.7;

}


/* ==========================================================
   NOTES
========================================================== */

.profile-note {

    font-size: 12px;

    color:
        var(--color-text-secondary);

    line-height: 1.6;

}


/* ==========================================================
   ALERTS
========================================================== */

.alert-success,
.alert-error {

    margin-bottom: 18px;

}


/* ==========================================================
   DIVIDER
========================================================== */

.profile-divider {

    border: 0;

    border-top:
        1px solid
        var(--color-border);

    margin:
        24px
        0;

}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (
    max-width: 850px
) {

    .profile-grid {

        grid-template-columns: 1fr;

    }

}


@media (
    max-width: 520px
) {

    .security-meta {

        grid-template-columns: 1fr;

    }


    .profile-card {

        padding: 18px;

    }


    .toggle-password {

        min-width: 66px;

        padding:
            0 12px;

    }

}

</style>

</head>


<body>


<div class="app-layout">


<?php

require __DIR__ .
    '/../includes/header.php';

?>


<main class="app-main">


<div class="profile-page">


    <!-- ======================================================
         PAGE HEADER
    ======================================================= -->

    <div class="page-header">

        <div>

            <h1>
                Profile & Security
            </h1>

            <p>
                View your Super Admin account
                and manage password security.
            </p>

        </div>

    </div>



    <!-- ======================================================
         SUCCESS MESSAGE
    ======================================================= -->

    <?php if ($success): ?>

        <div class="alert-success">

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>



    <!-- ======================================================
         ERROR MESSAGE
    ======================================================= -->

    <?php if ($error): ?>

        <div class="alert-error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>



    <!-- ======================================================
         PROFILE GRID
    ======================================================= -->

    <div class="profile-grid">


        <!-- ==================================================
             ACCOUNT INFORMATION
        =================================================== -->

        <section class="card profile-card">


            <h2>
                Account Information
            </h2>


            <!-- FULL NAME -->

            <div class="profile-row">

                <span class="profile-label">
                    Full Name
                </span>

                <div class="profile-value">

                    <?= htmlspecialchars(
                        $profile['full_name']
                        ?? '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <!-- EMAIL -->

            <div class="profile-row">

                <span class="profile-label">
                    Email Address
                </span>

                <div class="profile-value">

                    <?= htmlspecialchars(
                        $profile['email']
                        ?? '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <!-- ROLE -->

            <div class="profile-row">

                <span class="profile-label">
                    Role
                </span>

                <div class="profile-value">

                    <?= htmlspecialchars(
                        $profile['role']
                        ?? 'Super Admin',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <!-- STATUS -->

            <div class="profile-row">

                <span class="profile-label">
                    Account Status
                </span>

                <div class="profile-value">

                    <?= htmlspecialchars(
                        $profile['status']
                        ?? 'Active',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


        </section>



        <!-- ==================================================
             PASSWORD SECURITY
        =================================================== -->

        <section class="card profile-card">


            <h2>
                Password Security
            </h2>



            <!-- ==================================================
                 PASSWORD STATUS
            =================================================== -->

            <?php if (
                !$passwordChangedAt
            ): ?>


                <div
                    class="
                        security-box
                        security-warning
                    "
                >

                    <strong>
                        Password change date is not yet recorded.
                    </strong>

                    <br>

                    Change your password once to start the
                    <?= PASSWORD_REMINDER_DAYS ?>-day
                    security reminder.

                </div>


            <?php elseif (
                $passwordReminderDue
            ): ?>


                <div
                    class="
                        security-box
                        security-warning
                    "
                >

                    <strong>
                        Password update recommended.
                    </strong>

                    <br>

                    Your password was changed

                    <?= (int) $passwordAgeDays ?>

                    days ago.

                    Consider changing it now.

                </div>


            <?php else: ?>


                <div
                    class="
                        security-box
                        security-good
                    "
                >

                    <strong>
                        Password status is current.
                    </strong>

                    <br>

                    The next security reminder
                    is scheduled for

                    <?= htmlspecialchars(
                        $nextReminderDate,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>.

                </div>


            <?php endif; ?>



            <!-- ==================================================
                 SECURITY META
            =================================================== -->

            <div class="security-meta">


                <div>

                    <span class="profile-label">
                        Last Password Change
                    </span>

                    <div class="profile-value">

                        <?php if (
                            $passwordChangedAt
                        ): ?>

                            <?= htmlspecialchars(
                                date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $passwordChangedAt
                                    )
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        <?php else: ?>

                            Not recorded yet

                        <?php endif; ?>

                    </div>

                </div>



                <div>

                    <span class="profile-label">
                        Security Reminder
                    </span>

                    <div class="profile-value">

                        Every

                        <?= PASSWORD_REMINDER_DAYS ?>

                        days

                    </div>

                </div>


            </div>



            <hr class="profile-divider">



            <!-- ==================================================
                 CHANGE PASSWORD
            =================================================== -->

            <h2>
                Change Password
            </h2>


            <p class="profile-note">

                For security, your current password
                is required before a new password
                can be saved.

            </p>



            <form
                method="POST"
                action=""
                autocomplete="off"
            >


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >



                <!-- ==================================================
                     CURRENT PASSWORD
                =================================================== -->

                <div class="field-group">


                    <label
                        class="form-label"
                        for="current_password"
                    >
                        Current Password
                    </label>


                    <div class="password-wrap">


                        <input
                            class="form-input"
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            data-target="current_password"
                            aria-label="Show current password"
                            aria-pressed="false"
                        >
                            Show
                        </button>


                    </div>


                </div>



                <!-- ==================================================
                     NEW PASSWORD
                =================================================== -->

                <div class="field-group">


                    <label
                        class="form-label"
                        for="new_password"
                    >
                        New Password
                    </label>


                    <div class="password-wrap">


                        <input
                            class="form-input"
                            type="password"
                            id="new_password"
                            name="new_password"
                            autocomplete="new-password"
                            minlength="12"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            data-target="new_password"
                            aria-label="Show new password"
                            aria-pressed="false"
                        >
                            Show
                        </button>


                    </div>


                </div>



                <!-- ==================================================
                     CONFIRM PASSWORD
                =================================================== -->

                <div class="field-group">


                    <label
                        class="form-label"
                        for="confirm_password"
                    >
                        Confirm New Password
                    </label>


                    <div class="password-wrap">


                        <input
                            class="form-input"
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            autocomplete="new-password"
                            minlength="12"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            data-target="confirm_password"
                            aria-label="Show confirmed password"
                            aria-pressed="false"
                        >
                            Show
                        </button>


                    </div>


                </div>



                <!-- ==================================================
                     PASSWORD REQUIREMENTS
                =================================================== -->

                <ul class="requirements">

                    <li>
                        At least 12 characters
                    </li>

                    <li>
                        At least one uppercase
                        and one lowercase letter
                    </li>

                    <li>
                        At least one number
                    </li>

                    <li>
                        At least one special character
                    </li>

                    <li>
                        Must be different from
                        the current password
                    </li>

                </ul>



                <!-- ==================================================
                     SUBMIT
                =================================================== -->

                <button
                    type="submit"
                    class="btn-primary"
                    onclick="
                        return confirm(
                            'Change your Super Admin password?'
                        );
                    "
                >
                    Change Password
                </button>


            </form>


        </section>


    </div>


</div>


</main>


</div>



<!-- ==========================================================
     SHOW / HIDE PASSWORD
========================================================== -->

<script>

document
    .querySelectorAll(
        '.toggle-password'
    )
    .forEach(
        function (
            button
        ) {

            button.addEventListener(
                'click',
                function () {

                    const targetId =
                        button.dataset.target;


                    const input =
                        document.getElementById(
                            targetId
                        );


                    if (!input) {

                        return;

                    }


                    const shouldShow =
                        input.type ===
                        'password';


                    input.type =
                        shouldShow
                            ? 'text'
                            : 'password';


                    button.textContent =
                        shouldShow
                            ? 'Hide'
                            : 'Show';


                    button.setAttribute(
                        'aria-pressed',
                        shouldShow
                            ? 'true'
                            : 'false'
                    );


                    button.setAttribute(
                        'aria-label',
                        shouldShow
                            ? 'Hide password'
                            : 'Show password'
                    );

                }
            );

        }
    );

</script>


</body>

</html>