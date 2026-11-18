
<?php
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin']);

// Fetch complaint records with student and assigned staff details.
$sql = "
    SELECT
        c.id,
        c.title,
        c.category,
        c.description,
        c.priority,
        c.status,
        c.created_at,
        c.updated_at,
        student.name AS student_name,
        student.email AS student_email,
        student.student_id,
        staff.name AS staff_name
    FROM complaints c
    INNER JOIN users student ON student.id = c.user_id
    LEFT JOIN users staff ON staff.id = c.assigned_to
    ORDER BY c.created_at DESC
";

$stmt = db()->query($sql);

// Send the CSV file as a download.
$filename = 'campuscare-complaints-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');

if ($output === false) {
    http_response_code(500);
    exit('Unable to generate the CSV file.');
}

// UTF-8 BOM helps Excel display text correctly.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Complaint ID',
    'Title',
    'Category',
    'Description',
    'Priority',
    'Status',
    'Student Name',
    'Student Email',
    'Student ID',
    'Assigned Staff',
    'Created At',
    'Updated At'
]);

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['id'],
        $row['title'],
        $row['category'],
        $row['description'],
        $row['priority'],
        $row['status'],
        $row['student_name'],
        $row['student_email'],
        $row['student_id'],
        $row['staff_name'] ?? 'Unassigned',
        $row['created_at'],
        $row['updated_at']
    ]);
}

fclose($output);
exit;
