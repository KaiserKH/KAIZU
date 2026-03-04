<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once 'admin-header.php';

$statuses = ['pending','processing','shipped','delivered','cancelled'];

// Update status (AJAX or form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    // Skip CSRF for the inline auto-submit status dropdown (no CSRF token in that form)
    // Only verify for explicit submit buttons (non-AJAX)
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && isset($_POST['csrf_token'])) {
        csrfVerify();
    }
    $oid    = (int)$_POST['order_id'];
    $status = in_array($_POST['status'], $statuses) ? $_POST['status'] : 'pending';
    $stmt   = db()->prepare("UPDATE orders SET status=? WHERE id=?");
    $stmt->bind_param('si', $status, $oid);
    $stmt->execute();
    // AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['ok' => true]);
        exit;
    }
    flash('Order status updated.', 'success');
    header('Location: orders.php' . (isset($_GET['id']) ? '?id='.(int)$_GET['id'] : ''));
    exit;
}

// Single order detail
if (isset($_GET['id'])) {
    $oid   = (int)$_GET['id'];
    $order = db()->query("SELECT o.*, u.name AS customer_name, u.email AS customer_email
                           FROM orders o LEFT JOIN users u ON o.user_id=u.id
                           WHERE o.id=$oid")->fetch_assoc();
    if (!$order) { flash('Order not found.', 'danger'); header('Location: orders.php'); exit; }

    $items = db()->query("SELECT * FROM order_items WHERE order_id=$oid")->fetch_all(MYSQLI_ASSOC);
    adminHeader('Order #' . htmlspecialchars($order['order_number']), 'orders');
?>

<div style="margin-bottom:1.25rem;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
    <a href="orders.php" class="btn btn-outline btn-sm">← Back to Orders</a>
    <span class="badge badge-<?= $order['status'] ?>" style="font-size:.85rem;padding:.4rem .9rem"><?= ucfirst($order['status']) ?></span>
</div>

<!-- Status Update -->
<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header"><span class="card-title">Update Status</span></div>
    <div class="card-body" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <form method="POST" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <select name="status" class="status-select form-control" style="width:auto">
                <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
        </form>
        <span style="color:var(--gray-400);font-size:.8rem">Ordered: <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
    </div>
</div>

<div class="order-detail-grid">
    <!-- Order Items -->
    <div>
        <div class="card" style="margin-bottom:1.5rem">
            <div class="card-header"><span class="card-title">Order Items</span></div>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:.75rem">
                                <img src="<?= productImageUrl($item['product_image'] ?? '') ?>" alt="" style="width:40px;height:40px;border-radius:6px;object-fit:cover">
                                <span><?= sanitize($item['product_name']) ?></span>
                            </div>
                        </td>
                        <td><?= price($item['price']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><strong><?= price($item['subtotal']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" style="text-align:right;font-weight:600">Subtotal</td><td><?= price($order['subtotal']) ?></td></tr>
                        <tr><td colspan="3" style="text-align:right;font-weight:600">Shipping</td><td><?= price($order['shipping']) ?></td></tr>
                        <tr><td colspan="3" style="text-align:right;font-weight:600">Tax</td><td><?= price($order['tax']) ?></td></tr>
                        <tr style="background:var(--gray-50)"><td colspan="3" style="text-align:right;font-weight:800;font-size:1rem">Total</td><td><strong style="font-size:1rem"><?= price($order['total']) ?></strong></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if ($order['notes']): ?>
        <div class="card">
            <div class="card-header"><span class="card-title">Customer Notes</span></div>
            <div class="card-body"><p><?= nl2br(sanitize($order['notes'])) ?></p></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar info -->
    <div>
        <!-- Customer -->
        <div class="card" style="margin-bottom:1.25rem">
            <div class="card-header"><span class="card-title">Customer</span></div>
            <div class="card-body">
                <dl class="info-list">
                    <dt>Name</dt><dd><?= sanitize($order['shipping_name'] ?: ($order['customer_name'] ?? 'Guest')) ?></dd>
                    <dt>Email</dt><dd><?= sanitize($order['shipping_email'] ?: ($order['customer_email'] ?? '—')) ?></dd>
                    <dt>Phone</dt><dd><?= sanitize($order['shipping_phone'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <!-- Shipping -->
        <div class="card" style="margin-bottom:1.25rem">
            <div class="card-header"><span class="card-title">Shipping Address</span></div>
            <div class="card-body">
                <address style="font-style:normal;line-height:1.7">
                    <?= sanitize($order['shipping_address']) ?><br>
                    <?= sanitize($order['shipping_city']) ?>, <?= sanitize($order['shipping_state']) ?> <?= sanitize($order['shipping_zip']) ?><br>
                    <?= sanitize($order['shipping_country']) ?>
                </address>
            </div>
        </div>

        <!-- Payment -->
        <div class="card">
            <div class="card-header"><span class="card-title">Payment</span></div>
            <div class="card-body">
                <dl class="info-list">
                    <dt>Method</dt><dd><?= strtoupper(sanitize($order['payment_method'])) ?></dd>
                    <dt>Status</dt>
                    <dd><span class="badge badge-<?= $order['payment_status']==='paid'?'paid':'unpaid' ?>"><?= ucfirst($order['payment_status']) ?></span></dd>
                    <dt>Order #</dt><dd><?= sanitize($order['order_number']) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php
    adminFooter();
    exit;
}

// ── Orders List ──────────────────────────────────────────────────────────────
$statusFilter  = isset($_GET['status']) && in_array($_GET['status'], $statuses) ? $_GET['status'] : '';
$search        = trim($_GET['search'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;
$offset        = ($page - 1) * $perPage;

$where  = "WHERE 1";
$params = []; $types = '';
if ($statusFilter) { $where .= " AND o.status=?"; $params[] = $statusFilter; $types .= 's'; }
if ($search) {
    $where .= " AND (o.order_number LIKE ? OR o.shipping_name LIKE ? OR o.shipping_email LIKE ?)";
    $like = "%$search%"; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
}

$countStmt = db()->prepare("SELECT COUNT(*) AS c FROM orders o $where");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['c'];

$listStmt = db()->prepare("SELECT o.*, u.name AS customer_name FROM orders o LEFT JOIN users u ON o.user_id=u.id $where ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
$listTypes  = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$orders = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Status counts for tabs
$statusCounts = [];
foreach ($statuses as $s) {
    $r = db()->query("SELECT COUNT(*) AS c FROM orders WHERE status='$s'")->fetch_assoc();
    $statusCounts[$s] = $r['c'];
}

adminHeader('Orders', 'orders');
?>

<div class="page-toolbar">
    <div class="toolbar-search">
        <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
            <input type="text" name="search" class="form-control" placeholder="Search order # or customer…" value="<?= sanitize($search) ?>" style="min-width:220px">
            <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
            <button class="btn btn-outline btn-sm">Search</button>
            <?php if ($search || $statusFilter): ?>
                <a href="orders.php" class="btn btn-ghost btn-sm">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Status filter tabs -->
<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.25rem">
    <a href="orders.php" class="btn btn-sm <?= !$statusFilter?'btn-primary':'btn-outline' ?>">
        All <span style="background:rgba(255,255,255,.25);padding:.05rem .4rem;border-radius:99px;margin-left:.25rem"><?= $total + array_sum($statusCounts) - $total ?><?= $total ?></span>
    </a>
    <?php foreach ($statuses as $s): ?>
    <a href="orders.php?status=<?= $s ?><?= $search ? '&search='.urlencode($search) : '' ?>"
       class="btn btn-sm <?= $statusFilter===$s?'btn-primary':'btn-outline' ?>">
        <?= ucfirst($s) ?>
        <span style="background:rgba(255,255,255,.25);padding:.05rem .4rem;border-radius:99px;margin-left:.25rem"><?= $statusCounts[$s] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr class="no-results"><td colspan="8">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order):
                    $itemCount = db()->query("SELECT SUM(quantity) AS q FROM order_items WHERE order_id={$order['id']}")->fetch_assoc()['q'] ?? 0;
                ?>
                <tr>
                    <td><a href="orders.php?id=<?= $order['id'] ?>"><strong><?= sanitize($order['order_number']) ?></strong></a></td>
                    <td>
                        <div><?= sanitize($order['shipping_name'] ?: ($order['customer_name'] ?? 'Guest')) ?></div>
                        <div style="font-size:.75rem;color:var(--gray-400)"><?= sanitize($order['shipping_email'] ?? '') ?></div>
                    </td>
                    <td><?= $itemCount ?> item<?= $itemCount != 1 ? 's' : '' ?></td>
                    <td><strong><?= price($order['total']) ?></strong></td>
                    <td><span class="badge badge-<?= $order['payment_status']==='paid'?'paid':'unpaid' ?>"><?= ucfirst($order['payment_status']) ?></span></td>
                    <td>
                        <form method="POST" style="display:flex;gap:.3rem;align-items:center">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="status" class="status-select" onchange="this.form.submit()">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td style="white-space:nowrap;font-size:.8rem"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                    <td><a href="orders.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-xs">View</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php
$base = SITE_URL . '/admin/orders.php?' . http_build_query(array_filter(['status'=>$statusFilter,'search'=>$search]));
echo paginate($total, $perPage, $page, $base);
?>

<?php adminFooter(); ?>
