<?php
/**
 * Admin Profile
 * Hong Sing LKRR Commercial
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

$adminId = currentUserId();

if (!$adminId) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=session_expired');
    exit;
}

/* =========================================================
   HELPERS
   ========================================================= */

function profileRedirect(string $type, string $message): void
{
    header(
        'Location: ' .
        BASE_URL .
        'admin/profile.php?' .
        $type .
        '=' .
        urlencode($message)
    );
    exit;
}

function oldValue(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   GET ADMIN INFORMATION
   ========================================================= */

$stmt = $db->prepare("
    SELECT
        user_id,
        full_name,
        email,
        phone_number,
        password_hash,
        role,
        created_at
    FROM system_user
    WHERE user_id = ?
      AND role = 'Admin'
    LIMIT 1
");

$stmt->execute([$adminId]);

$admin = $stmt->fetch();

if (!$admin) {
    logoutUser();

    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php?error=account_not_found'
    );
    exit;
}

/* =========================================================
   HANDLE PROFILE UPDATE
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* =====================================================
       UPDATE PROFILE INFORMATION
       ===================================================== */

    if ($action === 'update_profile') {

        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');

        /* -----------------------------
           Validation
        ----------------------------- */

        if ($fullName === '') {
            profileRedirect(
                'error',
                'Full name is required.'
            );
        }

        if (mb_strlen($fullName) > 100) {
            profileRedirect(
                'error',
                'Full name must not exceed 100 characters.'
            );
        }

        if ($email === '') {
            profileRedirect(
                'error',
                'Email address is required.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            profileRedirect(
                'error',
                'Please enter a valid email address.'
            );
        }

        if (mb_strlen($email) > 100) {
            profileRedirect(
                'error',
                'Email address must not exceed 100 characters.'
            );
        }

        if ($phoneNumber !== '') {

            /*
             * Allow:
             * 09XXXXXXXXX
             * +639XXXXXXXXX
             * 639XXXXXXXXX
             * spaces, dashes, parentheses
             */

            $phoneDigits = preg_replace('/[^0-9+]/', '', $phoneNumber);

            if (
                !preg_match(
                    '/^(09\d{9}|\+639\d{9}|639\d{9})$/',
                    $phoneDigits
                )
            ) {
                profileRedirect(
                    'error',
                    'Please enter a valid Philippine phone number.'
                );
            }

            $phoneNumber = $phoneDigits;
        } else {
            $phoneNumber = null;
        }

        /* -----------------------------
           Check duplicate email
        ----------------------------- */

        $stmt = $db->prepare("
            SELECT user_id
            FROM system_user
            WHERE email = ?
              AND user_id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $email,
            $adminId
        ]);

        if ($stmt->fetch()) {
            profileRedirect(
                'error',
                'That email address is already being used by another account.'
            );
        }

        /* -----------------------------
           Update profile
        ----------------------------- */

        try {

            $stmt = $db->prepare("
                UPDATE system_user
                SET
                    full_name = ?,
                    email = ?,
                    phone_number = ?
                WHERE user_id = ?
                  AND role = 'Admin'
            ");

            $stmt->execute([
                $fullName,
                $email,
                $phoneNumber,
                $adminId
            ]);

            /*
             * Update session name so sidebar/header
             * immediately displays the new name.
             */
            $_SESSION['full_name'] = $fullName;

            profileRedirect(
                'success',
                'Your profile information has been updated successfully.'
            );

        } catch (PDOException $e) {

            profileRedirect(
                'error',
                'Unable to update your profile. Please try again.'
            );
        }
    }

    /* =====================================================
       CHANGE PASSWORD
       ===================================================== */

    if ($action === 'change_password') {

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        /* -----------------------------
           Required fields
        ----------------------------- */

        if (
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            profileRedirect(
                'error',
                'Please complete all password fields.'
            );
        }

        /* -----------------------------
           Verify current password
        ----------------------------- */

        if (!password_verify(
            $currentPassword,
            $admin['password_hash']
        )) {

            profileRedirect(
                'error',
                'Your current password is incorrect.'
            );
        }

        /* -----------------------------
           Password length
        ----------------------------- */

        if (strlen($newPassword) < 8) {
            profileRedirect(
                'error',
                'New password must be at least 8 characters long.'
            );
        }

        /* -----------------------------
           Confirm password
        ----------------------------- */

        if ($newPassword !== $confirmPassword) {
            profileRedirect(
                'error',
                'New password and confirmation password do not match.'
            );
        }

        /* -----------------------------
           Prevent same password
        ----------------------------- */

        if (password_verify(
            $newPassword,
            $admin['password_hash']
        )) {

            profileRedirect(
                'error',
                'Your new password must be different from your current password.'
            );
        }

        /* -----------------------------
           Hash password
        ----------------------------- */

        $newPasswordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        if ($newPasswordHash === false) {
            profileRedirect(
                'error',
                'Unable to secure the new password. Please try again.'
            );
        }

        /* -----------------------------
           Update password
        ----------------------------- */

        try {

            $stmt = $db->prepare("
                UPDATE system_user
                SET password_hash = ?
                WHERE user_id = ?
                  AND role = 'Admin'
            ");

            $stmt->execute([
                $newPasswordHash,
                $adminId
            ]);

            profileRedirect(
                'success',
                'Your password has been changed successfully.'
            );

        } catch (PDOException $e) {

            profileRedirect(
                'error',
                'Unable to change your password. Please try again.'
            );
        }
    }
}

/* =========================================================
   REFRESH ADMIN DATA AFTER UPDATE
   ========================================================= */

$stmt = $db->prepare("
    SELECT
        user_id,
        full_name,
        email,
        phone_number,
        role,
        created_at
    FROM system_user
    WHERE user_id = ?
      AND role = 'Admin'
    LIMIT 1
");

$stmt->execute([$adminId]);

$admin = $stmt->fetch();

if (!$admin) {
    logoutUser();

    header(
        'Location: ' .
        BASE_URL .
        'auth/login.php?error=account_not_found'
    );
    exit;
}

/* =========================================================
   PAGE DATA
   ========================================================= */

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

$initial = strtoupper(
    mb_substr(
        trim($admin['full_name']),
        0,
        1
    )
);

$memberSince = !empty($admin['created_at'])
    ? date('F d, Y', strtotime($admin['created_at']))
    : '—';

$phoneDisplay = $admin['phone_number'] ?? '';

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="app-main">

    <style>
        /* =====================================================
           ADMIN PROFILE PAGE
        ===================================================== */

        .profile-page {
            width: 100%;
        }

        /* -----------------------------------------------------
           Header
        ----------------------------------------------------- */

        .profile-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }

        .profile-page-title {
            margin: 0;
            font-size: 30px;
            font-weight: 800;
            color: var(--color-text-primary);
            letter-spacing: -0.5px;
        }

        .profile-page-subtitle {
            margin: 7px 0 0;
            color: var(--color-text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        /* -----------------------------------------------------
           Alerts
        ----------------------------------------------------- */

        .profile-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 17px;
            border-radius: var(--radius-md);
            margin-bottom: 22px;
            border: 1px solid transparent;
            font-size: 14px;
            line-height: 1.5;
        }

        .profile-alert-success {
            background: rgba(34, 197, 94, 0.10);
            border-color: rgba(34, 197, 94, 0.25);
            color: var(--color-success);
        }

        .profile-alert-error {
            background: rgba(239, 68, 68, 0.10);
            border-color: rgba(239, 68, 68, 0.25);
            color: var(--color-danger);
        }

        .profile-alert-icon {
            width: 22px;
            height: 22px;
            flex: 0 0 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        /* -----------------------------------------------------
           Main grid
        ----------------------------------------------------- */

        .profile-top-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(300px, 0.85fr);
            gap: 24px;
            margin-bottom: 24px;
        }

        .profile-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .profile-card-header {
            padding: 22px 24px;
            border-bottom: 1px solid var(--color-border);
        }

        .profile-card-title {
            margin: 0;
            color: var(--color-text-primary);
            font-size: 17px;
            font-weight: 750;
        }

        .profile-card-description {
            margin: 5px 0 0;
            color: var(--color-text-secondary);
            font-size: 13px;
            line-height: 1.5;
        }

        .profile-card-body {
            padding: 24px;
        }

        /* -----------------------------------------------------
           Form
        ----------------------------------------------------- */

        .profile-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .profile-field-full {
            grid-column: 1 / -1;
        }

        .profile-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .profile-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--color-text-primary);
        }

        .profile-required {
            color: var(--color-danger);
        }

        .profile-input {
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: var(--color-bg);
            color: var(--color-text-primary);
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .profile-input::placeholder {
            color: var(--color-text-muted);
        }

        .profile-input:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px var(--color-accent-soft);
            background: var(--color-surface);
        }

        .profile-help {
            margin: 0;
            color: var(--color-text-muted);
            font-size: 12px;
            line-height: 1.4;
        }

        /* -----------------------------------------------------
           Buttons
        ----------------------------------------------------- */

        .profile-form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--color-border);
        }

        .profile-btn {
            min-height: 42px;
            padding: 10px 17px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.15s ease;
        }

        .profile-btn:hover {
            transform: translateY(-1px);
        }

        .profile-btn-primary {
            background: var(--color-accent);
            color: #ffffff;
        }

        .profile-btn-primary:hover {
            background: var(--color-accent-hover);
        }

        /* -----------------------------------------------------
           Account summary
        ----------------------------------------------------- */

        .account-summary {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .account-avatar {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-accent-soft);
            color: var(--color-accent);
            border: 4px solid var(--color-surface);
            box-shadow: 0 0 0 1px var(--color-border);
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .account-name {
            margin: 0;
            color: var(--color-text-primary);
            font-size: 20px;
            font-weight: 800;
        }

        .account-email {
            margin: 5px 0 0;
            color: var(--color-text-secondary);
            font-size: 13px;
            word-break: break-word;
        }

        .account-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
            padding: 6px 11px;
            border-radius: 999px;
            background: var(--color-accent-soft);
            color: var(--color-accent);
            font-size: 12px;
            font-weight: 750;
        }

        .account-details {
            width: 100%;
            margin-top: 24px;
            border-top: 1px solid var(--color-border);
        }

        .account-detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 14px 0;
            border-bottom: 1px solid var(--color-border);
            text-align: left;
        }

        .account-detail-row:last-child {
            border-bottom: 0;
        }

        .account-detail-label {
            color: var(--color-text-muted);
            font-size: 12px;
            font-weight: 650;
        }

        .account-detail-value {
            color: var(--color-text-primary);
            font-size: 13px;
            font-weight: 700;
            text-align: right;
            word-break: break-word;
        }

        .account-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--color-success);
        }

        .account-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--color-success);
        }

        /* -----------------------------------------------------
           Security section
        ----------------------------------------------------- */

        .security-card {
            margin-bottom: 24px;
        }

        .security-intro {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            margin-bottom: 22px;
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
        }

        .security-icon {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--color-accent-soft);
            color: var(--color-accent);
            font-size: 19px;
        }

        .security-intro-title {
            margin: 0;
            color: var(--color-text-primary);
            font-size: 14px;
            font-weight: 750;
        }

        .security-intro-text {
            margin: 3px 0 0;
            color: var(--color-text-secondary);
            font-size: 12px;
            line-height: 1.5;
        }

        .password-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .profile-input {
            padding-right: 46px;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            bottom: 8px;
            width: 30px;
            height: 30px;
            border: 0;
            background: transparent;
            color: var(--color-text-muted);
            cursor: pointer;
            border-radius: 6px;
            font-size: 15px;
        }

        .password-toggle:hover {
            background: var(--color-surface-alt);
            color: var(--color-text-primary);
        }

        .password-requirements {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: var(--radius-md);
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
        }

        .password-requirements-title {
            margin: 0 0 7px;
            color: var(--color-text-primary);
            font-size: 12px;
            font-weight: 750;
        }

        .password-requirements-list {
            margin: 0;
            padding-left: 18px;
            color: var(--color-text-secondary);
            font-size: 12px;
            line-height: 1.7;
        }

        /* -----------------------------------------------------
           Bottom info
        ----------------------------------------------------- */

        .profile-security-note {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text-secondary);
            font-size: 12px;
            line-height: 1.6;
        }

        .profile-security-note-icon {
            color: var(--color-info);
            font-weight: 800;
            font-size: 15px;
        }

        /* -----------------------------------------------------
           Responsive
        ----------------------------------------------------- */

        @media (max-width: 1050px) {

            .profile-top-grid {
                grid-template-columns: 1fr;
            }

            .password-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {

            .profile-page-header {
                flex-direction: column;
                gap: 12px;
            }

            .profile-page-title {
                font-size: 25px;
            }

            .profile-form-grid {
                grid-template-columns: 1fr;
            }

            .profile-field-full {
                grid-column: auto;
            }

            .profile-card-header,
            .profile-card-body {
                padding: 18px;
            }

            .profile-form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .profile-btn {
                width: 100%;
            }

            .account-detail-row {
                align-items: flex-start;
                flex-direction: column;
                gap: 4px;
            }

            .account-detail-value {
                text-align: left;
            }
        }
    </style>

    <div class="profile-page">

        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <header class="profile-page-header">

            <div>
                <h1 class="profile-page-title">
                    Admin Profile
                </h1>

                <p class="profile-page-subtitle">
                    Manage your administrator account information
                    and security settings.
                </p>
            </div>

        </header>

        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if ($successMessage !== ''): ?>

            <div class="profile-alert profile-alert-success">
                <div class="profile-alert-icon">
                    ✓
                </div>

                <div>
                    <?= oldValue($successMessage) ?>
                </div>
            </div>

        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>

            <div class="profile-alert profile-alert-error">
                <div class="profile-alert-icon">
                    !
                </div>

                <div>
                    <?= oldValue($errorMessage) ?>
                </div>
            </div>

        <?php endif; ?>

        <!-- =================================================
             PROFILE + ACCOUNT SUMMARY
        ================================================== -->

        <div class="profile-top-grid">

            <!-- =============================================
                 PROFILE INFORMATION
            ============================================== -->

            <section class="profile-card">

                <div class="profile-card-header">

                    <h2 class="profile-card-title">
                        Profile Information
                    </h2>

                    <p class="profile-card-description">
                        Update the personal information associated
                        with your administrator account.
                    </p>

                </div>

                <div class="profile-card-body">

                    <form
                        method="POST"
                        action="<?= BASE_URL ?>admin/profile.php"
                        autocomplete="off"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >

                        <div class="profile-form-grid">

                            <!-- Full Name -->

                            <div class="profile-field profile-field-full">

                                <label
                                    for="full_name"
                                    class="profile-label"
                                >
                                    Full Name
                                    <span class="profile-required">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    class="profile-input"
                                    value="<?= oldValue($admin['full_name']) ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <!-- Email -->

                            <div class="profile-field">

                                <label
                                    for="email"
                                    class="profile-label"
                                >
                                    Email Address
                                    <span class="profile-required">*</span>
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="profile-input"
                                    value="<?= oldValue($admin['email']) ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <!-- Phone -->

                            <div class="profile-field">

                                <label
                                    for="phone_number"
                                    class="profile-label"
                                >
                                    Phone Number
                                </label>

                                <input
                                    type="tel"
                                    id="phone_number"
                                    name="phone_number"
                                    class="profile-input"
                                    value="<?= oldValue($phoneDisplay) ?>"
                                    placeholder="09XXXXXXXXX"
                                    maxlength="20"
                                >

                                <p class="profile-help">
                                    Example: 09171234567 or +639171234567
                                </p>

                            </div>

                        </div>

                        <div class="profile-form-actions">

                            <button
                                type="submit"
                                class="profile-btn profile-btn-primary"
                            >
                                Save Profile Changes
                            </button>

                        </div>

                    </form>

                </div>

            </section>

            <!-- =============================================
                 ACCOUNT SUMMARY
            ============================================== -->

            <section class="profile-card">

                <div class="profile-card-header">

                    <h2 class="profile-card-title">
                        Account Overview
                    </h2>

                    <p class="profile-card-description">
                        Your administrator account details.
                    </p>

                </div>

                <div class="profile-card-body">

                    <div class="account-summary">

                        <div class="account-avatar">
                            <?= oldValue($initial) ?>
                        </div>

                        <h3 class="account-name">
                            <?= oldValue($admin['full_name']) ?>
                        </h3>

                        <p class="account-email">
                            <?= oldValue($admin['email']) ?>
                        </p>

                        <div class="account-role-badge">
                            🛡
                            Administrator
                        </div>

                        <div class="account-details">

                            <div class="account-detail-row">

                                <span class="account-detail-label">
                                    Account Status
                                </span>

                                <span class="account-detail-value account-status">
                                    <span class="account-status-dot"></span>
                                    Active
                                </span>

                            </div>

                            <div class="account-detail-row">

                                <span class="account-detail-label">
                                    Role
                                </span>

                                <span class="account-detail-value">
                                    <?= oldValue($admin['role']) ?>
                                </span>

                            </div>

                            <div class="account-detail-row">

                                <span class="account-detail-label">
                                    Member Since
                                </span>

                                <span class="account-detail-value">
                                    <?= oldValue($memberSince) ?>
                                </span>

                            </div>

                            <?php if (!empty($admin['phone_number'])): ?>

                                <div class="account-detail-row">

                                    <span class="account-detail-label">
                                        Phone
                                    </span>

                                    <span class="account-detail-value">
                                        <?= oldValue($admin['phone_number']) ?>
                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </section>

        </div>

        <!-- =================================================
             CHANGE PASSWORD
        ================================================== -->

        <section class="profile-card security-card">

            <div class="profile-card-header">

                <h2 class="profile-card-title">
                    Change Password
                </h2>

                <p class="profile-card-description">
                    Keep your administrator account secure by using
                    a strong and unique password.
                </p>

            </div>

            <div class="profile-card-body">

                <div class="security-intro">

                    <div class="security-icon">
                        🔒
                    </div>

                    <div>

                        <h3 class="security-intro-title">
                            Password Security
                        </h3>

                        <p class="security-intro-text">
                            Your password is securely stored using
                            PHP password hashing. Never share your
                            administrator password with anyone.
                        </p>

                    </div>

                </div>

                <form
                    method="POST"
                    action="<?= BASE_URL ?>admin/profile.php"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="change_password"
                    >

                    <div class="password-grid">

                        <!-- Current Password -->

                        <div class="profile-field">

                            <label
                                for="current_password"
                                class="profile-label"
                            >
                                Current Password
                                <span class="profile-required">*</span>
                            </label>

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    class="profile-input"
                                    autocomplete="current-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="current_password"
                                    aria-label="Show current password"
                                    title="Show password"
                                >
                                    👁
                                </button>

                            </div>

                        </div>

                        <!-- New Password -->

                        <div class="profile-field">

                            <label
                                for="new_password"
                                class="profile-label"
                            >
                                New Password
                                <span class="profile-required">*</span>
                            </label>

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    class="profile-input"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="new_password"
                                    aria-label="Show new password"
                                    title="Show password"
                                >
                                    👁
                                </button>

                            </div>

                        </div>

                        <!-- Confirm Password -->

                        <div class="profile-field">

                            <label
                                for="confirm_password"
                                class="profile-label"
                            >
                                Confirm New Password
                                <span class="profile-required">*</span>
                            </label>

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    class="profile-input"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="confirm_password"
                                    aria-label="Show confirm password"
                                    title="Show password"
                                >
                                    👁
                                </button>

                            </div>

                        </div>

                    </div>

                    <div class="password-requirements">

                        <p class="password-requirements-title">
                            Password Requirements
                        </p>

                        <ul class="password-requirements-list">

                            <li>
                                Must contain at least 8 characters.
                            </li>

                            <li>
                                Use a password that is difficult
                                for others to guess.
                            </li>

                            <li>
                                Do not reuse your administrator
                                password on other websites.
                            </li>

                        </ul>

                    </div>

                    <div class="profile-form-actions">

                        <button
                            type="submit"
                            class="profile-btn profile-btn-primary"
                        >
                            Change Password
                        </button>

                    </div>

                </form>

            </div>

        </section>

        <!-- =================================================
             SECURITY NOTE
        ================================================== -->

        <div class="profile-security-note">

            <div class="profile-security-note-icon">
                🔐
            </div>

            <div>
                <strong>
                    Account Security:
                </strong>

                Changes to your profile are applied immediately.
                Your password is never stored as plain text and is
                protected using PHP's secure password hashing system.
            </div>

        </div>

    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
     * Show / hide password fields
     */

    const passwordToggles =
        document.querySelectorAll('.password-toggle');

    passwordToggles.forEach(function (button) {

        button.addEventListener('click', function () {

            const targetId =
                button.getAttribute('data-target');

            const input =
                document.getElementById(targetId);

            if (!input) {
                return;
            }

            if (input.type === 'password') {

                input.type = 'text';

                button.textContent = '🙈';

                button.setAttribute(
                    'aria-label',
                    'Hide password'
                );

                button.setAttribute(
                    'title',
                    'Hide password'
                );

            } else {

                input.type = 'password';

                button.textContent = '👁';

                button.setAttribute(
                    'aria-label',
                    'Show password'
                );

                button.setAttribute(
                    'title',
                    'Show password'
                );
            }
        });
    });

    /*
     * Client-side password confirmation
     */

    const passwordForm =
        document.querySelector(
            'form input[name="action"][value="change_password"]'
        )?.closest('form');

    if (passwordForm) {

        passwordForm.addEventListener('submit', function (event) {

            const newPassword =
                document.getElementById('new_password');

            const confirmPassword =
                document.getElementById('confirm_password');

            if (!newPassword || !confirmPassword) {
                return;
            }

            if (newPassword.value !== confirmPassword.value) {

                event.preventDefault();

                alert(
                    'New password and confirmation password do not match.'
                );

                confirmPassword.focus();

                return;
            }

            if (newPassword.value.length < 8) {

                event.preventDefault();

                alert(
                    'New password must be at least 8 characters long.'
                );

                newPassword.focus();
            }
        });
    }

    /*
     * Automatically hide URL alerts after a few seconds.
     */

    const alerts =
        document.querySelectorAll('.profile-alert');

    if (alerts.length) {

        setTimeout(function () {

            alerts.forEach(function (alert) {

                alert.style.transition =
                    'opacity 0.3s ease';

                alert.style.opacity = '0';

                setTimeout(function () {

                    alert.remove();

                }, 300);

            });

        }, 5000);
    }

});
</script>