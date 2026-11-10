<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_log.php';


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

    /*
     * Invalid authenticated role.
     */
    logoutUser();

    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php?error=unauthorized'
    );

    exit;
}


/* ==========================================================
   VARIABLES
========================================================== */

$error = '';

$identifier = '';

$now = time();


/* ==========================================================
   LOGIN RATE-LIMIT INITIALIZATION
========================================================== */

/*
 * This protection is stored in the current PHP session.
 *
 * Maximum attempts and lockout duration come from:
 *
 * LOGIN_MAX_ATTEMPTS
 * LOGIN_LOCKOUT_SECONDS
 */

if (
    !isset($_SESSION['login_attempts']) ||
    !is_int($_SESSION['login_attempts'])
) {

    $_SESSION['login_attempts'] = 0;
}

if (
    !isset($_SESSION['login_lockout_until']) ||
    !is_int($_SESSION['login_lockout_until'])
) {

    $_SESSION['login_lockout_until'] = 0;
}


/* ==========================================================
   EXPIRED LOCKOUT CLEANUP
========================================================== */

if (
    $_SESSION['login_lockout_until'] > 0 &&
    $now >= $_SESSION['login_lockout_until']
) {

    $_SESSION['login_attempts'] = 0;

    $_SESSION['login_lockout_until'] = 0;
}


