<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(ROLE_SUPER_ADMIN);
$db = Database::getConnection();

if (isset($_GET['ajax']) && $_GET['ajax'] === 'operations') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        $stmt = $db->query("SELECT o.order_id,o.order_number,o.priority_level,o.completion_status,o.order_date,
            c.full_name AS customer_name,d.driver_id,d.full_name AS driver_name,d.current_latitude,d.current_longitude,d.location_updated_at,
            t.truck_id,t.plate_number,t.model AS truck_model,
            dl.full_address AS delivery_address,dl.city AS delivery_city,dl.province AS delivery_province,
            dl.latitude AS destination_latitude,dl.longitude AS destination_longitude
            FROM `order` o
            LEFT JOIN customer c ON c.customer_id=o.customer_id
            LEFT JOIN driver d ON d.driver_id=o.driver_id
            LEFT JOIN truck t ON t.truck_id=o.truck_id
            LEFT JOIN delivery_location dl ON dl.location_id=(SELECT MAX(dl2.location_id) FROM delivery_location dl2 WHERE dl2.order_id=o.order_id)
            WHERE o.completion_status IN ('Approved','Assigned','Accepted','In Transit')
            ORDER BY CASE o.completion_status WHEN 'In Transit' THEN 1 WHEN 'Accepted' THEN 2 WHEN 'Assigned' THEN 3 WHEN 'Approved' THEN 4 ELSE 5 END,o.order_id DESC");
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$r){
            $r['order_id']=(int)$r['order_id'];
            $r['driver_id']=$r['driver_id']!==null?(int)$r['driver_id']:null;
            $r['truck_id']=$r['truck_id']!==null?(int)$r['truck_id']:null;
            foreach(['current_latitude','current_longitude','destination_latitude','destination_longitude'] as $k){$r[$k]=$r[$k]!==null?(float)$r[$k]:null;}
        } unset($r);
        echo json_encode(['success'=>true,'operations'=>$rows,'generated_at'=>date('Y-m-d H:i:s')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'Unable to load live operations.']);}
    exit;
}

