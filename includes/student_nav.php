
<?php
// Shared navigation for student pages.
// The parent page must load functions.php and define $user.

if (!isset($user) || !is_array($user) || !isset($user['id'])) {
    throw new RuntimeException(
        'Student navigation requires a logged-in user.'
    );
}

$navPdo = db();

$navStmt = $navPdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0'
);

$navStmt->execute([(int) $user['id']]);
$navUnreadCount = (int) $navStmt->fetchColumn();
?>

<nav class="nav-links">
    <a href="<?= e(BASE_URL) ?>/dashboard.php">
        Dashboard
    </a>

    <a href="<?= e(BASE_URL) ?>/submit_complaint.php">
        Submit Complaint
    </a>

    <a href="<?= e(BASE_URL) ?>/my_complaints.php">
        My Complaints
    </a>

    <a href="<?= e(BASE_URL) ?>/notifications.php">
        Notifications
        <?php if ($navUnreadCount > 0): ?>
            <span class="notification-badge">
                <?= $navUnreadCount ?>
            </span>
        <?php endif; ?>
    </a>

    <form method="POST" action="<?= e(BASE_URL) ?>/logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">
            Logout
        </button>
    </form>
</nav>
