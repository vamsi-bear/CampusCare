
<?php
require_once __DIR__ . '/../includes/functions.php';

$admin = require_role(['admin']);

$stats = db()->query(
    'SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = "Pending"), 0) AS pending,
        COALESCE(SUM(status = "In Progress"), 0) AS in_progress,
        COALESCE(SUM(status = "Resolved"), 0) AS resolved,
        COALESCE(SUM(status = "Rejected"), 0) AS rejected
     FROM complaints'
)->fetch();

$stmt = db()->query(
    'SELECT c.id, c.title, c.category, c.priority, c.status,
            c.created_at, u.name AS student_name, u.email
     FROM complaints c
     JOIN users u ON u.id = c.user_id
     ORDER BY c.created_at DESC
     LIMIT 10'
);

$complaints = $stmt->fetchAll();

function admin_status_class(string $status): string
{
    return match ($status) {
        'Pending' => 'status-pending',
        'In Progress' => 'status-progress',
        'Resolved' => 'status-resolved',
        'Rejected' => 'status-rejected',
        default => ''
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CampusCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php">
        <span class="brand-icon"></span>
        CampusCare Admin
    </a>

    <nav class="nav-links">
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
        </form>
    </nav>
</header>

<main class="page-container">
    <section class="dashboard-header">
        <h1>Administrator Dashboard</h1>
        <p class="muted">
            Welcome, <?= e($admin['name']) ?>. Monitor college complaints here.
        </p>
    </section>

    <section class="stats-grid">
        <article class="stat-card">
            <p>Total Complaints</p>
            <h2><?= (int) $stats['total'] ?></h2>
        </article>

        <article class="stat-card">
            <p>Pending</p>
            <h2><?= (int) $stats['pending'] ?></h2>
        </article>

        <article class="stat-card">
            <p>In Progress</p>
            <h2><?= (int) $stats['in_progress'] ?></h2>
        </article>

        <article class="stat-card">
            <p>Resolved</p>
            <h2><?= (int) $stats['resolved'] ?></h2>
        </article>

        <article class="stat-card">
            <p>Rejected</p>
            <h2><?= (int) $stats['rejected'] ?></h2>
        </article>
    </section>

    <section class="panel">
        <h2>Recent Complaints</h2>

        <?php if (!$complaints): ?>
            <p class="muted">No complaints have been submitted yet.</p>
        <?php else: ?>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Complaint</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <tr>
                                <td>#<?= (int) $complaint['id'] ?></td>
                                <td>
                                    <?= e($complaint['student_name']) ?>
                                    <br>
                                    <small><?= e($complaint['email']) ?></small>
                                </td>
                                <td><?= e($complaint['title']) ?></td>
                                <td><?= e($complaint['category']) ?></td>
                                <td><?= e($complaint['priority']) ?></td>
                                <td>
                                    <span class="status <?= e(admin_status_class($complaint['status'])) ?>">
                                        <?= e($complaint['status']) ?>
                                    </span>
                                </td>
                                <td><?= e($complaint['created_at']) ?></td>
                                <td>
                                    <a href="complaint.php?id=<?= (int) $complaint['id'] ?>">
                                        Manage
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
