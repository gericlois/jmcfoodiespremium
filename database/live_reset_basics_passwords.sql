-- =====================================================================
-- JMC Digital — LIVE migration: reset all Basics member passwords to the
-- pattern "basic" + lowercase(last name), e.g. Julius Menor -> basicmenor.
--
-- Also sets must_change_password = 1, so each member is forced through
-- the existing basics/change_password.php flow on next login — they log
-- in with this temporary password, then must immediately set a real one
-- of their own choosing. This reuses infrastructure that already exists;
-- no new gating logic needed.
--
-- NOTE ON COMPOUND SURNAMES: the pattern took the LAST WORD of full_name
-- only. Two accounts have multi-word surnames where this may not match
-- what you'd expect:
--   - id 15, "maria cristina S. De Guzman" -> basicguzman (not basicdeguzman)
--   - id 20, "Mary Joy Del Prado Dela cruz" -> basiccruz (not basicdelacruz)
-- If you'd rather those two use the full compound surname, tell me and
-- I'll regenerate just those two rows.
--
-- Run in phpMyAdmin's SQL tab AFTER the account-separation migration
-- (basics_users must already exist) — these ids match the same 10 real
-- Basics-only accounts from the Aug 28, 2026 live export.
-- =====================================================================

-- id=10, full_name="Test Basics User", plaintext="basicuser"
UPDATE basics_users SET password_hash = '$2y$10$JQujecpKvjN/YAXXk3yBgO2UY3sojGVn.arLtUFGwEOTnYruzG6/W', must_change_password = 1 WHERE id = 10;

-- id=11, full_name="Julius Menor", plaintext="basicmenor"
UPDATE basics_users SET password_hash = '$2y$10$69tPeL/JcSJnjrJjJm.w7uKWI0y0nkkknPIAEMjEcJrgKiF7qTpai', must_change_password = 1 WHERE id = 11;

-- id=13, full_name="Daisy D.G Briones", plaintext="basicbriones"
UPDATE basics_users SET password_hash = '$2y$10$ts6oBVZTxTCWUEIWKT/37ODiaxFXASBeD4eyAuABISEolUxhVt.ki', must_change_password = 1 WHERE id = 13;

-- id=14, full_name="Maricel nava", plaintext="basicnava"
UPDATE basics_users SET password_hash = '$2y$10$TCAaLdMLZULw1OF/Dyk08uHSbPX89QNyeiLj6RkR1zCs8jW9ZMVaC', must_change_password = 1 WHERE id = 14;

-- id=15, full_name="maria cristina S. De Guzman", plaintext="basicguzman"
UPDATE basics_users SET password_hash = '$2y$10$.9nd7vPNEMEoBwDmauBcH./YN0savsw5nyooVluyXy0EvD5epFAWG', must_change_password = 1 WHERE id = 15;

-- id=16, full_name="Danica May Briones Quindara", plaintext="basicquindara"
UPDATE basics_users SET password_hash = '$2y$10$ioziH6pBov2FTYdHJRMta.bf7fBaG4TvLYwOEGRmkSh1IcRXQzm.a', must_change_password = 1 WHERE id = 16;

-- id=17, full_name="Faustina Pestaño", plaintext="basicpestaño"
UPDATE basics_users SET password_hash = '$2y$10$j/qR5cm9F8C3Z/MN4tlc2OviirY362YVhXBCxA0uBrqCKUhDWZ7Qi', must_change_password = 1 WHERE id = 17;

-- id=19, full_name="Rona Ramos Santos", plaintext="basicsantos"
UPDATE basics_users SET password_hash = '$2y$10$apPo3D1zyhtGiHbEduenyuOmW2n25CJwpDqF1JhejAAOPvjW02FFe', must_change_password = 1 WHERE id = 19;

-- id=20, full_name="Mary Joy Del Prado Dela cruz", plaintext="basiccruz"
UPDATE basics_users SET password_hash = '$2y$10$2kBEXGjKs9IQ2DSlaHY1AOI74qHszOs3TFfiWmSRoBrISiuDAN3JS', must_change_password = 1 WHERE id = 20;

-- id=21, full_name="Joshua mendoza pansoy", plaintext="basicpansoy"
UPDATE basics_users SET password_hash = '$2y$10$fM1wkjskZdXf2kY4yoA6/e3KIjUmJBAnAQ0uDmcEcOn5oTF/KRjzy', must_change_password = 1 WHERE id = 21;