/* ==========================================================
   LOGIN FORM SUBMISSION
========================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
     * ------------------------------------------------------
     * CSRF VALIDATION
     * ------------------------------------------------------
     */

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Your session expired or the request was invalid. ' .
            'Please refresh the page and try again.';

    } elseif (
        /*
         * --------------------------------------------------
         * TEMPORARY LOGIN LOCKOUT
         * --------------------------------------------------
         */

        $_SESSION['login_lockout_until'] > $now
    ) {

        $remainingSeconds =
            $_SESSION['login_lockout_until']
            - $now;

        $remainingMinutes =
            max(
                1,
                (int) ceil(
                    $remainingSeconds / 60
                )
            );

        $error =
            'Too many unsuccessful login attempts. ' .
            'Please try again in approximately ' .
            $remainingMinutes .
            ' minute' .
            ($remainingMinutes === 1 ? '' : 's') .
            '.';

    } else {

        /*
         * --------------------------------------------------
         * INPUT
         * --------------------------------------------------
         */

        $identifier =
            cleanInput(
                (string) (
                    $_POST['identifier']
                    ?? ''
                )
            );

        $password =
            (string) (
                $_POST['password']
                ?? ''
            );


        /*
         * --------------------------------------------------
         * BASIC VALIDATION
         * --------------------------------------------------
         */

        if (
            $identifier === '' ||
            $password === ''
        ) {

            $error =
                'Please enter your email and password.';

        } else {

            try {

                $db =
                    Database::getConnection();

                $user = null;


                /*
                 * ==================================================
                 * SYSTEM USER
                 * ==================================================
                 *
                 * Includes:
                 *
                 * - Super Admin
                 * - Admin
                 */

                $stmt = $db->prepare("
                    SELECT
                        user_id AS id,
                        full_name,
                        email,
                        password_hash,
                        role,
                        status
                    FROM system_user
                    WHERE email = :identifier
                    LIMIT 1
                ");

                $stmt->execute([
                    'identifier' =>
                        $identifier
                ]);

                $user =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                /*
                 * ==================================================
                 * DRIVER
                 * ==================================================
                 */

                if (!$user) {

                    $stmt = $db->prepare("
                        SELECT
                            driver_id AS id,
                            full_name,
                            email,
                            password_hash,
                            ? AS role,
                            status
                        FROM driver
                        WHERE email = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ROLE_DRIVER,
                        $identifier
                    ]);

                    $user =
                        $stmt->fetch(
                            PDO::FETCH_ASSOC
                        );
                }


                /*
                 * ==================================================
                 * CUSTOMER
                 * ==================================================
                 */

                if (!$user) {

                    $stmt = $db->prepare("
                        SELECT
                            customer_id AS id,
                            full_name,
                            email,
                            password_hash,
                            ? AS role
                        FROM customer
                        WHERE email = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ROLE_CUSTOMER,
                        $identifier
                    ]);

                    $user =
                        $stmt->fetch(
                            PDO::FETCH_ASSOC
                        );
                }


                /*
                 * ==================================================
                 * PASSWORD VERIFICATION
                 * ==================================================
                 */

                $passwordIsValid =
                    $user &&
                    !empty(
                        $user['password_hash']
                    ) &&
                    password_verify(
                        $password,
                        $user['password_hash']
                    );


                /*
                 * ==================================================
                 * INVALID LOGIN
                 * ==================================================
                 */

                if (!$passwordIsValid) {

                    /*
                     * Increment failed attempt count.
                     */
                    $_SESSION['login_attempts']++;


                    /*
                     * Activate temporary lockout after
                     * the configured maximum attempts.
                     */
                    if (
                        $_SESSION['login_attempts']
                        >= LOGIN_MAX_ATTEMPTS
                    ) {

                        $_SESSION['login_lockout_until'] =
                            time()
                            +
                            LOGIN_LOCKOUT_SECONDS;

                        $minutes =
                            max(
                                1,
                                (int) ceil(
                                    LOGIN_LOCKOUT_SECONDS
                                    / 60
                                )
                            );

                        $error =
                            'Too many unsuccessful login attempts. ' .
                            'Login has been temporarily locked for ' .
                            $minutes .
                            ' minutes.';

                    } else {

                        /*
                         * Keep this message generic.
                         *
                         * Do NOT reveal whether the email exists.
                         */
                        $error =
                            'Incorrect email or password.';
                    }

                } else {

                    /*
                     * ==================================================
                     * ACCOUNT STATUS VALIDATION
                     * ==================================================
                     */

                    $role =
                        (string) $user['role'];

                    $accountAllowed = true;

                    $statusError = '';


                    /*
                     * --------------------------------------------------
                     * ADMIN
                     * --------------------------------------------------
                     */

                    if (
                        $role === ROLE_ADMIN
                    ) {

                        $status =
                            $user['status']
                            ?? '';

                        if ($status !== 'Active') {

                            $accountAllowed = false;

                            switch ($status) {

                                case 'On Leave':

                                    $statusError =
                                        'Your administrator account is currently on leave.';

                                    break;


                                case 'Absent':

                                    $statusError =
                                        'Your administrator account is currently marked as absent.';

                                    break;


                                case 'Resigned':

                                case 'Terminated':

                                    /*
                                     * Avoid unnecessary employment
                                     * details on the public login page.
                                     */
                                    $statusError =
                                        'This administrator account is currently unavailable.';

                                    break;


                                default:

                                    $statusError =
                                        'This administrator account is currently unavailable.';

                                    break;
                            }
                        }
                    }


                    /*
                     * --------------------------------------------------
                     * DRIVER
                     * --------------------------------------------------
                     */

                    if (
                        $role === ROLE_DRIVER
                    ) {

                        $status =
                            $user['status']
                            ?? '';

                        $allowedDriverStatuses = [
                            'Available',
                            'On Delivery'
                        ];

                        if (
                            !in_array(
                                $status,
                                $allowedDriverStatuses,
                                true
                            )
                        ) {

                            $accountAllowed = false;

                            switch ($status) {

                                case 'On Leave':

                                    $statusError =
                                        'Your driver account is currently on leave.';

                                    break;


                                case 'Absent':

                                    $statusError =
                                        'Your driver account is currently marked as absent.';

                                    break;


                                case 'Inactive':

                                case 'Resigned':

                                case 'Terminated':

                                    $statusError =
                                        'This driver account is currently unavailable.';

                                    break;


                                default:

                                    $statusError =
                                        'This driver account is currently unavailable.';

                                    break;
                            }
                        }
                    }


                    /*
                     * ==================================================
                     * BLOCKED ACCOUNT
                     * ==================================================
                     */

                    if (!$accountAllowed) {

                        /*
                         * Password was correct, so this is not
                         * considered a failed password attempt.
                         */
                        $_SESSION['login_attempts'] = 0;

                        $_SESSION['login_lockout_until'] = 0;

                        $error =
                            $statusError;

                    } else {

                        /*
                         * ==================================================
                         * SUCCESSFUL LOGIN
                         * ==================================================
                         */

                        $_SESSION['login_attempts'] = 0;

                        $_SESSION['login_lockout_until'] = 0;


                        loginUser(
                            (int) $user['id'],
                            (string) $user['full_name'],
                            $role
                        );


                        /*
                         * Record successful authentication.
                         */
                        try {

                            logAudit(
                                currentUserId(),
                                'LOGIN',
                                'Authentication',
                                currentUserId(),
                                currentUserName() .
                                ' logged into the system.'
                            );

                        } catch (Throwable $auditError) {

                            /*
                             * Audit logging failure should be
                             * recorded in PHP logs, but should not
                             * prevent a valid user from logging in.
                             */

                            error_log(
                                'Login audit error: ' .
                                $auditError->getMessage()
                            );
                        }


                        /*
                         * Redirect only to a known role dashboard.
                         */
                        if (
                            isset(
                                ROLE_DASHBOARDS[$role]
                            )
                        ) {

                            header(
                                'Location: ' .
                                ROLE_DASHBOARDS[$role]
                            );

                            exit;
                        }


                        /*
                         * Defensive fallback.
                         */
                        logoutUser();

                        $error =
                            'Your account role is not configured correctly.';
                    }
                }

            } catch (Throwable $e) {

                /*
                 * Never expose SQL/PDO details to the login page.
                 */

                error_log(
                    'Login error: ' .
                    $e->getMessage()
                );

                $error =
                    'Unable to sign in at the moment. Please try again.';
            }
        }
    }
}


