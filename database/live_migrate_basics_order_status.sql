-- =====================================================================
-- JMC Digital — LIVE migration: replace basics_orders.status's old value
-- set ('draft','placed','delivered','cancelled') with the new one
-- ('pending','confirmed','paid','out for delivery','delivered','cancelled')
-- and split the old single-step "placed -> delivered" admin action into
-- confirm -> (pay) -> out for delivery -> delivered, matching what's
-- already built & tested in local dev.
--
-- New lifecycle: a cart is a row with status='pending' and placed_at NULL;
-- placing the order sets placed_at but leaves status='pending' (now meaning
-- "awaiting admin confirmation"); admin confirms -> 'confirmed'; once
-- payments cover the full total_amount the order auto-flips to 'paid';
-- admin dispatches -> 'out for delivery'; admin marks received ->
-- 'delivered'. 'cancelled' is kept for admin use even though it's not one
-- of the five member-facing values.
--
-- CRITICAL ORDERING: run this SQL FIRST, in the same maintenance window,
-- immediately followed by uploading the changed PHP files (basics/**). The
-- new code writes/reads status values ('pending', 'confirmed', 'paid',
-- 'out for delivery') that do not exist in the live ENUM until this script
-- runs — uploading the code first will break order placement/lookup.
--
-- Paste this ENTIRE script into phpMyAdmin's SQL tab and run it as one
-- batch (do NOT use the Import tab — it has silently failed before on
-- this host).
-- =====================================================================

-- 1. New timestamp columns for the two new admin steps. Additive, safe.
ALTER TABLE basics_orders
    ADD COLUMN confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER placed_at,
    ADD COLUMN out_for_delivery_at TIMESTAMP NULL DEFAULT NULL AFTER confirmed_at;

-- 2. Widen the ENUM to hold both the old and new values at once, so
--    existing rows stay valid while we remap them in step 3.
ALTER TABLE basics_orders
    MODIFY COLUMN status ENUM('draft','placed','delivered','cancelled','pending','confirmed','paid','out for delivery') NOT NULL DEFAULT 'draft';

-- 3. Remap existing rows to the new values.
--    - draft (in-progress cart) -> pending, placed_at already NULL.
--    - placed (checked out, awaiting payment/delivery under the old flow)
--      -> paid if already fully paid, else confirmed (skips the new
--      "awaiting admin confirmation" step for orders that already made it
--      this far live — nothing to re-confirm).
--    - delivered / cancelled are unchanged (already valid values).
UPDATE basics_orders SET status = 'pending' WHERE status = 'draft';

UPDATE basics_orders o
    SET o.status = IF(
        (SELECT COALESCE(SUM(p.amount_paid), 0) FROM basics_payments p WHERE p.order_id = o.id) >= o.total_amount,
        'paid',
        'confirmed'
    )
    WHERE o.status = 'placed';

-- 4. Narrow the ENUM to the final six values and flip the default.
ALTER TABLE basics_orders
    MODIFY COLUMN status ENUM('pending','confirmed','paid','out for delivery','delivered','cancelled') NOT NULL DEFAULT 'pending';
