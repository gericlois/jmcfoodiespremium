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
    -- Whether this account has a JMC Foodies Wellness membership. Every account
    -- (Wellness, Basics, or both) shares this one `users` row/login; Basics-only
    -- signups get wellness_enrolled=0 and never see Wellness pages.
    wellness_enrolled TINYINT(1) NOT NULL DEFAULT 1,
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
    payment_method ENUM('wallet','bank_transfer','cod') NOT NULL,
    payment_reference VARCHAR(100) DEFAULT NULL,
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
    bank_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    account_number VARCHAR(50) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
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
('min_cashout_amount', '1000.00'),
('cashout_processing_fee_rate', '0.10'),
('company_email', 'jmcdigital@gmail.com');

-- Default admin login: username "jmcadmin", password "AdminJMC#2026"
INSERT INTO admins (username, password_hash, name) VALUES
('jmcadmin', '$2y$10$b6NL0VAz2LLzMf/hyM/RMOuvjT5TJWIfdIl0H1cWLhFhIzpypi2NS', 'Administrator');

-- Seed root user (no referrer) so real registrations have a code to chain from.
-- Login: username "jmcuser", password "Founder2026"
INSERT INTO users (referral_code, referred_by, full_name, address, birthdate, contact_number, username, password_hash, must_change_password, status) VALUES
('JMC-FQL5U5CLG', NULL, 'Founding Member', 'Nueva Ecija, Philippines', '1990-01-01', '09170000000', 'jmcuser', '$2y$10$FEtuCV1fZGez6s8vLRSLwOD88mGaBJS0I31UdrUFXFP6dNLSt9QvO', 0, 'active');

-- ---------------------------------------------------------------
-- JMC Foodies Basics — grocery credit-line ordering system.
-- Shares the `users`/`admins` login tables above; a user "joins" Basics by
-- getting a row here (application_status starts 'pending', admin approves).
CREATE TABLE basics_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    employer_name VARCHAR(150) NOT NULL,
    employer_contact VARCHAR(100) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    application_status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    membership_status ENUM('active','suspended','dormant','terminated') NOT NULL DEFAULT 'active',
    weekly_credit_limit DECIMAL(10,2) NOT NULL DEFAULT 0,
    emergency_credit_limit DECIMAL(10,2) NOT NULL DEFAULT 0,
    offense_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    consecutive_on_time_payments INT UNSIGNED NOT NULL DEFAULT 0,
    credit_limit_frozen TINYINT(1) NOT NULL DEFAULT 0,
    suspended_until DATE DEFAULT NULL,
    last_activity_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    admin_notes VARCHAR(255) DEFAULT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- KYC documents (2 valid IDs, Barangay Clearance, Application Form, COE).
-- Deliberately NOT served the way product images are — see uploads/basics_kyc/
-- .htaccess and basics/admin/kyc_view.php. These are government ID scans.
CREATE TABLE basics_kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    doc_type ENUM('valid_id_1','valid_id_2','barangay_clearance','membership_application_form','certificate_of_employment') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES basics_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Grocery SKU catalog (rice / breakfast / viand). srp=0 renders as "TBD" in
-- the UI until an admin fills in real prices via basics/admin/product_edit.php.
CREATE TABLE basics_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(20) NOT NULL UNIQUE,
    category ENUM('Bigas','Pang-almusal','Pang-ulam') NOT NULL,
    name VARCHAR(150) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    srp DECIMAL(10,2) NOT NULL DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Weekly ordering windows (Mon-Thu order, Fri cutoff, Sat-Sun payment,
-- Sun/Mon delivery). `status` is a display cache only — window-gating logic
-- always recomputes from the date columns against today's date.
CREATE TABLE basics_cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    order_open_date DATE NOT NULL UNIQUE,
    order_cutoff_date DATE NOT NULL,
    payment_start_date DATE NOT NULL,
    payment_due_date DATE NOT NULL,
    delivery_date DATE NOT NULL,
    status ENUM('upcoming','ordering','cutoff','payment','delivered','closed') NOT NULL DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- One cart/order per member per cycle (multi-item, unlike Wellness's
-- single-product orders table).
CREATE TABLE basics_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    cycle_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('draft','placed','delivered','cancelled') NOT NULL DEFAULT 'draft',
    placed_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES basics_members(id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id) REFERENCES basics_cycles(id) ON DELETE RESTRICT,
    UNIQUE KEY (member_id, cycle_id)
) ENGINE=InnoDB;