$activeDeliveries=(int)$db->query("SELECT COUNT(*) FROM `order` WHERE completion_status IN ('Assigned','Accepted','In Transit')")->fetchColumn();
$assignedCount=(int)$db->query("SELECT COUNT(*) FROM `order` WHERE completion_status='Assigned'")->fetchColumn();
$inTransitCount=(int)$db->query("SELECT COUNT(*) FROM `order` WHERE completion_status='In Transit'")->fetchColumn();
$completedToday=(int)$db->query("SELECT COUNT(DISTINCT o.order_id) FROM `order` o INNER JOIN delivery_status ds ON ds.order_id=o.order_id AND ds.status='Completed' WHERE o.completion_status='Completed' AND DATE(ds.timestamp)=CURDATE()")->fetchColumn();
$drivers=$db->query("SELECT driver_id,full_name FROM driver WHERE status NOT IN ('Resigned','Terminated') ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$trucks=$db->query("SELECT truck_id,plate_number FROM truck WHERE status NOT IN ('Retired','Out of Service') ORDER BY plate_number")->fetchAll(PDO::FETCH_ASSOC);

$navItems=[
 ['label'=>'Dashboard','url'=>BASE_URL.'super_admin/dashboard.php'],
 ['label'=>'Operations Map','url'=>BASE_URL.'super_admin/operations_map.php'],
 ['label'=>'Manage Admins','url'=>BASE_URL.'super_admin/manage_admins.php'],
 ['label'=>'Manage Drivers','url'=>BASE_URL.'super_admin/manage_drivers.php'],
 ['label'=>'Manage Trucks','url'=>BASE_URL.'super_admin/manage_trucks.php'],
 ['label'=>'Reports','url'=>BASE_URL.'super_admin/reports.php'],
 ['label'=>'System Logs','url'=>BASE_URL.'super_admin/system_logs.php'],
 ['label'=>'System Configuration','url'=>BASE_URL.'super_admin/system_configuration.php'],
 ['label'=>'Profile','url'=>BASE_URL.'super_admin/profile.php'],
];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Operations Map · Super Admin · Hong Sing LKRR Commercial</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.operations-page{width:100%;max-width:1600px;margin:0 auto}.operations-summary{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:16px;margin-bottom:20px}.operations-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px;align-items:start}.map-panel{min-width:0}#operations-map{width:100%;height:610px;border:1px solid var(--color-border);border-radius:10px;overflow:hidden;background:#eef2f7}.operations-sidebar{position:sticky;top:20px}.filter-grid{display:grid;gap:14px}.filter-field label{display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--color-text-secondary)}.filter-field select{width:100%;min-height:42px;padding:8px 10px;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);color:var(--color-text-primary)}.map-legend{display:grid;gap:10px;margin-top:18px;padding-top:18px;border-top:1px solid var(--color-border)}.legend-row{display:flex;align-items:center;gap:10px;font-size:12px;color:var(--color-text-secondary)}.legend-dot{width:13px;height:13px;border-radius:50%;flex:0 0 13px}.legend-driver{background:#2563eb}.legend-destination{background:#dc2626}.legend-approved{background:#d97706}.refresh-status{margin-top:16px;padding-top:16px;border-top:1px solid var(--color-border);font-size:12px;color:var(--color-text-muted);line-height:1.6}.operations-table-wrap{overflow-x:auto}.operations-table{min-width:900px}.gps-fresh{color:#15803d;font-weight:600}.gps-stale{color:#b45309;font-weight:600}.gps-missing{color:var(--color-text-muted)}.custom-map-pin-wrap{background:transparent;border:0}.map-pin{width:38px;height:38px;border-radius:50% 50% 50% 8px;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 3px 10px rgba(15,23,42,.28)}.map-pin svg{width:21px;height:21px;fill:#fff;transform:rotate(45deg)}.map-pin-driver{background:#2563eb}.map-pin-customer{background:#dc2626}.map-pin-approved{background:#d97706}.popup-title{font-weight:700;margin-bottom:5px}.popup-line{margin:3px 0}.popup-status{display:inline-block;margin-top:5px;padding:3px 7px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:700}.empty-map-message{padding:12px;margin-bottom:12px;border:1px dashed var(--color-border);border-radius:8px;color:var(--color-text-secondary);font-size:13px;display:none}@media(max-width:1100px){.operations-layout{grid-template-columns:1fr}.operations-sidebar{position:static}.operations-summary{grid-template-columns:repeat(2,minmax(150px,1fr))}}@media(max-width:650px){.operations-summary{grid-template-columns:1fr}#operations-map{height:480px}}
</style></head><body><div class="app-layout"><?php require __DIR__.'/../includes/header.php'; ?><main class="app-main"><div class="operations-page">
<div class="page-header"><div><h1>Operations Map</h1><p>System-wide monitoring of active deliveries, drivers, trucks, and customer destinations.</p></div></div>
<div class="operations-summary">
<div class="card stat-card accent"><span class="stat-label">Active Deliveries</span><div class="stat-value" id="active-count"><?= $activeDeliveries ?></div></div>
<div class="card stat-card"><span class="stat-label">Assigned</span><div class="stat-value" id="assigned-count"><?= $assignedCount ?></div></div>
<div class="card stat-card"><span class="stat-label">In Transit</span><div class="stat-value" id="transit-count"><?= $inTransitCount ?></div></div>
<div class="card stat-card"><span class="stat-label">Completed Today</span><div class="stat-value"><?= $completedToday ?></div></div>
</div>
<div class="operations-layout"><div class="card panel map-panel"><div style="display:flex;justify-content:space-between;gap:15px;align-items:flex-start;flex-wrap:wrap;margin-bottom:15px"><div><h2 style="margin-bottom:5px">Live Delivery Operations</h2><p style="margin:0;color:var(--color-text-secondary);font-size:13px">Driver positions use the latest GPS coordinates received from the driver's browser.</p></div><span class="status-pill status-delivered">Live Monitoring</span></div><div id="empty-map-message" class="empty-map-message">No operations match the selected filters.</div><div id="operations-map"></div></div>
<aside class="card panel operations-sidebar"><h2>Map Filters</h2><div class="filter-grid" style="margin-top:18px">
<div class="filter-field"><label for="status-filter">Delivery Status</label><select id="status-filter"><option value="">All Active Operations</option><option>Approved</option><option>Assigned</option><option>Accepted</option><option>In Transit</option></select></div>
<div class="filter-field"><label for="driver-filter">Driver</label><select id="driver-filter"><option value="">All Drivers</option><?php foreach($drivers as $driver): ?><option value="<?= (int)$driver['driver_id'] ?>"><?= htmlspecialchars($driver['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="filter-field"><label for="truck-filter">Truck</label><select id="truck-filter"><option value="">All Trucks</option><?php foreach($trucks as $truck): ?><option value="<?= (int)$truck['truck_id'] ?>"><?= htmlspecialchars($truck['plate_number']) ?></option><?php endforeach; ?></select></div></div>
<div class="map-legend"><div class="legend-row"><span class="legend-dot legend-driver"></span>Driver / Truck GPS</div><div class="legend-row"><span class="legend-dot legend-destination"></span>Customer Destination</div><div class="legend-row"><span class="legend-dot legend-approved"></span>Destination awaiting assignment</div></div><div class="refresh-status"><strong>Automatic refresh:</strong> every 10 seconds.<br><span id="last-refresh">Waiting for live data...</span></div></aside></div>
<div class="card panel" style="margin-top:20px"><h2>Active Operations</h2><div class="operations-table-wrap" style="margin-top:15px"><table class="data-table operations-table"><thead><tr><th>Order</th><th>Customer</th><th>Driver</th><th>Truck</th><th>Status</th><th>Priority</th><th>GPS Updated</th></tr></thead><tbody id="operations-table-body"><tr><td colspan="7">Loading operations...</td></tr></tbody></table></div></div>
</div></main></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){'use strict';
const DATA_URL=<?= json_encode(BASE_URL.'super_admin/operations_map.php?ajax=operations') ?>,REFRESH_MS=10000;
const map=L.map('operations-map').setView([14.5995,120.9842],11);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
const operationLayer=L.layerGroup().addTo(map),routeLayer=L.layerGroup().addTo(map);let operations=[],hasFittedMap=false,requestRunning=false;
const statusFilter=document.getElementById('status-filter'),driverFilter=document.getElementById('driver-filter'),truckFilter=document.getElementById('truck-filter'),tableBody=document.getElementById('operations-table-body'),emptyMapMessage=document.getElementById('empty-map-message'),lastRefresh=document.getElementById('last-refresh'),activeCount=document.getElementById('active-count'),assignedCount=document.getElementById('assigned-count'),transitCount=document.getElementById('transit-count');
const driverIcon=L.divIcon({className:'custom-map-pin-wrap',html:'<div class="map-pin map-pin-driver"><svg viewBox="0 0 24 24"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h9A2.5 2.5 0 0 1 17 6.5V8h1.6a2 2 0 0 1 1.7.95l1.42 2.3c.18.3.28.65.28 1V17a2 2 0 0 1-2 2h-.55a2.75 2.75 0 0 1-5.4 0H9.95a2.75 2.75 0 0 1-5.4 0H4a2 2 0 0 1-2-2V8.5A2 2 0 0 1 3 6.77V6.5ZM5.5 6a.5.5 0 0 0-.5.5V12h10V6.5a.5.5 0 0 0-.5-.5h-9ZM17 10v2h2.73l-1.23-2H17ZM7.25 17.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Zm9.5 0a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Z"/></svg></div>',iconSize:[42,48],iconAnchor:[21,44],popupAnchor:[0,-40]});
const pin=(cls)=>L.divIcon({className:'custom-map-pin-wrap',html:'<div class="map-pin '+cls+'"><svg viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg></div>',iconSize:[38,48],iconAnchor:[19,46],popupAnchor:[0,-42]});const customerIcon=pin('map-pin-customer'),approvedIcon=pin('map-pin-approved');
function esc(v){const d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML}function coord(v){return v!==null&&v!==''&&Number.isFinite(Number(v))}
function filtered(){return operations.filter(o=>(!statusFilter.value||o.completion_status===statusFilter.value)&&(!driverFilter.value||String(o.driver_id||'')===driverFilter.value)&&(!truckFilter.value||String(o.truck_id||'')===truckFilter.value))}
function gps(v){if(!v)return{text:'No GPS data',cls:'gps-missing'};const d=new Date(String(v).replace(' ','T'));if(Number.isNaN(d.getTime()))return{text:v,cls:'gps-stale'};const m=Math.max(0,Math.floor((Date.now()-d.getTime())/60000));return m<1?{text:'Just now',cls:'gps-fresh'}:m<=2?{text:m+' min ago',cls:'gps-fresh'}:{text:m+' min ago',cls:'gps-stale'}}
function sclass(s){return s==='In Transit'?'status-transit':s==='Accepted'?'status-delivered':'status-pending'}
function renderMap(){operationLayer.clearLayers();routeLayer.clearLayers();const visible=filtered(),bounds=[];visible.forEach(o=>{const hd=coord(o.destination_latitude)&&coord(o.destination_longitude),hg=coord(o.current_latitude)&&coord(o.current_longitude);if(hd){const p=[Number(o.destination_latitude),Number(o.destination_longitude)],addr=[o.delivery_address,o.delivery_city,o.delivery_province].filter(Boolean).join(', ');L.marker(p,{icon:o.completion_status==='Approved'?approvedIcon:customerIcon}).addTo(operationLayer).bindPopup('<div class="popup-title">'+esc(o.order_number)+'</div><div class="popup-line"><strong>Customer:</strong> '+esc(o.customer_name||'—')+'</div><div class="popup-line"><strong>Destination:</strong> '+esc(addr||'—')+'</div><span class="popup-status">'+esc(o.completion_status)+'</span>');bounds.push(p)}if(hg&&o.driver_id!==null&&o.completion_status!=='Approved'){const p=[Number(o.current_latitude),Number(o.current_longitude)],g=gps(o.location_updated_at);L.marker(p,{icon:driverIcon}).addTo(operationLayer).bindPopup('<div class="popup-title">'+esc(o.order_number)+'</div><div class="popup-line"><strong>Driver:</strong> '+esc(o.driver_name||'—')+'</div><div class="popup-line"><strong>Truck:</strong> '+esc(o.plate_number||'—')+'</div><div class="popup-line"><strong>GPS:</strong> '+esc(g.text)+'</div><span class="popup-status">'+esc(o.completion_status)+'</span>');bounds.push(p);if(hd)L.polyline([p,[Number(o.destination_latitude),Number(o.destination_longitude)]],{weight:3,opacity:.55,dashArray:'7, 7'}).addTo(routeLayer)}});emptyMapMessage.style.display=visible.length?'none':'block';if(!hasFittedMap&&bounds.length){map.fitBounds(L.latLngBounds(bounds),{padding:[45,45],maxZoom:15});hasFittedMap=true}}
function renderTable(){const v=filtered();if(!v.length){tableBody.innerHTML='<tr><td colspan="7">No active operations match the selected filters.</td></tr>';return}tableBody.innerHTML=v.map(o=>{const g=gps(o.location_updated_at);return '<tr><td><strong>'+esc(o.order_number)+'</strong></td><td>'+esc(o.customer_name||'—')+'</td><td>'+esc(o.driver_name||'Not assigned')+'</td><td>'+esc(o.plate_number||'Not assigned')+'</td><td><span class="status-pill '+sclass(o.completion_status)+'">'+esc(o.completion_status)+'</span></td><td>'+esc(o.priority_level||'Normal')+'</td><td class="'+g.cls+'">'+esc(g.text)+'</td></tr>'}).join('')}
function render(){renderMap();renderTable()}function updateSummary(){assignedCount.textContent=operations.filter(o=>o.completion_status==='Assigned').length;transitCount.textContent=operations.filter(o=>o.completion_status==='In Transit').length;activeCount.textContent=operations.filter(o=>['Assigned','Accepted','In Transit'].includes(o.completion_status)).length}
async function load(){if(requestRunning)return;requestRunning=true;try{const r=await fetch(DATA_URL,{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw new Error();const d=await r.json();if(!d.success)throw new Error();operations=Array.isArray(d.operations)?d.operations:[];updateSummary();render();lastRefresh.textContent='Last refreshed: '+new Date().toLocaleTimeString()}catch(e){lastRefresh.textContent='Unable to refresh live data.'}finally{requestRunning=false}}
[statusFilter,driverFilter,truckFilter].forEach(el=>el.addEventListener('change',render));load();setInterval(load,REFRESH_MS);
});
</script></body></html>
