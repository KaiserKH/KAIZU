<?php
require_once __DIR__ . '/db.php';

// ─── Auth Helpers ───────────────────────────────────────────────────────────

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Log the unauthorised attempt if a user is logged in but not admin
        if (isLoggedIn()) {
            logActivity('admin_access_denied', $_SESSION['user_id'] ?? null,
                'Attempted access to admin area from ' . $_SERVER['REQUEST_URI']);
        }
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
    // Block disabled accounts
    if (($_SESSION['user_status'] ?? 'active') === 'disabled') {
        session_destroy();
        header('Location: ' . SITE_URL . '/admin/login.php?error=disabled');
        exit;
    }
}

function loginUser($user) {
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['user_status'] = $user['status'] ?? 'active';
    $_SESSION['_last_regen'] = time();
}

function logoutUser() {
    if (isLoggedIn()) {
        logActivity('logout', $_SESSION['user_id'], 'User logged out');
    }
    $role = $_SESSION['user_role'] ?? 'customer';
    session_unset();
    session_destroy();
    // Redirect admins to admin login, customers to main login
    if ($role === 'admin') {
        header('Location: ' . SITE_URL . '/admin/login.php');
    } else {
        header('Location: ' . SITE_URL . '/login.php');
    }
    exit;
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $id   = (int)$_SESSION['user_id'];
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ─── CSRF Protection ─────────────────────────────────────────────────────────

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function csrfVerify() {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';
    if (!$submitted || !$expected || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('<h2>403 — Invalid or expired form token.</h2><p><a href="javascript:history.back()">Go back</a> and try again.</p>');
    }
}

// ─── Activity Logging ────────────────────────────────────────────────────────

function logActivity($action, $userId = null, $details = '') {
    try {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
        $uid = $userId ?? ($_SESSION['user_id'] ?? null);
        $stmt = db()->prepare(
            "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('issss', $uid, $action, $details, $ip, $ua);
        $stmt->execute();
    } catch (Exception $e) {
        // fail silently — logging must never break the app
    }
}

function getActivityLogs($limit = 50, $offset = 0, $action = '', $userId = 0) {
    $where  = 'WHERE 1';
    $params = []; $types = '';
    if ($action) { $where .= ' AND l.action = ?';  $params[] = $action; $types .= 's'; }
    if ($userId) { $where .= ' AND l.user_id = ?'; $params[] = $userId; $types .= 'i'; }
    $params[] = $limit; $params[] = $offset; $types .= 'ii';
    $stmt = db()->prepare(
        "SELECT l.*, u.name AS user_name, u.email AS user_email
         FROM activity_logs l
         LEFT JOIN users u ON l.user_id = u.id
         $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function countActivityLogs($action = '', $userId = 0) {
    $where  = 'WHERE 1';
    $params = []; $types = '';
    if ($action) { $where .= ' AND action = ?';  $params[] = $action; $types .= 's'; }
    if ($userId) { $where .= ' AND user_id = ?'; $params[] = $userId; $types .= 'i'; }
    $stmt = db()->prepare("SELECT COUNT(*) AS c FROM activity_logs $where");
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['c'];
}

// ─── Settings ────────────────────────────────────────────────────────────────

function getSetting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = db()->query("SELECT setting_key, setting_value FROM settings")->fetch_all(MYSQLI_ASSOC);
            $cache = array_column($rows, 'setting_value', 'setting_key');
        } catch (Exception $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

function setSetting($key, $value) {
    $stmt = db()->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)"
    );
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

function setSettings(array $map) {
    foreach ($map as $key => $value) {
        setSetting($key, $value);
    }
}

// ─── Product Helpers ────────────────────────────────────────────────────────

function getProducts($limit = 20, $offset = 0, $category = null, $search = null, $featured = false) {
    $sql  = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active'";
    $params = []; $types = '';

    if ($category) {
        $sql .= " AND c.slug = ?";
        $params[] = $category; $types .= 's';
    }
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss';
    }
    if ($featured) {
        $sql .= " AND p.featured = 1";
    }
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit; $params[] = $offset; $types .= 'ii';

    $stmt = db()->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getProduct($slug) {
    $stmt = db()->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.status = 'active'");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getProductById($id) {
    $id   = (int)$id;
    $stmt = db()->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getCategories() {
    return db()->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}

function countProducts($category = null, $search = null) {
    $sql = "SELECT COUNT(*) as cnt FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active'";
    $params = []; $types = '';
    if ($category) { $sql .= " AND c.slug = ?"; $params[] = $category; $types .= 's'; }
    if ($search)   { $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss'; }
    $stmt = db()->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

function getProductRating($productId) {
    $stmt = db()->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ─── Cart Helpers ───────────────────────────────────────────────────────────

function getCartKey() {
    if (isLoggedIn()) {
        return ['type' => 'user', 'value' => (int)$_SESSION['user_id']];
    }
    if (empty($_SESSION['cart_session'])) {
        $_SESSION['cart_session'] = session_id();
    }
    return ['type' => 'session', 'value' => $_SESSION['cart_session']];
}

function getCartItems() {
    $key = getCartKey();
    if ($key['type'] === 'user') {
        $stmt = db()->prepare("SELECT c.*, p.name, p.slug, p.image, p.stock, COALESCE(p.sale_price, p.price) AS unit_price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
        $stmt->bind_param('i', $key['value']);
    } else {
        $stmt = db()->prepare("SELECT c.*, p.name, p.slug, p.image, p.stock, COALESCE(p.sale_price, p.price) AS unit_price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.session_id = ?");
        $stmt->bind_param('s', $key['value']);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getCartCount() {
    $key = getCartKey();
    if ($key['type'] === 'user') {
        $stmt = db()->prepare("SELECT SUM(quantity) as cnt FROM cart WHERE user_id = ?");
        $stmt->bind_param('i', $key['value']);
    } else {
        $stmt = db()->prepare("SELECT SUM(quantity) as cnt FROM cart WHERE session_id = ?");
        $stmt->bind_param('s', $key['value']);
    }
    $stmt->execute();
    return (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}

function addToCart($productId, $quantity = 1) {
    $key = getCartKey();
    $productId = (int)$productId;
    $quantity  = (int)$quantity;

    // Check stock
    $product = getProductById($productId);
    if (!$product || $product['stock'] < $quantity) return false;

    if ($key['type'] === 'user') {
        $stmt = db()->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $key['value'], $productId);
    } else {
        $stmt = db()->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ?");
        $stmt->bind_param('si', $key['value'], $productId);
    }
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $newQty = $existing['quantity'] + $quantity;
        if ($newQty > $product['stock']) $newQty = $product['stock'];
        $upd = db()->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $upd->bind_param('ii', $newQty, $existing['id']);
        $upd->execute();
    } else {
        if ($key['type'] === 'user') {
            $ins = db()->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $ins->bind_param('iii', $key['value'], $productId, $quantity);
        } else {
            $ins = db()->prepare("INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, ?)");
            $ins->bind_param('sii', $key['value'], $productId, $quantity);
        }
        $ins->execute();
    }
    return true;
}

function updateCartItem($cartId, $quantity) {
    $cartId   = (int)$cartId;
    $quantity = (int)$quantity;
    if ($quantity <= 0) {
        removeFromCart($cartId);
        return;
    }
    $stmt = db()->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->bind_param('ii', $quantity, $cartId);
    $stmt->execute();
}

function removeFromCart($cartId) {
    $cartId = (int)$cartId;
    $stmt   = db()->prepare("DELETE FROM cart WHERE id = ?");
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
}

function clearCart() {
    $key = getCartKey();
    if ($key['type'] === 'user') {
        $stmt = db()->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param('i', $key['value']);
    } else {
        $stmt = db()->prepare("DELETE FROM cart WHERE session_id = ?");
        $stmt->bind_param('s', $key['value']);
    }
    $stmt->execute();
}

function mergeGuestCart($userId) {
    $sessionId = $_SESSION['cart_session'] ?? null;
    if (!$sessionId) return;

    $guestItems = db()->prepare("SELECT * FROM cart WHERE session_id = ?");
    $guestItems->bind_param('s', $sessionId);
    $guestItems->execute();
    $items = $guestItems->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
        $check = db()->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check->bind_param('ii', $userId, $item['product_id']);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $newQty = $existing['quantity'] + $item['quantity'];
            $upd = db()->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $upd->bind_param('ii', $newQty, $existing['id']);
            $upd->execute();
        } else {
            $ins = db()->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $ins->bind_param('iii', $userId, $item['product_id'], $item['quantity']);
            $ins->execute();
        }
    }

    $del = db()->prepare("DELETE FROM cart WHERE session_id = ?");
    $del->bind_param('s', $sessionId);
    $del->execute();
    unset($_SESSION['cart_session']);
}

function getCartTotals() {
    $items    = getCartItems();
    $subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $items));
    $shipping = ($subtotal >= FREE_SHIPPING_THRESHOLD || $subtotal == 0) ? 0 : SHIPPING_COST;
    $tax      = round($subtotal * TAX_RATE, 2);
    $total    = $subtotal + $shipping + $tax;
    return compact('items', 'subtotal', 'shipping', 'tax', 'total');
}

// ─── Order Helpers ───────────────────────────────────────────────────────────

function createOrder($data, $userId = null) {
    $totals = getCartTotals();
    if (empty($totals['items'])) return false;

    $orderNumber = 'ORD-' . strtoupper(uniqid());

    $stmt = db()->prepare("INSERT INTO orders (user_id, order_number, subtotal, shipping, tax, total, shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip, shipping_country, payment_method, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param(
        'isddddssssssssss',
        $userId, $orderNumber,
        $totals['subtotal'], $totals['shipping'], $totals['tax'], $totals['total'],
        $data['name'], $data['email'], $data['phone'],
        $data['address'], $data['city'], $data['state'], $data['zip'], $data['country'],
        $data['payment_method'], $data['notes']
    );
    $stmt->execute();
    $orderId = db()->insert_id;

    foreach ($totals['items'] as $item) {
        $subtotal = $item['unit_price'] * $item['quantity'];
        $ins = db()->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, price, quantity, subtotal) VALUES (?,?,?,?,?,?,?)");
        $ins->bind_param('iissdid', $orderId, $item['product_id'], $item['name'], $item['image'], $item['unit_price'], $item['quantity'], $subtotal);
        $ins->execute();

        // Reduce stock
        $upd = db()->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $upd->bind_param('ii', $item['quantity'], $item['product_id']);
        $upd->execute();
    }

    clearCart();
    return $orderNumber;
}

function getUserOrders($userId) {
    $stmt = db()->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getOrderByNumber($number) {
    $stmt = db()->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->bind_param('s', $number);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getOrderItems($orderId) {
    $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ─── Wishlist ────────────────────────────────────────────────────────────────

function toggleWishlist($userId, $productId) {
    $stmt = db()->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $del = db()->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $del->bind_param('ii', $userId, $productId);
        $del->execute();
        return false;
    } else {
        $ins = db()->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?,?)");
        $ins->bind_param('ii', $userId, $productId);
        $ins->execute();
        return true;
    }
}

function isInWishlist($userId, $productId) {
    $stmt = db()->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

// ─── Utility ─────────────────────────────────────────────────────────────────

function redirect($url) {
    header("Location: " . SITE_URL . "/$url");
    exit;
}

function price($amount) {
    return CURRENCY . number_format($amount, 2);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function flash($message, $type = 'success') {
    $_SESSION['flash'] = compact('message', 'type');
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash() {
    $flash = getFlash();
    if ($flash) {
        echo "<div class=\"alert alert-{$flash['type']}\">{$flash['message']}</div>";
    }
}

function productImageUrl($image) {
    return $image && file_exists(__DIR__ . "/../assets/images/$image")
        ? SITE_URL . "/assets/images/$image"
        : SITE_URL . "/assets/images/placeholder.png";
}

function generateStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '★' : '☆';
    }
    return $stars;
}

function paginate($total, $perPage, $currentPage, $baseUrl) {
    $pages = ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i == $currentPage ? ' active' : '';
        $html  .= "<a href=\"{$baseUrl}&page={$i}\" class=\"page-btn{$active}\">{$i}</a>";
    }
    $html .= '</div>';
    return $html;
}
