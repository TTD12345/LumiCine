CREATE TABLE IF NOT EXISTS lumicine_schedule_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ca_lam_viec_id INT NOT NULL,
    phong_ban_id INT NOT NULL,
    min_staff SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_staff SMALLINT UNSIGNED NOT NULL,
    trang_thai TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY lumicine_schedule_requirement_shift_department_unique (ca_lam_viec_id, phong_ban_id),
    CONSTRAINT lumicine_schedule_requirement_shift_fk
        FOREIGN KEY (ca_lam_viec_id) REFERENCES ca_lam_viec (id) ON DELETE CASCADE,
    CONSTRAINT lumicine_schedule_requirement_department_fk
        FOREIGN KEY (phong_ban_id) REFERENCES phong_ban (id) ON DELETE CASCADE,
    CONSTRAINT lumicine_schedule_requirement_capacity_check
        CHECK (min_staff <= max_staff)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lumicine_schedule_requirement_positions (
    requirement_id INT UNSIGNED NOT NULL,
    chuc_vu VARCHAR(100) NOT NULL,
    PRIMARY KEY (requirement_id, chuc_vu),
    CONSTRAINT lumicine_schedule_requirement_position_rule_fk
        FOREIGN KEY (requirement_id)
        REFERENCES lumicine_schedule_requirements (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lumicine_schedule_policy (
    id TINYINT UNSIGNED NOT NULL,
    max_daily_minutes INT UNSIGNED NULL,
    max_weekly_minutes INT UNSIGNED NULL,
    min_rest_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT lumicine_schedule_policy_singleton_check CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO lumicine_schedule_policy
    (id, max_daily_minutes, max_weekly_minutes, min_rest_minutes)
VALUES (1, NULL, NULL, 0)
ON DUPLICATE KEY UPDATE id = VALUES(id);
