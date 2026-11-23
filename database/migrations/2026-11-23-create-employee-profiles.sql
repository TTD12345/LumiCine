CREATE TABLE IF NOT EXISTS lumicine_employee_profiles (
    employee_id INT NOT NULL,
    birth_date DATE NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    hire_date DATE NULL,
    avatar_path VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id),
    CONSTRAINT fk_lumicine_employee_profile_employee
        FOREIGN KEY (employee_id) REFERENCES nhan_vien(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
