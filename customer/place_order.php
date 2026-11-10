<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_CUSTOMER);

$db = Database::getConnection();
$customerId = currentUserId();

$customerStmt = $db->prepare("SELECT full_name, address, city, province, phone_number FROM customer WHERE customer_id = :id");
$customerStmt->execute(['id' => $customerId]);
$customer = $customerStmt->fetch();

$errors = [];
$old = [
    'item_description'       => '',
    'total_amount'            => '',
    'expected_delivery_date' => '',
    'priority_level'          => 'Normal',
    'full_address'            => $customer['address'] ?? '',
    'city'                    => $customer['city'] ?? '',
    'province'                => $customer['province'] ?? '',
    'contact_person'          => $customer['full_name'] ?? '',
    'contact_number'          => $customer['phone_number'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['item_description']       = sanitize($_POST['item_description'] ?? '');
    $old['total_amount']            = sanitize($_POST['total_amount'] ?? '');
    $old['expected_delivery_date'] = sanitize($_POST['expected_delivery_date'] ?? '');
    $old['priority_level']          = sanitize($_POST['priority_level'] ?? 'Normal');
    $old['full_address']            = sanitize($_POST['full_address'] ?? '');
    $old['city']                    = sanitize($_POST['city'] ?? '');
    $old['province']                = sanitize($_POST['province'] ?? '');
    $old['contact_person']          = sanitize($_POST['contact_person'] ?? '');
    $old['contact_number']          = sanitize($_POST['contact_number'] ?? '');

    if ($old['item_description'] === '') $errors[] = 'Please describe the items for delivery.';
    if (!is_numeric($old['total_amount']) || (float) $old['total_amount'] <= 0) $errors[] = 'Please enter a valid order amount.';

    if ($old['expected_delivery_date'] === '') {
        $errors[] = 'Please select an expected delivery date.';
    } elseif (strtotime($old['expected_delivery_date']) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Expected delivery date cannot be in the past.';
    }

    if (!in_array($old['priority_level'], ['Normal', 'Urgent'], true)) $errors[] = 'Invalid priority level selected.';
    if ($old['full_address'] === '' || $old['city'] === '' || $old['province'] === '') $errors[] = 'Complete delivery address, city, and province are required.';
    if ($old['contact_person'] === '' || $old['contact_number'] === '') $errors[] = 'Recipient contact person and number are required.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $orderStmt = $db->prepare(
                "INSERT INTO `order`
                    (order_number, customer_id, item_description, total_amount,
                     order_date, expected_delivery_date, priority_level, completion_status)
                 VALUES
                    (:order_number, :customer_id, :item_description, :total_amount,
                     CURDATE(), :expected_delivery_date, :priority_level, 'Pending')"
            );
            $orderStmt->execute([
                'order_number'            => $orderNumber,
                'customer_id'              => $customerId,
                'item_description'        => $old['item_description'],
                'total_amount'             => $old['total_amount'],
                'expected_delivery_date' => $old['expected_delivery_date'],
                'priority_level'           => $old['priority_level'],
            ]);

            $orderId = (int) $db->lastInsertId();

            $locationStmt = $db->prepare(
                "INSERT INTO delivery_location
                    (order_id, full_address, city, province, contact_person, contact_number)
                 VALUES
                    (:order_id, :full_address, :city, :province, :contact_person, :contact_number)"
            );
            $locationStmt->execute([
                'order_id'       => $orderId,
                'full_address'   => $old['full_address'],
                'city'           => $old['city'],
                'province'       => $old['province'],
                'contact_person' => $old['contact_person'],
                'contact_number' => $old['contact_number'],
            ]);

            $statusStmt = $db->prepare(
                "INSERT INTO delivery_status (order_id, status, notes)
                 VALUES (:order_id, 'Pending', 'Order placed by customer, awaiting admin approval.')"
            );
            $statusStmt->execute(['order_id' => $orderId]);

            $db->commit();

            header('Location: ' . BASE_URL . 'customer/order_history.php?placed=1');
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Something went wrong while placing your order. Please try again.';
        }
    }
}

