<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['admin']);

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Database connection is not available. Check config/config.php.');
}

try {
    $sql = "
        SELECT
            u.id,
            u.name,
            u.email,
            COUNT(c.id) AS assigned_total,
            COALESCE(SUM(c.status = 'Pending'), 0) AS pending_count,
            COALESCE(SUM(c.status = 'In Progress'), 0) AS in_progress_count,
            COALESCE(SUM(c.status = 'Resolved'), 0) AS resolved_count,
            COALESCE(SUM(c.status = 'Rejected'), 0) AS rejected_count
        FROM users u
        LEFT JOIN complaints c ON c.assigned_to = u.id
        WHERE u.role = 'staff'
        GROUP BY u.id, u.name, u.email
        ORDER BY resolved_count DESC, assigned_total DESC, u.name ASC
    ";

    $stmt = $pdo->query($sql);
    $staffRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'staff' => count($staffRows),
        'assigned' => 0,
        'pending' => 0,
        'progress' => 0,
        'resolved' => 0,
        'rejected' => 0,
    ];

    foreach ($staffRows as $staff) {
        $summary['assigned'] += (int) $staff['assigned_total'];
        $summary['pending'] += (int) $staff['pending_count'];
        $summary['progress'] += (int) $staff['in_progress_count'];
        $summary['resolved'] += (int) $staff['resolved_count'];
        $summary['rejected'] += (int) $staff['rejected_count'];
    }

    $summary['resolution_rate'] = $summary['assigned'] > 0
        ? round(($summary['resolved'] / $summary['assigned']) * 100, 1)
        : 0.0;

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $filename = 'staff_performance_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM helps Excel display names correctly.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Staff ID',
            'Staff Name',
            'Email',
            'Assigned Complaints',
            'Pending',
            'In Progress',
            'Resolved',
            'Rejected',
            'Resolution Rate (%)'
        ]);

        foreach ($staffRows as $staff) {
            $assigned = (int) $staff['assigned_total'];
            $resolved = (int) $staff['resolved_count'];
            $rate = $assigned > 0
                ? round(($resolved / $assigned) * 100, 1)
                : 0;

            fputcsv($output, [
                $staff['id'],
                $staff['name'],
                $staff['email'],
                $assigned,
                $staff['pending_count'],
                $staff['in_progress_count'],
                $resolved,
                $staff['rejected_count'],
                $rate
            ]);
        }

        fclose($output);
        exit;
    }
} catch (Throwable $e) {
    error_log('Staff performance report error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load the staff performance report. Check the PHP error log.');
}

