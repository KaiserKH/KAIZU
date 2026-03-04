<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306);
        if ($this->conn->connect_error) {
            die('Database connection failed: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
        $this->initSecurityTables();
    }

    private function initSecurityTables() {
        // Activity / audit log table
        $this->conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT DEFAULT NULL,
            action      VARCHAR(80)  NOT NULL,
            details     TEXT,
            ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
            user_agent  VARCHAR(300) NOT NULL DEFAULT '',
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user   (user_id),
            INDEX idx_action (action),
            INDEX idx_time   (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Key-value settings store
        $this->conn->query("CREATE TABLE IF NOT EXISTS settings (
            setting_key   VARCHAR(80)  NOT NULL PRIMARY KEY,
            setting_value TEXT         NOT NULL DEFAULT '',
            updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed default settings if empty
        $this->conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
            ('site_name',               'ShopPHP'),
            ('site_email',              'info@shopphp.com'),
            ('currency',                '\$'),
            ('tax_rate',                '0.08'),
            ('shipping_cost',           '9.99'),
            ('free_shipping_threshold', '100.00'),
            ('maintenance_mode',        '0'),
            ('store_address',           ''),
            ('store_phone',             ''),
            ('meta_description',        'The best online shop')");

        // Add `status` column to users if missing (for disable/enable feature)
        $cols = $this->conn->query("SHOW COLUMNS FROM users LIKE 'status'");
        if ($cols && $cols->num_rows === 0) {
            $this->conn->query("ALTER TABLE users ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active' AFTER role");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() { return $this->conn; }
    public function query($sql)     { return $this->conn->query($sql); }
    public function prepare($sql)   { return $this->conn->prepare($sql); }
    public function escape($value)  { return $this->conn->real_escape_string($value); }
    public function lastInsertId()  { return $this->conn->insert_id; }

    // Expose insert_id as property for backward-compat
    public function __get($name) {
        if ($name === 'insert_id') return $this->conn->insert_id;
    }
}

// Global shortcut
function db() {
    return Database::getInstance()->getConnection();
}
