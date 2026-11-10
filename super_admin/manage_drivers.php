<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_log.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

$error = '';


/* ==========================================================
   ALLOWED DRIVER STATUSES
========================================================== */

$allowedStatuses = [
    'Available',
    'On Delivery',
    'On Leave',
    'Absent',
    'Inactive',
    'Resigned',
    'Terminated'
];


/* ==========================================================
   ADD DRIVER
========================================================== */

if (isset($_POST['create_driver'])) {

    $license = trim($_POST['license_number'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';


    if (
        $license === '' ||
        $name === '' ||
        $phone === '' ||
        $email === '' ||
        $password === ''
    ) {

        $error = 'Please complete all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif (strlen($password) < 8) {

        $error = 'Password must be at least 8 characters long.';

    } else {

        /* Check duplicate email / license */

        $check = $db->prepare("
            SELECT driver_id
            FROM driver
            WHERE email = ?
               OR license_number = ?
            LIMIT 1
        ");

        $check->execute([
            $email,
            $license
        ]);


        if ($check->fetch()) {

            $error = 'Email or License Number already exists.';

        } else {

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /* New drivers start as Available */

            $stmt = $db->prepare("
                INSERT INTO driver
                (
                    license_number,
                    full_name,
                    phone_number,
                    email,
                    password_hash,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Available'
                )
            ");

            $stmt->execute([
                $license,
                $name,
                $phone,
                $email,
                $hash
            ]);


            $newDriverId =
                (int) $db->lastInsertId();


            /* Audit Log */

            logAudit(
                currentUserId(),
                'CREATE',
                'driver',
                $newDriverId,
                'Created driver: ' . $name
            );


            header(
                'Location: manage_drivers.php?success=added'
            );

            exit;
        }
    }
}


/* ==========================================================
   UPDATE DRIVER
========================================================== */

if (isset($_POST['update_driver'])) {

    $id =
        (int) ($_POST['driver_id'] ?? 0);

    $license =
        trim($_POST['license_number'] ?? '');

    $name =
        trim($_POST['full_name'] ?? '');

    $phone =
        trim($_POST['phone_number'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $status =
        trim($_POST['status'] ?? 'Available');


    /* Validate */

    if (
        $id <= 0 ||
        $license === '' ||
        $name === '' ||
        $phone === '' ||
        $email === ''
    ) {

        $error =
            'Please complete all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error =
            'Please enter a valid email address.';

    } elseif (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $error =
            'Invalid driver status.';

    } else {

        /* Get current Driver data */

        $oldStmt = $db->prepare("
            SELECT
                driver_id,
                license_number,
                full_name,
                phone_number,
                email,
                status
            FROM driver
            WHERE driver_id = ?
            LIMIT 1
        ");

        $oldStmt->execute([
            $id
        ]);

        $oldDriver =
            $oldStmt->fetch();


        if (!$oldDriver) {

            $error =
                'Driver account not found.';

        } else {

            /* Check duplicate email / license */

            $check = $db->prepare("
                SELECT driver_id
                FROM driver
                WHERE
                    (
                        email = ?
                        OR license_number = ?
                    )
                    AND driver_id <> ?
                LIMIT 1
            ");

            $check->execute([
                $email,
                $license,
                $id
            ]);


            if ($check->fetch()) {

                $error =
                    'Email or License Number already exists.';

            } else {

                /*
                 * UPDATE ONLY.
                 *
                 * No DELETE operation is used.
                 * Driver history remains in the database.
                 */

                $stmt = $db->prepare("
                    UPDATE driver
                    SET
                        license_number = ?,
                        full_name = ?,
                        phone_number = ?,
                        email = ?,
                        status = ?
                    WHERE driver_id = ?
                ");

                $stmt->execute([
                    $license,
                    $name,
                    $phone,
                    $email,
                    $status,
                    $id
                ]);


                /* Build Audit Description */

                $changes = [];


                if (
                    $oldDriver['license_number'] !==
                    $license
                ) {

                    $changes[] =
                        'License changed from "' .
                        $oldDriver['license_number'] .
                        '" to "' .
                        $license .
                        '"';
                }


                if (
                    $oldDriver['full_name'] !==
                    $name
                ) {

                    $changes[] =
                        'Name changed from "' .
                        $oldDriver['full_name'] .
                        '" to "' .
                        $name .
                        '"';
                }


                if (
                    $oldDriver['phone_number'] !==
                    $phone
                ) {

                    $changes[] =
                        'Phone number changed';
                }


                if (
                    $oldDriver['email'] !==
                    $email
                ) {

                    $changes[] =
                        'Email changed from "' .
                        $oldDriver['email'] .
                        '" to "' .
                        $email .
                        '"';
                }


                if (
                    $oldDriver['status'] !==
                    $status
                ) {

                    $changes[] =
                        'Status changed from "' .
                        $oldDriver['status'] .
                        '" to "' .
                        $status .
                        '"';
                }


                if (empty($changes)) {

                    $description =
                        'Driver record reviewed/saved: ' .
                        $name;

                } else {

                    $description =
                        'Updated driver: ' .
                        $name .
                        ' | ' .
                        implode(
                            ' | ',
                            $changes
                        );
                }


                /* Audit Log */

                logAudit(
                    currentUserId(),
                    'UPDATE',
                    'driver',
                    $id,
                    $description
                );


                header(
                    'Location: manage_drivers.php?success=updated'
                );

                exit;
            }
        }
    }
}


/* ==========================================================
   SEARCH + STATUS FILTER
========================================================== */

$search =
    trim($_GET['search'] ?? '');

$statusFilter =
    trim($_GET['status'] ?? '');


/* ==========================================================
   DRIVER QUERY + CUSTOMER RATING
==========================================================

   Rating is calculated from customer feedback:

   AVG(f.rating)

========================================================== */

$sql = "
    SELECT

        d.*,

        t.plate_number,

        COALESCE(
            AVG(f.rating),
            0
        ) AS overall_rating,

        COUNT(
            f.feedback_id
        ) AS feedback_count

    FROM driver d

    LEFT JOIN truck t
        ON d.current_truck_id = t.truck_id

    LEFT JOIN feedback f
        ON f.driver_id = d.driver_id

    WHERE 1
";


$params = [];


/* Search */

if ($search !== '') {

    $sql .= "
        AND
        (
            d.full_name LIKE ?
            OR d.email LIKE ?
            OR d.license_number LIKE ?
            OR d.phone_number LIKE ?
        )
    ";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}


/* Status Filter */

if (
    $statusFilter !== '' &&
    in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $sql .= "
        AND d.status = ?
    ";

    $params[] =
        $statusFilter;
}


/* Group + Order */

$sql .= "
    GROUP BY
        d.driver_id

    ORDER BY
        d.created_at DESC
";


$stmt =
    $db->prepare($sql);

$stmt->execute(
    $params
);

$drivers =
    $stmt->fetchAll();


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

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Manage Drivers
</title>


<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>


<style>

/* ==========================================================
   DRIVER RATING
========================================================== */

.driver-rating {
    display: inline-flex;
    flex-direction: column;
    gap: 3px;
}


.driver-rating-main {
    font-weight: 700;
    white-space: nowrap;
}


.driver-rating-stars {
    color: #f59e0b;
    letter-spacing: 1px;
}


.driver-rating-count {
    font-size: 11px;
    color: var(--color-text-secondary);
    white-space: nowrap;
}


.no-rating {
    color: var(--color-text-muted);
    font-size: 13px;
}


/* ==========================================================
   SEARCH / FILTER
========================================================== */

.driver-filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    width: 100%;
}


.driver-filter-row .search-box {
    min-width: 240px;
}


.driver-filter-row select {
    min-width: 170px;
}


/* ==========================================================
   DRIVER MODALS
========================================================== */

.modal {

    position: fixed;

    inset: 0;

    z-index: 9999;

    display: none;

    overflow-y: auto;

    padding: 30px 20px;

    background:
        rgba(15, 23, 42, 0.48);
}


/*
   The modal card is limited to the viewport.

   If the screen is shorter than the form,
   only the card becomes scrollable.
*/
.modal-content {

    position: relative;

    width: 100%;

    max-width: 560px;

    max-height:
        calc(100vh - 60px);

    margin: 0 auto;

    padding: 0;

    overflow-y: auto;

    background:
        var(
            --color-surface,
            #ffffff
        );

    border:
        1px solid
        var(
            --color-border,
            #e2e8f0
        );

    border-radius: 16px;

    box-shadow:
        0 20px 50px
        rgba(15, 23, 42, 0.20);
}


/* Modal form */

.modal-content form {

    padding:
        26px
        30px
        24px;
}


/* ==========================================================
   MODAL HEADER
========================================================== */

.modal-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 16px;

    margin-bottom: 22px;

    padding-bottom: 16px;

    border-bottom:
        1px solid
        var(
            --color-border,
            #e2e8f0
        );
}


.modal-header h2 {

    margin: 0;

    font-size: 1.45rem;

    line-height: 1.2;

    color:
        var(
            --color-text-primary,
            #0f172a
        );
}


/* Close Button */

.modal-close {

    width: 42px;

    height: 42px;

    min-width: 42px;

    padding: 0;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border:
        1px solid
        var(
            --color-border,
            #cbd5e1
        );

    border-radius: 8px;

    background: transparent;

    color:
        var(
            --color-text-primary,
            #0f172a
        );

    font-size: 20px;

    line-height: 1;

    cursor: pointer;
}


.modal-close:hover {

    background: #f1f5f9;
}


/* ==========================================================
   MODAL FORM GROUPS
========================================================== */

.modal-field {

    margin-bottom: 17px;
}


.modal-field .form-label {

    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 700;

    line-height: 1.2;

    text-transform: uppercase;

    letter-spacing: 0.04em;
}


.modal-field .form-input {

    display: block;

    width: 100%;

    box-sizing: border-box;

    margin: 0;
}


/* ==========================================================
   STATUS HELP
========================================================== */

.status-help {

    margin-top: 8px;

    padding: 9px 11px;

    border:
        1px solid
        var(
            --color-border,
            #e2e8f0
        );

    border-radius: 8px;

    background: #f8fafc;

    color:
        var(
            --color-text-secondary,
            #64748b
        );

    font-size: 12px;

    line-height: 1.45;
}


/* ==========================================================
   MODAL FOOTER
========================================================== */

.modal-footer {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 22px;

    padding-top: 18px;

    border-top:
        1px solid
        var(
            --color-border,
            #e2e8f0
        );
}


/* ==========================================================
   MODAL SCROLLBAR
========================================================== */

.modal-content::-webkit-scrollbar {
    width: 7px;
}


.modal-content::-webkit-scrollbar-track {
    background: transparent;
}


.modal-content::-webkit-scrollbar-thumb {

    background: #cbd5e1;

    border-radius: 10px;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (max-width: 768px) {

    .driver-filter-row {

        flex-direction: column;

        align-items: stretch;
    }


    .driver-filter-row .form-input,
    .driver-filter-row .search-box {

        width: 100%;

        min-width: 0;
    }
}


@media (max-width: 600px) {

    .modal {

        padding: 15px;
    }


    .modal-content {

        max-width: 100%;

        max-height:
            calc(100vh - 30px);

        border-radius: 12px;
    }


    .modal-content form {

        padding:
            22px
            18px;
    }


    .modal-footer {

        flex-direction:
            column-reverse;
    }


    .modal-footer button {

        width: 100%;
    }
}

</style>

</head>


<body>


<div class="app-layout">


<?php

require
    __DIR__ .
    '/../includes/header.php';

?>


<main class="app-main">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="page-header">


    <div>

        <h1>
            Manage Drivers
        </h1>

        <p>
            Create, update and monitor delivery drivers.
        </p>

    </div>


    <button
        type="button"
        class="btn-success"
        onclick="openAddModal()"
    >

        + Add Driver

    </button>


</div>


<!-- ==========================================================
     SUCCESS MESSAGE
========================================================== -->

<?php if (
    isset($_GET['success'])
): ?>

    <div
        class="alert-success"
        style="
            margin-bottom:15px;
            padding:12px 15px;
            border-radius:8px;
        "
    >

        <?php

        switch ($_GET['success']) {

            case 'added':

                echo
                    'Driver added successfully.';

                break;


            case 'updated':

                echo
                    'Driver updated successfully.';

                break;


            default:

                echo
                    'Action completed successfully.';

                break;
        }

        ?>

    </div>

<?php endif; ?>


<!-- ==========================================================
     PHP ERROR
========================================================== -->

<?php if ($error): ?>

    <div class="alert-error">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!-- ==========================================================
     DRIVER TABLE
========================================================== -->

<div class="card panel">


    <!-- SEARCH + STATUS FILTER -->

    <form
        class="toolbar"
        method="GET"
    >


        <div class="driver-filter-row">


            <input
                type="text"
                name="search"
                class="form-input search-box"
                placeholder="Search driver..."
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <select
                name="status"
                class="form-input"
            >

                <option value="">
                    All Status
                </option>


                <?php foreach (
                    $allowedStatuses
                    as $driverStatus
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $driverStatus,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= (
                            $statusFilter ===
                            $driverStatus
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $driverStatus,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>

                <?php endforeach; ?>


            </select>


            <button
                type="submit"
                class="btn-primary"
            >

                Search

            </button>


            <?php if (
                $search !== '' ||
                $statusFilter !== ''
            ): ?>

                <a
                    href="manage_drivers.php"
                    class="btn-secondary"
                >

                    Clear

                </a>

            <?php endif; ?>


        </div>


    </form>


    <!-- TABLE -->

    <div style="overflow-x:auto;">


        <table class="data-table">


            <thead>

            <tr>

                <th>
                    Driver
                </th>

                <th>
                    License
                </th>

                <th>
                    Phone
                </th>

                <th>
                    Status
                </th>

                <th>
                    Truck
                </th>

                <th>
                    Deliveries
                </th>

                <th>
                    Overall Rating
                </th>

                <th>
                    Last Location
                </th>

                <th>
                    Actions
                </th>

            </tr>

            </thead>


            <tbody>


            <?php if (
                empty($drivers)
            ): ?>


                <tr>

                    <td colspan="9">

                        <div class="empty-state">

                            No drivers found.

                        </div>

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach (
                    $drivers
                    as $driver
                ): ?>


                    <?php

                    /* Status visual */

                    switch ($driver['status']) {

                        case 'Available':

                            $statusClass =
                                'status-delivered';

                            break;


                        case 'On Delivery':

                            $statusClass =
                                'status-transit';

                            break;


                        case 'Absent':

                        case 'Terminated':

                            $statusClass =
                                'status-failed';

                            break;


                        case 'On Leave':

                        case 'Inactive':

                        case 'Resigned':

                        default:

                            $statusClass =
                                'status-pending';

                            break;
                    }

                    ?>


                    <tr>


                        <!-- DRIVER -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $driver['full_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>


                            <br>


                            <small>

                                <?= htmlspecialchars(
                                    $driver['email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </small>

                        </td>


                        <!-- LICENSE -->

                        <td>

                            <?= htmlspecialchars(
                                $driver['license_number'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- PHONE -->

                        <td>

                            <?= htmlspecialchars(
                                $driver['phone_number'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    status-pill
                                    <?= $statusClass ?>
                                "
                            >

                                <?= htmlspecialchars(
                                    $driver['status'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </td>


                        <!-- TRUCK -->

                        <td>

                            <?php if (
                                !empty(
                                    $driver['plate_number']
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $driver['plate_number'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <!-- DELIVERIES -->

                        <td>

                            <?= (int) (
                                $driver[
                                    'total_deliveries'
                                ] ?? 0
                            ) ?>

                        </td>


                        <!-- RATING -->

                        <td>

                            <?php

                            $feedbackCount =
                                (int) (
                                    $driver[
                                        'feedback_count'
                                    ] ?? 0
                                );

                            $overallRating =
                                (float) (
                                    $driver[
                                        'overall_rating'
                                    ] ?? 0
                                );

                            ?>


                            <?php if (
                                $feedbackCount > 0
                            ): ?>


                                <div
                                    class="driver-rating"
                                >


                                    <div
                                        class="driver-rating-main"
                                    >

                                        <span
                                            class="driver-rating-stars"
                                        >
                                            ★
                                        </span>


                                        <?= number_format(
                                            $overallRating,
                                            1
                                        ) ?>

                                        /5

                                    </div>


                                    <div
                                        class="driver-rating-count"
                                    >

                                        <?= $feedbackCount ?>

                                        feedback<?= (
                                            $feedbackCount === 1
                                        )
                                            ? ''
                                            : 's'
                                        ?>

                                    </div>


                                </div>


                            <?php else: ?>


                                <span class="no-rating">

                                    No ratings yet

                                </span>


                            <?php endif; ?>

                        </td>


                        <!-- LAST LOCATION -->

                        <td>

                            <?php if (
                                !empty(
                                    $driver['last_location']
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $driver['last_location'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <div class="action-buttons">


                                <!--
                                    EDIT ONLY.
                                    No Delete button.
                                -->

                                <button
                                    type="button"
                                    class="btn-warning"
                                    onclick='editDriver(
                                        <?= json_encode(
                                            (int) $driver['driver_id']
                                        ) ?>,
                                        <?= json_encode(
                                            $driver['license_number']
                                        ) ?>,
                                        <?= json_encode(
                                            $driver['full_name']
                                        ) ?>,
                                        <?= json_encode(
                                            $driver['phone_number']
                                        ) ?>,
                                        <?= json_encode(
                                            $driver['email']
                                        ) ?>,
                                        <?= json_encode(
                                            $driver['status']
                                        ) ?>
                                    )'
                                >

                                    Edit

                                </button>


                            </div>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php endif; ?>


            </tbody>


        </table>


    </div>


</div>


</main>


</div>


<!-- ==========================================================
     ADD DRIVER MODAL
========================================================== -->

<div
    class="modal"
    id="addModal"
>


    <div class="modal-content">


        <form
            method="POST"
            action=""
        >


            <div class="modal-header">


                <h2>
                    Add Driver
                </h2>


                <button
                    type="button"
                    class="modal-close"
                    onclick="closeModal()"
                    aria-label="Close"
                >

                    ×

                </button>


            </div>


            <!-- LICENSE -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="add_license"
                >

                    License Number

                </label>


                <input
                    type="text"
                    id="add_license"
                    name="license_number"
                    class="form-input"
                    required
                >

            </div>


            <!-- FULL NAME -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="add_name"
                >

                    Full Name

                </label>


                <input
                    type="text"
                    id="add_name"
                    name="full_name"
                    class="form-input"
                    required
                >

            </div>


            <!-- PHONE -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="add_phone"
                >

                    Phone Number

                </label>


                <input
                    type="text"
                    id="add_phone"
                    name="phone_number"
                    class="form-input"
                    required
                >

            </div>


            <!-- EMAIL -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="add_email"
                >

                    Email

                </label>


                <input
                    type="email"
                    id="add_email"
                    name="email"
                    class="form-input"
                    required
                >

            </div>


            <!-- PASSWORD -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="add_password"
                >

                    Password

                </label>


                <input
                    type="password"
                    id="add_password"
                    name="password"
                    class="form-input"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >


                <div class="status-help">

                    Password must contain at least
                    8 characters.

                    New drivers are automatically
                    created with an
                    <strong>Available</strong>
                    status.

                </div>

            </div>


            <!-- FOOTER -->

            <div class="modal-footer">


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn-success"
                    name="create_driver"
                >

                    Create Driver

                </button>


            </div>


        </form>


    </div>


</div>


<!-- ==========================================================
     EDIT DRIVER MODAL
========================================================== -->

<div
    class="modal"
    id="editModal"
>


    <div class="modal-content">


        <form
            method="POST"
            action=""
        >


            <input
                type="hidden"
                name="driver_id"
                id="edit_driver_id"
            >


            <!-- HEADER -->

            <div class="modal-header">


                <h2>
                    Edit Driver
                </h2>


                <button
                    type="button"
                    class="modal-close"
                    onclick="closeModal()"
                    aria-label="Close"
                >

                    ×

                </button>


            </div>


            <!-- LICENSE -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="edit_license"
                >

                    License Number

                </label>


                <input
                    type="text"
                    class="form-input"
                    id="edit_license"
                    name="license_number"
                    required
                >

            </div>


            <!-- FULL NAME -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="edit_name"
                >

                    Full Name

                </label>


                <input
                    type="text"
                    class="form-input"
                    id="edit_name"
                    name="full_name"
                    required
                >

            </div>


            <!-- PHONE -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="edit_phone"
                >

                    Phone Number

                </label>


                <input
                    type="text"
                    class="form-input"
                    id="edit_phone"
                    name="phone_number"
                    required
                >

            </div>


            <!-- EMAIL -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="edit_email"
                >

                    Email

                </label>


                <input
                    type="email"
                    class="form-input"
                    id="edit_email"
                    name="email"
                    required
                >

            </div>


            <!-- STATUS -->

            <div class="modal-field">

                <label
                    class="form-label"
                    for="edit_status"
                >

                    Driver Status

                </label>


                <select
                    class="form-input"
                    id="edit_status"
                    name="status"
                    required
                >


                    <option value="Available">
                        Available
                    </option>


                    <option value="On Delivery">
                        On Delivery
                    </option>


                    <option value="On Leave">
                        On Leave
                    </option>


                    <option value="Absent">
                        Absent
                    </option>


                    <option value="Inactive">
                        Inactive
                    </option>


                    <option value="Resigned">
                        Resigned
                    </option>


                    <option value="Terminated">
                        Terminated
                    </option>


                </select>


                <div
                    class="status-help"
                    id="statusHelp"
                >

                    Available drivers can be considered
                    for delivery assignment.

                </div>

            </div>


            <!-- FOOTER -->

            <div class="modal-footer">


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn-warning"
                    name="update_driver"
                >

                    Save Changes

                </button>


            </div>


        </form>


    </div>


</div>


<!-- ==========================================================
     JAVASCRIPT
========================================================== -->

<script>


/* ==========================================================
   OPEN ADD DRIVER
========================================================== */

function openAddModal()
{

    const modal =
        document.getElementById(
            'addModal'
        );


    if (modal) {

        modal.style.display =
            'block';

        document.body.style.overflow =
            'hidden';
    }
}


/* ==========================================================
   EDIT DRIVER
========================================================== */

function editDriver(
    id,
    license,
    name,
    phone,
    email,
    status
) {

    document.getElementById(
        'edit_driver_id'
    ).value = id;


    document.getElementById(
        'edit_license'
    ).value = license;


    document.getElementById(
        'edit_name'
    ).value = name;


    document.getElementById(
        'edit_phone'
    ).value = phone;


    document.getElementById(
        'edit_email'
    ).value = email;


    document.getElementById(
        'edit_status'
    ).value = status;


    updateStatusHelp();


    const modal =
        document.getElementById(
            'editModal'
        );


    modal.style.display =
        'block';


    /*
     * Prevent the background page from
     * scrolling while modal is open.
     */
    document.body.style.overflow =
        'hidden';


    /*
     * Always open the modal from the top.
     */
    const modalContent =
        modal.querySelector(
            '.modal-content'
        );


    if (modalContent) {

        modalContent.scrollTop = 0;
    }
}


/* ==========================================================
   STATUS HELP
========================================================== */

function updateStatusHelp()
{

    const statusElement =
        document.getElementById(
            'edit_status'
        );


    const help =
        document.getElementById(
            'statusHelp'
        );


    if (
        !statusElement ||
        !help
    ) {

        return;
    }


    const status =
        statusElement.value;


    const messages = {

        'Available':
            'The driver is available and can be considered for a new delivery assignment.',

        'On Delivery':
            'The driver is currently handling an active delivery.',

        'On Leave':
            'The driver is temporarily on leave and should not receive new delivery assignments.',

        'Absent':
            'The driver is currently absent and should not receive new delivery assignments.',

        'Inactive':
            'Legacy inactive status. The driver should not receive new delivery assignments.',

        'Resigned':
            'The driver has resigned. The account record remains for historical purposes, but the driver should no longer receive assignments.',

        'Terminated':
            'The driver has been terminated. The account record remains for historical purposes, but the driver should no longer receive assignments.'

    };


    help.textContent =
        messages[status] || '';
}


/* ==========================================================
   STATUS CHANGE
========================================================== */

const statusSelect =
    document.getElementById(
        'edit_status'
    );


if (statusSelect) {

    statusSelect.addEventListener(
        'change',
        updateStatusHelp
    );
}


/* ==========================================================
   CLOSE MODAL
========================================================== */

function closeModal()
{

    const addModal =
        document.getElementById(
            'addModal'
        );


    const editModal =
        document.getElementById(
            'editModal'
        );


    if (addModal) {

        addModal.style.display =
            'none';
    }


    if (editModal) {

        editModal.style.display =
            'none';
    }


    /*
     * Restore normal page scrolling.
     */
    document.body.style.overflow =
        '';
}


/* ==========================================================
   CLICK OUTSIDE
========================================================== */

window.addEventListener(
    'click',
    function(event)
    {

        if (
            event.target.classList.contains(
                'modal'
            )
        ) {

            closeModal();
        }
    }
);


/* ==========================================================
   ESC KEY
========================================================== */

document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key === 'Escape'
        ) {

            closeModal();
        }
    }
);


</script>


</body>

</html>