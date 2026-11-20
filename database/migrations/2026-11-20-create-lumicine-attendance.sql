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

CREATE TABLE IF NOT EXISTS lumicine_attendance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_at DATETIME NULL,
    check_out_at DATETIME NULL,
    last_punch_type ENUM('check_in', 'check_out') NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY lumicine_attendance_user_date_unique (user_id, attendance_date),
    CONSTRAINT lumicine_attendance_user_fk
        FOREIGN KEY (user_id) REFERENCES lumicine_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
