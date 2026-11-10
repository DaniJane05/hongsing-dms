<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ml_service.php';

requireRole(ROLE_ADMIN);
$db = Database::getConnection();

/* Hong Sing LKRR Commercial – Google Maps place pin */
const WAREHOUSE_LAT = 14.6749623;
const WAREHOUSE_LNG = 120.9874442;
const WAREHOUSE_NAME = 'Hong Sing LKRR Commercial';
const ROUTING_PROVIDER = 'OSRM';
const ML_MODEL_VERSION = 'RF-TAT-v2-incidents';
const MAX_EXACT_STOPS = 12;

$errors = [];
$messages = [];
$selectedDriverId = (int)($_GET['driver_id'] ?? $_POST['driver_id'] ?? 0);
$selectedTruckId  = (int)($_GET['truck_id'] ?? $_POST['truck_id'] ?? 0);
$routePreview = null;

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getDriverHistoryFeatures(PDO $db, int $driverId, int $totalDeliveries): array {
    $out = [
        'driver_delay_rate' => 0.0,
        'driver_verified_violation_rate' => 0.0,
        'history_source' => 'none',
        'delayed_deliveries' => 0,
        'verified_violations' => 0,
        'history_deliveries' => 0,
    ];
    if ($driverId <= 0) return $out;

    try {
        $stmt = $db->prepare("SELECT
            COUNT(DISTINCT CASE WHEN estimated_delay_minutes > 0 THEN order_id END) delayed,
            COUNT(DISTINCT CASE WHEN violation_status='Verified Violation' THEN order_id END) violations
            FROM delivery_incident WHERE driver_id=?");
        $stmt->execute([$driverId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $delayed = (int)($row['delayed'] ?? 0);
        $violations = (int)($row['violations'] ?? 0);
        if ($delayed > 0 || $violations > 0) {
            $den = max(1, $totalDeliveries);
            return [
                'driver_delay_rate' => min(1.0, $delayed / $den),
                'driver_verified_violation_rate' => min(1.0, $violations / $den),
                'history_source' => 'actual',
                'delayed_deliveries' => $delayed,
                'verified_violations' => $violations,
                'history_deliveries' => $den,
            ];
        }

        $stmt = $db->prepare("SELECT completed_deliveries, delayed_deliveries, verified_violations
            FROM ml_driver_history WHERE driver_id=? LIMIT 1");
        $stmt->execute([$driverId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $den = max(1, (int)$row['completed_deliveries']);
            $delayed = min($den, max(0, (int)$row['delayed_deliveries']));
            $violations = min($den, max(0, (int)$row['verified_violations']));
            return [
                'driver_delay_rate' => $delayed / $den,
                'driver_verified_violation_rate' => $violations / $den,
                'history_source' => 'synthetic',
                'delayed_deliveries' => $delayed,
                'verified_violations' => $violations,
                'history_deliveries' => $den,
            ];
        }
    } catch (Throwable $e) {
        error_log('Driver history lookup failed: ' . $e->getMessage());
    }
    return $out;
}

function getTruckHistoryFeatures(PDO $db, int $truckId): array {
    $out = [
        'truck_breakdown_rate' => 0.0,
        'history_source' => 'none',
        'breakdown_deliveries' => 0,
        'history_deliveries' => 0,
    ];
    if ($truckId <= 0) return $out;

    try {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT order_id) FROM delivery_incident
            WHERE truck_id=? AND incident_type='Vehicle Breakdown'");
        $stmt->execute([$truckId]);
        $breakdowns = (int)$stmt->fetchColumn();
        if ($breakdowns > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `order`
                WHERE truck_id=? AND completion_status='Completed'");
            $stmt->execute([$truckId]);
            $den = max(1, (int)$stmt->fetchColumn());
            return [
                'truck_breakdown_rate' => min(1.0, $breakdowns / $den),
                'history_source' => 'actual',
                'breakdown_deliveries' => $breakdowns,
                'history_deliveries' => $den,
            ];
        }

        $stmt = $db->prepare("SELECT completed_deliveries, breakdown_deliveries
            FROM ml_truck_history WHERE truck_id=? LIMIT 1");
        $stmt->execute([$truckId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $den = max(1, (int)$row['completed_deliveries']);
            $breakdowns = min($den, max(0, (int)$row['breakdown_deliveries']));
            return [
                'truck_breakdown_rate' => $breakdowns / $den,
                'history_source' => 'synthetic',
                'breakdown_deliveries' => $breakdowns,
                'history_deliveries' => $den,
            ];
        }
    } catch (Throwable $e) {
        error_log('Truck history lookup failed: ' . $e->getMessage());
    }
    return $out;
}

function activeTruckLoad(PDO $db, int $truckId): float {
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_weight_kg),0) FROM `order`
        WHERE truck_id=? AND completion_status IN ('Assigned','Accepted','In Transit')");
    $stmt->execute([$truckId]);
    return (float)$stmt->fetchColumn();
}

function httpJson(string $url, int $timeout = 15): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for road routing.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'HongSing-DMS-Capstone/1.0',
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $err) throw new RuntimeException('Routing service connection failed: ' . $err);
    if ($status < 200 || $status >= 300) throw new RuntimeException('Routing service returned HTTP ' . $status . '.');
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Routing service returned invalid JSON.');
    return $data;
}

function osrmTable(array $points): array {
    $coords = [];
    foreach ($points as $p) $coords[] = $p['lng'] . ',' . $p['lat'];
    $url = 'https://router.project-osrm.org/table/v1/driving/' . implode(';', $coords)
         . '?annotations=distance,duration';
    $data = httpJson($url, 20);
    if (($data['code'] ?? '') !== 'Ok' || empty($data['distances']) || empty($data['durations'])) {
        throw new RuntimeException('Could not build the road distance/time matrix.');
    }
    return ['distances' => $data['distances'], 'durations' => $data['durations']];
}

function osrmRoute(array $points, array $sequence): array {
    $coords = [WAREHOUSE_LNG . ',' . WAREHOUSE_LAT];
    foreach ($sequence as $idx) $coords[] = $points[$idx]['lng'] . ',' . $points[$idx]['lat'];
    $url = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coords)
         . '?overview=full&geometries=geojson&steps=false';
    $data = httpJson($url, 20);
    if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'][0])) {
        throw new RuntimeException('Could not retrieve the optimized road geometry.');
    }
    return $data['routes'][0];
}

