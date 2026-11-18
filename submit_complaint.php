
<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

if ($user['role'] !== 'student') {
    redirect('/dashboard.php');
}

$pdo = db();
$userId = (int) $user['id'];

$errors = [];
$title = '';
$category = '';
$description = '';
$priority = 'Medium';

$categories = [
    'Infrastructure',
    'Academics',
    'Hostel',
    'Library',
    'Canteen',
    'Transportation',
    'IT and Wi-Fi',
    'Cleanliness',
    'Other'
];

// Count unread notifications for the summary panel
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ? AND is_read = 0'
);
$stmt->execute([$userId]);
$unreadNotifications = (int) $stmt->fetchColumn();

if (is_post()) {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';

    if ($title === '' || strlen($title) > 200) {
        $errors[] = 'Enter a title of up to 200 characters.';
    }

    if (!in_array($category, $categories, true)) {
        $errors[] = 'Please select a valid complaint category.';
    }

    if ($description === '' || strlen($description) > 10000) {
        $errors[] = 'Enter a description of up to 10,000 characters.';
    }

    if (!in_array($priority, ['Low', 'Medium', 'High'], true)) {
        $errors[] = 'Please select a valid priority.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO complaints
                    (user_id, title, category, description, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $userId,
                $title,
                $category,
                $description,
                $priority,
                'Pending'
            ]);

            $complaintId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO complaint_updates
                    (complaint_id, user_id, status, note)
                 VALUES (?, ?, ?, ?)'
            );

            $stmt->execute([
                $complaintId,
                $userId,
                'Pending',
                'Complaint submitted.'
            ]);

            log_activity(
                $userId,
                'COMPLAINT_CREATED',
                'Complaint ID: ' . $complaintId
            );

            $pdo->commit();

            set_flash(
                'success',
                'Your complaint has been submitted successfully.'
            );

            redirect('/my_complaints.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = 'Unable to submit your complaint. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Submit Complaint | CampusCare</title>

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

    <section class="form-card">
        <h1>Submit a Complaint</h1>

        <p class="muted">
            Describe your concern clearly so the college team can help.
        </p>

        <?php if ($unreadNotifications > 0): ?>
            <section class="panel">
                <h2>New Notifications</h2>

                <p>
                    You have <?= $unreadNotifications ?>
                    unread notification<?= $unreadNotifications === 1 ? '' : 's' ?>.
                </p>

                <a
                    class="btn btn-primary"
                    href="<?= e(BASE_URL) ?>/notifications.php"
                >
                    View Notifications
                </a>
            </section>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(BASE_URL) ?>/submit_complaint.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="title">Complaint title</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="200"
                    value="<?= e($title) ?>"
                    placeholder="Briefly describe the issue"
                    required
                >
            </div>

            <div class="form-group">
                <label for="category">Category</label>

                <select id="category" name="category" required>
                    <option value="">Select a category</option>

                    <?php foreach ($categories as $item): ?>
                        <option
                            value="<?= e($item) ?>"
                            <?= $category === $item ? 'selected' : '' ?>
                        >
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="priority">Priority</label>

                <select id="priority" name="priority" required>
                    <?php foreach (['Low', 'Medium', 'High'] as $item): ?>
                        <option
                            value="<?= e($item) ?>"
                            <?= $priority === $item ? 'selected' : '' ?>
                        >
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Complaint details</label>

                <textarea
                    id="description"
                    name="description"
                    rows="6"
                    maxlength="10000"
                    placeholder="Explain what happened, where it happened, and any relevant details."
                    required
                ><?= e($description) ?></textarea>
            </div>

            <div class="actions form-actions">
                <button class="btn btn-primary" type="submit">
                    Submit Complaint
                </button>

                <a href="<?= e(BASE_URL) ?>/dashboard.php">
                    Cancel
                </a>
            </div>
        </form>
    </section>

</main>

</body>
</html>
