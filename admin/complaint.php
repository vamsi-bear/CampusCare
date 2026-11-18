
<?php
require_once __DIR__ . '/../includes/functions.php';

$admin = require_role(['admin']);

$complaintId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$complaintId || $complaintId < 1) {
    http_response_code(400);
    exit('Invalid complaint ID.');
}

$statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

$errors = [];
$pdo = db();

if (is_post()) {
    verify_csrf();

    $newStatus = $_POST['status'] ?? '';
    $assignedToInput = $_POST['assigned_to'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if (!in_array($newStatus, $statuses, true)) {
        $errors[] = 'Select a valid status.';
    }

    if (strlen($note) > 2000) {
        $errors[] = 'The update note must not exceed 2,000 characters.';
    }

    $assignedTo = null;

    if ($assignedToInput !== '') {
        $assignedTo = filter_var(
            $assignedToInput,
            FILTER_VALIDATE_INT
        );

        if (!$assignedTo || $assignedTo < 1) {
            $errors[] = 'Select a valid staff member.';
        } else {
            $staffStmt = $pdo->prepare(
                "SELECT id FROM users
                 WHERE id = ? AND role = 'staff' AND is_active = 1"
            );
            $staffStmt->execute([$assignedTo]);

            if (!$staffStmt->fetch()) {
                $errors[] = 'The selected staff account is unavailable.';
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, user_id, status, assigned_to
FROM complaints
WHERE id = ?
FOR UPDATE'
            );
            $stmt->execute([$complaintId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                $pdo->rollBack();
                http_response_code(404);
                exit('Complaint not found.');
            }

            $stmt = $pdo->prepare(
                'UPDATE complaints
                 SET status = ?, assigned_to = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $newStatus,
                $assignedTo,
                $complaintId
            ]);

       
$historyChanged = (
    $existing['status'] !== $newStatus ||
    $note !== ''
);

if ($historyChanged) {
    $historyNote = $note !== ''
        ? $note
        : 'Status changed to ' . $newStatus . '.';

    $stmt = $pdo->prepare(
        'INSERT INTO complaint_updates
            (complaint_id, user_id, status, note)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([
        $complaintId,
        $admin['id'],
        $newStatus,
        $historyNote
    ]);

    // Notify the student about the status change or new update.
    $statusChanged = $existing['status'] !== $newStatus;

    if ($statusChanged) {
        $notificationTitle = 'Complaint Status Updated';
        $notificationMessage =
            'Your complaint #' . $complaintId .
            ' (' . $newStatus . ').';

        if ($note !== '') {
            $notificationMessage .= ' Update: ' . $note;
        }
    } else {
        $notificationTitle = 'New Complaint Update';
        $notificationMessage =
            'There is a new update for your complaint #' .
            $complaintId . ': ' . $note;
    }

    create_notification(
        (int) $complaintId > 0
            ? (int) $pdo->query(
                'SELECT user_id FROM complaints WHERE id = ' .
                (int) $complaintId
            )->fetchColumn()
            : 0,
        (int) $complaintId,
        $notificationTitle,
        $notificationMessage
    );
}


            log_activity(
                (int) $admin['id'],
                'COMPLAINT_UPDATED',
                'Complaint ID: ' . $complaintId
                    . '; Status: ' . $newStatus
                    . '; Assigned staff: '
                    . ($assignedTo ?? 'Unassigned')
            );

            $pdo->commit();

            set_flash('success', 'Complaint updated successfully.');

            redirect('/admin/complaint.php?id=' . $complaintId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = 'Unable to update the complaint. Please try again.';
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT c.*, u.name AS student_name, u.email AS student_email,
            s.name AS staff_name
     FROM complaints c
     JOIN users u ON u.id = c.user_id
     LEFT JOIN users s ON s.id = c.assigned_to
     WHERE c.id = ?
     LIMIT 1'
);
$stmt->execute([$complaintId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    http_response_code(404);
    exit('Complaint not found.');
}

$staffMembers = $pdo->query(
    "SELECT id, name, email
     FROM users
     WHERE role = 'staff' AND is_active = 1
     ORDER BY name"
)->fetchAll();

$stmt = $pdo->prepare(
    'SELECT cu.status, cu.note, cu.created_at, u.name AS updated_by
     FROM complaint_updates cu
     LEFT JOIN users u ON u.id = cu.user_id
     WHERE cu.complaint_id = ?
     ORDER BY cu.created_at DESC, cu.id DESC'
);
$stmt->execute([$complaintId]);
$updates = $stmt->fetchAll();

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaint | CampusCare Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php">
        <span class="brand-icon">C</span>
        CampusCare Admin
    </a>

    <nav class="nav-links">
        <a href="dashboard.php">Admin Dashboard</a>
        <form method="POST" action="../logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Logout</button>
        </form>
    </nav>
</header>

<main class="page-container">
    <section class="dashboard-header">
        <a href="dashboard.php">← Back to Admin Dashboard</a>
        <h1>Complaint #<?= (int) $complaint['id'] ?></h1>
        <p class="muted">Review the complaint and update its progress.</p>
    </section>

    <?php if ($flash): ?>
        <div class="<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= e($flash['message']) ?>
        </div>
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

    <section class="panel">
        <h2><?= e($complaint['title']) ?></h2>
        <p><strong>Student:</strong> <?= e($complaint['student_name']) ?></p>
        <p><strong>Email:</strong> <?= e($complaint['student_email']) ?></p>
        <p><strong>Category:</strong> <?= e($complaint['category']) ?></p>
        <p><strong>Priority:</strong> <?= e($complaint['priority']) ?></p>
        <p><strong>Current status:</strong> <?= e($complaint['status']) ?></p>
        <p>
            <strong>Assigned staff:</strong>
            <?= e($complaint['staff_name'] ?? 'Unassigned') ?>
        </p>
        <h3>Description</h3>
        <p><?= nl2br(e($complaint['description'])) ?></p>
    </section>

    <section class="form-card">
        <h2>Update Complaint</h2>

        <form method="POST" action="complaint.php?id=<?= (int) $complaintId ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="status">Complaint status</label>
                <select id="status" name="status" required>
                    <?php foreach ($statuses as $status): ?>
                        <option
                            value="<?= e($status) ?>"
                            <?= $complaint['status'] === $status ? 'selected' : '' ?>
                        >
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="assigned_to">Assign to staff member</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($staffMembers as $staff): ?>
                        <option
                            value="<?= (int) $staff['id'] ?>"
                            <?= (int) $complaint['assigned_to'] === (int) $staff['id'] ? 'selected' : '' ?>
                        >
                            <?= e($staff['name']) ?> (<?= e($staff['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="note">Update note (optional)</label>
                <textarea
                    id="note"
                    name="note"
                    rows="4"
                    maxlength="2000"
                    placeholder="Describe the action taken or provide an update."
                ></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                Save Changes
            </button>
        </form>
    </section>

    <section class="panel">
        <h2>Complaint History</h2>

        <?php if (!$updates): ?>
            <p class="muted">No updates are available.</p>
        <?php else: ?>
            <?php foreach ($updates as $update): ?>
                <article class="complaint-update">
                    <strong><?= e($update['status']) ?></strong>
                    <p><?= nl2br(e($update['note'])) ?></p>
                    <small class="muted">
                        <?= e($update['created_at']) ?>
                        <?php if ($update['updated_by']): ?>
                            — <?= e($update['updated_by']) ?>
                        <?php endif; ?>
                    </small>
               </article>
                <hr>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
