-- =====================================================================
-- LIVE: add order archiving to both order systems.
-- `archived_at` (NULL = active/visible, set = archived/hidden from the
-- default list) lets admins declutter finished orders without deleting
-- them. Only orders in a terminal status can be archived — see
-- admin/orders.php + admin/order_view.php (Wellness: completed/cancelled)
-- and basics/admin/orders.php + basics/admin/order_view.php
-- (Basics: delivered/cancelled).
-- =====================================================================

ALTER TABLE orders ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER status;
ALTER TABLE basics_orders ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER status;
