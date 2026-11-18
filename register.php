
<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect already logged-in users.
if (current_user()) {
    redirect('/dashboard.php');
}

$errors = [];
$name = '';
$email = '';
$studentId = '';

if (is_post()) {
    verify_csrf();

    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $studentIdInput = $_POST['student_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate input types before processing.
    if (
        !is_string($nameInput) ||
        !is_string($emailInput) ||
        !is_string($studentIdInput) ||
        !is_string($password) ||
        !is_string($confirmPassword)
    ) {
        $errors[] = 'Invalid form data. Please try again.';
    } else {
        $name = trim($nameInput);
        $email = strtolower(trim($emailInput));
        $studentId = trim($studentIdInput);

        // Validate name.
        if (
            $name === '' ||
            strlen($name) > 100 ||
            preg_match('/[\x00-\x1F\x7F]/', $name)
        ) {
            $errors[] = 'Enter a valid name (maximum 100 characters).';
        }

        // Validate email.
        if (
            $email === '' ||
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Enter a valid email address.';
        }

        // Validate optional student ID.
        if (
            strlen($studentId) > 50 ||
            preg_match('/[\x00-\x1F\x7F]/', $studentId)
        ) {
            $errors[] = 'Enter a valid student ID (maximum 50 characters).';
        }

        // Validate password length.
        // PASSWORD_DEFAULT may use bcrypt, which accepts at most 72 bytes.
        if (strlen($password) < 10) {
            $errors[] = 'Password must contain at least 10 characters.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'Password must not exceed 72 bytes.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
    }

    if (!$errors) {
        try {
            $pdo = db();

            // Check whether the email already exists.
            $stmt = $pdo->prepare(
                'SELECT id FROM users WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $errors[] = 'This email address is already registered.';
            } else {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($passwordHash === false) {
                    throw new RuntimeException(
                        'Password hashing failed.'
                    );
                }

                // Public registration always creates a student account.
                $stmt = $pdo->prepare(
                    'INSERT INTO users
                        (name, email, student_id, password_hash, role)
                     VALUES (?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $name,
                    $email,
                    $studentId !== '' ? $studentId : null,
                    $passwordHash,
                    'student',
                ]);

                set_flash(
                    'success',
                    'Registration successful! You can now log in.'
                );

                redirect('/login.php');
            }
        } catch (PDOException $exception) {
            error_log(
                'Registration database error: ' .
                $exception->getMessage()
            );

            if ($exception->getCode() === '23000') {
                $errors[] =
                    'This email or student ID may already be registered.';
            } else {
                $errors[] =
                    'Registration failed. Please try again later.';
            }
        } catch (Throwable $exception) {
            error_log(
                'Registration error: ' . $exception->getMessage()
            );

            $errors[] =
                'Registration failed. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | CampusCare</title>
    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/index.php">
        <span class="brand-icon">C</span>
        CampusCare
    </a>

    <nav class="nav-links">
        <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
        <a href="<?= e(BASE_URL) ?>/login.php">Login</a>
    </nav>
</header>

<main class="page-container">
    <section class="form-card">
        <h1>Create your account</h1>
        <p class="muted">
            Register as a student to submit and track college complaints.
        </p>

        <?php if ($errors): ?>
            <div class="error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="<?= e(BASE_URL) ?>/register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    autocomplete="name"
                    value="<?= e($name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">College email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    autocomplete="email"
                    value="<?= e($email) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="student_id">Student ID (optional)</label>
                <input
                    type="text"
                    id="student_id"
                    name="student_id"
                    maxlength="50"
                    autocomplete="off"
                    value="<?= e($studentId) ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="10"
                    maxlength="72"
                    autocomplete="new-password"
                    required
                >
                <small>Use at least 10 characters.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="10"
                    maxlength="72"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button class="btn btn-primary" type="submit">
                Create Account
            </button>
        </form>

        <p class="form-footnote">
            Already have an account?
            <a href="<?= e(BASE_URL) ?>/login.php">Log in here</a>
        </p>
    </section>
</main>
</body>
</html>
