<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();


/*
|--------------------------------------------------------------------------
| SEARCH & FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$action = trim($_GET['action'] ?? '');
$role   = trim($_GET['role'] ?? '');
$module = trim($_GET['module'] ?? '');

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            full_name LIKE ?
            OR role LIKE ?
            OR description LIKE ?
            OR ip_address LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| ACTION FILTER
|--------------------------------------------------------------------------
*/

if ($action !== '') {

    $where[] = "action = ?";
    $params[] = $action;
}


/*
|--------------------------------------------------------------------------
| ROLE FILTER
|--------------------------------------------------------------------------
*/

if ($role !== '') {

    $where[] = "role = ?";
    $params[] = $role;
}


/*
|--------------------------------------------------------------------------
| MODULE FILTER
|--------------------------------------------------------------------------
*/

if ($module !== '') {

    $where[] = "module = ?";
    $params[] = $module;
}


/*
|--------------------------------------------------------------------------
| BUILD WHERE CLAUSE
|--------------------------------------------------------------------------
*/

$whereSQL = '';

if (!empty($where)) {

    $whereSQL =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);


/*
|--------------------------------------------------------------------------
| LOGS PER PAGE
|--------------------------------------------------------------------------
*/

$limit = 15;


/*
|--------------------------------------------------------------------------
| COUNT FILTERED LOGS
|--------------------------------------------------------------------------
*/

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM audit_log
    $whereSQL
");

$countStmt->execute($params);

$totalRows =
    (int) $countStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL PAGES
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int) ceil(
        $totalRows / $limit
    )
);


/*
|--------------------------------------------------------------------------
| PREVENT INVALID PAGE
|--------------------------------------------------------------------------
*/

if ($page > $totalPages) {

    $page = $totalPages;
}


/*
|--------------------------------------------------------------------------
| OFFSET
|--------------------------------------------------------------------------
*/

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| GET LOGS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM audit_log
    $whereSQL
    ORDER BY created_at DESC
    LIMIT :limit
    OFFSET :offset
";

$stmt = $db->prepare($sql);


/*
|--------------------------------------------------------------------------
| BIND FILTER VALUES
|--------------------------------------------------------------------------
*/

$parameterIndex = 1;

foreach ($params as $value) {

    $stmt->bindValue(
        $parameterIndex,
        $value,
        PDO::PARAM_STR
    );

    $parameterIndex++;
}


/*
|--------------------------------------------------------------------------
| BIND LIMIT / OFFSET
|--------------------------------------------------------------------------
*/

