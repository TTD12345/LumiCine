SET @employee_position_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nhan_vien'
          AND COLUMN_NAME = 'chuc_vu'
    ),
    'SELECT 1',
    'ALTER TABLE nhan_vien ADD COLUMN chuc_vu VARCHAR(100) NULL AFTER ho_ten'
);

PREPARE employee_position_statement FROM @employee_position_sql;
EXECUTE employee_position_statement;
DEALLOCATE PREPARE employee_position_statement;
