<?php
$pageTitle = 'Register';
require_once 'includes/config.php';
require_once 'includes/functions.php';
if (isLoggedIn()) redirect('index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$name)                                    $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))$errors[] = 'Valid email is required.';
    if (strlen($password) < 6)                     $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                    $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Check if email exists
        $check = db()->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $errors[] = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = db()->prepare("INSERT INTO users (name, email, password) VALUES (?,?,?)");
            $ins->bind_param('sss', $name, $email, $hash);
            $ins->execute();

            $userId = db()->insert_id;
            $user   = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'customer', 'status' => 'active'];
            loginUser($user);
            logActivity('register', $userId, "New account registered: $email");
            mergeGuestCart($userId);
            flash("Welcome, $name! Your account has been created.", 'success');
            redirect('index.php');
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Create Account</h1>
        <p>Join us for a better shopping experience</p>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= sanitize($e) ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= sanitize($_POST['name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?= SITE_URL ?>/login.php">Sign in</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
