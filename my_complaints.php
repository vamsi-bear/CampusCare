
<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_role(['student']);
$pdo = db();
$userId = (int) $user['id'];

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'Pending',
    'In Progress',
    'Resolved',
    'Rejected'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

// Count unread notifications for the summary panel
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0'
);
$stmt->execute([$userId]);
$unreadNotifications = (int) $stmt->fetchColumn();

// Build complaint search query
$sql = '
    SELECT id, title, category, priority, status, created_at, updated_at
    FROM complaints
    WHERE user_id = ?
';

$params = [$userId];

if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR category LIKE ? OR description LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}

if ($status !== '') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints | CampusCare</title>

    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">

</head>
<body>

<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/dashboard.php">
        <span class="brand-icon"></span>
        CampusCare Student
    </a>

    <?php require __DIR__ . '/includes/student_nav.php'; ?>
</header>

<main class="page-container">
    <section class="dashboard-header">
        <div>
            <h1>My Complaints</h1>
            <p class="muted">Search your submissions and track their latest status.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/submit_complaint.php">+ Submit a Complaint</a>
    </section>

    <?php if ($unreadNotifications > 0): ?>
        <section class="panel">
            <h2>New Notifications</h2>

            <p class="muted">
                You have <?= $unreadNotifications ?>
                unread notification<?= $unreadNotifications === 1 ? '' : 's' ?>.
            </p>

            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/notifications.php">
                View Notifications
            </a>
        </section>
    <?php endif; ?>

    <section class="panel">
        <form method="GET" action="<?= e(BASE_URL) ?>/my_complaints.php">
            <div class="filters">
                <input
                    type="search"
                    name="search"
                    placeholder="Search title, category, or description"
                    value="<?= e($search) ?>"
                >

                <select name="status">
                    <option value="">All statuses</option>

                    <?php foreach ($allowedStatuses as $option): ?>
                        <option
                            value="<?= e($option) ?>"
                            <?= $status === $option ? 'selected' : '' ?>
                        >
                            <?= e($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-primary" type="submit">Search</button>

                <a
                    class="btn btn-secondary"
                    href="<?= e(BASE_URL) ?>/my_complaints.php"
                >
                    Clear
                </a>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Results (<?= count($complaints) ?>)</h2>

        <?php if (empty($complaints)): ?>
            <div class="empty">
                <h3>No complaints found</h3>

                <p>
                    Try changing the search or status filter.
                </p>

                <a
                    class="btn btn-primary"
                    href="<?= e(BASE_URL) ?>/submit_complaint.php"
                >
                    Submit a complaint
                </a>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Complaint</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Details</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <?php
                            $statusClass = match ($complaint['status']) {
                                'Pending' => 'pending',
                                'In Progress' => 'in-progress',
                                'Resolved' => 'resolved',
                                'Rejected' => 'rejected',
                                default => ''
                            };
                            ?>

                            <tr>
                                <td>
                                    #<?= (int) $complaint['id'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($complaint['title']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($complaint['category']) ?>
                                </td>

                                <td>
                                    <?= e($complaint['priority']) ?>
                                </td>

                                <td>
                                    <span class="status <?= e($statusClass) ?>">
                                        <?= e($complaint['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd M Y',
                                        strtotime($complaint['created_at'])
                                    )) ?>
                                </td>

                                <td>
                                    <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/complaint_details.php?id=<?= (int) $complaint['id'] ?>">
                                        View
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
