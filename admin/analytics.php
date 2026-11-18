<?php

require_once __DIR__ . '/../includes/functions.php';

$admin = require_role(['admin']);
$pdo = db();

// Overall complaint statistics.
$stmt = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'In Progress'), 0) AS in_progress,
        COALESCE(SUM(status = 'Resolved'), 0) AS resolved,
        COALESCE(SUM(status = 'Rejected'), 0) AS rejected
     FROM complaints"
);
$stats = $stmt->fetch();

// Student feedback statistics.
$stmt = $pdo->query(
    "SELECT
        COUNT(*) AS feedback_count,
        COALESCE(AVG(rating), 0) AS average_rating
     FROM feedback"
);
$feedbackStats = $stmt->fetch();

// Average resolution time based on recorded Resolved updates.
$stmt = $pdo->query(
    "SELECT
        COUNT(*) AS resolved_count,
        COALESCE(
            AVG(TIMESTAMPDIFF(MINUTE, c.created_at, r.resolved_at)) / 60,
            0
        ) AS average_hours
     FROM complaints c
     INNER JOIN (
         SELECT complaint_id, MIN(created_at) AS resolved_at
         FROM complaint_updates
         WHERE status = 'Resolved'
         GROUP BY complaint_id
     ) r ON r.complaint_id = c.id
     WHERE r.resolved_at >= c.created_at"
);
$resolutionStats = $stmt->fetch();

// Status distribution.
$stmt = $pdo->query(
    "SELECT status, COUNT(*) AS total
     FROM complaints
     GROUP BY status
     ORDER BY total DESC"
);
$statusRows = $stmt->fetchAll();

// Category distribution.
$stmt = $pdo->query(
    "SELECT category, COUNT(*) AS total
     FROM complaints
     GROUP BY category
     ORDER BY total DESC, category ASC"
);
$categoryRows = $stmt->fetchAll();

// Monthly complaint totals for the current year.
$stmt = $pdo->query(
    "SELECT MONTH(created_at) AS month_number, COUNT(*) AS total
     FROM complaints
     WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
     GROUP BY MONTH(created_at)
     ORDER BY month_number"
);
$monthlyRows = $stmt->fetchAll();

$monthlyTotals = array_fill(1, 12, 0);

foreach ($monthlyRows as $row) {
    $monthlyTotals[(int) $row['month_number']] = (int) $row['total'];
}

$monthNames = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
];

$maxMonthly = max(1, max($monthlyTotals));
$total = (int) $stats['total'];

