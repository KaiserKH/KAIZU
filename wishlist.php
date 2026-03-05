<?php
$pageTitle = 'Wishlist';
require_once 'includes/config.php';
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$stmt   = db()->prepare("SELECT p.*, c.name AS category_name FROM wishlist w JOIN products p ON w.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE w.user_id = ? ORDER BY w.created_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="container">
    <h1 class="section-title mb-3">My Wishlist
        <span style="font-size:1rem;color:var(--gray);font-weight:400">(<?= count($products) ?> items)</span>
    </h1>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="icon">♡</div>
            <h3>Your wishlist is empty</h3>
            <p>Save items you love for later.</p>
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Browse Products</a>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <?php include 'includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
