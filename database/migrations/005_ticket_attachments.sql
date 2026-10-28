-- ============================================================
--  Image attachments on tickets (student and faculty)
--  Run this BEFORE deploying the code that uses it.
--  Select your database first (phpMyAdmin), or:
--  mysql -u root helpdesk_db < database/migrations/005_ticket_attachments.sql
-- ============================================================
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