function sequenceCost(array $sequence, array $edgeCost): float {
    $cost = 0.0;
    $from = 0; // matrix index 0 = warehouse; stop k uses matrix index k+1
    foreach ($sequence as $stopIdx) {
        $to = $stopIdx + 1;
        $cost += (float)($edgeCost[$from][$to] ?? INF);
        $from = $to;
    }
    return $cost;
}

/* Exact open-path Held-Karp DP: starts at warehouse, no forced return. */
function optimizeExact(int $n, array $edgeCost): array {
    $full = (1 << $n) - 1;
    $dp = [];
    $parent = [];

    for ($j = 0; $j < $n; $j++) {
        $mask = 1 << $j;
        $dp[$mask][$j] = (float)$edgeCost[0][$j + 1];
        $parent[$mask][$j] = -1;
    }

    for ($mask = 1; $mask <= $full; $mask++) {
        if (!isset($dp[$mask])) continue;
        foreach ($dp[$mask] as $last => $baseCost) {
            for ($next = 0; $next < $n; $next++) {
                if ($mask & (1 << $next)) continue;
                $newMask = $mask | (1 << $next);
                $newCost = $baseCost + (float)$edgeCost[$last + 1][$next + 1];
                if (!isset($dp[$newMask][$next]) || $newCost < $dp[$newMask][$next]) {
                    $dp[$newMask][$next] = $newCost;
                    $parent[$newMask][$next] = $last;
                }
            }
        }
    }

    if (empty($dp[$full])) throw new RuntimeException('No feasible optimized sequence was found.');
    $last = array_key_first($dp[$full]);
    foreach ($dp[$full] as $j => $cost) if ($cost < $dp[$full][$last]) $last = $j;

    $sequence = [];
    $mask = $full;
    while ($last !== -1) {
        $sequence[] = $last;
        $prev = $parent[$mask][$last] ?? -1;
        $mask ^= (1 << $last);
        $last = $prev;
    }
    return array_reverse($sequence);
}

