-- ============================================================
--  Online status for the Virtual IT Office (dashboard)
--  Run this BEFORE deploying the code that uses it.
--  Select your database first (phpMyAdmin), or:
--  mysql -u root helpdesk_db < database/migrations/003_presence.sql
-- ============================================================
ALTER TABLE users
  ADD COLUMN last_seen_at DATETIME NULL COMMENT 'Last page view; drives online status in the virtual office' AFTER last_login_at;
