<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_log.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

$error = '';


/*
=====================================================
   ALLOWED ADMIN STATUSES
=====================================================
*/

$allowedStatuses = [
    'Active',
    'On Leave',
    'Absent',
    'Resigned',
    'Terminated'
];


/*
=====================================================
   ADD ADMIN
=====================================================
*/

if (isset($_POST['add_admin'])) {

    $name = trim(
        $_POST['full_name'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $password =
        $_POST['password'] ?? '';

    /*
     * New Admin accounts automatically
     * start as Active.
     */
    $status = 'Active';


    /*
    =================================================
       VALIDATION
    =================================================
    */

    if (
        $name === '' ||
        $email === '' ||
        $password === ''
    ) {

        $error =
            'Please complete all fields.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } elseif (
        strlen($password) < 8
    ) {

        $error =
            'Password must be at least 8 characters long.';

    } else {

        /*
        =============================================
           CHECK DUPLICATE EMAIL
        =============================================
        */

        $check = $db->prepare("
            SELECT user_id
            FROM system_user
            WHERE email = ?
            LIMIT 1
        ");

        $check->execute([
            $email
        ]);


        if ($check->fetch()) {

            $error =
                'Email already exists.';

        } else {

            /*
            =========================================
               HASH PASSWORD
            =========================================
            */

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /*
            =========================================
               INSERT ADMIN
            =========================================
            */

            $insert = $db->prepare("
                INSERT INTO system_user
                (
                    full_name,
                    email,
                    password_hash,
                    role,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'Admin',
                    ?
                )
            ");

            $insert->execute([
                $name,
                $email,
                $hash,
                $status
            ]);


            /*
             * Get the newly created Admin ID
             * for the audit trail.
             */
            $newAdminId =
                (int) $db->lastInsertId();


            /*
            =========================================
               AUDIT LOG
            =========================================
            */

            logAudit(
                currentUserId(),
                'CREATE',
                'Admins',
                $newAdminId,
                'Created administrator: ' .
                $name
            );


            /*
            =========================================
               REDIRECT
            =========================================
            */

            header(
                'Location: manage_admins.php?success=added'
            );

            exit;
        }
    }
}


/*
=====================================================
   UPDATE ADMIN
=====================================================
*/

if (isset($_POST['update_admin'])) {

    $id =
        (int) ($_POST['user_id'] ?? 0);

    $name = trim(
        $_POST['full_name'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $status = trim(
        $_POST['status'] ?? 'Active'
    );


    /*
    =================================================
       VALIDATION
    =================================================
    */

    if (
        $id <= 0 ||
        $name === '' ||
        $email === ''
    ) {

        $error =
            'Please complete all required fields.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } elseif (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        /*
         * Prevent invalid status values from
         * being manually submitted.
         */
        $error =
            'Invalid administrator status.';

    } else {

        /*
        =============================================
           CHECK DUPLICATE EMAIL
        =============================================
        */

        $check = $db->prepare("
            SELECT user_id
            FROM system_user
            WHERE email = ?
              AND user_id <> ?
            LIMIT 1
        ");

        $check->execute([
            $email,
            $id
        ]);


        if ($check->fetch()) {

            $error =
                'Email already exists.';

        } else {

            /*
            =========================================
               GET CURRENT ADMIN DATA
            =========================================

            Retrieve the old information before
            updating so we can record meaningful
            changes in the audit trail.
            */

            $oldStmt = $db->prepare("
                SELECT
                    user_id,
                    full_name,
                    email,
                    status
                FROM system_user
                WHERE user_id = ?
                  AND role = 'Admin'
                LIMIT 1
            ");

            $oldStmt->execute([
                $id
            ]);

            $oldAdmin =
                $oldStmt->fetch();


            if (!$oldAdmin) {

                $error =
                    'Administrator account not found.';

            } else {

                /*
                =====================================
                   UPDATE ADMIN
                =====================================

                IMPORTANT:
                No DELETE operation is used.

                Admin records remain permanently
                stored for historical/audit purposes.
                */

                $update = $db->prepare("
                    UPDATE system_user
                    SET
                        full_name = ?,
                        email = ?,
                        status = ?
                    WHERE user_id = ?
                      AND role = 'Admin'
                ");

                $update->execute([
                    $name,
                    $email,
                    $status,
                    $id
                ]);


                /*
                =====================================
                   AUDIT DESCRIPTION
                =====================================
                */

                $changes = [];


                if (
                    $oldAdmin['full_name']
                    !==
                    $name
                ) {

                    $changes[] =
                        'Name changed from "' .
                        $oldAdmin['full_name'] .
                        '" to "' .
                        $name .
                        '"';
                }


                if (
                    $oldAdmin['email']
                    !==
                    $email
                ) {

                    $changes[] =
                        'Email changed from "' .
                        $oldAdmin['email'] .
                        '" to "' .
                        $email .
                        '"';
                }


                if (
                    $oldAdmin['status']
                    !==
                    $status
                ) {

                    $changes[] =
                        'Status changed from "' .
                        $oldAdmin['status'] .
                        '" to "' .
                        $status .
                        '"';
                }


                /*
                 * If no actual value changed,
                 * still record that the Admin
                 * record was saved.
                 */
                if (empty($changes)) {

                    $description =
                        'Administrator record reviewed/saved: ' .
                        $name;

                } else {

                    $description =
                        'Updated administrator: ' .
                        $name .
                        ' | ' .
                        implode(
                            ' | ',
                            $changes
                        );
                }


                /*
                =====================================
                   AUDIT LOG
                =====================================
                */

                logAudit(
                    currentUserId(),
                    'UPDATE',
                    'Admins',
                    $id,
                    $description
                );


                /*
                =====================================
                   REDIRECT
                =====================================
                */

                header(
                    'Location: manage_admins.php?success=updated'
                );

                exit;
            }
        }
    }
}


/*
=====================================================
   SEARCH + STATUS FILTER
=====================================================
*/

$search = trim(
    $_GET['search'] ?? ''
);

$statusFilter = trim(
    $_GET['status'] ?? ''
);


$where = "
    WHERE role = 'Admin'
";

$params = [];


/*
=====================================================
   SEARCH BY NAME / EMAIL
=====================================================
*/

if ($search !== '') {

    $where .= "
        AND
        (
            full_name LIKE ?
            OR email LIKE ?
        )
    ";

    $params[] =
        "%{$search}%";

    $params[] =
        "%{$search}%";
}


/*
=====================================================
   STATUS FILTER
=====================================================
*/

if (
    $statusFilter !== '' &&
    in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $where .= "
        AND status = ?
    ";

    $params[] =
        $statusFilter;
}


/*
=====================================================
   PAGINATION
=====================================================
*/

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$limit = 10;

$offset =
    ($page - 1) * $limit;


/*
=====================================================
   COUNT RECORDS
=====================================================
*/

$count = $db->prepare("
    SELECT COUNT(*)
    FROM system_user
    {$where}
");

$count->execute(
    $params
);

$totalRows =
    (int) $count->fetchColumn();


$totalPages = max(
    1,
    (int) ceil(
        $totalRows / $limit
    )
);


/*
 * Prevent invalid page number.
 */
if ($page > $totalPages) {

    $page =
        $totalPages;

    $offset =
        ($page - 1) * $limit;
}


/*
=====================================================
   GET ADMIN RECORDS
=====================================================
*/

$sql = "
    SELECT
        user_id,
        full_name,
        email,
        role,
        status,
        created_at
    FROM system_user

    {$where}

    ORDER BY created_at DESC

    LIMIT {$limit}
    OFFSET {$offset}
";


$stmt =
    $db->prepare($sql);

$stmt->execute(
    $params
);

$admins =
    $stmt->fetchAll();


/*
=====================================================
   NAVIGATION
=====================================================
*/

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
        Manage Admins
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >


    <style>

        /*
        =====================================================
           STATUS BADGES
        =====================================================
        */

        .admin-status {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 5px 10px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 700;

            white-space: nowrap;
        }


        .admin-status-active {

            background: #dcfce7;

            color: #166534;
        }


        .admin-status-leave {

            background: #dbeafe;

            color: #1d4ed8;
        }


        .admin-status-absent {

            background: #fef3c7;

            color: #92400e;
        }


        .admin-status-resigned {

            background: #f3f4f6;

            color: #4b5563;
        }


        .admin-status-terminated {

            background: #fee2e2;

            color: #991b1b;
        }


        /*
        =====================================================
           STATUS HELP
        =====================================================
        */

        .status-help {

            margin-top: 7px;

            color:
                var(
                    --color-text-secondary,
                    #64748b
                );

            font-size: 12px;

            line-height: 1.5;
        }


        /*
        =====================================================
           FILTER AREA
        =====================================================
        */

        .admin-filter-row {

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;

            width: 100%;
        }


        .admin-filter-row .search-box {

            min-width: 240px;
        }


        .admin-filter-row select {

            min-width: 160px;
        }


        /*
        =====================================================
           ACTIONS
        =====================================================
        */

        .action-buttons {

            display: flex;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;
        }


        /*
        =====================================================
           RESPONSIVE
        =====================================================
        */

        @media (max-width: 768px) {

            .admin-filter-row {

                align-items: stretch;

                flex-direction: column;
            }


            .admin-filter-row .form-input,
            .admin-filter-row .search-box {

                width: 100%;

                min-width: 0;
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


    <!--
    =====================================================
       PAGE HEADER
    =====================================================
    -->

    <div class="page-header">


        <div>

            <h1>
                Manage Admins
            </h1>

            <p>
                Create, update and manage administrator accounts.
            </p>

        </div>


        <button
            type="button"
            class="btn-success"
            onclick="openAddModal()"
        >

            + Add Admin

        </button>


    </div>


    <!--
    =====================================================
       SUCCESS MESSAGE
    =====================================================
    -->

    <?php if (isset($_GET['success'])): ?>

        <div class="alert-success">

            <?php

            switch (
                $_GET['success']
            ) {

                case 'added':

                    echo
                        'Administrator added successfully.';

                    break;


                case 'updated':

                    echo
                        'Administrator updated successfully.';

                    break;


                default:

                    echo
                        'Action completed successfully.';

                    break;
            }

            ?>

        </div>

    <?php endif; ?>


    <!--
    =====================================================
       ERROR MESSAGE
    =====================================================
    -->

    <?php if ($error): ?>

        <div class="alert-error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!--
    =====================================================
       ADMIN PANEL
    =====================================================
    -->

    <div class="card panel">


        <!--
        =================================================
           SEARCH + STATUS FILTER
        =================================================
        -->

        <form
            method="GET"
            class="toolbar"
        >

            <div class="admin-filter-row">


                <!-- SEARCH -->

                <input
                    type="text"
                    name="search"
                    class="form-input search-box"
                    placeholder="Search admin..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <!-- STATUS -->

                <select
                    name="status"
                    class="form-input"
                >

                    <option value="">
                        All Status
                    </option>


                    <?php foreach (
                        $allowedStatuses
                        as $adminStatus
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $adminStatus,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            <?= (
                                $statusFilter ===
                                $adminStatus
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= htmlspecialchars(
                                $adminStatus,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- SEARCH BUTTON -->

                <button
                    type="submit"
                    class="btn-primary"
                >

                    Search

                </button>


                <!-- CLEAR -->

                <?php if (
                    $search !== '' ||
                    $statusFilter !== ''
                ): ?>

                    <a
                        href="manage_admins.php"
                        class="btn-secondary"
                    >

                        Clear

                    </a>

                <?php endif; ?>


            </div>

        </form>


        <!--
        =================================================
           TABLE
        =================================================
        -->

        <div style="overflow-x:auto;">


            <table class="data-table">


                <thead>

                <tr>

                    <th>
                        Name
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Date Created
                    </th>

                    <th width="120">
                        Actions
                    </th>

                </tr>

                </thead>


                <tbody>


                <?php if (empty($admins)): ?>


                    <tr>

                        <td colspan="5">

                            <div class="empty-state">

                                No administrator accounts found.

                            </div>

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $admins
                        as $admin
                    ): ?>


                        <?php

                        /*
                         * Determine status badge class.
                         */

                        switch ($admin['status']) {

                            case 'Active':

                                $statusClass =
                                    'admin-status-active';

                                break;


                            case 'On Leave':

                                $statusClass =
                                    'admin-status-leave';

                                break;


                            case 'Absent':

                                $statusClass =
                                    'admin-status-absent';

                                break;


                            case 'Resigned':

                                $statusClass =
                                    'admin-status-resigned';

                                break;


                            case 'Terminated':

                                $statusClass =
                                    'admin-status-terminated';

                                break;


                            default:

                                $statusClass =
                                    'admin-status-resigned';

                                break;
                        }

                        ?>


                        <tr>


                            <!-- NAME -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $admin['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </td>


                            <!-- EMAIL -->

                            <td>

                                <?= htmlspecialchars(
                                    $admin['email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="
                                        admin-status
                                        <?= $statusClass ?>
                                    "
                                >

                                    <?= htmlspecialchars(
                                        $admin['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <!-- CREATED -->

                            <td>

                                <?php

                                if (
                                    !empty(
                                        $admin['created_at']
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        date(
                                            'M d, Y',
                                            strtotime(
                                                $admin['created_at']
                                            )
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                } else {

                                    echo '—';
                                }

                                ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>


                                <div class="action-buttons">


                                    <!--
                                    =================================
                                       EDIT ONLY
                                    =================================

                                    No Delete button is provided.

                                    Historical Admin records remain
                                    stored in the database.
                                    -->

                                    <button
                                        type="button"
                                        class="btn-info"
                                        onclick='editAdmin(
                                            <?= json_encode(
                                                (int) $admin['user_id']
                                            ) ?>,
                                            <?= json_encode(
                                                $admin['full_name']
                                            ) ?>,
                                            <?= json_encode(
                                                $admin['email']
                                            ) ?>,
                                            <?= json_encode(
                                                $admin['status']
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


        <!--
        =================================================
           PAGINATION
        =================================================
        -->

        <div class="pagination">


            <div>


                <?php if ($totalRows > 0): ?>


                    Showing

                    <?= $offset + 1 ?>

                    to

                    <?= min(
                        $offset + $limit,
                        $totalRows
                    ) ?>

                    of

                    <?= $totalRows ?>


                <?php else: ?>


                    Showing 0 of 0


                <?php endif; ?>


            </div>


            <div class="pagination-links">


                <?php

                for (
                    $i = 1;
                    $i <= $totalPages;
                    $i++
                ):

                ?>


                    <a
                        class="<?= (
                            $i === $page
                        )
                            ? 'active'
                            : ''
                        ?>"
                        href="?page=<?= $i ?>&search=<?= urlencode(
                            $search
                        ) ?>&status=<?= urlencode(
                            $statusFilter
                        ) ?>"
                    >

                        <?= $i ?>

                    </a>


                <?php endfor; ?>


            </div>


        </div>


    </div>


</main>


</div>


<!--
=====================================================
   ADD ADMIN MODAL
=====================================================
-->

<div
    id="addModal"
    class="modal"
>


    <div class="modal-content">


        <form
            method="POST"
            action=""
        >


            <div class="modal-header">


                <h2>
                    Add Admin
                </h2>


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModals()"
                >

                    ✕

                </button>


            </div>


            <!-- FULL NAME -->

            <label class="form-label">

                Full Name

            </label>


            <input
                type="text"
                name="full_name"
                class="form-input"
                required
                maxlength="150"
            >


            <br><br>


            <!-- EMAIL -->

            <label class="form-label">

                Email

            </label>


            <input
                type="email"
                name="email"
                class="form-input"
                required
                maxlength="255"
            >


            <br><br>


            <!-- PASSWORD -->

            <label class="form-label">

                Password

            </label>


            <input
                type="password"
                name="password"
                class="form-input"
                required
                minlength="8"
                autocomplete="new-password"
            >


            <div class="status-help">

                Password must contain at least
                8 characters.

                <br>

                New administrator accounts are
                automatically created with an
                <strong>Active</strong> status.

            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModals()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="add_admin"
                    class="btn-success"
                >

                    Create Admin

                </button>


            </div>


        </form>


    </div>


</div>


<!--
=====================================================
   EDIT ADMIN MODAL
=====================================================
-->

<div
    id="editModal"
    class="modal"
>


    <div class="modal-content">


        <form
            method="POST"
            action=""
        >


            <input
                type="hidden"
                name="user_id"
                id="edit_user_id"
            >


            <div class="modal-header">


                <h2>
                    Edit Admin
                </h2>


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModals()"
                >

                    ✕

                </button>


            </div>


            <!-- FULL NAME -->

            <label class="form-label">

                Full Name

            </label>


            <input
                type="text"
                name="full_name"
                id="edit_name"
                class="form-input"
                required
                maxlength="150"
            >


            <br><br>


            <!-- EMAIL -->

            <label class="form-label">

                Email

            </label>


            <input
                type="email"
                name="email"
                id="edit_email"
                class="form-input"
                required
                maxlength="255"
            >


            <br><br>


            <!-- STATUS -->

            <label class="form-label">

                Employment Status

            </label>


            <select
                name="status"
                id="edit_status"
                class="form-input"
                required
            >


                <option value="Active">

                    Active

                </option>


                <option value="On Leave">

                    On Leave

                </option>


                <option value="Absent">

                    Absent

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

                Active administrators can perform
                their normal system responsibilities.

            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModals()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="update_admin"
                    class="btn-warning"
                >

                    Save Changes

                </button>


            </div>


        </form>


    </div>


</div>


<!--
=====================================================
   JAVASCRIPT
=====================================================
-->

<script>


/*
=====================================================
   OPEN ADD ADMIN
=====================================================
*/

function openAddModal()
{
    const modal =
        document.getElementById(
            'addModal'
        );

    if (modal) {

        modal.style.display =
            'block';
    }
}


/*
=====================================================
   EDIT ADMIN
=====================================================
*/

function editAdmin(
    id,
    name,
    email,
    status
) {

    document.getElementById(
        'edit_user_id'
    ).value = id;


    document.getElementById(
        'edit_name'
    ).value = name;


    document.getElementById(
        'edit_email'
    ).value = email;


    document.getElementById(
        'edit_status'
    ).value = status;


    updateStatusHelp();


    document.getElementById(
        'editModal'
    ).style.display = 'block';
}


/*
=====================================================
   STATUS DESCRIPTION
=====================================================
*/

function updateStatusHelp()
{

    const status =
        document.getElementById(
            'edit_status'
        ).value;


    const help =
        document.getElementById(
            'statusHelp'
        );


    const messages = {

        'Active':
            'The administrator is currently active and can access the system.',

        'On Leave':
            'The administrator is temporarily on leave and cannot access Admin pages.',

        'Absent':
            'The administrator is currently absent and cannot access Admin pages.',

        'Resigned':
            'The administrator has resigned. The account and historical records will remain in the system, but access is disabled.',

        'Terminated':
            'The administrator has been terminated. The account and historical records will remain in the system, but access is disabled.'

    };


    help.textContent =
        messages[status] || '';
}


/*
=====================================================
   STATUS SELECT LISTENER
=====================================================
*/

const editStatus =
    document.getElementById(
        'edit_status'
    );


if (editStatus) {

    editStatus.addEventListener(
        'change',
        updateStatusHelp
    );
}


/*
=====================================================
   CLOSE MODALS
=====================================================
*/

function closeModals()
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
}


/*
=====================================================
   CLICK OUTSIDE MODAL
=====================================================
*/

window.addEventListener(
    'click',
    function(event)
    {

        if (
            event.target.classList.contains(
                'modal'
            )
        ) {

            closeModals();
        }
    }
);


/*
=====================================================
   ESC KEY
=====================================================
*/

document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key === 'Escape'
        ) {

            closeModals();
        }
    }
);


</script>


</body>

</html>