$stmt->bindValue(
    ':limit',
    $limit,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$logs =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| FILTER DROPDOWNS
|--------------------------------------------------------------------------
*/

$actions = $db->query("
    SELECT DISTINCT action
    FROM audit_log
    WHERE action IS NOT NULL
      AND action <> ''
    ORDER BY action
")->fetchAll(
    PDO::FETCH_COLUMN
);


$roles = $db->query("
    SELECT DISTINCT role
    FROM audit_log
    WHERE role IS NOT NULL
      AND role <> ''
    ORDER BY role
")->fetchAll(
    PDO::FETCH_COLUMN
);


$modules = $db->query("
    SELECT DISTINCT module
    FROM audit_log
    WHERE module IS NOT NULL
      AND module <> ''
    ORDER BY module
")->fetchAll(
    PDO::FETCH_COLUMN
);


/*
|--------------------------------------------------------------------------
| PAGINATION URL HELPER
|--------------------------------------------------------------------------
|
| Keeps all active filters while changing pages.
|
*/

function paginationUrl(
    int $targetPage,
    string $search,
    string $action,
    string $role,
    string $module
): string {

    $query = [
        'page' => $targetPage
    ];


    if ($search !== '') {
        $query['search'] = $search;
    }

    if ($action !== '') {
        $query['action'] = $action;
    }

    if ($role !== '') {
        $query['role'] = $role;
    }

    if ($module !== '') {
        $query['module'] = $module;
    }


    return '?' . http_build_query($query);
}


/*
|--------------------------------------------------------------------------
| PAGINATION RANGE
|--------------------------------------------------------------------------
|
| Example:
|
| Page 1:
| [1] 2 3 ... 20
|
| Page 6:
| 1 ... 4 5 [6] 7 8 ... 20
|
*/

$paginationStart = max(
    1,
    $page - 2
);

$paginationEnd = min(
    $totalPages,
    $page + 2
);


/*
|--------------------------------------------------------------------------
| SHOWING RANGE
|--------------------------------------------------------------------------
*/

$showingStart =
    $totalRows > 0
        ? $offset + 1
        : 0;

$showingEnd =
    $totalRows > 0
        ? min(
            $offset + $limit,
            $totalRows
        )
        : 0;


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

$navItems = [

    [
        'label' => 'Dashboard',
        'url' =>
            BASE_URL .
            'super_admin/dashboard.php'
    ],

    [
        'label' => 'Operations Map',
        'url' =>
            BASE_URL .
            'super_admin/operations_map.php'
    ],

    [
        'label' => 'Manage Admins',
        'url' =>
            BASE_URL .
            'super_admin/manage_admins.php'
    ],

    [
        'label' => 'Manage Drivers',
        'url' =>
            BASE_URL .
            'super_admin/manage_drivers.php'
    ],

    [
        'label' => 'Manage Trucks',
        'url' =>
            BASE_URL .
            'super_admin/manage_trucks.php'
    ],

    [
        'label' => 'Reports',
        'url' =>
            BASE_URL .
            'super_admin/reports.php'
    ],

    [
        'label' => 'System Logs',
        'url' =>
            BASE_URL .
            'super_admin/system_logs.php'
    ],

    [
        'label' => 'System Configuration',
        'url' =>
            BASE_URL .
            'super_admin/system_configuration.php'
    ],

    [
        'label' => 'Profile',
        'url' =>
            BASE_URL .
            'super_admin/profile.php'
    ]

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
    System Logs
</title>

<link
    rel="stylesheet"
    href="<?= BASE_URL ?>assets/css/theme.css"
>


<style>

/* ==========================================================
   PAGE HEADER
========================================================== */

.system-logs-header {

    margin-bottom: 22px;

}

.system-logs-header h1 {

    margin: 0 0 5px;

}

.system-logs-header p {

    margin: 0;

    color:
        var(--color-text-secondary);

}


/* ==========================================================
   TOOLBAR
========================================================== */

.logs-toolbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

    flex-wrap: wrap;

    margin-bottom: 20px;

}


.logs-filter-group {

    display: flex;

    align-items: center;

    gap: 10px;

    flex-wrap: wrap;

    width: 100%;

}


.logs-search {

    min-width: 230px;

    flex: 1;

}


.logs-toolbar .form-input {

    min-height: 40px;

}


/* ==========================================================
   TABLE
========================================================== */

.system-logs-table-wrapper {

    width: 100%;

    overflow-x: auto;

}


.system-logs-table {

    min-width: 1000px;

}


.log-description {

    min-width: 240px;

    max-width: 420px;

    white-space: normal;

    line-height: 1.45;

}


.log-ip {

    white-space: nowrap;

}


.log-date {

    white-space: nowrap;

}


/* ==========================================================
   RESULTS INFORMATION
========================================================== */

.results-summary {

    margin-bottom: 15px;

    font-size: 13px;

    color:
        var(--color-text-secondary);

}


/* ==========================================================
   PAGINATION
========================================================== */

.logs-pagination {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    flex-wrap: wrap;

    margin-top: 24px;

    padding-top: 20px;

    border-top:
        1px solid
        var(--color-border);

}


.pagination-info {

    font-size: 13px;

    color:
        var(--color-text-secondary);

}


.pagination-links {

    display: flex;

    align-items: center;

    gap: 6px;

    flex-wrap: wrap;

}


.pagination-link {

    min-width: 38px;

    height: 38px;

    box-sizing: border-box;

    padding: 0 11px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border:
        1px solid
        var(--color-border);

    border-radius: 8px;

    background:
        var(--color-surface);

    color:
        var(--color-text-primary);

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    transition:
        border-color .15s ease,
        background .15s ease,
        color .15s ease;

}


.pagination-link:hover {

    border-color:
        var(--color-accent);

    color:
        var(--color-accent);

}


.pagination-link.active {

    background:
        var(--color-accent);

    border-color:
        var(--color-accent);

    color: #ffffff;

}


.pagination-link.disabled {

    opacity: .45;

    pointer-events: none;

    cursor: default;

}


.pagination-dots {

    min-width: 28px;

    height: 38px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    color:
        var(--color-text-muted);

    font-weight: 700;

}


/* ==========================================================
   MOBILE
========================================================== */

@media (
    max-width: 850px
) {

    .logs-filter-group {

        align-items: stretch;

    }


    .logs-search {

        width: 100%;

        flex-basis: 100%;

    }

}


@media (
    max-width: 650px
) {

    .logs-filter-group {

        flex-direction: column;

    }


    .logs-filter-group .form-input,
    .logs-filter-group .btn-primary,
    .logs-filter-group .btn-secondary {

        width: 100%;

        box-sizing: border-box;

    }


    .logs-pagination {

        flex-direction: column;

        align-items: flex-start;

    }


    .pagination-links {

        width: 100%;

    }

}

</style>

</head>


<body>


<div class="app-layout">


<?php

require __DIR__ .
    '/../includes/header.php';

?>


<main class="app-main">


<!-- ==========================================================
     PAGE HEADER
========================================================== -->

<div class="system-logs-header">

    <h1>
        System Logs
    </h1>

    <p>
        View all recorded activities in the system.
    </p>

</div>



<!-- ==========================================================
     LOGS CARD
========================================================== -->

<div class="card panel">


    <!-- ======================================================
         SEARCH & FILTER
    ======================================================= -->

    <form
        method="GET"
        action=""
        class="logs-toolbar"
    >


        <div class="logs-filter-group">


            <!-- SEARCH -->

            <input
                type="text"
                name="search"
                class="form-input logs-search"
                placeholder="Search user, role, description or IP..."
                value="<?= htmlspecialchars(
                    $search
                ) ?>"
            >


            <!-- ACTION -->

            <select
                name="action"
                class="form-input"
            >

                <option value="">
                    All Actions
                </option>


                <?php foreach (
                    $actions
                    as $actionOption
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $actionOption
                        ) ?>"
                        <?= $action === $actionOption
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $actionOption
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <!-- ROLE -->

            <select
                name="role"
                class="form-input"
            >

                <option value="">
                    All Roles
                </option>


                <?php foreach (
                    $roles
                    as $roleOption
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $roleOption
                        ) ?>"
                        <?= $role === $roleOption
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $roleOption
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <!-- MODULE -->

            <select
                name="module"
                class="form-input"
            >

                <option value="">
                    All Modules
                </option>


                <?php foreach (
                    $modules
                    as $moduleOption
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $moduleOption
                        ) ?>"
                        <?= $module === $moduleOption
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $moduleOption
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <!-- FILTER -->

            <button
                type="submit"
                class="btn-primary"
            >
                Filter
            </button>


            <!-- RESET -->

            <a
                href="<?= BASE_URL ?>super_admin/system_logs.php"
                class="btn-secondary"
            >
                Reset
            </a>


        </div>


    </form>



    <!-- ======================================================
         RESULT SUMMARY
    ======================================================= -->

    <div class="results-summary">

        <?php if ($totalRows > 0): ?>

            Found

            <strong>
                <?= number_format(
                    $totalRows
                ) ?>
            </strong>

            log<?= $totalRows === 1
                ? ''
                : 's'
            ?>.

        <?php else: ?>

            No logs match the current filters.

        <?php endif; ?>

    </div>



    <!-- ======================================================
         TABLE
    ======================================================= -->

    <div class="system-logs-table-wrapper">


        <table
            class="data-table system-logs-table"
        >


            <thead>

                <tr>

                    <th>
                        Date
                    </th>

                    <th>
                        User
                    </th>

                    <th>
                        Role
                    </th>

                    <th>
                        Action
                    </th>

                    <th>
                        Module
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        IP Address
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (
                empty($logs)
            ): ?>


                <tr>

                    <td
                        colspan="7"
                    >

                        <div class="empty-state">

                            No audit logs found.

                        </div>

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach (
                    $logs
                    as $log
                ): ?>


                    <tr>


                        <!-- DATE -->

                        <td class="log-date">

                            <?php

                            if (
                                !empty(
                                    $log['created_at']
                                )
                            ) {

                                echo htmlspecialchars(
                                    date(
                                        'M d, Y h:i A',
                                        strtotime(
                                            $log['created_at']
                                        )
                                    )
                                );

                            } else {

                                echo '—';

                            }

                            ?>

                        </td>


                        <!-- USER -->

                        <td>

                            <?= htmlspecialchars(
                                $log['full_name']
                                ?? 'Unknown User'
                            ) ?>

                        </td>


                        <!-- ROLE -->

                        <td>

                            <?= htmlspecialchars(
                                $log['role']
                                ?? '—'
                            ) ?>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <?php

                            $logAction =
                                strtoupper(
                                    trim(
                                        $log['action']
                                        ?? ''
                                    )
                                );


                            $statusClass =
                                'status-pending';


                            switch (
                                $logAction
                            ) {

                                case 'LOGIN':

                                case 'CREATE':

                                    $statusClass =
                                        'status-delivered';

                                    break;


                                case 'LOGOUT':

                                case 'UPDATE':

                                    $statusClass =
                                        'status-transit';

                                    break;


                                case 'DELETE':

                                    $statusClass =
                                        'status-failed';

                                    break;


                                default:

                                    $statusClass =
                                        'status-pending';

                                    break;

                            }

                            ?>


                            <span
                                class="
                                    status-pill
                                    <?= $statusClass ?>
                                "
                            >

                                <?= htmlspecialchars(
                                    $log['action']
                                    ?? 'Unknown'
                                ) ?>

                            </span>

                        </td>


                        <!-- MODULE -->

                        <td>

                            <?= htmlspecialchars(
                                $log['module']
                                ?? '—'
                            ) ?>

                        </td>


                        <!-- DESCRIPTION -->

                        <td class="log-description">

                            <?= htmlspecialchars(
                                $log['description']
                                ?? '—'
                            ) ?>

                        </td>


                        <!-- IP -->

                        <td class="log-ip">

                            <?= htmlspecialchars(
                                $log['ip_address']
                                ?? '—'
                            ) ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php endif; ?>


            </tbody>


        </table>


    </div>



    <!-- ======================================================
         PAGINATION
    ======================================================= -->

    <?php if (
        $totalRows > 0
    ): ?>


        <div class="logs-pagination">


            <!-- PAGINATION INFO -->

            <div class="pagination-info">

                Showing

                <strong>
                    <?= $showingStart ?>
                </strong>

                to

                <strong>
                    <?= $showingEnd ?>
                </strong>

                of

                <strong>
                    <?= $totalRows ?>
                </strong>

                logs

            </div>



            <!-- PAGINATION LINKS -->

            <div class="pagination-links">


                <!-- PREVIOUS -->

                <?php if (
                    $page > 1
                ): ?>

                    <a
                        class="pagination-link"
                        href="<?= htmlspecialchars(
                            paginationUrl(
                                $page - 1,
                                $search,
                                $action,
                                $role,
                                $module
                            )
                        ) ?>"
                    >
                        ‹ Previous
                    </a>

                <?php else: ?>

                    <span
                        class="
                            pagination-link
                            disabled
                        "
                    >
                        ‹ Previous
                    </span>

                <?php endif; ?>



                <!-- FIRST PAGE -->

                <?php if (
                    $paginationStart > 1
                ): ?>

                    <a
                        class="pagination-link"
                        href="<?= htmlspecialchars(
                            paginationUrl(
                                1,
                                $search,
                                $action,
                                $role,
                                $module
                            )
                        ) ?>"
                    >
                        1
                    </a>


                    <?php if (
                        $paginationStart > 2
                    ): ?>

                        <span
                            class="pagination-dots"
                        >
                            …
                        </span>

                    <?php endif; ?>


                <?php endif; ?>



                <!-- CURRENT PAGE RANGE -->

                <?php for (
                    $i = $paginationStart;
                    $i <= $paginationEnd;
                    $i++
                ): ?>


                    <a
                        class="
                            pagination-link
                            <?= $i === $page
                                ? 'active'
                                : ''
                            ?>
                        "
                        href="<?= htmlspecialchars(
                            paginationUrl(
                                $i,
                                $search,
                                $action,
                                $role,
                                $module
                            )
                        ) ?>"
                        <?= $i === $page
                            ? 'aria-current="page"'
                            : ''
                        ?>
                    >

                        <?= $i ?>

                    </a>


                <?php endfor; ?>



                <!-- LAST PAGE -->

                <?php if (
                    $paginationEnd <
                    $totalPages
                ): ?>


                    <?php if (
                        $paginationEnd <
                        $totalPages - 1
                    ): ?>

                        <span
                            class="pagination-dots"
                        >
                            …
                        </span>

                    <?php endif; ?>


                    <a
                        class="pagination-link"
                        href="<?= htmlspecialchars(
                            paginationUrl(
                                $totalPages,
                                $search,
                                $action,
                                $role,
                                $module
                            )
                        ) ?>"
                    >

                        <?= $totalPages ?>

                    </a>


                <?php endif; ?>



                <!-- NEXT -->

                <?php if (
                    $page < $totalPages
                ): ?>

                    <a
                        class="pagination-link"
                        href="<?= htmlspecialchars(
                            paginationUrl(
                                $page + 1,
                                $search,
                                $action,
                                $role,
                                $module
                            )
                        ) ?>"
                    >
                        Next ›
                    </a>

                <?php else: ?>

                    <span
                        class="
                            pagination-link
                            disabled
                        "
                    >
                        Next ›
                    </span>

                <?php endif; ?>


            </div>


        </div>


    <?php endif; ?>


</div>


</main>


</div>


</body>

</html>