USE ai_study_note;

SET @reminder_note_column = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'study_reminders' AND COLUMN_NAME = 'note_id'),
    'DO 0',
    'ALTER TABLE study_reminders ADD COLUMN note_id INT UNSIGNED NULL, ADD CONSTRAINT fk_reminder_note FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE SET NULL'
);
PREPARE reminder_note_column FROM @reminder_note_column;
EXECUTE reminder_note_column;
DEALLOCATE PREPARE reminder_note_column;

SET @reminder_description_column = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'study_reminders' AND COLUMN_NAME = 'description'),
    'DO 0',
    "ALTER TABLE study_reminders ADD COLUMN description TEXT NULL"
);
PREPARE reminder_description_column FROM @reminder_description_column;
EXECUTE reminder_description_column;
DEALLOCATE PREPARE reminder_description_column;
