<?php
// ── Database (read from environment variables; fall back to local defaults) ──
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'shopuser');
define('DB_PASS', getenv('DB_PASS') ?: 'shop123');
define('DB_NAME', getenv('DB_NAME') ?: 'shopphp');

// ── Site Defaults (overridden at runtime by settings table) ──────────────────
define('SITE_NAME',               getenv('SITE_NAME')  ?: 'ShopPHP');
define('SITE_URL',                rtrim(getenv('SITE_URL') ?: 'http://localhost:8080', '/'));
define('SITE_EMAIL',              getenv('SITE_EMAIL') ?: 'info@shopphp.com');
define('CURRENCY',                '$');
define('TAX_RATE',                0.08);
define('SHIPPING_COST',           9.99);
define('FREE_SHIPPING_THRESHOLD', 100.00);

// ── Security Headers ─────────────────────────────────────────────────────────
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ── Session Hardening ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 7200); // 2 hours
    session_start();
}

// Regenerate session ID periodically (every 30 min) to prevent fixation
if (!isset($_SESSION['_last_regen'])) {
    $_SESSION['_last_regen'] = time();
} elseif (time() - $_SESSION['_last_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ── Error Reporting (disabled in production via APP_DEBUG env var) ───────────
$appDebug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
error_reporting($appDebug ? E_ALL : 0);
ini_set('display_errors', $appDebug ? 1 : 0);
ini_set('log_errors', 1);
