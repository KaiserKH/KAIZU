<?php
$pageTitle = 'Shopping Cart';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$totals = getCartTotals();
$items  = $totals['items'];
?>

<div class="container">
    <h1 class="section-title" style="margin-bottom:1.5rem">Shopping Cart
        <span style="font-size:1rem;color:var(--gray);font-weight:400">(<?= count($items) ?> item<?= count($items)!==1?'s':'' ?>)</span>
    </h1>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Looks like you haven't added anything yet.</p>
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary btn-lg">Start Shopping</a>
        </div>
    <?php else: ?>
    <div class="cart-layout">
        <!-- Cart Items -->
        <div>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr data-row-id="<?= $item['id'] ?>">
                        <td data-label="Product">
                            <div class="cart-product">
                                <img src="<?= productImageUrl($item['image']) ?>" alt="<?= sanitize($item['name']) ?>">
                                <div>
                                    <a href="<?= SITE_URL ?>/product.php?slug=<?= $item['slug'] ?>" class="cart-product-name">
                                        <?= sanitize($item['name']) ?>
                                    </a>
                                    <?php if ($item['stock'] < $item['quantity']): ?>
                                        <div style="color:var(--danger);font-size:.8rem">Only <?= $item['stock'] ?> available</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td data-label="Price"><?= price($item['unit_price']) ?></td>
                        <td data-label="Quantity">
                            <div class="cart-qty">
                                <input type="number" class="cart-qty-input" value="<?= $item['quantity'] ?>"
                                       min="1" max="<?= $item['stock'] ?>"
                                       data-cart-id="<?= $item['id'] ?>" style="width:65px;padding:.35rem;border:1px solid var(--border);border-radius:var(--radius);text-align:center">
                            </div>
                        </td>
                        <td data-label="Subtotal"><strong><?= price($item['unit_price'] * $item['quantity']) ?></strong></td>
                        <td>
                            <button class="remove-btn remove-item-btn" data-cart-id="<?= $item['id'] ?>" title="Remove">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display:flex;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:.5rem">
                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-outline">← Continue Shopping</a>
                <a href="<?= SITE_URL ?>/api/cart.php?action=clear" class="btn btn-danger btn-sm" onclick="return confirm('Clear cart?')">Clear Cart</a>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="order-summary">
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="summary-subtotal"><?= price($totals['subtotal']) ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span id="summary-shipping">
                    <?php if ($totals['shipping'] == 0): ?>
                        <span class="free">FREE</span>
                    <?php else: ?>
                        <?= price($totals['shipping']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="summary-row">
                <span>Tax (<?= TAX_RATE * 100 ?>%)</span>
                <span id="summary-tax"><?= price($totals['tax']) ?></span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span id="summary-total"><?= price($totals['total']) ?></span>
            </div>

            <?php if ($totals['subtotal'] < FREE_SHIPPING_THRESHOLD && $totals['subtotal'] > 0): ?>
            <div style="background:#eff6ff;border-radius:var(--radius);padding:.75rem;font-size:.85rem;margin-bottom:1rem;color:#1e40af">
                Add <?= price(FREE_SHIPPING_THRESHOLD - $totals['subtotal']) ?> more for <strong>free shipping!</strong>
            </div>
            <?php endif; ?>

            <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary btn-block btn-lg">Proceed to Checkout →</a>
            <div style="text-align:center;margin-top:.75rem;font-size:.8rem;color:var(--gray)">🔒 Secure checkout</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
