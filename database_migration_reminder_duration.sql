USE ai_study_note;
SET @duration_column = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'study_reminders' AND COLUMN_NAME = 'duration_minutes'),
    'DO 0',
    'ALTER TABLE study_reminders ADD COLUMN duration_minutes INT UNSIGNED NOT NULL DEFAULT 30'
);
PREPARE duration_column FROM @duration_column;
EXECUTE duration_column;
DEALLOCATE PREPARE duration_column;
