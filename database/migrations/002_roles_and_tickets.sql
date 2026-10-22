-- ============================================================
--  Upgrade from v1 (login/register only) to v2 (roles + ticketing)
--  mysql -u root helpdesk_db < database/migrations/002_roles_and_tickets.sql
-- ============================================================
USE helpdesk_db;

-- Old roles: user/technician/admin + user_type student/faculty/staff
ALTER TABLE users
  MODIFY role ENUM('user','student','lab','faculty','technician','admin') NOT NULL DEFAULT 'student';

UPDATE users
   SET role = IF(user_type = 'student', 'student', 'faculty')
 WHERE role = 'user';

ALTER TABLE users
  MODIFY role ENUM('student','lab','faculty','technician','admin') NOT NULL DEFAULT 'student',
  DROP COLUMN user_type,
  MODIFY department VARCHAR(100) NULL COMMENT 'Course, laboratory, office, or IT team',
  ADD COLUMN phone VARCHAR(30) NULL AFTER department,
  ADD COLUMN created_by INT UNSIGNED NULL AFTER last_login_at,
  ADD KEY idx_users_role (role);

-- New tables, categories, and sample accounts (all idempotent)
SOURCE database/helpdesk_db.sql;
