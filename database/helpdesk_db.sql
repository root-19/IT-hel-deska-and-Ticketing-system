-- ============================================================
--  ICCT IT Help Desk & Ticketing System
--  Database schema (MySQL / MariaDB) - fresh install
--  Import via phpMyAdmin or:  mysql -u root < helpdesk_db.sql
--  Upgrading an existing install? Run migrations/002_roles_and_tickets.sql instead.
--  Then run migrations/004_knowledge_base.sql to add the AI assistant's knowledge base,
--  and migrations/005_ticket_attachments.sql for ticket image uploads.
-- ============================================================

CREATE DATABASE IF NOT EXISTS helpdesk_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE helpdesk_db;

-- ------------------------------------------------------------
--  Users
--  student     - self-registers, reports student portal / account issues
--  lab         - Computer Laboratory account (created by admin)
--  faculty     - Faculty & Staff account (created by admin)
--  technician  - IT personnel who resolve assigned tickets (created by admin)
--  admin       - IT Admin, manages tickets, accounts, and reports
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_number      VARCHAR(30)  NOT NULL UNIQUE,
  first_name     VARCHAR(60)  NOT NULL,
  last_name      VARCHAR(60)  NOT NULL,
  email          VARCHAR(120) NOT NULL UNIQUE,
  role           ENUM('student','lab','faculty','technician','admin') NOT NULL DEFAULT 'student',
  department     VARCHAR(100) NULL COMMENT 'Course, laboratory, office, or IT team',
  phone          VARCHAR(30)  NULL,
  password_hash  VARCHAR(255) NOT NULL,
  status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at  DATETIME NULL,
  last_seen_at   DATETIME NULL COMMENT 'Last page view; drives online status in the virtual office',
  created_by     INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_users_role (role)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Ticket categories, grouped by which account type can use them
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(50)  NOT NULL UNIQUE,
  name         VARCHAR(80)  NOT NULL,
  description  VARCHAR(200) NOT NULL,
  audience     ENUM('student','lab','faculty') NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tickets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requester_id  INT UNSIGNED NOT NULL,
  category_id   SMALLINT UNSIGNED NOT NULL,
  subject       VARCHAR(150) NOT NULL,
  description   TEXT NOT NULL,
  location      VARCHAR(120) NULL,
  priority      ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status        ENUM('open','assigned','in_progress','on_hold','resolved','closed') NOT NULL DEFAULT 'open',
  assigned_to   INT UNSIGNED NULL,
  assigned_at   DATETIME NULL,
  resolved_at   DATETIME NULL,
  closed_at     DATETIME NULL,
  rating        TINYINT UNSIGNED NULL,
  feedback      VARCHAR(500) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tickets_status (status),
  KEY idx_tickets_assigned (assigned_to),
  KEY idx_tickets_created (created_at),
  CONSTRAINT fk_tickets_requester FOREIGN KEY (requester_id) REFERENCES users (id),
  CONSTRAINT fk_tickets_category  FOREIGN KEY (category_id)  REFERENCES categories (id),
  CONSTRAINT fk_tickets_assignee  FOREIGN KEY (assigned_to)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Timeline of everything that happens to a ticket
CREATE TABLE IF NOT EXISTS ticket_activity (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('created','comment','internal_note','status','assignment','priority','feedback') NOT NULL,
  body        TEXT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_ticket (ticket_id),
  CONSTRAINT fk_activity_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_user   FOREIGN KEY (user_id)   REFERENCES users (id)
) ENGINE=InnoDB;

