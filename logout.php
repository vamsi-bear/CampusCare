
<?php
require_once __DIR__ . '/includes/functions.php';

if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();

$userId = $_SESSION['user_id'] ?? null;

// Logging should not prevent a user from logging out.
if ($userId !== null) {
    try {
        log_activity(
            (int) $userId,
            'LOGOUT',
            'User logged out.'
        );
    } catch (Throwable $exception) {
        error_log(
            'Logout activity logging failed: ' .
            $exception->getMessage()
        );
    }
}

// Clear all session data.
$_SESSION = [];

// Expire the session cookie using its existing settings.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// Destroy the server-side session.
session_destroy();

// Return to the public landing page.
header('Location: ' . BASE_URL . '/index.php');
exit;
