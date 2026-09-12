-- =====================================================================
-- JMC Foodies Basics — LIVE: add a restricted "staff" admin role and one
-- staff account. Staff can only review/approve membership applications and
-- orders (basics/admin/applications.php, application_view.php, orders.php,
-- order_view.php, delivery_receipt.php, kyc_view.php) — every other Basics
-- admin page (products, payments, members, settings, etc.) redirects staff
-- back to Applications. Enforced in includes/auth.php via
-- require_basics_admin_role(). Existing admins default to 'super_admin'
-- (unrestricted) since the new column's DEFAULT applies to existing rows.
--
-- Login: basicsstaff / ec19f5a2e0c7 — same credentials as the local dev
-- account already created. Change the password after first login.
-- =====================================================================

ALTER TABLE basics_admins ADD COLUMN role ENUM('super_admin','staff') NOT NULL DEFAULT 'super_admin' AFTER name;

INSERT INTO basics_admins (username, password_hash, name, role)
VALUES ('basicsstaff', '$2y$10$7Dwh2Jnrk89ShjudFjHAOOV8s/B.bye2jFZJ1vOj19zJRBtnJ48Gu', 'Basics Staff', 'staff');
