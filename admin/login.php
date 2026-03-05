<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in as admin → dashboard
if (isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/'); exit;
}

$error = '';
$info  = '';

if (isset($_GET['error'])) {
    $info = match($_GET['error']) {
        'disabled' => 'Your account has been disabled.',
        default    => ''
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            if (($user['status'] ?? 'active') === 'disabled') {
                logActivity('admin_login_blocked', $user['id'], "Disabled admin login attempt");
                $error = 'This admin account has been disabled.';
            } else {
                loginUser($user);
                logActivity('admin_login', $user['id'], "Admin login from {$_SERVER['REMOTE_ADDR']}");
                header('Location: ' . SITE_URL . '/admin/'); exit;
            }
        } else {
            usleep(500000); // 0.5s brute-force delay for admin
            logActivity('admin_login_failed', null, "Failed admin login for: $email");
            $error = 'Invalid credentials or insufficient privileges.';
        }
    }
}

$siteName = getSetting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= htmlspecialchars($siteName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            background: #0f172a;
            color: #e2e8f0;
        }

        /* Left panel */
        .al-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }

        /* Animated grid background */
        .al-left::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(37,99,235,.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(37,99,235,.07) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: gridMove 20s linear infinite;
        }
        @keyframes gridMove {
            0%   { transform: translateY(0); }
            100% { transform: translateY(40px); }
        }

        /* Glow orb */
        .al-left::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(37,99,235,.25) 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            pointer-events: none;
        }

        .al-brand {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .al-brand-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 40px rgba(37,99,235,.4);
            animation: iconPulse 3s ease-in-out infinite;
        }
        @keyframes iconPulse {
            0%, 100% { box-shadow: 0 0 30px rgba(37,99,235,.3); }
            50%       { box-shadow: 0 0 60px rgba(37,99,235,.6); }
        }
        .al-brand h1 { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: .5rem; }
        .al-brand p  { color: #94a3b8; font-size: 1rem; }

        .al-features {
            position: relative; z-index: 1;
            margin-top: 3rem;
            display: flex; flex-direction: column; gap: .9rem;
        }
        .al-feature {
            display: flex; align-items: center; gap: .75rem;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 10px;
            padding: .75rem 1.1rem;
            backdrop-filter: blur(8px);
        }
        .al-feature-icon { font-size: 1.1rem; }
        .al-feature-text { font-size: .85rem; color: #cbd5e1; }

        /* Right panel */
        .al-right {
            width: 480px;
            flex-shrink: 0;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
        }

        .al-form-wrap { width: 100%; max-width: 360px; }

        .al-logo-small {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin: 0 auto 1.5rem;
        }

        .al-form-wrap h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            text-align: center;
            margin-bottom: .4rem;
        }
        .al-form-wrap .al-subtitle {
            text-align: center;
            color: #64748b;
            font-size: .875rem;
            margin-bottom: 2rem;
        }

        .al-alert {
            padding: .8rem 1rem;
            border-radius: 10px;
            font-size: .85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: .5rem;
        }
        .al-alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .al-alert-info  { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

        .al-form-group { margin-bottom: 1.1rem; }
        .al-form-group label {
            display: block;
            font-size: .8rem;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: .4rem;
        }
        .al-form-group input {
            width: 100%;
            padding: .75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: .9rem;
            color: #111827;
            outline: none;
            transition: border-color .18s, box-shadow .18s;
        }
        .al-form-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37,99,235,.1);
        }

        .al-btn {
            width: 100%;
            padding: .85rem;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: .5rem;
            transition: opacity .18s, transform .18s;
            letter-spacing: .3px;
        }
        .al-btn:hover   { opacity: .92; }
        .al-btn:active  { transform: scale(.98); }

        .al-back-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .82rem;
            color: #94a3b8;
        }
        .al-back-link a { color: #2563eb; font-weight: 600; }

        .al-secure-note {
            display: flex;
            align-items: center;
            gap: .4rem;
            justify-content: center;
            margin-top: 2rem;
            font-size: .75rem;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .al-left  { padding: 2.5rem 1.5rem; flex: none; }
            .al-features { display: none; }
            .al-right { width: 100%; padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Left branding panel -->
<div class="al-left">
    <div class="al-brand">
        <div class="al-brand-icon">⚙</div>
        <h1><?= htmlspecialchars($siteName) ?></h1>
        <p>Administration Panel</p>
    </div>
    <div class="al-features">
        <div class="al-feature">
            <span class="al-feature-icon">📊</span>
            <span class="al-feature-text">Real-time sales dashboard</span>
        </div>
        <div class="al-feature">
            <span class="al-feature-icon">📦</span>
            <span class="al-feature-text">Manage products & inventory</span>
        </div>
        <div class="al-feature">
            <span class="al-feature-icon">🛒</span>
            <span class="al-feature-text">Process and track orders</span>
        </div>
        <div class="al-feature">
            <span class="al-feature-icon">🔒</span>
            <span class="al-feature-text">Audit log & security monitoring</span>
        </div>
    </div>
</div>

<!-- Right login panel -->
<div class="al-right">
    <div class="al-form-wrap">
        <div class="al-logo-small">⚙</div>
        <h2>Admin Login</h2>
        <p class="al-subtitle">Sign in with your administrator credentials</p>

        <?php if ($error): ?>
        <div class="al-alert al-alert-error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
        <div class="al-alert al-alert-info">ℹ <?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="al-form-group">
                <label>Email Address</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="admin@example.com" required autofocus>
            </div>
            <div class="al-form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••••" required>
            </div>
            <button type="submit" class="al-btn">Sign In to Dashboard →</button>
        </form>

        <div class="al-back-link">
            ← <a href="<?= SITE_URL ?>/">Return to store</a>
        </div>
        <div class="al-secure-note">🔒 Secured connection · Admin only</div>
    </div>
</div>

</body>
</html>
