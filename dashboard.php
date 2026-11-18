
<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

if ($user['role'] === 'admin') {
    redirect('/admin/dashboard.php');
}

if ($user['role'] === 'staff') {
    redirect('/staff/dashboard.php');
}

$pdo = db();
$userId = (int) $user['id'];

// Get complaint statistics
$stmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = "Pending"), 0) AS pending,
        COALESCE(SUM(status = "In Progress"), 0) AS in_progress,
        COALESCE(SUM(status = "Resolved"), 0) AS resolved
     FROM complaints
     WHERE user_id = ?'
);

$stmt->execute([$userId]);
$stats = $stmt->fetch();

// Get the five most recent complaints
$stmt = $pdo->prepare(
    'SELECT id, title, category, priority, status, created_at
     FROM complaints
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 5'
);

$stmt->execute([$userId]);
$complaints = $stmt->fetchAll();

// Count unread notifications for the dashboard summary
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0'
);

$stmt->execute([$userId]);
$unreadNotifications = (int) $stmt->fetchColumn();

// CSS class for complaint status
function status_class(string $status): string
{
    return match ($status) {
        'Pending' => 'pending',
        'In Progress' => 'in-progress',
        'Resolved' => 'resolved',
        'Rejected' => 'rejected',
        default => ''
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | CampusCare</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="topbar">
    <a class="brand" href="dashboard.php">
        <span class="brand-icon"></span>
        CampusCare Student
    </a>

    <?php require __DIR__ . '/includes/student_nav.php'; ?>
</header>

<main class="page-container">

    <section class="dashboard-header">
        <h1>Welcome, <?= e($user['name']) ?>!</h1>

        <p class="muted">
            Track your college complaints and their progress here.
        </p>

        <a class="btn btn-primary" href="submit_complaint.php">
            + Submit a Complaint
        </a>
    </section>

    <?php if ($unreadNotifications > 0): ?>
        <section class="panel">
            <h2>New Notifications</h2>

            <p class="muted">
                You have <?= $unreadNotifications ?>
                unread notification<?= $unreadNotifications === 1 ? '' : 's' ?>.
            </p>

            <a class="btn btn-primary" href="notifications.php">
                View Notifications
            </a>
        </section>
    <?php endif; ?>

    <section class="stats-grid">

        <article class="stat-card">
            <p>Total Complaints</p>
            <h2><?= (int) ($stats['total'] ?? 0) ?></h2>
        </article>

        <article class="stat-card">
            <p>Pending</p>
            <h2><?= (int) ($stats['pending'] ?? 0) ?></h2>
        </article>

        <article class="stat-card">
            <p>In Progress</p>
            <h2><?= (int) ($stats['in_progress'] ?? 0) ?></h2>
        </article>

        <article class="stat-card">
            <p>Resolved</p>
            <h2><?= (int) ($stats['resolved'] ?? 0) ?></h2>
        </article>

    </section>

    <section class="panel">
        <h2>Recent Complaints</h2>

        <?php if (empty($complaints)): ?>

            <p class="muted">
                You haven't submitted any complaints yet.
            </p>

            <a class="btn btn-primary" href="submit_complaint.php">
                Submit your first complaint
            </a>

        <?php else: ?>

            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <tr>
                                <td>
                                    <?= e($complaint['title']) ?>
                                </td>

                                <td>
                                    <?= e($complaint['category']) ?>
                                </td>

                                <td>
                                    <?= e($complaint['priority']) ?>
                                </td>

                                <td>
                                    <span class="status <?= e(status_class($complaint['status'])) ?>">
                                        <?= e($complaint['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($complaint['created_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="actions">
                <a class="btn btn-secondary" href="my_complaints.php">View All Complaints</a>
            </p>

        <?php endif; ?>
    </section>

</main>

</body>
</html>
