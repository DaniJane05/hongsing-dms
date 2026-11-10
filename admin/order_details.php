<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$db = Database::getConnection();

$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: orders.php?error=invalid_order');
    exit;
}

$stmt = $db->prepare("
    SELECT
        o.*,
        c.full_name AS customer_name,
        c.email AS customer_email,
        c.phone_number AS customer_phone,
        d.full_name AS driver_name,
        d.phone_number AS driver_phone,
        t.plate_number,
        t.model AS truck_model,
        r.route_code,
        r.estimated_distance,
        r.estimated_time,
        dl.full_address AS delivery_address,
        dl.city AS delivery_city,
        dl.province AS delivery_province,
        dl.contact_person,
        dl.contact_number,
        rc.photo_proof_path,
        rc.delivery_time,
        rc.delivery_notes
    FROM `order` o
    LEFT JOIN customer c ON c.customer_id = o.customer_id
    LEFT JOIN driver d ON d.driver_id = o.driver_id
    LEFT JOIN truck t ON t.truck_id = o.truck_id
    LEFT JOIN route r ON r.route_id = o.route_id
    LEFT JOIN delivery_location dl
    ON dl.order_id = o.order_id
    LEFT JOIN receipt rc ON rc.order_id = o.order_id
    WHERE o.order_id = ?
    LIMIT 1
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php?error=order_not_found');
    exit;
}

$historyStmt = $db->prepare("
    SELECT status, timestamp, notes
    FROM delivery_status
    WHERE order_id = ?
    ORDER BY timestamp ASC
");
$historyStmt->execute([$orderId]);
$statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);


function showVal($v): string {
    $v = trim((string)($v ?? ''));
    return $v !== '' ? e($v) : '—';
}
function showDT($v): string {
    if (!$v) return '—';
    $t = strtotime((string)$v);
    return $t ? date('M d, Y · h:i A', $t) : e($v);
}

$weight = (float)($order['total_weight_kg'] ?? 0);
if ($weight <= 0) $weight = (float)($order['total_weight'] ?? 0);

$address = implode(', ', array_filter(array_unique([
    trim((string)($order['delivery_address'] ?? '')),
    trim((string)($order['delivery_city'] ?? '')),
    trim((string)($order['delivery_province'] ?? ''))
])));

$podUrl = null;
if (!empty($order['photo_proof_path'])) {
    $file = basename((string)$order['photo_proof_path']);
    $podUrl = defined('UPLOAD_URL_POD')
        ? rtrim(UPLOAD_URL_POD, '/') . '/' . rawurlencode($file)
        : rtrim(BASE_URL, '/') . '/uploads/proof_of_delivery/' . rawurlencode($file);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Details - <?= e($order['order_number']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
.order-details-page{width:100%;max-width:1380px;margin:0 auto}
.details-top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px}
.details-top h1{margin:7px 0 5px;font-size:28px;color:var(--color-text-primary)}
.details-top p{margin:0;color:var(--color-text-secondary);font-size:13px}
.back-link{color:var(--color-text-secondary);text-decoration:none;font-size:13px;font-weight:600}
.back-link:hover{color:var(--color-accent)}
.completed-badge{padding:8px 14px;border-radius:999px;background:var(--color-accent-soft);color:var(--color-success);font-size:12px;font-weight:800}
.details-layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(330px,.8fr);gap:20px;align-items:start}
.details-col{display:grid;gap:20px}
.detail-card{border:1px solid var(--color-border);border-radius:var(--radius-lg);background:var(--color-surface);box-shadow:var(--shadow-card);overflow:hidden}
.card-head{padding:18px 20px;border-bottom:1px solid var(--color-border)}
.card-head h2{margin:0 0 4px;font-size:16px;color:var(--color-text-primary)}
.card-head p{margin:0;font-size:12px;color:var(--color-text-muted)}
.card-body{padding:20px}
.info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 22px}
.info-full{grid-column:1/-1}
.label{display:block;margin-bottom:5px;color:var(--color-text-muted);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.value{display:block;color:var(--color-text-primary);font-size:13px;font-weight:600;line-height:1.55;overflow-wrap:anywhere}
.total{font-size:20px;font-weight:800}
.urgent{color:var(--color-danger)}
.pod-wrap{padding:10px;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg)}
.pod-image{display:block;width:100%;max-height:420px;object-fit:contain;border-radius:8px;background:#000;cursor:zoom-in}
.pod-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}
.pod-time{font-size:11px;color:var(--color-text-secondary)}
.photo-btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:var(--radius-sm);background:var(--color-accent);color:#fff;text-decoration:none;font-size:12px;font-weight:700}
.empty{padding:28px 15px;border:1px dashed var(--color-border);border-radius:var(--radius-md);text-align:center;color:var(--color-text-muted);font-size:12px}
.timeline{display:grid}
.timeline-row{display:grid;grid-template-columns:16px 1fr;gap:12px;padding-bottom:20px}
.timeline-row:last-child{padding-bottom:0}
.dot{position:relative;width:11px;height:11px;margin-top:3px;border-radius:50%;background:var(--color-accent)}
.timeline-row:not(:last-child) .dot:after{content:"";position:absolute;left:5px;top:13px;width:1px;height:calc(100% + 20px);background:var(--color-border)}
.timeline-status{font-size:13px;font-weight:700;color:var(--color-text-primary)}
.timeline-date{display:block;margin-top:3px;font-size:11px;color:var(--color-text-muted)}
.timeline-note{margin-top:5px;font-size:12px;color:var(--color-text-secondary)}
@media(max-width:980px){.details-layout{grid-template-columns:1fr}}
@media(max-width:650px){.details-top{flex-direction:column}.info-grid{grid-template-columns:1fr}.info-full{grid-column:auto}}
</style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<main class="app-main">
<div class="order-details-page">
<header class="details-top">
<div>
<a class="back-link" href="<?= BASE_URL ?>admin/orders.php">← Back to Orders</a>
<h1><?= e($order['order_number']) ?></h1>
<p>Completed delivery details and proof of delivery.</p>
</div>
<span class="completed-badge"><?= e($order['completion_status']) ?></span>
</header>