function analytics_percent(int $value, int $total): float
{
    return $total > 0 ? ($value / $total) * 100 : 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Complaint Analytics | CampusCare</title>

    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/css/style.css">

    <style>
        .analytics-heading {
            margin-bottom: 26px;
        }

        .analytics-heading h1 {
            margin-bottom: 6px;
        }

        .analytics-heading p {
            margin: 0;
            color: var(--muted);
        }

        .analytics-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
            margin-bottom: 24px;
            justify-content: flex-end;
            align-items: center;   
        }

        /* Seven statistics cards */
        .analytics-stats {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            grid-auto-flow: row;
            gap: 16px;
            width: 100%;
            margin: 0 0 30px;
            align-items: stretch;
        }

        .analytics-stats > .analytics-stat {
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-sizing: border-box;
            min-width: 0;
            min-height: 145px;
            padding: 20px;
            overflow-wrap: anywhere;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .analytics-stats > .analytics-stat p {
            margin: 0 0 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .analytics-stats > .analytics-stat strong {
            display: block;
            font-size: 29px;
            line-height: 1.3;
            font-weight: 700;
        }

        .analytics-stats > .analytics-stat small {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        /* Analytics charts */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            align-items: start;
        }

        .analytics-panel {
            box-sizing: border-box;
            min-width: 0;
            padding: 26px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .analytics-panel h2 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .analytics-subtitle {
            margin-bottom: 24px;
            color: var(--muted);
            font-size: 14px;
        }

        .bar-item {
            margin-bottom: 22px;
        }

        .bar-label {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .bar-label strong {
            overflow-wrap: anywhere;
        }

        .bar-track {
            width: 100%;
            height: 11px;
            overflow: hidden;
            border-radius: 20px;
            background: #edf0f7;
        }

        .bar-fill {
            height: 100%;
            min-width: 0;
            border-radius: inherit;
            background: var(--primary);
        }

        .status-pending {
            background: #d97706;
        }

        .status-progress {
            background: #4f46e5;
        }

        .status-resolved {
            background: #16a34a;
        }

        .status-rejected {
            background: #dc2626;
        }

        /* Monthly chart */
        .monthly-chart {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 8px;
            align-items: end;
            height: 230px;
            padding-top: 10px;
        }

        .month-column {
            display: flex;
            height: 100%;
            flex-direction: column;
            align-items: center;
            justify-content: end;
            gap: 8px;
            min-width: 0;
        }

        .month-value {
            font-size: 11px;
            color: var(--muted);
        }

        .month-bar-area {
            display: flex;
            width: 100%;
            height: 165px;
            align-items: end;
            justify-content: center;
        }

        .month-bar {
            width: min(26px, 90%);
            min-height: 3px;
            border-radius: 6px 6px 0 0;
            background: var(--primary);
        }

        .month-label {
            color: var(--muted);
            font-size: 11px;
        }

        .analytics-empty {
            padding: 20px 0;
            color: var(--muted);
        }

        .analytics-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.analytics-header h1 {
    margin: 0 0 8px;
}

.analytics-header p {
    margin: 0;
}

.analytics-header .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    white-space: nowrap;
    text-decoration: none !important;
}

        @media (max-width: 1000px) {
            .analytics-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 700px) {
            .analytics-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 12px;
            }

            .analytics-stats > .analytics-stat {
                min-height: 125px;
                padding: 16px;
            }

            .analytics-stats > .analytics-stat strong {
                font-size: 25px;
            }

            .analytics-grid {
                grid-template-columns: 1fr;
            }

            .analytics-panel {
                padding: 20px;
            }

            .monthly-chart {
                gap: 4px;
            }

            .month-label {
                font-size: 10px;
            }
        }

        @media (max-width: 380px) {
            .analytics-stats {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/admin/dashboard.php">
        <span style="font-size:28px;color:black">CampusCare Admin</span>
    </a>

    <nav>
         <a href="<?= BASE_URL ?>/admin/export_complaints.php">
    Export Complaints
</a>
        <a href="<?= e(BASE_URL) ?>/admin/users.php">Users</a>

<a href="<?= BASE_URL ?>/admin/staff.php">Staff</a>
<a href="<?= BASE_URL ?>/admin/staff_performance.php">
    Staff Performance Report
</a>
        <a href="<?= e(BASE_URL) ?>/admin/analytics.php">Analytics</a>
                <a href="<?= BASE_URL ?>/admin/assign_complaints.php">Assignments</a>
        <form method="POST" action="../logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                Logout
            </button>
    </nav>
</header>

<main class="page-container">

    <div class="analytics-header">
    <div>
        <h1>Complaint Analytics</h1>
        <p>Monitor complaint volume, status, student satisfaction, and resolution trends.</p>
    </div>

    <a class="btn btn-secondary"
       href="<?= e(BASE_URL) ?>/admin/dashboard.php">
        &larr; Back to Dashboard
    </a>
</div>

    <section class="analytics-stats">

        <article class="analytics-stat">
            <p>Total Complaints</p>
            <strong><?= $total ?></strong>
        </article>

        <article class="analytics-stat">
            <p>Pending</p>
            <strong><?= (int) $stats['pending'] ?></strong>
        </article>

        <article class="analytics-stat">
            <p>In Progress</p>
            <strong><?= (int) $stats['in_progress'] ?></strong>
        </article>

        <article class="analytics-stat">
            <p>Resolved</p>
            <strong><?= (int) $stats['resolved'] ?></strong>
        </article>

        <article class="analytics-stat">
            <p>Rejected</p>
            <strong><?= (int) $stats['rejected'] ?></strong>
        </article>

        <article class="analytics-stat">
            <p>Student Satisfaction</p>

            <strong>
                <?= number_format((float) $feedbackStats['average_rating'], 1) ?>/5
            </strong>

            <small>
                <?= (int) $feedbackStats['feedback_count'] ?>
                feedback response<?= (int) $feedbackStats['feedback_count'] === 1 ? '' : 's' ?>
            </small>
        </article>

        <article class="analytics-stat">
            <p>Average Resolution Time</p>

            <strong>
                <?php if ((int) $resolutionStats['resolved_count'] > 0): ?>
                    <?= number_format((float) $resolutionStats['average_hours'], 1) ?> hrs
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </strong>

            <small>
                Based on <?= (int) $resolutionStats['resolved_count'] ?>
                recorded resolution update<?= (int) $resolutionStats['resolved_count'] === 1 ? '' : 's' ?>
            </small>
        </article>

    </section>

    <div class="analytics-grid">

        <section class="analytics-panel">
            <h2>Status Distribution</h2>

            <p class="analytics-subtitle">
                Current status of all recorded complaints.
            </p>

            <?php if (!$total): ?>
                <p class="analytics-empty">
                    No complaints are available to analyze yet.
                </p>
            <?php else: ?>

                <?php
                $statusColors = [
                    'Pending' => 'status-pending',
                    'In Progress' => 'status-progress',
                    'Resolved' => 'status-resolved',
                    'Rejected' => 'status-rejected',
                ];
                ?>

                <?php foreach ($statusRows as $row): ?>
                    <?php
                    $count = (int) $row['total'];
                    $percentage = analytics_percent($count, $total);
                    $colorClass = $statusColors[$row['status']] ?? '';
                    ?>

                    <div class="bar-item">
                        <div class="bar-label">
                            <strong><?= e($row['status']) ?></strong>
                            <span>
                                <?= $count ?>
                                (<?= number_format($percentage, 1) ?>%)
                            </span>
                        </div>

                        <div class="bar-track">
                            <div
                                class="bar-fill <?= e($colorClass) ?>"
                                style="width:<?= $percentage ?>%">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>Complaints by Category</h2>

            <p class="analytics-subtitle">
                Categories with the highest complaint volume.
            </p>

            <?php if (!$categoryRows): ?>
                <p class="analytics-empty">
                    No category data available yet.
                </p>
            <?php else: ?>

                <?php foreach ($categoryRows as $row): ?>
                    <?php
                    $count = (int) $row['total'];
                    $percentage = analytics_percent($count, $total);
                    ?>

                    <div class="bar-item">
                        <div class="bar-label">
                            <strong><?= e($row['category']) ?></strong>
                            <span>
                                <?= $count ?>
                                (<?= number_format($percentage, 1) ?>%)
                            </span>
                        </div>

                        <div class="bar-track">
                            <div class="bar-fill"
                                 style="width:<?= $percentage ?>%">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </section>

        <section class="analytics-panel" style="grid-column:1 / -1">
            <h2>Monthly Complaint Trend</h2>

            <p class="analytics-subtitle">
                Complaints submitted each month in <?= date('Y') ?>.
            </p>

            <div class="monthly-chart">

                <?php foreach ($monthlyTotals as $monthNumber => $count): ?>
                    <?php
                    $height = $count > 0
                        ? max(5, ($count / $maxMonthly) * 100)
                        : 0;
                    ?>

                    <div class="month-column">
                        <span class="month-value"><?= $count ?></span>

                        <div class="month-bar-area">
                            <div class="month-bar"
                                 style="height:<?= $height ?>%">
                            </div>
                        </div>

                        <span class="month-label">
                            <?= $monthNames[$monthNumber - 1] ?>
                        </span>
                    </div>
                <?php endforeach; ?>

            </div>
        </section>

    </div>

</main>

</body>
</html>
