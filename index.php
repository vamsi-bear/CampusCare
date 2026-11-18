
<?php

require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$base = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CampusCare | College Complaint Management</title>

    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --ink: #172554;
            --muted: #64748b;
            --background: #f7f8ff;
            --border: #e2e8f0;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: var(--background);
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.65;
        }

        a {
            text-decoration: none;
        }

        .cc-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.97);
            border-bottom: 1px solid var(--border);
        }

        .cc-nav {
            width: min(1160px, 92%);
            min-height: 76px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .cc-brand {
            color: var(--ink);
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -0.8px;
        }

        .cc-brand span {
            color: var(--primary);
        }

        .cc-links {
            display: flex;
            align-items: center;
            gap: 26px;
        }

        .cc-links a {
            color: var(--ink);
            font-weight: 600;
        }

        .cc-links a:hover {
            color: var(--primary);
        }

        .cc-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 12px 20px;
            border: 1px solid var(--primary);
            border-radius: 10px;
            background: var(--primary);
            color: var(--white);
            font-weight: 700;
            transition: background 0.2s, transform 0.2s;
        }

        .cc-button:hover {
            background: var(--primary-dark);
            color: var(--white);
            transform: translateY(-1px);
        }

        .cc-button-outline {
            background: var(--white);
            color: var(--primary);
        }

        .cc-button-outline:hover {
            background: #eef2ff;
            color: var(--primary-dark);
        }

        .cc-container {
            width: min(1160px, 92%);
            margin: auto;
        }

        .cc-hero {
            padding: 76px 0 70px;
        }

        .cc-hero-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            align-items: center;
            gap: 64px;
        }

        .cc-eyebrow {
            display: inline-block;
            margin-bottom: 15px;
            color: var(--primary);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .cc-hero h1 {
            max-width: 650px;
            margin: 0 0 22px;
            font-size: clamp(38px, 5vw, 60px);
            line-height: 1.13;
            letter-spacing: -2px;
        }

        .cc-hero h1 span {
            color: var(--primary);
        }

        .cc-description {
            max-width: 560px;
            margin-bottom: 28px;
            color: var(--muted);
            font-size: 18px;
        }

        .cc-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .cc-trust {
            margin-top: 22px;
            color: var(--muted);
            font-size: 14px;
        }

        .cc-preview {
            padding: 28px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: 0 24px 65px rgba(30, 41, 90, 0.09);
        }

        .cc-preview-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .cc-preview-top h2 {
            margin: 0;
            font-size: 20px;
        }

        .cc-live {
            padding: 5px 10px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 700;
        }

        .cc-preview-subtitle {
            margin: 18px 0;
            color: var(--muted);
            font-size: 14px;
        }

        .cc-preview-item {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 14px;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .cc-preview-icon {
            display: grid;
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            place-items: center;
            border-radius: 12px;
            background: #eef2ff;
            color: var(--primary);
            font-size: 21px;
        }

        .cc-preview-item strong {
            display: block;
            font-size: 15px;
        }

        .cc-preview-item small {
            display: block;
            color: var(--muted);
        }

        .cc-status {
            margin-left: auto;
            padding: 5px 9px;
            border-radius: 8px;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .cc-section {
            padding: 58px 0 72px;
        }

        .cc-section-heading {
            max-width: 680px;
            margin: 0 auto 38px;
            text-align: center;
        }

        .cc-section-heading h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 38px);
            letter-spacing: -1px;
        }

        .cc-section-heading p {
            margin: 0;
            color: var(--muted);
        }

        .cc-features {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .cc-feature {
            padding: 28px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 5px 18px rgba(15, 23, 42, 0.025);
        }

        .cc-feature-number {
            display: grid;
            width: 44px;
            height: 44px;
            margin-bottom: 20px;
            place-items: center;
            border-radius: 12px;
            background: #eef2ff;
            color: var(--primary);
            font-weight: 800;
        }

        .cc-feature h3 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .cc-feature p {
            margin: 0;
            color: var(--muted);
        }

        .cc-cta {
            margin-bottom: 70px;
            padding: 42px;
            border-radius: 20px;
            background: #eef2ff;
            text-align: center;
        }

        .cc-cta h2 {
            margin: 0 0 12px;
            font-size: 30px;
        }

        .cc-cta p {
            margin: 0 0 24px;
            color: var(--muted);
        }

        .cc-footer {
            padding: 25px 0;
            background: var(--white);
            border-top: 1px solid var(--border);
        }

        .cc-footer-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            color: var(--muted);
            font-size: 14px;
        }

        .cc-footer a {
            color: var(--primary);
        }

        @media (max-width: 850px) {
            .cc-hero {
                padding-top: 52px;
            }

            .cc-hero-grid {
                grid-template-columns: 1fr;
                gap: 38px;
            }

            .cc-preview {
                max-width: 600px;
                width: 100%;
            }

            .cc-features {
                grid-template-columns: 1fr;
            }

            .cc-section {
                padding: 42px 0 55px;
            }
        }

        @media (max-width: 520px) {
            .cc-nav {
                min-height: 68px;
                align-items: flex-start;
                flex-direction: column;
                justify-content: center;
                padding: 12px 0;
                gap: 10px;
            }

            .cc-brand {
                font-size: 22px;
            }

            .cc-links {
                width: 100%;
                justify-content: space-between;
                gap: 12px;
                font-size: 14px;
            }

            .cc-links .cc-button {
                padding: 8px 12px;
            }

            .cc-hero h1 {
                font-size: 39px;
                letter-spacing: -1.2px;
            }

            .cc-description {
                font-size: 16px;
            }

            .cc-preview {
                padding: 18px;
            }

            .cc-preview-item {
                gap: 9px;
                padding: 12px;
            }

            .cc-preview-icon {
                width: 38px;
                height: 38px;
            }

            .cc-status {
                padding: 4px 6px;
                font-size: 10px;
            }

            .cc-cta {
                padding: 28px 18px;
            }

            .cc-footer-inner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<header class="cc-header">
    <nav class="cc-nav">
        <a class="cc-brand" href="<?= e($base) ?>/index.php">
            <span>C</span> CampusCare
        </a>

        <div class="cc-links">
            <a href="#features">Features</a>
            <a href="<?= e($base) ?>/login.php">Login</a>
            <a class="cc-button" href="<?= e($base) ?>/register.php">
                Get Started
            </a>
        </div>
    </nav>
</header>

<main>
    <section class="cc-hero">
        <div class="cc-container cc-hero-grid">

            <div>
                <span class="cc-eyebrow">Your campus. Your voice.</span>

                <h1>
                    A better campus starts with
                    <span>being heard.</span>
                </h1>

                <p class="cc-description">
                    Report campus concerns, track complaint progress,
                    and communicate with college administrators through
                    one transparent and easy-to-use platform.
                </p>

                <div class="cc-actions">
                    <a class="cc-button"
                       href="<?= e($base) ?>/login.php">
                        Submit a Complaint &rarr;
                    </a>

                    <a class="cc-button cc-button-outline"
                       href="<?= e($base) ?>/login.php">
                        Track Your Complaint
                    </a>
                </div>

                <p class="cc-trust">
                    ✓ Secure access &nbsp; · &nbsp;
                    ✓ Organized records &nbsp; · &nbsp;
                    ✓ Clear updates
                </p>
            </div>

            <div class="cc-preview">
                <div class="cc-preview-top">
                    <h2>CampusCare Overview</h2>
                    <span class="cc-live">Live Portal</span>
                </div>

                <p class="cc-preview-subtitle">
                    A transparent way to improve campus life.
                </p>

                <div class="cc-preview-item">
                    <div class="cc-preview-icon">&#127979;</div>
                    <div>
                        <strong>Campus Facilities</strong>
                        <small>Follow your complaint status</small>
                    </div>
                    <span class="cc-status">Pending</span>
                </div>

                <div class="cc-preview-item">
                    <div class="cc-preview-icon">&#128218;</div>
                    <div>
                        <strong>Student Services</strong>
                        <small>View updates and responses</small>
                    </div>
                    <span class="cc-status"
                          style="background:#dcfce7;color:#166534">
                        Resolved
                    </span>
                </div>

                <div class="cc-preview-item">
                    <div class="cc-preview-icon">&#128274;</div>
                    <div>
                        <strong>Account Security</strong>
                        <small>Protected, role-based access</small>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section class="cc-section" id="features">
        <div class="cc-container">

            <div class="cc-section-heading">
                <span class="cc-eyebrow">Built for your campus</span>
                <h2>From reporting to resolution</h2>
                <p>
                    Everything you need to make campus concerns easier
                    to manage.
                </p>
            </div>

            <div class="cc-features">
                <article class="cc-feature">
                    <div class="cc-feature-number">01</div>
                    <h3>Easy Reporting</h3>
                    <p>
                        Submit concerns with a clear title, category,
                        priority, and description.
                    </p>
                </article>

                <article class="cc-feature">
                    <div class="cc-feature-number">02</div>
                    <h3>Status Tracking</h3>
                    <p>
                        Follow your complaint as it moves through
                        the resolution process.
                    </p>
                </article>

                <article class="cc-feature">
                    <div class="cc-feature-number">03</div>
                    <h3>Account Security</h3>
                    <p>
                        Protected accounts and role-based access
                        for students, staff, and administrators.
                    </p>
                </article>
            </div>

        </div>
    </section>

    <section class="cc-container">
        <div class="cc-cta">
            <h2>Help make your campus better</h2>
            <p>
                Sign in to submit a concern or create your student account
                to get started.
            </p>
            <a class="cc-button"
               href="<?= e($base) ?>/register.php">
                Create Your Account &rarr;
            </a>
        </div>
    </section>
</main>

<footer class="cc-footer">
    <div class="cc-container cc-footer-inner">
        <span>
            &copy; <?= date('Y') ?> CampusCare · College Complaint Management Portal
        </span>
        <span>
            <a href="<?= e($base) ?>/login.php">Login</a>
            &nbsp; · &nbsp;
            <a href="<?= e($base) ?>/register.php">Register</a>
        </span>
    </div>
</footer>

</body>
</html>
