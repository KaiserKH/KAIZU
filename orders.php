<?php
$pageTitle = 'My Orders';
require_once 'includes/config.php';
require_once 'includes/functions.php';
requireLogin();

$user   = getCurrentUser();
$orders = getUserOrders($user['id']);

// View single order
$viewOrder = null;
if (isset($_GET['order'])) {
    $viewOrder = getOrderByNumber($_GET['order']);
    if ($viewOrder && $viewOrder['user_id'] !== $user['id']) $viewOrder = null;
}

require_once 'includes/header.php';
?>

<div class="container">
    <h1 class="section-title mb-3">My Orders</h1>

    <?php if ($viewOrder): ?>
        <!-- Order Detail -->
        <div style="margin-bottom:1rem">
            <a href="<?= SITE_URL ?>/orders.php" class="btn btn-outline btn-sm">← Back to Orders</a>
        </div>
        <div class="form-card">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem">
                <div>
                    <div style="color:var(--gray);font-size:.85rem">Order Number</div>
                    <div style="font-weight:700;font-size:1.1rem"><?= sanitize($viewOrder['order_number']) ?></div>
                </div>
                <div>
                    <div style="color:var(--gray);font-size:.85rem">Placed On</div>
                    <div><?= date('M j, Y', strtotime($viewOrder['created_at'])) ?></div>
                </div>
                <div>
                    <span class="status-badge status-<?= $viewOrder['status'] ?>"><?= ucfirst($viewOrder['status']) ?></span>
                </div>
            </div>

            <?php $items = getOrderItems($viewOrder['id']); ?>
            <?php foreach ($items as $item): ?>
            <div style="display:flex;align-items:center;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--border)">
                <img src="<?= productImageUrl($item['product_image']) ?>" alt="" style="width:55px;height:55px;object-fit:cover;border-radius:var(--radius)">
                <div style="flex:1">
                    <div style="font-weight:500"><?= sanitize($item['product_name']) ?></div>
                    <div style="color:var(--gray);font-size:.85rem">Qty: <?= $item['quantity'] ?> × <?= price($item['price']) ?></div>
                </div>
                <strong><?= price($item['subtotal']) ?></strong>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:1rem">
                <div class="summary-row"><span>Subtotal</span><span><?= price($viewOrder['subtotal']) ?></span></div>
                <div class="summary-row"><span>Shipping</span><span><?= price($viewOrder['shipping']) ?></span></div>
                <div class="summary-row"><span>Tax</span><span><?= price($viewOrder['tax']) ?></span></div>
                <div class="summary-row total"><span>Total</span><span><?= price($viewOrder['total']) ?></span></div>
            </div>
        </div>

    <?php elseif (empty($orders)): ?>
        <div class="empty-state">
            <div class="icon">📦</div>
            <h3>No orders yet</h3>
            <p>You haven't placed any orders yet.</p>
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= sanitize($order['order_number']) ?></strong></td>
                    <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                    <td>
                        <?php
                        $items = getOrderItems($order['id']);
                        echo count($items) . ' item' . (count($items) !== 1 ? 's' : '');
                        ?>
                    </td>
                    <td><strong><?= price($order['total']) ?></strong></td>
                    <td><span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                    <td><a href="?order=<?= urlencode($order['order_number']) ?>" class="btn btn-outline btn-sm">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
