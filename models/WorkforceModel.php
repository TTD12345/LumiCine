<?php

class WorkforceModel extends BaseModel
{
    public function findEmployeeIdByCode(?string $employeeCode): ?int
    {
        if ($employeeCode === null || $employeeCode === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id FROM nhan_vien WHERE ma_nhan_vien = :employee_code AND trang_thai = 1 LIMIT 1'
        );
        $statement->execute(['employee_code' => $employeeCode]);
        $employeeId = $statement->fetchColumn();

        return $employeeId === false ? null : (int) $employeeId;
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
                    e.trang_thai AS active
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

        if (
            ($id !== null && (!$id || $id < 1))
            || !is_string($code)
            || !is_string($name)
            || !is_string($position)
            || ($departmentId !== null && $departmentId !== '' && !is_string($departmentId) && !is_int($departmentId))
        ) {
            throw new InvalidArgumentException('Thông tin nhân viên không hợp lệ.');
        }

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
                'INSERT INTO nhan_vien (ma_nhan_vien, ho_ten, chuc_vu, ma_phong_ban, trang_thai)
                 VALUES (:code, :name, :position, :department_id, 1)'
            );
            $statement->execute([
                'code' => $code,
                'name' => $name,
                'position' => $position !== '' ? $position : null,
                'department_id' => $department,
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
                        'UPDATE lumicine_users SET employee_code = :new_code
                         WHERE BINARY employee_code = BINARY :old_code'
                    );
                    $updateMapping->execute(['new_code' => $code, 'old_code' => $currentCode]);
                }
            }

            $statement = $this->pdo->prepare(
                'UPDATE nhan_vien
                 SET ma_nhan_vien = :code, ho_ten = :name, chuc_vu = :position,
                     ma_phong_ban = :department_id
                 WHERE id = :id'
            );
            $statement->execute([
                'code' => $code,
                'name' => $name,
                'position' => $position !== '' ? $position : null,
                'department_id' => $department,
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
            'SELECT u.id, u.username, u.display_name, u.employee_code,
                    e.ho_ten AS employee_name
             FROM lumicine_users u
             LEFT JOIN nhan_vien e ON BINARY e.ma_nhan_vien = BINARY u.employee_code
             WHERE u.role = \'employee\'
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
                'SELECT 1 FROM nhan_vien
                 WHERE ma_nhan_vien = :employee_code AND trang_thai = 1
                 LIMIT 1'
            );
            $employee->execute(['employee_code' => $employeeCode]);
            if ($employee->fetchColumn() === false) {
                throw new InvalidArgumentException('Không tìm thấy nhân viên đang hoạt động với mã này.');
            }
        }

        $account = $this->pdo->prepare(
            'SELECT id FROM lumicine_users WHERE id = :user_id AND role = \'employee\' LIMIT 1'
        );
        $account->execute(['user_id' => $userId]);
        if ($account->fetchColumn() === false) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE lumicine_users SET employee_code = :employee_code WHERE id = :user_id'
        );
        try {
            $statement->execute([
                'employee_code' => $employeeCode,
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
            . ' WHERE a.ngay_lam = :work_date AND a.trang_thai = \'DA_PHAN\'';
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
                'SELECT id FROM nhan_vien WHERE id = :id AND trang_thai = 1'
            );
            $employeeCheck->execute(['id' => $employeeId]);
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
                    c.trang_thai AS attendance_status
             FROM phan_ca a
             INNER JOIN ca_lam_viec s ON s.id = a.ca_lam_viec_id
             LEFT JOIN cham_cong c ON c.phan_ca_id = a.id
             WHERE a.nhan_vien_id = :employee_id AND a.ngay_lam = :work_date
                   AND a.trang_thai = \'DA_PHAN\'
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
            ? ['check_in' => '', 'check_out' => '', 'has_assignment' => false]
            : [
                'check_in' => $record['check_in'] ?? '',
                'check_out' => $record['check_out'] ?? '',
                'has_assignment' => true,
                'attendance_status' => $record['attendance_status'],
            ];
    }

    public function attendanceHistory(int $employeeId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.ngay_lam AS date,
                    DATE_FORMAT(c.thoi_gian_vao, \'%H:%i\') AS check_in,
                    DATE_FORMAT(c.thoi_gian_ra, \'%H:%i\') AS check_out,
                    c.tong_phut_lam AS worked_minutes,
                    c.trang_thai AS attendance_status
             FROM cham_cong c
             INNER JOIN phan_ca a ON a.id = c.phan_ca_id
             WHERE a.nhan_vien_id = :employee_id
             ORDER BY a.ngay_lam DESC LIMIT 90'
        );
        $statement->execute(['employee_id' => $employeeId]);

        return $statement->fetchAll();
    }

    public function punch(int $employeeId, string $date): array
    {
        if ($date !== date('Y-m-d')) {
            throw new InvalidArgumentException('Chỉ có thể chấm công trong ngày hiện tại.');
        }

        $this->pdo->beginTransaction();
        try {
            $assignmentStatement = $this->pdo->prepare(
                'SELECT a.id AS assignment_id, c.gio_bat_dau, c.so_phut_cho_di_muon,
                        attendance.id AS attendance_id,
                        attendance.thoi_gian_vao AS check_in,
                        attendance.thoi_gian_ra AS check_out,
                        attendance.trang_thai AS attendance_status
                 FROM phan_ca a
                 INNER JOIN ca_lam_viec c ON c.id = a.ca_lam_viec_id
                 LEFT JOIN cham_cong attendance ON attendance.phan_ca_id = a.id
                 WHERE a.nhan_vien_id = :employee_id AND a.ngay_lam = :work_date
                       AND a.trang_thai = \'DA_PHAN\'
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
                             ) - :grace_minutes
                         ),
                         trang_thai = \'DANG_LAM\'
                     WHERE id = :id'
                );
                $update->execute([
                    'work_date' => $date,
                    'start_time' => $assignment['gio_bat_dau'],
                    'grace_minutes' => max(0, (int) $assignment['so_phut_cho_di_muon']),
                    'id' => $attendance['id'],
                ]);
                $punchType = 'check_in';
            } elseif ($attendance['thoi_gian_ra'] === null) {
                $update = $this->pdo->prepare(
                    'UPDATE cham_cong
                     SET thoi_gian_ra = NOW(),
                         tong_phut_lam = GREATEST(0, TIMESTAMPDIFF(MINUTE, thoi_gian_vao, NOW())),
                         trang_thai = \'DA_HOAN_THANH\'
                     WHERE id = :id'
                );
                $update->execute(['id' => $attendance['id']]);
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
                       CASE o.trang_thai
                           WHEN \'CHO_DUYET\' THEN \'pending\'
                           WHEN \'DA_DUYET\' THEN \'approved\'
                           ELSE \'rejected\'
                       END AS status
                FROM tang_ca o
                INNER JOIN cham_cong c ON c.id = o.cham_cong_id
                INNER JOIN phan_ca a ON a.id = c.phan_ca_id
                INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id';
        $parameters = [];
        if ($employeeId !== null) {
            $sql .= ' WHERE a.nhan_vien_id = :employee_id';
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

        $this->pdo->beginTransaction();
        try {
            $assignmentStatement = $this->pdo->prepare(
                'SELECT a.id, a.ngay_lam AS work_date,
                        c.gio_bat_dau AS shift_start, c.gio_ket_thuc AS shift_end
                 FROM phan_ca a
                 INNER JOIN ca_lam_viec c ON c.id = a.ca_lam_viec_id
                 WHERE a.nhan_vien_id = :employee_id
                       AND a.ngay_lam BETWEEN :from_date AND :work_date
                       AND a.trang_thai = \'DA_PHAN\'
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
                'INSERT INTO tang_ca (cham_cong_id, gio_bat_dau, gio_ket_thuc, tong_phut_tang_ca, ly_do, trang_thai)
                 VALUES (:attendance_id, :start_at, :end_at, TIMESTAMPDIFF(MINUTE, :start_at_calc, :end_at_calc), :reason, \'CHO_DUYET\')'
            );
            $startValue = $startDate->format('Y-m-d H:i:s');
            $endValue = $endDate->format('Y-m-d H:i:s');
            $insert->execute([
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

    public function reviewOvertime(int $requestId, string $status, ?int $reviewerId): void
    {
        $databaseStatus = $status === 'approved' ? 'DA_DUYET' : 'TU_CHOI';
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

    public function attendanceReport(int $month, int $year): array
    {
        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
            throw new InvalidArgumentException('Kỳ báo cáo không hợp lệ.');
        }

        $statement = $this->pdo->prepare(
            'SELECT e.id, e.ma_nhan_vien AS employee_code, e.ho_ten AS name,
                    COALESCE(d.ten_phong_ban, \'\') AS department,
                    COALESCE(e.chuc_vu, \'\') AS position,
                    a.ngay_lam AS work_date, c.thoi_gian_vao AS check_in,
                    c.thoi_gian_ra AS check_out, c.trang_thai AS attendance_status,
                    COALESCE(c.so_phut_di_muon, 0) AS late_minutes,
                    COALESCE(o.overtime_minutes, 0) AS overtime_minutes
             FROM nhan_vien e
             LEFT JOIN phong_ban d ON d.id = e.ma_phong_ban
             LEFT JOIN phan_ca a ON a.nhan_vien_id = e.id
                  AND a.trang_thai = \'DA_PHAN\'
                  AND MONTH(a.ngay_lam) = :month
                  AND YEAR(a.ngay_lam) = :year
             LEFT JOIN cham_cong c ON c.phan_ca_id = a.id
             LEFT JOIN (
                 SELECT t.cham_cong_id, SUM(t.tong_phut_tang_ca) AS overtime_minutes
                 FROM tang_ca t WHERE t.trang_thai = \'DA_DUYET\'
                 GROUP BY t.cham_cong_id
             ) o ON o.cham_cong_id = c.id
             WHERE e.trang_thai = 1
             ORDER BY e.ho_ten, a.ngay_lam'
        );
        $statement->execute(['month' => $month, 'year' => $year]);
        $rows = $statement->fetchAll();
        $employees = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!isset($employees[$id])) {
                $employees[$id] = [
                    'id' => $row['employee_code'],
                    'name' => $row['name'],
                    'department' => $row['department'],
                    'position' => $row['position'],
                    'workingDays' => 0,
                    'worked' => 0,
                    'leave' => 0,
                    'paidLeave' => 0,
                    'unpaidLeave' => 0,
                    'leaveDataAvailable' => false,
                    'absent' => 0,
                    'late' => 0,
                    'lateMinutes' => 0,
                    'lateMinutesPerOccurrence' => 0,
                    'overtime' => 0,
                    'holidayWorkDays' => 0,
                    'holidayRestDays' => 0,
                    'makeupWorkDates' => [],
                    'dailyRecords' => [],
                ];
            }
            if ($row['work_date'] === null) {
                continue;
            }

            $employee =& $employees[$id];
            $employee['workingDays']++;
            $checkIn = $row['check_in'] === null ? '' : date('H:i', strtotime($row['check_in']));
            $checkOut = $row['check_out'] === null ? '' : date('H:i', strtotime($row['check_out']));
            $lateMinutes = (int) $row['late_minutes'];
            $attendanceStatus = $row['attendance_status'];
            $state = $attendanceStatus === 'VANG_MAT'
                ? 'absent'
                : ($lateMinutes > 0 ? 'late' : ($checkIn !== '' ? 'on-time' : 'unrecorded'));
            $employee['dailyRecords'][] = [
                'date' => $row['work_date'],
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
                'state' => $state,
                'lateMinutes' => $lateMinutes,
                'attendanceStatus' => $attendanceStatus,
            ];
            if ($checkIn !== '') {
                $employee['worked']++;
            }
            if ($state === 'absent') {
                $employee['absent']++;
            }
            if ($lateMinutes > 0) {
                $employee['late']++;
                $employee['lateMinutes'] += $lateMinutes;
            }
            $employee['overtime'] += (int) $row['overtime_minutes'] / 60;
            unset($employee);
        }

        foreach ($employees as &$employee) {
            $employee['lateMinutesPerOccurrence'] = $employee['late'] > 0
                ? (int) round($employee['lateMinutes'] / $employee['late'])
                : 0;
        }
        unset($employee);

        return array_values($employees);
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
            throw new InvalidArgumentException('Thời gian tăng ca không hợp lệ.');
        }

        return $parsed;
    }
}
