<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

/* ==========================================================
   DELETE TRUCK
========================================================== */

if (isset($_GET['delete'])) {

    $truckId = (int) $_GET['delete'];

    // Prevent deletion if truck is assigned to a driver
    $check = $db->prepare("
        SELECT COUNT(*)
        FROM driver
        WHERE current_truck_id = ?
    ");

    $check->execute([$truckId]);

    if ($check->fetchColumn() > 0) {

        header("Location: manage_trucks.php?error=assigned");
        exit;

    }

    $stmt = $db->prepare("
        DELETE FROM truck
        WHERE truck_id = ?
    ");

    $stmt->execute([$truckId]);

    header("Location: manage_trucks.php?success=deleted");
    exit;
}

/* ==========================================================
   ADD TRUCK
========================================================== */

if (isset($_POST['create_truck'])) {

    $plate = trim($_POST['plate_number']);
    $model = trim($_POST['model']);
    $capacity = trim($_POST['capacity']);
    $status = $_POST['status'];
    $maintenance = $_POST['last_maintenance'];

    $check = $db->prepare("
        SELECT COUNT(*)
        FROM truck
        WHERE plate_number = ?
    ");

    $check->execute([$plate]);

    if ($check->fetchColumn() == 0) {

        $stmt = $db->prepare("
            INSERT INTO truck
            (
                plate_number,
                model,
                capacity,
                status,
                last_maintenance
            )
            VALUES
            (
                ?,?,?,?,?
            )
        ");

        $stmt->execute([
            $plate,
            $model,
            $capacity,
            $status,
            $maintenance
        ]);

        header("Location: manage_trucks.php?success=added");
        exit;

    }

    $error = "Plate number already exists.";

}

/* ==========================================================
   UPDATE TRUCK
========================================================== */

if (isset($_POST['update_truck'])) {

    $truckId = (int) $_POST['truck_id'];

    $plate = trim($_POST['plate_number']);
    $model = trim($_POST['model']);
    $capacity = trim($_POST['capacity']);
    $status = $_POST['status'];
    $maintenance = $_POST['last_maintenance'];

    $duplicate = $db->prepare("
        SELECT COUNT(*)
        FROM truck
        WHERE plate_number = ?
        AND truck_id <> ?
    ");

    $duplicate->execute([
        $plate,
        $truckId
    ]);

    if ($duplicate->fetchColumn() == 0) {

        $stmt = $db->prepare("
            UPDATE truck
            SET
                plate_number=?,
                model=?,
                capacity=?,
                status=?,
                last_maintenance=?
            WHERE truck_id=?
        ");

        $stmt->execute([
            $plate,
            $model,
            $capacity,
            $status,
            $maintenance,
            $truckId
        ]);

        header("Location: manage_trucks.php?success=updated");
        exit;

    }

    $error = "Plate number already exists.";

}

/* ==========================================================
   SEARCH + FILTER
========================================================== */

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "
SELECT
    t.*,
    d.full_name AS driver_name
FROM truck t

LEFT JOIN driver d
ON d.current_truck_id = t.truck_id

WHERE 1
";

$params = [];

if ($search != '') {

    $sql .= "
    AND
    (
        t.plate_number LIKE ?
        OR
        t.model LIKE ?
        OR
        d.full_name LIKE ?
    )
    ";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";

}

if ($statusFilter != '') {

    $sql .= "
    AND t.status = ?
    ";

    $params[] = $statusFilter;

}

$sql .= "
ORDER BY t.plate_number ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$trucks = $stmt->fetchAll();

/* ==========================================================
   NAVIGATION
========================================================== */

$navItems = [

['label' => 'Dashboard', 'url' => BASE_URL . 'super_admin/dashboard.php'],
    ['label' => 'Operations Map', 'url' => BASE_URL . 'super_admin/operations_map.php'],
    ['label' => 'Manage Admins', 'url' => BASE_URL . 'super_admin/manage_admins.php'],
    ['label' => 'Manage Drivers', 'url' => BASE_URL . 'super_admin/manage_drivers.php'],
    ['label' => 'Manage Trucks', 'url' => BASE_URL . 'super_admin/manage_trucks.php'],
    ['label' => 'Reports', 'url' => BASE_URL . 'super_admin/reports.php'],
    ['label' => 'System Logs', 'url' => BASE_URL . 'super_admin/system_logs.php'],
    ['label' => 'System Configuration', 'url' => BASE_URL . 'super_admin/system_configuration.php'],
    ['label' => 'Profile', 'url' => BASE_URL . 'super_admin/profile.php'],

];
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Manage Trucks</title>

<link rel="stylesheet"
      href="<?= BASE_URL ?>assets/css/theme.css">

</head>

<body>

<div class="app-layout">

<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="app-main">

    <!-- ==========================================
         PAGE HEADER
    =========================================== -->

    <div class="page-header">

        <div>

            <h1>Manage Trucks</h1>

            <p>
                Manage the company's fleet of delivery trucks.
            </p>

        </div>

        <button
            class="btn-success"
            onclick="document.getElementById('addModal').style.display='block'">

            + Add Truck

        </button>

    </div>

    <!-- ==========================================
         SUCCESS ALERT
    =========================================== -->

    <?php if(isset($_GET['success'])): ?>

        <div class="alert-success">

            <?php

            switch($_GET['success']){

                case "added":

                    echo "Truck added successfully.";

                    break;

                case "updated":

                    echo "Truck updated successfully.";

                    break;

                case "deleted":

                    echo "Truck deleted successfully.";

                    break;

                default:

                    echo "Action completed successfully.";

            }

            ?>

        </div>

    <?php endif; ?>



    <!-- ==========================================
         ERROR ALERT
    =========================================== -->

    <?php if(isset($_GET['error']) && $_GET['error']=="assigned"): ?>

        <div class="alert-error">

            This truck cannot be deleted because it is currently assigned to a driver.

        </div>

    <?php endif; ?>


    <?php if(isset($error)): ?>

        <div class="alert-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- ==========================================
         TOOLBAR
    =========================================== -->

    <div class="card panel">

        <form
            method="GET"
            class="toolbar">

            <div class="toolbar-left">

                <input
                    type="text"
                    name="search"
                    class="form-input search-box"
                    placeholder="Search truck..."

                    value="<?= htmlspecialchars($search) ?>">


                <select
                    name="status"
                    class="form-input">

                    <option value="">All Status</option>

                    <option
                        value="Available"
                        <?= $statusFilter=="Available" ? "selected" : "" ?>>

                        Available

                    </option>

                    <option
                        value="In Use"
                        <?= $statusFilter=="In Use" ? "selected" : "" ?>>

                        In Use

                    </option>

                    <option
                        value="Maintenance"
                        <?= $statusFilter=="Maintenance" ? "selected" : "" ?>>

                        Maintenance

                    </option>

                </select>

                <button class="btn-primary">

                    Search

                </button>

            </div>

        </form>

                <!-- ==========================================
             TRUCK TABLE
        =========================================== -->

        <table class="data-table">

            <thead>

                <tr>

                    <th>Plate Number</th>

                    <th>Truck Model</th>

                    <th>Capacity (kg)</th>

                    <th>Status</th>

                    <th>Assigned Driver</th>

                    <th>Last Maintenance</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php if(empty($trucks)): ?>

                <tr>

                    <td colspan="7">

                        <div class="empty-state">

                            No trucks found.

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach($trucks as $truck): ?>

                <tr>

                    <td>

                        <strong>

                            <?= htmlspecialchars($truck['plate_number']) ?>

                        </strong>

                    </td>

                    <td>

                        <?= htmlspecialchars($truck['model']) ?>

                    </td>

                    <td>

                        <?= number_format((float)$truck['capacity'], 2) ?> kg

                    </td>

                    <td>

                        <?php

                        $statusClass = match($truck['status']){

                            'Available'   => 'status-delivered',

                            'In Use'      => 'status-transit',

                            'Maintenance' => 'status-pending',

                            default       => 'status-failed'

                        };

                        ?>

                        <span class="status-pill <?= $statusClass ?>">

                            <?= htmlspecialchars($truck['status']) ?>

                        </span>

                    </td>

                    <td>

                        <?php

                        if($truck['driver_name']){

                            echo htmlspecialchars($truck['driver_name']);

                        }else{

                            echo "<span style='color:var(--color-text-muted)'>Unassigned</span>";

                        }

                        ?>

                    </td>

                    <td>

                        <?php

                        if(!empty($truck['last_maintenance'])){

                            echo date(
                                "M d, Y",
                                strtotime($truck['last_maintenance'])
                            );

                        }else{

                            echo "-";

                        }

                        ?>

                    </td>

                    <td>

                        <div class="action-buttons">

                            <button
                                type="button"
                                class="btn-warning"

                                onclick="editTruck(

                                '<?= $truck['truck_id'] ?>',

                                '<?= htmlspecialchars($truck['plate_number'],ENT_QUOTES) ?>',

                                '<?= htmlspecialchars($truck['model'],ENT_QUOTES) ?>',

                                '<?= $truck['capacity'] ?>',

                                '<?= htmlspecialchars($truck['status'],ENT_QUOTES) ?>',

                                '<?= $truck['last_maintenance'] ?>'

                                )">

                                Edit

                            </button>

                            <a

                                href="?delete=<?= $truck['truck_id'] ?>"

                                class="btn-danger"

                                onclick="return confirm('Delete this truck?')">

                                Delete

                            </a>

                        </div>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</div>

<!-- ==========================================================
     ADD TRUCK MODAL
========================================================== -->

<div class="modal" id="addModal">

    <div class="modal-content">

        <form method="POST">

            <div class="modal-header">
                <h2>Add Truck</h2>
            </div>

            <label class="form-label">Plate Number</label>
            <input
                type="text"
                name="plate_number"
                class="form-input"
                required>

            <br><br>

            <label class="form-label">Truck Model</label>
            
            <input
                type="text"
                name="model"
                class="form-input"
                required>

            <br><br>

            <label class="form-label">Capacity (kg)</label>

            <input
                type="number"
                name="capacity"
                class="form-input"
                min="1"
                step="0.01"
                placeholder="e.g. 5000"
                class="form-input"
                required>

            <br><br>

            <label class="form-label">Status</label>

            <select
                name="status"
                class="form-input">

                <option>Available</option>
                <option>In Use</option>
                <option>Maintenance</option>

            </select>

            <br><br>

            <label class="form-label">Last Maintenance</label>

            <input
                type="date"
                name="last_maintenance"
                class="form-input">

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal()">

                    Cancel

                </button>

                <button
                    class="btn-success"
                    name="create_truck">

                    Save Truck

                </button>

            </div>

        </form>

    </div>

</div>

<!-- ==========================================================
     EDIT TRUCK MODAL
========================================================== -->

<div class="modal" id="editModal">

    <div class="modal-content">

        <form method="POST">

            <input
                type="hidden"
                name="truck_id"
                id="edit_id">

            <div class="modal-header">
                <h2>Edit Truck</h2>
            </div>

            <label class="form-label">Plate Number</label>

            <input
                id="edit_plate"
                name="plate_number"
                class="form-input"
                required>

            <br><br>

            <label class="form-label">Truck Model</label>

            <input
                id="edit_model"
                name="model"
                class="form-input"
                required>

            <br><br>

            <label class="form-label">Capacity (kg)</label>

            <input
                type="number"
                id="edit_capacity"
                name="capacity"
                class="form-input"
                min="1"
                step="0.01"
                required>

            <br><br>

            <label class="form-label">Status</label>

            <select
                id="edit_status"
                name="status"
                class="form-input">

                <option>Available</option>
                <option>In Use</option>
                <option>Maintenance</option>

            </select>

            <br><br>

            <label class="form-label">Last Maintenance</label>

            <input
                type="date"
                id="edit_last"
                name="last_maintenance"
                class="form-input">

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal()">

                    Cancel

                </button>

                <button
                    class="btn-warning"
                    name="update_truck">

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>

<script>

function closeModal(){

    document.getElementById("addModal").style.display="none";
    document.getElementById("editModal").style.display="none";

}

window.onclick=function(e){

    if(e.target.classList.contains("modal")){

        closeModal();

    }

}

function editTruck(
    id,
    plate,
    model,
    capacity,
    status,
    lastMaintenance
){

    document.getElementById("edit_id").value=id;

    document.getElementById("edit_plate").value=plate;

    document.getElementById("edit_model").value=model;

    document.getElementById("edit_capacity").value=capacity;

    document.getElementById("edit_status").value=status;

    document.getElementById("edit_last").value=lastMaintenance;

    document.getElementById("editModal").style.display="block";

}

</script>

</body>
</html>