USE ai_study_note;
SET @trash_index = IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND INDEX_NAME = 'idx_notes_deleted'),
    'DO 0', 'ALTER TABLE notes ADD KEY idx_notes_deleted (deleted_at, id)'
);
PREPARE trash_index FROM @trash_index;
EXECUTE trash_index;
DEALLOCATE PREPARE trash_index;