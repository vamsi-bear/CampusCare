
<?php
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin']);

$staffMembers = db()->query("
    SELECT
        u.id,
        u.name,
        u.email,
        u.student_id,
        u.is_active,
        u.created_at,
        COUNT(c.id) AS assigned_complaints
    FROM users u
    LEFT JOIN complaints c ON c.assigned_to = u.id
    WHERE u.role = 'staff'
    GROUP BY
        u.id, u.name, u.email, u.student_id, u.is_active, u.created_at
    ORDER BY u.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management | CampusCare</title>
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7ff;
            color: #172554;
        }
        .topbar {
            background: #fff;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 2px 12px #1725540d;
        }
        .brand { font-size: 28px; font-weight: 800; color: #000001; }
        nav { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
        a { color: #000000; text-decoration: none; font-weight: 600; }
        .logout { display: inline; margin: 0; }
        /* Logout button */
.logout button {
    border: 0;
    background: blue;
    color: #efefef;
    cursor: pointer;
    font: inherit;
    font-weight: 600;
    padding: 9px 16px;
    border-radius: 8px;
    transition: background 0.2s ease;
}

.logout button:hover {
    background: #0000a0;
}
        main { max-width: 1150px; margin: 35px auto; padding: 0 20px; }
        h1 { margin-bottom: 8px; }
        .muted { color: #64748b; }
        .panel {
            margin-top: 25px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 24px #17255408;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 750px; }
        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        th { background: #f8fafc; color: #475569; font-size: 13px; }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .active { background: #dcfce7; color: #166534; }
        .inactive { background: #fee2e2; color: #991b1b; }
        .empty { padding: 30px; text-align: center; color: #64748b; }
        @media (max-width: 600px) {
            main { margin-top: 22px; padding: 0 12px; }
            .panel { padding: 12px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">CampusCare Admin</div>
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

<main>
    <h1>Staff Management</h1>
    <p class="muted">View staff accounts and the complaints assigned to each member.</p>

    <section class="panel">
        <h2>Staff Members (<?= count($staffMembers) ?>)</h2>

        <?php if (!$staffMembers): ?>
            <div class="empty">
                <h3>No staff members found</h3>
                <p>Open the Users page and change an existing user's role to Staff.</p>
                <a href="<?= BASE_URL ?>/admin/users.php">Manage users</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Account Status</th>
                            <th>Assigned Complaints</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($staffMembers as $member): ?>
                        <tr>
                            <td>
                                <strong><?= e($member['name']) ?></strong>
                                <?php if (!empty($member['student_id'])): ?>
                                    <br><small class="muted"><?= e($member['student_id']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($member['email']) ?></td>
                            <td>
                                <?php if ((int)$member['is_active'] === 1): ?>
                                    <span class="badge active">Active</span>
                                <?php else: ?>
                                    <span class="badge inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$member['assigned_complaints'] ?></td>
                            <td><?= e(date('d M Y', strtotime($member['created_at']))) ?></td>
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
