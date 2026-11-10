<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$db = Database::getConnection();

$adminId = currentUserId();

if (!$adminId) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=session_expired');
    exit;
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function feedbackStars(int $rating): string
{
    $rating = max(0, min(5, $rating));

    return str_repeat('★', $rating)
        . str_repeat('☆', 5 - $rating);
}

function ratingLabel(int $rating): string
{
    return match ($rating) {
        5 => 'Excellent',
        4 => 'Very Good',
        3 => 'Good',
        2 => 'Needs Improvement',
        1 => 'Poor',
        default => 'No Rating'
    };
}

/*
|--------------------------------------------------------------------------
| RATING GRAPH AJAX
|--------------------------------------------------------------------------
|
| This section is handled before the normal page queries.
| This prevents the statistics and feedback list queries from
| running every time Chart.js requests graph data.
|
*/

if (isset($_GET['rating_data'])) {

    header('Content-Type: application/json; charset=utf-8');

    $period = $_GET['rating_data'];

    $allowedPeriods = [
        'day',
        'week',
        'month',
        'year'
    ];

    if (!in_array($period, $allowedPeriods, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid period.'
        ]);
        exit;
    }

    $labels = [];
    $values = [];

    /*
    |--------------------------------------------------------------------------
    | DAY
    |--------------------------------------------------------------------------
    |
    | Shows daily average ratings for the last 30 days.
    |
    */

    if ($period === 'day') {

        $stmt = $db->query("
            SELECT
                DATE(created_at) AS period_date,
                AVG(rating) AS average_rating
            FROM feedback
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            GROUP BY DATE(created_at)
            ORDER BY period_date ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {

            $labels[] = date(
                'M d',
                strtotime($row['period_date'])
            );

            $values[] = round(
                (float) $row['average_rating'],
                2
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | WEEK
    |--------------------------------------------------------------------------
    |
    | Shows weekly average ratings for the last 12 weeks.
    |
    */

    elseif ($period === 'week') {

        $stmt = $db->query("
            SELECT
                YEARWEEK(created_at, 1) AS period_week,
                MIN(DATE(created_at)) AS week_start,
                AVG(rating) AS average_rating
            FROM feedback
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 WEEK)
            GROUP BY YEARWEEK(created_at, 1)
            ORDER BY period_week ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {

            $labels[] = 'Week of ' . date(
                'M d',
                strtotime($row['week_start'])
            );

            $values[] = round(
                (float) $row['average_rating'],
                2
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MONTH
    |--------------------------------------------------------------------------
    |
    | Shows monthly average ratings for the last 12 months.
    |
    */

    elseif ($period === 'month') {

        $stmt = $db->query("
            SELECT
                YEAR(created_at) AS year_value,
                MONTH(created_at) AS month_value,
                AVG(rating) AS average_rating
            FROM feedback
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
            GROUP BY
                YEAR(created_at),
                MONTH(created_at)
            ORDER BY
                year_value ASC,
                month_value ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {

            $date = sprintf(
                '%04d-%02d-01',
                $row['year_value'],
                $row['month_value']
            );

            $labels[] = date(
                'M Y',
                strtotime($date)
            );

            $values[] = round(
                (float) $row['average_rating'],
                2
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | YEAR
    |--------------------------------------------------------------------------
    |
    | Shows yearly average ratings.
    |
    */

    else {

        $stmt = $db->query("
            SELECT
                YEAR(created_at) AS year_value,
                AVG(rating) AS average_rating
            FROM feedback
            GROUP BY YEAR(created_at)
            ORDER BY year_value ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {

            $labels[] = (string) $row['year_value'];

            $values[] = round(
                (float) $row['average_rating'],
                2
            );
        }
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'values' => $values
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$ratingFilter = isset($_GET['rating'])
    ? (int) $_GET['rating']
    : 0;

if ($ratingFilter < 1 || $ratingFilter > 5) {
    $ratingFilter = 0;
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 8;

$page = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;

$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| SUMMARY STATISTICS
|--------------------------------------------------------------------------
*/

$statsStmt = $db->query("
    SELECT
        COUNT(*) AS total_feedback,
        COALESCE(AVG(rating), 0) AS average_rating,
        SUM(
            CASE
                WHEN rating = 5 THEN 1
                ELSE 0
            END
        ) AS five_star,
        SUM(
            CASE
                WHEN rating <= 2 THEN 1
                ELSE 0
            END
        ) AS low_rating
    FROM feedback
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalFeedback = (int) ($stats['total_feedback'] ?? 0);

$averageRating = (float) ($stats['average_rating'] ?? 0);

$fiveStarCount = (int) ($stats['five_star'] ?? 0);

$lowRatingCount = (int) ($stats['low_rating'] ?? 0);

/*
|--------------------------------------------------------------------------
| BUILD FILTER QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            c.full_name LIKE ?
            OR d.full_name LIKE ?
            OR o.order_number LIKE ?
            OR f.comments LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($ratingFilter > 0) {

    $where[] = "f.rating = ?";

    $params[] = $ratingFilter;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| TOTAL FILTERED FEEDBACK
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM feedback f

    INNER JOIN `order` o
        ON o.order_id = f.order_id

    INNER JOIN customer c
        ON c.customer_id = f.customer_id

    LEFT JOIN driver d
        ON d.driver_id = f.driver_id

    $whereSql
";

$countStmt = $db->prepare($countSql);

$countStmt->execute($params);

$filteredCount = (int) $countStmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($filteredCount / $perPage)
);

if ($page > $totalPages) {

    $page = $totalPages;

    $offset = ($page - 1) * $perPage;
}

/*
|--------------------------------------------------------------------------
| FETCH FEEDBACK
|--------------------------------------------------------------------------
*/

$feedbackSql = "
    SELECT
        f.feedback_id,
        f.order_id,
        f.rating,
        f.comments,
        f.created_at,

        o.order_number,

        c.customer_id,
        c.full_name AS customer_name,

        d.driver_id,
        d.full_name AS driver_name

    FROM feedback f

    INNER JOIN `order` o
        ON o.order_id = f.order_id

    INNER JOIN customer c
        ON c.customer_id = f.customer_id

    LEFT JOIN driver d
        ON d.driver_id = f.driver_id

    $whereSql

    ORDER BY
        f.created_at DESC,
        f.feedback_id DESC

    LIMIT $perPage OFFSET $offset
";

$feedbackStmt = $db->prepare($feedbackSql);

$feedbackStmt->execute($params);

$feedbackList = $feedbackStmt->fetchAll(PDO::FETCH_ASSOC);

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
        Customer Feedback · Hong Sing LKRR Commercial
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/theme.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        /*
        ==========================================================
        PAGE
        ==========================================================
        */

        .feedback-page {
            width: 100%;
            margin: 0;
        }

        /*
        ==========================================================
        PAGE HEADER
        ==========================================================
        */

        .feedback-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            width: 100%;

            margin-bottom: 24px;
        }

        .feedback-page-header h1 {
            margin: 0;

            font-size: 1.65rem;
            line-height: 1.2;

            color: var(--color-text-primary);
        }

        .feedback-page-header p {
            margin: 6px 0 0;

            color: var(--color-text-secondary);

            font-size: 0.9rem;
        }

        /*
        ==========================================================
        SUMMARY CARDS
        ==========================================================
        */

        .feedback-stat-grid {
            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 18px;

            width: 100%;

            margin-bottom: 22px;
        }

        .feedback-stat {
            min-height: 125px;

            padding: 21px 22px;

            background: var(--color-surface);

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            box-shadow: var(--shadow-card);

            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .feedback-stat-label {
            margin-bottom: 8px;

            font-size: 0.72rem;
            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.05em;

            color: var(--color-text-muted);
        }

        .feedback-stat-value {
            font-size: 1.9rem;
            line-height: 1;

            font-weight: 700;

            color: var(--color-text-primary);
        }

        .feedback-stat-sub {
            margin-top: 7px;

            font-size: 0.76rem;

            color: var(--color-text-secondary);
        }

        .feedback-stat.rating .feedback-stat-value {
            color: var(--color-accent);
        }

        .feedback-stat.positive .feedback-stat-value {
            color: var(--color-success);
        }

        .feedback-stat.warning .feedback-stat-value {
            color: var(--color-warning);
        }

        /*
        ==========================================================
        RATING CHART
        ==========================================================
        */

        .rating-chart-card {
            width: 100%;

            background: var(--color-surface);

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            box-shadow: var(--shadow-card);

            margin-bottom: 22px;

            overflow: hidden;
        }

        .rating-chart-header {
            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 20px;

            padding: 20px 24px;

            border-bottom: 1px solid var(--color-border);
        }

        .rating-chart-heading h2 {
            margin: 0;

            font-size: 1.05rem;

            color: var(--color-text-primary);
        }

        .rating-chart-heading p {
            margin: 5px 0 0;

            font-size: 0.78rem;

            color: var(--color-text-muted);
        }

        /*
        ==========================================================
        PERIOD BUTTONS
        ==========================================================
        */

        .rating-periods {
            display: flex;

            align-items: center;

            gap: 5px;

            padding: 4px;

            background: var(--color-surface-alt);

            border: 1px solid var(--color-border);

            border-radius: 9px;
        }

        .rating-period-button {
            min-width: 65px;

            height: 34px;

            padding: 0 12px;

            border: none;

            border-radius: 6px;

            background: transparent;

            color: var(--color-text-secondary);

            font-family: inherit;

            font-size: 0.76rem;

            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.15s ease,
                color 0.15s ease,
                box-shadow 0.15s ease;
        }

        .rating-period-button:hover {
            color: var(--color-text-primary);

            background: var(--color-surface);
        }

        .rating-period-button.active {
            background: var(--color-surface);

            color: var(--color-accent);

            box-shadow:
                0 1px 4px
                rgba(15, 27, 48, 0.10);
        }

        /*
        ==========================================================
        CHART AREA
        ==========================================================
        */

        .rating-chart-body {
            width: 100%;

            height: 360px;

            padding: 22px 24px 24px;
        }

        .rating-chart-wrapper {
            position: relative;

            width: 100%;
            height: 100%;
        }

        #ratingTrendChart {
            width: 100% !important;
            height: 100% !important;
        }

        .chart-loading {
            position: absolute;

            inset: 0;

            display: none;

            align-items: center;
            justify-content: center;

            background: rgba(255, 255, 255, 0.75);

            z-index: 5;

            font-size: 0.82rem;

            color: var(--color-text-secondary);
        }

        /*
        ==========================================================
        FILTER CARD
        ==========================================================
        */

        .feedback-filter-card {
            width: 100%;

            padding: 18px;

            margin-bottom: 22px;

            background: var(--color-surface);

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            box-shadow: var(--shadow-card);
        }

        .feedback-filter-form {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                180px
                auto;

            gap: 12px;

            align-items: end;

            width: 100%;
        }

        .filter-group {
            min-width: 0;
        }

        .filter-label {
            display: block;

            margin-bottom: 6px;

            font-size: 0.74rem;

            font-weight: 700;

            color: var(--color-text-secondary);
        }

        .filter-input,
        .filter-select {
            width: 100%;

            height: 44px;

            padding: 0 13px;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-sm);

            background: var(--color-surface);

            color: var(--color-text-primary);

            font-family: inherit;

            font-size: 0.86rem;

            outline: none;

            box-sizing: border-box;
        }

        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--color-accent);

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }

        .filter-actions {
            display: flex;

            gap: 8px;
        }

        .filter-button {
            height: 44px;

            padding: 0 22px;

            border: none;

            border-radius: var(--radius-sm);

            background: var(--color-accent);

            color: #ffffff;

            font-family: inherit;

            font-size: 0.84rem;

            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.15s ease,
                transform 0.15s ease;
        }

        .filter-button:hover {
            background: var(--color-accent-hover);

            transform: translateY(-1px);
        }

        .clear-filter {
            height: 44px;

            padding: 0 16px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border: 1px solid var(--color-border);

            border-radius: var(--radius-sm);

            background: var(--color-surface);

            color: var(--color-text-secondary);

            font-size: 0.84rem;

            font-weight: 600;

            text-decoration: none;

            transition:
                border-color 0.15s ease,
                color 0.15s ease,
                background 0.15s ease;
        }

        .clear-filter:hover {
            border-color: var(--color-accent);

            color: var(--color-accent);

            background: var(--color-accent-soft);
        }

        /*
        ==========================================================
        RESULTS CARD
        ==========================================================
        */

        .feedback-results-card {
            width: 100%;

            background: var(--color-surface);

            border: 1px solid var(--color-border);

            border-radius: var(--radius-lg);

            box-shadow: var(--shadow-card);

            overflow: hidden;
        }

        .feedback-results-header {
            display: flex;

            align-items: center;
            justify-content: space-between;

            padding: 20px 24px;

            border-bottom: 1px solid var(--color-border);
        }

        .feedback-results-title h2 {
            margin: 0;

            font-size: 1.05rem;

            color: var(--color-text-primary);
        }

        .feedback-results-title p {
            margin: 4px 0 0;

            font-size: 0.77rem;

            color: var(--color-text-muted);
        }

        /*
        ==========================================================
        FEEDBACK LIST
        ==========================================================
        */

        .feedback-list {
            display: flex;

            flex-direction: column;

            width: 100%;
        }

        .feedback-item {
            display: grid;

            grid-template-columns:
                240px
                minmax(0, 1fr)
                150px;

            gap: 28px;

            width: 100%;

            padding: 24px;

            border-bottom: 1px solid var(--color-border);

            box-sizing: border-box;

            transition:
                background 0.15s ease;
        }

        .feedback-item:last-child {
            border-bottom: none;
        }

        .feedback-item:hover {
            background: var(--color-surface-alt);
        }

        /*
        ==========================================================
        CUSTOMER
        ==========================================================
        */

        .feedback-customer {
            min-width: 0;
        }

        .customer-name {
            margin-bottom: 7px;

            color: var(--color-text-primary);

            font-size: 0.92rem;

            font-weight: 700;

            word-break: break-word;
        }

        .customer-order {
            margin-bottom: 9px;

            color: var(--color-accent);

            font-size: 0.76rem;

            font-weight: 600;

            word-break: break-word;
        }

        .feedback-meta {
            font-size: 0.76rem;

            line-height: 1.5;

            color: var(--color-text-muted);
        }

        .feedback-meta strong {
            color: var(--color-text-secondary);
        }

        /*
        ==========================================================
        CONTENT
        ==========================================================
        */

        .feedback-content {
            min-width: 0;
        }

        .feedback-rating {
            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 10px;

            margin-bottom: 10px;
        }

        .feedback-stars {
            color: #f59e0b;

            font-size: 1.15rem;

            letter-spacing: 1px;

            white-space: nowrap;
        }

        .feedback-rating-label {
            display: inline-flex;

            align-items: center;

            padding: 4px 9px;

            border-radius: 20px;

            background: var(--color-accent-soft);

            color: var(--color-accent);

            font-size: 0.69rem;

            font-weight: 700;
        }

        .feedback-comment {
            margin: 0;

            color: var(--color-text-secondary);

            font-size: 0.87rem;

            line-height: 1.65;

            word-break: break-word;
        }

        .feedback-no-comment {
            margin: 0;

            color: var(--color-text-muted);

            font-size: 0.83rem;

            font-style: italic;
        }

        /*
        ==========================================================
        DATE
        ==========================================================
        */

        .feedback-date {
            text-align: right;

            color: var(--color-text-muted);

            font-size: 0.76rem;

            line-height: 1.6;
        }

        .feedback-date strong {
            display: block;

            margin-bottom: 2px;

            color: var(--color-text-secondary);

            font-size: 0.78rem;
        }

        /*
        ==========================================================
        EMPTY STATE
        ==========================================================
        */

        .feedback-empty {
            padding: 80px 30px;

            text-align: center;
        }

        .feedback-empty-icon {
            width: 58px;
            height: 58px;

            margin: 0 auto 16px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: var(--color-accent-soft);

            color: var(--color-accent);

            font-size: 1.5rem;
        }

        .feedback-empty h3 {
            margin: 0 0 6px;

            font-size: 1rem;

            color: var(--color-text-primary);
        }

        .feedback-empty p {
            margin: 0;

            color: var(--color-text-muted);

            font-size: 0.84rem;
        }

        /*
        ==========================================================
        PAGINATION
        ==========================================================
        */

        .feedback-pagination {
            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 15px;

            padding: 16px 24px;

            border-top: 1px solid var(--color-border);
        }

        .pagination-info {
            color: var(--color-text-muted);

            font-size: 0.78rem;
        }

        .pagination-links {
            display: flex;

            align-items: center;

            gap: 6px;
        }

        .pagination-link {
            min-width: 34px;

            height: 34px;

            padding: 0 9px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border: 1px solid var(--color-border);

            border-radius: 7px;

            background: var(--color-surface);

            color: var(--color-text-secondary);

            font-size: 0.78rem;

            font-weight: 600;

            text-decoration: none;

            box-sizing: border-box;

            transition:
                border-color 0.15s ease,
                color 0.15s ease,
                background 0.15s ease;
        }

        .pagination-link:hover {
            border-color: var(--color-accent);

            color: var(--color-accent);

            background: var(--color-accent-soft);
        }

        .pagination-link.active {
            background: var(--color-accent);

            border-color: var(--color-accent);

            color: #ffffff;
        }

        .pagination-link.disabled {
            opacity: 0.45;

            pointer-events: none;
        }

        /*
        ==========================================================
        RESPONSIVE
        ==========================================================
        */

        @media (max-width: 1100px) {

            .feedback-stat-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .feedback-item {
                grid-template-columns:
                    210px
                    minmax(0, 1fr);
            }

            .feedback-date {
                grid-column: 2;

                text-align: left;
            }
        }

        @media (max-width: 850px) {

            .rating-chart-header {
                align-items: flex-start;

                flex-direction: column;
            }

            .rating-periods {
                width: 100%;

                justify-content: stretch;
            }

            .rating-period-button {
                flex: 1;
            }

            .feedback-filter-form {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                width: 100%;
            }

            .filter-button,
            .clear-filter {
                flex: 1;
            }

            .feedback-item {
                grid-template-columns: 1fr;

                gap: 13px;
            }

            .feedback-date {
                grid-column: auto;

                text-align: left;
            }
        }

        @media (max-width: 550px) {

            .feedback-stat-grid {
                grid-template-columns: 1fr;
            }

            .feedback-page-header h1 {
                font-size: 1.4rem;
            }

            .rating-chart-body {
                height: 300px;

                padding: 15px;
            }

            .rating-chart-header {
                padding: 18px;
            }

            .feedback-filter-card {
                padding: 14px;
            }

            .feedback-results-header {
                padding: 18px;
            }

            .feedback-item {
                padding: 18px;
            }

            .feedback-pagination {
                flex-direction: column;

                align-items: flex-start;
            }

            .pagination-links {
                width: 100%;

                justify-content: center;

                flex-wrap: wrap;
            }
        }

    </style>

</head>

<body>

<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="app-main">

    <div class="feedback-page">

        <!-- ==================================================
             PAGE HEADER
        =================================================== -->

        <div class="feedback-page-header">

            <div>

                <h1>
                    Customer Feedback
                </h1>

                <p>
                    Monitor customer satisfaction and
                    review delivery feedback.
                </p>

            </div>

        </div>

        <!-- ==================================================
             STATISTICS
        =================================================== -->

        <section class="feedback-stat-grid">

            <div class="feedback-stat">

                <div class="feedback-stat-label">
                    Total Feedback
                </div>

                <div class="feedback-stat-value">
                    <?= number_format($totalFeedback) ?>
                </div>

                <div class="feedback-stat-sub">
                    All customer reviews
                </div>

            </div>

            <div class="feedback-stat rating">

                <div class="feedback-stat-label">
                    Average Rating
                </div>

                <div class="feedback-stat-value">

                    <?= number_format($averageRating, 1) ?>

                    <span style="font-size: 1rem;">
                        ★
                    </span>

                </div>

                <div class="feedback-stat-sub">
                    Overall customer satisfaction
                </div>

            </div>

            <div class="feedback-stat positive">

                <div class="feedback-stat-label">
                    5-Star Reviews
                </div>

                <div class="feedback-stat-value">
                    <?= number_format($fiveStarCount) ?>
                </div>

                <div class="feedback-stat-sub">
                    Excellent ratings
                </div>

            </div>

            <div class="feedback-stat warning">

                <div class="feedback-stat-label">
                    Low Ratings
                </div>

                <div class="feedback-stat-value">
                    <?= number_format($lowRatingCount) ?>
                </div>

                <div class="feedback-stat-sub">
                    Ratings of 1–2 stars
                </div>

            </div>

        </section>

        <!-- ==================================================
             RATING TREND
        =================================================== -->

        <section class="rating-chart-card">

            <div class="rating-chart-header">

                <div class="rating-chart-heading">

                    <h2>
                        Overall Customer Rating
                    </h2>

                    <p>
                        Average customer rating over time
                    </p>

                </div>

                <div class="rating-periods">

                    <button
                        type="button"
                        class="rating-period-button"
                        data-period="day"
                    >
                        Day
                    </button>

                    <button
                        type="button"
                        class="rating-period-button"
                        data-period="week"
                    >
                        Week
                    </button>

                    <button
                        type="button"
                        class="rating-period-button active"
                        data-period="month"
                    >
                        Month
                    </button>

                    <button
                        type="button"
                        class="rating-period-button"
                        data-period="year"
                    >
                        Year
                    </button>

                </div>

            </div>

            <div class="rating-chart-body">

                <div class="rating-chart-wrapper">

                    <div
                        id="chartLoading"
                        class="chart-loading"
                    >
                        Loading rating data...
                    </div>

                    <canvas id="ratingTrendChart"></canvas>

                </div>

            </div>

        </section>

        <!-- ==================================================
             SEARCH / FILTER
        =================================================== -->

        <section class="feedback-filter-card">

            <form
                method="GET"
                action=""
                class="feedback-filter-form"
            >

                <div class="filter-group">

                    <label
                        for="search"
                        class="filter-label"
                    >
                        Search Feedback
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="filter-input"
                        placeholder="Customer, driver, order number, or comment..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>

                <div class="filter-group">

                    <label
                        for="rating"
                        class="filter-label"
                    >
                        Rating
                    </label>

                    <select
                        id="rating"
                        name="rating"
                        class="filter-select"
                    >

                        <option value="0">
                            All Ratings
                        </option>

                        <?php for ($i = 5; $i >= 1; $i--): ?>

                            <option
                                value="<?= $i ?>"
                                <?= $ratingFilter === $i ? 'selected' : '' ?>
                            >
                                <?= $i ?>
                                Star<?= $i > 1 ? 's' : '' ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

                <div class="filter-actions">

                    <button
                        type="submit"
                        class="filter-button"
                    >
                        Search
                    </button>

                    <?php if (
                        $search !== '' ||
                        $ratingFilter > 0
                    ): ?>

                        <a
                            href="<?= BASE_URL ?>admin/feedback.php"
                            class="clear-filter"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>

        <!-- ==================================================
             FEEDBACK RESULTS
        =================================================== -->

        <section class="feedback-results-card">

            <div class="feedback-results-header">

                <div class="feedback-results-title">

                    <h2>
                        Customer Reviews
                    </h2>

                    <p>

                        <?= number_format($filteredCount) ?>

                        feedback record<?= $filteredCount !== 1 ? 's' : '' ?>

                        found

                    </p>

                </div>

            </div>

            <?php if (empty($feedbackList)): ?>

                <div class="feedback-empty">

                    <div class="feedback-empty-icon">
                        ★
                    </div>

                    <h3>
                        No feedback found
                    </h3>

                    <p>
                        No customer feedback matches
                        your current filters.
                    </p>

                </div>

            <?php else: ?>

                <div class="feedback-list">

                    <?php foreach ($feedbackList as $feedback): ?>

                        <?php

                        $rating = (int) $feedback['rating'];

                        $customerName =
                            $feedback['customer_name']
                            ?: 'Unknown Customer';

                        $driverName =
                            $feedback['driver_name']
                            ?: 'No Driver Assigned';

                        $orderNumber =
                            $feedback['order_number']
                            ?: 'Order #' . $feedback['order_id'];

                        ?>

                        <article class="feedback-item">

                            <!-- CUSTOMER -->

                            <div class="feedback-customer">

                                <div class="customer-name">

                                    <?= htmlspecialchars(
                                        $customerName
                                    ) ?>

                                </div>

                                <div class="customer-order">

                                    <?= htmlspecialchars(
                                        $orderNumber
                                    ) ?>

                                </div>

                                <div class="feedback-meta">

                                    <strong>
                                        Driver:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $driverName
                                    ) ?>

                                </div>

                            </div>

                            <!-- CONTENT -->

                            <div class="feedback-content">

                                <div class="feedback-rating">

                                    <span class="feedback-stars">

                                        <?= feedbackStars($rating) ?>

                                    </span>

                                    <span class="feedback-rating-label">

                                        <?= htmlspecialchars(
                                            ratingLabel($rating)
                                        ) ?>

                                    </span>

                                </div>

                                <?php if (
                                    !empty(
                                        trim(
                                            $feedback['comments'] ?? ''
                                        )
                                    )
                                ): ?>

                                    <p class="feedback-comment">

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $feedback['comments']
                                            )
                                        ) ?>

                                    </p>

                                <?php else: ?>

                                    <p class="feedback-no-comment">
                                        No written comment provided.
                                    </p>

                                <?php endif; ?>

                            </div>

                            <!-- DATE -->

                            <div class="feedback-date">

                                <strong>
                                    Submitted
                                </strong>

                                <?php if (
                                    !empty($feedback['created_at'])
                                ): ?>

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $feedback['created_at']
                                        )
                                    ) ?>

                                    <br>

                                    <?= date(
                                        'h:i A',
                                        strtotime(
                                            $feedback['created_at']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

                <!-- ==================================================
                     PAGINATION
                =================================================== -->

                <?php if ($totalPages > 1): ?>

                    <div class="feedback-pagination">

                        <div class="pagination-info">

                            Page
                            <?= $page ?>
                            of
                            <?= $totalPages ?>

                        </div>

                        <div class="pagination-links">

                            <?php

                            $queryParams = [];

                            if ($search !== '') {
                                $queryParams['search'] = $search;
                            }

                            if ($ratingFilter > 0) {
                                $queryParams['rating'] = $ratingFilter;
                            }

                            ?>

                            <!-- PREVIOUS -->

                            <?php if ($page > 1): ?>

                                <?php

                                $queryParams['page'] = $page - 1;

                                $previousUrl =
                                    'feedback.php?' .
                                    http_build_query($queryParams);

                                ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        $previousUrl
                                    ) ?>"
                                    class="pagination-link"
                                >
                                    ‹
                                </a>

                            <?php else: ?>

                                <span
                                    class="pagination-link disabled"
                                >
                                    ‹
                                </span>

                            <?php endif; ?>

                            <!-- PAGE NUMBERS -->

                            <?php

                            $startPage =
                                max(1, $page - 2);

                            $endPage =
                                min(
                                    $totalPages,
                                    $page + 2
                                );

                            for (
                                $paginationPage = $startPage;
                                $paginationPage <= $endPage;
                                $paginationPage++
                            ):

                                $queryParams['page'] =
                                    $paginationPage;

                                $pageUrl =
                                    'feedback.php?' .
                                    http_build_query($queryParams);

                            ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        $pageUrl
                                    ) ?>"
                                    class="pagination-link <?= $paginationPage === $page ? 'active' : '' ?>"
                                >
                                    <?= $paginationPage ?>
                                </a>

                            <?php endfor; ?>

                            <!-- NEXT -->

                            <?php if ($page < $totalPages): ?>

                                <?php

                                $queryParams['page'] =
                                    $page + 1;

                                $nextUrl =
                                    'feedback.php?' .
                                    http_build_query($queryParams);

                                ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        $nextUrl
                                    ) ?>"
                                    class="pagination-link"
                                >
                                    ›
                                </a>

                            <?php else: ?>

                                <span
                                    class="pagination-link disabled"
                                >
                                    ›
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </section>

    </div>

</main>

<script>

/*
|--------------------------------------------------------------------------
| CUSTOMER RATING TREND CHART
|--------------------------------------------------------------------------
*/

const chartCanvas =
    document.getElementById('ratingTrendChart');

const chartLoading =
    document.getElementById('chartLoading');

const periodButtons =
    document.querySelectorAll(
        '.rating-period-button'
    );

let ratingChart = null;

/*
|--------------------------------------------------------------------------
| LOAD GRAPH DATA
|--------------------------------------------------------------------------
*/

async function loadRatingChart(period) {

    chartLoading.style.display = 'flex';

    try {

        const url =
            'feedback.php?rating_data=' +
            encodeURIComponent(period);

        const response =
            await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });

        if (!response.ok) {

            throw new Error(
                'Unable to load rating data.'
            );
        }

        const data =
            await response.json();

        if (!data.success) {

            throw new Error(
                data.message ||
                'Unable to load rating data.'
            );
        }

        renderRatingChart(
            data.labels,
            data.values,
            period
        );

    } catch (error) {

        console.error(error);

        renderRatingChart(
            [],
            [],
            period
        );

    } finally {

        chartLoading.style.display = 'none';
    }
}

/*
|--------------------------------------------------------------------------
| RENDER GRAPH
|--------------------------------------------------------------------------
*/

function renderRatingChart(
    labels,
    values,
    period
) {

    if (ratingChart) {
        ratingChart.destroy();
    }

    const context =
        chartCanvas.getContext('2d');

    /*
    |--------------------------------------------------------------------------
    | GRADIENT
    |--------------------------------------------------------------------------
    */

    const gradient =
        context.createLinearGradient(
            0,
            0,
            0,
            320
        );

    gradient.addColorStop(
        0,
        'rgba(37, 99, 235, 0.20)'
    );

    gradient.addColorStop(
        1,
        'rgba(37, 99, 235, 0.00)'
    );

    /*
    |--------------------------------------------------------------------------
    | CHART
    |--------------------------------------------------------------------------
    */

    ratingChart =
        new Chart(
            context,
            {
                type: 'line',

                data: {

                    labels: labels,

                    datasets: [
                        {
                            label:
                                'Average Rating',

                            data: values,

                            borderColor:
                                '#2563eb',

                            backgroundColor:
                                gradient,

                            borderWidth: 3,

                            pointRadius: 4,

                            pointHoverRadius: 6,

                            pointBackgroundColor:
                                '#ffffff',

                            pointBorderColor:
                                '#2563eb',

                            pointBorderWidth: 2,

                            fill: true,

                            tension: 0.35
                        }
                    ]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    interaction: {

                        intersect: false,

                        mode: 'index'
                    },

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: {

                            callbacks: {

                                label: function(context) {

                                    const value =
                                        Number(
                                            context.parsed.y
                                        );

                                    return (
                                        ' Average Rating: ' +
                                        value.toFixed(2) +
                                        ' / 5'
                                    );
                                }
                            }
                        }
                    },

                    scales: {

                        x: {

                            grid: {
                                display: false
                            },

                            ticks: {

                                color:
                                    '#8493ae',

                                font: {
                                    size: 11
                                },

                                maxRotation: 0
                            }
                        },

                        y: {

                            min: 0,

                            max: 5,

                            ticks: {

                                stepSize: 1,

                                color:
                                    '#8493ae',

                                font: {
                                    size: 11
                                }
                            },

                            title: {

                                display: true,

                                text:
                                    'Average Rating',

                                color:
                                    '#4c5d7c',

                                font: {

                                    size: 11,

                                    weight: '600'
                                }
                            },

                            grid: {

                                color:
                                    '#e8eefb'
                            }
                        }
                    }
                }
            }
        );
}

/*
|--------------------------------------------------------------------------
| PERIOD BUTTONS
|--------------------------------------------------------------------------
*/

periodButtons.forEach(button => {

    button.addEventListener(
        'click',
        function() {

            periodButtons.forEach(item => {

                item.classList.remove(
                    'active'
                );

            });

            this.classList.add('active');

            loadRatingChart(
                this.dataset.period
            );

        }
    );

});

/*
|--------------------------------------------------------------------------
| INITIAL GRAPH
|--------------------------------------------------------------------------
*/

loadRatingChart('month');

</script>

</body>
</html>