-- Images a requester attached when submitting a ticket (files live in uploads/tickets/)
CREATE TABLE IF NOT EXISTS ticket_attachments (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id      INT UNSIGNED NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  stored_name    VARCHAR(64)  NOT NULL UNIQUE COMMENT 'Random file name inside uploads/tickets/',
  original_name  VARCHAR(255) NOT NULL,
  mime_type      VARCHAR(50)  NOT NULL,
  size_bytes     INT UNSIGNED NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attachments_ticket (ticket_id),
  CONSTRAINT fk_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
  CONSTRAINT fk_attachments_user   FOREIGN KEY (user_id)   REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  ticket_id   INT UNSIGNED NULL,
  message     VARCHAR(255) NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notifications_user   FOREIGN KEY (user_id)   REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Categories
-- ------------------------------------------------------------
INSERT IGNORE INTO categories (slug, name, description, audience, sort_order) VALUES
  ('student_portal',   'Student Portal Problem',        'Can''t open grades, enrollment, schedules, or other portal pages.', 'student', 1),
  ('student_account',  'Login / Account Issue',         'Forgot password, locked account, or errors when logging in.',      'student', 2),
  ('student_assist',   'Technical Assistance',          'Help with your device, campus Wi-Fi, or school software.',          'student', 3),
  ('lab_computer',     'Broken / Defective Computer',   'Unit won''t boot, damaged hardware, or missing peripherals.',      'lab',     1),
  ('lab_internet',     'Internet / Network Problem',    'No connection or slow internet inside the laboratory.',            'lab',     2),
  ('lab_printer',      'Printer Problem',               'Paper jams, no printout, or low toner/ink.',                       'lab',     3),
  ('lab_software',     'Software Installation Request', 'Install or update software needed for classes.',                  'lab',     4),
  ('faculty_online',   'Online Class Problem',          'Issues with the LMS, video calls, or online class tools.',         'faculty', 1),
  ('faculty_systems',  'School Systems Issue',          'Problems with grading, attendance, or other school systems.',      'faculty', 2),
  ('faculty_account',  'Email / Account Request',       'New school email, password reset, or system access.',              'faculty', 3),
  ('faculty_equipment','Equipment Request',             'Projector, laptop, speakers, or other equipment.',                 'faculty', 4);

-- ------------------------------------------------------------
--  Default accounts - CHANGE THESE PASSWORDS before real use
--  admin@icct.edu.ph ........ Admin@123
--  all sample accounts ...... Password@123
-- ------------------------------------------------------------
INSERT IGNORE INTO users (id_number, first_name, last_name, email, role, department, password_hash) VALUES
  ('ADMIN-0001', 'System',   'Administrator', 'admin@icct.edu.ph',      'admin',      'IT Department',
   '$2y$10$ugN0LSJvq7V9EvfE11ZPc.UKeOg4TisrefgXAwI4cXMmjMnXBkNlm'),
  ('TECH-0001',  'Mark',     'Reyes',         'tech1@icct.edu.ph',      'technician', 'Hardware & Laboratory',
   '$2y$10$3Qr036A3jOQoEXb7Jm2RTuIGIvp/1qnrZYS/HCHK/CjrgPiIr013G'),
  ('TECH-0002',  'Ana',      'Santos',        'tech2@icct.edu.ph',      'technician', 'Systems & Accounts',
   '$2y$10$3Qr036A3jOQoEXb7Jm2RTuIGIvp/1qnrZYS/HCHK/CjrgPiIr013G'),
  ('LAB-0001',   'Computer', 'Laboratory 1',  'lab1@icct.edu.ph',       'lab',        'Computer Laboratory 1',
   '$2y$10$3Qr036A3jOQoEXb7Jm2RTuIGIvp/1qnrZYS/HCHK/CjrgPiIr013G'),
  ('FAC-0001',   'Maria',    'Cruz',          'faculty1@icct.edu.ph',   'faculty',    'College of Computer Studies',
   '$2y$10$3Qr036A3jOQoEXb7Jm2RTuIGIvp/1qnrZYS/HCHK/CjrgPiIr013G'),
  ('2024-00001', 'Juan',     'Dela Cruz',     'student1@icct.edu.ph',   'student',    'BS Information Technology',
   '$2y$10$3Qr036A3jOQoEXb7Jm2RTuIGIvp/1qnrZYS/HCHK/CjrgPiIr013G');
