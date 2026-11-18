
<?php
require_once __DIR__ . '/config/config.php';

try {
    $pdo = db();

    echo '<h2>Database Connection Successful!</h2>';
    echo '<p>Complaint Portal is connected to MySQL.</p>';

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    echo '<h3>Database Tables</h3><ul>';

    foreach ($tables as $table) {
        echo '<li>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    echo '</ul>';
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h2>Database Connection Failed</h2>';
    echo '<p>Check your database name, credentials, and MySQL service.</p>';

    error_log($e->getMessage());
}
