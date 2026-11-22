-- HRM foreign-key consistency migration.
-- Run once after backing up the database.
-- This migration never deletes records. Existing constraints are reused.

-- Keep employee_code as a compatible mapping while moving to a direct employee_id relation.
SET @employee_id_column_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lumicine_users'
          AND COLUMN_NAME = 'employee_id'
    ),
    'SELECT 1',
    'ALTER TABLE lumicine_users ADD COLUMN employee_id INT NULL AFTER employee_code'
);
PREPARE employee_id_column_statement FROM @employee_id_column_sql;
EXECUTE employee_id_column_statement;
DEALLOCATE PREPARE employee_id_column_statement;

UPDATE lumicine_users u
INNER JOIN nhan_vien e
    ON BINARY u.employee_code = BINARY e.ma_nhan_vien
SET u.employee_id = e.id
WHERE u.employee_id IS NULL;

SET @employee_id_unique_sql = IF(
    NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS current_index
        WHERE current_index.TABLE_SCHEMA = DATABASE()
          AND current_index.TABLE_NAME = 'lumicine_users'
          AND current_index.COLUMN_NAME = 'employee_id'
          AND current_index.NON_UNIQUE = 0
          AND (
              SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS same_index
              WHERE same_index.TABLE_SCHEMA = DATABASE()
                AND same_index.TABLE_NAME = 'lumicine_users'
                AND same_index.INDEX_NAME = current_index.INDEX_NAME
          ) = 1
    ),
    'CREATE UNIQUE INDEX lumicine_users_employee_id_unique ON lumicine_users (employee_id)',
    'SELECT 1'
);
PREPARE employee_id_unique_statement FROM @employee_id_unique_sql;
EXECUTE employee_id_unique_statement;
DEALLOCATE PREPARE employee_id_unique_statement;

SET @lumicine_user_employee_fk_sql = IF(
    NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'lumicine_users'
          AND COLUMN_NAME = 'employee_id'
          AND REFERENCED_TABLE_NAME = 'nhan_vien'
          AND REFERENCED_COLUMN_NAME = 'id'
    ),
    'ALTER TABLE lumicine_users ADD CONSTRAINT fk_lumicine_users_employee FOREIGN KEY (employee_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE lumicine_user_employee_fk_statement FROM @lumicine_user_employee_fk_sql;
EXECUTE lumicine_user_employee_fk_statement;
DEALLOCATE PREPARE lumicine_user_employee_fk_statement;

