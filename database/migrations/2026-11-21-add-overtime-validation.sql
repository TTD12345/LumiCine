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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS lumicine_overtime_rules (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    max_per_request_minutes INT UNSIGNED NOT NULL DEFAULT 480,
    max_per_day_minutes INT UNSIGNED NOT NULL DEFAULT 240,
    max_per_week_minutes INT UNSIGNED NOT NULL DEFAULT 1200,
    max_per_month_minutes INT UNSIGNED NOT NULL DEFAULT 2400,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO lumicine_overtime_rules
    (id, max_per_request_minutes, max_per_day_minutes, max_per_week_minutes, max_per_month_minutes)
VALUES (1, 480, 240, 1200, 2400)
ON DUPLICATE KEY UPDATE id = id;
