<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();

$customerId = currentUserId();

if (!$customerId) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function redirectToAddresses(): void
{
    header('Location: ' . BASE_URL . 'customer/addresses.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | ADD ADDRESS
    |--------------------------------------------------------------------------
    */
    if ($action === 'add') {

        $label = trim($_POST['label'] ?? 'Home');
        $fullAddress = trim($_POST['full_address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');

        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');

        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($label === '') {
            $label = 'Home';
        }

        if (
            $fullAddress === '' ||
            $city === '' ||
            $province === '' ||
            $contactPerson === '' ||
            $contactNumber === ''
        ) {
            $errorMessage = 'Please complete all required fields.';
        } else {

            try {

                /*
                | First address automatically becomes default
                */
                $countStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM customer_address
                    WHERE customer_id = ?
                ");

                $countStmt->execute([$customerId]);

                $addressCount = (int) $countStmt->fetchColumn();

                if ($addressCount === 0) {
                    $isDefault = 1;
                }

                /*
                | Remove default from other addresses
                */
                if ($isDefault === 1) {

                    $resetStmt = $db->prepare("
                        UPDATE customer_address
                        SET is_default = 0
                        WHERE customer_id = ?
                    ");

                    $resetStmt->execute([$customerId]);
                }

                /*
                | Insert address
                */
                $stmt = $db->prepare("
                    INSERT INTO customer_address (
                        customer_id,
                        label,
                        full_address,
                        city,
                        province,
                        contact_person,
                        contact_number,
                        latitude,
                        longitude,
                        is_default
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $customerId,
                    $label,
                    $fullAddress,
                    $city,
                    $province,
                    $contactPerson,
                    $contactNumber,
                    $latitude !== '' ? $latitude : null,
                    $longitude !== '' ? $longitude : null,
                    $isDefault
                ]);

                $_SESSION['address_success'] =
                    'Delivery address added successfully.';

                redirectToAddresses();

            } catch (PDOException $e) {

                $errorMessage =
                    'Failed to add delivery address. Please try again.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ADDRESS
    |--------------------------------------------------------------------------
    */
    elseif ($action === 'update') {

        $addressId = (int) ($_POST['address_id'] ?? 0);

        $label = trim($_POST['label'] ?? 'Home');
        $fullAddress = trim($_POST['full_address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');

        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');

        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($addressId <= 0) {

            $errorMessage = 'Invalid address.';

        } elseif (
            $fullAddress === '' ||
            $city === '' ||
            $province === '' ||
            $contactPerson === '' ||
            $contactNumber === ''
        ) {

            $errorMessage = 'Please complete all required fields.';

        } else {

            try {

                /*
                | Verify ownership
                */
                $checkStmt = $db->prepare("
                    SELECT address_id
                    FROM customer_address
                    WHERE address_id = ?
                    AND customer_id = ?
                    LIMIT 1
                ");

                $checkStmt->execute([
                    $addressId,
                    $customerId
                ]);

                if (!$checkStmt->fetch()) {

                    $errorMessage = 'Address not found.';

                } else {

                    /*
                    | Remove default from other addresses
                    */
                    if ($isDefault === 1) {

                        $resetStmt = $db->prepare("
                            UPDATE customer_address
                            SET is_default = 0
                            WHERE customer_id = ?
                        ");

                        $resetStmt->execute([$customerId]);
                    }

                    /*
                    | Update
                    */
                    $stmt = $db->prepare("
                        UPDATE customer_address
                        SET
                            label = ?,
                            full_address = ?,
                            city = ?,
                            province = ?,
                            contact_person = ?,
                            contact_number = ?,
                            latitude = ?,
                            longitude = ?,
                            is_default = ?
                        WHERE address_id = ?
                        AND customer_id = ?
                    ");

                    $stmt->execute([
                        $label,
                        $fullAddress,
                        $city,
                        $province,
                        $contactPerson,
                        $contactNumber,
                        $latitude !== '' ? $latitude : null,
                        $longitude !== '' ? $longitude : null,
                        $isDefault,
                        $addressId,
                        $customerId
                    ]);

                    /*
                    | Safety: if the edited address was not default
                    | but there is no default address, assign one.
                    */
                    if ($isDefault === 0) {

                        $defaultCheck = $db->prepare("
                            SELECT COUNT(*)
                            FROM customer_address
                            WHERE customer_id = ?
                            AND is_default = 1
                        ");

                        $defaultCheck->execute([$customerId]);

                        if ((int) $defaultCheck->fetchColumn() === 0) {

                            $makeDefault = $db->prepare("
                                UPDATE customer_address
                                SET is_default = 1
                                WHERE address_id = ?
                                AND customer_id = ?
                            ");

                            $makeDefault->execute([
                                $addressId,
                                $customerId
                            ]);
                        }
                    }

                    $_SESSION['address_success'] =
                        'Delivery address updated successfully.';

                    redirectToAddresses();
                }

            } catch (PDOException $e) {

                $errorMessage =
                    'Failed to update delivery address. Please try again.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ADDRESS
    |--------------------------------------------------------------------------
    */
    elseif ($action === 'delete') {

        $addressId = (int) ($_POST['address_id'] ?? 0);

        if ($addressId > 0) {

            try {

                $checkStmt = $db->prepare("
                    SELECT is_default
                    FROM customer_address
                    WHERE address_id = ?
                    AND customer_id = ?
                    LIMIT 1
                ");

                $checkStmt->execute([
                    $addressId,
                    $customerId
                ]);

                $address = $checkStmt->fetch();

                if ($address) {

                    $wasDefault = (int) $address['is_default'] === 1;

                    $deleteStmt = $db->prepare("
                        DELETE FROM customer_address
                        WHERE address_id = ?
                        AND customer_id = ?
                    ");

                    $deleteStmt->execute([
                        $addressId,
                        $customerId
                    ]);

                    /*
                    | If deleted address was default,
                    | assign another address as default.
                    */
                    if ($wasDefault) {

                        $newDefaultStmt = $db->prepare("
                            SELECT address_id
                            FROM customer_address
                            WHERE customer_id = ?
                            ORDER BY address_id ASC
                            LIMIT 1
                        ");

                        $newDefaultStmt->execute([$customerId]);

                        $newDefaultId =
                            $newDefaultStmt->fetchColumn();

                        if ($newDefaultId) {

                            $setDefaultStmt = $db->prepare("
                                UPDATE customer_address
                                SET is_default = 1
                                WHERE address_id = ?
                                AND customer_id = ?
                            ");

                            $setDefaultStmt->execute([
                                $newDefaultId,
                                $customerId
                            ]);
                        }
                    }

                    $_SESSION['address_success'] =
                        'Delivery address deleted successfully.';
                }

            } catch (PDOException $e) {

                $_SESSION['address_error'] =
                    'Failed to delete delivery address.';
            }
        }

        redirectToAddresses();
    }

    /*
    |--------------------------------------------------------------------------
    | SET DEFAULT
    |--------------------------------------------------------------------------
    */
    elseif ($action === 'set_default') {

        $addressId = (int) ($_POST['address_id'] ?? 0);

        if ($addressId > 0) {

            try {

                /*
                | Verify ownership
                */
                $checkStmt = $db->prepare("
                    SELECT address_id
                    FROM customer_address
                    WHERE address_id = ?
                    AND customer_id = ?
                    LIMIT 1
                ");

                $checkStmt->execute([
                    $addressId,
                    $customerId
                ]);

                if ($checkStmt->fetch()) {

                    /*
                    | Remove current default
                    */
                    $resetStmt = $db->prepare("
                        UPDATE customer_address
                        SET is_default = 0
                        WHERE customer_id = ?
                    ");

                    $resetStmt->execute([$customerId]);

                    /*
                    | Set selected address
                    */
                    $setStmt = $db->prepare("
                        UPDATE customer_address
                        SET is_default = 1
                        WHERE address_id = ?
                        AND customer_id = ?
                    ");

                    $setStmt->execute([
                        $addressId,
                        $customerId
                    ]);

                    $_SESSION['address_success'] =
                        'Default delivery address updated.';
                }

            } catch (PDOException $e) {

                $_SESSION['address_error'] =
                    'Failed to set default address.';
            }
        }

        redirectToAddresses();
    }
}

/*
|--------------------------------------------------------------------------
| Session Messages
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['address_success'])) {

    $successMessage = $_SESSION['address_success'];

    unset($_SESSION['address_success']);
}

if (isset($_SESSION['address_error'])) {

    $errorMessage = $_SESSION['address_error'];

    unset($_SESSION['address_error']);
}

/*
|--------------------------------------------------------------------------
| Get Addresses
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT
        address_id,
        label,
        full_address,
        city,
        province,
        contact_person,
        contact_number,
        latitude,
        longitude,
        is_default,
        created_at
    FROM customer_address
    WHERE customer_id = ?
    ORDER BY is_default DESC, address_id DESC
");

$stmt->execute([$customerId]);

$addresses = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/
$navItems = [];

$pageTitle = 'Delivery Addresses';

require_once __DIR__ . '/../includes/header.php';

?>

<style>

/* ============================================================
   CUSTOMER ADDRESSES PAGE
   ============================================================ */

.addresses-page {
    width: 100%;
    max-width: 1250px;
    margin: 0 auto;
    padding: 30px 32px 50px;
    box-sizing: border-box;
}

/* ============================================================
   PAGE HEADER
   ============================================================ */

.addresses-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 28px;
}

.addresses-title-area {
    min-width: 0;
}

.addresses-title {
    margin: 0;
    color: var(--color-text);
    font-size: 28px;
    line-height: 1.25;
    font-weight: 750;
    letter-spacing: -0.4px;
}

.addresses-subtitle {
    margin: 7px 0 0;
    color: var(--color-text-secondary);
    font-size: 14px;
    line-height: 1.5;
}

.add-address-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 18px;
    border: 0;
    border-radius: 10px;
    background: var(--color-primary);
    color: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition:
        transform 0.15s ease,
        opacity 0.15s ease,
        box-shadow 0.15s ease;
}

.add-address-btn:hover {
    opacity: 0.92;
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.10);
}

.add-address-btn:active {
    transform: translateY(0);
}

.add-address-plus {
    font-size: 19px;
    line-height: 1;
}

/* ============================================================
   ALERTS
   ============================================================ */

.address-alert {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 14px 16px;
    margin-bottom: 22px;
    border-radius: 11px;
    border: 1px solid transparent;
    font-size: 14px;
    line-height: 1.5;
}

.address-alert-icon {
    flex: 0 0 auto;
    font-size: 17px;
    line-height: 1.2;
}

.address-alert-success {
    background: rgba(34, 197, 94, 0.09);
    border-color: rgba(34, 197, 94, 0.22);
    color: #15803d;
}

.address-alert-error {
    background: rgba(239, 68, 68, 0.09);
    border-color: rgba(239, 68, 68, 0.22);
    color: #b91c1c;
}

/* ============================================================
   ADDRESS GRID
   ============================================================ */

.addresses-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

/* ============================================================
   ADDRESS CARD
   ============================================================ */

.address-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 15px;
    overflow: hidden;
    box-sizing: border-box;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease;
}

.address-card:hover {
    border-color: rgba(0, 0, 0, 0.13);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
    transform: translateY(-1px);
}

.address-card.is-default {
    border-color: var(--color-primary);
}

/* ============================================================
   CARD TOP BAR
   ============================================================ */

.address-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 18px 20px;
    border-bottom: 1px solid var(--color-border);
}

.address-label-wrap {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.address-label-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.04);
    font-size: 20px;
}

.address-card.is-default .address-label-icon {
    background: rgba(0, 0, 0, 0.045);
}

.address-label-text {
    min-width: 0;
}

.address-label-name {
    margin: 0;
    color: var(--color-text);
    font-size: 16px;
    font-weight: 750;
    line-height: 1.3;
    word-break: break-word;
}

.address-label-caption {
    margin-top: 3px;
    color: var(--color-text-secondary);
    font-size: 12px;
}

.default-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    min-height: 25px;
    padding: 0 9px;
    border-radius: 999px;
    background: var(--color-primary);
    color: #fff;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

/* ============================================================
   CARD BODY
   ============================================================ */

.address-card-body {
    padding: 19px 20px 18px;
    flex: 1;
}

.address-info-section {
    margin-bottom: 17px;
}

.address-info-section:last-child {
    margin-bottom: 0;
}

.address-info-title {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 7px;
    color: var(--color-text);
    font-size: 12px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.35px;
}

.address-info-icon {
    font-size: 14px;
}

.address-info-text {
    color: var(--color-text-secondary);
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
}

.address-location-line {
    display: block;
    margin-top: 2px;
}

.address-contact-box {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
    margin-top: 18px;
    padding: 13px 14px;
    border-radius: 10px;
    background: var(--color-background);
    border: 1px solid var(--color-border);
}

.contact-item {
    min-width: 0;
}

.contact-label {
    display: block;
    margin-bottom: 4px;
    color: var(--color-text-secondary);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.contact-value {
    display: block;
    color: var(--color-text);
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

/* ============================================================
   CARD ACTIONS
   ============================================================ */

.address-card-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid var(--color-border);
    background: var(--color-background);
}

.address-card-actions form {
    margin: 0;
}

.address-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 35px;
    padding: 0 11px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    background: var(--color-surface);
    color: var(--color-text);
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        color 0.15s ease;
}

.address-action-btn:hover {
    background: var(--color-background);
}

.address-action-edit {
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.address-action-edit:hover {
    background: rgba(0, 0, 0, 0.025);
}

.address-action-default {
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.address-action-delete {
    border-color: rgba(220, 38, 38, 0.25);
    color: #dc2626;
}

.address-action-delete:hover {
    background: rgba(220, 38, 38, 0.06);
    border-color: rgba(220, 38, 38, 0.4);
}

/* ============================================================
   EMPTY STATE
   ============================================================ */

.address-empty {
    padding: 70px 30px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    text-align: center;
}

.address-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: var(--color-background);
    border: 1px solid var(--color-border);
    font-size: 32px;
}

.address-empty-title {
    margin: 0 0 8px;
    color: var(--color-text);
    font-size: 19px;
    font-weight: 750;
}

.address-empty-text {
    max-width: 450px;
    margin: 0 auto 22px;
    color: var(--color-text-secondary);
    font-size: 14px;
    line-height: 1.6;
}

/* ============================================================
   MODAL
   ============================================================ */

.address-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
    background: rgba(0, 0, 0, 0.58);
    backdrop-filter: blur(3px);
}

.address-modal.active {
    display: flex;
}

.address-modal-dialog {
    width: 100%;
    max-width: 650px;
    max-height: calc(100vh - 40px);
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 17px;
    overflow: hidden;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.25);
    animation: addressModalIn 0.18s ease-out;
}

@keyframes addressModalIn {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.985);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.address-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 19px 22px;
    border-bottom: 1px solid var(--color-border);
}

.address-modal-heading {
    min-width: 0;
}

.address-modal-title {
    margin: 0;
    color: var(--color-text);
    font-size: 19px;
    font-weight: 750;
}

.address-modal-subtitle {
    margin: 4px 0 0;
    color: var(--color-text-secondary);
    font-size: 12px;
}

.address-modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    flex: 0 0 35px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--color-text-secondary);
    font-size: 25px;
    line-height: 1;
    cursor: pointer;
}

.address-modal-close:hover {
    background: var(--color-background);
    color: var(--color-text);
}

/* ============================================================
   FORM
   ============================================================ */

.address-form {
    overflow-y: auto;
    padding: 22px;
}

.form-section {
    margin-bottom: 22px;
}

.form-section:last-of-type {
    margin-bottom: 0;
}

.form-section-title {
    margin: 0 0 13px;
    color: var(--color-text);
    font-size: 14px;
    font-weight: 750;
}

.form-section-description {
    margin: -7px 0 14px;
    color: var(--color-text-secondary);
    font-size: 12px;
    line-height: 1.5;
}

.form-group {
    margin-bottom: 15px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    margin-bottom: 7px;
    color: var(--color-text);
    font-size: 13px;
    font-weight: 700;
}

.required {
    color: #dc2626;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.form-control {
    width: 100%;
    min-height: 42px;
    box-sizing: border-box;
    padding: 9px 11px;
    border: 1px solid var(--color-border);
    border-radius: 9px;
    outline: none;
    background: var(--color-background);
    color: var(--color-text);
    font-family: inherit;
    font-size: 13px;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.form-control::placeholder {
    color: var(--color-text-secondary);
    opacity: 0.7;
}

.form-control:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.045);
}

textarea.form-control {
    min-height: 95px;
    resize: vertical;
    line-height: 1.5;
}

/* ============================================================
   LABEL OPTIONS
   ============================================================ */

.label-options {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}

.label-option {
    position: relative;
}

.label-option input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.label-option-content {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 8px;
    border: 1px solid var(--color-border);
    border-radius: 9px;
    background: var(--color-background);
    color: var(--color-text);
    font-size: 12px;
    font-weight: 650;
    cursor: pointer;
    text-align: center;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        color 0.15s ease;
}

.label-option-content:hover {
    border-color: var(--color-primary);
}

.label-option input:checked + .label-option-content {
    border-color: var(--color-primary);
    background: var(--color-primary);
    color: #fff;
}

/* ============================================================
   DEFAULT CHECKBOX
   ============================================================ */

.default-address-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 13px 14px;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-background);
    cursor: pointer;
}

.default-address-option input {
    width: 17px;
    height: 17px;
    margin: 1px 0 0;
    accent-color: var(--color-primary);
    cursor: pointer;
}

.default-address-text {
    min-width: 0;
}

.default-address-title {
    display: block;
    color: var(--color-text);
    font-size: 13px;
    font-weight: 700;
}

.default-address-description {
    display: block;
    margin-top: 3px;
    color: var(--color-text-secondary);
    font-size: 11px;
    line-height: 1.45;
}

/* ============================================================
   LOCATION NOTE
   ============================================================ */

.location-note {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 14px;
    padding: 11px 12px;
    border-radius: 9px;
    background: var(--color-background);
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
    font-size: 11px;
    line-height: 1.5;
}

.location-note-icon {
    flex: 0 0 auto;
    font-size: 14px;
}

/* ============================================================
   MODAL FOOTER
   ============================================================ */

.address-modal-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 9px;
    padding: 15px 22px;
    border-top: 1px solid var(--color-border);
    background: var(--color-background);
}

.modal-btn {
    min-height: 39px;
    padding: 0 15px;
    border-radius: 9px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-text);
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.modal-btn:hover {
    background: var(--color-background);
}

.modal-btn-primary {
    border-color: var(--color-primary);
    background: var(--color-primary);
    color: #fff;
}

.modal-btn-primary:hover {
    opacity: 0.92;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */

@media (max-width: 950px) {

    .addresses-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 700px) {

    .addresses-page {
        padding: 22px 17px 40px;
    }

    .addresses-page-header {
        align-items: stretch;
        flex-direction: column;
        gap: 15px;
    }

    .add-address-btn {
        width: 100%;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .label-options {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .address-modal {
        padding: 10px;
    }

    .address-modal-dialog {
        max-height: calc(100vh - 20px);
        border-radius: 14px;
    }

    .address-form {
        padding: 18px;
    }

    .address-modal-header {
        padding: 16px 18px;
    }

    .address-modal-footer {
        padding: 13px 18px;
    }

}

@media (max-width: 480px) {

    .addresses-page {
        padding-left: 13px;
        padding-right: 13px;
    }

    .addresses-title {
        font-size: 24px;
    }

    .address-card-top {
        padding: 16px;
    }

    .address-card-body {
        padding: 17px 16px;
    }

    .address-card-actions {
        padding: 12px 16px;
    }

    .address-contact-box {
        grid-template-columns: 1fr;
    }

    .address-card-actions {
        align-items: stretch;
    }

    .address-card-actions form,
    .address-action-btn {
        flex: 1;
    }

    .address-action-btn {
        width: 100%;
    }

    .address-empty {
        padding: 55px 20px;
    }

}

</style>

<div class="addresses-page">

    <!-- ======================================================
         PAGE HEADER
         ====================================================== -->

    <div class="addresses-page-header">

        <div class="addresses-title-area">

            <h1 class="addresses-title">
                Delivery Addresses
            </h1>

            <p class="addresses-subtitle">
                Save and manage your delivery locations for faster checkout.
            </p>

        </div>

        <button
            type="button"
            class="add-address-btn"
            onclick="openAddModal()"
        >
            <span class="add-address-plus">+</span>
            <span>Add Address</span>
        </button>

    </div>


    <!-- ======================================================
         ALERTS
         ====================================================== -->

    <?php if ($successMessage): ?>

        <div class="address-alert address-alert-success">

            <span class="address-alert-icon">✓</span>

            <span>
                <?= htmlspecialchars($successMessage) ?>
            </span>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage): ?>

        <div class="address-alert address-alert-error">

            <span class="address-alert-icon">!</span>

            <span>
                <?= htmlspecialchars($errorMessage) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         EMPTY STATE
         ====================================================== -->

    <?php if (empty($addresses)): ?>

        <div class="address-empty">

            <div class="address-empty-icon">
                📍
            </div>

            <h2 class="address-empty-title">
                No saved addresses yet
            </h2>

            <p class="address-empty-text">
                Add your first delivery address so you can easily
                select it during checkout.
            </p>

            <button
                type="button"
                class="add-address-btn"
                onclick="openAddModal()"
            >
                <span class="add-address-plus">+</span>
                <span>Add Your First Address</span>
            </button>

        </div>

    <?php else: ?>


        <!-- ==================================================
             ADDRESS CARDS
             ================================================== -->

        <div class="addresses-grid">

            <?php foreach ($addresses as $address): ?>

                <?php

                $isDefault =
                    (int) $address['is_default'] === 1;

                $label = $address['label'] ?? 'Home';

                $labelIcon = match (strtolower($label)) {

                    'home' => '🏠',

                    'office' => '🏢',

                    'warehouse' => '🏭',

                    default => '📍'
                };

                ?>

                <article
                    class="address-card <?= $isDefault ? 'is-default' : '' ?>"
                >

                    <!-- CARD HEADER -->

                    <div class="address-card-top">

                        <div class="address-label-wrap">

                            <div class="address-label-icon">
                                <?= $labelIcon ?>
                            </div>

                            <div class="address-label-text">

                                <h2 class="address-label-name">
                                    <?= htmlspecialchars($label) ?>
                                </h2>

                                <div class="address-label-caption">
                                    Delivery Address
                                </div>

                            </div>

                        </div>


                        <?php if ($isDefault): ?>

                            <span class="default-badge">
                                Default
                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- CARD BODY -->

                    <div class="address-card-body">

                        <div class="address-info-section">

                            <div class="address-info-title">

                                <span class="address-info-icon">
                                    📍
                                </span>

                                <span>
                                    Delivery Location
                                </span>

                            </div>

                            <div class="address-info-text">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $address['full_address']
                                    )
                                ) ?>

                                <span class="address-location-line">
                                    <?= htmlspecialchars($address['city']) ?>,
                                    <?= htmlspecialchars($address['province']) ?>
                                </span>

                            </div>

                        </div>


                        <div class="address-contact-box">

                            <div class="contact-item">

                                <span class="contact-label">
                                    Recipient
                                </span>

                                <span class="contact-value">
                                    <?= htmlspecialchars(
                                        $address['contact_person']
                                    ) ?>
                                </span>

                            </div>


                            <div class="contact-item">

                                <span class="contact-label">
                                    Contact Number
                                </span>

                                <span class="contact-value">
                                    <?= htmlspecialchars(
                                        $address['contact_number']
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    </div>


                    <!-- CARD ACTIONS -->

                    <div class="address-card-actions">

                        <button
                            type="button"
                            class="address-action-btn address-action-edit"
                            onclick='openEditModal(<?= htmlspecialchars(
                                json_encode(
                                    $address,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_AMP |
                                    JSON_HEX_QUOT
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>)'
                        >
                            ✎ Edit
                        </button>


                        <?php if (!$isDefault): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="set_default"
                                >

                                <input
                                    type="hidden"
                                    name="address_id"
                                    value="<?= (int) $address['address_id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="address-action-btn address-action-default"
                                >
                                    ★ Set Default
                                </button>

                            </form>

                        <?php endif; ?>


                        <form
                            method="POST"
                            onsubmit="return confirmDeleteAddress();"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="address_id"
                                value="<?= (int) $address['address_id'] ?>"
                            >

                            <button
                                type="submit"
                                class="address-action-btn address-action-delete"
                            >
                                🗑 Delete
                            </button>

                        </form>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<!-- ============================================================
     ADD / EDIT ADDRESS MODAL
     ============================================================ -->

<div
    class="address-modal"
    id="addressModal"
    aria-hidden="true"
>

    <div
        class="address-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalTitle"
    >

        <!-- MODAL HEADER -->

        <div class="address-modal-header">

            <div class="address-modal-heading">

                <h2
                    class="address-modal-title"
                    id="modalTitle"
                >
                    Add Delivery Address
                </h2>

                <p class="address-modal-subtitle">
                    Enter the details of your delivery location.
                </p>

            </div>


            <button
                type="button"
                class="address-modal-close"
                onclick="closeAddressModal()"
                aria-label="Close"
            >
                &times;
            </button>

        </div>


        <!-- FORM -->

        <form
            method="POST"
            class="address-form"
            id="addressForm"
        >

            <input
                type="hidden"
                name="action"
                id="formAction"
                value="add"
            >

            <input
                type="hidden"
                name="address_id"
                id="addressId"
                value=""
            >


            <!-- ADDRESS LABEL -->

            <div class="form-section">

                <h3 class="form-section-title">
                    Address Type
                </h3>

                <div class="label-options">

                    <label class="label-option">

                        <input
                            type="radio"
                            name="label"
                            value="Home"
                            checked
                        >

                        <span class="label-option-content">
                            🏠 Home
                        </span>

                    </label>


                    <label class="label-option">

                        <input
                            type="radio"
                            name="label"
                            value="Office"
                        >

                        <span class="label-option-content">
                            🏢 Office
                        </span>

                    </label>


                    <label class="label-option">

                        <input
                            type="radio"
                            name="label"
                            value="Warehouse"
                        >

                        <span class="label-option-content">
                            🏭 Warehouse
                        </span>

                    </label>


                    <label class="label-option">

                        <input
                            type="radio"
                            name="label"
                            value="Other"
                        >

                        <span class="label-option-content">
                            📍 Other
                        </span>

                    </label>

                </div>

            </div>


            <!-- DELIVERY LOCATION -->

            <div class="form-section">

                <h3 class="form-section-title">
                    Delivery Location
                </h3>

                <div class="form-group">

                    <label
                        class="form-label"
                        for="fullAddress"
                    >
                        Full Address
                        <span class="required">*</span>
                    </label>

                    <textarea
                        class="form-control"
                        name="full_address"
                        id="fullAddress"
                        placeholder="House/Building No., Street, Barangay"
                        required
                    ></textarea>

                </div>


                <div class="form-row">

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="city"
                        >
                            City
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="city"
                            id="city"
                            placeholder="e.g. Quezon City"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="province"
                        >
                            Province
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="province"
                            id="province"
                            placeholder="e.g. Metro Manila"
                            required
                        >

                    </div>

                </div>


                <input
                    type="hidden"
                    name="latitude"
                    id="latitude"
                    value=""
                >

                <input
                    type="hidden"
                    name="longitude"
                    id="longitude"
                    value=""
                >

                <div class="location-note">

                    <span class="location-note-icon">
                        ℹ
                    </span>

                    <span>
                        Location coordinates can be stored with the
                        address for future delivery tracking and mapping.
                    </span>

                </div>

            </div>


            <!-- CONTACT -->

            <div class="form-section">

                <h3 class="form-section-title">
                    Recipient Information
                </h3>

                <div class="form-row">

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="contactPerson"
                        >
                            Contact Person
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="contact_person"
                            id="contactPerson"
                            placeholder="Recipient name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="contactNumber"
                        >
                            Contact Number
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="contact_number"
                            id="contactNumber"
                            placeholder="09XXXXXXXXX"
                            required
                        >

                    </div>

                </div>

            </div>


            <!-- DEFAULT -->

            <div class="form-section">

                <label
                    class="default-address-option"
                    for="isDefault"
                >

                    <input
                        type="checkbox"
                        name="is_default"
                        id="isDefault"
                    >

                    <span class="default-address-text">

                        <span class="default-address-title">
                            Set as default delivery address
                        </span>

                        <span class="default-address-description">
                            This address will be selected automatically
                            during checkout.
                        </span>

                    </span>

                </label>

            </div>


            <!-- MODAL FOOTER -->

            <div class="address-modal-footer">

                <button
                    type="button"
                    class="modal-btn"
                    onclick="closeAddressModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="modal-btn modal-btn-primary"
                    id="saveAddressButton"
                >
                    Save Address
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* ============================================================
   ADDRESS MODAL
   ============================================================ */

const addressModal =
    document.getElementById('addressModal');

const addressForm =
    document.getElementById('addressForm');


/*
|--------------------------------------------------------------------------
| Open Add Modal
|--------------------------------------------------------------------------
*/
function openAddModal() {

    addressForm.reset();

    document.getElementById('modalTitle').textContent =
        'Add Delivery Address';

    document.getElementById('formAction').value =
        'add';

    document.getElementById('addressId').value =
        '';

    document.getElementById('latitude').value =
        '';

    document.getElementById('longitude').value =
        '';

    /*
    | Reset label to Home
    */
    const homeRadio =
        document.querySelector(
            'input[name="label"][value="Home"]'
        );

    if (homeRadio) {
        homeRadio.checked = true;
    }

    /*
    | Make sure default checkbox is unchecked
    */
    document.getElementById('isDefault').checked =
        false;

    document.getElementById('saveAddressButton').textContent =
        'Save Address';

    addressModal.classList.add('active');

    addressModal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow = 'hidden';

    setTimeout(function () {

        document.getElementById('fullAddress').focus();

    }, 100);
}


/*
|--------------------------------------------------------------------------
| Open Edit Modal
|--------------------------------------------------------------------------
*/
function openEditModal(address) {

    document.getElementById('modalTitle').textContent =
        'Edit Delivery Address';

    document.getElementById('formAction').value =
        'update';

    document.getElementById('addressId').value =
        address.address_id || '';

    document.getElementById('fullAddress').value =
        address.full_address || '';

    document.getElementById('city').value =
        address.city || '';

    document.getElementById('province').value =
        address.province || '';

    document.getElementById('contactPerson').value =
        address.contact_person || '';

    document.getElementById('contactNumber').value =
        address.contact_number || '';

    document.getElementById('latitude').value =
        address.latitude || '';

    document.getElementById('longitude').value =
        address.longitude || '';

    /*
    | Select label
    */
    const labelRadios =
        document.querySelectorAll(
            'input[name="label"]'
        );

    labelRadios.forEach(function (radio) {

        radio.checked =
            radio.value === address.label;

    });


    /*
    | Default
    */
    document.getElementById('isDefault').checked =
        Number(address.is_default) === 1;


    document.getElementById('saveAddressButton').textContent =
        'Update Address';

    addressModal.classList.add('active');

    addressModal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow = 'hidden';

    setTimeout(function () {

        document.getElementById('fullAddress').focus();

    }, 100);
}


/*
|--------------------------------------------------------------------------
| Close Modal
|--------------------------------------------------------------------------
*/
function closeAddressModal() {

    addressModal.classList.remove('active');

    addressModal.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.style.overflow = '';
}


/*
|--------------------------------------------------------------------------
| Click Outside Modal
|--------------------------------------------------------------------------
*/
addressModal.addEventListener(
    'click',
    function (event) {

        if (event.target === addressModal) {

            closeAddressModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| Escape Key
|--------------------------------------------------------------------------
*/
document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {

            if (
                addressModal.classList.contains('active')
            ) {

                closeAddressModal();

            }

        }

    }
);


/*
|--------------------------------------------------------------------------
| Confirm Delete
|--------------------------------------------------------------------------
*/
function confirmDeleteAddress() {

    return confirm(
        'Are you sure you want to delete this delivery address?'
    );
}

</script>