$navItems = [
    ['label' => 'Dashboard',      'url' => BASE_URL . 'customer/dashboard.php'],
    ['label' => 'Place Order',    'url' => BASE_URL . 'customer/place_order.php'],
    ['label' => 'Order History',  'url' => BASE_URL . 'customer/order_history.php'],
    ['label' => 'Track Delivery', 'url' => BASE_URL . 'customer/track_delivery.php'],
    ['label' => 'Feedback',       'url' => BASE_URL . 'customer/feedback.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Place Order · Hong Sing LKRR Commercial</title>
<?php require __DIR__ . '/../includes/theme_init.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
    .form-section-title {
        font-size: 0.95rem;
        color: var(--color-accent);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 24px 0 14px;
    }
    .form-section-title:first-child { margin-top: 0; }

    .field-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
    .field-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .field-group { margin-bottom: 16px; }

    textarea.form-input { resize: vertical; min-height: 90px; font-family: inherit; }

    .form-actions { display: flex; gap: 12px; margin-top: 8px; }
    .form-actions .btn-primary { width: auto; padding: 13px 28px; }

    @media (max-width: 900px) {
        .field-row-3, .field-row-2 { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="app-layout">
    <?php require __DIR__ . '/../includes/header.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Place a New Order</h1>
                <p>Fill in the details below and we'll review it shortly.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <ul style="margin:0;padding-left:18px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card panel" style="max-width: 760px;">
            <form method="POST" action="">

                <div class="form-section-title">Order Details</div>

                <div class="field-group">
                    <label class="form-label" for="item_description">Item Description</label>
                    <textarea id="item_description" name="item_description" class="form-input"
                              placeholder="e.g. 50 boxes of food-grade plastic containers (500ml)"
                              required><?= htmlspecialchars($old['item_description']) ?></textarea>
                </div>

                <div class="field-row-3">
                    <div class="field-group">
                        <label class="form-label" for="total_amount">Order Amount (₱)</label>
                        <input type="number" step="0.01" min="0" id="total_amount" name="total_amount"
                               class="form-input" placeholder="0.00"
                               value="<?= htmlspecialchars($old['total_amount']) ?>" required>
                    </div>
                    <div class="field-group">
                        <label class="form-label" for="expected_delivery_date">Expected Delivery Date</label>
                        <input type="date" id="expected_delivery_date" name="expected_delivery_date"
                               class="form-input" min="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($old['expected_delivery_date']) ?>" required>
                    </div>
                    <div class="field-group">
                        <label class="form-label" for="priority_level">Priority</label>
                        <select id="priority_level" name="priority_level" class="form-input">
                            <option value="Normal" <?= $old['priority_level'] === 'Normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="Urgent" <?= $old['priority_level'] === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="form-section-title">Delivery Location</div>

                <div class="field-group">
                    <label class="form-label" for="full_address">Complete Address</label>
                    <input type="text" id="full_address" name="full_address" class="form-input"
                           placeholder="Unit/Bldg No., Street, Barangay"
                           value="<?= htmlspecialchars($old['full_address']) ?>" required>
                </div>

                <div class="field-row-2">
                    <div class="field-group">
                        <label class="form-label" for="city">City</label>
                        <input type="text" id="city" name="city" class="form-input"
                               value="<?= htmlspecialchars($old['city']) ?>" required>
                    </div>
                    <div class="field-group">
                        <label class="form-label" for="province">Province</label>
                        <input type="text" id="province" name="province" class="form-input"
                               value="<?= htmlspecialchars($old['province']) ?>" required>
                    </div>
                </div>

                <div class="form-section-title">Recipient Contact</div>

                <div class="field-row-2">
                    <div class="field-group">
                        <label class="form-label" for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-input"
                               value="<?= htmlspecialchars($old['contact_person']) ?>" required>
                    </div>
                    <div class="field-group">
                        <label class="form-label" for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" class="form-input"
                               placeholder="09XX XXX XXXX"
                               value="<?= htmlspecialchars($old['contact_number']) ?>" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Submit Order</button>
                    <a href="<?= BASE_URL ?>customer/dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>