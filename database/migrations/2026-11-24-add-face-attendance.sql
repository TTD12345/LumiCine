CREATE TABLE IF NOT EXISTS lumicine_face_profiles (
    employee_id INT NOT NULL,
    face_descriptor JSON NOT NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id),
    CONSTRAINT fk_lumicine_face_profile_employee
        FOREIGN KEY (employee_id) REFERENCES nhan_vien(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
