SET @must_change_password_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'lumicine_users'
      AND COLUMN_NAME = 'must_change_password'
);

SET @must_change_password_column_sql = IF(
    @must_change_password_column_exists = 0,
    'ALTER TABLE lumicine_users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash',
    'SELECT 1'
);
PREPARE must_change_password_column_statement FROM @must_change_password_column_sql;
EXECUTE must_change_password_column_statement;
DEALLOCATE PREPARE must_change_password_column_statement;

SET @mark_existing_employee_accounts_sql = IF(
    @must_change_password_column_exists = 0,
    'UPDATE lumicine_users SET must_change_password = 1 WHERE role = ''employee''',
    'SELECT 1'
);
PREPARE mark_existing_employee_accounts_statement FROM @mark_existing_employee_accounts_sql;
EXECUTE mark_existing_employee_accounts_statement;
DEALLOCATE PREPARE mark_existing_employee_accounts_statement;
