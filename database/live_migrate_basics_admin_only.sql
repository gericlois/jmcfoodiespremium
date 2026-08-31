-- =====================================================================
-- JMC Digital — LIVE migration: admin accounts for BOTH sides, idempotent.
--
-- Safe to run whether the accounts already exist or not:
--   - Wellness: `admins` table already exists live — this resets jmcadmin's
--     password to the known value if the row exists, or creates it if not.
--   - Basics: creates `basics_admins` if it doesn't exist yet, and resets/
--     creates the basicsadmin row the same way.
-- Uses INSERT ... ON DUPLICATE KEY UPDATE (keyed on the `username` unique
-- constraint), so re-running this script is harmless — it will not error
-- on a duplicate, it just overwrites the password/name back to these
-- values. Does not touch `users`, `basics_members`, or any other table.
--
-- Credentials (same as your local dev copies):
--   Wellness admin: jmcadmin / AdminJMC#2026
--   Basics admin:   basicsadmin / BasicsAdmin2026!
--
-- IMPORTANT LIMITATION: this alone is not enough for the Basics admin
-- panel to actually work. The new code (basics/admin/*.php) joins against
-- a `basics_users` table for every member-facing page (Applications,
-- Members, Orders, Payments, etc.) — that table doesn't exist until you
-- also run the member-side migration (database/live_migrate_account_
-- separation.sql). Without it, basicsadmin can log in, but every other
-- admin page will error.
-- =====================================================================

CREATE TABLE IF NOT EXISTS basics_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO basics_admins (username, password_hash, name) VALUES
('basicsadmin', '$2y$10$0MY.czmOiS6deRkpxg.o.OpfvbXzupy2C8ZzUIaueqoXUBQ68cRay', 'Basics Administrator')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name);

INSERT INTO admins (username, password_hash, name) VALUES
('jmcadmin', '$2y$10$b6NL0VAz2LLzMf/hyM/RMOuvjT5TJWIfdIl0H1cWLhFhIzpypi2NS', 'Administrator')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name);
