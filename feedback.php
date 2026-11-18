
<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_role(['student']);
$pdo = db();

$complaintId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$complaintId || $complaintId < 1) {
    http_response_code(400);
    exit('Invalid complaint ID.');
}

// Verify that this complaint belongs to the logged-in student.
$stmt = $pdo->prepare("
    SELECT id, title, status
    FROM complaints
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$complaintId, (int) $user['id']]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    http_response_code(404);
    exit('Complaint not found.');
}

if ($complaint['status'] !== 'Resolved') {
    http_response_code(403);
    exit('Feedback can only be submitted for resolved complaints.');
}

// Load existing feedback, if any.
$stmt = $pdo->prepare("
    SELECT id, rating, comment
    FROM feedback
    WHERE complaint_id = ? AND user_id = ?
");
$stmt->execute([$complaintId, (int) $user['id']]);
$existingFeedback = $stmt->fetch(PDO::FETCH_ASSOC);

$errors = [];
$rating = (int) ($existingFeedback['rating'] ?? 0);
$comment = (string) ($existingFeedback['comment'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $postedRating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $rating = $postedRating === false || $postedRating === null
        ? 0
        : $postedRating;

    $comment = trim((string) ($_POST['comment'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a rating between 1 and 5.';
    }

    if (strlen($comment) > 2000) {
        $errors[] = 'Your comment must be no more than 2000 characters.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO feedback
                    (complaint_id, user_id, rating, comment)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    comment = VALUES(comment)
            ");

            $stmt->execute([
                $complaintId,
                (int) $user['id'],
                $rating,
                $comment !== '' ? $comment : null
            ]);

            log_activity(
                (int) $user['id'],
                'FEEDBACK_SUBMITTED',
                'Feedback submitted for complaint #' . $complaintId
            );

            $pdo->commit();

            set_flash(
                'success',
                'Thank you! Your feedback has been saved.'
            );

            redirect('/complaint_details.php?id=' . $complaintId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Feedback save error: ' . $e->getMessage());
            $errors[] = 'Unable to save feedback right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Feedback | CampusCare</title>

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
    <section class="form-card">
        <h1><?= $existingFeedback ? 'Update Your Feedback' : 'Share Your Feedback' ?></h1>

        <p class="muted">
            Complaint #<?= (int) $complaint['id'] ?>:
            <strong><?= e($complaint['title']) ?></strong>
        </p>

        <p class="muted">
            Your feedback helps improve campus services.
        </p>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form
            method="POST"
            action="<?= e(BASE_URL) ?>/feedback.php?id=<?= (int) $complaintId ?>"
        >
            <?= csrf_field() ?>

            <div class="form-group">
            <label for="rating">Your rating</label>

            <select id="rating" name="rating" required>
                <option value="">Choose a rating</option>

                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option
                        value="<?= $i ?>"
                        <?= $rating === $i ? 'selected' : '' ?>
                    >
                        <?= $i ?> star<?= $i === 1 ? '' : 's' ?>
                        <?= match ($i) {
                            5 => ' — Excellent',
                            4 => ' — Very good',
                            3 => ' — Good',
                            2 => ' — Needs improvement',
                            1 => ' — Poor',
                        } ?>
                    </option>
                <?php endfor; ?>
            </select>
            </div>

            <div class="form-group">
            <label for="comment">Comments (optional)</label>

            <textarea
                id="comment"
                name="comment"
                maxlength="2000"
                placeholder="Tell us about your experience..."
            ><?= e($comment) ?></textarea>
            </div>

            <div class="actions form-actions">
                <button class="btn btn-primary" type="submit">
                    <?= $existingFeedback ? 'Update Feedback' : 'Submit Feedback' ?>
                </button>
                <a class="btn btn-secondary"
                   href="<?= e(BASE_URL) ?>/complaint_details.php?id=<?= (int) $complaintId ?>">
                    Cancel
                </a>
            </div>
        </form>
    </section>
</main>

</body>
</html>
