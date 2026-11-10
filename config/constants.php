<?php

/**
 * Global constants for the Hong Sing LKRR DMS
 */


/*
|--------------------------------------------------------------------------
| LOAD LOCAL SECRETS
|--------------------------------------------------------------------------
|
| Sensitive credentials are stored separately from the main
| application configuration.
|
*/

require_once __DIR__ . '/secrets.php';

$secretsFile = __DIR__ . '/secrets.php';

if (file_exists($secretsFile)) {
    require_once $secretsFile;
}


/*
|--------------------------------------------------------------------------
| APPLICATION
|--------------------------------------------------------------------------
*/

// Base URL for local XAMPP development.
define(
    'BASE_URL',
    'http://localhost/hongsing-dms/'
);


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

define(
    'SESSION_NAME',
    'hongsing_dms_session'
);

/*
 * Session inactivity timeout.
 *
 * 7200 seconds = 2 hours
 */
define(
    'SESSION_LIFETIME',
    7200
);


/*
|--------------------------------------------------------------------------
| UPLOAD PATHS
|--------------------------------------------------------------------------
*/

define(
    'UPLOAD_DIR_ROOT',
    __DIR__ . '/../uploads/'
);

define(
    'UPLOAD_DIR_POD',
    UPLOAD_DIR_ROOT . 'proof_of_delivery/'
);

define(
    'UPLOAD_DIR_SIGNATURES',
    UPLOAD_DIR_ROOT . 'signatures/'
);


/*
|--------------------------------------------------------------------------
| PUBLIC UPLOAD URLS
|--------------------------------------------------------------------------
*/

define(
    'UPLOAD_URL_POD',
    BASE_URL . 'uploads/proof_of_delivery/'
);

define(
    'UPLOAD_URL_SIGNATURES',
    BASE_URL . 'uploads/signatures/'
);


/*
|--------------------------------------------------------------------------
| USER ROLES
|--------------------------------------------------------------------------
*/

define(
    'ROLE_SUPER_ADMIN',
    'Super Admin'
);

define(
    'ROLE_ADMIN',
    'Admin'
);

define(
    'ROLE_DRIVER',
    'Driver'
);

define(
    'ROLE_CUSTOMER',
    'Customer'
);


/*
|--------------------------------------------------------------------------
| ROLE DASHBOARDS
|--------------------------------------------------------------------------
*/

define('ROLE_DASHBOARDS', [

    ROLE_SUPER_ADMIN =>
        BASE_URL . 'super_admin/dashboard.php',

    ROLE_ADMIN =>
        BASE_URL . 'admin/dashboard.php',

    ROLE_DRIVER =>
        BASE_URL . 'driver/dashboard.php',

    ROLE_CUSTOMER =>
        BASE_URL . 'customer/dashboard.php',

]);


/*
|--------------------------------------------------------------------------
| MACHINE LEARNING SERVICE
|--------------------------------------------------------------------------
*/

define(
    'ML_SERVICE_URL',
    'http://localhost:5000'
);


/*
|--------------------------------------------------------------------------
| TEXTBEE SMS CONFIGURATION
|--------------------------------------------------------------------------
*/

define(
    'TEXTBEE_API_URL',
    'https://api.textbee.dev/api/v1/gateway/send-sms'
);

/*
 * Actual API key comes from config/secrets.php.
 */
define(
    'TEXTBEE_API_KEY',
    defined('LOCAL_TEXTBEE_API_KEY')
        ? LOCAL_TEXTBEE_API_KEY
        : ''
);


/*
|--------------------------------------------------------------------------
| PHONE OTP SECURITY
|--------------------------------------------------------------------------
*/

define(
    'PHONE_OTP_EXPIRY_MINUTES',
    10
);

define(
    'PHONE_OTP_RESEND_SECONDS',
    60
);

define(
    'PHONE_OTP_MAX_ATTEMPTS',
    5
);


/*
|--------------------------------------------------------------------------
| EMAIL / GMAIL SMTP
|--------------------------------------------------------------------------
*/

define(
    'MAIL_HOST',
    'smtp.gmail.com'
);

define(
    'MAIL_PORT',
    587
);

define(
    'MAIL_USERNAME',
    defined('LOCAL_MAIL_USERNAME')
        ? LOCAL_MAIL_USERNAME
        : ''
);

define(
    'MAIL_PASSWORD',
    defined('LOCAL_MAIL_PASSWORD')
        ? LOCAL_MAIL_PASSWORD
        : ''
);

define(
    'MAIL_FROM_EMAIL',
    MAIL_USERNAME
);

define(
    'MAIL_FROM_NAME',
    'Hong Sing LKRR Commercial'
);


/*
|--------------------------------------------------------------------------
| EMAIL OTP SECURITY
|--------------------------------------------------------------------------
*/

define(
    'EMAIL_OTP_EXPIRY_MINUTES',
    10
);

define(
    'EMAIL_OTP_RESEND_SECONDS',
    60
);

define(
    'EMAIL_OTP_MAX_ATTEMPTS',
    5
);


/*
|--------------------------------------------------------------------------
| LOGIN SECURITY
|--------------------------------------------------------------------------
|
| These constants will be used for login brute-force protection.
|
*/

define(
    'LOGIN_MAX_ATTEMPTS',
    5
);

/*
 * 900 seconds = 15 minutes
 */
define(
    'LOGIN_LOCKOUT_SECONDS',
    900
);


/*
|--------------------------------------------------------------------------
| APPLICATION ENVIRONMENT
|--------------------------------------------------------------------------
|
| development = local XAMPP
| production  = deployed/live server
|
*/

define(
    'APP_ENV',
    'development'
);


/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);

if (APP_ENV === 'production') {

    /*
     * Do not expose PHP, SQL, filesystem,
     * or database errors to public users.
     */

    ini_set(
        'display_errors',
        '0'
    );

    ini_set(
        'log_errors',
        '1'
    );

} else {

    /*
     * Development mode.
     *
     * Show errors while the system is still
     * being developed and tested locally.
     */

    ini_set(
        'display_errors',
        '1'
    );

    ini_set(
        'log_errors',
        '1'
    );
}