function optimizeGreedy(int $n, array $edgeCost): array {
    $remaining = range(0, $n - 1);
    $sequence = [];
    $from = 0;
    while ($remaining) {
        $bestPos = 0;
        $bestCost = INF;
        foreach ($remaining as $pos => $stopIdx) {
            $c = (float)($edgeCost[$from][$stopIdx + 1] ?? INF);
            if ($c < $bestCost) { $bestCost = $c; $bestPos = $pos; }
        }
        $chosen = $remaining[$bestPos];
        $sequence[] = $chosen;
        $from = $chosen + 1;
        array_splice($remaining, $bestPos, 1);
    }
    return $sequence;
}

function makeRouteCode(): string {
    return 'OPT-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function classifyDelayRisk(int $predictedMinutes, int $roadMinutes, int $stops): string {
    $extraPerStop = ($predictedMinutes - $roadMinutes) / max(1, $stops);
    if ($extraPerStop <= 15) return 'Low';
    if ($extraPerStop <= 30) return 'Medium';
    return 'High';
}

/* Driver/truck groups that currently have routable work. */
$batchStmt = $db->query("SELECT
        o.driver_id, o.truck_id, d.full_name driver_name, d.rating, d.total_deliveries,
        t.plate_number, t.model, t.capacity,
        COUNT(*) order_count,
        SUM(CASE WHEN dl.latitude IS NOT NULL AND dl.longitude IS NOT NULL THEN 1 ELSE 0 END) geocoded_count
    FROM `order` o
    JOIN driver d ON d.driver_id=o.driver_id
    JOIN truck t ON t.truck_id=o.truck_id
    LEFT JOIN delivery_location dl ON dl.order_id=o.order_id
    WHERE o.completion_status IN ('Assigned','Accepted')
      AND o.driver_id IS NOT NULL AND o.truck_id IS NOT NULL
    GROUP BY o.driver_id,o.truck_id,d.full_name,d.rating,d.total_deliveries,t.plate_number,t.model,t.capacity
    ORDER BY d.full_name,t.plate_number");
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedBatch = null;
$orders = [];
if ($selectedDriverId > 0 && $selectedTruckId > 0) {
    foreach ($batches as $b) {
        if ((int)$b['driver_id'] === $selectedDriverId && (int)$b['truck_id'] === $selectedTruckId) {
            $selectedBatch = $b; break;
        }
    }

    $stmt = $db->prepare("SELECT o.order_id,o.order_number,o.total_weight_kg,o.priority_level,o.completion_status,
            dl.full_address,dl.city,dl.province,dl.latitude,dl.longitude
        FROM `order` o
        JOIN delivery_location dl ON dl.order_id=o.order_id
        WHERE o.driver_id=? AND o.truck_id=?
          AND o.completion_status IN ('Assigned','Accepted')
          AND dl.latitude IS NOT NULL AND dl.longitude IS NOT NULL
        ORDER BY o.order_id ASC");
    $stmt->execute([$selectedDriverId,$selectedTruckId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Optimize preview */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['optimize_route'])) {
    if (!$selectedBatch) $errors[] = 'Please select a valid driver/truck batch.';
    if (count($orders) < 2) $errors[] = 'At least two geocoded Assigned/Accepted deliveries are required for multi-stop optimization.';

    if (!$errors) {
        try {
            $points = [[
                'name' => WAREHOUSE_NAME, 'lat' => WAREHOUSE_LAT, 'lng' => WAREHOUSE_LNG
            ]];
            foreach ($orders as $o) {
                $points[] = [
                    'name' => $o['order_number'],
                    'lat' => (float)$o['latitude'],
                    'lng' => (float)$o['longitude'],
                ];
            }

            $matrix = osrmTable($points);
            $driverHistory = getDriverHistoryFeatures($db, $selectedDriverId, (int)$selectedBatch['total_deliveries']);
            $truckHistory = getTruckHistoryFeatures($db, $selectedTruckId);
            $truckLoad = activeTruckLoad($db, $selectedTruckId);
            $driverRating = !empty($selectedBatch['rating']) ? (float)$selectedBatch['rating'] : 4.2;

            /* One ML prediction for every feasible directed edge into a delivery stop. */
            $mlInputs = [];
            $n = count($orders);
            for ($from = 0; $from <= $n; $from++) {
                for ($to = 1; $to <= $n; $to++) {
                    if ($from === $to) continue;
                    $distanceM = $matrix['distances'][$from][$to] ?? null;
                    $durationS = $matrix['durations'][$from][$to] ?? null;
                    if ($distanceM === null || $durationS === null) continue;
                    $dest = $orders[$to - 1];
                    $mlInputs[] = [
                        'edge_from' => $from,
                        'edge_to' => $to,
                        'driver_id' => $selectedDriverId,
                        'truck_id' => $selectedTruckId,
                        'weight_kg' => max(0.01, (float)$dest['total_weight_kg']),
                        'priority_level' => $dest['priority_level'],
                        'route_distance_km' => max(0.01, (float)$distanceM / 1000),
                        'estimated_route_time_minutes' => max(1, (int)round((float)$durationS / 60)),
                        'driver_rating' => $driverRating,
                        'driver_total_deliveries' => (int)$selectedBatch['total_deliveries'],
                        'driver_delay_rate' => (float)$driverHistory['driver_delay_rate'],
                        'driver_verified_violation_rate' => (float)$driverHistory['driver_verified_violation_rate'],
                        'truck_capacity_kg' => (float)$selectedBatch['capacity'],
                        'truck_current_load_kg' => $truckLoad,
                        'truck_breakdown_rate' => (float)$truckHistory['truck_breakdown_rate'],
                    ];
                }
            }

            $mlResults = predictDeliveryCombinations($mlInputs);
            $edgeCost = array_fill(0, $n + 1, array_fill(0, $n + 1, INF));
            $edgeTat = [];
            foreach ($mlResults as $r) {
                $from = (int)$r['edge_from']; $to = (int)$r['edge_to'];
                $roadMin = max(1, (int)round(($matrix['durations'][$from][$to] ?? 60) / 60));
                $tat = isset($r['predicted_tat_minutes']) && $r['predicted_tat_minutes'] !== null
                    ? max(1, (int)$r['predicted_tat_minutes']) : $roadMin;
                $edgeCost[$from][$to] = $tat;
                $edgeTat[$from][$to] = [
                    'predicted' => $tat,
                    'minimum' => $r['minimum_tat_minutes'] ?? null,
                    'maximum' => $r['maximum_tat_minutes'] ?? null,
                    'road_minutes' => $roadMin,
                    'distance_km' => (float)($matrix['distances'][$from][$to] ?? 0) / 1000,
                ];
            }

            /* Fail clearly if an edge required by optimization has no road/ML fallback cost. */
            for ($from = 0; $from <= $n; $from++) {
                for ($to = 1; $to <= $n; $to++) {
                    if ($from === $to) continue;
                    if (!is_finite($edgeCost[$from][$to])) {
                        $dur = $matrix['durations'][$from][$to] ?? null;
                        if ($dur !== null) {
                            $edgeCost[$from][$to] = max(1, (int)round($dur / 60));
                            $edgeTat[$from][$to] = [
                                'predicted' => $edgeCost[$from][$to], 'minimum' => null, 'maximum' => null,
                                'road_minutes' => $edgeCost[$from][$to],
                                'distance_km' => (float)($matrix['distances'][$from][$to] ?? 0) / 1000,
                            ];
                        }
                    }
                }
            }

            $sequence = $n <= MAX_EXACT_STOPS ? optimizeExact($n, $edgeCost) : optimizeGreedy($n, $edgeCost);
            $baseline = range(0, $n - 1);
            $optimizedPredicted = (int)round(sequenceCost($sequence, $edgeCost));
            $baselinePredicted = (int)round(sequenceCost($baseline, $edgeCost));
            $improvement = $baselinePredicted > 0
                ? max(0.0, (($baselinePredicted - $optimizedPredicted) / $baselinePredicted) * 100) : 0.0;

            $roadRoute = osrmRoute(array_slice($points, 1), $sequence);
            $roadDistanceKm = (float)$roadRoute['distance'] / 1000;
            $roadMinutes = max(1, (int)round((float)$roadRoute['duration'] / 60));
            $delayRisk = classifyDelayRisk($optimizedPredicted, $roadMinutes, $n);

            $stops = [];
            $from = 0;
            foreach ($sequence as $position => $stopIdx) {
                $matrixTo = $stopIdx + 1;
                $o = $orders[$stopIdx];
                $leg = $edgeTat[$from][$matrixTo] ?? [];
                $stops[] = [
                    'sequence' => $position + 1,
                    'order_id' => (int)$o['order_id'],
                    'order_number' => $o['order_number'],
                    'priority_level' => $o['priority_level'],
                    'weight_kg' => (float)$o['total_weight_kg'],
                    'address' => $o['full_address'],
                    'latitude' => (float)$o['latitude'],
                    'longitude' => (float)$o['longitude'],
                    'leg_distance_km' => round((float)($leg['distance_km'] ?? 0), 2),
                    'leg_road_minutes' => (int)($leg['road_minutes'] ?? 0),
                    'leg_predicted_tat_minutes' => (int)($leg['predicted'] ?? 0),
                ];
                $from = $matrixTo;
            }

            $routePreview = [
                'driver_id' => $selectedDriverId,
                'truck_id' => $selectedTruckId,
                'driver_name' => $selectedBatch['driver_name'],
                'truck_plate' => $selectedBatch['plate_number'],
                'stops' => $stops,
                'road_distance_km' => round($roadDistanceKm, 2),
                'road_minutes' => $roadMinutes,
                'predicted_minutes' => $optimizedPredicted,
                'baseline_predicted_minutes' => $baselinePredicted,
                'optimization_score' => round($improvement, 4),
                'delay_risk' => $delayRisk,
                'geometry' => $roadRoute['geometry']['coordinates'] ?? [],
                'driver_history_source' => $driverHistory['history_source'],
                'truck_history_source' => $truckHistory['history_source'],
                'driver_delay_rate' => $driverHistory['driver_delay_rate'],
                'driver_violation_rate' => $driverHistory['driver_verified_violation_rate'],
                'truck_breakdown_rate' => $truckHistory['truck_breakdown_rate'],
                'optimizer' => $n <= MAX_EXACT_STOPS ? 'Exact ML-cost sequence optimization' : 'Greedy ML-cost sequence optimization',
            ];

            $_SESSION['route_optimization_preview'] = $routePreview;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

/* Save confirmed preview */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_optimized_route'])) {
    $preview = $_SESSION['route_optimization_preview'] ?? null;
    if (!$preview || (int)$preview['driver_id'] !== $selectedDriverId || (int)$preview['truck_id'] !== $selectedTruckId) {
        $errors[] = 'The route preview expired or does not match this driver/truck. Please optimize again.';
    } else {
        try {
            $db->beginTransaction();
            $orderIds = array_map(fn($s) => (int)$s['order_id'], $preview['stops']);
            if (count($orderIds) < 2) throw new RuntimeException('At least two orders are required.');

            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $lock = $db->prepare("SELECT order_id,driver_id,truck_id,completion_status FROM `order`
                WHERE order_id IN ($placeholders) FOR UPDATE");
            $lock->execute($orderIds);
            $locked = $lock->fetchAll(PDO::FETCH_ASSOC);
            if (count($locked) !== count($orderIds)) throw new RuntimeException('One or more orders no longer exist.');
            foreach ($locked as $row) {
                if ((int)$row['driver_id'] !== $selectedDriverId || (int)$row['truck_id'] !== $selectedTruckId ||
                    !in_array($row['completion_status'], ['Assigned','Accepted'], true)) {
                    throw new RuntimeException('One or more orders changed assignment/status. Optimize the route again.');
                }
            }

            $routeCode = makeRouteCode();
            $stopJson = json_encode($preview['stops'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pathDetails = WAREHOUSE_NAME . ' -> ' . implode(' -> ', array_map(fn($s) => $s['order_number'], $preview['stops']));
            $estimatedTime = $preview['road_minutes'] . ' minutes';

            $stmt = $db->prepare("INSERT INTO route
                (route_code,route_type,path_details,estimated_distance,estimated_time,estimated_time_minutes,
                 predicted_time_minutes,delay_risk,optimization_score,waypoints,stop_sequence,routing_provider,model_version)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $routeCode, 'Optimized', $pathDetails, $preview['road_distance_km'], $estimatedTime,
                $preview['road_minutes'], $preview['predicted_minutes'], $preview['delay_risk'],
                $preview['optimization_score'], count($preview['stops']), $stopJson, ROUTING_PROVIDER, ML_MODEL_VERSION
            ]);
            $routeId = (int)$db->lastInsertId();

            $update = $db->prepare("UPDATE `order` SET route_id=? WHERE order_id=?");
            foreach ($orderIds as $oid) $update->execute([$routeId, $oid]);

            $db->commit();
            unset($_SESSION['route_optimization_preview']);
            header('Location: route_optimization.php?driver_id=' . $selectedDriverId . '&truck_id=' . $selectedTruckId . '&saved=1&route_id=' . $routeId);
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

if (isset($_GET['saved'])) $messages[] = 'Optimized route saved successfully and linked to the included orders.';

$navItems = [
    ['label'=>'Dashboard','url'=>BASE_URL.'admin/dashboard.php'],
    ['label'=>'Orders','url'=>BASE_URL.'admin/orders.php'],
    ['label'=>'Route Optimization','url'=>BASE_URL.'admin/route_optimization.php'],
    ['label'=>'Notifications','url'=>BASE_URL.'admin/notifications.php'],
    ['label'=>'Products','url'=>BASE_URL.'admin/products.php'],
    ['label'=>'Profile','url'=>BASE_URL.'admin/profile.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ML Route Optimization</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.route-page{max-width:1450px;margin:0 auto;padding:24px}.page-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px}.page-head h1{margin:0 0 6px}.muted{opacity:.72}.notice{padding:14px 16px;border:1px solid var(--color-border);border-radius:12px;margin-bottom:14px;background:var(--color-surface)}.notice.error{border-color:#dc2626}.notice.success{border-color:#16a34a}.grid{display:grid;grid-template-columns:minmax(300px,390px) minmax(0,1fr);gap:22px}.card{background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:18px}.card h2,.card h3{margin-top:0}.batch{display:block;padding:14px;border:1px solid var(--color-border);border-radius:12px;margin-bottom:10px;text-decoration:none;color:inherit}.batch.active{outline:2px solid var(--color-primary)}.batch strong{display:block;margin-bottom:5px}.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;border:1px solid var(--color-border);margin-right:5px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:11px 15px;cursor:pointer;text-decoration:none;background:var(--color-primary);color:#fff;font-weight:700}.btn.secondary{background:transparent;color:inherit;border:1px solid var(--color-border)}.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:15px}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.metric{border:1px solid var(--color-border);border-radius:12px;padding:13px}.metric small{display:block;opacity:.7;margin-bottom:5px}.metric strong{font-size:20px}.orders-table{width:100%;border-collapse:collapse}.orders-table th,.orders-table td{padding:10px;border-bottom:1px solid var(--color-border);text-align:left;vertical-align:top}.stop{display:grid;grid-template-columns:42px 1fr;gap:12px;padding:13px 0;border-bottom:1px solid var(--color-border)}.stop-num{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--color-primary);color:#fff;font-weight:800}.stop-meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:7px}.map{height:520px;border-radius:14px;overflow:hidden;border:1px solid var(--color-border);margin-top:15px}.warehouse{padding:12px;border-radius:10px;border:1px dashed var(--color-border);margin-bottom:10px}.history-note{font-size:13px;opacity:.8;margin-top:10px}.empty{padding:28px;text-align:center;border:1px dashed var(--color-border);border-radius:12px}.risk-Low{color:#15803d}.risk-Medium{color:#b45309}.risk-High{color:#b91c1c}@media(max-width:1000px){.grid{grid-template-columns:1fr}.summary{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.route-page{padding:14px}.summary{grid-template-columns:1fr}.orders-table{font-size:13px}.map{height:400px}}
</style>
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<main class="app-main"><div class="route-page">
<div class="page-head"><div><h1>ML Route Optimization</h1><p class="muted">Recommend an efficient multi-stop delivery sequence using road travel data and the Random Forest TAT model.</p></div></div>

<?php foreach ($errors as $e): ?><div class="notice error"><?= h($e) ?></div><?php endforeach; ?>
<?php foreach ($messages as $m): ?><div class="notice success"><?= h($m) ?><?php if(isset($_GET['route_id'])): ?> Route ID: <?= (int)$_GET['route_id'] ?>.<?php endif; ?></div><?php endforeach; ?>

<div class="grid">
<aside class="card">
<h2>Delivery Batches</h2>
<p class="muted">Choose a driver/truck combination. Only Assigned and Accepted deliveries are included.</p>
<?php if (!$batches): ?><div class="empty">No assigned delivery batches are currently available.</div><?php endif; ?>
<?php foreach ($batches as $b):
    $active=((int)$b['driver_id']===$selectedDriverId && (int)$b['truck_id']===$selectedTruckId);
?>
<a class="batch <?= $active?'active':'' ?>" href="?driver_id=<?= (int)$b['driver_id'] ?>&truck_id=<?= (int)$b['truck_id'] ?>">
<strong><?= h($b['driver_name']) ?> · <?= h($b['plate_number']) ?></strong>
<span class="badge"><?= (int)$b['order_count'] ?> deliveries</span>
<span class="badge"><?= (int)$b['geocoded_count'] ?> geocoded</span>
<div class="muted" style="margin-top:7px"><?= h($b['model']) ?> · Capacity <?= number_format((float)$b['capacity'],2) ?> kg</div>
</a>
<?php endforeach; ?>
</aside>

<section>
<?php if (!$selectedBatch): ?>
<div class="card empty"><h2>Select a delivery batch</h2><p>Choose a driver and truck from the left to review its stops and generate an optimized route.</p></div>
<?php else: ?>
<div class="card">
<h2><?= h($selectedBatch['driver_name']) ?> · <?= h($selectedBatch['plate_number']) ?></h2>
<div class="warehouse"><strong>START — <?= h(WAREHOUSE_NAME) ?></strong><br><span class="muted">14.6749623, 120.9874442</span></div>
<?php if ($orders): ?>
<table class="orders-table"><thead><tr><th>Order</th><th>Destination</th><th>Priority</th><th>Weight</th></tr></thead><tbody>
<?php foreach($orders as $o): ?><tr><td><strong><?= h($o['order_number']) ?></strong><br><span class="muted"><?= h($o['completion_status']) ?></span></td><td><?= h($o['full_address']) ?><br><span class="muted"><?= h($o['latitude']) ?>, <?= h($o['longitude']) ?></span></td><td><?= h($o['priority_level']) ?></td><td><?= number_format((float)$o['total_weight_kg'],2) ?> kg</td></tr><?php endforeach; ?>
</tbody></table>
<form method="post"><input type="hidden" name="driver_id" value="<?= $selectedDriverId ?>"><input type="hidden" name="truck_id" value="<?= $selectedTruckId ?>"><div class="btn-row"><button class="btn" name="optimize_route" value="1">Generate ML-Optimized Route</button></div></form>
<?php else: ?><div class="empty">This batch has no Assigned/Accepted deliveries with valid coordinates.</div><?php endif; ?>
</div>

<?php if ($routePreview): ?>
<div class="card" style="margin-top:22px">
<h2>Recommended Route</h2>
<p class="muted">The sequence minimizes the sum of ML-predicted TAT across feasible road legs. Admin confirmation is required before saving.</p>
<div class="summary">
<div class="metric"><small>Road Distance</small><strong><?= number_format($routePreview['road_distance_km'],2) ?> km</strong></div>
<div class="metric"><small>Road Travel Time</small><strong><?= (int)$routePreview['road_minutes'] ?> min</strong></div>
<div class="metric"><small>ML Predicted TAT</small><strong><?= (int)$routePreview['predicted_minutes'] ?> min</strong></div>
<div class="metric"><small>Delay Risk</small><strong class="risk-<?= h($routePreview['delay_risk']) ?>"><?= h($routePreview['delay_risk']) ?></strong></div>
</div>
<div class="summary">
<div class="metric"><small>Default Sequence TAT</small><strong><?= (int)$routePreview['baseline_predicted_minutes'] ?> min</strong></div>
<div class="metric"><small>Optimization Improvement</small><strong><?= number_format((float)$routePreview['optimization_score'],2) ?>%</strong></div>
<div class="metric"><small>Driver Delay Rate</small><strong><?= number_format($routePreview['driver_delay_rate']*100,1) ?>%</strong></div>
<div class="metric"><small>Truck Breakdown Rate</small><strong><?= number_format($routePreview['truck_breakdown_rate']*100,1) ?>%</strong></div>
</div>
<p class="history-note">Driver history source: <strong><?= h(ucfirst($routePreview['driver_history_source'])) ?></strong> · Truck history source: <strong><?= h(ucfirst($routePreview['truck_history_source'])) ?></strong> · Optimizer: <?= h($routePreview['optimizer']) ?>. Synthetic history is used only when actual operational history is unavailable.</p>

<h3 style="margin-top:22px">Stop Sequence</h3>
<div class="warehouse"><strong>START — <?= h(WAREHOUSE_NAME) ?></strong></div>
<?php foreach($routePreview['stops'] as $s): ?><div class="stop"><div class="stop-num"><?= (int)$s['sequence'] ?></div><div><strong><?= h($s['order_number']) ?></strong><div><?= h($s['address']) ?></div><div class="stop-meta"><span class="badge"><?= h($s['priority_level']) ?></span><span class="badge"><?= number_format((float)$s['weight_kg'],2) ?> kg</span><span class="badge">Leg <?= number_format((float)$s['leg_distance_km'],2) ?> km</span><span class="badge">Road <?= (int)$s['leg_road_minutes'] ?> min</span><span class="badge">ML TAT <?= (int)$s['leg_predicted_tat_minutes'] ?> min</span></div></div></div><?php endforeach; ?>
<div id="route-map" class="map"></div>
<form method="post"><input type="hidden" name="driver_id" value="<?= $selectedDriverId ?>"><input type="hidden" name="truck_id" value="<?= $selectedTruckId ?>"><div class="btn-row"><button class="btn" name="save_optimized_route" value="1">Confirm & Save Optimized Route</button><button class="btn secondary" name="optimize_route" value="1">Recalculate</button></div></form>
</div>
<?php endif; ?>
<?php endif; ?>
</section>
</div></div></main>

<?php if ($routePreview): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const preview = <?= json_encode($routePreview, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const map = L.map('route-map').setView([<?= WAREHOUSE_LAT ?>, <?= WAREHOUSE_LNG ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
const bounds=[];
L.marker([<?= WAREHOUSE_LAT ?>,<?= WAREHOUSE_LNG ?>]).addTo(map).bindPopup('<strong>START</strong><br>Hong Sing LKRR Commercial');
bounds.push([<?= WAREHOUSE_LAT ?>,<?= WAREHOUSE_LNG ?>]);
preview.stops.forEach(s=>{const icon=L.divIcon({className:'',html:`<div style="width:30px;height:30px;border-radius:50%;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;border:2px solid white;box-shadow:0 1px 5px #555">${s.sequence}</div>`,iconSize:[30,30],iconAnchor:[15,15]});L.marker([s.latitude,s.longitude],{icon}).addTo(map).bindPopup(`<strong>Stop ${s.sequence}</strong><br>${s.order_number}<br>${s.address}`);bounds.push([s.latitude,s.longitude]);});
if (preview.geometry && preview.geometry.length) {const latlngs=preview.geometry.map(c=>[c[1],c[0]]);L.polyline(latlngs,{weight:5,opacity:.8}).addTo(map);latlngs.forEach(p=>bounds.push(p));}
if(bounds.length) map.fitBounds(bounds,{padding:[35,35]});
</script>
<?php endif; ?>
</body></html>