CREATE TABLE basics_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES basics_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES basics_products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Settlement records. fee/net-style snapshot pattern (mirrors cashouts'
-- fee_amount/net_amount) so a later settings change never rewrites history.
CREATE TABLE basics_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    member_id INT NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    penalty_rate DECIMAL(5,4) NOT NULL DEFAULT 0,
    penalty_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    offense_number TINYINT UNSIGNED DEFAULT NULL,
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES basics_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES basics_members(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('basics_late_penalty_tier1', '0.03'),
('basics_late_penalty_tier2', '0.05'),
('basics_late_penalty_tier3', '0.05'),
('basics_grace_period_days', '7'),
('basics_dormancy_weeks', '3');

-- Basics product master sheet (SRP list) — 41 SKUs across 3 categories.
-- No prices were provided; srp=0 until filled in via the admin panel.
INSERT INTO basics_products (sku, category, name, unit, srp) VALUES
('R001','Bigas','Regular Rice - Economy','5kg',0),
('R002','Bigas','Dinorado Rice - Value','5kg',0),
('R003','Bigas','Jasmine Rice - Premium','5kg',0),
('R004','Bigas','Regular Rice - Economy','10kg',0),
('R005','Bigas','Dinorado Rice - Value','10kg',0),
('R006','Bigas','Jasmine Rice - Premium','10kg',0),
('B001','Pang-almusal','Great Taste White 3-in-1','Per Pack',0),
('B002','Pang-almusal','Kopiko Brown 3-in-1','Per Pack',0),
('B003','Pang-almusal','Kopiko Blanca 3-in-1','Per Pack',0),
('B004','Pang-almusal','Nescafé Creamy White Sugar Free','Per Pack',0),
('B005','Pang-almusal','San Mig Coffee Sugar Free','Per Pack',0),
('B006','Pang-almusal','Great Taste Granules','Bottle',0),
('B007','Pang-almusal','Nescafé Classic','Bottle',0),
('B008','Pang-almusal','Nescafé Gold','Bottle',0),
('B009','Pang-almusal','Coffee-Mate Creamer','170g',0),
('B010','Pang-almusal','White Sugar','1kg',0),
('B011','Pang-almusal','Brown Sugar','1kg',0),
('B012','Pang-almusal','Alaska Powdered Milk','300g',0),
('B013','Pang-almusal','Bear Brand Powdered Milk','320g',0),
('B014','Pang-almusal','Anchor Powdered Milk','400g',0),
('B015','Pang-almusal','Local Bakery Bread','Loaf',0),
('B016','Pang-almusal','Gardenia Bread','Loaf',0),
('B017','Pang-almusal','Walter Bread','Loaf',0),
('C001','Pang-ulam','Mega Sardines','155g',0),
('C002','Pang-ulam','555 Sardines','155g',0),
('C003','Pang-ulam','Ligo Sardines','155g',0),
('C004','Pang-ulam','Argentina Corned Beef','150g',0),
('C005','Pang-ulam','Highlands Corned Beef','150g',0),
('C006','Pang-ulam','Delimondo Corned Beef','150g',0),
('C007','Pang-ulam','Argentina Meat Loaf','170g',0),
('C008','Pang-ulam','CDO Meat Loaf','170g',0),
('C009','Pang-ulam','Purefoods Meat Loaf','170g',0),
('C010','Pang-ulam','Bingo Luncheon Meat','340g',0),
('C011','Pang-ulam','CDO Luncheon Meat','340g',0),
('C012','Pang-ulam','Purefoods Luncheon Meat','340g',0),
('C013','Pang-ulam','Mega Tuna','180g',0),
('C014','Pang-ulam','Century Tuna','180g',0),
('C015','Pang-ulam','San Marino Tuna','180g',0),
('C016','Pang-ulam','Youngstown Vienna Sausage','130g',0),
('C017','Pang-ulam','CDO Vienna Sausage','130g',0),
('C018','Pang-ulam','Purefoods Vienna Sausage','130g',0);
