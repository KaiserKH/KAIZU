<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once 'admin-header.php';

// Delete all logs (with CSRF)
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    csrfVerify();
    db()->query("DELETE FROM activity_logs");
    logActivity('logs_cleared', $_SESSION['user_id'], 'Admin cleared all activity logs');
    flash('Activity logs cleared.', 'success');
    header('Location: logs.php'); exit;
}

$actionFilter = trim($_GET['action'] ?? '');
$userFilter   = (int)($_GET['user_id'] ?? 0);
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

// Build query dynamically
$where  = 'WHERE 1';
$params = []; $types = '';
if ($actionFilter) { $where .= ' AND l.action = ?';           $params[] = $actionFilter; $types .= 's'; }
if ($userFilter)   { $where .= ' AND l.user_id = ?';          $params[] = $userFilter;   $types .= 'i'; }
if ($search)       { $where .= ' AND (l.details LIKE ? OR l.ip_address LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $like = "%$search%"; $params = array_merge($params, [$like,$like,$like,$like]); $types .= 'ssss'; }

$countStmt = db()->prepare("SELECT COUNT(*) AS c FROM activity_logs l LEFT JOIN users u ON l.user_id=u.id $where");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['c'];

$params[] = $perPage; $params[] = $offset; $types .= 'ii';
$listStmt = db()->prepare(
    "SELECT l.*, u.name AS user_name, u.email AS user_email
     FROM activity_logs l
     LEFT JOIN users u ON l.user_id = u.id
     $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?"
);
$listStmt->bind_param($types, ...$params);
$listStmt->execute();
$logs = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary stats
$stats = db()->query("
    SELECT action, COUNT(*) AS cnt
    FROM activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY action ORDER BY cnt DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// All distinct actions for filter dropdown
$allActions = db()->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetch_all(MYSQLI_ASSOC);

// Action badge color map
function actionBadgeClass($action) {
    return match(true) {
        str_contains($action, 'failed') || str_contains($action, 'denied') || str_contains($action, 'blocked') => 'badge-cancelled',
        str_contains($action, 'login')   => 'badge-delivered',
        str_contains($action, 'logout')  => 'badge-processing',
        str_contains($action, 'delete')  => 'badge-cancelled',
        str_contains($action, 'admin')   => 'badge-admin',
        str_contains($action, 'register')=> 'badge-shipped',
        default                           => 'badge-inactive',
    };
}

adminHeader('Activity Logs', 'logs');
?>

<!-- 24h Summary Cards -->
<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem">
    <?php foreach ($stats as $stat): ?>
    <div style="background:var(--white);border-radius:10px;padding:.75rem 1.1rem;box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:.6rem">
        <span class="badge <?= actionBadgeClass($stat['action']) ?>"><?= htmlspecialchars($stat['action']) ?></span>
        <strong><?= number_format($stat['cnt']) ?></strong>
        <span style="font-size:.72rem;color:var(--gray-400)">last 24h</span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($stats)): ?>
    <p style="color:var(--gray-400);font-size:.875rem">No activity in the last 24 hours.</p>
    <?php endif; ?>
</div>

<!-- Toolbar -->
<div class="page-toolbar" style="margin-bottom:1.25rem">
    <form method="GET" class="toolbar-search" style="flex-wrap:wrap;gap:.5rem">
        <input type="text" name="search" class="form-control" placeholder="Search IP, user, details…"
               value="<?= htmlspecialchars($search) ?>" style="min-width:200px">
        <select name="action" class="form-control" style="width:auto">
            <option value="">All Actions</option>
            <?php foreach ($allActions as $a): ?>
            <option value="<?= htmlspecialchars($a['action']) ?>" <?= $actionFilter===$a['action']?'selected':'' ?>>
                <?= htmlspecialchars($a['action']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm">Filter</button>
        <?php if ($search || $actionFilter || $userFilter): ?>
        <a href="logs.php" class="btn btn-ghost btn-sm">✕ Clear</a>
        <?php endif; ?>
    </form>
    <a href="?clear=1&csrf_token=<?= csrfToken() ?>" class="btn btn-danger btn-sm"
       onclick="return confirmAction(this.href,'Clear All Logs','This will permanently delete ALL <?= number_format($total) ?> log entries.')">
       🗑 Clear Logs
    </a>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Activity Log</span>
        <span style="font-size:.85rem;color:var(--gray-400)"><?= number_format($total) ?> entries</span>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Time</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr class="no-results"><td colspan="6">No log entries found.</td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="color:var(--gray-400);font-size:.75rem"><?= $log['id'] ?></td>
                    <td style="white-space:nowrap;font-size:.78rem">
                        <?= date('M j, Y', strtotime($log['created_at'])) ?>
                        <div style="color:var(--gray-400)"><?= date('g:i:s A', strtotime($log['created_at'])) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= actionBadgeClass($log['action']) ?>">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($log['user_name']): ?>
                            <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($log['user_name']) ?></div>
                            <div style="font-size:.75rem;color:var(--gray-400)"><?= htmlspecialchars($log['user_email'] ?? '') ?></div>
                        <?php else: ?>
                            <span style="color:var(--gray-300);font-size:.82rem">Guest / Unknown</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code style="font-size:.78rem;background:var(--gray-100);padding:.15rem .45rem;border-radius:4px">
                            <?= htmlspecialchars($log['ip_address']) ?>
                        </code>
                    </td>
                    <td style="font-size:.82rem;color:var(--gray-600);max-width:260px">
                        <?= htmlspecialchars($log['details'] ?: '—') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$base = SITE_URL . '/admin/logs.php?' . http_build_query(array_filter(['action'=>$actionFilter,'search'=>$search,'user_id'=>$userFilter?:null]));
echo paginate($total, $perPage, $page, $base);
adminFooter();
?>