/* ==========================================================
   ERROR MESSAGE FROM REDIRECT
========================================================== */

if (
    $error === '' &&
    isset($_GET['error'])
) {

    switch (
        (string) $_GET['error']
    ) {

        case 'session_expired':

            $error =
                'Your session expired due to inactivity. Please sign in again.';

            break;


        case 'unauthorized':

            $error =
                'You are not authorized to access that page.';

            break;


        case 'account_unavailable':

        case 'account_verification_failed':

            $error =
                'Your account is currently unavailable. Please contact the administrator.';

            break;


        case 'on_leave':

            $error =
                'Your administrator account is currently on leave.';

            break;


        case 'absent':

            $error =
                'Your administrator account is currently marked as absent.';

            break;


        case 'resigned':

        case 'terminated':

            $error =
                'Your administrator account is currently unavailable.';

            break;


        case 'driver_on_leave':

            $error =
                'Your driver account is currently on leave.';

            break;


        case 'driver_absent':

            $error =
                'Your driver account is currently marked as absent.';

            break;


        case 'driver_inactive':

        case 'driver_resigned':

        case 'driver_terminated':

        case 'driver_unavailable':

            $error =
                'Your driver account is currently unavailable.';

            break;
    }
}


/* ==========================================================
   CURRENT LOCKOUT DISPLAY
========================================================== */

$currentlyLocked =
    isset(
        $_SESSION['login_lockout_until']
    )
    &&
    (int) $_SESSION['login_lockout_until']
    > time();

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
    Sign In · Hong Sing LKRR Commercial
</title>

<link
    rel="stylesheet"
    href="<?= e(BASE_URL) ?>assets/css/theme.css"
>

<style>

.login-wrapper {

    position: relative;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: flex-end;

    padding: 60px 8vw;

    overflow: hidden;
}


