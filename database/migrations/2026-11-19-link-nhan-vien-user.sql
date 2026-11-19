SET @employee_user_column_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nhan_vien'
          AND COLUMN_NAME = 'user_id'
    ),
    'SELECT 1',
    'ALTER TABLE nhan_vien ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id'
);

PREPARE employee_user_column_statement FROM @employee_user_column_sql;
EXECUTE employee_user_column_statement;
DEALLOCATE PREPARE employee_user_column_statement;

SET @employee_user_unique_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nhan_vien'
          AND INDEX_NAME = 'nhan_vien_user_unique'
    ),
    'SELECT 1',
    'ALTER TABLE nhan_vien ADD UNIQUE KEY nhan_vien_user_unique (user_id)'
);

PREPARE employee_user_unique_statement FROM @employee_user_unique_sql;
EXECUTE employee_user_unique_statement;
DEALLOCATE PREPARE employee_user_unique_statement;

SET @employee_user_foreign_key_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nhan_vien'
          AND CONSTRAINT_NAME = 'nhan_vien_user_foreign'
    ),
    'SELECT 1',
    'ALTER TABLE nhan_vien
     ADD CONSTRAINT nhan_vien_user_foreign
     FOREIGN KEY (user_id) REFERENCES lumicine_users(id)
     ON UPDATE CASCADE
     ON DELETE SET NULL'
);

PREPARE employee_user_foreign_key_statement FROM @employee_user_foreign_key_sql;
EXECUTE employee_user_foreign_key_statement;
DEALLOCATE PREPARE employee_user_foreign_key_statement;
