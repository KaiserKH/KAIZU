<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

// Handle GET clear action
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'clear') {
    clearCart();
    flash('Cart cleared.', 'success');
    header('Location: ' . SITE_URL . '/cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $body['action'] ?? '';

function formatTotals($totals) {
    return [
        'subtotal_fmt' => price($totals['subtotal']),
        'shipping_fmt' => $totals['shipping'] == 0 ? 'FREE' : price($totals['shipping']),
        'tax_fmt'      => price($totals['tax']),
        'total_fmt'    => price($totals['total']),
    ];
}

switch ($action) {
    case 'add':
        $productId = (int)($body['product_id'] ?? 0);
        $quantity  = (int)($body['quantity']   ?? 1);
        if (!$productId) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }
        $success = addToCart($productId, $quantity);
        $totals  = getCartTotals();
        echo json_encode([
            'success'    => $success,
            'message'    => $success ? 'Added to cart!' : 'Could not add item (out of stock?)',
            'cart_count' => getCartCount(),
            'totals'     => formatTotals($totals),
        ]);
        break;

    case 'update':
        $cartId   = (int)($body['cart_id']  ?? 0);
        $quantity = (int)($body['quantity'] ?? 1);
        updateCartItem($cartId, $quantity);
        $totals = getCartTotals();
        echo json_encode([
            'success'    => true,
            'cart_count' => getCartCount(),
            'totals'     => formatTotals($totals),
        ]);
        break;

    case 'remove':
        $cartId = (int)($body['cart_id'] ?? 0);
        removeFromCart($cartId);
        $totals = getCartTotals();
        echo json_encode([
            'success'    => true,
            'cart_count' => getCartCount(),
            'totals'     => formatTotals($totals),
        ]);
        break;

    case 'count':
        echo json_encode(['success' => true, 'cart_count' => getCartCount()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