.login-bg {

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


.login-bg::after {

    content: '';

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            90deg,
            rgba(0, 0, 0, 0.20),
            rgba(0, 0, 0, 0.55)
        );
}


.login-card {

    position: relative;

    z-index: 1;

    width: 100%;

    max-width: 430px;

    padding: 40px 36px;
}


.login-card h1 {

    font-size: 1.9rem;

    margin:
        18px
        0
        8px;
}


.login-card p.subtitle {

    color:
        var(
            --color-text-secondary,
            #666
        );

    font-size: 0.9rem;

    line-height: 1.5;

    margin-bottom: 28px;
}


.field-group {

    margin-bottom: 18px;
}


.row-between {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    margin-bottom: 24px;

    font-size: 0.85rem;
}


.divider {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 22px 0;

    color:
        var(
            --color-text-muted,
            #777
        );

    font-size: 0.75rem;

    text-transform: uppercase;

    letter-spacing: 0.08em;
}


.divider::before,
.divider::after {

    content: '';

    flex: 1;

    height: 1px;

    background:
        var(
            --color-border,
            #ddd
        );
}


.signup-line {

    text-align: center;

    font-size: 0.88rem;

    color:
        var(
            --color-text-secondary,
            #666
        );
}


.security-note {

    margin-top: 14px;

    text-align: center;

    font-size: 0.78rem;

    color:
        var(
            --color-text-muted,
            #777
        );
}


@media (
    max-width: 768px
) {

    .login-wrapper {

        justify-content: center;

        padding: 24px 16px;
    }


    .login-card {

        padding: 30px 22px;
    }
}

</style>

</head>


<body>


<div class="login-wrapper">


    <div class="login-bg"></div>


    <div class="login-card card">


        <img
            src="<?= e(BASE_URL) ?>assets/images/logo.jpg"
            alt="Hong Sing LKRR Commercial"
            class="brand-logo"
        >


        <h1>
            Welcome back
        </h1>


        <p class="subtitle">
            Sign in to access the Hong Sing LKRR
            Delivery Management System.
        </p>


        <?php if (
            isset($_GET['logged_out'])
        ): ?>

            <div class="alert-success">

                You've been logged out successfully.

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="alert-error">

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            autocomplete="on"
        >


            <!-- ==============================================
                 CSRF TOKEN
            =============================================== -->

            <?= csrfField() ?>


            <!-- ==============================================
                 EMAIL
            =============================================== -->

            <div class="field-group">

                <label
                    class="form-label"
                    for="identifier"
                >
                    Email
                </label>


                <input
                    type="email"
                    id="identifier"
                    name="identifier"
                    class="form-input"
                    placeholder="you@company.com"
                    value="<?= e($identifier) ?>"
                    autocomplete="username"
                    maxlength="254"
                    required
                    autofocus
                    <?= $currentlyLocked ? 'disabled' : '' ?>
                >

            </div>


            <!-- ==============================================
                 PASSWORD
            =============================================== -->

            <div class="field-group">

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
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                    <?= $currentlyLocked ? 'disabled' : '' ?>
                >

            </div>


            <!-- ==============================================
                 FORGOT PASSWORD
            =============================================== -->

            <div class="row-between">

                <a
                    href="<?= e(BASE_URL) ?>auth/forgot_password.php"
                >
                    Forgot password?
                </a>

            </div>


            <!-- ==============================================
                 SIGN IN
            =============================================== -->

            <button
                type="submit"
                class="btn-primary"
                <?= $currentlyLocked ? 'disabled' : '' ?>
            >

                <?= $currentlyLocked
                    ? 'Temporarily Locked'
                    : 'Sign In'
                ?>

            </button>


        </form>


        <p class="security-note">

            Login attempts are monitored for account security.

        </p>


        <div class="divider">
            or
        </div>


        <p class="signup-line">

            Don't have an account?

            <a
                href="<?= e(BASE_URL) ?>auth/register_customer.php"
            >
                Sign up
            </a>

        </p>


    </div>


</div>


</body>

</html>