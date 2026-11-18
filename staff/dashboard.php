
<?php
require_once __DIR__ . '/../includes/functions.php';

$staff = require_role(['staff']);
$pdo = db();
$staffId = (int) $staff['id'];

// Get statistics for this staff member's assigned complaints.
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'In Progress'), 0) AS in_progress,
        COALESCE(SUM(status = 'Resolved'), 0) AS resolved,
        COALESCE(SUM(status = 'Rejected'), 0) AS rejected
    FROM complaints
    WHERE assigned_to = ?
");
$stmt->execute([$staffId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get the most recently updated assigned complaints.
$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.title,
        c.category,
        c.priority,
        c.status,
        c.created_at,
        c.updated_at,
        u.name AS student_name
    FROM complaints c
    INNER JOIN users u ON u.id = c.user_id
    WHERE c.assigned_to = ?
    ORDER BY c.updated_at DESC, c.id DESC
    LIMIT 100
");
$stmt->execute([$staffId]);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

function staff_status_class(string $status): string
{
    return match ($status) {
        'Pending' => 'pending',
        'In Progress' => 'in-progress',
        'Resolved' => 'resolved',
        'Rejected' => 'rejected',
        default => 'unknown',
    };
}

function staff_priority_class(string $priority): string
{
    return match (strtolower($priority)) {
        'high' => 'priority-high',
        'medium' => 'priority-medium',
        'low' => 'priority-low',
        default => 'priority-unknown',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Dashboard | CampusCare</title>

    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f7ff;
            color: #172554;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #fff;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: 0 2px 12px rgba(23, 37, 84, .06);
        }

        .brand {
            color: #000005;
            font-size: 28px;
            font-weight: 800;
            text-decoration: none;
        }

        .topbar nav {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .topbar nav a {
            color: #475569;
            text-decoration: none;
            font-weight: 600;
        }

        .topbar nav a:hover {
            color: #4f46e5;
        }

        .logout-form {
            margin: 0;
        }

        .logout-button {
            background: #4f46e5;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .staff-wrap {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .staff-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .staff-head h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .staff-head p {
            color: #64748b;
            margin: 0;
            line-height: 1.6;
        }

        .staff-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .staff-stat {
            background: #fff;
            padding: 22px;
            border-radius: 16px;
            border: 1px solid #e0e5f0;
            box-shadow: 0 5px 20px rgba(23, 37, 84, .04);
        }

        .staff-stat span {
            color: #64748b;
            font-size: 14px;
        }

        .staff-stat strong {
            display: block;
            font-size: 30px;
            margin-top: 10px;
        }

        .staff-panel {
            background: #fff;
            border: 1px solid #e0e5f0;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 5px 20px rgba(23, 37, 84, .04);
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .panel-heading h2 {
            margin: 0;
            font-size: 21px;
        }

        .panel-heading a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 700;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .staff-table th,
        .staff-table td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #e0e5f0;
        }

        .staff-table th {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .staff-table td {
            font-size: 14px;
        }

        .staff-table tbody tr:hover {
            background: #f8faff;
        }

        .status,
        .priority {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .in-progress {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .resolved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .unknown,
        .priority-unknown {
            background: #e2e8f0;
            color: #334155;
        }

        .priority-high {
            background: #fee2e2;
            color: #b91c1c;
        }

        .priority-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .priority-low {
            background: #dcfce7;
            color: #166534;
        }

        .view-link {
            display: inline-block;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 700;
        }

        .staff-empty {
            padding: 35px 15px;
            text-align: center;
            color: #64748b;
        }

        .staff-empty h3 {
            color: #172554;
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 16px 18px;
            }

            .topbar nav {
                gap: 12px;
            }

            .staff-wrap {
                margin: 24px auto;
                padding: 0 14px;
            }

            .staff-head h1 {
                font-size: 25px;
            }

            .staff-panel {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <a class="brand"
       href="<?= e(BASE_URL) ?>/staff/dashboard.php">
        CampusCare Staff
    </a>

    <nav>
       
        <a href="<?= e(BASE_URL) ?>/staff/complaints.php">
            My Complaints
        </a>

        <form class="logout-form"
              method="POST"
              action="<?= e(BASE_URL) ?>/logout.php">
            <?= csrf_field() ?>
            <button class="logout-button" type="submit">
                Logout
            </button>
        </form>
    </nav>
</header>

<main class="staff-wrap">
    <section class="staff-head">
        <div>
            <h1>Staff Dashboard</h1>
            <p>
                Welcome, <?= e($staff['name']) ?>.
                Here is an overview of your assigned complaints.
            </p>
        </div>
    </section>

    <section class="staff-stats">
        <div class="staff-stat">
            <span>Total Assigned</span>
            <strong><?= (int) ($stats['total'] ?? 0) ?></strong>
        </div>

        <div class="staff-stat">
            <span>Pending</span>
            <strong><?= (int) ($stats['pending'] ?? 0) ?></strong>
        </div>

        <div class="staff-stat">
            <span>In Progress</span>
            <strong><?= (int) ($stats['in_progress'] ?? 0) ?></strong>
        </div>

        <div class="staff-stat">
            <span>Resolved</span>
            <strong><?= (int) ($stats['resolved'] ?? 0) ?></strong>
        </div>

        <div class="staff-stat">
            <span>Rejected</span>
            <strong><?= (int) ($stats['rejected'] ?? 0) ?></strong>
        </div>
    </section>

    <section class="staff-panel">
        <div class="panel-heading">
            <h2>My Assigned Complaints</h2>

            <a href="<?= e(BASE_URL) ?>/staff/complaints.php">
                View All My Complaints
            </a>
        </div>

        <?php if (!$complaints): ?>
            <div class="staff-empty">
                <h3>No assigned complaints yet</h3>
                <p>
                    Complaints assigned to you will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="staff-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Complaint</th>
                        <th>Student</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($complaints as $complaint): ?>
                        <tr>
                            <td>
                                #<?= (int) $complaint['id'] ?>
                            </td>

                            <td>
                                <?= e($complaint['title']) ?>
                            </td>

                            <td>
                                <?= e($complaint['student_name']) ?>
                            </td>

                            <td>
                                <?= e($complaint['category']) ?>
                            </td>

                            <td>
                                <span class="priority <?= e(staff_priority_class((string) $complaint['priority'])) ?>">
                                    <?= e($complaint['priority']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status <?= e(staff_status_class((string) $complaint['status'])) ?>">
                                    <?= e($complaint['status']) ?>
                                </span>
                            </td>

                            <td>
                                <a class="view-link"
                                   href="<?= e(BASE_URL) ?>/staff/complaint.php?id=<?= (int) $complaint['id'] ?>">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