$totalResolved = (int) $summary['resolved'];
$totalAssigned = (int) $summary['assigned'];
$overallRate = $summary['resolution_rate'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Performance | CampusCare</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --bg: #f4f6fb;
            --surface: #ffffff;
            --text: #172033;
            --muted: #667085;
            --border: #ffffff;
            --green: #15803d;
            --orange: #c2410c;
            --red: #b91c1c;
            --shadow: 0 8px 24px rgba(20, 30, 60, .06);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .topbar {
            background: #fdfdfd;
            color: #020000;
            padding: 17px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: .3px;
        }


.top-links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.top-links a,
.top-links a:visited {
    color: #000000;
    text-decoration: none;
    padding: 0px 12px;
    font-size: 18px;
    border-bottom: 2px solid transparent;
    transition: color 0.2s ease, background 0.2s ease, border-color 0.2s ease;
}

.top-links a:hover,
.top-links a:active,
.top-links a:focus {
    color: #2563eb;
    background: #ffffff;
    text-decoration: none;
    border-bottom: 2px solid #2563eb;
}

        .container {
            max-width: 1400px;
            width: 92%;
            margin: 30px auto 50px;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        h1 {
            font-size: clamp(25px, 3vw, 34px);
            margin: 0 0 8px;
        }

        .subtitle {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 15px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-light {
            background: #ffffff;
            border-color: rgb(196, 198, 201);
            color: rgb(61, 119, 226);
        }
        
        .btn-light:hover {
            background: #5047cd;
            border-color: rgb(101, 108, 241);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 21px;
            box-shadow: var(--shadow);
            min-width: 0;
        }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 29px;
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .stat-note {
            margin-top: 9px;
            color: var(--muted);
            font-size: 12px;
        }

        .report-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel-heading {
            padding: 21px 23px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .panel-heading h2 {
            margin: 0 0 5px;
            font-size: 20px;
        }

        .panel-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .search-box {
            width: 260px;
            max-width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font: inherit;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        th, td {
            padding: 15px 16px;
            text-align: left;
            border-bottom: 1px solid #edf0f5;
            font-size: 13px;
            white-space: nowrap;
        }

        th {
            background: #f8fafc;
            color: #475467;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        tbody tr:hover {
            background: #fafbff;
        }

        .staff-name {
            font-weight: 700;
            color: #1f2937;
        }

        .staff-email {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .number {
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-pending {
            background: #fff7ed;
            color: var(--orange);
        }

        .status-progress {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-resolved {
            background: #f0fdf4;
            color: var(--green);
        }

        .status-rejected {
            background: #fef2f2;
            color: var(--red);
        }

        .rate-cell {
            min-width: 145px;
        }

        .rate-label {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .progress-track {
            width: 100%;
            height: 7px;
            border-radius: 10px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: inherit;
        }

        .empty-state {
            padding: 45px 20px;
            text-align: center;
            color: var(--muted);
        }

        .panel-footer {
            padding: 15px 22px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .logout button {
    border: 0;
    background: blue;
    color: #efefef;
    cursor: pointer;
    font: inherit;
    font-weight: 600;
    padding: 9px 16px;
    border-radius: 8px;
    transition: background 0.2s ease;
}

.logout button:hover {
    background: #0000a0;
}

        @media (max-width: 1150px) {
            .stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {
            .container {
                width: 94%;
                margin-top: 22px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 25px;
            }

            .panel-heading {
                padding: 17px;
            }

            .search-box {
                width: 100%;
            }

            .topbar {
                padding: 15px 4%;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="brand">CampusCare</div>

    <nav class="top-links">
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

<main class="container">
    <section class="page-heading">
        <div>
            <h1>Staff Performance Report</h1>
            <p class="subtitle">
                Review staff workload, complaint progress and resolution performance.
            </p>
        </div>

        <div class="actions">
            <a class="btn btn-light"
               href="<?= htmlspecialchars(BASE_URL . '/admin/dashboard.php') ?>">
                Back to Dashboard
            </a>

            <a class="btn btn-primary"
               href="<?= htmlspecialchars(BASE_URL . '/admin/staff_performance.php?export=csv') ?>">
                Export CSV
            </a>
        </div>
    </section>

    <section class="stats">
        <article class="stat-card">
            <div class="stat-label">Total Staff</div>
            <div class="stat-value"><?= number_format($summary['staff']) ?></div>
            <div class="stat-note">Registered staff accounts</div>
        </article>

        <article class="stat-card">
            <div class="stat-label">Assigned Complaints</div>
            <div class="stat-value"><?= number_format($summary['assigned']) ?></div>
            <div class="stat-note">Complaints assigned to staff</div>
        </article>

        <article class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= number_format($summary['pending']) ?></div>
            <div class="stat-note">Awaiting action</div>
        </article>

        <article class="stat-card">
            <div class="stat-label">Resolved</div>
            <div class="stat-value"><?= number_format($summary['resolved']) ?></div>
            <div class="stat-note">Marked as resolved</div>
        </article>

        <article class="stat-card">
            <div class="stat-label">Overall Resolution Rate</div>
            <div class="stat-value"><?= number_format($overallRate, 1) ?>%</div>
            <div class="stat-note">Resolved ÷ assigned complaints</div>
        </article>
    </section>

    <section class="report-panel">
        <div class="panel-heading">
            <div>
                <h2>Staff-wise Breakdown</h2>
                <p>Search staff by name or email.</p>
            </div>

            <input
                type="search"
                id="staffSearch"
                class="search-box"
                placeholder="Search staff..."
                aria-label="Search staff by name or email"
            >
        </div>

        <?php if (count($staffRows) > 0): ?>
            <div class="table-wrap">
                <table id="staffTable">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Assigned</th>
                            <th>Pending</th>
                            <th>In Progress</th>
                            <th>Resolved</th>
                            <th>Rejected</th>
                            <th>Resolution Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($staffRows as $staff): ?>
                        <?php
                        $assigned = (int) $staff['assigned_total'];
                        $pending = (int) $staff['pending_count'];
                        $progress = (int) $staff['in_progress_count'];
                        $resolved = (int) $staff['resolved_count'];
                        $rejected = (int) $staff['rejected_count'];

                        $rate = $assigned > 0
                            ? round(($resolved / $assigned) * 100, 1)
                            : 0.0;

                        $staffName = (string) $staff['name'];
                        $staffEmail = (string) $staff['email'];
                        ?>
                        <tr>
                            <td>
                                <div class="staff-name">
                                    <?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="staff-email">
                                    <?= htmlspecialchars($staffEmail, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>

                            <td class="number"><?= number_format($assigned) ?></td>

                            <td>
                                <span class="status status-pending">
                                    <?= number_format($pending) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status status-progress">
                                    <?= number_format($progress) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status status-resolved">
                                    <?= number_format($resolved) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status status-rejected">
                                    <?= number_format($rejected) ?>
                                </span>
                            </td>

                            <td class="rate-cell">
                                <div class="rate-label">
                                    <span><?= number_format($rate, 1) ?>%</span>
                                </div>
                                <div
                                    class="progress-track"
                                    role="progressbar"
                                    aria-label="Resolution rate for <?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="<?= $rate ?>"
                                >
                                    <div
                                        class="progress-fill"
                                        style="width: <?= min(100, max(0, $rate)) ?>%"
                                    ></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No staff members found</h3>
                <p>Add staff accounts to see their complaint performance here.</p>
            </div>
        <?php endif; ?>

        <div class="panel-footer">
            Resolution rate = resolved complaints divided by all complaints assigned
            to that staff member, multiplied by 100. Staff with no assignments have
            a rate of 0%. Only complaints currently assigned to each staff member
            are counted.
        </div>
    </section>
</main>

<script>
    const searchInput = document.getElementById('staffSearch');
    const table = document.getElementById('staffTable');

    if (searchInput && table) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();

            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.hidden = !row.textContent.toLowerCase().includes(query);
            });
        });
    }
</script>

</body>
</html>
