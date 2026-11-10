<?php

/**
 * Session management and role-based access guards.
 * Include this at the top of every protected page.
 */

require_once __DIR__ . '/../config/constants.php';


/* ==========================================================
   SECURE SESSION CONFIGURATION
========================================================== */

if (session_status() === PHP_SESSION_NONE) {

    /*
     * Reject uninitialized session IDs supplied by clients.
     */
    ini_set('session.use_strict_mode', '1');

    /*
     * Sessions should only use cookies.
     */
    ini_set('session.use_only_cookies', '1');

    /*
     * Prevent JavaScript from reading the session cookie.
     */
    ini_set('session.cookie_httponly', '1');

    /*
     * Determine whether the current request uses HTTPS.
     *
     * Local XAMPP normally uses HTTP, so Secure will be false.
     * When deployed through HTTPS, it automatically becomes true.
     */
    $isHttps =
        (!empty($_SERVER['HTTPS']) &&
            strtolower((string) $_SERVER['HTTPS']) !== 'off')
        ||
        ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_name(SESSION_NAME);

    session_set_cookie_params([
        /*
         * Browser cookie lifetime.
         *
         * SESSION_LIFETIME currently equals 7200 seconds.
         */
        'lifetime' => SESSION_LIFETIME,

        /*
         * Cookie is available throughout the application.
         */
        'path' => '/',

        /*
         * Empty domain means current host only.
         */
        'domain' => '',

        /*
         * HTTPS-only in production when HTTPS is detected.
         */
        'secure' => $isHttps,

        /*
         * Prevent JavaScript access to the session cookie.
         */
        'httponly' => true,

        /*
         * Helps protect against cross-site requests while
         * preserving normal navigation.
         */
        'samesite' => 'Lax',
    ]);

    session_start();
}


/* ==========================================================
   LOGIN USER
========================================================== */

/**
 * Log a user in after credentials have already been verified.
 */
function loginUser(
    int $userId,
    string $fullName,
    string $role
): void {

    /*
     * Prevent session fixation.
     */
    session_regenerate_id(true);

    $_SESSION['user_id'] =
        $userId;

    $_SESSION['full_name'] =
        $fullName;

    $_SESSION['role'] =
        $role;

    /*
     * Keep the original login timestamp for auditing/reference.
     */
    $_SESSION['logged_in_at'] =
        time();

    /*
     * Used for inactivity timeout.
     */
    $_SESSION['last_activity'] =
        time();

    /*
     * Regenerate CSRF token after authentication.
     *
     * This prevents carrying an anonymous-session token into
     * the authenticated session.
     */
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}


/* ==========================================================
   LOGOUT USER
========================================================== */

/**
 * Completely destroy the authenticated session.
 */
function logoutUser(): void
{
    /*
     * Clear session variables.
     */
    $_SESSION = [];

    /*
     * Remove session cookie.
     */
    if (ini_get('session.use_cookies')) {

        $params =
            session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    /*
     * Destroy server-side session data.
     */
    if (
        session_status() ===
        PHP_SESSION_ACTIVE
    ) {

        session_destroy();
    }
}


/* ==========================================================
   LOGIN CHECK
========================================================== */

/**
 * Determine whether a valid authenticated session exists.
 */
function isLoggedIn(): bool
{
    return isset(
        $_SESSION['user_id'],
        $_SESSION['role']
    );
}


/* ==========================================================
   CURRENT ROLE
========================================================== */

function currentRole(): ?string
{
    return
        $_SESSION['role']
        ?? null;
}


/* ==========================================================
   CURRENT USER ID
========================================================== */

function currentUserId(): ?int
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}


/* ==========================================================
   CURRENT USER NAME
========================================================== */

function currentUserName(): string
{
    return
        $_SESSION['full_name']
        ?? 'Unknown User';
}


/* ==========================================================
   CURRENT USER ROLE
========================================================== */

function currentUserRole(): string
{
    return
        currentRole()
        ?? 'Unknown';
}


/* ==========================================================
   SESSION INACTIVITY TIMEOUT
========================================================== */

/**
 * Expire authenticated sessions after SESSION_LIFETIME
 * seconds without activity.
 */
