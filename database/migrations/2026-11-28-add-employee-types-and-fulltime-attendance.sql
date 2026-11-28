-- Adds employee classification and allows attendance without a manual assignment.
-- Existing employee records default to FULLTIME as requested; review Intern records
-- and update loai_nhan_vien after applying this migration.

SET @employee_type_column_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nhan_vien'
          AND COLUMN_NAME = 'loai_nhan_vien'
    ),
    'SELECT 1',
    'ALTER TABLE nhan_vien ADD COLUMN loai_nhan_vien ENUM(''FULLTIME'',''INTERN'') NOT NULL DEFAULT ''FULLTIME'' AFTER chuc_vu'
);
PREPARE employee_type_column_statement FROM @employee_type_column_sql;
EXECUTE employee_type_column_statement;
DEALLOCATE PREPARE employee_type_column_statement;

SET @attendance_date_column_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cham_cong'
          AND COLUMN_NAME = 'ngay_lam'
    ),
    'SELECT 1',
    'ALTER TABLE cham_cong ADD COLUMN ngay_lam DATE NULL AFTER phan_ca_id'
);
PREPARE attendance_date_column_statement FROM @attendance_date_column_sql;
EXECUTE attendance_date_column_statement;
DEALLOCATE PREPARE attendance_date_column_statement;

UPDATE cham_cong c
INNER JOIN phan_ca a ON a.id = c.phan_ca_id
SET c.ngay_lam = a.ngay_lam
WHERE c.ngay_lam IS NULL;

SET @assignment_nullable_sql = (
    SELECT IF(
        IS_NULLABLE = 'NO',
        CONCAT(
            'ALTER TABLE cham_cong MODIFY COLUMN phan_ca_id ',
            COLUMN_TYPE,
            ' NULL'
        ),
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cham_cong'
      AND COLUMN_NAME = 'phan_ca_id'
    LIMIT 1
);
SET @assignment_nullable_sql = COALESCE(@assignment_nullable_sql, 'SELECT 1');
PREPARE assignment_nullable_statement FROM @assignment_nullable_sql;
EXECUTE assignment_nullable_statement;
DEALLOCATE PREPARE assignment_nullable_statement;

SET @attendance_employee_date_index_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cham_cong'
          AND INDEX_NAME = 'idx_cham_cong_employee_date'
    ),
    'SELECT 1',
    'CREATE INDEX idx_cham_cong_employee_date ON cham_cong (nhan_vien_id, ngay_lam)'
);
PREPARE attendance_employee_date_index_statement FROM @attendance_employee_date_index_sql;
EXECUTE attendance_employee_date_index_statement;
DEALLOCATE PREPARE attendance_employee_date_index_statement;
