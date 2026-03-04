<?php
require_once __DIR__ . '/functions.php';
$cartCount = getCartCount();
$flash     = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '' ?><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>const SITE_URL = '<?= SITE_URL ?>';</script>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
    <div class="container">
        <span>Free shipping on orders over <?= price(FREE_SHIPPING_THRESHOLD) ?></span>
        <div class="topbar-links">
            <?php if (isLoggedIn()): ?>
                Welcome, <?= sanitize($_SESSION['user_name']) ?> |
                <?php if (isAdmin()): ?><a href="<?= SITE_URL ?>/admin/">Admin</a> | <?php endif; ?>
                <a href="<?= SITE_URL ?>/orders.php">My Orders</a> |
                <a href="<?= SITE_URL ?>/logout.php">Logout</a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/login.php">Login</a> |
                <a href="<?= SITE_URL ?>/register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Header -->
<header class="header">
    <div class="container">
        <a href="<?= SITE_URL ?>/" class="logo"><?= SITE_NAME ?></a>

        <form class="search-form" action="<?= SITE_URL ?>/shop.php" method="GET">
            <input type="text" name="search" placeholder="Search products..." value="<?= sanitize($_GET['search'] ?? '') ?>">
            <button type="submit">🔍</button>
        </form>

        <nav class="header-actions">
            <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/wishlist.php" class="icon-btn" title="Wishlist">♡</a>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/cart.php" class="icon-btn cart-icon" title="Cart">
                🛒 <span class="cart-count" id="cart-count"><?= $cartCount ?></span>
            </a>
        </nav>
    </div>
</header>

<!-- Navigation -->
<nav class="navbar">
    <div class="container">
        <ul class="nav-links" id="navLinks">
            <li><a href="<?= SITE_URL ?>/" class="<?= $currentPage === 'index' ? 'active' : '' ?>">Home</a></li>
            <li><a href="<?= SITE_URL ?>/shop.php" class="<?= $currentPage === 'shop' ? 'active' : '' ?>">Shop</a></li>
            <?php foreach (getCategories() as $cat): ?>
                <li><a href="<?= SITE_URL ?>/shop.php?category=<?= $cat['slug'] ?>"><?= sanitize($cat['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
    </div>
</nav>

<!-- Flash Messages -->
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> container" style="margin-top:1rem">
        <?= sanitize($flash['message']) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<main class="main-content">