<div class="details-layout">
<div class="details-col">

<section class="detail-card">
<div class="card-head"><h2>Order Information</h2><p>Order and delivery summary.</p></div>
<div class="card-body"><div class="info-grid">
<div><span class="label">Order Date</span><span class="value"><?= showDT($order['order_date']) ?></span></div>
<div><span class="label">Priority</span><span class="value <?= $order['priority_level']==='Urgent'?'urgent':'' ?>"><?= e($order['priority_level']) ?></span></div>
<div><span class="label">Total Weight</span><span class="value"><?= number_format($weight,2) ?> kg</span></div>
<div><span class="label">Route</span><span class="value"><?= showVal($order['route_code']) ?></span></div>
<div><span class="label">Distance</span><span class="value"><?= showVal($order['estimated_distance']) ?></span></div>
<div><span class="label">Estimated Route Time</span><span class="value"><?= showVal($order['estimated_time']) ?></span></div>
<div class="info-full"><span class="label">Items / Order Description</span><span class="value"><?= showVal($order['item_description']) ?></span></div>
</div></div>
</section>

<section class="detail-card">
<div class="card-head"><h2>Customer & Delivery</h2><p>Recipient and destination information.</p></div>
<div class="card-body"><div class="info-grid">
<div><span class="label">Customer</span><span class="value"><?= showVal($order['customer_name']) ?></span></div>
<div><span class="label">Customer Phone</span><span class="value"><?= showVal($order['customer_phone']) ?></span></div>
<div><span class="label">Contact Person</span><span class="value"><?= showVal($order['contact_person']) ?></span></div>
<div><span class="label">Delivery Contact</span><span class="value"><?= showVal($order['contact_number']) ?></span></div>
<div class="info-full"><span class="label">Delivery Address</span><span class="value"><?= $address!==''?e($address):'—' ?></span></div>
</div></div>
</section>

<section class="detail-card">
<div class="card-head"><h2>Delivery History</h2><p>Status updates recorded for this order.</p></div>
<div class="card-body">
<?php if (!$statusHistory): ?>
<div class="empty">No delivery status history is available.</div>
<?php else: ?>
<div class="timeline">
<?php foreach ($statusHistory as $h): ?>
<div class="timeline-row"><div class="dot"></div><div>
<div class="timeline-status"><?= e($h['status']) ?></div>
<span class="timeline-date"><?= showDT($h['timestamp']) ?></span>
<?php if (!empty($h['notes'])): ?><div class="timeline-note"><?= e($h['notes']) ?></div><?php endif; ?>
</div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</section>

</div>

<div class="details-col">

<section class="detail-card">
<div class="card-head"><h2>Proof of Delivery</h2><p>Photo submitted by the driver.</p></div>
<div class="card-body">
<?php if ($podUrl): ?>
<div class="pod-wrap">
<a href="<?= e($podUrl) ?>" target="_blank" rel="noopener"><img class="pod-image" src="<?= e($podUrl) ?>" alt="Proof of Delivery"></a>
<div class="pod-footer">
<span class="pod-time">Completed: <?= showDT($order['delivery_time']) ?></span>
<a class="photo-btn" href="<?= e($podUrl) ?>" target="_blank" rel="noopener">View Full Photo</a>
</div>
</div>
<?php if (!empty($order['delivery_notes'])): ?>
<div style="margin-top:14px"><span class="label">Delivery Note</span><span class="value"><?= e($order['delivery_notes']) ?></span></div>
<?php endif; ?>
<?php else: ?>
<div class="empty">No proof of delivery photo has been uploaded for this order.</div>
<?php endif; ?>
</div>
</section>

<section class="detail-card">
<div class="card-head"><h2>Assignment</h2><p>Driver and truck used for the delivery.</p></div>
<div class="card-body"><div class="info-grid">
<div class="info-full"><span class="label">Driver</span><span class="value"><?= showVal($order['driver_name']) ?></span></div>
<div class="info-full"><span class="label">Driver Phone</span><span class="value"><?= showVal($order['driver_phone']) ?></span></div>
<div><span class="label">Truck</span><span class="value"><?= showVal($order['plate_number']) ?></span></div>
<div><span class="label">Model</span><span class="value"><?= showVal($order['truck_model']) ?></span></div>
</div></div>
</section>

<section class="detail-card">
<div class="card-head"><h2>Payment Summary</h2><p>Recorded order charges.</p></div>
<div class="card-body"><div class="info-grid">
<div><span class="label">Subtotal</span><span class="value">₱<?= number_format((float)($order['subtotal']??0),2) ?></span></div>
<div><span class="label">Urgent Fee</span><span class="value">₱<?= number_format((float)($order['urgent_fee']??0),2) ?></span></div>
<div class="info-full"><span class="label">Total Amount</span><span class="value total">₱<?= number_format((float)($order['total_amount']??0),2) ?></span></div>
</div></div>
</section>

</div>
</div>
</div>
</main>
</div>
</body>
</html>
