<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$orderNumber = sanitize($_GET['order'] ?? '');
$order       = $orderNumber ? getOrderByNumber($orderNumber) : null;

if (!$order) {
    flash('Order not found.', 'danger');
    redirect('index.php');
}

$pageTitle  = 'Order Confirmed!';
$orderItems = getOrderItems($order['id']);
require_once 'includes/header.php';
?>

<div class="container" style="max-width:700px">
    <div style="text-align:center;padding:2rem 0">
        <div style="font-size:4rem">🎉</div>
        <h1 style="font-size:2rem;font-weight:700;margin:.5rem 0">Order Confirmed!</h1>
        <p style="color:var(--gray)">Thank you for your purchase. We'll send a confirmation email shortly.</p>
    </div>

    <div class="form-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
            <div>
                <div style="font-size:.85rem;color:var(--gray)">Order Number</div>
                <div style="font-weight:700;font-size:1.1rem"><?= sanitize($order['order_number']) ?></div>
            </div>
            <div>
                <div style="font-size:.85rem;color:var(--gray)">Date</div>
                <div><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
            </div>
            <div>
                <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
            </div>
        </div>
    </div>

    <div class="form-card" style="margin-top:1rem">
        <h3 style="margin-bottom:1rem">Items Ordered</h3>
        <?php foreach ($orderItems as $item): ?>
        <div style="display:flex;align-items:center;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--border)">
            <?php if ($item['product_image']): ?>
                <img src="<?= productImageUrl($item['product_image']) ?>" alt="" style="width:55px;height:55px;object-fit:cover;border-radius:var(--radius)">
            <?php endif; ?>
            <div style="flex:1">
                <div style="font-weight:500"><?= sanitize($item['product_name']) ?></div>
                <div style="color:var(--gray);font-size:.85rem">Qty: <?= $item['quantity'] ?> × <?= price($item['price']) ?></div>
            </div>
            <div style="font-weight:700"><?= price($item['subtotal']) ?></div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:1rem">
            <div class="summary-row"><span>Subtotal</span><span><?= price($order['subtotal']) ?></span></div>
            <div class="summary-row"><span>Shipping</span><span><?= $order['shipping'] == 0 ? '<span class="free">FREE</span>' : price($order['shipping']) ?></span></div>
            <div class="summary-row"><span>Tax</span><span><?= price($order['tax']) ?></span></div>
            <div class="summary-row total"><span>Total</span><span><?= price($order['total']) ?></span></div>
        </div>
    </div>

    <div class="form-card" style="margin-top:1rem">
        <h3 style="margin-bottom:.75rem">Shipping To</h3>
        <p><?= sanitize($order['shipping_name']) ?><br>
        <?= sanitize($order['shipping_address']) ?><br>
        <?= sanitize($order['shipping_city']) ?>, <?= sanitize($order['shipping_state']) ?> <?= sanitize($order['shipping_zip']) ?><br>
        <?= sanitize($order['shipping_country']) ?></p>
    </div>

    <div style="display:flex;gap:1rem;justify-content:center;margin-top:2rem;flex-wrap:wrap">
        <?php if (isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/orders.php" class="btn btn-outline">View My Orders</a>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Continue Shopping</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
