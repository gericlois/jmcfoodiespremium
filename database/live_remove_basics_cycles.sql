-- =====================================================================
-- JMC Foodies Basics — LIVE: remove the weekly ordering cycle concept.
-- Ordering is now continuous (no order open/cutoff window), delivery is
-- scheduled per order rather than per cycle, and payment due dates are
-- computed per order (delivered_at + 7 days, see basics_payment_due_date()
-- in basics/includes/functions.php) instead of coming from a shared cycle
-- date. Run this once against the live database as part of this deploy.
-- =====================================================================

ALTER TABLE basics_orders
  DROP FOREIGN KEY basics_orders_ibfk_2,
  DROP INDEX member_id,
  DROP INDEX cycle_id,
  ADD INDEX member_id (member_id),
  DROP COLUMN cycle_id;

DROP TABLE basics_cycles;
