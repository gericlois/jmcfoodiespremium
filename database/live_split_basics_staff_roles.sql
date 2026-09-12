-- =====================================================================
-- JMC Foodies Basics — LIVE: split the single "staff" role into two
-- narrower roles.
--
-- staff_orders   — Applications, Manage Products, Manage Orders, Supplier
--                   Summary (+ kyc_view.php, delivery_receipt.php, product_edit.php)
-- staff_payments — Payments, Payment Reminders, Payment Submissions,
--                   Emergency Cash Credit, Member Benefits, Dormancy Report
--                   (+ payment_proof_view.php, benefit_document_view.php)
--
-- Enforced in the individual admin pages via require_basics_admin_role().
-- The existing basicsstaff account (previously plain "staff", scoped to
-- applications+orders only) is remapped to staff_orders since that was its
-- original job. A new basicsstaff2 account is created for staff_payments.
--
-- Login: basicsstaff2 / 550d3f6b2e16 — same credentials as the local dev
-- account already created. Change the password after first login.
-- =====================================================================

ALTER TABLE basics_admins MODIFY COLUMN role ENUM('super_admin','staff','staff_orders','staff_payments') NOT NULL DEFAULT 'super_admin';
UPDATE basics_admins SET role = 'staff_orders', name = 'Basics Staff (Orders)' WHERE role = 'staff';
ALTER TABLE basics_admins MODIFY COLUMN role ENUM('super_admin','staff_orders','staff_payments') NOT NULL DEFAULT 'super_admin';

INSERT INTO basics_admins (username, password_hash, name, role)
VALUES ('basicsstaff2', '$2y$10$bIcwpLtCigoG.XXlHLYQ6OdQRjkYh9u3YRJ5wbxxNm1JRZIFTUu7a', 'Basics Staff (Payments)', 'staff_payments');
