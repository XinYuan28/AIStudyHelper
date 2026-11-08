USE ai_study_note;

CREATE TABLE IF NOT EXISTS note_quizzes (
    note_id INT UNSIGNED NOT NULL,
    questions JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (note_id),
    CONSTRAINT fk_note_quizzes_note FOREIGN KEY (note_id) REFERENCES notes (id) ON DELETE CASCADE
) ENGINE=InnoDB;
