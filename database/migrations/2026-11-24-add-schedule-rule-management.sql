CREATE TABLE IF NOT EXISTS lumicine_schedule_rule_settings (
    ma_quy_tac VARCHAR(5) NOT NULL,
    trang_thai TINYINT(1) NOT NULL DEFAULT 1,
    thu_tu_uu_tien SMALLINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ma_quy_tac)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO lumicine_schedule_rule_settings (ma_quy_tac, trang_thai, thu_tu_uu_tien)
VALUES
    ('QT001', 1, NULL),
    ('QT002', 1, NULL),
    ('QT003', 1, NULL),
    ('QT004', 1, NULL),
    ('QT005', 1, NULL),
    ('QT006', 1, NULL),
    ('QT007', 1, NULL),
    ('QT008', 1, NULL),
    ('QT009', 1, 2),
    ('QT010', 1, 1),
    ('QT011', 0, 3),
    ('QT012', 1, 4)
ON DUPLICATE KEY UPDATE ma_quy_tac = VALUES(ma_quy_tac);

SET @max_consecutive_nights_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'lumicine_schedule_policy'
      AND COLUMN_NAME = 'max_consecutive_nights'
);
SET @add_max_consecutive_nights = IF(
    @max_consecutive_nights_exists = 0,
    'ALTER TABLE lumicine_schedule_policy ADD COLUMN max_consecutive_nights SMALLINT UNSIGNED NULL AFTER min_rest_minutes',
    'DO 0'
);
PREPARE add_max_consecutive_nights_statement FROM @add_max_consecutive_nights;
EXECUTE add_max_consecutive_nights_statement;
DEALLOCATE PREPARE add_max_consecutive_nights_statement;
