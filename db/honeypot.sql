-- Honeypot event log (IS Project 1). Run: sudo mysql CAT2 < db/honeypot.sql
CREATE TABLE IF NOT EXISTS honeypot_hits (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  ip          VARCHAR(45)  NOT NULL,
  method      VARCHAR(10)  NOT NULL,
  path        VARCHAR(255) NOT NULL,
  user_agent  VARCHAR(255) NOT NULL DEFAULT '',
  username    VARCHAR(100) NOT NULL DEFAULT '',
  pw_len      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  attack_type VARCHAR(30)  NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_ip (ip)
);
