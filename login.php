
<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$error = '';
$email = '';

if (is_post()) {
    verify_csrf();

    $emailInput = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!is_string($emailInput) || !is_string($password)) {
        $error = 'Invalid email or password.';
    } else {
        $email = strtolower(trim($emailInput));

        if (
            $email === '' ||
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $password === ''
        ) {
            $error = 'Invalid email or password.';
        } else {
            try {
                $stmt = db()->prepare(
                    'SELECT id, name, email, password_hash, role, is_active
                     FROM users
                     WHERE email = ?
                     LIMIT 1'
                );

                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    $user &&
                    (int) $user['is_active'] === 1 &&
                    password_verify($password, $user['password_hash'])
                ) {
                    // Regenerate session ID and CSRF token after login.
                    establish_user_session($user);

                    // Audit logging should not block successful login.
                    try {
                        log_activity(
                            (int) $user['id'],
                            'LOGIN',
                            'User logged in successfully.'
                        );
                    } catch (Throwable $exception) {
                        error_log(
                            'Login activity logging failed: ' .
                            $exception->getMessage()
                        );
                    }

                    redirect('/dashboard.php');
                }

                $error = 'Invalid email or password.';
            } catch (Throwable $exception) {
                error_log(
                    'Login failed: ' . $exception->getMessage()
                );

                $error = 'Login is temporarily unavailable. Please try again.';
            }
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CampusCare</title>
    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/index.php">
        <span class="brand-icon"></span>
        CampusCare
    </a>

    <nav class="nav-links">
        <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
        <a href="<?= e(BASE_URL) ?>/register.php">Register</a>
    </nav>
</header>

<main class="page-container">
    <section class="form-card">
        <h1>Welcome back</h1>
        <p class="muted">Log in to manage your college complaints.</p>

        <?php if (is_array($flash) && !empty($flash['message'])): ?>
            <div class="<?= ($flash['type'] ?? '') === 'success'
                ? 'success'
                : 'error' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="<?= e(BASE_URL) ?>/login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    autocomplete="username"
                    value="<?= e($email) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button class="btn btn-primary" type="submit">
                Log In
            </button>
        </form>

        <p class="form-footnote">
            Don't have an account?
            <a href="<?= e(BASE_URL) ?>/register.php">Create an account</a>
        </p>
    </section>
</main>
</body>
</html>
