USE ai_study_note;

SET @profile_gender = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'gender'),
    'DO 0', 'ALTER TABLE users ADD COLUMN gender VARCHAR(20) NULL'
);
PREPARE profile_gender FROM @profile_gender;
EXECUTE profile_gender;
DEALLOCATE PREPARE profile_gender;

SET @profile_birthday = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'birthday'),
    'DO 0', 'ALTER TABLE users ADD COLUMN birthday DATE NULL'
);
PREPARE profile_birthday FROM @profile_birthday;
EXECUTE profile_birthday;
DEALLOCATE PREPARE profile_birthday;
