<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_log.php';

requireRole(ROLE_SUPER_ADMIN);

$db = Database::getConnection();

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/




/*
|--------------------------------------------------------------------------
| UPDATE URGENT DELIVERY RATES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedRates = $_POST['rates'] ?? [];

    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!is_array($postedRates) || empty($postedRates)) {
        $errors[] = 'No urgent delivery rates were submitted.';
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD CURRENT RATES
    |--------------------------------------------------------------------------
    */

    $currentRates = [];

    try {

        $currentStmt = $db->query("
            SELECT
                rate_id,
                min_distance_km,
                max_distance_km,
                fee,
                is_active
            FROM urgent_delivery_rate
            ORDER BY min_distance_km ASC
        ");

        foreach ($currentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $currentRates[(int) $row['rate_id']] = $row;
        }

    } catch (Throwable $e) {

        $errors[] =
            'Unable to load the current urgent delivery rates.';

        error_log(
            'System Configuration Load Error: '
            . $e->getMessage()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE SUBMITTED FEES
    |--------------------------------------------------------------------------
    */

    $validatedRates = [];

    if (empty($errors)) {

        foreach ($postedRates as $rateId => $fee) {

            $rateId = (int) $rateId;

            /*
             * Make sure the submitted rate actually exists
             * in the database.
             */
            if (!isset($currentRates[$rateId])) {

                $errors[] =
                    'An invalid urgent delivery rate was submitted.';

                continue;
            }

            /*
             * Fee must contain a valid number.
             */
            if (
                $fee === ''
                ||
                !is_numeric($fee)
            ) {

                $errors[] =
                    'Please enter a valid fee for all distance ranges.';

                continue;
            }

            $fee = round((float) $fee, 2);

            /*
             * Negative delivery fees are not allowed.
             */
            if ($fee < 0) {

                $errors[] =
                    'Urgent delivery fees cannot be negative.';

                continue;
            }

            /*
             * Safety limit to prevent accidental
             * extremely large values.
             */
            if ($fee > 100000) {

                $errors[] =
                    'An urgent delivery fee is unusually high. Please check the amount.';

                continue;
            }

            $validatedRates[$rateId] = $fee;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE CHANGES
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $db->beginTransaction();

            $updateStmt = $db->prepare("
                UPDATE urgent_delivery_rate
                SET fee = :fee
                WHERE rate_id = :rate_id
            ");

            $changes = [];

            foreach ($validatedRates as $rateId => $newFee) {

                $oldFee =
                    (float) $currentRates[$rateId]['fee'];

                /*
                 * Do not update rows whose fee
                 * has not actually changed.
                 */
                if (abs($oldFee - $newFee) < 0.001) {
                    continue;
                }

                $updateStmt->execute([
                    'fee'     => $newFee,
                    'rate_id' => $rateId
                ]);

                /*
                |--------------------------------------------------------------------------
                | BUILD AUDIT DESCRIPTION
                |--------------------------------------------------------------------------
                */

                $minDistance =
                    (float) $currentRates[$rateId]['min_distance_km'];

                $maxDistance =
                    $currentRates[$rateId]['max_distance_km'];

                if ($maxDistance === null) {

                    $rangeText =
                        number_format($minDistance, 2)
                        . ' km and above';

                } else {

                    $rangeText =
                        number_format($minDistance, 2)
                        . ' - '
                        . number_format(
                            (float) $maxDistance,
                            2
                        )
                        . ' km';
                }

                $changes[] =
                    $rangeText
                    . ': '
                    . formatMoney($oldFee)
                    . ' -> '
                    . formatMoney($newFee);
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT CONFIGURATION FIRST
            |--------------------------------------------------------------------------
            |
            | The actual configuration update is committed before
            | writing the audit log.
            |
            */

            $db->commit();


            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            if (!empty($changes)) {

                try {

                    logAudit(
                        currentUserId(),
                        'UPDATE',
                        'System Configuration',
                        null,
                        'Updated urgent delivery rates: '
                        . implode('; ', $changes)
                    );

                } catch (Throwable $auditException) {

                    /*
                     * Audit logging should not undo a successfully
                     * saved configuration change.
                     */
                    error_log(
                        'System Configuration Audit Error: '
                        . $auditException->getMessage()
                    );
                }

                $success =
                    'Urgent delivery rates were updated successfully.';

            } else {

                $success =
                    'No changes were made to the urgent delivery rates.';
            }

        } catch (Throwable $e) {

            /*
             * Roll back only if the transaction
             * is still active.
             */
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'System Configuration Save Error: '
                . $e->getMessage()
            );

            /*
             * Detailed error is temporarily displayed
             * while the system is still under development.
             */
            $errors[] =
                'Unable to update the urgent delivery rates. Error: '
                . $e->getMessage();
        }
    }

} // END POST REQUEST


/*
|--------------------------------------------------------------------------
| LOAD URGENT DELIVERY RATES
|--------------------------------------------------------------------------
*/

$urgentRates = [];

try {

    $ratesStmt = $db->query("
        SELECT
            rate_id,
            min_distance_km,
            max_distance_km,
            fee,
            is_active,
            created_at,
            updated_at
        FROM urgent_delivery_rate
        ORDER BY min_distance_km ASC
    ");

    $urgentRates =
        $ratesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $errors[] =
        'Unable to load urgent delivery rates.';

    error_log(
        'Urgent Delivery Rate Load Error: '
        . $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| SUPER ADMIN NAVIGATION
|--------------------------------------------------------------------------
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

require_once __DIR__ . '/../includes/header.php';

?>

<style>

.system-config-page {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.system-config-header {
    margin-bottom: 28px;
}

.system-config-header h1 {
    margin: 0 0 8px;
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text-primary);
}

.system-config-header p {
    margin: 0;
    color: var(--color-text-secondary);
    line-height: 1.6;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.config-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 24px;
}

.config-card-header {
    margin-bottom: 22px;
}

.config-card-header h2 {
    margin: 0 0 7px;
    font-size: 20px;
    color: var(--color-text-primary);
}

.config-card-header p {
    margin: 0;
    color: var(--color-text-secondary);
    font-size: 14px;
    line-height: 1.6;
}


/*
|--------------------------------------------------------------------------
| ALERTS
|--------------------------------------------------------------------------
*/

.alert {
    padding: 14px 16px;
    border-radius: var(--radius-md);
    margin-bottom: 18px;
    border: 1px solid transparent;
    line-height: 1.5;
}

.alert-success {
    background: rgba(34, 197, 94, .08);
    border-color: rgba(34, 197, 94, .25);
    color: var(--color-success);
}

.alert-danger {
    background: rgba(220, 53, 69, .08);
    border-color: rgba(220, 53, 69, .25);
    color: var(--color-danger);
}

.alert ul {
    margin: 0;
    padding-left: 20px;
}

.alert li + li {
    margin-top: 5px;
}


/*
|--------------------------------------------------------------------------
| RATE TABLE
|--------------------------------------------------------------------------
*/

.rate-table-wrapper {
    overflow-x: auto;
}

.rate-table {
    width: 100%;
    border-collapse: collapse;
}

.rate-table th,
.rate-table td {
    padding: 15px 14px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

.rate-table th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--color-text-muted);
    background: var(--color-surface-alt);
}

.rate-table td {
    color: var(--color-text-primary);
    vertical-align: middle;
}

.rate-table tbody tr:last-child td {
    border-bottom: 0;
}


/*
|--------------------------------------------------------------------------
| DISTANCE
|--------------------------------------------------------------------------
*/

.distance-range {
    font-weight: 700;
}

.distance-description {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    font-weight: 400;
    color: var(--color-text-muted);
}


/*
|--------------------------------------------------------------------------
| FEE INPUT
|--------------------------------------------------------------------------
*/

.fee-input-wrapper {
    position: relative;
    width: 180px;
    max-width: 100%;
}

.fee-symbol {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-secondary);
    font-weight: 700;
    pointer-events: none;
}

.fee-input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    color: var(--color-text-primary);
    padding: 11px 13px 11px 31px;
    font: inherit;
    font-weight: 700;
    outline: none;
}

.fee-input:focus {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px var(--color-accent-soft);
}


/*
|--------------------------------------------------------------------------
| INFORMATION BOX
|--------------------------------------------------------------------------
*/

.config-info {
    margin-top: 22px;
    padding: 16px;
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
}

.config-info strong {
    display: block;
    margin-bottom: 7px;
    color: var(--color-text-primary);
}

.config-info p {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    color: var(--color-text-secondary);
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.config-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 22px;
}

.config-actions .btn {
    min-width: 160px;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.status-active {
    background: rgba(34, 197, 94, .10);
    color: var(--color-success);
}

.status-inactive {
    background: rgba(107, 114, 128, .10);
    color: var(--color-text-muted);
}


/*
|--------------------------------------------------------------------------
| EMPTY STATE
|--------------------------------------------------------------------------
*/

.empty-rates {
    padding: 35px 20px;
    text-align: center;
    color: var(--color-text-secondary);
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .config-card {
        padding: 18px;
    }

    .rate-table th,
    .rate-table td {
        padding: 12px 10px;
    }

    .fee-input-wrapper {
        width: 140px;
    }

    .config-actions {
        justify-content: stretch;
    }

    .config-actions .btn {
        width: 100%;
    }
}

</style>


<main class="app-main">

    <div class="system-config-page">


        <!-- ==========================================================
             PAGE HEADER
        =========================================================== -->

        <div class="system-config-header">

            <h1>
                System Configuration
            </h1>

            <p>
                Manage system-wide delivery settings used by
                Hong Sing LKRR Commercial.
            </p>

        </div>


        <!-- ==========================================================
             SUCCESS MESSAGE
        =========================================================== -->

        <?php if ($success !== ''): ?>

            <div class="alert alert-success">
                <?= e($success) ?>
            </div>

        <?php endif; ?>


        <!-- ==========================================================
             ERROR MESSAGES
        =========================================================== -->

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- ==========================================================
             URGENT DELIVERY CONFIGURATION
        =========================================================== -->

        <section class="config-card">

            <div class="config-card-header">

                <h2>
                    Urgent Delivery Rates
                </h2>

                <p>
                    Set the additional fee charged when a customer
                    selects Urgent Delivery. The applicable fee is
                    automatically determined using the calculated
                    road distance.
                </p>

            </div>


            <?php if (!empty($urgentRates)): ?>

                <form method="POST">


                    <!-- ==============================================
                         RATE TABLE
                    =============================================== -->

                    <div class="rate-table-wrapper">

                        <table class="rate-table">

                            <thead>

                                <tr>

                                    <th>
                                        Distance Range
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Urgent Fee
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($urgentRates as $rate): ?>

                                    <?php

                                    $minimum =
                                        (float) $rate['min_distance_km'];

                                    $maximum =
                                        $rate['max_distance_km'];

                                    if ($maximum === null) {

                                        $rangeText =
                                            number_format($minimum, 2)
                                            . ' km and above';

                                    } else {

                                        $rangeText =
                                            number_format($minimum, 2)
                                            . ' – '
                                            . number_format(
                                                (float) $maximum,
                                                2
                                            )
                                            . ' km';
                                    }

                                    ?>


                                    <tr>


                                        <!-- Distance Range -->

                                        <td>

                                            <span class="distance-range">
                                                <?= e($rangeText) ?>
                                            </span>

                                            <span class="distance-description">

                                                <?php if ($maximum === null): ?>

                                                    Long-distance urgent delivery

                                                <?php else: ?>

                                                    Urgent delivery within this
                                                    road-distance range

                                                <?php endif; ?>

                                            </span>

                                        </td>


                                        <!-- Status -->

                                        <td>

                                            <?php if ((int) $rate['is_active'] === 1): ?>

                                                <span class="status-badge status-active">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="status-badge status-inactive">
                                                    Inactive
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Fee -->

                                        <td>

                                            <div class="fee-input-wrapper">

                                                <span class="fee-symbol">
                                                    ₱
                                                </span>

                                                <input
                                                    type="number"
                                                    name="rates[<?= (int) $rate['rate_id'] ?>]"
                                                    class="fee-input"
                                                    min="0"
                                                    max="100000"
                                                    step="0.01"
                                                    value="<?= e(
                                                        number_format(
                                                            (float) $rate['fee'],
                                                            2,
                                                            '.',
                                                            ''
                                                        )
                                                    ) ?>"
                                                    required
                                                >

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- ==============================================
                         INFORMATION
                    =============================================== -->

                    <div class="config-info">

                        <strong>
                            How urgent delivery fees work
                        </strong>

                        <p>
                            Normal Delivery has no urgent surcharge.
                            When Urgent Delivery is selected, the
                            system uses the road distance calculated
                            from Hong Sing LKRR Commercial to the
                            customer's delivery location and applies
                            the corresponding fee above. The urgent
                            fee is added to the product subtotal.
                        </p>

                    </div>


                    <!-- ==============================================
                         SAVE
                    =============================================== -->

                    <div class="config-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Changes
                        </button>

                    </div>


                </form>


            <?php else: ?>


                <!-- ==============================================
                     EMPTY STATE
                =============================================== -->

                <div class="empty-rates">

                    No urgent delivery rates are configured.

                </div>


            <?php endif; ?>


        </section>

    </div>

</main>