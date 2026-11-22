-- Add HR reporting fields without removing or rewriting existing records.

SET @employee_termination_column_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nhan_vien'
          AND COLUMN_NAME = 'ngay_nghi_viec'
    ),
    'SELECT 1',
    'ALTER TABLE nhan_vien ADD COLUMN ngay_nghi_viec DATE NULL AFTER trang_thai'
);
PREPARE employee_termination_column_statement FROM @employee_termination_column_sql;
EXECUTE employee_termination_column_statement;
DEALLOCATE PREPARE employee_termination_column_statement;

SET @leave_type_column_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lumicine_leave_requests'
          AND COLUMN_NAME = 'loai_nghi'
    ),
    'SELECT 1',
    'ALTER TABLE lumicine_leave_requests ADD COLUMN loai_nghi VARCHAR(32) NOT NULL DEFAULT ''KHAC'' AFTER gio_ket_thuc'
);
PREPARE leave_type_column_statement FROM @leave_type_column_sql;
EXECUTE leave_type_column_statement;
DEALLOCATE PREPARE leave_type_column_statement;
