
<?php

require_once __DIR__ . '/../config/config.php';

// Configure and start the session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => BASE_URL . '/',
    ]);

    session_start();
}

/**
 * Get the shared PDO database connection.
 */
function db(): PDO
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException(
            'Database connection is not initialized. Check config/config.php.'
        );
    }

    return $pdo;
}

/**
 * Escape output before displaying it in HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Check whether the current request uses POST.
 */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Regenerate the CSRF token.
 */
function regenerate_csrf_token(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return $_SESSION['csrf_token'];
}

/**
 * Generate or retrieve the current CSRF token.
 */
function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        return regenerate_csrf_token();
    }

    return $_SESSION['csrf_token'];
}

/**
 * Return the CSRF input field for forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token()) . '">';
}

/**
 * Validate the submitted CSRF token.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submitted) ||
        !is_string($stored) ||
        $stored === '' ||
        !hash_equals($stored, $submitted)
    ) {
        http_response_code(403);
        exit(
            'Invalid security token. Please refresh the page and try again.'
        );
    }
}

/**
 * Redirect to a path inside the application.
 */
function redirect(string $path): never
{
    if (
        $path === '' ||
        $path[0] !== '/' ||
        str_starts_with($path, '//') ||
        str_contains($path, "\r") ||
        str_contains($path, "\n")
    ) {
        throw new InvalidArgumentException('Invalid redirect path.');
    }

    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Get the current active user from the database.
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, student_id, role, is_active
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int) $user['is_active'] !== 1) {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
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

            session_regenerate_id(true);
        }

        return null;
    }

    return $user;
}

/**
 * Require an authenticated user.
 */
function require_login(): array
{
    $user = current_user();

    if (!$user) {
        redirect('/login.php');
    }

    return $user;
}

/**
 * Require one of the permitted user roles.
 */
function require_role(array $roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }

    return $user;
}

/**
 * Regenerate the session ID after successful authentication.
 */
function establish_user_session(array $user): void
{
    if (empty($user['id']) || empty($user['role'])) {
        throw new InvalidArgumentException(
            'A valid authenticated user is required.'
        );
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate session ID.');
    }

    $_SESSION['user_id'] = (int) $user['id'];
    regenerate_csrf_token();
}

/**
 * Store a one-time flash message.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and remove the one-time flash message.
 */
function get_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        unset($_SESSION['flash']);
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

/**
 * Record an action in the audit log.
 */
function log_activity(
    ?int $userId,
    string $action,
    string $details = ''
): void {
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, details)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([$userId, $action, $details]);
}

/**
 * Create a notification for a user.
 */
function create_notification(
    int $userId,
    ?int $complaintId,
    string $title,
    string $message
): bool {
    if (
        $userId < 1 ||
        ($complaintId !== null && $complaintId < 1) ||
        trim($title) === '' ||
        trim($message) === ''
    ) {
        return false;
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications
                (user_id, complaint_id, title, message)
             VALUES (?, ?, ?, ?)'
        );

        return $stmt->execute([
            $userId,
            $complaintId,
            trim($title),
            trim($message),
        ]);
    } catch (PDOException $exception) {
        error_log(
            'Notification creation failed: ' . $exception->getMessage()
        );

        return false;
    }
}
