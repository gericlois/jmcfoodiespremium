-- Referral-based product sales platform schema
CREATE DATABASE IF NOT EXISTS referral_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE referral_platform;

-- ---------------------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    srp DECIMAL(10,2) NOT NULL DEFAULT 210.00,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Extra marketing/highlight images shown on a product's profile page.
-- Unlike `products.image` (the one main catalog photo), a product can have
-- any number of these (or none).
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referral_code VARCHAR(20) NOT NULL UNIQUE,
    referred_by INT DEFAULT NULL,
    full_name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    birthdate DATE NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    email VARCHAR(190) NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('wallet','gcash') NOT NULL,
    gcash_reference VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
    confirmed_at TIMESTAMP NULL DEFAULT NULL,
    confirmed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (confirmed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cashouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    gcash_number VARCHAR(20) NOT NULL,
    gcash_name VARCHAR(150) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes VARCHAR(255) DEFAULT NULL,
    processed_by INT DEFAULT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Editable business settings (rebate/override rates, min cashout, contact
-- email) — managed from admin/settings.php instead of hardcoded constants.
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('personal_rebate','referral_override','purchase_wallet_debit','purchase_refund','cashout','cashout_reversal') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_order_id INT DEFAULT NULL,
    reference_cashout_id INT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (reference_cashout_id) REFERENCES cashouts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Seed data
INSERT INTO products (name, description, srp, image, status) VALUES
('Activated Charcoal', 'Premium activated charcoal food supplement. 100 capsules, 560mg per capsule.', 699.00, 'activated-charcoal.png', 'active'),
('Premium Healthy Coffee Mix', 'Good coffee. Real nutrition. Better you. Natural antioxidant creamy white coffee with malunggay, spinach, inulin & collagen. Benefits in every cup. Net weight 110g.\n\nBenefits:\n- Boosts Immunity\n- Energy & Alertness\n- Improves Focus & Concentration\n- Supports Digestive Health\n- Natural Antioxidants\n- Collagen Peptides for Beauty\n- Nourishing Superfoods\n- Wellness for Your Daily Life', 230.00, 'healthy-coffee-mix.png', 'active'),
('Choco Wellness', 'Indulge in a creamy, delicious cup of wellness. Rich chocolate, collagen, prebiotic fiber & superfoods. Net weight 110g, makes approx. 7 cups. Proudly made in the Philippines.\n\nBenefits:\n- Supports Immunity: helps strengthen your body''s natural defenses\n- Rich in Antioxidants: helps fight free radicals and supports overall well-being\n- Natural Energy Boost: gentle, sustained energy to keep you active all day\n- Supports Digestive Health: contains prebiotic fiber (inulin) for a healthy gut and digestion\n- Healthy Skin, Hair & Nails: collagen helps promote healthy, radiant skin, strong hair and nails\n- Made with Superfoods: malunggay and spinach, nature''s powerful greens', 230.00, 'choco-wellness.png', 'active');

-- Extra highlight/marketing images (none for Activated Charcoal — no source images provided for it)
INSERT INTO product_images (product_id, image, sort_order) VALUES
(2, 'healthy-coffee-mix-catalog.jpg', 0),
(2, 'healthy-coffee-mix-catalog2.jpg', 1),
(3, 'choco-wellness-catalog.jpg', 0),
(3, 'choco-wellness-catalog2.jpg', 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('personal_rebate_rate', '0.20'),
('referral_override_rate', '0.10'),
('min_cashout_amount', '100.00'),
('company_email', 'support@example.com');

-- Default admin login: username "admin", password "admin123"
INSERT INTO admins (username, password_hash, name) VALUES
('admin', '$2y$10$sL2pfSbiNdwBgSExIOoNa.CPd3tdNGoWCXUOxlLMGX3gkR8VFNIbG', 'Administrator');

-- Seed root user (no referrer) so real registrations have a code to chain from.
-- Login: username "founder", temporary password "Founder123!" (must be changed on first login)
INSERT INTO users (referral_code, referred_by, full_name, address, birthdate, contact_number, username, password_hash, must_change_password, status) VALUES
('JMC-FQL5U5CLG', NULL, 'Founding Member', 'Nueva Ecija, Philippines', '1990-01-01', '09170000000', 'founder', '$2y$10$nV56Gkoviq4gWUY4j/VF1.Ys.TMCcIo2XraJosAWrmEIHKg4fYgDe', 1, 'active');
