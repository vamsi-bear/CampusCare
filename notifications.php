
<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$userId = (int) $user['id'];
$pdo = db();

// Mark a notification as read.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $notificationId = filter_input(
        INPUT_POST,
        'notification_id',
        FILTER_VALIDATE_INT
    );

    if (!$notificationId || $notificationId < 1) {
        set_flash('error', 'Invalid notification.');
        redirect('/notifications.php');
    }

    $stmt = $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$notificationId, $userId]);

    if ($stmt->rowCount() > 0) {
        set_flash('success', 'Notification marked as read.');
    }

    redirect('/notifications.php');
}

// Fetch notifications belonging to the logged-in user only.
$stmt = $pdo->prepare(
    'SELECT id, complaint_id, title, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC'
);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications.
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0'
);
$stmt->execute([$userId]);
$unreadCount = (int) $stmt->fetchColumn();

// Get the shared flash message, if available.
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | CampusCare</title>

    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">

</head>
<body>

<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/dashboard.php">
        <span class="brand-icon"></span>
        CampusCare Student
    </a>

    <?php if (($user['role'] ?? '') === 'student'): ?>
        <?php require __DIR__ . '/includes/student_nav.php'; ?>
    <?php else: ?>
        <nav class="nav-links">
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="<?= e(BASE_URL) ?>/admin/dashboard.php">
                    Admin Dashboard
                </a>
            <?php elseif (($user['role'] ?? '') === 'staff'): ?>
                <a href="<?= e(BASE_URL) ?>/staff/dashboard.php">
                    Staff Dashboard
                </a>
            <?php endif; ?>

            <form method="POST" action="<?= e(BASE_URL) ?>/logout.php">
                <?= csrf_field() ?>
                <button class="btn" type="submit">Logout</button>
            </form>
        </nav>
    <?php endif; ?>
</header>

<main class="page-container">
    <section class="dashboard-header">
      <div>
        <h1>Notifications</h1>
        <p class="muted">Stay updated about changes to your complaints.</p>
      </div>
      <span class="status <?= $unreadCount > 0 ? 'in-progress' : 'resolved' ?>">
          <?= $unreadCount ?> unread
      </span>
    </section>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="<?= ($flash['type'] ?? '') === 'error' ? 'error' : 'success' ?>" role="status">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$notifications): ?>
        <section class="panel empty-state">
            <h2>You're all caught up!</h2>
            <p>You don't have any notifications yet.</p>
        </section>
    <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
            <article class="panel <?= (int) $notification['is_read'] === 0 ? 'notification-unread' : '' ?>">
                <h3><?= e($notification['title']) ?></h3>

                <p><?= nl2br(e($notification['message'])) ?></p>

                <div class="muted">
                    <?= e($notification['created_at']) ?>
                    ·
                    <?= (int) $notification['is_read'] === 0
                        ? 'Unread'
                        : 'Read' ?>
                </div>

                <div class="actions">
                    <?php if (!empty($notification['complaint_id'])): ?>
                        <a
                            class="btn btn-primary"
                            href="<?= e(BASE_URL) ?>/complaint_details.php?id=<?= (int) $notification['complaint_id'] ?>"
                        >
                            View Complaint
                        </a>
                    <?php endif; ?>

                    <?php if ((int) $notification['is_read'] === 0): ?>
                        <form
                            method="POST"
                            action="<?= e(BASE_URL) ?>/notifications.php"
                        >
                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="notification_id"
                                value="<?= (int) $notification['id'] ?>"
                            >

                            <button class="btn btn-secondary" type="submit">
                                Mark as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

</body>
</html>
