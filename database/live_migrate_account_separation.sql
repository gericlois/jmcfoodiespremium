-- =====================================================================
-- JMC Digital — LIVE migration: split the shared login into two fully
-- separate account systems (Wellness: users/admins, Basics: basics_users/
-- basics_admins), matching what's already built & tested in local dev.
--
-- Paste this ENTIRE script into phpMyAdmin's SQL tab and run it as one
-- batch (do NOT use the Import tab — it has silently failed before on
-- this host). Verified safe against the live export from Aug 28, 2026:
--   - users has 14 rows; 10 (ids 10,11,13,14,15,16,17,19,20,21) have
--     wellness_enrolled = 0 and are Basics-only, each with exactly one
--     basics_members row (verified 1:1, zero orphans).
--   - basics_orders, basics_order_items, basics_payments, cashouts,
--     wallet_transactions are all currently EMPTY (0 rows) despite
--     nonzero AUTO_INCREMENT, so there is no cascade-delete risk to
--     real order/payment data from this migration.
--   - admins has 1 row (jmcadmin, id=1). No basics_admins table exists.
--
-- CRITICAL ORDERING: do NOT deploy this before uploading all the changed
-- PHP files (includes/auth.php, includes/functions.php, basics/**, the
-- new basics/login.php etc.). The live site currently runs on the OLD
-- shared-login code, which expects `users.wellness_enrolled` and will
-- break immediately once this SQL removes it. Upload the code FIRST,
-- then run this script, in the same maintenance window.
-- =====================================================================

-- 1. Create the two new tables (schema matches local dev exactly).
CREATE TABLE basics_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    birthdate DATE NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    email VARCHAR(190) NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE basics_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Seed the Basics admin login — SAME credentials as your local dev copy:
--    username "basicsadmin", password "BasicsAdmin2026!"
--    Explicitly id=1 so the id-preservation trick in step 4 below works.
INSERT INTO basics_admins (id, username, password_hash, name) VALUES
(1, 'basicsadmin', '$2y$10$0MY.czmOiS6deRkpxg.o.OpfvbXzupy2C8ZzUIaueqoXUBQ68cRay', 'Basics Administrator');

-- 3. Copy the 10 Basics-only accounts into basics_users, preserving their
--    exact ids so basics_members.user_id needs no value remapping at all —
--    only its FK target changes (in step 6), the numbers stay identical.
INSERT INTO basics_users (id, full_name, address, birthdate, contact_number, email, username, password_hash, must_change_password, status, created_at)
SELECT id, full_name, address, birthdate, contact_number, email, username, password_hash, must_change_password, status, created_at
FROM users WHERE wellness_enrolled = 0;

-- 4. Drop every FK that currently points into `users`/`admins` from
--    Basics-side tables. MUST happen before deleting rows from `users`
--    in step 7 — basics_members_ibfk_1 is ON DELETE CASCADE, so deleting
--    users first would silently wipe the matching basics_members rows
--    (and cascade further into any orders/payments under them).
ALTER TABLE basics_members DROP FOREIGN KEY basics_members_ibfk_1;
ALTER TABLE basics_members DROP FOREIGN KEY basics_members_ibfk_2;
ALTER TABLE basics_benefit_requests DROP FOREIGN KEY basics_benefit_requests_ibfk_2;
ALTER TABLE basics_emergency_credit_requests DROP FOREIGN KEY basics_emergency_credit_requests_ibfk_2;
ALTER TABLE basics_payments DROP FOREIGN KEY basics_payments_ibfk_3;
ALTER TABLE basics_payment_submissions DROP FOREIGN KEY basics_payment_submissions_ibfk_4;
ALTER TABLE activity_log DROP FOREIGN KEY activity_log_ibfk_1;

-- 5. Re-add those FKs pointing at the new tables instead. Because the
--    basicsadmin seed row above is id=1 — the same id jmcadmin had in
--    `admins` — any existing reviewed_by/recorded_by value of 1 now
--    correctly resolves to the new basics_admins row with no data
--    rewrite needed (only basics_members #4 currently has such a value;
--    every other reviewed_by/recorded_by column is empty right now).
ALTER TABLE basics_members
    ADD CONSTRAINT basics_members_ibfk_1 FOREIGN KEY (user_id) REFERENCES basics_users(id) ON DELETE CASCADE,
    ADD CONSTRAINT basics_members_ibfk_2 FOREIGN KEY (reviewed_by) REFERENCES basics_admins(id) ON DELETE SET NULL;
ALTER TABLE basics_benefit_requests
    ADD CONSTRAINT basics_benefit_requests_ibfk_2 FOREIGN KEY (reviewed_by) REFERENCES basics_admins(id) ON DELETE SET NULL;
ALTER TABLE basics_emergency_credit_requests
    ADD CONSTRAINT basics_emergency_credit_requests_ibfk_2 FOREIGN KEY (reviewed_by) REFERENCES basics_admins(id) ON DELETE SET NULL;
ALTER TABLE basics_payments
    ADD CONSTRAINT basics_payments_ibfk_3 FOREIGN KEY (recorded_by) REFERENCES basics_admins(id) ON DELETE SET NULL;
ALTER TABLE basics_payment_submissions
    ADD CONSTRAINT basics_payment_submissions_ibfk_4 FOREIGN KEY (reviewed_by) REFERENCES basics_admins(id) ON DELETE SET NULL;

-- 6. activity_log: add the disambiguation column (no FK re-added here —
--    admin_id can now point into either admins or basics_admins, so it's
--    deliberately left unconstrained, matching local dev's schema).
ALTER TABLE activity_log ADD COLUMN admin_type ENUM('wellness','basics') DEFAULT NULL AFTER admin_id;
UPDATE activity_log SET admin_type = 'wellness' WHERE admin_id IS NOT NULL;

-- 7. Now safe to remove the migrated accounts from `users` (their data
--    now lives in basics_users, and the FK that used to cascade off of
--    them was dropped in step 4) and drop the now-unused flag column.
DELETE FROM users WHERE wellness_enrolled = 0;
ALTER TABLE users DROP COLUMN wellness_enrolled;
