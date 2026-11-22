CREATE TABLE IF NOT EXISTS lumicine_leave_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nhan_vien_id INT NOT NULL,
    gio_bat_dau DATETIME NOT NULL,
    gio_ket_thuc DATETIME NOT NULL,
    ly_do VARCHAR(1000) NULL,
    trang_thai ENUM('CHO_DUYET','DA_DUYET','TU_CHOI') NOT NULL DEFAULT 'CHO_DUYET',
    nguoi_duyet_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_leave_employee_period (nhan_vien_id, gio_bat_dau, gio_ket_thuc),
    CONSTRAINT fk_leave_employee FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_reviewer FOREIGN KEY (nguoi_duyet_id) REFERENCES nhan_vien(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
