USE ai_study_note;

CREATE TABLE IF NOT EXISTS telegram_connections (
    user_id INT UNSIGNED NOT NULL,
    chat_id BIGINT NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_telegram_chat (chat_id),
    CONSTRAINT fk_telegram_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS telegram_link_tokens (
    token_hash CHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    expires_at_utc DATETIME NOT NULL,
    PRIMARY KEY (token_hash),
    UNIQUE KEY uq_telegram_link_user (user_id),
    CONSTRAINT fk_telegram_link_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS telegram_worker_state (
    id TINYINT UNSIGNED NOT NULL,
    next_update_id BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT IGNORE INTO telegram_worker_state (id) VALUES (1);
