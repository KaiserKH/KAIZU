-- ShopPHP Ecommerce Database Schema
-- Run: mysql -u root -p < database.sql

CREATE DATABASE IF NOT EXISTS shopphp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shopphp;

-- Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) DEFAULT NULL,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(255),
    images JSON,
    featured TINYINT(1) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    zip VARCHAR(20),
    country VARCHAR(100) DEFAULT 'US',
    role ENUM('customer','admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cart
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(100),
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    shipping DECIMAL(10,2) DEFAULT 0.00,
    tax DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    -- Shipping details
    shipping_name VARCHAR(100),
    shipping_email VARCHAR(150),
    shipping_phone VARCHAR(20),
    shipping_address TEXT,
    shipping_city VARCHAR(100),
    shipping_state VARCHAR(100),
    shipping_zip VARCHAR(20),
    shipping_country VARCHAR(100),
    -- Payment
    payment_method VARCHAR(50) DEFAULT 'cod',
    payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order Items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255) NOT NULL,
    product_image VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Reviews
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (product_id, user_id)
);

-- Wishlist
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id)
);

-- Sample Data
INSERT INTO categories (name, slug, description) VALUES
('Electronics', 'electronics', 'Gadgets, phones, computers and more'),
('Clothing', 'clothing', 'Men and women fashion'),
('Home & Garden', 'home-garden', 'Furniture, decor and garden supplies'),
('Sports', 'sports', 'Sports equipment and activewear'),
('Books', 'books', 'Fiction, non-fiction, educational');

INSERT INTO products (category_id, name, slug, description, price, sale_price, stock, image, featured) VALUES
(1, 'Wireless Bluetooth Headphones', 'wireless-bluetooth-headphones', 'Premium sound quality with 30-hour battery life and active noise cancellation.', 99.99, 79.99, 50, 'headphones.jpg', 1),
(1, 'Smart Watch Pro', 'smart-watch-pro', 'Track fitness, receive notifications, and more with this sleek smartwatch.', 249.99, NULL, 30, 'smartwatch.jpg', 1),
(1, 'Mechanical Keyboard', 'mechanical-keyboard', 'RGB backlit mechanical keyboard with tactile switches for an exceptional typing experience.', 129.99, 109.99, 25, 'keyboard.jpg', 0),
(1, '4K Webcam', '4k-webcam', 'Crystal clear 4K video conferencing camera with built-in noise-cancelling mic.', 89.99, NULL, 40, 'webcam.jpg', 0),
(2, 'Classic White T-Shirt', 'classic-white-tshirt', '100% organic cotton premium quality t-shirt. Available in multiple sizes.', 24.99, NULL, 100, 'tshirt.jpg', 0),
(2, 'Denim Jacket', 'denim-jacket', 'Stylish denim jacket with modern fit. Perfect for casual outings.', 69.99, 54.99, 45, 'denim-jacket.jpg', 1),
(3, 'Ergonomic Office Chair', 'ergonomic-office-chair', 'Lumbar support, adjustable height and armrests for all-day comfort.', 299.99, 249.99, 15, 'office-chair.jpg', 1),
(3, 'Indoor Plant Set', 'indoor-plant-set', 'Set of 5 low-maintenance indoor plants perfect for home or office.', 49.99, NULL, 60, 'plants.jpg', 0),
(4, 'Yoga Mat Premium', 'yoga-mat-premium', 'Non-slip 6mm thick yoga mat with carrying strap. Eco-friendly material.', 39.99, NULL, 80, 'yoga-mat.jpg', 0),
(4, 'Resistance Bands Set', 'resistance-bands-set', 'Set of 5 resistance bands with different tension levels for home workouts.', 29.99, 24.99, 120, 'resistance-bands.jpg', 0);

-- Admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@shopphp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Activity / audit log (auto-created by db.php if missing, also seeded here)
CREATE TABLE IF NOT EXISTS activity_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT DEFAULT NULL,
    action     VARCHAR(80)  NOT NULL,
    details    TEXT,
    ip_address VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent VARCHAR(300) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_action (action),
    INDEX idx_time   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Site settings store
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT        NOT NULL DEFAULT '',
    updated_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('site_name',               'ShopPHP'),
    ('site_email',              'info@shopphp.com'),
    ('currency',                '$'),
    ('tax_rate',                '0.08'),
    ('shipping_cost',           '9.99'),
    ('free_shipping_threshold', '100.00'),
    ('maintenance_mode',        '0'),
    ('store_address',           ''),
    ('store_phone',             ''),
    ('meta_description',        'The best online shop');
