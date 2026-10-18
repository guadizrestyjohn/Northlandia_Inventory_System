SET @northlandia_photo_remarks_sql = IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'photo_documentation_records'
       AND COLUMN_NAME = 'remarks') = 0,
    'ALTER TABLE `photo_documentation_records` ADD COLUMN `remarks` TEXT NULL AFTER `original_name`',
    'SELECT 1'
);
PREPARE northlandia_photo_remarks_stmt FROM @northlandia_photo_remarks_sql;
EXECUTE northlandia_photo_remarks_stmt;
DEALLOCATE PREPARE northlandia_photo_remarks_stmt;