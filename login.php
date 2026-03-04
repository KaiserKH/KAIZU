<?php
$pageTitle = 'Sign In';
require_once 'includes/config.php';
require_once 'includes/functions.php';
if (isLoggedIn()) redirect('index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $redirect = $_GET['redirect'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            if (($user['status'] ?? 'active') === 'disabled') {
                $errors[] = 'Your account has been disabled. Contact support.';
                logActivity('login_blocked', $user['id'], "Disabled account login attempt: $email");
            } else {
                loginUser($user);
                logActivity('login', $user['id'], "Login from {$_SERVER['REMOTE_ADDR']}");
                mergeGuestCart($user['id']);
                flash("Welcome back, " . htmlspecialchars($user['name']) . "!", 'success');
                // Safe redirect
                if ($redirect && strpos($redirect, '..') === false && strpos($redirect, 'http') !== 0) {
                    header("Location: " . SITE_URL . $redirect); exit;
                }
                redirect('index.php');
            }
        } else {
            usleep(300000); // 0.3s delay to slow brute-force
            logActivity('login_failed', null, "Failed login attempt for: $email");
            $errors[] = 'Invalid email or password.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Welcome Back</h1>
        <p>Sign in to your account to continue shopping</p>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= sanitize($e) ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control"
                       value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="<?= SITE_URL ?>/register.php">Create one</a>
        </div>
        <div class="auth-footer" style="margin-top:.5rem">
            Are you an admin? <a href="<?= SITE_URL ?>/admin/login.php">Admin Login →</a>
        </div>

        <div style="margin-top:1rem;padding:1rem;background:var(--light);border-radius:var(--radius);font-size:.8rem;color:var(--gray)">
            <strong>Demo:</strong> admin@shopphp.com / password
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
