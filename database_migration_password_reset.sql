CREATE TABLE IF NOT EXISTS password_reset_tokens (
 user_id INT UNSIGNED NOT NULL PRIMARY KEY,
 token_hash CHAR(64) NOT NULL UNIQUE,
 password_hash_at_issue VARCHAR(255) NOT NULL,
 created_at_utc DATETIME NOT NULL,
 expires_at_utc DATETIME NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
