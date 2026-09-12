-- =====================================================================
-- JMC Digital — LIVE migration: Basics order approval step.
--
-- 1. Adds 'confirmed' to basics_orders.status, between 'pending' (member
--    placed the order, awaiting admin approval) and 'paid' (payment
--    recorded). Admin must now explicitly Approve a pending order before
--    payment can be recorded against it or the member can submit payment
--    proof — matches the code already deployed for this.
--
-- 2. Fixes a real bug that will otherwise block every payment recording
--    once orders start flowing through the new 'confirmed' step:
--    activity_log is still latin1-charset, so any log message containing
--    a ₱ symbol (e.g. "Recorded on-time payment of ₱1,210.52...") fails
--    with "Incorrect string value" and rolls back the whole payment
--    transaction. Converting to utf8mb4 fixes this — safe, additive,
--    no data loss (existing rows are plain ASCII).
-- =====================================================================

ALTER TABLE basics_orders MODIFY COLUMN status ENUM('draft','pending','confirmed','paid','delivered','cancelled') NOT NULL DEFAULT 'draft';

ALTER TABLE activity_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
