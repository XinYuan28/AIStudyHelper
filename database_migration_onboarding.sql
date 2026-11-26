CREATE TABLE IF NOT EXISTS account_onboarding (
 user_id INT UNSIGNED NOT NULL PRIMARY KEY,
 privacy_version VARCHAR(20) NULL,
 privacy_accepted_at_utc DATETIME NULL,
 telegram_welcome_pending TINYINT(1) NOT NULL DEFAULT 0,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