-- Each condition below verifies both tables/columns exist before adding a constraint.
SET @fk_phan_ca_shift_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phan_ca')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ca_lam_viec')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phan_ca' AND COLUMN_NAME = 'ca_lam_viec_id')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ca_lam_viec' AND COLUMN_NAME = 'id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phan_ca' AND COLUMN_NAME = 'ca_lam_viec_id' AND REFERENCED_TABLE_NAME = 'ca_lam_viec' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE phan_ca ADD CONSTRAINT fk_phan_ca_shift FOREIGN KEY (ca_lam_viec_id) REFERENCES ca_lam_viec(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_phan_ca_shift_statement FROM @fk_phan_ca_shift_sql;
EXECUTE fk_phan_ca_shift_statement;
DEALLOCATE PREPARE fk_phan_ca_shift_statement;

SET @fk_phan_ca_employee_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phan_ca')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phan_ca' AND COLUMN_NAME = 'nhan_vien_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phan_ca' AND COLUMN_NAME = 'nhan_vien_id' AND REFERENCED_TABLE_NAME = 'nhan_vien' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE phan_ca ADD CONSTRAINT fk_phan_ca_employee FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_phan_ca_employee_statement FROM @fk_phan_ca_employee_sql;
EXECUTE fk_phan_ca_employee_statement;
DEALLOCATE PREPARE fk_phan_ca_employee_statement;

SET @fk_cham_cong_employee_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong' AND COLUMN_NAME = 'nhan_vien_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong' AND COLUMN_NAME = 'nhan_vien_id' AND REFERENCED_TABLE_NAME = 'nhan_vien' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE cham_cong ADD CONSTRAINT fk_cham_cong_employee FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_cham_cong_employee_statement FROM @fk_cham_cong_employee_sql;
EXECUTE fk_cham_cong_employee_statement;
DEALLOCATE PREPARE fk_cham_cong_employee_statement;

SET @fk_cham_cong_assignment_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phan_ca')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong' AND COLUMN_NAME = 'phan_ca_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong' AND COLUMN_NAME = 'phan_ca_id' AND REFERENCED_TABLE_NAME = 'phan_ca' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE cham_cong ADD CONSTRAINT fk_cham_cong_assignment FOREIGN KEY (phan_ca_id) REFERENCES phan_ca(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_cham_cong_assignment_statement FROM @fk_cham_cong_assignment_sql;
EXECUTE fk_cham_cong_assignment_statement;
DEALLOCATE PREPARE fk_cham_cong_assignment_statement;

SET @fk_adjustment_attendance_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dieu_chinh_cham_cong')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dieu_chinh_cham_cong' AND COLUMN_NAME = 'cham_cong_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dieu_chinh_cham_cong' AND COLUMN_NAME = 'cham_cong_id' AND REFERENCED_TABLE_NAME = 'cham_cong' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE dieu_chinh_cham_cong ADD CONSTRAINT fk_dieu_chinh_cham_cong FOREIGN KEY (cham_cong_id) REFERENCES cham_cong(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_adjustment_attendance_statement FROM @fk_adjustment_attendance_sql;
EXECUTE fk_adjustment_attendance_statement;
DEALLOCATE PREPARE fk_adjustment_attendance_statement;

SET @fk_overtime_employee_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca' AND COLUMN_NAME = 'nhan_vien_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca' AND COLUMN_NAME = 'nhan_vien_id' AND REFERENCED_TABLE_NAME = 'nhan_vien' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE tang_ca ADD CONSTRAINT fk_tangca_nhanvien FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_overtime_employee_statement FROM @fk_overtime_employee_sql;
EXECUTE fk_overtime_employee_statement;
DEALLOCATE PREPARE fk_overtime_employee_statement;

SET @fk_overtime_attendance_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cham_cong')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca' AND COLUMN_NAME = 'cham_cong_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca' AND COLUMN_NAME = 'cham_cong_id' AND REFERENCED_TABLE_NAME = 'cham_cong' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE tang_ca ADD CONSTRAINT fk_tangca_chamcong FOREIGN KEY (cham_cong_id) REFERENCES cham_cong(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE fk_overtime_attendance_statement FROM @fk_overtime_attendance_sql;
EXECUTE fk_overtime_attendance_statement;
DEALLOCATE PREPARE fk_overtime_attendance_statement;

SET @overtime_reviewer_column_sql = IF(
    EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tang_ca'
          AND COLUMN_NAME = 'nguoi_duyet_id'
    ),
    'SELECT 1',
    'ALTER TABLE tang_ca ADD COLUMN nguoi_duyet_id INT NULL AFTER trang_thai'
);
PREPARE overtime_reviewer_column_statement FROM @overtime_reviewer_column_sql;
EXECUTE overtime_reviewer_column_statement;
DEALLOCATE PREPARE overtime_reviewer_column_statement;

SET @fk_overtime_reviewer_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca' AND COLUMN_NAME = 'nguoi_duyet_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tang_ca' AND COLUMN_NAME = 'nguoi_duyet_id' AND REFERENCED_TABLE_NAME = 'nhan_vien' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE tang_ca ADD CONSTRAINT fk_tangca_reviewer FOREIGN KEY (nguoi_duyet_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE fk_overtime_reviewer_statement FROM @fk_overtime_reviewer_sql;
EXECUTE fk_overtime_reviewer_statement;
DEALLOCATE PREPARE fk_overtime_reviewer_statement;

SET @fk_leave_employee_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lumicine_leave_requests')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lumicine_leave_requests' AND COLUMN_NAME = 'nhan_vien_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lumicine_leave_requests' AND COLUMN_NAME = 'nhan_vien_id' AND REFERENCED_TABLE_NAME = 'nhan_vien' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE lumicine_leave_requests ADD CONSTRAINT fk_leave_employee FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_leave_employee_statement FROM @fk_leave_employee_sql;
EXECUTE fk_leave_employee_statement;
DEALLOCATE PREPARE fk_leave_employee_statement;

SET @fk_leave_reviewer_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lumicine_leave_requests')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lumicine_leave_requests' AND COLUMN_NAME = 'nguoi_duyet_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lumicine_leave_requests' AND COLUMN_NAME = 'nguoi_duyet_id' AND REFERENCED_TABLE_NAME = 'nhan_vien' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE lumicine_leave_requests ADD CONSTRAINT fk_leave_reviewer FOREIGN KEY (nguoi_duyet_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE fk_leave_reviewer_statement FROM @fk_leave_reviewer_sql;
EXECUTE fk_leave_reviewer_statement;
DEALLOCATE PREPARE fk_leave_reviewer_statement;

SET @fk_employee_department_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phong_ban')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien' AND COLUMN_NAME = 'ma_phong_ban')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien' AND COLUMN_NAME = 'ma_phong_ban' AND REFERENCED_TABLE_NAME = 'phong_ban' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE nhan_vien ADD CONSTRAINT fk_nhan_vien_phong_ban FOREIGN KEY (ma_phong_ban) REFERENCES phong_ban(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE fk_employee_department_statement FROM @fk_employee_department_sql;
EXECUTE fk_employee_department_statement;
DEALLOCATE PREPARE fk_employee_department_statement;

SET @fk_payroll_employee_sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tong_hop_cong')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nhan_vien')
    AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tong_hop_cong' AND COLUMN_NAME = 'nhan_vien_id')
    AND NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tong_hop_cong' AND COLUMN_NAME = 'nhan_vien_id' AND REFERENCED_TABLE_NAME = 'nhan_vien' AND REFERENCED_COLUMN_NAME = 'id'),
    'ALTER TABLE tong_hop_cong ADD CONSTRAINT fk_tong_hop_cong_employee FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE fk_payroll_employee_statement FROM @fk_payroll_employee_sql;
EXECUTE fk_payroll_employee_statement;
DEALLOCATE PREPARE fk_payroll_employee_statement;

-- Diagnostic list: orphan rows that must be resolved before any missing FK can be added.
SELECT 'lumicine_users.employee_id' AS relation, u.id AS source_id
FROM lumicine_users u
LEFT JOIN nhan_vien e ON e.id = u.employee_id
WHERE u.employee_id IS NOT NULL AND e.id IS NULL;

SELECT 'lumicine_leave_requests.nhan_vien_id' AS relation, l.id AS source_id
FROM lumicine_leave_requests l
LEFT JOIN nhan_vien e ON e.id = l.nhan_vien_id
WHERE e.id IS NULL;

SELECT 'lumicine_leave_requests.nguoi_duyet_id' AS relation, l.id AS source_id
FROM lumicine_leave_requests l
LEFT JOIN nhan_vien e ON e.id = l.nguoi_duyet_id
WHERE l.nguoi_duyet_id IS NOT NULL AND e.id IS NULL;
