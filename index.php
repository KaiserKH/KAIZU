<?php
$pageTitle = 'Home';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$featured  = getProducts(8, 0, null, null, true);
$newArrivals = getProducts(8, 0);
$categories  = getCategories();
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Discover Amazing Products</h1>
        <p>Shop the latest trends at unbeatable prices. Free shipping on orders over <?= price(FREE_SHIPPING_THRESHOLD) ?></p>
        <div class="hero-actions">
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-white btn-lg">Shop Now</a>
            <a href="<?= SITE_URL ?>/shop.php?category=electronics" class="btn btn-outline" style="border-color:white;color:white">Electronics</a>
        </div>
    </div>
</section>

<!-- Categories -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Shop by Category</h2>
                <p class="section-subtitle">Browse our wide range of product categories</p>
            </div>
        </div>
        <div class="products-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr))">
            <?php foreach ($categories as $cat): ?>
            <a href="<?= SITE_URL ?>/shop.php?category=<?= $cat['slug'] ?>" class="product-card" style="text-align:center;padding:1.5rem;text-decoration:none;color:inherit;">
                <div style="font-size:2.5rem;margin-bottom:.75rem">
                    <?= match($cat['slug']) {
                        'electronics'  => '💻',
                        'clothing'     => '👕',
                        'home-garden'  => '🏠',
                        'sports'       => '⚽',
                        'books'        => '📚',
                        default        => '🛍'
                    } ?>
                </div>
                <div style="font-weight:600"><?= sanitize($cat['name']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products -->
<?php if (!empty($featured)): ?>
<section class="section" style="background:var(--light);padding:3rem 0">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Featured Products</h2>
                <p class="section-subtitle">Handpicked products just for you</p>
            </div>
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-outline">View All →</a>
        </div>
        <div class="products-grid">
            <?php foreach ($featured as $product): ?>
                <?php include 'includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Promo Banner -->
<section class="section">
    <div class="container">
        <div style="background:linear-gradient(135deg,#f59e0b,#ef4444);border-radius:1rem;padding:3rem;text-align:center;color:white">
            <h2 style="font-size:1.8rem;font-weight:700;margin-bottom:.5rem">Summer Sale — Up to 50% Off!</h2>
            <p style="opacity:.9;margin-bottom:1.5rem">Limited time offer. Don't miss out on these amazing deals.</p>
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-white btn-lg">Shop the Sale</a>
        </div>
    </div>
</section>

<!-- New Arrivals -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">New Arrivals</h2>
                <p class="section-subtitle">Fresh products added this week</p>
            </div>
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-outline">View All →</a>
        </div>
        <div class="products-grid">
            <?php foreach ($newArrivals as $product): ?>
                <?php include 'includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Features Bar -->
<section class="section" style="background:var(--dark);color:white;padding:2rem 0;">
    <div class="container">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.5rem;text-align:center">
            <?php foreach ([
                ['🚚', 'Free Shipping', 'On orders over ' . price(FREE_SHIPPING_THRESHOLD)],
                ['🔄', 'Easy Returns',  '30-day return policy'],
                ['🔒', 'Secure Payment','100% protected payments'],
                ['🎧', '24/7 Support',  'We\'re always here to help'],
            ] as [$icon, $title, $sub]): ?>
            <div>
                <div style="font-size:2rem;margin-bottom:.5rem"><?= $icon ?></div>
                <div style="font-weight:600"><?= $title ?></div>
                <div style="opacity:.6;font-size:.85rem"><?= $sub ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
