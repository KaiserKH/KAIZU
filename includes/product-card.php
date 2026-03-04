<?php
// Reusable product card — expects $product array in scope
$rating  = getProductRating($product['id']);
$inWish  = isLoggedIn() ? isInWishlist($_SESSION['user_id'], $product['id']) : false;
$imgUrl  = productImageUrl($product['image']);
$onSale  = !empty($product['sale_price']);
$price   = $onSale ? $product['sale_price'] : $product['price'];
?>
<div class="product-card">
    <div class="product-image">
        <a href="<?= SITE_URL ?>/product.php?slug=<?= $product['slug'] ?>">
            <img src="<?= $imgUrl ?>" alt="<?= sanitize($product['name']) ?>" loading="lazy">
        </a>
        <?php if ($onSale): ?>
            <span class="product-badge">SALE</span>
        <?php elseif ($product['featured']): ?>
            <span class="product-badge featured">FEATURED</span>
        <?php endif; ?>
        <button class="wishlist-btn <?= $inWish ? 'active' : '' ?>"
                data-product-id="<?= $product['id'] ?>"
                title="<?= $inWish ? 'Remove from wishlist' : 'Add to wishlist' ?>">
            <?= $inWish ? '♥' : '♡' ?>
        </button>
    </div>
    <div class="product-info">
        <?php if (!empty($product['category_name'])): ?>
            <div class="product-category"><?= sanitize($product['category_name']) ?></div>
        <?php endif; ?>
        <div class="product-name">
            <a href="<?= SITE_URL ?>/product.php?slug=<?= $product['slug'] ?>"><?= sanitize($product['name']) ?></a>
        </div>
        <?php if ($rating['count'] > 0): ?>
        <div class="product-rating">
            <span class="stars"><?= generateStars(round($rating['avg_rating'])) ?></span>
            <span class="rating-count">(<?= $rating['count'] ?>)</span>
        </div>
        <?php endif; ?>
        <div class="product-price">
            <span class="price-current"><?= price($price) ?></span>
            <?php if ($onSale): ?>
                <span class="price-original"><?= price($product['price']) ?></span>
            <?php endif; ?>
        </div>
        <div class="product-actions">
            <?php if ($product['stock'] > 0): ?>
                <button class="btn btn-primary btn-sm add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
                    🛒 Add to Cart
                </button>
            <?php else: ?>
                <button class="btn btn-sm" style="background:var(--light);color:var(--gray)" disabled>Out of Stock</button>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/product.php?slug=<?= $product['slug'] ?>" class="btn btn-outline btn-sm">View</a>
        </div>
    </div>
</div>
