USE ai_study_note;

CREATE TABLE IF NOT EXISTS study_reminders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    note_id INT UNSIGNED NULL,
    description TEXT NULL,
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    scheduled_at_utc DATETIME NOT NULL,
    time_zone VARCHAR(64) NOT NULL,
    delivery_method ENUM('calendar', 'telegram') NOT NULL,
    cancelled_at_utc DATETIME NULL,
    telegram_sent_at_utc DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_study_reminders_user_schedule (user_id, scheduled_at_utc),
    KEY idx_study_reminders_due (delivery_method, cancelled_at_utc, telegram_sent_at_utc, scheduled_at_utc),
    CONSTRAINT fk_reminder_note FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE SET NULL,
    CONSTRAINT fk_study_reminders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
