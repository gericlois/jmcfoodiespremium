-- =====================================================================
-- JMC Digital — LIVE migration: communication_log table.
--
-- Records every SMS/email send attempt (channel, recipient, subject,
-- message, sent/failed status, and who sent it) — both automatic
-- triggers (order confirmations, payment reminders, etc.) and
-- admin-initiated ones (individual member message, Announcement
-- Broadcast). Populated automatically by send_sms()/send_email() in
-- includes/functions.php — no other code needs to change to start
-- logging once this table exists.
--
-- Viewable at admin/communication_log.php (Wellness) and
-- basics/admin/communication_log.php (Basics), filtered by module.
-- =====================================================================

CREATE TABLE communication_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('sms','email') NOT NULL,
    module ENUM('wellness','basics') DEFAULT NULL,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('sent','failed') NOT NULL,
    admin_id INT DEFAULT NULL,
    admin_type ENUM('wellness','basics') DEFAULT NULL,
    admin_name VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
