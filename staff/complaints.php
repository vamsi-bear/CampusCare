
<?php
require_once __DIR__ . '/../includes/functions.php';

$staff = require_role(['staff']);
$pdo = db();
$staffId = (int) $staff['id'];

$allowedStatuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$sql = "
    SELECT
        c.id,
        c.title,
        c.category,
        c.priority,
        c.status,
        c.created_at,
        c.updated_at,
        student.name AS student_name
    FROM complaints c
    INNER JOIN users student ON student.id = c.user_id
    WHERE c.assigned_to = ?
";

$params = [$staffId];

if ($status !== '') {
    $sql .= " AND c.status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $sql .= " AND (
        c.title LIKE ? OR
        c.category LIKE ? OR
        student.name LIKE ? OR
        CAST(c.id AS CHAR) LIKE ?
    )";

    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$sql .= "
    ORDER BY
        CASE c.status
            WHEN 'Pending' THEN 1
            WHEN 'In Progress' THEN 2
            WHEN 'Resolved' THEN 3
            WHEN 'Rejected' THEN 4
            ELSE 5
        END,
        c.updated_at DESC,
        c.id DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

function staff_list_status_class(string $status): string
{
    return match ($status) {
        'Pending' => 'pending',
        'In Progress' => 'in-progress',
        'Resolved' => 'resolved',
        'Rejected' => 'rejected',
        default => 'unknown',
    };
}

function staff_list_priority_class(string $priority): string
{
    return match (strtolower($priority)) {
        'high' => 'priority-high',
        'medium' => 'priority-medium',
        'low' => 'priority-low',
        default => 'priority-unknown',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Complaints | CampusCare</title>

    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f7ff;
            color: #172554;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #fff;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: 0 2px 12px rgba(23, 37, 84, .06);
        }

        .brand {
            color: #000000;
            font-size: 28px;
            font-weight: 800;
            text-decoration: none;
        }

        .topbar nav {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .topbar nav a {
            color: #475569;
            text-decoration: none;
            font-weight: 600;
        }

        .topbar nav a:hover {
            color: #4f46e5;
        }

        .logout-form {
            margin: 0;
        }

        .logout-button {
            padding: 10px 15px;
            border: 0;
            border-radius: 8px;
            background: #4f46e5;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .list-wrap {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .list-head {
            margin-bottom: 24px;
        }

        .list-head h1 {
            margin-bottom: 8px;
        }

        .list-head p {
            color: #64748b;
            line-height: 1.6;
        }

        .list-panel {
            background: #fff;
            border: 1px solid #e0e5f0;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 5px 20px rgba(23, 37, 84, .04);
        }

        .list-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .list-filters input,
        .list-filters select {
            padding: 11px 12px;
            border: 1px solid #d5dbea;
            border-radius: 9px;
            font: inherit;
            min-width: 180px;
            background: #fff;
        }

        .list-filters input:focus,
        .list-filters select:focus {
            outline: 2px solid #a5b4fc;
            border-color: #4f46e5;
        }

        .filter-button {
            padding: 11px 18px;
            background: #4f46e5;
            color: #fff;
            border: 0;
            border-radius: 9px;
            font-weight: 700;
            cursor: pointer;
        }

        .list-reset {
            align-self: center;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }

        .results-summary {
            color: #64748b;
            font-size: 14px;
            margin: 15px 0;
        }

        .list-table-wrap {
            overflow-x: auto;
        }

        .list-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .list-table th,
        .list-table td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #e0e5f0;
        }

        .list-table th {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .list-table td {
            font-size: 14px;
        }

        .list-table tbody tr:hover {
            background: #f8faff;
        }

        .status,
        .priority {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .in-progress {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .resolved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .unknown,
        .priority-unknown {
            background: #e2e8f0;
            color: #334155;
        }

        .priority-high {
            background: #fee2e2;
            color: #b91c1c;
        }

        .priority-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .priority-low {
            background: #dcfce7;
            color: #166534;
        }

        .view-link {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 700;
            white-space: nowrap;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        .list-empty {
            text-align: center;
            padding: 35px;
            color: #64748b;
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 16px 18px;
            }

            .topbar nav {
                gap: 12px;
            }

            .list-wrap {
                margin: 24px auto;
                padding: 0 14px;
            }

            .list-panel {
                padding: 14px;
            }

            .list-filters {
                flex-direction: column;
            }

            .list-filters input,
            .list-filters select,
            .filter-button {
                width: 100%;
                min-width: 0;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <a class="brand"
       href="<?= e(BASE_URL) ?>/staff/dashboard.php">
        CampusCare Staff
    </a>

    <nav>
        <a href="<?= e(BASE_URL) ?>/staff/dashboard.php">
            Dashboard
        </a>

        <a href="<?= e(BASE_URL) ?>/staff/complaints.php">
            My Complaints
        </a>

        <form class="logout-form"
              action="<?= e(BASE_URL) ?>/logout.php"
              method="post">
            <?= csrf_field() ?>
            <button class="logout-button" type="submit">
                Logout
            </button>
        </form>
    </nav>
</header>

<main class="list-wrap">
    <section class="list-head">
        <h1>My Assigned Complaints</h1>
        <p>
            Search your assigned complaints and filter them by status.
        </p>
    </section>

    <section class="list-panel">
        <form
            method="get"
            action="<?= e(BASE_URL) ?>/staff/complaints.php"
            class="list-filters"
        >
            <input
                type="search"
                name="search"
                placeholder="Search title, student or ID"
                value="<?= e($search) ?>"
                maxlength="100"
            >

            <select name="status" aria-label="Filter by status">
                <option value="">All statuses</option>

                <?php foreach ($allowedStatuses as $option): ?>
                    <option
                        value="<?= e($option) ?>"
                        <?= $status === $option ? 'selected' : '' ?>
                    >
                        <?= e($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="filter-button" type="submit">
                Apply Filters
            </button>

            <a
                class="list-reset"
                href="<?= e(BASE_URL) ?>/staff/complaints.php"
            >
                Reset
            </a>
        </form>

        <p class="results-summary">
            Showing <strong><?= count($complaints) ?></strong>
            complaint(s). A maximum of 200 results is displayed.
        </p>

        <?php if (!$complaints): ?>
            <div class="list-empty">
                <h3>No matching complaints</h3>
                <p>
                    Try changing your search or selecting another status.
                </p>
            </div>
        <?php else: ?>
            <div class="list-table-wrap">
                <table class="list-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Complaint</th>
                        <th>Student</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($complaints as $item): ?>
                        <tr>
                            <td>#<?= (int) $item['id'] ?></td>

                            <td><?= e($item['title']) ?></td>

                            <td><?= e($item['student_name']) ?></td>

                            <td><?= e($item['category']) ?></td>

                            <td>
                                <span class="priority <?= e(staff_list_priority_class((string) $item['priority'])) ?>">
                                    <?= e($item['priority']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status <?= e(staff_list_status_class((string) $item['status'])) ?>">
                                    <?= e($item['status']) ?>
                                </span>
                            </td>

                            <td><?= e($item['updated_at']) ?></td>

                            <td>
                                <a
                                    class="view-link"
                                    href="<?= e(BASE_URL) ?>/staff/complaint.php?id=<?= (int) $item['id'] ?>"
                                >
                                    View / Update
                                </a>
                            </td>
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
