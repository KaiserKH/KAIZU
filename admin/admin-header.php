<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

function adminHeader($title, $active = '') {
    $url  = SITE_URL;
    $user = $_SESSION['user_name'] ?? 'Admin';
    // Pending order count for badge
    $pendingOrders = db()->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — ShopPHP Admin</title>
    <link rel="stylesheet" href="<?= $url ?>/assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>const SITE_URL = '<?= $url ?>';</script>
</head>
<body>
<div class="admin-wrap">

<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">⚙</span>
        ShopPHP Admin
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">Main</div>
        <a href="<?= $url ?>/admin/" class="<?= $active==='dashboard'?'active':'' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>

        <div class="nav-section-title">Catalog</div>
        <a href="<?= $url ?>/admin/products.php" class="<?= $active==='products'?'active':'' ?>">
            <span class="nav-icon">📦</span> Products
        </a>
        <a href="<?= $url ?>/admin/categories.php" class="<?= $active==='categories'?'active':'' ?>">
            <span class="nav-icon">🏷</span> Categories
        </a>

        <div class="nav-section-title">Sales</div>
        <a href="<?= $url ?>/admin/orders.php" class="<?= $active==='orders'?'active':'' ?>">
            <span class="nav-icon">🛒</span> Orders
            <?php if ($pendingOrders > 0): ?>
                <span class="nav-badge"><?= $pendingOrders ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $url ?>/admin/users.php" class="<?= $active==='users'?'active':'' ?>">
            <span class="nav-icon">👥</span> Users
        </a>

        <div class="nav-section-title">Site</div>
        <a href="<?= $url ?>/admin/settings.php" class="<?= $active==='settings'?'active':'' ?>">
            <span class="nav-icon">⚙</span> Settings
        </a>
        <a href="<?= $url ?>/admin/logs.php" class="<?= $active==='logs'?'active':'' ?>">
            <span class="nav-icon">📋</span> Activity Logs
        </a>
        <a href="<?= $url ?>/" target="_blank"><span class="nav-icon">🌐</span> View Store</a>
        <a href="<?= $url ?>/logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
</aside>

<!-- Main -->
<div class="admin-main">
    <header class="admin-topbar">
        <div style="display:flex;align-items:center;gap:.75rem">
            <button class="sidebar-toggle" id="sidebarToggle" title="Toggle menu">☰</button>
            <span class="topbar-title"><?= htmlspecialchars($title) ?></span>
        </div>
        <div class="topbar-right">
            <span class="topbar-user">👤 <?= htmlspecialchars($user) ?></span>
            <a href="<?= $url ?>/" target="_blank" class="topbar-view-store">🌐 View Store</a>
        </div>
    </header>
    <div class="admin-content">
<?php
    // Show flash messages
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger';
        echo "<div class='alert alert-{$type}'>" . htmlspecialchars($_SESSION['flash']['message']) . "</div>";
        unset($_SESSION['flash']);
    }
}

function adminFooter() {
?>
    </div><!-- /.admin-content -->
</div><!-- /.admin-main -->
</div><!-- /.admin-wrap -->

<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-icon">⚠️</div>
        <div class="modal-title" id="confirmTitle">Are you sure?</div>
        <div class="modal-text"  id="confirmText">This action cannot be undone.</div>
        <div class="modal-actions">
            <a href="#" id="confirmBtn" class="btn btn-danger">Confirm</a>
            <button onclick="closeConfirm()" class="btn btn-outline">Cancel</button>
        </div>
    </div>
</div>

<script>
// Sidebar toggle for mobile
const sidebarToggle = document.getElementById('sidebarToggle');
const adminSidebar  = document.getElementById('adminSidebar');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => adminSidebar.classList.toggle('open'));
}

// Confirm modal
function confirmAction(url, title, text) {
    document.getElementById('confirmTitle').textContent = title || 'Are you sure?';
    document.getElementById('confirmText').textContent  = text  || 'This action cannot be undone.';
    document.getElementById('confirmBtn').href = url;
    document.getElementById('confirmModal').classList.add('open');
    return false;
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
}
document.getElementById('confirmModal').addEventListener('click', function(e){
    if (e.target === this) closeConfirm();
});

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s'; setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
</body>
</html>
<?php
}
