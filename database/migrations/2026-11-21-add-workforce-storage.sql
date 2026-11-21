SET @employee_code_column_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lumicine_users'
          AND COLUMN_NAME = 'employee_code'
    ),
    'SELECT 1',
    'ALTER TABLE lumicine_users ADD COLUMN employee_code VARCHAR(32) NULL AFTER display_name'
);

PREPARE employee_code_column_statement FROM @employee_code_column_sql;
EXECUTE employee_code_column_statement;
DEALLOCATE PREPARE employee_code_column_statement;

SET @employee_code_index_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lumicine_users'
          AND INDEX_NAME = 'lumicine_users_employee_code_unique'
    ),
    'SELECT 1',
    'CREATE UNIQUE INDEX lumicine_users_employee_code_unique ON lumicine_users (employee_code)'
);

PREPARE employee_code_index_statement FROM @employee_code_index_sql;
EXECUTE employee_code_index_statement;
DEALLOCATE PREPARE employee_code_index_statement;

SET @assignment_location_sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'phan_ca'
          AND COLUMN_NAME = 'dia_diem'
    ),
    'SELECT 1',
    'ALTER TABLE phan_ca ADD COLUMN dia_diem VARCHAR(100) NULL AFTER ngay_lam'
);

PREPARE assignment_location_statement FROM @assignment_location_sql;
EXECUTE assignment_location_statement;
DEALLOCATE PREPARE assignment_location_statement;

CREATE TABLE IF NOT EXISTS lumicine_holidays (
    ngay DATE NOT NULL,
    ten_ngay VARCHAR(150) NOT NULL,
    loai_ngay ENUM('holiday', 'workday') NOT NULL DEFAULT 'holiday',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ngay)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
