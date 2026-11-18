
<?php
require_once __DIR__ . '/../includes/functions.php';

$admin = require_role(['admin']);
$pdo = db();
$error = '';

if (is_post()) {
    verify_csrf();

    $complaintId = filter_input(
        INPUT_POST,
        'complaint_id',
        FILTER_VALIDATE_INT
    );
    $staffId = trim($_POST['staff_id'] ?? '');

    if (!$complaintId || $complaintId < 1) {
        $error = 'Please select a valid complaint.';
    } elseif ($staffId !== '' &&
        (!ctype_digit($staffId) || (int) $staffId < 1)) {
        $error = 'Invalid staff selection.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, assigned_to FROM complaints WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$complaintId]);
            $complaint = $stmt->fetch();

            if (!$complaint) {
                throw new RuntimeException('Complaint not found.');
            }

            $assignedTo = null;

            if ($staffId !== '') {
                $stmt = $pdo->prepare(
                    "SELECT id FROM users
                     WHERE id = ? AND role = 'staff' AND is_active = 1"
                );
                $stmt->execute([(int) $staffId]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException(
                        'Selected staff member is inactive or invalid.'
                    );
                }

                $assignedTo = (int) $staffId;
            }

            $stmt = $pdo->prepare(
                'UPDATE complaints
                 SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $stmt->execute([$assignedTo, $complaintId]);

            $details = $assignedTo === null
                ? "Complaint #{$complaintId} unassigned."
                : "Complaint #{$complaintId} assigned to staff ID {$assignedTo}.";

            log_activity((int) $admin['id'], 'COMPLAINT_ASSIGNMENT', $details);

            $pdo->commit();

            set_flash('success', 'Complaint assignment saved successfully.');
            redirect('/admin/assign_complaints.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } else {
                error_log('Complaint assignment failed: ' . $exception->getMessage());
                $error = 'Unable to save the assignment. Please try again.';
            }
        }
    }
}

$staffMembers = $pdo->query(
    "SELECT id, name, email
     FROM users
     WHERE role = 'staff' AND is_active = 1
     ORDER BY name"
)->fetchAll();

$search = trim($_GET['search'] ?? '');
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$sql = "
    SELECT
        c.id, c.title, c.category, c.priority, c.status,
        c.created_at, c.assigned_to,
        student.name AS student_name,
        staff.name AS staff_name
    FROM complaints c
    JOIN users student ON student.id = c.user_id
    LEFT JOIN users staff ON staff.id = c.assigned_to
";
$params = [];

if ($search !== '') {
    $sql .= " WHERE c.title LIKE ?
               OR student.name LIKE ?
               OR CAST(c.id AS CHAR) LIKE ?";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
}

$sql .= " ORDER BY
    CASE WHEN c.assigned_to IS NULL THEN 0 ELSE 1 END,
    c.created_at DESC
    LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assign Complaints | CampusCare</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .assign-wrap{max-width:1250px;margin:35px auto;padding:0 20px}
        .assign-panel{background:#fff;border:1px solid #e0e5f0;
            border-radius:16px;padding:22px;margin-bottom:22px}
        .assign-muted{color:#64748b}
        .assign-search{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}
        .assign-search input{padding:11px;border:1px solid #d5dbea;
            border-radius:9px;min-width:240px;font:inherit}
        .assign-search button,.assign-save{padding:10px 14px;
            background:#4f46e5;color:white;border:0;border-radius:8px;
            cursor:pointer;font-weight:600}
        .assign-table-wrap{overflow-x:auto}
        .assign-table{width:100%;border-collapse:collapse;min-width:900px}
        .assign-table th,.assign-table td{padding:13px 10px;
            border-bottom:1px solid #e0e5f0;text-align:left}
        .assign-table th{font-size:13px;color:#64748b}
        .assign-table select{padding:9px;border:1px solid #d5dbea;
            border-radius:8px;max-width:220px}
        .assign-message{padding:13px;border-radius:9px;margin-bottom:16px}
        .assign-error{background:#fef2f2;color:#991b1b}
        .assign-success{background:#ecfdf5;color:#065f46}
        .btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 16px;
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease;
}

.btn-secondary:hover {
    background: #eff3f6;
}
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= BASE_URL ?>/admin/dashboard.php">CampusCare Admin</a>
    <nav>
        <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
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
    </nav>
</header>

<main class="assign-wrap">
    <h1>Complaint Assignments</h1>
    <p class="assign-muted">
        Assign complaints to active staff members or remove an assignment.
    </p>

    <?php if ($error !== ''): ?>
        <div class="assign-message assign-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="assign-message assign-success"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="assign-panel">
        <h2>Complaints</h2>

        <form method="get" class="assign-search">
            <input type="search" name="search" maxlength="100"
                   placeholder="Search complaint, student or ID"
                   value="<?= e($search) ?>">
            <button type="submit">Search</button>
<a href="<?= BASE_URL ?>/admin/assign_complaints.php" class="btn btn-secondary">
    Reset
</a>
        </form>

        <div class="assign-table-wrap">
            <table class="assign-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Complaint</th>
                    <th>Student</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned Staff</th>
                    <th>Save Assignment</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$complaints): ?>
                    <tr><td colspan="8">No complaints found.</td></tr>
                <?php else: ?>
                    <?php foreach ($complaints as $item): ?>
                        <tr>
                            <td>#<?= (int) $item['id'] ?></td>
                            <td><?= e($item['title']) ?></td>
                            <td><?= e($item['student_name']) ?></td>
                            <td><?= e($item['category']) ?></td>
                            <td><?= e($item['priority']) ?></td>
                            <td><?= e($item['status']) ?></td>
                            <td><?= e($item['staff_name'] ?? 'Unassigned') ?></td>
                            <td>
                                <form method="post"
                                      action="<?= BASE_URL ?>/admin/assign_complaints.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="complaint_id"
                                           value="<?= (int) $item['id'] ?>">
                                    <select name="staff_id">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($staffMembers as $member): ?>
                                            <option value="<?= (int) $member['id'] ?>"
                                                <?= (int) ($item['assigned_to'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>>
                                                <?= e($member['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="assign-save" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="assign-muted">Up to 200 complaints are displayed.</p>
    </section>
</main>
</body>
</html>
