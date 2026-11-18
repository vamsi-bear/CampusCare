
<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

if ($user['role'] !== 'student') {
    redirect('/dashboard.php');
}

$pdo = db();
$userId = (int) $user['id'];

$complaintId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$complaintId || $complaintId < 1) {
    http_response_code(400);
    exit('Invalid complaint ID.');
}

// Fetch only the logged-in student's complaint
$stmt = $pdo->prepare(
    'SELECT id, user_id, title, category, description,
            priority, status, created_at, updated_at
     FROM complaints
     WHERE id = ? AND user_id = ?
     LIMIT 1'
);

$stmt->execute([$complaintId, $userId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    http_response_code(404);
    exit('Complaint not found.');
}

// Fetch complaint status history
$stmt = $pdo->prepare(
    'SELECT cu.status, cu.note, cu.created_at, u.name AS updated_by
     FROM complaint_updates cu
     LEFT JOIN users u ON u.id = cu.user_id
     WHERE cu.complaint_id = ?
     ORDER BY cu.created_at DESC, cu.id DESC'
);

$stmt->execute([$complaintId]);
$updates = $stmt->fetchAll();

// Fetch feedback submitted by this student
$stmt = $pdo->prepare(
    'SELECT rating, comment, created_at
     FROM feedback
     WHERE complaint_id = ? AND user_id = ?
     LIMIT 1'
);

$stmt->execute([$complaintId, $userId]);
$feedback = $stmt->fetch();

// Count unread notifications for the summary panel
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0'
);

$stmt->execute([$userId]);
$unreadNotifications = (int) $stmt->fetchColumn();

// CSS class for complaint status
function details_status_class(string $status): string
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

    <title>Complaint Details | CampusCare</title>

    <link rel="stylesheet" href="assets/css/style.css">

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
            <h1>Complaint #<?= (int) $complaint['id'] ?></h1>
            <p class="muted">Submitted on <?= e($complaint['created_at']) ?></p>
        </div>
    </section>

    <?php if ($unreadNotifications > 0): ?>
        <section class="panel">
            <h2>New Notifications</h2>

            <p class="muted">
                You have <?= $unreadNotifications ?>
                unread notification<?= $unreadNotifications === 1 ? '' : 's' ?>.
            </p>

            <a class="btn btn-primary"
               href="<?= e(BASE_URL) ?>/notifications.php">
                View Notifications
            </a>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2><?= e($complaint['title']) ?></h2>

        <p>
            <strong>Category:</strong>
            <?= e($complaint['category']) ?>
        </p>

        <p>
            <strong>Priority:</strong>
            <?= e($complaint['priority']) ?>
        </p>

        <p>
            <strong>Current status:</strong>

            <span class="status <?= e(details_status_class($complaint['status'])) ?>">
                <?= e($complaint['status']) ?>
            </span>
        </p>

        <p>
            <strong>Last updated:</strong>
            <?= e($complaint['updated_at']) ?>
        </p>

        <div>
            <h3>Description</h3>

            <p class="complaint-description">
                <?= nl2br(e($complaint['description'])) ?>
            </p>
        </div>
    </section>

    <section class="panel">
        <h2>Status History</h2>

        <?php if (empty($updates)): ?>
            <p class="muted">
                No status updates are available yet.
            </p>
        <?php else: ?>
            <?php foreach ($updates as $update): ?>
                <article class="complaint-update">
                    <p>
                        <span class="status <?= e(details_status_class($update['status'])) ?>">
                            <?= e($update['status']) ?>
                        </span>
                    </p>

                    <?php if (!empty($update['note'])): ?>
                        <p><?= nl2br(e($update['note'])) ?></p>
                    <?php endif; ?>

                    <small class="muted">
                        <?= e($update['created_at']) ?>

                        <?php if (!empty($update['updated_by'])): ?>
                            &mdash; <?= e($update['updated_by']) ?>
                        <?php endif; ?>
                    </small>
                </article>

                            <hr>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Student Feedback</h2>

        <?php if ($feedback): ?>
            <p>
                <strong>Rating:</strong>

                <span class="feedback-stars">
                    <?= str_repeat('★', (int) $feedback['rating']) ?><?= str_repeat('☆', 5 - (int) $feedback['rating']) ?>
                </span>

                (<?= (int) $feedback['rating'] ?>/5)
            </p>

            <?php if (!empty($feedback['comment'])): ?>
                <p>
                    <strong>Comment:</strong><br>
                    <?= nl2br(e($feedback['comment'])) ?>
                </p>
            <?php else: ?>
                <p class="muted">
                    No written comment provided.
                </p>
            <?php endif; ?>

            <p class="muted">
                Submitted on <?= e($feedback['created_at']) ?>
            </p>
        <?php else: ?>
            <p class="muted">
                No feedback submitted yet.
            </p>
        <?php endif; ?>

        <?php if ($complaint['status'] === 'Resolved'): ?>
            <a class="btn btn-primary"
               href="<?= e(BASE_URL) ?>/feedback.php?id=<?= (int) $complaintId ?>">
                <?= $feedback ? 'Update Feedback' : 'Submit Feedback' ?>
            </a>
        <?php endif; ?>
    </section>

</main>

</body>
</html>
