<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once 'admin-header.php';

// Stats
$totalOrders    = db()->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
$totalRevenue   = db()->query("SELECT COALESCE(SUM(total),0) AS s FROM orders WHERE status != 'cancelled'")->fetch_assoc()['s'];
$totalProducts  = db()->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$totalUsers     = db()->query("SELECT COUNT(*) AS c FROM users WHERE role='customer'")->fetch_assoc()['c'];
$pendingOrders  = db()->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
$todayRevenue   = db()->query("SELECT COALESCE(SUM(total),0) AS s FROM orders WHERE DATE(created_at)=CURDATE() AND status!='cancelled'")->fetch_assoc()['s'];
$monthRevenue   = db()->query("SELECT COALESCE(SUM(total),0) AS s FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status!='cancelled'")->fetch_assoc()['s'];
$outOfStock     = db()->query("SELECT COUNT(*) AS c FROM products WHERE stock=0 AND status='active'")->fetch_assoc()['c'];

// Recent orders (10)
$recentOrders = db()->query("
    SELECT o.*, u.name AS customer_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Low stock (≤10)
$lowStock = db()->query("
    SELECT * FROM products
    WHERE stock > 0 AND stock <= 10 AND status='active'
    ORDER BY stock ASC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Monthly revenue for mini bar chart (last 7 days)
$dailyRevenue = db()->query("
    SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS rev
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetch_all(MYSQLI_ASSOC);

// Build 7-day map
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = 0;
}
foreach ($dailyRevenue as $row) {
    $days[$row['day']] = (float)$row['rev'];
}
$maxDay = max($days) ?: 1;

adminHeader('Dashboard', 'dashboard');
?>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon">🛒</div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($totalOrders) ?></div>
            <div class="stat-label">Total Orders</div>
            <?php if ($pendingOrders > 0): ?>
            <div class="stat-delta up">⚠ <?= $pendingOrders ?> pending</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">💰</div>
        <div class="stat-body">
            <div class="stat-value"><?= price($totalRevenue) ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-delta up">📅 <?= price($monthRevenue) ?> this month</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">📦</div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-label">Products</div>
            <?php if ($outOfStock > 0): ?>
            <div class="stat-delta down">⚠ <?= $outOfStock ?> out of stock</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon">👥</div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-label">Customers</div>
        </div>
    </div>
    <div class="stat-card green" style="border-top-color:#0891b2">
        <div class="stat-icon" style="background:#e0f2fe">📈</div>
        <div class="stat-body">
            <div class="stat-value" style="color:#0891b2"><?= price($todayRevenue) ?></div>
            <div class="stat-label">Today's Revenue</div>
        </div>
    </div>
</div>

<!-- 7-Day Revenue Trend -->
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <span class="card-title">📈 Revenue — Last 7 Days</span>
        <a href="orders.php" class="btn btn-outline btn-sm">All Orders →</a>
    </div>
    <div class="card-body">
        <div style="display:flex;align-items:flex-end;gap:6px;height:80px">
            <?php foreach ($days as $date => $rev): ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                <div title="<?= date('M j', strtotime($date)) ?>: <?= price($rev) ?>"
                     style="width:100%;background:<?= $rev>0?'#2563eb':'#e5e7eb' ?>;
                            height:<?= $maxDay>0 ? round(($rev/$maxDay)*60)+8 : 8 ?>px;
                            border-radius:4px 4px 0 0;transition:all .2s;cursor:pointer"
                     onmouseover="this.style.background='#1d4ed8'"
                     onmouseout="this.style.background='<?= $rev>0?'#2563eb':'#e5e7eb' ?>'">
                </div>
                <span style="font-size:.65rem;color:#9ca3af;white-space:nowrap"><?= date('M j', strtotime($date)) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Orders</span>
            <a href="orders.php" class="btn btn-outline btn-sm">View All →</a>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr class="no-results"><td colspan="6">No orders yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><strong><?= sanitize($order['order_number']) ?></strong></td>
                        <td><?= sanitize($order['shipping_name'] ?: ($order['customer_name'] ?? 'Guest')) ?></td>
                        <td><?= price($order['total']) ?></td>
                        <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td style="white-space:nowrap"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                        <td><a href="orders.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-xs">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚠ Low Stock</span>
            <a href="products.php" class="btn btn-outline btn-sm">Manage →</a>
        </div>
        <div class="card-body" style="padding-top:.5rem">
            <?php if (empty($lowStock) && $outOfStock === 0): ?>
                <p style="color:var(--gray-400);text-align:center;padding:1rem">All products well stocked ✓</p>
            <?php else: ?>
                <?php foreach ($lowStock as $p): ?>
                <div class="stock-item">
                    <a href="products.php?edit=<?= $p['id'] ?>" class="stock-item-name" title="<?= sanitize($p['name']) ?>">
                        <?= sanitize($p['name']) ?>
                    </a>
                    <span class="stock-item-qty <?= $p['stock'] <= 3 ? 'low' : 'medium' ?>"><?= $p['stock'] ?> left</span>
                </div>
                <?php endforeach; ?>
                <?php if ($outOfStock > 0): ?>
                <div class="stock-item">
                    <span class="stock-item-name" style="color:#dc2626">+ <?= $outOfStock ?> out of stock</span>
                    <a href="products.php?stock=0" class="btn btn-xs btn-danger">Fix</a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php adminFooter(); ?>