function enforceSessionTimeout(): void
{
    if (!isLoggedIn()) {
        return;
    }

    $now =
        time();

    /*
     * Support sessions created before this updated auth.php
     * was installed.
     */
    if (!isset($_SESSION['last_activity'])) {

        $_SESSION['last_activity'] =
            $_SESSION['logged_in_at']
            ?? $now;
    }

    $lastActivity =
        (int) $_SESSION['last_activity'];

    /*
     * User has been inactive for too long.
     */
    if (
        ($now - $lastActivity)
        > SESSION_LIFETIME
    ) {

        logoutUser();

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php?error=session_expired'
        );

        exit;
    }

    /*
     * User is active.
     *
     * Refresh inactivity timestamp.
     */
    $_SESSION['last_activity'] =
        $now;
}


/* ==========================================================
   CSRF PROTECTION
========================================================== */

/**
 * Return the CSRF token for the current session.
 *
 * A cryptographically secure token is generated when one
 * does not already exist.
 */
function csrfToken(): string
{
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/**
 * Verify a submitted CSRF token.
 */
function verifyCsrfToken(
    ?string $token
): bool {

    if (
        $token === null ||
        $token === '' ||
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {

        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/**
 * Require a valid CSRF token.
 *
 * Use this for state-changing POST requests.
 */
function requireCsrfToken(
    ?string $token = null
): void {

    if ($token === null) {

        $token =
            isset($_POST['csrf_token'])
                ? (string) $_POST['csrf_token']
                : null;
    }

    if (!verifyCsrfToken($token)) {

        http_response_code(403);

        exit(
            'Invalid or expired request. ' .
            'Please refresh the page and try again.'
        );
    }
}


/**
 * Generate a ready-to-use hidden CSRF form field.
 */
function csrfField(): string
{
    return
        '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(
            csrfToken(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) .
        '">';
}


/* ==========================================================
   OUTPUT ESCAPING
========================================================== */

/**
 * Safely encode plain text for HTML output.
 *
 * Example:
 *
 * <?= e($customer['full_name']) ?>
 */
function e(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* ==========================================================
   INPUT CLEANING
========================================================== */

/**
 * Basic normalization for plain text form input.
 *
 * This does NOT replace:
 *
 * - PDO prepared statements for SQL
 * - e() for HTML output
 * - specific validation for email/numbers/etc.
 */
function cleanInput(
    string $input
): string {

    return trim($input);
}


/**
 * Legacy helper.
 *
 * Kept so existing pages using sanitize() do not suddenly break.
 *
 * New code should normally use:
 *
 * cleanInput() when receiving text
 * e() when displaying text
 */
function sanitize(
    string $input
): string {

    return htmlspecialchars(
        trim($input),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* ==========================================================
   ADMIN STATUS ENFORCEMENT
========================================================== */

/**
 * Verify that an already logged-in Admin is still Active.
 *
 * This prevents an old Admin session from remaining usable
 * after the Super Admin changes the account's status.
 */
function enforceAdminStatus(): void
{
    /*
     * Only normal Admin accounts use this check.
     */
    if (currentRole() !== ROLE_ADMIN) {
        return;
    }

    require_once
        __DIR__ .
        '/../config/database.php';

    try {

        $db =
            Database::getConnection();

        $stmt = $db->prepare("
            SELECT
                status
            FROM system_user
            WHERE user_id = ?
              AND role = ?
            LIMIT 1
        ");

        $stmt->execute([
            currentUserId(),
            ROLE_ADMIN
        ]);

        $admin =
            $stmt->fetch();

        /*
         * Account no longer exists.
         */
        if (!$admin) {

            logoutUser();

            header(
                'Location: ' .
                BASE_URL .
                'auth/login.php?error=account_unavailable'
            );

            exit;
        }

        $status =
            $admin['status']
            ?? '';

        /*
         * Active Admin can continue.
         */
        if ($status === 'Active') {
            return;
        }

        switch ($status) {

            case 'On Leave':

                $errorCode =
                    'on_leave';

                break;


            case 'Absent':

                $errorCode =
                    'absent';

                break;


            case 'Resigned':

                $errorCode =
                    'resigned';

                break;


            case 'Terminated':

                $errorCode =
                    'terminated';

                break;


            default:

                /*
                 * Unknown status:
                 * fail closed.
                 */
                $errorCode =
                    'account_unavailable';

                break;
        }

        logoutUser();

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php?error=' .
            urlencode($errorCode)
        );

        exit;

    } catch (Throwable $e) {

        /*
         * Do not grant access when account status
         * cannot be verified.
         */

        error_log(
            'Admin status verification failed: ' .
            $e->getMessage()
        );

        logoutUser();

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php?error=account_verification_failed'
        );

        exit;
    }
}


/* ==========================================================
   DRIVER STATUS ENFORCEMENT
========================================================== */

/**
 * Verify that a logged-in Driver still has permission
 * to access Driver pages.
 *
 * Allowed:
 *
 * - Available
 * - On Delivery
 *
 * Blocked:
 *
 * - On Leave
 * - Absent
 * - Inactive
 * - Resigned
 * - Terminated
 */
function enforceDriverStatus(): void
{
    if (currentRole() !== ROLE_DRIVER) {
        return;
    }

    require_once
        __DIR__ .
        '/../config/database.php';

    try {

        $db =
            Database::getConnection();

        $stmt = $db->prepare("
            SELECT
                status
            FROM driver
            WHERE driver_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            currentUserId()
        ]);

        $driver =
            $stmt->fetch();

        /*
         * Driver record no longer exists.
         */
        if (!$driver) {

            logoutUser();

            header(
                'Location: ' .
                BASE_URL .
                'auth/login.php?error=driver_unavailable'
            );

            exit;
        }

        $status =
            $driver['status']
            ?? '';

        /*
         * Driver is permitted to use the system.
         */
        if (
            $status === 'Available' ||
            $status === 'On Delivery'
        ) {

            return;
        }

        switch ($status) {

            case 'On Leave':

                $errorCode =
                    'driver_on_leave';

                break;


            case 'Absent':

                $errorCode =
                    'driver_absent';

                break;


            case 'Inactive':

                $errorCode =
                    'driver_inactive';

                break;


            case 'Resigned':

                $errorCode =
                    'driver_resigned';

                break;


            case 'Terminated':

                $errorCode =
                    'driver_terminated';

                break;


            default:

                /*
                 * Unknown Driver status:
                 * fail closed.
                 */
                $errorCode =
                    'driver_unavailable';

                break;
        }

        logoutUser();

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php?error=' .
            urlencode($errorCode)
        );

        exit;

    } catch (Throwable $e) {

        error_log(
            'Driver status verification failed: ' .
            $e->getMessage()
        );

        logoutUser();

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php?error=account_verification_failed'
        );

        exit;
    }
}


/* ==========================================================
   ROLE-BASED PAGE PROTECTION
========================================================== */

/**
 * Restrict a page to one or more roles.
 *
 * Examples:
 *
 * requireRole(ROLE_ADMIN);
 *
 * requireRole([
 *     ROLE_ADMIN,
 *     ROLE_SUPER_ADMIN
 * ]);
 */
function requireRole(
    $allowedRoles
): void {

    /*
     * 1. Authentication
     */
    if (!isLoggedIn()) {

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php'
        );

        exit;
    }


    /*
     * 2. Inactivity timeout
     */
    enforceSessionTimeout();


    /*
     * 3. Verify Admin account status.
     */
    enforceAdminStatus();


    /*
     * 4. Verify Driver account status.
     */
    enforceDriverStatus();


    /*
     * 5. Normalize roles.
     */
    if (is_string($allowedRoles)) {

        $allowedRoles = [
            $allowedRoles
        ];
    }


    /*
     * Invalid authorization configuration:
     * fail closed.
     */
    if (
        !is_array($allowedRoles) ||
        empty($allowedRoles)
    ) {

        logoutUser();

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php?error=unauthorized'
        );

        exit;
    }


    /*
     * 6. Role authorization.
     */
    if (
        !in_array(
            currentRole(),
            $allowedRoles,
            true
        )
    ) {

        $dashboards =
            ROLE_DASHBOARDS;

        $fallback =
            $dashboards[currentRole()]
            ??
            (
                BASE_URL .
                'auth/login.php'
            );

        /*
         * Correctly append query parameter whether or
         * not the fallback already contains ?.
         */
        $separator =
            strpos(
                $fallback,
                '?'
            ) !== false
                ? '&'
                : '?';

        header(
            'Location: ' .
            $fallback .
            $separator .
            'error=unauthorized'
        );

        exit;
    }
}