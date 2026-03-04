<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once 'admin-header.php';

// Ensure users table has a `status` column (add if missing)
$cols = db()->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC);
$colNames = array_column($cols, 'Field');
if (!in_array('status', $colNames)) {
    db()->query("ALTER TABLE users ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active' AFTER role");
}

$currentUserId = (int)$_SESSION['user_id'];

// Actions
if (isset($_GET['action'], $_GET['id'])) {
    $uid    = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($uid === $currentUserId) {
        flash("You cannot modify your own account.", 'danger');
        header('Location: users.php'); exit;
    }

    if ($action === 'delete') {
        db()->prepare("DELETE FROM users WHERE id=?")->bind_param('i',$uid)->execute();
        flash('User deleted.', 'success');
    } elseif ($action === 'disable') {
        db()->prepare("UPDATE users SET status='disabled' WHERE id=?")->bind_param('i',$uid)->execute();
        flash('User disabled.', 'success');
    } elseif ($action === 'enable') {
        db()->prepare("UPDATE users SET status='active' WHERE id=?")->bind_param('i',$uid)->execute();
        flash('User enabled.', 'success');
    } elseif ($action === 'make_admin') {
        db()->prepare("UPDATE users SET role='admin' WHERE id=?")->bind_param('i',$uid)->execute();
        flash('User promoted to admin.', 'success');
    } elseif ($action === 'make_customer') {
        db()->prepare("UPDATE users SET role='customer' WHERE id=?")->bind_param('i',$uid)->execute();
        flash('User demoted to customer.', 'success');
    }
    header('Location: users.php'); exit;
}

// Filters
$roleFilter = $_GET['role'] ?? '';
$search     = trim($_GET['search'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 25;
$offset     = ($page - 1) * $perPage;

$where  = "WHERE 1";
$params = []; $types  = '';
if ($roleFilter) { $where .= " AND role=?"; $params[] = $roleFilter; $types .= 's'; }
if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $like = "%$search%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}

$countStmt = db()->prepare("SELECT COUNT(*) AS c FROM users $where");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['c'];

$listStmt = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS order_count,
    (SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=u.id AND status!='cancelled') AS total_spent
    FROM users u $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
$listTypes  = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$users = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalAdmins    = db()->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$totalCustomers = db()->query("SELECT COUNT(*) AS c FROM users WHERE role='customer'")->fetch_assoc()['c'];
$totalDisabled  = db()->query("SELECT COUNT(*) AS c FROM users WHERE status='disabled'")->fetch_assoc()['c'] ?? 0;

adminHeader('Users', 'users');
?>

<!-- Stats row -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:1.5rem">
    <div class="stat-card purple">
        <div class="stat-icon">👥</div>
        <div class="stat-body">
            <div class="stat-value"><?= $totalCustomers ?></div>
            <div class="stat-label">Customers</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🛡</div>
        <div class="stat-body">
            <div class="stat-value"><?= $totalAdmins ?></div>
            <div class="stat-label">Admins</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">🚫</div>
        <div class="stat-body">
            <div class="stat-value"><?= $totalDisabled ?></div>
            <div class="stat-label">Disabled</div>
        </div>
    </div>
</div>

<div class="page-toolbar">
    <form method="GET" class="toolbar-search" style="flex-wrap:wrap;gap:.5rem">
        <input type="text" name="search" class="form-control" placeholder="Search name or email…" value="<?= sanitize($search) ?>">
        <select name="role" class="form-control" style="width:auto">
            <option value="">All Roles</option>
            <option value="customer" <?= $roleFilter==='customer'?'selected':'' ?>>Customers</option>
            <option value="admin"    <?= $roleFilter==='admin'?'selected':'' ?>>Admins</option>
        </select>
        <button class="btn btn-outline btn-sm">Filter</button>
        <?php if ($search || $roleFilter): ?><a href="users.php" class="btn btn-ghost btn-sm">✕ Clear</a><?php endif; ?>
    </form>
    <span style="font-size:.85rem;color:var(--gray-400)"><?= number_format($total) ?> user<?= $total!=1?'s':'' ?></span>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Orders</th>
                    <th>Spent</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr class="no-results"><td colspan="9">No users found.</td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): $isMe = ($u['id'] == $currentUserId); ?>
                <tr>
                    <td style="color:var(--gray-400)">#<?= $u['id'] ?></td>
                    <td>
                        <strong><?= sanitize($u['name']) ?></strong>
                        <?php if ($isMe): ?><span style="font-size:.7rem;color:var(--primary)"> (you)</span><?php endif; ?>
                    </td>
                    <td style="font-size:.82rem"><?= sanitize($u['email']) ?></td>
                    <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td>
                        <?php $status = $u['status'] ?? 'active'; ?>
                        <span class="badge badge-<?= $status === 'disabled' ? 'disabled' : 'active' ?>"><?= ucfirst($status) ?></span>
                    </td>
                    <td><?= number_format($u['order_count']) ?></td>
                    <td><?= price($u['total_spent']) ?></td>
                    <td style="white-space:nowrap;font-size:.78rem"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if (!$isMe): ?>
                        <div class="btn-group">
                            <?php if (($u['status'] ?? 'active') === 'active'): ?>
                                <a href="?action=disable&id=<?= $u['id'] ?>" class="btn btn-warning btn-xs"
                                   onclick="return confirmAction(this.href,'Disable User','Disable <?= sanitize($u['name']) ?>?')">Disable</a>
                            <?php else: ?>
                                <a href="?action=enable&id=<?= $u['id'] ?>" class="btn btn-success btn-xs">Enable</a>
                            <?php endif; ?>

                            <?php if ($u['role'] === 'customer'): ?>
                                <a href="?action=make_admin&id=<?= $u['id'] ?>" class="btn btn-outline btn-xs"
                                   onclick="return confirmAction(this.href,'Promote to Admin','Give <?= sanitize($u['name']) ?> admin access?')">→ Admin</a>
                            <?php else: ?>
                                <a href="?action=make_customer&id=<?= $u['id'] ?>" class="btn btn-outline btn-xs"
                                   onclick="return confirmAction(this.href,'Demote User','Remove admin access?')">→ Customer</a>
                            <?php endif; ?>

                            <a href="?action=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-xs"
                               onclick="return confirmAction(this.href,'Delete User','Permanently delete <?= sanitize($u['name']) ?> and all their data?')">Delete</a>
                        </div>
                        <?php else: ?>
                            <span style="color:var(--gray-300);font-size:.78rem">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$base = SITE_URL . '/admin/users.php?' . http_build_query(array_filter(['role'=>$roleFilter,'search'=>$search]));
echo paginate($total, $perPage, $page, $base);
adminFooter();
?>
