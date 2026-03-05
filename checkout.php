<?php
$pageTitle = 'Checkout';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$totals = getCartTotals();
if (empty($totals['items'])) {
    flash('Your cart is empty.', 'warning');
    redirect('cart.php');
}

$user   = getCurrentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['name','email','phone','address','city','state','zip','country','payment_method'];
    $data   = [];
    foreach ($fields as $f) {
        $data[$f] = trim($_POST[$f] ?? '');
    }
    $data['notes'] = trim($_POST['notes'] ?? '');

    // Validate
    if (!$data['name'])           $errors['name']    = 'Name is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email required.';
    if (!$data['address'])        $errors['address'] = 'Address is required.';
    if (!$data['city'])           $errors['city']    = 'City is required.';
    if (!$data['zip'])            $errors['zip']     = 'ZIP code is required.';
    if (!$data['country'])        $errors['country'] = 'Country is required.';

    if (empty($errors)) {
        $userId      = isLoggedIn() ? $_SESSION['user_id'] : null;
        $orderNumber = createOrder($data, $userId);
        if ($orderNumber) {
            flash("Order placed successfully! Order #$orderNumber", 'success');
            redirect("order-success.php?order=$orderNumber");
        } else {
            $errors['general'] = 'Failed to place order. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/">Home</a> <span>/</span>
        <a href="<?= SITE_URL ?>/cart.php">Cart</a> <span>/</span>
        <span>Checkout</span>
    </div>
    <h1 class="section-title mb-3">Checkout</h1>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= $errors['general'] ?></div>
    <?php endif; ?>

    <form method="POST" class="checkout-layout">
        <!-- Shipping Form -->
        <div>
            <div class="form-card">
                <h2>Shipping Information</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span>*</span></label>
                        <input type="text" name="name" class="form-control <?= isset($errors['name'])?'error':'' ?>"
                               value="<?= sanitize($_POST['name'] ?? ($user['name'] ?? '')) ?>">
                        <?php if (isset($errors['name'])): ?><div class="form-error"><?= $errors['name'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Email <span>*</span></label>
                        <input type="email" name="email" class="form-control <?= isset($errors['email'])?'error':'' ?>"
                               value="<?= sanitize($_POST['email'] ?? ($user['email'] ?? '')) ?>">
                        <?php if (isset($errors['email'])): ?><div class="form-error"><?= $errors['email'] ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? ($user['phone'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>Address <span>*</span></label>
                    <textarea name="address" class="form-control <?= isset($errors['address'])?'error':'' ?>" rows="2"><?= sanitize($_POST['address'] ?? ($user['address'] ?? '')) ?></textarea>
                    <?php if (isset($errors['address'])): ?><div class="form-error"><?= $errors['address'] ?></div><?php endif; ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City <span>*</span></label>
                        <input type="text" name="city" class="form-control <?= isset($errors['city'])?'error':'' ?>"
                               value="<?= sanitize($_POST['city'] ?? ($user['city'] ?? '')) ?>">
                        <?php if (isset($errors['city'])): ?><div class="form-error"><?= $errors['city'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>State / Province</label>
                        <input type="text" name="state" class="form-control" value="<?= sanitize($_POST['state'] ?? ($user['state'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ZIP / Postal Code <span>*</span></label>
                        <input type="text" name="zip" class="form-control <?= isset($errors['zip'])?'error':'' ?>"
                               value="<?= sanitize($_POST['zip'] ?? ($user['zip'] ?? '')) ?>">
                        <?php if (isset($errors['zip'])): ?><div class="form-error"><?= $errors['zip'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Country <span>*</span></label>
                        <select name="country" class="form-control">
                            <?php foreach (['US'=>'United States','GB'=>'United Kingdom','CA'=>'Canada','AU'=>'Australia','IN'=>'India','PK'=>'Pakistan','Other'=>'Other'] as $code => $name): ?>
                                <option value="<?= $code ?>" <?= ($_POST['country'] ?? ($user['country'] ?? 'US')) === $code ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Order Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Special delivery instructions..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-card" style="margin-top:1.5rem">
                <h2>Payment Method</h2>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="payment_method" value="cod" <?= ($_POST['payment_method'] ?? 'cod') === 'cod' ? 'checked' : '' ?>>
                        <span>💵 Cash on Delivery</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="payment_method" value="bank" <?= ($_POST['payment_method'] ?? '') === 'bank' ? 'checked' : '' ?>>
                        <span>🏦 Bank Transfer</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="payment_method" value="card" <?= ($_POST['payment_method'] ?? '') === 'card' ? 'checked' : '' ?>>
                        <span>💳 Credit / Debit Card (Demo)</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div>
            <div class="order-summary">
                <h3>Your Order</h3>
                <?php foreach ($totals['items'] as $item): ?>
                <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--border)">
                    <img src="<?= productImageUrl($item['image']) ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:var(--radius)">
                    <div style="flex:1;font-size:.875rem">
                        <div style="font-weight:500"><?= sanitize($item['name']) ?></div>
                        <div style="color:var(--gray)">Qty: <?= $item['quantity'] ?></div>
                    </div>
                    <div style="font-weight:600"><?= price($item['unit_price'] * $item['quantity']) ?></div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:1rem">
                    <div class="summary-row"><span>Subtotal</span><span><?= price($totals['subtotal']) ?></span></div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?= $totals['shipping'] == 0 ? '<span class="free">FREE</span>' : price($totals['shipping']) ?></span>
                    </div>
                    <div class="summary-row"><span>Tax</span><span><?= price($totals['tax']) ?></span></div>
                    <div class="summary-row total"><span>Total</span><span><?= price($totals['total']) ?></span></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:1rem">
                    Place Order 🎉
                </button>
                <div style="text-align:center;font-size:.8rem;color:var(--gray);margin-top:.5rem">🔒 Your payment info is secure</div>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
