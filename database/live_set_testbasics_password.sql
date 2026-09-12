-- =====================================================================
-- JMC Digital — LIVE: set testbasics's password to "testbasics".
-- Same hash already verified working locally. must_change_password is
-- set to 0, so it logs straight into the dashboard, no forced reset.
-- =====================================================================

UPDATE basics_users
SET password_hash = '$2y$10$NwbOMyUxI9Y/Vun.Q0S/ZetARKQLyTot/JBFTsYTQywhjJzN82ziO', must_change_password = 0
WHERE username = 'testbasics';
