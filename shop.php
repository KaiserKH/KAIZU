<?php
$pageTitle = 'Shop';
require_once 'includes/config.php';
require_once 'includes/functions.php';
$perPage  = 12;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;
$category = $_GET['category'] ?? null;
$search   = $_GET['search']   ?? null;
$sort     = $_GET['sort']     ?? 'newest';

$total    = countProducts($category, $search);
$products = getProducts($perPage, $offset, $category, $search);

// Sort manually (for demo; ideally add ORDER BY to getProducts)
if ($sort === 'price_asc')  usort($products, fn($a,$b) => $a['price'] <=> $b['price']);
if ($sort === 'price_desc') usort($products, fn($a,$b) => $b['price'] <=> $a['price']);

$categories  = getCategories();
$currentCat  = $category ? array_filter($categories, fn($c) => $c['slug'] === $category) : null;
$currentCatName = $currentCat ? array_values($currentCat)[0]['name'] : 'All Products';

if ($category) $pageTitle = $currentCatName;
elseif ($search) $pageTitle = 'Search: ' . sanitize($search);

require_once 'includes/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/">Home</a>
        <span>/</span>
        <a href="<?= SITE_URL ?>/shop.php">Shop</a>
        <?php if ($category || $search): ?>
            <span>/</span>
            <span><?= $category ? sanitize($currentCatName) : 'Search: ' . sanitize($search) ?></span>
        <?php endif; ?>
    </div>

    <div class="shop-layout">
        <!-- Mobile filter overlay -->
        <div class="filter-overlay" id="filterOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="shopSidebar">
            <div class="sidebar-widget" style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 1.25rem">
                <span style="font-weight:700;font-size:.9rem">Filters</span>
                <button class="sidebar-close btn btn-ghost btn-sm" id="sidebarClose" style="display:none">✕ Close</button>
            </div>
            <div class="sidebar-widget">
                <div class="sidebar-title">Categories</div>
                <ul class="filter-list">
                    <li><a href="<?= SITE_URL ?>/shop.php" class="<?= !$category ? 'active' : '' ?>">
                        All Products <span class="filter-count"><?= countProducts() ?></span></a></li>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="<?= SITE_URL ?>/shop.php?category=<?= $cat['slug'] ?>"
                               class="<?= $category === $cat['slug'] ? 'active' : '' ?>">
                                <?= sanitize($cat['name']) ?>
                                <span class="filter-count"><?= countProducts($cat['slug']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sidebar-widget">
                <div class="sidebar-title">Filter by Price</div>
                <form method="GET" action="">
                    <?php if ($category): ?><input type="hidden" name="category" value="<?= sanitize($category) ?>"><?php endif; ?>
                    <?php if ($search):   ?><input type="hidden" name="search"   value="<?= sanitize($search) ?>"><?php endif; ?>
                    <div class="form-group">
                        <label>Min Price</label>
                        <input type="number" name="min_price" class="form-control" value="<?= (int)($_GET['min_price'] ?? 0) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Max Price</label>
                        <input type="number" name="max_price" class="form-control" value="<?= (int)($_GET['max_price'] ?? 1000) ?>" min="0">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block">Apply Filter</button>
                </form>
            </div>
        </aside>

        <!-- Products -->
        <div class="shop-content">
            <!-- Mobile filter toggle -->
            <button class="btn btn-ghost filter-toggle-btn" id="filterToggleBtn">
                ⚙ Filters <?php if ($category || $search): ?><span class="status-badge status-processing" style="margin-left:.3rem">Active</span><?php endif; ?>
            </button>
            <div class="shop-toolbar">
                <span class="result-count">
                    <?= $total ?> product<?= $total !== 1 ? 's' : '' ?> found
                    <?= $search ? ' for "<strong>' . sanitize($search) . '</strong>"' : '' ?>
                </span>
                <form method="GET" action="">
                    <?php if ($category): ?><input type="hidden" name="category" value="<?= sanitize($category) ?>"><?php endif; ?>
                    <?php if ($search):   ?><input type="hidden" name="search"   value="<?= sanitize($search) ?>"><?php endif; ?>
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="newest"     <?= $sort==='newest'     ?'selected':'' ?>>Newest First</option>
                        <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':'' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort==='price_desc' ?'selected':'' ?>>Price: High to Low</option>
                    </select>
                </form>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <div class="icon">🔍</div>
                    <h3>No products found</h3>
                    <p>Try different keywords or browse all categories.</p>
                    <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Browse All Products</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <?php include 'includes/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <?php
                $baseUrl = SITE_URL . '/shop.php?' . http_build_query(array_filter(['category' => $category, 'search' => $search, 'sort' => $sort]));
                echo paginate($total, $perPage, $page, $baseUrl);
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
