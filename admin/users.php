
<?php

require_once __DIR__ . '/../includes/functions.php';

$admin = require_role(['admin']);
$pdo = db();

$roles = ['student', 'staff', 'admin'];
$errors = [];
$flash = get_flash();

if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if (!$userId || $userId < 1) {
        $errors[] = 'Invalid user ID.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, name, email, role, is_active
                 FROM users
                 WHERE id = ?
                 FOR UPDATE'
            );
            $stmt->execute([$userId]);
            $target = $stmt->fetch();

            if (!$target) {
                throw new RuntimeException('User not found.');
            }

            // Prevent changing or deactivating your own admin account.
            if ((int) $target['id'] === (int) $admin['id']) {
                throw new RuntimeException(
                    'You cannot change your own role or account status here.'
                );
            }

            if ($action === 'change_role') {
                $newRole = $_POST['role'] ?? '';

                if (!in_array($newRole, $roles, true)) {
                    throw new RuntimeException('Please select a valid role.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE users SET role = ? WHERE id = ?'
                );
                $stmt->execute([$newRole, $userId]);

                log_activity(
                    (int) $admin['id'],
                    'ADMIN_CHANGED_USER_ROLE',
                    'User #' . $userId . ' role changed to ' . $newRole
                );

                $successMessage = 'User role updated successfully.';

            } elseif ($action === 'toggle_active') {
                $newActive = (int) $target['is_active'] === 1 ? 0 : 1;

                // Do not allow deactivating the last active administrator.
                if ($newActive === 0 && $target['role'] === 'admin') {
                    $stmt = $pdo->query(
                        "SELECT COUNT(*) FROM users
                         WHERE role = 'admin' AND is_active = 1"
                    );

                    if ((int) $stmt->fetchColumn() <= 1) {
                        throw new RuntimeException(
                            'You cannot deactivate the last active administrator.'
                        );
                    }
                }

                $stmt = $pdo->prepare(
                    'UPDATE users SET is_active = ? WHERE id = ?'
                );
                $stmt->execute([$newActive, $userId]);

                log_activity(
                    (int) $admin['id'],
                    'ADMIN_CHANGED_USER_STATUS',
                    'User #' . $userId . ' active status set to ' . $newActive
                );

                $successMessage = $newActive
                    ? 'User account activated.'
                    : 'User account deactivated.';

            } else {
                throw new RuntimeException('Invalid action.');
            }

            $pdo->commit();

            set_flash('success', $successMessage);
            redirect('/admin/users.php');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to update the user. Please try again.';

            if (!($e instanceof RuntimeException)) {
                error_log('Admin user management error: ' . $e->getMessage());
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$sql = 'SELECT id, name, email, student_id, role, is_active, created_at
        FROM users WHERE 1 = 1';

$params = [];

if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ? OR student_id LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (in_array($roleFilter, $roles, true)) {
    $sql .= ' AND role = ?';
    $params[] = $roleFilter;
}

$sql .= ' ORDER BY created_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | CampusCare</title>

    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/css/style.css">

    <style>
        .users-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 25px;
        }

        .users-heading h1 {
            margin-bottom: 5px;
        }

        .users-heading p {
            color: var(--muted);
            margin: 0;
        }

        .users-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 14px;
            margin-bottom: 24px;
        }

        .users-toolbar .form-group {
            flex: 1;
            min-width: 180px;
            margin: 0;
        }

        .users-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .users-table {
            min-width: 950px;
        }

        .users-table td {
            vertical-align: middle;
        }

        .users-table form {
            margin: 0;
        }

        .role-form {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 230px;
        }

        .role-form select {
            min-width: 120px;
        }

        .role-form button,
        .account-button {
            padding: 8px 12px;
            min-height: 38px;
            font-size: 13px;
        }

        .account-button.deactivate {
            background: #fff;
            color: #b91c1c;
            border-color: #fecaca;
        }

        .account-button.deactivate:hover {
            background: #fef2f2;
        }

        .account-button.activate {
            background: #15803d;
            border-color: #15803d;
        }

        .account-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .account-status.active {
            background: #dcfce7;
            color: #166534;
        }

        .account-status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 600px) {
            .users-toolbar {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/admin/dashboard.php">
        <span style="font-size:28px;color:black">CampusCare Admin</span>
    </a>

    <nav>
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

    <section class="users-heading">
        <div>
            <h1>User Management</h1>
            <p>Manage registered students, staff, and administrators.</p>
        </div>

        <a class="btn btn-secondary"
           href="<?= e(BASE_URL) ?>/admin/dashboard.php">
            Back to Dashboard
        </a>
    </section>

    <?php if ($flash): ?>
        <div class="<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <section class="panel">
        <h2>Registered Accounts</h2>

        <form method="get"
              action="<?= e(BASE_URL) ?>/admin/users.php"
              class="users-toolbar">

            <div class="form-group">
                <label for="search">Search users</label>
                <input
                    id="search"
                    type="text"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Name, email, or student ID"
                >
            </div>

            <div class="form-group">
                <label for="role">Filter by role</label>
                <select id="role" name="role">
                    <option value="">All roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role) ?>"
                            <?= $roleFilter === $role ? 'selected' : '' ?>>
                            <?= e(ucfirst($role)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn btn-primary" type="submit">Search</button>

            <a class="btn btn-secondary"
               href="<?= e(BASE_URL) ?>/admin/users.php">
                Clear
            </a>
        </form>

        <div class="users-table-wrap">
            <table class="table users-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Account Status</th>
                    <th>Registered</th>
                    <th>Change Role</th>
                    <th>Account Action</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$users): ?>
                    <tr>
                        <td colspan="8">No users found.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>#<?= (int) $user['id'] ?></td>
                        <td><?= e($user['name']) ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td><?= e(ucfirst($user['role'])) ?></td>

                        <td>
                            <span class="account-status <?= (int) $user['is_active'] === 1 ? 'active' : 'inactive' ?>">
                                <?= (int) $user['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>

                        <td><?= e($user['created_at']) ?></td>

                        <td>
                            <?php if ((int) $user['id'] === (int) $admin['id']): ?>
                                <span>Current account</span>
                            <?php else: ?>
                                <form method="post"
                                      action="<?= e(BASE_URL) ?>/admin/users.php"
                                      class="role-form">

                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id"
                                           value="<?= (int) $user['id'] ?>">
                                    <input type="hidden" name="action"
                                           value="change_role">

                                    <select name="role" aria-label="Select role">
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= e($role) ?>"
                                                <?= $user['role'] === $role ? 'selected' : '' ?>>
                                                <?= e(ucfirst($role)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit">Save</button>
                                </form>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ((int) $user['id'] === (int) $admin['id']): ?>
                                <span>Protected</span>
                            <?php else: ?>
                                <form method="post"
                                      action="<?= e(BASE_URL) ?>/admin/users.php"
                                      onsubmit="return confirm('Change this account status?');">

                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id"
                                           value="<?= (int) $user['id'] ?>">
                                    <input type="hidden" name="action"
                                           value="toggle_active">

                                    <?php if ((int) $user['is_active'] === 1): ?>
                                        <button class="account-button deactivate"
                                                type="submit">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button class="account-button activate"
                                                type="submit">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>
</body>
</html>
