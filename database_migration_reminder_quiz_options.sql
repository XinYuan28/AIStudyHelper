USE ai_study_note;
SET @lead_column = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='study_reminders' AND COLUMN_NAME='lead_minutes'),
    'DO 0', 'ALTER TABLE study_reminders ADD COLUMN lead_minutes INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE lead_column FROM @lead_column;
EXECUTE lead_column;
DEALLOCATE PREPARE lead_column;

ALTER TABLE quiz_results MODIFY question_type ENUM('mcq','text','dual') NOT NULL DEFAULT 'mcq';
SET @section_column = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quiz_results' AND COLUMN_NAME='section_count'),
    'DO 0', 'ALTER TABLE quiz_results ADD COLUMN section_count TINYINT UNSIGNED NOT NULL DEFAULT 1'
);
PREPARE section_column FROM @section_column;
EXECUTE section_column;
DEALLOCATE PREPARE section_column;