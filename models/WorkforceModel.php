<?php

class WorkforceModel extends BaseModel
{
    private const LEAVE_TYPES = [
        'PHEP_NAM' => 'Phép năm',
        'NGHI_OM' => 'Nghỉ ốm',
        'KHONG_LUONG' => 'Nghỉ không lương',
        'VIEC_RIENG' => 'Việc riêng',
        'KHAC' => 'Khác',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->ensureLeaveRequestsTable();
        $this->ensureEmployeeProfilesTable();
        $this->ensureFaceProfilesTable();
        $this->ensureUserEmployeeIdColumn();
    }

    private function ensureFaceProfilesTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS lumicine_face_profiles (
                employee_id INT NOT NULL,
                face_descriptor JSON NOT NULL,
                enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (employee_id),
                CONSTRAINT fk_lumicine_face_profile_employee
                    FOREIGN KEY (employee_id) REFERENCES nhan_vien(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureUserEmployeeIdColumn(): void
    {
        $column = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'lumicine_users\'
               AND COLUMN_NAME = \'employee_id\''
        );
        $column->execute();
        if ((int) $column->fetchColumn() === 0) {
            $this->pdo->exec(
                'ALTER TABLE lumicine_users ADD COLUMN employee_id INT NULL AFTER employee_code'
            );
        }
        $this->pdo->exec(
            'UPDATE lumicine_users u
             INNER JOIN nhan_vien e
                 ON BINARY u.employee_code = BINARY e.ma_nhan_vien
             SET u.employee_id = e.id
             WHERE u.employee_id IS NULL'
        );
    }

    private function ensureLeaveRequestsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS lumicine_leave_requests (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nhan_vien_id INT NOT NULL,
                gio_bat_dau DATETIME NOT NULL,
                gio_ket_thuc DATETIME NOT NULL,
                loai_nghi VARCHAR(32) NOT NULL DEFAULT \'KHAC\',
                ly_do VARCHAR(1000) NULL,
                trang_thai ENUM(\'CHO_DUYET\',\'DA_DUYET\',\'TU_CHOI\') NOT NULL DEFAULT \'CHO_DUYET\',
                nguoi_duyet_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_leave_employee_period (nhan_vien_id, gio_bat_dau, gio_ket_thuc)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureEmployeeProfilesTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS lumicine_employee_profiles (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function employeeProfile(int $employeeId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.ma_nhan_vien AS employee_code, e.ho_ten AS name,
                    COALESCE(e.chuc_vu, \'\') AS position,
                    COALESCE(d.ten_phong_ban, \'\') AS department,
                    p.birth_date, p.phone, p.email, p.hire_date
             FROM nhan_vien e
             LEFT JOIN phong_ban d ON d.id = e.ma_phong_ban
             LEFT JOIN lumicine_employee_profiles p ON p.employee_id = e.id
             WHERE e.id = :employee_id
             LIMIT 1'
        );
        $statement->execute(['employee_id' => $employeeId]);
        $profile = $statement->fetch();
        if ($profile === false) {
            throw new InvalidArgumentException('Không tìm thấy hồ sơ nhân viên.');
        }

        return $profile;
    }

    public function saveEmployeeProfile(int $employeeId, string $phone, string $email): void
    {
        $phone = trim($phone);
        $email = trim($email);
        if (
            lumicineUtf8Length($phone) > 30
            || ($phone !== '' && preg_match('/^[0-9+().\\s-]+$/', $phone) !== 1)
            || lumicineUtf8Length($email) > 190
            || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        ) {
            throw new InvalidArgumentException('Số điện thoại hoặc email không hợp lệ.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO lumicine_employee_profiles (employee_id, phone, email)
             VALUES (:employee_id, :phone, :email)
             ON DUPLICATE KEY UPDATE phone = VALUES(phone), email = VALUES(email)'
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
        ]);
    }

    public function employeeTimesheet(int $employeeId, int $month, int $year): array
    {
        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
            throw new InvalidArgumentException('Tháng bảng công không hợp lệ.');
        }

        $statement = $this->pdo->prepare(
            'SELECT a.ngay_lam AS date,
                    MIN(s.gio_bat_dau) AS shift_start, MAX(s.gio_ket_thuc) AS shift_end,
                    DATE_FORMAT(MIN(c.thoi_gian_vao), \'%H:%i\') AS check_in,
                    DATE_FORMAT(MAX(c.thoi_gian_ra), \'%H:%i\') AS check_out,
                    COALESCE(SUM(c.tong_phut_lam), 0) AS worked_minutes,
                    COALESCE(MAX(c.so_phut_di_muon), 0) AS late_minutes,
                    COALESCE(MAX(c.so_phut_ve_som), 0) AS early_minutes,
                    MAX(c.trang_thai = \'VANG_MAT\') AS is_absent,
                    EXISTS (
                        SELECT 1 FROM lumicine_leave_requests l
                        WHERE l.nhan_vien_id = a.nhan_vien_id
                          AND l.trang_thai = \'DA_DUYET\'
                          AND DATE(l.gio_bat_dau) <= a.ngay_lam
                          AND DATE(l.gio_ket_thuc) >= a.ngay_lam
                    ) AS approved_leave,
                    COALESCE((
                        SELECT SUM(t.tong_phut_tang_ca)
                        FROM tang_ca t
                        WHERE t.nhan_vien_id = a.nhan_vien_id
                          AND t.trang_thai = \'DA_DUYET\'
                          AND DATE(t.gio_bat_dau) = a.ngay_lam
                    ), 0) AS overtime_minutes
             FROM phan_ca a
             INNER JOIN ca_lam_viec s ON s.id = a.ca_lam_viec_id
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             LEFT JOIN cham_cong c ON c.phan_ca_id = a.id
             WHERE a.nhan_vien_id = :employee_id
               AND a.trang_thai = \'DA_PHAN\'
               AND a.ngay_lam >= :month_start
               AND a.ngay_lam < :next_month
               AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)
             GROUP BY a.nhan_vien_id, a.ngay_lam
             ORDER BY a.ngay_lam DESC'
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'month_start' => sprintf('%04d-%02d-01', $year, $month),
            'next_month' => (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
                ->modify('first day of next month')
                ->format('Y-m-d'),
        ]);

        $records = $statement->fetchAll();
        $summary = ['work_days' => 0, 'overtime_minutes' => 0, 'leave_days' => 0];
        $today = date('Y-m-d');
        $now = new DateTimeImmutable();
        foreach ($records as &$record) {
            $date = (string) $record['date'];
            $record['worked_minutes'] = (int) $record['worked_minutes'];
            $record['late_minutes'] = (int) $record['late_minutes'];
            $record['early_minutes'] = (int) $record['early_minutes'];
            $record['overtime_minutes'] = (int) $record['overtime_minutes'];
            $hasCheckIn = !empty($record['check_in']);
            $hasCheckOut = !empty($record['check_out']);
            if ($hasCheckIn && $hasCheckOut && $record['worked_minutes'] === 0) {
                $checkInTimestamp = strtotime($date . ' ' . $record['check_in']);
                $checkOutTimestamp = strtotime($date . ' ' . $record['check_out']);
                if ($checkOutTimestamp <= $checkInTimestamp) {
                    $checkOutTimestamp += 86400;
                }
                $record['worked_minutes'] = max(
                    0,
                    (int) (($checkOutTimestamp - $checkInTimestamp) / 60)
                );
            }

            if ($record['approved_leave'] && !$hasCheckIn) {
                $record['status'] = 'leave';
                $summary['leave_days']++;
            } elseif (!$hasCheckIn && (int) $record['is_absent'] === 1) {
                $record['status'] = 'absent';
            } elseif ($hasCheckIn && $hasCheckOut) {
                $record['status'] = $record['late_minutes'] > 0
                    ? 'late'
                    : ($record['early_minutes'] > 0 ? 'early' : 'complete');
                $summary['work_days']++;
            } elseif ($hasCheckIn) {
                $record['status'] = $date < $today ? 'incomplete' : 'working';
            } elseif ($date < $today) {
                $record['status'] = 'absent';
            } elseif ($date === $today) {
                $shiftStart = new DateTimeImmutable($date . ' ' . $record['shift_start']);
                $shiftEnd = new DateTimeImmutable($date . ' ' . $record['shift_end']);
                if ($shiftEnd <= $shiftStart) {
                    $shiftEnd = $shiftEnd->modify('+1 day');
                }
                $record['status'] = $now >= $shiftEnd ? 'absent' : 'not_started';
            } else {
                $record['status'] = 'not_started';
            }
            $summary['overtime_minutes'] += $record['overtime_minutes'];
            unset($record['approved_leave'], $record['is_absent'], $record['shift_start'], $record['shift_end']);
        }
        unset($record);

        return ['records' => $records, 'summary' => $summary];
    }

    public function findEmployeeIdByCode(?string $employeeCode): ?int
    {
        if ($employeeCode === null || $employeeCode === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id FROM nhan_vien
             WHERE ma_nhan_vien = :employee_code
               AND trang_thai = 1
             LIMIT 1'
        );
        $statement->execute(['employee_code' => $employeeCode]);
        $employeeId = $statement->fetchColumn();

        return $employeeId === false ? null : (int) $employeeId;
    }

    public function findEmployeeIdByUserEmployeeId(?int $employeeId): ?int
    {
        if ($employeeId === null || $employeeId < 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT id FROM nhan_vien
             WHERE id = :id
               AND trang_thai = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $employeeId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function listEmployees(): array
    {
        return $this->pdo->query(
            'SELECT e.id, e.ma_nhan_vien AS code, e.ho_ten AS name,
                    COALESCE(e.chuc_vu, \'\') AS position,
                    COALESCE(d.ten_phong_ban, \'\') AS department
             FROM nhan_vien e
             LEFT JOIN phong_ban d ON d.id = e.ma_phong_ban
             WHERE e.trang_thai = 1
               AND (e.ngay_nghi_viec IS NULL OR e.ngay_nghi_viec > CURDATE())
             ORDER BY e.ho_ten'
        )->fetchAll();
    }

    public function listManagedEmployees(): array
    {
        return $this->pdo->query(
            'SELECT e.id, e.ma_nhan_vien AS code, e.ho_ten AS name,
                    COALESCE(e.chuc_vu, \'\') AS position,
                    e.ma_phong_ban AS department_id,
                    COALESCE(d.ten_phong_ban, \'\') AS department,
                    e.ngay_nghi_viec AS termination_date,
                    (e.trang_thai = 1 AND (e.ngay_nghi_viec IS NULL OR e.ngay_nghi_viec > CURDATE())) AS active
             FROM nhan_vien e
             LEFT JOIN phong_ban d ON d.id = e.ma_phong_ban
             ORDER BY e.trang_thai DESC, e.ma_nhan_vien ASC'
        )->fetchAll();
    }

    public function listDepartments(): array
    {
        return $this->pdo->query(
            'SELECT d.id, d.ten_phong_ban AS name, COUNT(e.id) AS employee_count
             FROM phong_ban d
             LEFT JOIN nhan_vien e ON e.ma_phong_ban = d.id
             GROUP BY d.id, d.ten_phong_ban
             ORDER BY d.ten_phong_ban ASC'
        )->fetchAll();
    }

    public function saveEmployee(array $data): int
    {
        $id = isset($data['id']) ? filter_var($data['id'], FILTER_VALIDATE_INT) : null;
        $code = $data['code'] ?? null;
        $name = $data['name'] ?? null;
        $position = $data['position'] ?? '';
        $departmentId = $data['departmentId'] ?? null;
        $terminationDate = $data['terminationDate'] ?? null;

        if (
            ($id !== null && (!$id || $id < 1))
            || !is_string($code)
            || !is_string($name)
            || !is_string($position)
            || ($terminationDate !== null && !is_string($terminationDate))
            || ($departmentId !== null && $departmentId !== '' && !is_string($departmentId) && !is_int($departmentId))
        ) {
            throw new InvalidArgumentException('Thông tin nhân viên không hợp lệ.');
        }

        $terminationDate = $terminationDate !== null ? trim($terminationDate) : '';
        if ($terminationDate !== '') {
            $this->validateDate($terminationDate);
        }
        $terminationDate = $terminationDate !== '' ? $terminationDate : null;
        $code = trim($code);
        $name = trim($name);
        $position = trim($position);
        if (
            $code === ''
            || lumicineUtf8Length($code) > 32
            || $name === ''
            || lumicineUtf8Length($name) > 100
            || lumicineUtf8Length($position) > 100
        ) {
            throw new InvalidArgumentException('Mã nhân viên, họ tên hoặc chức vụ không hợp lệ.');
        }

        $department = null;
        if ($departmentId !== null && $departmentId !== '') {
            $department = filter_var($departmentId, FILTER_VALIDATE_INT);
            if (!$department || $department < 1) {
                throw new InvalidArgumentException('Phòng ban không hợp lệ.');
            }
            $departmentCheck = $this->pdo->prepare('SELECT id FROM phong_ban WHERE id = :id LIMIT 1');
            $departmentCheck->execute(['id' => $department]);
            if ($departmentCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Phòng ban không tồn tại.');
            }
        }

        $duplicateSql = 'SELECT id FROM nhan_vien WHERE ma_nhan_vien = :code';
        $duplicateParameters = ['code' => $code];
        if ($id !== null) {
            $duplicateSql .= ' AND id <> :id';
            $duplicateParameters['id'] = $id;
        }
        $duplicate = $this->pdo->prepare($duplicateSql . ' LIMIT 1');
        $duplicate->execute($duplicateParameters);
        if ($duplicate->fetchColumn() !== false) {
            throw new InvalidArgumentException('Mã nhân viên đã được sử dụng.');
        }

        if ($id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO nhan_vien
                    (ma_nhan_vien, ho_ten, chuc_vu, ma_phong_ban, ngay_nghi_viec, trang_thai)
                 VALUES (:code, :name, :position, :department_id, :termination_date, 1)'
            );
            $statement->execute([
                'code' => $code,
                'name' => $name,
                'position' => $position !== '' ? $position : null,
                'department_id' => $department,
                'termination_date' => $terminationDate,
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        $this->pdo->beginTransaction();
        try {
            $current = $this->pdo->prepare(
                'SELECT ma_nhan_vien FROM nhan_vien WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $current->execute(['id' => $id]);
            $currentCode = $current->fetchColumn();
            if ($currentCode === false) {
                throw new InvalidArgumentException('Không tìm thấy hồ sơ nhân viên.');
            }

            if ((string) $currentCode !== $code) {
                $mappedAccount = $this->pdo->prepare(
                    'SELECT id FROM lumicine_users
                     WHERE BINARY employee_code = BINARY :old_code LIMIT 1'
                );
                $mappedAccount->execute(['old_code' => $currentCode]);
                if ($mappedAccount->fetchColumn() !== false) {
                    $codeInUse = $this->pdo->prepare(
                        'SELECT id FROM lumicine_users
                         WHERE employee_code = :new_code LIMIT 1'
                    );
                    $codeInUse->execute(['new_code' => $code]);
                    if ($codeInUse->fetchColumn() !== false) {
                        throw new InvalidArgumentException('Mã nhân viên mới đã được liên kết với tài khoản khác.');
                    }
                    $updateMapping = $this->pdo->prepare(
                        'UPDATE lumicine_users
                         SET employee_code = :new_code,
                             employee_id = :employee_id
                         WHERE BINARY employee_code = BINARY :old_code'
                    );
                    $updateMapping->execute([
                        'new_code' => $code,
                        'employee_id' => $id,
                        'old_code' => $currentCode,
                    ]);
                }
            }

            $statement = $this->pdo->prepare(
                'UPDATE nhan_vien
                 SET ma_nhan_vien = :code, ho_ten = :name, chuc_vu = :position,
                     ma_phong_ban = :department_id, ngay_nghi_viec = :termination_date
                 WHERE id = :id'
            );
            $statement->execute([
                'code' => $code,
                'name' => $name,
                'position' => $position !== '' ? $position : null,
                'department_id' => $department,
                'termination_date' => $terminationDate,
                'id' => $id,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $id;
    }

    public function setEmployeeActive(int $employeeId, bool $active): void
    {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Nhân viên không hợp lệ.');
        }
        if ($active) {
            $termination = $this->pdo->prepare(
                'SELECT ngay_nghi_viec FROM nhan_vien WHERE id = :id LIMIT 1'
            );
            $termination->execute(['id' => $employeeId]);
            $terminationDate = $termination->fetchColumn();
            if ($terminationDate === false) {
                throw new InvalidArgumentException('Không tìm thấy hồ sơ nhân viên.');
            }
            if ($terminationDate !== null && $terminationDate <= date('Y-m-d')) {
                throw new InvalidArgumentException('Hãy xóa hoặc cập nhật ngày nghỉ việc trước khi kích hoạt nhân viên.');
            }
        }
        $statement = $this->pdo->prepare(
            'UPDATE nhan_vien SET trang_thai = :active WHERE id = :id'
        );
        $statement->execute(['active' => $active ? 1 : 0, 'id' => $employeeId]);
        if ($statement->rowCount() === 0) {
            $exists = $this->pdo->prepare('SELECT id FROM nhan_vien WHERE id = :id LIMIT 1');
            $exists->execute(['id' => $employeeId]);
            if ($exists->fetchColumn() === false) {
                throw new InvalidArgumentException('Không tìm thấy hồ sơ nhân viên.');
            }
        }
    }

    public function saveDepartment(array $data): int
    {
        $id = isset($data['id']) ? filter_var($data['id'], FILTER_VALIDATE_INT) : null;
        $name = $data['name'] ?? null;
        if (($id !== null && (!$id || $id < 1)) || !is_string($name)) {
            throw new InvalidArgumentException('Thông tin phòng ban không hợp lệ.');
        }
        $name = trim($name);
        if ($name === '' || lumicineUtf8Length($name) > 100) {
            throw new InvalidArgumentException('Tên phòng ban phải có tối đa 100 ký tự.');
        }

        $duplicateSql = 'SELECT id FROM phong_ban WHERE ten_phong_ban = :name';
        $duplicateParameters = ['name' => $name];
        if ($id !== null) {
            $duplicateSql .= ' AND id <> :id';
            $duplicateParameters['id'] = $id;
        }
        $duplicate = $this->pdo->prepare($duplicateSql . ' LIMIT 1');
        $duplicate->execute($duplicateParameters);
        if ($duplicate->fetchColumn() !== false) {
            throw new InvalidArgumentException('Tên phòng ban đã được sử dụng.');
        }

        if ($id === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO phong_ban (ten_phong_ban) VALUES (:name)'
            );
            $statement->execute(['name' => $name]);

            return (int) $this->pdo->lastInsertId();
        }

        $statement = $this->pdo->prepare(
            'UPDATE phong_ban SET ten_phong_ban = :name WHERE id = :id'
        );
        $statement->execute(['name' => $name, 'id' => $id]);
        if ($statement->rowCount() === 0) {
            $exists = $this->pdo->prepare('SELECT id FROM phong_ban WHERE id = :id LIMIT 1');
            $exists->execute(['id' => $id]);
            if ($exists->fetchColumn() === false) {
                throw new InvalidArgumentException('Không tìm thấy phòng ban.');
            }
        }

        return $id;
    }

    public function deleteDepartment(int $departmentId): void
    {
        if ($departmentId < 1) {
            throw new InvalidArgumentException('Phòng ban không hợp lệ.');
        }

        $usage = $this->pdo->prepare(
            'SELECT COUNT(*) FROM nhan_vien WHERE ma_phong_ban = :id'
        );
        $usage->execute(['id' => $departmentId]);
        if ((int) $usage->fetchColumn() > 0) {
            throw new InvalidArgumentException('Không thể xóa phòng ban đang được gán cho nhân viên.');
        }

        $statement = $this->pdo->prepare('DELETE FROM phong_ban WHERE id = :id');
        $statement->execute(['id' => $departmentId]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Không tìm thấy phòng ban.');
        }
    }

    public function listEmployeeAccountMappings(): array
    {
        return $this->pdo->query(
            'SELECT u.id, u.username, u.display_name, u.employee_code, u.employee_id, u.role,
                    COALESCE(direct.ho_ten, legacy.ho_ten) AS employee_name
             FROM lumicine_users u
             LEFT JOIN nhan_vien direct ON direct.id = u.employee_id
             LEFT JOIN nhan_vien legacy ON BINARY legacy.ma_nhan_vien = BINARY u.employee_code
             ORDER BY u.username'
        )->fetchAll();
    }

    public function updateEmployeeAccountMapping(int $userId, ?string $employeeCode): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Tài khoản không hợp lệ.');
        }

        if ($employeeCode !== null) {
            $employeeCode = trim($employeeCode);
            if ($employeeCode === '' || mb_strlen($employeeCode) > 32) {
                throw new InvalidArgumentException('Mã nhân viên không hợp lệ.');
            }

            $employee = $this->pdo->prepare(
                'SELECT id FROM nhan_vien
                 WHERE ma_nhan_vien = :employee_code AND trang_thai = 1
                 LIMIT 1'
            );
            $employee->execute(['employee_code' => $employeeCode]);
            $employeeId = $employee->fetchColumn();
            if ($employeeId === false) {
                throw new InvalidArgumentException('Không tìm thấy nhân viên đang hoạt động với mã này.');
            }
        } else {
            $employeeId = null;
        }

        $account = $this->pdo->prepare(
            'SELECT id FROM lumicine_users
             WHERE id = :user_id
               AND role IN (\'employee\', \'accountant\', \'hr\', \'chairman\')
             LIMIT 1'
        );
        $account->execute(['user_id' => $userId]);
        if ($account->fetchColumn() === false) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE lumicine_users
             SET employee_code = :employee_code, employee_id = :employee_id
             WHERE id = :user_id'
        );
        try {
            $statement->execute([
                'employee_code' => $employeeCode,
                'employee_id' => $employeeId !== null ? (int) $employeeId : null,
                'user_id' => $userId,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new InvalidArgumentException('Mã nhân viên này đã được liên kết với một tài khoản khác.');
            }
            throw $exception;
        }
    }

    public function listAssignments(string $date, ?int $employeeId = null): array
    {
        $this->validateDate($date);
        $sql = $this->assignmentQuery()
            . ' WHERE a.ngay_lam = :work_date AND a.trang_thai = \'DA_PHAN\'
                    AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)';
        $parameters = ['work_date' => $date];
        if ($employeeId !== null) {
            $sql .= ' AND a.nhan_vien_id = :employee_id';
            $parameters['employee_id'] = $employeeId;
        }
        $sql .= ' ORDER BY c.gio_bat_dau, e.ho_ten';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $assignments = $statement->fetchAll();
        $now = new DateTimeImmutable();

        foreach ($assignments as &$assignment) {
            $start = new DateTimeImmutable($assignment['work_date'] . ' ' . $assignment['start']);
            $end = new DateTimeImmutable($assignment['work_date'] . ' ' . $assignment['end']);
            if ($end <= $start) {
                $end = $end->modify('+1 day');
            }
            $assignment['status'] = $now < $start ? 'upcoming' : ($now < $end ? 'active' : 'completed');
            $assignment['id'] = (string) $assignment['id'];
            $assignment['employee_id'] = (int) $assignment['employee_id'];
        }
        unset($assignment);

        return $assignments;
    }

    public function listAssignmentsRange(
        string $fromDate,
        string $toDate,
        ?int $scopeEmployeeId = null,
        ?int $departmentId = null,
        ?int $selectedEmployeeId = null
    ): array {
        $this->validateDate($fromDate);
        $this->validateDate($toDate);
        $startDate = new DateTimeImmutable($fromDate);
        $endDate = new DateTimeImmutable($toDate);
        if ($endDate < $startDate || (int) $startDate->diff($endDate)->format('%a') > 366) {
            throw new InvalidArgumentException('Khoảng thời gian lọc lịch phân ca không hợp lệ.');
        }

        $sql = $this->assignmentQuery()
            . ' WHERE a.ngay_lam BETWEEN :from_date AND :to_date
                    AND a.trang_thai = \'DA_PHAN\'
                    AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)';
        $parameters = ['from_date' => $fromDate, 'to_date' => $toDate];
        $employeeId = $scopeEmployeeId ?? $selectedEmployeeId;
        if ($employeeId !== null) {
            $sql .= ' AND a.nhan_vien_id = :employee_id';
            $parameters['employee_id'] = $employeeId;
        }
        if ($departmentId !== null) {
            $sql .= ' AND e.ma_phong_ban = :department_id';
            $parameters['department_id'] = $departmentId;
        }
        $sql .= ' ORDER BY a.ngay_lam, c.gio_bat_dau, e.ho_ten';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $assignments = $statement->fetchAll();
        $now = new DateTimeImmutable();
        foreach ($assignments as &$assignment) {
            $start = new DateTimeImmutable($assignment['work_date'] . ' ' . $assignment['start']);
            $end = new DateTimeImmutable($assignment['work_date'] . ' ' . $assignment['end']);
            if ($end <= $start) {
                $end = $end->modify('+1 day');
            }
            $assignment['status'] = $now < $start ? 'upcoming' : ($now < $end ? 'active' : 'completed');
            $assignment['id'] = (string) $assignment['id'];
            $assignment['employee_id'] = (int) $assignment['employee_id'];
        }
        unset($assignment);

        return $assignments;
    }

    public function listShiftTypes(bool $includeInactive = false): array
    {
        $sql = 'SELECT id, ten_ca AS name, gio_bat_dau AS start, gio_ket_thuc AS end,
                       COALESCE(so_phut_cho_di_muon, 0) AS late_tolerance,
                       trang_thai AS active
                FROM ca_lam_viec';
        if (!$includeInactive) {
            $sql .= ' WHERE trang_thai = 1';
        }
        $sql .= ' ORDER BY gio_bat_dau, id';
        $statement = $this->pdo->query($sql);

        return $statement->fetchAll();
    }

    public function setShiftTypeActive(int $shiftId, bool $active): void
    {
        $check = $this->pdo->prepare('SELECT id FROM ca_lam_viec WHERE id = :id');
        $check->execute(['id' => $shiftId]);
        if ($check->fetchColumn() === false) {
            throw new InvalidArgumentException('Loại ca làm việc không tồn tại.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE ca_lam_viec SET trang_thai = :active WHERE id = :id'
        );
        $statement->execute([
            'id' => $shiftId,
            'active' => $active ? 1 : 0,
        ]);
    }

    public function saveAssignment(array $data): array
    {
        $employeeId = filter_var($data['employeeId'] ?? null, FILTER_VALIDATE_INT);
        $date = $data['date'] ?? '';
        $shiftId = isset($data['shiftId']) ? filter_var($data['shiftId'], FILTER_VALIDATE_INT) : null;
        $start = $data['start'] ?? '';
        $end = $data['end'] ?? '';
        $locationValue = $data['location'] ?? '';
        if (!is_string($date) || !is_string($start) || !is_string($end) || !is_string($locationValue)) {
            throw new InvalidArgumentException('Thông tin ca làm việc không hợp lệ.');
        }
        $location = trim($locationValue);
        $id = isset($data['id']) ? filter_var($data['id'], FILTER_VALIDATE_INT) : null;

        if (!$employeeId || $employeeId < 1 || ($id !== null && (!$id || $id < 1))) {
            throw new InvalidArgumentException('Nhân viên hoặc ca làm việc không hợp lệ.');
        }
        $this->validateDate($date);
        if ($shiftId !== null) {
            if (!$shiftId || $shiftId < 1) {
                throw new InvalidArgumentException('Loại ca làm việc không hợp lệ.');
            }
            $shiftStatement = $this->pdo->prepare(
                'SELECT gio_bat_dau, gio_ket_thuc FROM ca_lam_viec
                 WHERE id = :id AND trang_thai = 1 LIMIT 1'
            );
            $shiftStatement->execute(['id' => $shiftId]);
            $shift = $shiftStatement->fetch();
            if ($shift === false) {
                throw new InvalidArgumentException('Loại ca làm việc không tồn tại.');
            }
            $start = (string) $shift['gio_bat_dau'];
            $end = (string) $shift['gio_ket_thuc'];
        }
        $this->validateTime($start);
        $this->validateTime($end);
        if ($location === '' || mb_strlen($location) > 100) {
            throw new InvalidArgumentException('Địa điểm làm việc không hợp lệ.');
        }

        $this->pdo->beginTransaction();
        try {
            $employeeCheck = $this->pdo->prepare(
                'SELECT id FROM nhan_vien
                 WHERE id = :id AND trang_thai = 1
                   AND (ngay_nghi_viec IS NULL OR :work_date < ngay_nghi_viec)'
            );
            $employeeCheck->execute(['id' => $employeeId, 'work_date' => $date]);
            if ($employeeCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Nhân viên không tồn tại hoặc đã ngừng hoạt động.');
            }

            if ($id !== null) {
                $assignmentCheck = $this->pdo->prepare(
                    'SELECT id FROM phan_ca WHERE id = :id AND trang_thai = \'DA_PHAN\' FOR UPDATE'
                );
                $assignmentCheck->execute(['id' => $id]);
                if ($assignmentCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('Ca làm việc không tồn tại.');
                }
                $attendanceCheck = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM cham_cong WHERE phan_ca_id = :assignment_id'
                );
                $attendanceCheck->execute(['assignment_id' => $id]);
                if ((int) $attendanceCheck->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Không thể sửa ca đã có dữ liệu chấm công.');
                }
            }

            $shiftId = $this->findOrCreateShiftType($start, $end);
            if ($id === null) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO phan_ca (nhan_vien_id, ca_lam_viec_id, ngay_lam, dia_diem, trang_thai)
                     VALUES (:employee_id, :shift_id, :work_date, :location, \'DA_PHAN\')'
                );
                $statement->execute([
                    'employee_id' => $employeeId,
                    'shift_id' => $shiftId,
                    'work_date' => $date,
                    'location' => $location,
                ]);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $statement = $this->pdo->prepare(
                    'UPDATE phan_ca
                     SET nhan_vien_id = :employee_id, ca_lam_viec_id = :shift_id,
                         ngay_lam = :work_date, dia_diem = :location
                     WHERE id = :id AND trang_thai = \'DA_PHAN\''
                );
                $statement->execute([
                    'employee_id' => $employeeId,
                    'shift_id' => $shiftId,
                    'work_date' => $date,
                    'location' => $location,
                    'id' => $id,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $assignments = $this->listAssignments($date);
        foreach ($assignments as $assignment) {
            if ((int) $assignment['id'] === $id) {
                return $assignment;
            }
        }

        throw new RuntimeException('Không thể tải lại ca làm việc vừa lưu.');
    }

    public function cancelAssignment(int $assignmentId): void
    {
        $this->pdo->beginTransaction();
        try {
            $assignment = $this->pdo->prepare(
                'SELECT id FROM phan_ca WHERE id = :id AND trang_thai = \'DA_PHAN\' FOR UPDATE'
            );
            $assignment->execute(['id' => $assignmentId]);
            if ($assignment->fetchColumn() === false) {
                throw new InvalidArgumentException('Ca làm việc không tồn tại hoặc đã bị hủy.');
            }
            $attendance = $this->pdo->prepare(
                'SELECT COUNT(*) FROM cham_cong WHERE phan_ca_id = :assignment_id'
            );
            $attendance->execute(['assignment_id' => $assignmentId]);
            if ((int) $attendance->fetchColumn() > 0) {
                throw new InvalidArgumentException('Không thể hủy ca đã có dữ liệu chấm công hoặc tăng ca.');
            }
            $update = $this->pdo->prepare(
                'UPDATE phan_ca SET trang_thai = \'HUY\' WHERE id = :id'
            );
            $update->execute(['id' => $assignmentId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function getAttendance(int $employeeId, string $date): array
    {
        $this->validateDate($date);
        $statement = $this->pdo->prepare(
            'SELECT DATE_FORMAT(c.thoi_gian_vao, \'%H:%i\') AS check_in,
                    DATE_FORMAT(c.thoi_gian_ra, \'%H:%i\') AS check_out,
                    c.trang_thai AS attendance_status,
                    COALESCE(c.so_phut_di_muon, 0) AS late_minutes,
                    COALESCE(c.so_phut_ve_som, 0) AS early_minutes,
                    s.ten_ca AS shift_name,
                    DATE_FORMAT(s.gio_bat_dau, \'%H:%i\') AS shift_start,
                    DATE_FORMAT(s.gio_ket_thuc, \'%H:%i\') AS shift_end,
                    COALESCE(a.dia_diem, \'\') AS location
             FROM phan_ca a
             INNER JOIN ca_lam_viec s ON s.id = a.ca_lam_viec_id
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             LEFT JOIN cham_cong c ON c.phan_ca_id = a.id
             WHERE a.nhan_vien_id = :employee_id AND a.ngay_lam = :work_date
                   AND a.trang_thai = \'DA_PHAN\'
                   AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)
             ORDER BY CASE
                          WHEN c.thoi_gian_vao IS NOT NULL AND c.thoi_gian_ra IS NULL THEN 0
                          WHEN c.trang_thai = \'VANG_MAT\' THEN 2
                          WHEN c.id IS NULL OR c.thoi_gian_vao IS NULL THEN 1
                          ELSE 2
                      END,
                      s.gio_bat_dau, a.id
             LIMIT 1'
        );
        $statement->execute(['employee_id' => $employeeId, 'work_date' => $date]);
        $record = $statement->fetch();

        return $record === false
            ? [
                'check_in' => '',
                'check_out' => '',
                'late_minutes' => 0,
                'early_minutes' => 0,
                'has_assignment' => false,
                'shift_name' => 'Ca làm việc',
                'shift_start' => '',
                'shift_end' => '',
                'location' => '',
            ]
            : [
                'check_in' => $record['check_in'] ?? '',
                'check_out' => $record['check_out'] ?? '',
                'late_minutes' => (int) $record['late_minutes'],
                'early_minutes' => (int) $record['early_minutes'],
                'has_assignment' => true,
                'attendance_status' => $record['attendance_status'],
                'shift_name' => $record['shift_name'] ?? 'Ca làm việc',
                'shift_start' => $record['shift_start'] ?? '',
                'shift_end' => $record['shift_end'] ?? '',
                'location' => $record['location'] ?? '',
            ];
    }

    public function attendanceHistory(int $employeeId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.ngay_lam AS date,
                    DATE_FORMAT(c.thoi_gian_vao, \'%H:%i\') AS check_in,
                    DATE_FORMAT(c.thoi_gian_ra, \'%H:%i\') AS check_out,
                    c.tong_phut_lam AS worked_minutes,
                    COALESCE(c.so_phut_di_muon, 0) AS late_minutes,
                    COALESCE(c.so_phut_ve_som, 0) AS early_minutes,
                    c.trang_thai AS attendance_status
             FROM cham_cong c
             INNER JOIN phan_ca a ON a.id = c.phan_ca_id
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             WHERE a.nhan_vien_id = :employee_id
                   AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)
             ORDER BY a.ngay_lam DESC LIMIT 90'
        );
        $statement->execute(['employee_id' => $employeeId]);

        return $statement->fetchAll();
    }

    public function faceProfileStatus(int $employeeId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT enrolled_at, updated_at
             FROM lumicine_face_profiles
             WHERE employee_id = :employee_id
             LIMIT 1'
        );
        $statement->execute(['employee_id' => $employeeId]);
        $profile = $statement->fetch();

        return [
            'enrolled' => $profile !== false,
            'updated_at' => $profile['updated_at'] ?? null,
        ];
    }

    public function validateFaceDescriptor(array $descriptor): array
    {
        if (count($descriptor) !== 128) {
            throw new InvalidArgumentException('Dữ liệu khuôn mặt không hợp lệ.');
        }

        $normalized = [];
        foreach ($descriptor as $value) {
            if (!is_int($value) && !is_float($value) && !is_string($value)) {
                throw new InvalidArgumentException('Dữ liệu khuôn mặt không hợp lệ.');
            }
            $number = (float) $value;
            if (!is_finite($number)) {
                throw new InvalidArgumentException('Dữ liệu khuôn mặt không hợp lệ.');
            }
            $normalized[] = $number;
        }

        return $normalized;
    }

    public function enrollFace(int $employeeId, array $descriptor): void
    {
        $descriptor = $this->validateFaceDescriptor($descriptor);
        $statement = $this->pdo->prepare(
            'INSERT INTO lumicine_face_profiles (employee_id, face_descriptor)
             VALUES (:employee_id, :descriptor)
             ON DUPLICATE KEY UPDATE
                 face_descriptor = VALUES(face_descriptor)'
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'descriptor' => json_encode($descriptor, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function verifyFace(int $employeeId, array $descriptor): bool
    {
        $descriptor = $this->validateFaceDescriptor($descriptor);
        $statement = $this->pdo->prepare(
            'SELECT face_descriptor
             FROM lumicine_face_profiles
             WHERE employee_id = :employee_id
             LIMIT 1'
        );
        $statement->execute(['employee_id' => $employeeId]);
        $profile = $statement->fetch();
        if ($profile === false) {
            throw new InvalidArgumentException('Bạn chưa đăng ký khuôn mặt.');
        }

        $savedDescriptor = json_decode((string) $profile['face_descriptor'], true);
        if (!is_array($savedDescriptor)) {
            throw new RuntimeException('Dữ liệu khuôn mặt đã đăng ký không hợp lệ.');
        }
        $savedDescriptor = $this->validateFaceDescriptor($savedDescriptor);

        $distance = 0.0;
        foreach ($descriptor as $index => $value) {
            $difference = $value - $savedDescriptor[$index];
            $distance += $difference * $difference;
        }
        $distance = sqrt($distance);

        return $distance <= 0.45;
    }

    public function punch(int $employeeId, string $date): array
    {
        if ($date !== date('Y-m-d')) {
            throw new InvalidArgumentException('Chỉ có thể chấm công trong ngày hiện tại.');
        }

        $this->pdo->beginTransaction();
        try {
            $assignmentStatement = $this->pdo->prepare(
                'SELECT a.id AS assignment_id, c.gio_bat_dau, c.gio_ket_thuc,
                        attendance.id AS attendance_id,
                        attendance.thoi_gian_vao AS check_in,
                        attendance.thoi_gian_ra AS check_out,
                        attendance.trang_thai AS attendance_status
                 FROM phan_ca a
                 INNER JOIN ca_lam_viec c ON c.id = a.ca_lam_viec_id
                 INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
                 LEFT JOIN cham_cong attendance ON attendance.phan_ca_id = a.id
                 WHERE a.nhan_vien_id = :employee_id AND a.ngay_lam = :work_date
                       AND a.trang_thai = \'DA_PHAN\'
                       AND e.trang_thai = 1
                       AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)
                 ORDER BY CASE
                              WHEN attendance.thoi_gian_vao IS NOT NULL AND attendance.thoi_gian_ra IS NULL THEN 0
                              WHEN attendance.trang_thai = \'VANG_MAT\' THEN 2
                              WHEN attendance.id IS NULL OR attendance.thoi_gian_vao IS NULL THEN 1
                              ELSE 2
                          END,
                          c.gio_bat_dau, a.id
                 LIMIT 1 FOR UPDATE'
            );
            $assignmentStatement->execute(['employee_id' => $employeeId, 'work_date' => $date]);
            $assignment = $assignmentStatement->fetch();
            if ($assignment === false) {
                throw new InvalidArgumentException('Bạn chưa được phân ca trong ngày hôm nay.');
            }

            $createAttendance = $this->pdo->prepare(
                'INSERT INTO cham_cong (nhan_vien_id, phan_ca_id, trang_thai)
                 VALUES (:employee_id, :assignment_id, \'CHUA_CHECK_IN\')
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $createAttendance->execute([
                'employee_id' => $employeeId,
                'assignment_id' => $assignment['assignment_id'],
            ]);
            $attendanceStatement = $this->pdo->prepare(
                'SELECT id, thoi_gian_vao, thoi_gian_ra, trang_thai
                 FROM cham_cong WHERE phan_ca_id = :assignment_id FOR UPDATE'
            );
            $attendanceStatement->execute(['assignment_id' => $assignment['assignment_id']]);
            $attendance = $attendanceStatement->fetch();
            if ($attendance === false) {
                throw new RuntimeException('Không thể tạo bản ghi chấm công.');
            }
            if ($attendance['trang_thai'] === 'VANG_MAT') {
                throw new InvalidArgumentException('Bản ghi của ca này đã được đánh dấu vắng mặt.');
            }

            if ($attendance['thoi_gian_vao'] === null) {
                $update = $this->pdo->prepare(
                    'UPDATE cham_cong
                     SET thoi_gian_vao = NOW(),
                         so_phut_di_muon = GREATEST(
                             0,
                             TIMESTAMPDIFF(
                                 MINUTE,
                                 CONCAT(:work_date, \' \', :start_time),
                                 NOW()
                             )
                         ),
                         trang_thai = \'DANG_LAM\'
                     WHERE id = :id'
                );
                $update->execute([
                    'work_date' => $date,
                    'start_time' => $assignment['gio_bat_dau'],
                    'id' => $attendance['id'],
                ]);
                $punchType = 'check_in';
            } elseif ($attendance['thoi_gian_ra'] === null) {
                $update = $this->pdo->prepare(
                    'UPDATE cham_cong
                     SET thoi_gian_ra = NOW(),
                         tong_phut_lam = GREATEST(0, TIMESTAMPDIFF(MINUTE, thoi_gian_vao, NOW())),
                         so_phut_ve_som = GREATEST(
                             0,
                             TIMESTAMPDIFF(
                                 MINUTE,
                                 NOW(),
                                 TIMESTAMPADD(
                                     DAY,
                                     CASE
                                         WHEN :end_time_compare <= :start_time_compare THEN 1
                                         ELSE 0
                                     END,
                                     TIMESTAMP(:work_date_end, :end_time_value)
                                 )
                             )
                         ),
                         trang_thai = \'DA_HOAN_THANH\'
                     WHERE id = :id'
                );
                $update->execute([
                    'end_time_compare' => $assignment['gio_ket_thuc'],
                    'start_time_compare' => $assignment['gio_bat_dau'],
                    'work_date_end' => $date,
                    'end_time_value' => $assignment['gio_ket_thuc'],
                    'id' => $attendance['id'],
                ]);
                $punchType = 'check_out';
            } else {
                throw new InvalidArgumentException('Bạn đã hoàn tất chấm công cho ca này.');
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return ['type' => $punchType, 'record' => $this->getAttendance($employeeId, $date)];
    }

    public function overtimeRequests(?int $employeeId = null): array
    {
        $sql = 'SELECT o.id, e.ho_ten AS employee_name,
                       DATE_FORMAT(o.gio_bat_dau, \'%Y-%m-%dT%H:%i\') AS start,
                       DATE_FORMAT(o.gio_ket_thuc, \'%Y-%m-%dT%H:%i\') AS end,
                       o.ly_do AS reason,
                       o.created_at AS submitted_at,
                       COALESCE(reviewer.ho_ten, \'\') AS reviewer_name,
                       CASE o.trang_thai
                           WHEN \'CHO_DUYET\' THEN \'pending\'
                           WHEN \'DA_DUYET\' THEN \'approved\'
                           ELSE \'rejected\'
                       END AS status
                FROM tang_ca o
                LEFT JOIN cham_cong c ON c.id = o.cham_cong_id
                LEFT JOIN phan_ca a ON a.id = c.phan_ca_id
                INNER JOIN nhan_vien e ON e.id = COALESCE(o.nhan_vien_id, a.nhan_vien_id)
                LEFT JOIN nhan_vien reviewer ON reviewer.id = o.nguoi_duyet_id';
        $parameters = [];
        if ($employeeId !== null) {
            $sql .= ' WHERE COALESCE(o.nhan_vien_id, a.nhan_vien_id) = :employee_id';
            $parameters['employee_id'] = $employeeId;
        }
        $sql .= ' ORDER BY o.created_at DESC, o.id DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function submitOvertime(int $employeeId, string $start, string $end, string $reason): void
    {
        $startDate = $this->parseDateTime($start);
        $endDate = $this->parseDateTime($end);
        if ($endDate <= $startDate || mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('Thời gian hoặc lý do tăng ca không hợp lệ.');
        }
        if ($this->overlapsAdministrativeHours($startDate, $endDate)) {
            throw new InvalidArgumentException('Chỉ được đăng ký tăng ca ngoài giờ hành chính 08:00 - 17:00.');
        }

        $this->pdo->beginTransaction();
        try {
            $employeeCheck = $this->pdo->prepare(
                'SELECT id FROM nhan_vien
                 WHERE id = :id AND trang_thai = 1
                   AND (
                       ngay_nghi_viec IS NULL
                       OR (:start_date < ngay_nghi_viec AND :end_date < ngay_nghi_viec)
                   )'
            );
            $employeeCheck->execute([
                'id' => $employeeId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);
            if ($employeeCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Không thể đăng ký tăng ca sau ngày nghỉ việc.');
            }

            $assignmentStatement = $this->pdo->prepare(
                'SELECT a.id, a.ngay_lam AS work_date,
                        c.gio_bat_dau AS shift_start, c.gio_ket_thuc AS shift_end
                 FROM phan_ca a
                 INNER JOIN ca_lam_viec c ON c.id = a.ca_lam_viec_id
                 INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
                 WHERE a.nhan_vien_id = :employee_id
                       AND a.ngay_lam BETWEEN :from_date AND :work_date
                       AND a.trang_thai = \'DA_PHAN\'
                       AND e.trang_thai = 1
                       AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)
                 ORDER BY a.ngay_lam, c.gio_bat_dau, a.id
                 FOR UPDATE'
            );
            $assignmentStatement->execute([
                'employee_id' => $employeeId,
                'work_date' => $startDate->format('Y-m-d'),
                'from_date' => $startDate->modify('-1 day')->format('Y-m-d'),
            ]);
            $assignments = $assignmentStatement->fetchAll();
            if ($assignments === []) {
                throw new InvalidArgumentException('Bạn chưa được phân ca trong ngày tăng ca.');
            }

            $matchingAssignmentId = null;
            foreach ($assignments as $assignment) {
                if ((string) $assignment['work_date'] === $startDate->format('Y-m-d')) {
                    $matchingAssignmentId = (int) $assignment['id'];
                    break;
                }
            }
            if ($matchingAssignmentId === null) {
                $matchingAssignmentId = (int) $assignments[0]['id'];
            }

            foreach ($assignments as $assignment) {
                $shiftStart = $this->parseDateTime(
                    (string) $assignment['work_date'] . 'T' . substr((string) $assignment['shift_start'], 0, 5)
                );
                $shiftEnd = $this->parseDateTime(
                    (string) $assignment['work_date'] . 'T' . substr((string) $assignment['shift_end'], 0, 5)
                );
                if ($shiftEnd <= $shiftStart) {
                    $shiftEnd = $shiftEnd->modify('+1 day');
                }

                if ($startDate < $shiftEnd && $endDate > $shiftStart) {
                    throw new InvalidArgumentException(
                        'Thời gian tăng ca không hợp lệ vì trùng hoặc nằm trong giờ làm việc '
                        . $shiftStart->format('H:i') . ' - ' . $shiftEnd->format('H:i') . ' ngày '
                        . $assignment['work_date'] . '.'
                    );
                }
            }

            $assignmentId = $matchingAssignmentId;

            $createAttendance = $this->pdo->prepare(
                'INSERT INTO cham_cong (nhan_vien_id, phan_ca_id, trang_thai)
                 VALUES (:employee_id, :assignment_id, \'CHUA_CHECK_IN\')
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $createAttendance->execute([
                'employee_id' => $employeeId,
                'assignment_id' => $assignmentId,
            ]);
            $attendanceStatement = $this->pdo->prepare(
                'SELECT id FROM cham_cong WHERE phan_ca_id = :assignment_id'
            );
            $attendanceStatement->execute(['assignment_id' => $assignmentId]);
            $attendanceId = $attendanceStatement->fetchColumn();
            if ($attendanceId === false) {
                throw new RuntimeException('Không thể liên kết đơn tăng ca với lịch chấm công.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO tang_ca (nhan_vien_id, cham_cong_id, gio_bat_dau, gio_ket_thuc, tong_phut_tang_ca, ly_do, trang_thai)
                 VALUES (:employee_id, :attendance_id, :start_at, :end_at, TIMESTAMPDIFF(MINUTE, :start_at_calc, :end_at_calc), :reason, \'CHO_DUYET\')'
            );
            $startValue = $startDate->format('Y-m-d H:i:s');
            $endValue = $endDate->format('Y-m-d H:i:s');
            $insert->execute([
                'employee_id' => $employeeId,
                'attendance_id' => $attendanceId,
                'start_at' => $startValue,
                'end_at' => $endValue,
                'start_at_calc' => $startValue,
                'end_at_calc' => $endValue,
                'reason' => trim($reason),
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function leaveTypes(): array
    {
        return self::LEAVE_TYPES;
    }

    public function leaveRequests(?int $employeeId = null): array
    {
        $sql = 'SELECT l.id, e.ho_ten AS employee_name,
                       DATE_FORMAT(l.gio_bat_dau, \'%Y-%m-%dT%H:%i\') AS start,
                       DATE_FORMAT(l.gio_ket_thuc, \'%Y-%m-%dT%H:%i\') AS end,
                       l.loai_nghi AS leave_type,
                       l.ly_do AS reason,
                       l.created_at AS submitted_at,
                       COALESCE(reviewer.ho_ten, \'\') AS reviewer_name,
                       CASE l.trang_thai
                           WHEN \'CHO_DUYET\' THEN \'pending\'
                           WHEN \'DA_DUYET\' THEN \'approved\'
                           ELSE \'rejected\'
                       END AS status
                FROM lumicine_leave_requests l
                INNER JOIN nhan_vien e ON e.id = l.nhan_vien_id
                LEFT JOIN nhan_vien reviewer ON reviewer.id = l.nguoi_duyet_id';
        $parameters = [];
        if ($employeeId !== null) {
            $sql .= ' WHERE l.nhan_vien_id = :employee_id';
            $parameters['employee_id'] = $employeeId;
        }
        $sql .= ' ORDER BY l.created_at DESC, l.id DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function submitLeave(
        int $employeeId,
        string $start,
        string $end,
        string $reason,
        string $leaveType
    ): void
    {
        $startDate = $this->parseDateTime($start);
        $endDate = $this->parseDateTime($end);
        $reason = trim($reason);
        if (
            $endDate <= $startDate
            || lumicineUtf8Length($reason) < 5
            || lumicineUtf8Length($reason) > 1000
            || !isset(self::LEAVE_TYPES[$leaveType])
        ) {
            throw new InvalidArgumentException('Thời gian hoặc lý do nghỉ phép không hợp lệ.');
        }
        $now = new DateTimeImmutable();
        if ($startDate < $now) {
            throw new InvalidArgumentException('Không thể đăng ký nghỉ phép trong quá khứ.');
        }

        $this->pdo->beginTransaction();
        try {
            $employeeCheck = $this->pdo->prepare(
                'SELECT id FROM nhan_vien
                 WHERE id = :id AND trang_thai = 1
                   AND (
                       ngay_nghi_viec IS NULL
                       OR (:leave_start_date < ngay_nghi_viec AND :leave_end_date < ngay_nghi_viec)
                   )'
            );
            $employeeCheck->execute([
                'id' => $employeeId,
                'leave_start_date' => $startDate->format('Y-m-d'),
                'leave_end_date' => $endDate->format('Y-m-d'),
            ]);
            if ($employeeCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Nhân viên không tồn tại hoặc đã ngừng hoạt động.');
            }

            $overlap = $this->pdo->prepare(
                'SELECT id FROM lumicine_leave_requests
                 WHERE nhan_vien_id = :employee_id
                   AND trang_thai IN (\'CHO_DUYET\', \'DA_DUYET\')
                   AND gio_bat_dau <= :end_at
                   AND gio_ket_thuc >= :start_at
                 LIMIT 1 FOR UPDATE'
            );
            $overlap->execute([
                'employee_id' => $employeeId,
                'start_at' => $startDate->format('Y-m-d H:i:s'),
                'end_at' => $endDate->format('Y-m-d H:i:s'),
            ]);
            if ($overlap->fetchColumn() !== false) {
                throw new InvalidArgumentException('Thời gian nghỉ phép bị trùng với đơn đang chờ duyệt hoặc đã được duyệt.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO lumicine_leave_requests
                 (nhan_vien_id, gio_bat_dau, gio_ket_thuc, loai_nghi, ly_do, trang_thai)
                 VALUES (:employee_id, :start_at, :end_at, :leave_type, :reason, \'CHO_DUYET\')'
            );
            $insert->execute([
                'employee_id' => $employeeId,
                'start_at' => $startDate->format('Y-m-d H:i:s'),
                'end_at' => $endDate->format('Y-m-d H:i:s'),
                'leave_type' => $leaveType,
                'reason' => $reason,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function reviewLeave(int $requestId, string $status, ?int $reviewerId): void
    {
        $databaseStatus = $status === 'approved' ? 'DA_DUYET' : 'TU_CHOI';
        $statement = $this->pdo->prepare(
            'UPDATE lumicine_leave_requests
             SET trang_thai = :status, nguoi_duyet_id = :reviewer_id
             WHERE id = :id AND trang_thai = \'CHO_DUYET\''
        );
        $statement->execute([
            'status' => $databaseStatus,
            'reviewer_id' => $reviewerId,
            'id' => $requestId,
        ]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Đơn nghỉ phép không tồn tại hoặc đã được xử lý.');
        }
    }

    public function cancelLeave(int $requestId, int $employeeId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM lumicine_leave_requests
             WHERE id = :id AND nhan_vien_id = :employee_id AND trang_thai = \'CHO_DUYET\''
        );
        $statement->execute([
            'id' => $requestId,
            'employee_id' => $employeeId,
        ]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Chỉ có thể hủy đơn nghỉ phép đang chờ duyệt.');
        }
    }

    public function reviewOvertime(int $requestId, string $status, ?int $reviewerId): void
    {
        $databaseStatus = $status === 'approved' ? 'DA_DUYET' : 'TU_CHOI';
        $this->pdo->beginTransaction();
        try {
            $requestStatement = $this->pdo->prepare(
                'SELECT o.id, o.nhan_vien_id, o.cham_cong_id,
                        o.gio_bat_dau, o.gio_ket_thuc, o.trang_thai
                 FROM tang_ca o
                 WHERE o.id = :id AND o.trang_thai = \'CHO_DUYET\' FOR UPDATE'
            );
            $requestStatement->execute(['id' => $requestId]);
            $request = $requestStatement->fetch();
            if ($request === false) {
                throw new InvalidArgumentException('Đơn tăng ca không tồn tại hoặc đã được xử lý.');
            }

            if ($status === 'approved') {
                $employeeId = (int) ($request['nhan_vien_id'] ?? 0);
                if ($employeeId === 0 && $request['cham_cong_id'] !== null) {
                    $legacyStatement = $this->pdo->prepare(
                        'SELECT nhan_vien_id FROM cham_cong WHERE id = :attendance_id'
                    );
                    $legacyStatement->execute(['attendance_id' => $request['cham_cong_id']]);
                    $employeeId = (int) ($legacyStatement->fetchColumn() ?: 0);
                }
                if ($employeeId < 1) {
                    throw new InvalidArgumentException('Đơn tăng ca chưa gắn hồ sơ nhân viên.');
                }
                $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $request['gio_bat_dau']);
                $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $request['gio_ket_thuc']);
                if (!$start || !$end || $end <= $start || $this->overlapsAdministrativeHours($start, $end)) {
                    throw new InvalidArgumentException('Chỉ được duyệt tăng ca ngoài giờ hành chính 08:00 - 17:00.');
                }
                $employeeCheck = $this->pdo->prepare(
                    'SELECT id FROM nhan_vien
                     WHERE id = :id AND trang_thai = 1
                       AND (
                           ngay_nghi_viec IS NULL
                           OR (:start_date < ngay_nghi_viec AND :end_date < ngay_nghi_viec)
                       )'
                );
                $employeeCheck->execute([
                    'id' => $employeeId,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                ]);
                if ($employeeCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('Không thể duyệt tăng ca sau ngày nghỉ việc.');
                }
            }

            $statement = $this->pdo->prepare(
                'UPDATE tang_ca
                 SET trang_thai = :status, nguoi_duyet_id = :reviewer_id
                 WHERE id = :id AND trang_thai = \'CHO_DUYET\''
            );
            $statement->execute([
                'status' => $databaseStatus,
                'reviewer_id' => $reviewerId,
                'id' => $requestId,
            ]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Đơn tăng ca không tồn tại hoặc đã được xử lý.');
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function holidays(int $year): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DATE_FORMAT(ngay, \'%Y-%m-%d\') AS date, ten_ngay AS name,
                    loai_ngay AS type, \'manual\' AS source
             FROM lumicine_holidays WHERE YEAR(ngay) = :year ORDER BY ngay'
        );
        $statement->execute(['year' => $year]);

        return $statement->fetchAll();
    }

    public function addHoliday(string $date, string $name, string $type): void
    {
        $this->validateDate($date);
        if (mb_strlen(trim($name)) === 0 || mb_strlen(trim($name)) > 150) {
            throw new InvalidArgumentException('Tên ngày nghỉ không hợp lệ.');
        }
        if (!in_array($type, ['holiday', 'workday'], true)) {
            throw new InvalidArgumentException('Loại ngày không hợp lệ.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO lumicine_holidays (ngay, ten_ngay, loai_ngay)
             VALUES (:date, :name, :type)'
        );
        try {
            $statement->execute(['date' => $date, 'name' => trim($name), 'type' => $type]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new InvalidArgumentException('Ngày này đã có trong lịch nghỉ.');
            }
            throw $exception;
        }
    }

    public function removeHoliday(string $date): void
    {
        $this->validateDate($date);
        $statement = $this->pdo->prepare('DELETE FROM lumicine_holidays WHERE ngay = :date');
        $statement->execute(['date' => $date]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Không tìm thấy ngày nghỉ cần xóa.');
        }
    }

    public function payrollStartDay(): int
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM lumicine_settings WHERE setting_key = :setting_key'
        );
        $statement->execute(['setting_key' => 'payroll_start_day']);
        $value = $statement->fetchColumn();
        $day = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false || $day === false || $day < 1 || $day > 31) {
            throw new RuntimeException('Ngày bắt đầu kỳ lương trong cài đặt không hợp lệ.');
        }

        return $day;
    }

    public function savePayrollStartDay(int $day): void
    {
        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException('Ngày bắt đầu kỳ lương phải từ 1 đến 31.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO lumicine_settings (setting_key, setting_value)
             VALUES (\'payroll_start_day\', :insert_value)
             ON DUPLICATE KEY UPDATE setting_value = :update_value'
        );
        $statement->execute([
            'insert_value' => (string) $day,
            'update_value' => (string) $day,
        ]);
    }

    public function attendanceReport(int $month, int $year): array
    {
        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
            throw new InvalidArgumentException('Kỳ báo cáo không hợp lệ.');
        }

        $startDay = $this->payrollStartDay();
        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $periodStart = $periodStart->setDate(
            $year,
            $month,
            min($startDay, (int) $periodStart->format('t'))
        );
        $nextPeriodStart = $periodStart->modify('first day of next month');
        $nextPeriodStart = $nextPeriodStart->setDate(
            (int) $nextPeriodStart->format('Y'),
            (int) $nextPeriodStart->format('n'),
            min($startDay, (int) $nextPeriodStart->format('t'))
        );
        $periodEnd = $nextPeriodStart->modify('-1 day')->format('Y-m-d');
        $periodStartValue = $periodStart->format('Y-m-d');
        $leaveTypes = self::LEAVE_TYPES;
        $employeeStatement = $this->pdo->prepare(
            'SELECT e.id, e.ma_nhan_vien AS employee_code, e.ho_ten AS name,
                    COALESCE(d.ten_phong_ban, \'\') AS department,
                    COALESCE(e.chuc_vu, \'\') AS position,
                    e.ngay_nghi_viec AS termination_date
             FROM nhan_vien e
             LEFT JOIN phong_ban d ON d.id = e.ma_phong_ban
             WHERE e.trang_thai = 1
                OR EXISTS (
                    SELECT 1 FROM phan_ca history
                    WHERE history.nhan_vien_id = e.id
                      AND history.trang_thai = \'DA_PHAN\'
                      AND history.ngay_lam BETWEEN :history_start AND :history_end
                      AND (e.ngay_nghi_viec IS NULL OR history.ngay_lam < e.ngay_nghi_viec)
                )
             ORDER BY e.ho_ten'
        );
        $employeeStatement->execute([
            'history_start' => $periodStartValue,
            'history_end' => $periodEnd,
        ]);
        $employees = [];
        foreach ($employeeStatement->fetchAll() as $row) {
            $employees[(int) $row['id']] = [
                'id' => $row['employee_code'],
                'name' => $row['name'],
                'department' => $row['department'],
                'position' => $row['position'],
                'terminationDate' => $row['termination_date'],
                'workingDays' => 0,
                'worked' => 0,
                'leave' => 0,
                'leaveByType' => array_fill_keys(array_keys($leaveTypes), 0),
                'partialLeave' => 0,
                'incomplete' => 0,
                'absent' => 0,
                'late' => 0,
                'lateMinutes' => 0,
                'lateMinutesPerOccurrence' => 0,
                'earlyMinutes' => 0,
                'overtime' => 0,
                'holidayWorkDays' => 0,
                'holidayRestDays' => 0,
                'makeupWorkDates' => [],
                'dailyRecords' => [],
                '_days' => [],
            ];
        }

        $assignmentStatement = $this->pdo->prepare(
            'SELECT a.nhan_vien_id AS employee_id, a.ngay_lam AS work_date,
                    s.gio_bat_dau AS shift_start, s.gio_ket_thuc AS shift_end,
                    c.thoi_gian_vao AS check_in, c.thoi_gian_ra AS check_out,
                    c.trang_thai AS attendance_status,
                    COALESCE(c.so_phut_di_muon, 0) AS late_minutes,
                    COALESCE(c.so_phut_ve_som, 0) AS early_minutes,
                    (SELECT l.loai_nghi
                     FROM lumicine_leave_requests l
                     WHERE l.nhan_vien_id = a.nhan_vien_id
                       AND l.trang_thai = \'DA_DUYET\'
                       AND l.gio_bat_dau <= TIMESTAMP(a.ngay_lam, s.gio_bat_dau)
                       AND l.gio_ket_thuc >= TIMESTAMP(
                           CASE
                               WHEN s.gio_ket_thuc <= s.gio_bat_dau
                                   THEN DATE_ADD(a.ngay_lam, INTERVAL 1 DAY)
                               ELSE a.ngay_lam
                           END,
                           s.gio_ket_thuc
                       )
                     ORDER BY l.gio_bat_dau, l.id
                     LIMIT 1) AS full_leave_type,
                    EXISTS (
                        SELECT 1 FROM lumicine_leave_requests l
                        WHERE l.nhan_vien_id = a.nhan_vien_id
                          AND l.trang_thai = \'DA_DUYET\'
                          AND l.gio_bat_dau < TIMESTAMP(
                              CASE
                                  WHEN s.gio_ket_thuc <= s.gio_bat_dau
                                      THEN DATE_ADD(a.ngay_lam, INTERVAL 1 DAY)
                                  ELSE a.ngay_lam
                              END,
                              s.gio_ket_thuc
                          )
                          AND l.gio_ket_thuc > TIMESTAMP(a.ngay_lam, s.gio_bat_dau)
                    ) AS has_approved_leave
             FROM phan_ca a
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             INNER JOIN ca_lam_viec s ON s.id = a.ca_lam_viec_id
             LEFT JOIN cham_cong c ON c.phan_ca_id = a.id
             WHERE a.trang_thai = \'DA_PHAN\'
               AND a.ngay_lam BETWEEN :period_start AND :period_end
               AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)
               AND (a.dia_diem IS NULL OR a.dia_diem <> \'Tăng ca\')
             ORDER BY a.nhan_vien_id, a.ngay_lam, s.gio_bat_dau'
        );
        $assignmentStatement->execute([
            'period_start' => $periodStartValue,
            'period_end' => $periodEnd,
        ]);
        $rows = $assignmentStatement->fetchAll();

        foreach ($rows as $row) {
            $employeeId = (int) $row['employee_id'];
            if (!isset($employees[$employeeId])) {
                continue;
            }
            $workDate = (string) $row['work_date'];
            if (!isset($employees[$employeeId]['_days'][$workDate])) {
                $employees[$employeeId]['_days'][$workDate] = [
                    'date' => $workDate,
                    'assignmentCount' => 0,
                    'fullLeaveCount' => 0,
                    'fullLeaveTypes' => [],
                    'hasApprovedLeave' => false,
                    'hasCheckIn' => false,
                    'hasCompletedShift' => false,
                    'checkIn' => '',
                    'checkOut' => '',
                    'lateMinutes' => 0,
                    'earlyMinutes' => 0,
                    'attendanceStatus' => null,
                ];
            }
            $day =& $employees[$employeeId]['_days'][$workDate];
            $day['assignmentCount']++;
            $day['hasApprovedLeave'] = $day['hasApprovedLeave'] || (bool) $row['has_approved_leave'];
            if ($row['full_leave_type'] !== null && isset($leaveTypes[$row['full_leave_type']])) {
                $day['fullLeaveCount']++;
                $day['fullLeaveTypes'][$row['full_leave_type']] = true;
            }

            if ($row['check_in'] !== null) {
                $day['hasCheckIn'] = true;
                $day['checkIn'] = $day['checkIn'] === ''
                    ? date('H:i', strtotime($row['check_in']))
                    : min($day['checkIn'], date('H:i', strtotime($row['check_in'])));
                $day['lateMinutes'] += (int) $row['late_minutes'];
                if ($row['check_out'] !== null) {
                    $day['hasCompletedShift'] = true;
                    $formattedCheckOut = date('H:i', strtotime($row['check_out']));
                    $day['checkOut'] = $day['checkOut'] === ''
                        ? $formattedCheckOut
                        : max($day['checkOut'], $formattedCheckOut);
                    $day['earlyMinutes'] += (int) $row['early_minutes'];
                }
                $day['attendanceStatus'] = $row['attendance_status'];
            } elseif ($row['attendance_status'] === 'VANG_MAT') {
                $day['attendanceStatus'] = 'VANG_MAT';
            }
            unset($day);
        }

        foreach ($employees as &$employee) {
            foreach ($employee['_days'] as $day) {
                $employee['workingDays']++;
                if ($day['hasCheckIn']) {
                    if ($day['hasCompletedShift']) {
                        $employee['worked']++;
                        $employee['lateMinutes'] += $day['lateMinutes'];
                        $employee['earlyMinutes'] += $day['earlyMinutes'];
                        if ($day['lateMinutes'] > 0) {
                            $employee['late']++;
                        }
                        $state = $day['lateMinutes'] > 0
                            ? 'late'
                            : ($day['earlyMinutes'] > 0 ? 'early' : 'on-time');
                    } else {
                        $employee['incomplete']++;
                        $state = 'incomplete';
                    }
                } elseif ($day['fullLeaveCount'] === $day['assignmentCount']) {
                    $employee['leave']++;
                    foreach (array_keys($day['fullLeaveTypes']) as $leaveType) {
                        $employee['leaveByType'][$leaveType]++;
                    }
                    $state = 'leave';
                } elseif ($day['hasApprovedLeave']) {
                    $employee['partialLeave']++;
                    $state = 'partial-leave';
                } else {
                    $employee['absent']++;
                    $state = 'absent';
                }

                $employee['dailyRecords'][] = [
                    'date' => $day['date'],
                    'checkIn' => $day['checkIn'],
                    'checkOut' => $day['checkOut'],
                    'state' => $state,
                    'lateMinutes' => $day['lateMinutes'],
                    'earlyMinutes' => $day['earlyMinutes'],
                    'leaveTypes' => array_keys($day['fullLeaveTypes']),
                    'attendanceStatus' => $day['attendanceStatus'],
                ];
            }
            unset($employee['_days']);
        }
        unset($employee);

        $overtimeStatement = $this->pdo->prepare(
            'SELECT COALESCE(t.nhan_vien_id, c.nhan_vien_id) AS employee_id,
                    SUM(t.tong_phut_tang_ca) AS overtime_minutes
             FROM tang_ca t
             LEFT JOIN cham_cong c ON c.id = t.cham_cong_id
             INNER JOIN nhan_vien e ON e.id = COALESCE(t.nhan_vien_id, c.nhan_vien_id)
             WHERE t.trang_thai = \'DA_DUYET\'
               AND DATE(t.gio_bat_dau) BETWEEN :period_start AND :period_end
               AND (e.ngay_nghi_viec IS NULL OR DATE(t.gio_bat_dau) < e.ngay_nghi_viec)
             GROUP BY COALESCE(t.nhan_vien_id, c.nhan_vien_id)'
        );
        $overtimeStatement->execute([
            'period_start' => $periodStartValue,
            'period_end' => $periodEnd,
        ]);
        foreach ($overtimeStatement->fetchAll() as $row) {
            $employeeId = (int) $row['employee_id'];
            if (isset($employees[$employeeId])) {
                $employees[$employeeId]['overtime'] = (int) $row['overtime_minutes'] / 60;
            }
        }

        foreach ($employees as &$employee) {
            $employee['lateMinutesPerOccurrence'] = $employee['late'] > 0
                ? (int) round($employee['lateMinutes'] / $employee['late'])
                : 0;
        }
        unset($employee);

        return array_values($employees);
    }

    public function dashboardReport(string $fromDate, string $toDate, ?int $departmentId = null): array
    {
        $this->validateDate($fromDate);
        $this->validateDate($toDate);
        $start = new DateTimeImmutable($fromDate);
        $end = new DateTimeImmutable($toDate);
        if ($end < $start || (int) $start->diff($end)->format('%a') > 366) {
            throw new InvalidArgumentException('Khoảng thời gian báo cáo không hợp lệ.');
        }

        $departmentClause = $departmentId !== null ? ' AND e.ma_phong_ban = :department_id' : '';
        $employeeParams = ['start' => $fromDate, 'end' => $toDate];
        if ($departmentId !== null) {
            $employeeParams['department_id'] = $departmentId;
        }

        $employeeStatement = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT e.id) AS total_employees
             FROM nhan_vien e
             WHERE e.trang_thai = 1
               AND (e.ngay_nghi_viec IS NULL OR e.ngay_nghi_viec > :end)'
               . ($departmentId !== null ? ' AND e.ma_phong_ban = :department_id' : '')
        );
        $employeeCountParams = ['end' => $toDate];
        if ($departmentId !== null) {
            $employeeCountParams['department_id'] = $departmentId;
        }
        $employeeStatement->execute($employeeCountParams);
        $totalEmployees = (int) $employeeStatement->fetchColumn();

        $attendanceStatement = $this->pdo->prepare(
            'SELECT a.ngay_lam AS work_date,
                    a.nhan_vien_id AS employee_id,
                    COUNT(DISTINCT a.id) AS assignment_count,
                    COUNT(DISTINCT CASE
                        WHEN c.thoi_gian_vao IS NOT NULL AND c.thoi_gian_ra IS NOT NULL
                        THEN a.id END) AS completed_count,
                    MAX(CASE WHEN c.thoi_gian_vao IS NOT NULL AND c.thoi_gian_ra IS NOT NULL
                             AND COALESCE(c.so_phut_di_muon, 0) > 0 THEN 1 ELSE 0 END) AS is_late,
                    MAX(CASE WHEN c.thoi_gian_vao IS NOT NULL AND c.thoi_gian_ra IS NOT NULL
                             AND COALESCE(c.so_phut_di_muon, 0) = 0 THEN 1 ELSE 0 END) AS is_on_time,
                    EXISTS (
                        SELECT 1 FROM lumicine_leave_requests l
                        WHERE l.nhan_vien_id = a.nhan_vien_id
                          AND l.trang_thai = \'DA_DUYET\'
                          AND DATE(l.gio_bat_dau) <= a.ngay_lam
                          AND DATE(l.gio_ket_thuc) >= a.ngay_lam
                    ) AS is_leave
             FROM phan_ca a
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             LEFT JOIN cham_cong c ON c.phan_ca_id = a.id
             WHERE a.trang_thai = \'DA_PHAN\'
               AND a.ngay_lam BETWEEN :start AND :end
               AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)'
               . $departmentClause
               . ' GROUP BY a.ngay_lam, a.nhan_vien_id
                 ORDER BY a.ngay_lam, a.nhan_vien_id'
        );
        $attendanceStatement->execute($employeeParams);

        $daily = [];
        $worked = 0;
        $late = 0;
        $leaveDays = 0;
        $leaveSeen = [];
        $topEmployees = [];
        $departmentWork = [];
        foreach ($attendanceStatement->fetchAll() as $row) {
            $date = (string) $row['work_date'];
            $employeeId = (int) $row['employee_id'];
            if (!isset($daily[$date])) {
                $daily[$date] = ['date' => $date, 'on_time' => 0, 'late' => 0, 'absent' => 0];
            }
            if ((int) $row['completed_count'] > 0) {
                $worked++;
                if ((int) $row['is_late'] === 1) {
                    $late++;
                    $daily[$date]['late']++;
                } else {
                    $daily[$date]['on_time']++;
                }
                $topEmployees[$employeeId] = ($topEmployees[$employeeId] ?? 0) + 1;
            } elseif ((int) $row['is_leave'] === 1) {
                $leaveKey = $date . ':' . $employeeId;
                if (!isset($leaveSeen[$leaveKey])) {
                    $leaveSeen[$leaveKey] = true;
                    $leaveDays++;
                }
            } else {
                $daily[$date]['absent']++;
            }
        }

        $leaveStatement = $this->pdo->prepare(
            'SELECT l.trang_thai,
                    COUNT(DISTINCT l.id) AS request_count
             FROM lumicine_leave_requests l
             INNER JOIN nhan_vien e ON e.id = l.nhan_vien_id
             WHERE DATE(l.gio_bat_dau) <= :end
               AND DATE(l.gio_ket_thuc) >= :start'
               . $departmentClause
               . ' GROUP BY l.trang_thai'
        );
        $leaveStatement->execute($employeeParams);
        $leaveStatus = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
        foreach ($leaveStatement->fetchAll() as $row) {
            $key = $row['trang_thai'] === 'DA_DUYET' ? 'approved'
                : ($row['trang_thai'] === 'CHO_DUYET' ? 'pending' : 'rejected');
            $leaveStatus[$key] = (int) $row['request_count'];
        }

        $overtimeStatement = $this->pdo->prepare(
            'SELECT DATE(o.gio_bat_dau) AS overtime_date,
                    o.trang_thai,
                    SUM(COALESCE(o.tong_phut_tang_ca, 0)) AS overtime_minutes
             FROM tang_ca o
             INNER JOIN nhan_vien e ON e.id = COALESCE(o.nhan_vien_id,
                 (SELECT c.nhan_vien_id FROM cham_cong c WHERE c.id = o.cham_cong_id LIMIT 1))
             WHERE DATE(o.gio_bat_dau) BETWEEN :start AND :end'
               . $departmentClause
               . ' GROUP BY DATE(o.gio_bat_dau), o.trang_thai
                 ORDER BY overtime_date'
        );
        $overtimeStatement->execute($employeeParams);
        $overtimeDaily = [];
        $overtimeTotals = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
        foreach ($overtimeStatement->fetchAll() as $row) {
            $date = (string) $row['overtime_date'];
            $key = $row['trang_thai'] === 'DA_DUYET' ? 'approved'
                : ($row['trang_thai'] === 'CHO_DUYET' ? 'pending' : 'rejected');
            $minutes = (int) $row['overtime_minutes'];
            $overtimeTotals[$key] += $minutes;
            if (!isset($overtimeDaily[$date])) {
                $overtimeDaily[$date] = ['date' => $date, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
            }
            $overtimeDaily[$date][$key] += round($minutes / 60, 2);
        }

        $departmentStatement = $this->pdo->prepare(
            'SELECT COALESCE(d.ten_phong_ban, \'Chưa phân công\') AS department,
                    COUNT(DISTINCT CONCAT(a.ngay_lam, "-", a.nhan_vien_id)) AS work_days
             FROM phan_ca a
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             LEFT JOIN phong_ban d ON d.id = e.ma_phong_ban
             INNER JOIN cham_cong c ON c.phan_ca_id = a.id
             WHERE a.trang_thai = \'DA_PHAN\'
               AND c.thoi_gian_vao IS NOT NULL
               AND c.thoi_gian_ra IS NOT NULL
               AND a.ngay_lam BETWEEN :start AND :end
               AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)'
               . $departmentClause
               . ' GROUP BY d.id, d.ten_phong_ban
                 ORDER BY d.ten_phong_ban'
        );
        $departmentStatement->execute($employeeParams);
        $departmentWork = array_map(
            static fn (array $row): array => [
                'department' => (string) $row['department'],
                'work_days' => (int) $row['work_days'],
            ],
            $departmentStatement->fetchAll()
        );

        $topStatement = $this->pdo->prepare(
            'SELECT e.id, e.ho_ten AS name, COUNT(DISTINCT a.ngay_lam) AS work_days
             FROM phan_ca a
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             INNER JOIN cham_cong c ON c.phan_ca_id = a.id
             WHERE a.trang_thai = \'DA_PHAN\'
               AND c.thoi_gian_vao IS NOT NULL
               AND c.thoi_gian_ra IS NOT NULL
               AND a.ngay_lam BETWEEN :start AND :end
               AND (e.ngay_nghi_viec IS NULL OR a.ngay_lam < e.ngay_nghi_viec)'
               . $departmentClause
               . ' GROUP BY e.id, e.ho_ten
                 ORDER BY work_days DESC, e.ho_ten ASC
                 LIMIT 5'
        );
        $topStatement->execute($employeeParams);
        $topEmployees = array_map(
            static fn (array $row): array => [
                'name' => (string) $row['name'],
                'work_days' => (int) $row['work_days'],
            ],
            $topStatement->fetchAll()
        );

        $cursor = new DateTimeImmutable($fromDate);
        $last = new DateTimeImmutable($toDate);
        $dailyFilled = [];
        while ($cursor <= $last) {
            $date = $cursor->format('Y-m-d');
            $dailyFilled[] = $daily[$date] ?? ['date' => $date, 'on_time' => 0, 'late' => 0, 'absent' => 0];
            $cursor = $cursor->modify('+1 day');
        }

        $overtimeFilled = [];
        $cursor = new DateTimeImmutable($fromDate);
        while ($cursor <= $last) {
            $date = $cursor->format('Y-m-d');
            $overtimeFilled[] = $overtimeDaily[$date] ?? ['date' => $date, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'summary' => [
                'total_employees' => $totalEmployees,
                'total_work_days' => $worked,
                'total_late' => $late,
                'total_leave_days' => $leaveDays,
            ],
            'attendance_daily' => $dailyFilled,
            'department_work' => $departmentWork,
            'leave_status' => $leaveStatus,
            'overtime_daily' => $overtimeFilled,
            'overtime_totals' => [
                'approved_hours' => round($overtimeTotals['approved'] / 60, 2),
                'pending_hours' => round($overtimeTotals['pending'] / 60, 2),
                'rejected_hours' => round($overtimeTotals['rejected'] / 60, 2),
            ],
            'top_employees' => $topEmployees,
        ];
    }

    private function assignmentQuery(): string
    {
        return 'SELECT a.id, a.ngay_lam AS work_date, e.id AS employee_id,
                       e.ho_ten AS name, COALESCE(e.chuc_vu, \'\') AS role,
                       COALESCE(d.ten_phong_ban, \'\') AS department,
                       c.id AS shift_id, c.ten_ca AS shift_name,
                       c.gio_bat_dau AS start, c.gio_ket_thuc AS end,
                       COALESCE(a.dia_diem, \'\') AS location
                FROM phan_ca a
                INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
                INNER JOIN ca_lam_viec c ON c.id = a.ca_lam_viec_id
                LEFT JOIN phong_ban d ON d.id = e.ma_phong_ban';
    }

    private function findOrCreateShiftType(string $start, string $end): int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM ca_lam_viec
             WHERE gio_bat_dau = :start AND gio_ket_thuc = :end AND trang_thai = 1
             ORDER BY id LIMIT 1'
        );
        $statement->execute(['start' => $start, 'end' => $end]);
        $shiftId = $statement->fetchColumn();
        if ($shiftId !== false) {
            return (int) $shiftId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO ca_lam_viec (ten_ca, gio_bat_dau, gio_ket_thuc, trang_thai)
             VALUES (:name, :start, :end, 1)'
        );
        $insert->execute([
            'name' => 'Ca ' . substr($start, 0, 5) . '-' . substr($end, 0, 5),
            'start' => $start,
            'end' => $end,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function validateDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Ngày không hợp lệ.');
        }
    }

    private function validateTime(string $time): void
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time) !== 1) {
            throw new InvalidArgumentException('Giờ làm việc không hợp lệ.');
        }
    }

    private function parseDateTime(string $value): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $value) {
            throw new InvalidArgumentException('Thời gian không hợp lệ.');
        }

        return $parsed;
    }

    private function overlapsAdministrativeHours(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        $date = $start->setTime(0, 0);
        $lastDate = $end->setTime(0, 0);

        while ($date <= $lastDate) {
            $workStart = $date->setTime(8, 0);
            $workEnd = $date->setTime(17, 0);
            if ($start < $workEnd && $end > $workStart) {
                return true;
            }
            $date = $date->modify('+1 day');
        }

        return false;
    }
}
