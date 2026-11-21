SET @overtime_employee_column_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tang_ca'
          AND COLUMN_NAME = 'nhan_vien_id'
    ),
    'SELECT 1',
    'ALTER TABLE tang_ca ADD COLUMN nhan_vien_id INT NULL AFTER cham_cong_id'
);
PREPARE overtime_employee_column_statement FROM @overtime_employee_column_sql;
EXECUTE overtime_employee_column_statement;
DEALLOCATE PREPARE overtime_employee_column_statement;

UPDATE tang_ca o
INNER JOIN cham_cong c ON c.id = o.cham_cong_id
SET o.nhan_vien_id = c.nhan_vien_id
WHERE o.nhan_vien_id IS NULL;

ALTER TABLE tang_ca MODIFY cham_cong_id INT NULL;

SET @overtime_employee_index_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tang_ca'
          AND INDEX_NAME = 'fk_tangca_nhanvien'
    ),
    'SELECT 1',
    'ALTER TABLE tang_ca ADD KEY fk_tangca_nhanvien (nhan_vien_id)'
);
PREPARE overtime_employee_index_statement FROM @overtime_employee_index_sql;
EXECUTE overtime_employee_index_statement;
DEALLOCATE PREPARE overtime_employee_index_statement;

SET @overtime_employee_fk_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tang_ca'
          AND CONSTRAINT_NAME = 'fk_tangca_nhanvien'
    ),
    'SELECT 1',
    'ALTER TABLE tang_ca ADD CONSTRAINT fk_tangca_nhanvien FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id) ON DELETE CASCADE'
);
PREPARE overtime_employee_fk_statement FROM @overtime_employee_fk_sql;
EXECUTE overtime_employee_fk_statement;
DEALLOCATE PREPARE overtime_employee_fk_statement;
