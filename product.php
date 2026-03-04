<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$slug    = $_GET['slug'] ?? '';
$product = $slug ? getProduct($slug) : null;

if (!$product) {
    flash('Product not found.', 'danger');
    redirect('shop.php');
}

$pageTitle   = $product['name'];
$rating      = getProductRating($product['id']);
$inWish      = isLoggedIn() ? isInWishlist($_SESSION['user_id'], $product['id']) : false;
$imgUrl      = productImageUrl($product['image']);
$onSale      = !empty($product['sale_price']);
$displayPrice = $onSale ? $product['sale_price'] : $product['price'];
$discount    = $onSale ? round((1 - $product['sale_price']/$product['price'])*100) : 0;

// Related products
$related = $product['category_id'] ? getProducts(4, 0, $product['category_slug'] ?? null) : [];
$related = array_filter($related, fn($p) => $p['id'] !== $product['id']);

// Reviews
$stmt = db()->prepare("SELECT r.*, u.name AS reviewer_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->bind_param('i', $product['id']);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    requireLogin();
    $ratingVal = (int)$_POST['rating'];
    $comment   = sanitize($_POST['comment']);
    if ($ratingVal >= 1 && $ratingVal <= 5) {
        $ins = db()->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment)");
        $ins->bind_param('iiis', $product['id'], $_SESSION['user_id'], $ratingVal, $comment);
        $ins->execute();
        flash('Review submitted!', 'success');
        redirect("product.php?slug={$product['slug']}");
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/">Home</a> <span>/</span>
        <a href="<?= SITE_URL ?>/shop.php">Shop</a> <span>/</span>
        <?php if ($product['category_name']): ?>
            <a href="<?= SITE_URL ?>/shop.php?category=<?= $product['category_slug'] ?>"><?= sanitize($product['category_name']) ?></a>
            <span>/</span>
        <?php endif; ?>
        <span><?= sanitize($product['name']) ?></span>
    </div>

    <!-- Product Detail -->
    <div class="product-detail">
        <!-- Gallery -->
        <div class="product-gallery">
            <div class="main-image">
                <img id="mainProductImage" src="<?= $imgUrl ?>" alt="<?= sanitize($product['name']) ?>">
            </div>
        </div>

        <!-- Info -->
        <div class="product-detail-info">
            <?php if ($product['category_name']): ?>
                <div class="product-category"><?= sanitize($product['category_name']) ?></div>
            <?php endif; ?>
            <h1><?= sanitize($product['name']) ?></h1>

            <?php if ($rating['count'] > 0): ?>
            <div class="product-rating mt-1">
                <span class="stars"><?= generateStars(round($rating['avg_rating'])) ?></span>
                <span class="rating-count"><?= number_format($rating['avg_rating'], 1) ?> (<?= $rating['count'] ?> reviews)</span>
            </div>
            <?php endif; ?>

            <div class="product-detail-price">
                <?= price($displayPrice) ?>
                <?php if ($onSale): ?>
                    <span class="original"><?= price($product['price']) ?></span>
                    <span class="product-badge" style="position:static;display:inline-block"><?= $discount ?>% OFF</span>
                <?php endif; ?>
            </div>

            <div class="product-meta">
                <?php
                $stockClass = match(true) {
                    $product['stock'] <= 0   => 'out-stock',
                    $product['stock'] <= 5   => 'low-stock',
                    default                  => 'in-stock'
                };
                $stockLabel = match(true) {
                    $product['stock'] <= 0 => 'Out of Stock',
                    $product['stock'] <= 5 => "Only {$product['stock']} left!",
                    default                => 'In Stock'
                };
                ?>
                <span class="stock-badge <?= $stockClass ?>"><?= $stockLabel ?></span>
                <span style="margin-left:.75rem">SKU: #<?= str_pad($product['id'], 5, '0', STR_PAD_LEFT) ?></span>
            </div>

            <p style="color:#374151;line-height:1.7;margin:1rem 0"><?= nl2br(sanitize($product['description'])) ?></p>

            <?php if ($product['stock'] > 0): ?>
            <div class="qty-selector">
                <button class="qty-btn" data-action="minus">−</button>
                <input type="number" id="quantity" class="qty-input" value="1" min="1" max="<?= $product['stock'] ?>">
                <button class="qty-btn" data-action="plus">+</button>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button class="btn btn-primary btn-lg add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
                    🛒 Add to Cart
                </button>
                <button class="btn btn-outline wishlist-btn <?= $inWish ? 'active' : '' ?>" data-product-id="<?= $product['id'] ?>">
                    <?= $inWish ? '♥ Wishlisted' : '♡ Wishlist' ?>
                </button>
            </div>
            <?php else: ?>
                <button class="btn btn-lg" style="background:var(--light);color:var(--gray)" disabled>Out of Stock</button>
            <?php endif; ?>

            <hr class="divider">
            <div style="font-size:.875rem;color:var(--gray);display:flex;flex-direction:column;gap:.4rem">
                <span>✅ Free shipping on orders over <?= price(FREE_SHIPPING_THRESHOLD) ?></span>
                <span>🔄 30-day return policy</span>
                <span>🔒 Secure checkout</span>
            </div>
        </div>
    </div>

    <!-- Tabs: Description / Reviews -->
    <div class="tabs-container" style="margin-top:3rem">
        <div class="tabs">
            <button class="tab-btn active" data-tab="tab-desc">Description</button>
            <button class="tab-btn" data-tab="tab-reviews">Reviews (<?= count($reviews) ?>)</button>
        </div>

        <div id="tab-desc" class="tab-content active" style="background:var(--white);padding:1.5rem;border-radius:var(--radius-lg);box-shadow:var(--shadow)">
            <p style="line-height:1.8"><?= nl2br(sanitize($product['description'])) ?></p>
        </div>

        <div id="tab-reviews" class="tab-content" style="background:var(--white);padding:1.5rem;border-radius:var(--radius-lg);box-shadow:var(--shadow)">
            <?php if (empty($reviews)): ?>
                <p class="text-gray">No reviews yet. Be the first to review this product!</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <span class="stars"><?= generateStars($review['rating']) ?></span>
                        <strong class="reviewer-name"><?= sanitize($review['reviewer_name']) ?></strong>
                        <span class="review-date"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                    </div>
                    <p class="review-text"><?= sanitize($review['comment']) ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
            <hr class="divider">
            <h4 style="margin-bottom:1rem">Write a Review</h4>
            <form method="POST">
                <div class="form-group">
                    <label>Rating</label>
                    <div style="font-size:1.8rem;cursor:pointer">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star-select" data-val="<?= $i ?>" style="color:#d1d5db;transition:.1s">★</span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="0">
                </div>
                <div class="form-group">
                    <label>Comment</label>
                    <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience..."></textarea>
                </div>
                <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
            </form>
            <?php else: ?>
                <p class="text-gray mt-2"><a href="<?= SITE_URL ?>/login.php">Login</a> to write a review.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <div class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Related Products</h2>
            </div>
        </div>
        <div class="products-grid">
            <?php foreach ($related as $product): ?>
                <?php include 'includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Highlight stars on click for review form
document.querySelectorAll('.star-select').forEach(star => {
    star.style.cursor = 'pointer';
});
</script>

<?php require_once 'includes/footer.php'; ?>
