-- Add server-side login throttling buckets to an existing installation.
USE helpdesk_db;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
  scope          VARCHAR(80) PRIMARY KEY,
  attempts       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_started INT UNSIGNED NOT NULL,
  locked_until   INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
