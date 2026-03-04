<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'redirect' => SITE_URL . '/login.php']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$productId = (int)($body['product_id'] ?? 0);
$userId    = (int)$_SESSION['user_id'];

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$inWishlist = toggleWishlist($userId, $productId);
echo json_encode(['success' => true, 'in_wishlist' => $inWishlist]);
