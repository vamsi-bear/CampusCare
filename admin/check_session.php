
<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "Session user ID: ";
var_export($_SESSION['user_id'] ?? null);

echo "\n\nCurrent user details:\n";
var_export(current_user());

echo "\n";
