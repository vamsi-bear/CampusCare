
<?php
require_once __DIR__ . '/../includes/functions.php';

$staff = require_role(['staff']);
$pdo = db();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid complaint ID.');
}

$allowedStatuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];
$error = '';

/**
 * Only display complaints assigned to the logged-in staff member.
 */
function staff_complaint_status_class(string $status): string
{
    return match ($status) {
        'Pending' => 'pending',
        'In Progress' => 'in-progress',
        'Resolved' => 'resolved',
        'Rejected' => 'rejected',
        default => '',
    };
}

if (is_post()) {
    verify_csrf();

    $newStatus = trim((string) ($_POST['status'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));

    if (!in_array($newStatus, $allowedStatuses, true)) {
        $error = 'Please select a valid status.';
    } elseif (strlen($note) > 2000) {
        $error = 'The progress note must not exceed 2000 characters.';
    } elseif (
        $newStatus !== 'Resolved' &&
        $newStatus !== 'Rejected' &&
        $note === ''
    ) {
        $error = 'Please enter a progress note.';
    } else {
        try {
            $pdo->beginTransaction();

            // Verify ownership and lock the complaint during the update.
            $stmt = $pdo->prepare(
                'SELECT id, user_id, status, assigned_to
                 FROM complaints
                 WHERE id = ? AND assigned_to = ?
                 FOR UPDATE'
            );
            $stmt->execute([$id, (int) $staff['id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $pdo->rollBack();
                http_response_code(404);
                exit('Complaint not found or not assigned to you.');
            }

            $oldStatus = $existing['status'];
            $statusChanged = $oldStatus !== $newStatus;
            $noteAdded = $note !== '';

            if ($statusChanged || $noteAdded) {
                // Update the complaint only when something changed.
                $stmt = $pdo->prepare(
                    'UPDATE complaints
                     SET status = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ? AND assigned_to = ?'
                );
                $stmt->execute([
                    $newStatus,
                    $id,
                    (int) $staff['id'],
                ]);

                // Store the update in the complaint history.
                $historyNote = $noteAdded
                    ? $note
                    : 'Status updated to ' . $newStatus . '.';

                $stmt = $pdo->prepare(
                    'INSERT INTO complaint_updates
                        (complaint_id, user_id, status, note)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([
                    $id,
                    (int) $staff['id'],
                    $newStatus,
                    $historyNote,
                ]);

                // Notify the student.
                if ($statusChanged) {
                    $notificationTitle = 'Complaint Status Updated';
                    $notificationMessage =
                        'Your complaint #' . $id .
                        ' status changed from ' . $oldStatus .
                        ' to ' . $newStatus . '.';

                    if ($noteAdded) {
                        $notificationMessage .=
                            ' Staff update: ' . $note;
                    }
                } else {
                    $notificationTitle = 'New Complaint Update';
                    $notificationMessage =
                        'Staff added a new update to your complaint #' .
                        $id . ': ' . $note;
                }

                $notificationCreated = create_notification(
                    (int) $existing['user_id'],
                    (int) $id,
                    $notificationTitle,
                    $notificationMessage
                );

                if (!$notificationCreated) {
                    throw new RuntimeException(
                        'Unable to create the student notification.'
                    );
                }

                log_activity(
                    (int) $staff['id'],
                    'STAFF_COMPLAINT_UPDATE',
                    'Complaint #' . $id . ': ' .
                    $oldStatus . ' -> ' . $newStatus
                );
            }

            $pdo->commit();

            if ($statusChanged || $noteAdded) {
                set_flash(
                    'success',
                    'Complaint updated and student notified successfully.'
                );
            } else {
                set_flash(
                    'success',
                    'No changes were made to the complaint.'
                );
            }

            redirect('/staff/complaint.php?id=' . $id);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Staff complaint update failed: ' .
                $exception->getMessage()
            );

            $error = 'Unable to save the update. Please try again.';
        }
    }
}

// Retrieve the complaint only if it belongs to this staff member.
$stmt = $pdo->prepare(
    'SELECT
        c.*,
        student.name AS student_name,
        student.email AS student_email,
        student.student_id
     FROM complaints c
     JOIN users student ON student.id = c.user_id
     WHERE c.id = ? AND c.assigned_to = ?
     LIMIT 1'
);
$stmt->execute([$id, (int) $staff['id']]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    http_response_code(404);
    exit('Complaint not found or not assigned to you.');
}

// Retrieve complaint history.
$stmt = $pdo->prepare(
    'SELECT
        cu.status,
        cu.note,
        cu.created_at,
        u.name AS updated_by
     FROM complaint_updates cu
     LEFT JOIN users u ON u.id = cu.user_id
     WHERE cu.complaint_id = ?
     ORDER BY cu.created_at DESC, cu.id DESC'
);
$stmt->execute([$id]);
$updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complaint #<?= (int) $complaint['id'] ?> | CampusCare</title>
    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/css/style.css">

    <style>
        .detail-wrap {
            max-width: 950px;
            margin: 35px auto;
            padding: 0 20px 35px;
        }

        .detail-panel {
            background: #fff;
            border: 1px solid #e0e5f0;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .04);
        }

        .detail-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .detail-head h1 {
            margin: 8px 0;
            overflow-wrap: anywhere;
        }

        .detail-muted {
            color: #64748b;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin: 22px 0;
        }

        .detail-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 6px;
        }

        .detail-value {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .detail-description,
        .history-item p {
            white-space: pre-wrap;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .history-item {
            padding: 16px 0;
            border-bottom: 1px solid #e0e5f0;
        }

        .history-item:last-child {
            border-bottom: 0;
        }

        .detail-back {
            display: inline-block;
            margin-bottom: 18px;
            color: #000003;
            font-weight: 600;
            text-decoration: none;
        }

        .staff-form-group {
            margin: 16px 0;
        }

        .staff-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .staff-form-group select,
        .staff-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d5dbea;
            border-radius: 9px;
            font: inherit;
            box-sizing: border-box;
        }

        .staff-form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .staff-submit {
            background: #4f46e5;
            color: #fff;
            border: 0;
            border-radius: 9px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .staff-submit:hover {
            background: #4338ca;
        }

        .staff-message {
            padding: 13px 15px;
            border-radius: 9px;
            margin-bottom: 18px;
            overflow-wrap: anywhere;
        }

        .staff-error {
            background: #fef2f2;
            color: #991b1b;
        }

        .staff-success {
            background: #ecfdf5;
            color: #065f46;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status.in-progress {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status.resolved {
            background: #d1fae5;
            color: #065f46;
        }

        .status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .topbar nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar nav form {
            margin: 0;
        }

        .topbar nav button {
            cursor: pointer;
            font: inherit;
        }

        .topbar .brand {
    font-size: 28px;
    font-weight: 700;
}

        .detail-back {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 11px 20px;
    background: #533ce7;
    color: #ffffff;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.25s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.detail-back:hover {
    background: #2d25d6;
    color: #ffffff;
    border-color: #2563eb;
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(37, 99, 235, 0.2);
}

.back-icon {
    font-size: 20px;
    line-height: 1;
}

        @media (max-width: 600px) {
            .detail-wrap {
                margin-top: 20px;
                padding: 0 12px 25px;
            }

            .detail-panel {
                padding: 18px;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/staff/dashboard.php">
        CampusCare Staff
    </a>

    <nav>
        <a href="<?= e(BASE_URL) ?>/staff/dashboard.php">Dashboard</a>
        <a href="<?= e(BASE_URL) ?>/staff/complaints.php">My Complaints</a>

        <form action="<?= e(BASE_URL) ?>/logout.php" method="post">
            <?= csrf_field() ?>
            <button type="submit">Logout</button>
        </form>
    </nav>
</header>

<main class="detail-wrap">
    <a class="detail-back"
   href="<?= e(BASE_URL) ?>/staff/complaints.php">
    <span class="back-icon">&larr;</span>
    <span>Back to My Complaints</span>
</a>

    <?php if ($error !== ''): ?>
        <div class="staff-message staff-error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="staff-message staff-success" role="status">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="detail-panel">
        <div class="detail-head">
            <div>
                <p class="detail-muted">
                    Complaint #<?= (int) $complaint['id'] ?>
                </p>
                <h1><?= e($complaint['title']) ?></h1>
            </div>

            <span class="status <?= e(
                staff_complaint_status_class($complaint['status'])
            ) ?>">
                <?= e($complaint['status']) ?>
            </span>
        </div>

        <div class="detail-grid">
            <div>
                <div class="detail-label">Student</div>
                <div class="detail-value">
                    <?= e($complaint['student_name']) ?>
                </div>
            </div>

            <div>
                <div class="detail-label">Student Email</div>
                <div class="detail-value">
                    <?= e($complaint['student_email']) ?>
                </div>
            </div>

            <div>
                <div class="detail-label">Student ID</div>
                <div class="detail-value">
                    <?= e($complaint['student_id'] ?? 'Not provided') ?>
                </div>
            </div>

            <div>
                <div class="detail-label">Category</div>
                <div class="detail-value">
                    <?= e($complaint['category']) ?>
                </div>
            </div>

            <div>
                <div class="detail-label">Priority</div>
                <div class="detail-value">
                    <?= e($complaint['priority']) ?>
                </div>
            </div>

            <div>
                <div class="detail-label">Submitted</div>
                <div class="detail-value">
                    <?= e($complaint['created_at']) ?>
                </div>
            </div>

            <div>
                <div class="detail-label">Last Updated</div>
                <div class="detail-value">
                    <?= e($complaint['updated_at']) ?>
                </div>
            </div>
        </div>

        <h3>Description</h3>
        <div class="detail-description"><?= e(
            $complaint['description']
        ) ?></div>
    </section>

    <section class="detail-panel">
        <h2>Update Complaint</h2>
        <p class="detail-muted">
            Select the current status and add a progress note.
            The student will receive a notification when an update is saved.
        </p>

        <form method="post"
              action="<?= e(BASE_URL) ?>/staff/complaint.php?id=<?= (int) $complaint['id'] ?>">
            <?= csrf_field() ?>

            <div class="staff-form-group">
                <label for="status">New Status</label>
                <select id="status" name="status" required>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option
                            value="<?= e($status) ?>"
                            <?= $complaint['status'] === $status
                                ? 'selected'
                                : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="staff-form-group">
                <label for="note">Progress Note</label>
                <textarea
                    id="note"
                    name="note"
                    maxlength="2000"
                    placeholder="Describe the action taken, findings, or next steps..."
                ><?= e($_POST['note'] ?? '') ?></textarea>
            </div>

            <button class="staff-submit" type="submit">
                Save Update
            </button>
        </form>
    </section>

    <section class="detail-panel">
        <h2>Complaint History</h2>

        <?php if (!$updates): ?>
            <p class="detail-muted">
                No updates have been recorded yet.
            </p>
        <?php else: ?>
            <?php foreach ($updates as $update): ?>
                <div class="history-item">
                    <strong><?= e($update['status']) ?></strong>

                    <p><?= e($update['note'] ?? '') ?></p>

                    <small class="detail-muted">
                        By <?= e($update['updated_by'] ?? 'Unknown') ?>
                        &middot;
                        <?= e($update['created_at']) ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
