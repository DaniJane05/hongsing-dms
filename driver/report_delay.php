<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(ROLE_DRIVER);
$db = Database::getConnection();
$driverId = (int) currentUserId();
$orderId = (int)($_GET['id'] ?? $_POST['order_id'] ?? 0);
$stmt=$db->prepare("SELECT o.order_id,o.order_number,o.truck_id,o.completion_status,d.full_name FROM `order` o JOIN driver d ON d.driver_id=o.driver_id WHERE o.order_id=? AND o.driver_id=? LIMIT 1");
$stmt->execute([$orderId,$driverId]); $order=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$order || $order['completion_status']!=='In Transit'){ header('Location: '.BASE_URL.'driver/deliveries.php'); exit; }
$types=['Vehicle Breakdown'=>'Operational','Traffic/Road Issue'=>'External','Customer Unavailable'=>'Customer-related','Weather'=>'External','Wrong/Incomplete Address'=>'Customer-related','Loading/Warehouse Delay'=>'Operational','Driver-related Issue'=>'Driver-related','Accident/Emergency'=>'External','Delivery Interrupted'=>'Operational','Others'=>'Operational'];
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrfToken();
    $type=(string)($_POST['incident_type']??''); $description=trim((string)($_POST['description']??'')); $delay=max(0,(int)($_POST['estimated_delay_minutes']??0));
    $category=$types[$type]??null; $lat=is_numeric($_POST['latitude']??null)?(float)$_POST['latitude']:null; $lng=is_numeric($_POST['longitude']??null)?(float)$_POST['longitude']:null;
    if(!$category || $description==='' || $delay<=0){ $error='Please complete the incident type, description, and estimated delay.'; }
    else {
      $evidence = null;

if (
    !empty($_FILES['evidence']['name']) &&
    $_FILES['evidence']['error'] === UPLOAD_ERR_OK
) {

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $fileSize = (int) $_FILES['evidence']['size'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))
        ->file($_FILES['evidence']['tmp_name']);


    /*
    |--------------------------------------------------------------------------
    | VALIDATE FILE
    |--------------------------------------------------------------------------
    */

    if (
        !isset($allowed[$mime]) ||
        $fileSize > 5 * 1024 * 1024
    ) {

        $error =
            'Evidence must be JPG, PNG, or WEBP and not exceed 5 MB.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | INCIDENT UPLOAD DIRECTORY
        |--------------------------------------------------------------------------
        */

        $dir =
            __DIR__ .
            '/../assets/uploads/incidents/';


        /*
        |--------------------------------------------------------------------------
        | CREATE DIRECTORY IF IT DOES NOT EXIST
        |--------------------------------------------------------------------------
        */

        if (!is_dir($dir)) {

            if (
                !mkdir(
                    $dir,
                    0755,
                    true
                ) &&
                !is_dir($dir)
            ) {

                $error =
                    'Could not create the incident evidence directory.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE FILE
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $name =
                'incident_' .
                $orderId .
                '_' .
                bin2hex(random_bytes(6)) .
                '.' .
                $allowed[$mime];


            $destination =
                $dir . $name;


            if (
                move_uploaded_file(
                    $_FILES['evidence']['tmp_name'],
                    $destination
                )
            ) {

                $evidence =
                    'assets/uploads/incidents/' .
                    $name;

            } else {

                $error =
                    'Could not save the evidence image.';
            }
        }
    }
}
      if($error===''){
        $db->beginTransaction();
        try{
          $ins=$db->prepare("INSERT INTO delivery_incident(order_id,driver_id,truck_id,incident_type,incident_category,description,estimated_delay_minutes,evidence_path,latitude,longitude) VALUES(?,?,?,?,?,?,?,?,?,?)");
          $ins->execute([$orderId,$driverId,$order['truck_id'],$type,$category,$description,$delay,$evidence,$lat,$lng]);
          $admins=$db->query("SELECT user_id,role FROM system_user WHERE status='Active' AND role IN ('Admin','Super Admin')")->fetchAll(PDO::FETCH_ASSOC);
          $msg='Delay/incident reported for order '.$order['order_number'].' by '.$order['full_name'].': '.$type.' (estimated '.$delay.' min delay).';
          $n=$db->prepare("INSERT INTO notification(order_id,status_id,notification_type,recipient_role,recipient_id,delivery_status,message) VALUES(?,NULL,'In-App',?,?,'Sent',?)");
          foreach($admins as $a){$n->execute([$orderId,$a['role'],$a['user_id'],$msg]);}
          $db->commit(); header('Location: '.BASE_URL.'driver/delivery_details.php?id='.$orderId.'&incident=reported'); exit;
        }catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); $error='Unable to save incident. Make sure the delivery_incident migration has been imported.'; }
      }
    }
}
require_once __DIR__.'/../includes/header.php';
?>
<style>.incident-wrap{max-width:760px;margin:30px auto;padding:0 18px}.incident-card{background:var(--color-surface,#fff);border:1px solid var(--color-border,#ddd);border-radius:14px;padding:24px}.incident-card label{display:block;font-weight:700;margin:15px 0 6px}.incident-card select,.incident-card textarea,.incident-card input{width:100%;padding:11px;border:1px solid var(--color-border,#ccc);border-radius:8px;background:var(--color-surface,#fff);color:var(--color-text-primary,#111)}.incident-actions{display:flex;gap:10px;margin-top:20px}.incident-actions button,.incident-actions a{padding:11px 16px;border-radius:8px;text-decoration:none;border:0;cursor:pointer}.incident-actions button{background:var(--color-primary,#2563eb);color:#fff}.notice{padding:12px;border-radius:8px;background:#fee2e2;color:#991b1b}</style>
<div class="incident-wrap"><div class="incident-card"><h1>Report Delivery Delay / Incident</h1><p>Order <strong><?=htmlspecialchars($order['order_number'])?></strong>. Report unexpected events affecting this delivery. The administrator will review whether it is a valid delay or driver-related violation.</p><?php if($error):?><div class="notice"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data"><?=csrfField()?><input type="hidden" name="order_id" value="<?=$orderId?>"><input type="hidden" id="latitude" name="latitude"><input type="hidden" id="longitude" name="longitude">
<label>Incident Type</label><select name="incident_type" required><option value="">Select reason</option><?php foreach($types as $t=>$c):?><option value="<?=htmlspecialchars($t)?>"><?=htmlspecialchars($t)?> — <?=htmlspecialchars($c)?></option><?php endforeach;?></select>
<label>Estimated Delay (minutes)</label><input type="number" name="estimated_delay_minutes" min="1" max="1440" required>
<label>Description / What happened?</label><textarea name="description" rows="5" maxlength="2000" required></textarea>
<label>Evidence Photo (optional, max 5 MB)</label><input type="file" name="evidence" accept="image/jpeg,image/png,image/webp">
<div class="incident-actions"><button type="submit">Submit Incident Report</button><a href="<?=BASE_URL?>driver/delivery_details.php?id=<?=$orderId?>">Cancel</a></div></form></div></div>
<script>if(navigator.geolocation){navigator.geolocation.getCurrentPosition(p=>{document.getElementById('latitude').value=p.coords.latitude;document.getElementById('longitude').value=p.coords.longitude;},()=>{}, {enableHighAccuracy:true,timeout:5000});}</script>
