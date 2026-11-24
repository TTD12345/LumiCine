<?php

class SchedulePlanner extends BaseModel
{
    private const MAX_RANGE_DAYS = 31;
    private const MAX_PLAN_ASSIGNMENTS = 2000;
    private const RULE_DEFINITIONS = [
        'QT001' => [
            'name' => 'Phân ca theo chức vụ',
            'type' => 'mandatory',
            'scope' => 'Chức vụ · phòng ban · ca',
            'description' => 'Chỉ chọn chức vụ được khai báo trong quy tắc ca của phòng ban.',
            'target' => 'auto-rule-form',
        ],
        'QT002' => [
            'name' => 'Phân ca theo phòng ban',
            'type' => 'mandatory',
            'scope' => 'Phòng ban · ca',
            'description' => 'Chỉ dùng ca được cấu hình cho phòng ban tương ứng.',
            'target' => 'auto-rule-form',
        ],
        'QT003' => [
            'name' => 'Không trùng lịch',
            'type' => 'mandatory',
            'scope' => 'Toàn công ty',
            'description' => 'Kiểm tra lịch đã xác nhận và bản dự kiến, bao gồm ca qua đêm.',
            'target' => null,
        ],
        'QT004' => [
            'name' => 'Nghỉ phép đã duyệt',
            'type' => 'mandatory',
            'scope' => 'Toàn công ty',
            'description' => 'Chặn lịch chồng lên đơn nghỉ phép có trạng thái đã duyệt.',
            'target' => null,
        ],
        'QT005' => [
            'name' => 'Giới hạn giờ làm',
            'type' => 'mandatory',
            'scope' => 'Toàn công ty',
            'description' => 'Áp dụng giới hạn giờ/ngày và giờ/tuần khi được cấu hình.',
            'target' => 'auto-policy-qt005',
        ],
        'QT006' => [
            'name' => 'Nghỉ tối thiểu giữa ca',
            'type' => 'mandatory',
            'scope' => 'Toàn công ty',
            'description' => 'Kiểm tra thời gian nghỉ giữa các ca, kể cả lịch qua đêm.',
            'target' => 'auto-policy-qt006',
        ],
        'QT007' => [
            'name' => 'Trạng thái nhân viên',
            'type' => 'mandatory',
            'scope' => 'Toàn công ty',
            'description' => 'Không chọn nhân viên ngừng hoạt động hoặc đã đến ngày nghỉ việc.',
            'target' => null,
        ],
        'QT008' => [
            'name' => 'Số lượng nhân sự',
            'type' => 'mandatory',
            'scope' => 'Phòng ban · ca',
            'description' => 'Bổ sung đến mức tối thiểu, cảnh báo thiếu và không vượt mức tối đa.',
            'target' => 'auto-rule-form',
        ],
        'QT009' => [
            'name' => 'Phân ca công bằng',
            'type' => 'priority',
            'scope' => 'Toàn công ty',
            'description' => 'Ưu tiên nhân viên có ít ca hơn trong kỳ và trong tuần.',
            'target' => null,
        ],
        'QT010' => [
            'name' => 'Cân bằng giờ làm',
            'type' => 'priority',
            'scope' => 'Toàn công ty',
            'description' => 'Ưu tiên số giờ trong tuần thấp hơn, sau đó đến tổng giờ trong kỳ.',
            'target' => null,
        ],
        'QT011' => [
            'name' => 'Ưu tiên nguyện vọng',
            'type' => 'priority',
            'scope' => 'Nhân viên · ca',
            'description' => 'Chỉ áp dụng khi hệ thống có dữ liệu nguyện vọng ca.',
            'target' => null,
        ],
        'QT012' => [
            'name' => 'Hạn chế ca đêm liên tiếp',
            'type' => 'priority',
            'scope' => 'Toàn công ty',
            'description' => 'Ưu tiên người có ít ca đêm liên tiếp; có thể đặt mức tối đa.',
            'target' => 'auto-policy-qt012',
        ],
    ];

    public function configuration(): array
    {
        $shiftTypes = $this->pdo->query(
            'SELECT id, ten_ca AS name, gio_bat_dau AS start, gio_ket_thuc AS end,
                    gio_nghi_bat_dau AS break_start, gio_nghi_ket_thuc AS break_end,
                    trang_thai AS active
             FROM ca_lam_viec ORDER BY gio_bat_dau, id'
        )->fetchAll();
        $departments = $this->pdo->query(
            'SELECT id, ten_phong_ban AS name
             FROM phong_ban WHERE trang_thai = 1 ORDER BY ten_phong_ban'
        )->fetchAll();
        $positions = $this->pdo->query(
            'SELECT DISTINCT chuc_vu FROM nhan_vien
             WHERE chuc_vu IS NOT NULL AND TRIM(chuc_vu) <> \'\'
             ORDER BY chuc_vu'
        )->fetchAll(PDO::FETCH_COLUMN);
        $rules = $this->pdo->query(
            'SELECT r.id, r.ca_lam_viec_id AS shift_id, r.phong_ban_id AS department_id,
                    r.min_staff, r.max_staff, r.trang_thai AS active,
                    s.ten_ca AS shift_name, s.gio_bat_dau AS start, s.gio_ket_thuc AS end,
                    d.ten_phong_ban AS department
             FROM lumicine_schedule_requirements r
             INNER JOIN ca_lam_viec s ON s.id = r.ca_lam_viec_id
             INNER JOIN phong_ban d ON d.id = r.phong_ban_id
             ORDER BY s.gio_bat_dau, d.ten_phong_ban'
        )->fetchAll();
        $rulePositions = $this->pdo->query(
            'SELECT requirement_id, chuc_vu FROM lumicine_schedule_requirement_positions
             ORDER BY chuc_vu'
        )->fetchAll();
        $positionsByRule = [];
        foreach ($rulePositions as $row) {
            $positionsByRule[(int) $row['requirement_id']][] = $row['chuc_vu'];
        }
        foreach ($rules as &$rule) {
            $rule['id'] = (int) $rule['id'];
            $rule['shift_id'] = (int) $rule['shift_id'];
            $rule['department_id'] = (int) $rule['department_id'];
            $rule['min_staff'] = (int) $rule['min_staff'];
            $rule['max_staff'] = (int) $rule['max_staff'];
            $rule['active'] = (int) $rule['active'] === 1;
            $rule['positions'] = $positionsByRule[$rule['id']] ?? [];
        }
        unset($rule);

        $policy = $this->getPolicy();
        $ruleSettings = $this->getRuleSettings();
        return [
            'shiftTypes' => $shiftTypes,
            'departments' => $departments,
            'positions' => $positions,
            'rules' => $rules,
            'policy' => $policy,
            'ruleCatalog' => $this->buildRuleCatalog($rules, $policy, $ruleSettings),
        ];
    }

    public function saveRequirement(array $data): array
    {
        $shiftId = filter_var($data['shiftId'] ?? null, FILTER_VALIDATE_INT);
        $departmentId = filter_var($data['departmentId'] ?? null, FILTER_VALIDATE_INT);
        $minimum = filter_var($data['minStaff'] ?? null, FILTER_VALIDATE_INT);
        $maximum = filter_var($data['maxStaff'] ?? null, FILTER_VALIDATE_INT);
        $positions = $data['positions'] ?? [];
        if (
            !$shiftId || $shiftId < 1 || !$departmentId || $departmentId < 1
            || $minimum === false || $minimum < 0
            || !$maximum || $maximum < 1 || $maximum > 200
            || $minimum > $maximum || !is_array($positions)
            || count($positions) > 100
        ) {
            throw new InvalidArgumentException('Quy tắc nhân sự theo ca không hợp lệ.');
        }

        $shift = $this->pdo->prepare(
            'SELECT id FROM ca_lam_viec WHERE id = :id AND trang_thai = 1'
        );
        $shift->execute(['id' => $shiftId]);
        if ($shift->fetchColumn() === false) {
            throw new InvalidArgumentException('Ca làm việc không tồn tại hoặc đã ngừng hoạt động.');
        }
        $department = $this->pdo->prepare(
            'SELECT id FROM phong_ban WHERE id = :id AND trang_thai = 1'
        );
        $department->execute(['id' => $departmentId]);
        if ($department->fetchColumn() === false) {
            throw new InvalidArgumentException('Phòng ban không tồn tại hoặc đã ngừng hoạt động.');
        }

        $validPositions = $this->pdo->query(
            'SELECT DISTINCT chuc_vu FROM nhan_vien
             WHERE chuc_vu IS NOT NULL AND TRIM(chuc_vu) <> \'\''
        )->fetchAll(PDO::FETCH_COLUMN);
        $positions = array_values(array_unique(array_filter(
            $positions,
            static fn ($position): bool => is_string($position) && in_array($position, $validPositions, true)
        )));
        if (count($positions) !== count($data['positions'])) {
            throw new InvalidArgumentException('Danh sách chức vụ không hợp lệ hoặc đã thay đổi.');
        }

        $this->pdo->beginTransaction();
        try {
            $save = $this->pdo->prepare(
                'INSERT INTO lumicine_schedule_requirements
                    (ca_lam_viec_id, phong_ban_id, min_staff, max_staff, trang_thai)
                 VALUES (:shift_id, :department_id, :min_staff, :max_staff, 1)
                 ON DUPLICATE KEY UPDATE
                    min_staff = VALUES(min_staff),
                    max_staff = VALUES(max_staff),
                    trang_thai = 1'
            );
            $save->execute([
                'shift_id' => $shiftId,
                'department_id' => $departmentId,
                'min_staff' => $minimum,
                'max_staff' => $maximum,
            ]);
            $requirementId = (int) $this->pdo->lastInsertId();
            if ($requirementId === 0) {
                $find = $this->pdo->prepare(
                    'SELECT id FROM lumicine_schedule_requirements
                     WHERE ca_lam_viec_id = :shift_id AND phong_ban_id = :department_id'
                );
                $find->execute(['shift_id' => $shiftId, 'department_id' => $departmentId]);
                $requirementId = (int) $find->fetchColumn();
            }
            $removePositions = $this->pdo->prepare(
                'DELETE FROM lumicine_schedule_requirement_positions WHERE requirement_id = :id'
            );
            $removePositions->execute(['id' => $requirementId]);
            $addPosition = $this->pdo->prepare(
                'INSERT INTO lumicine_schedule_requirement_positions (requirement_id, chuc_vu)
                 VALUES (:requirement_id, :position)'
            );
            foreach ($positions as $position) {
                $addPosition->execute(['requirement_id' => $requirementId, 'position' => $position]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $this->configuration();
    }

    public function deleteRequirement(int $id): array
    {
        return $this->setRequirementStatus($id, false);
    }

    public function setRequirementStatus(int $id, bool $active): array
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Quy tắc phân ca không hợp lệ.');
        }
        $statement = $this->pdo->prepare(
            'UPDATE lumicine_schedule_requirements SET trang_thai = :active WHERE id = :id'
        );
        $statement->execute(['active' => $active ? 1 : 0, 'id' => $id]);
        if ($statement->rowCount() === 0) {
            $exists = $this->pdo->prepare(
                'SELECT 1 FROM lumicine_schedule_requirements WHERE id = :id'
            );
            $exists->execute(['id' => $id]);
            if ($exists->fetchColumn() === false) {
                throw new InvalidArgumentException('Không tìm thấy quy tắc phân ca.');
            }
        }
        return $this->configuration();
    }

    public function savePolicy(array $data): array
    {
        $daily = $this->parseHours($data['maxDailyHours'] ?? null, 'giờ tối đa trong ngày');
        $weekly = $this->parseHours($data['maxWeeklyHours'] ?? null, 'giờ tối đa trong tuần');
        $rest = $this->parseHours($data['minRestHours'] ?? 0, 'thời gian nghỉ tối thiểu');
        $maxConsecutiveNights = $data['maxConsecutiveNights'] ?? null;
        if ($maxConsecutiveNights === '') {
            $maxConsecutiveNights = null;
        }
        if ($maxConsecutiveNights !== null) {
            $maxConsecutiveNights = filter_var($maxConsecutiveNights, FILTER_VALIDATE_INT);
        }
        if (
            ($daily !== null && ($daily < 1 || $daily > 24))
            || ($weekly !== null && ($weekly < 1 || $weekly > 168))
            || $rest < 0 || $rest > 48
            || ($maxConsecutiveNights !== null && (
                $maxConsecutiveNights === false
                || $maxConsecutiveNights < 1
                || $maxConsecutiveNights > 14
            ))
        ) {
            throw new InvalidArgumentException('Giới hạn giờ làm hoặc thời gian nghỉ không hợp lệ.');
        }
        if ($daily !== null && $weekly !== null && $daily > $weekly) {
            throw new InvalidArgumentException('Giới hạn giờ trong ngày không thể lớn hơn giới hạn trong tuần.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE lumicine_schedule_policy
             SET max_daily_minutes = :daily, max_weekly_minutes = :weekly,
                 min_rest_minutes = :rest, max_consecutive_nights = :max_nights
             WHERE id = 1'
        );
        $statement->execute([
            'daily' => $daily === null ? null : (int) round($daily * 60),
            'weekly' => $weekly === null ? null : (int) round($weekly * 60),
            'rest' => (int) round($rest * 60),
            'max_nights' => $maxConsecutiveNights,
        ]);
        return $this->configuration();
    }

    public function saveRuleSettings(array $data): array
    {
        $code = $data['code'] ?? null;
        $enabled = $data['enabled'] ?? null;
        $priorityOrder = filter_var($data['priorityOrder'] ?? null, FILTER_VALIDATE_INT);
        if (
            !is_string($code)
            || !in_array($code, ['QT009', 'QT010', 'QT012'], true)
            || !is_bool($enabled)
            || $priorityOrder === false
            || $priorityOrder < 1
            || $priorityOrder > 99
        ) {
            throw new InvalidArgumentException('Cấu hình quy tắc ưu tiên không hợp lệ.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE lumicine_schedule_rule_settings
             SET trang_thai = :enabled, thu_tu_uu_tien = :priority_order
             WHERE ma_quy_tac = :code'
        );
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'priority_order' => $priorityOrder,
            'code' => $code,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $this->pdo->prepare(
                'SELECT 1 FROM lumicine_schedule_rule_settings WHERE ma_quy_tac = :code'
            );
            $exists->execute(['code' => $code]);
            if ($exists->fetchColumn() === false) {
                throw new RuntimeException('Chưa áp dụng migration quản lý quy tắc phân ca.');
            }
        }
        return $this->configuration();
    }

    public function preview(array $request): array
    {
        $criteria = $this->validateCriteria($request);
        $context = $this->loadContext($criteria);
        $engine = new SchedulePlanningEngine();
        $result = $engine->plan(
            $criteria['from_date'],
            $criteria['to_date'],
            $context['rules'],
            $context['employees'],
            $context['events'],
            $context['leaves'],
            $context['policy'],
            $context['priority_rules']
        );
        foreach ($result['assignments'] as &$assignment) {
            $assignment['location'] = $criteria['location'];
        }
        unset($assignment);

        return [
            'criteria' => $criteria,
            'assignments' => $result['assignments'],
            'shortages' => $result['shortages'],
            'issues' => $result['issues'],
            'coverage' => $result['coverage'],
            'policy' => $context['policy'],
            'summary' => [
                'planned' => count($result['assignments']),
                'shortage_slots' => array_sum(array_column($result['shortages'], 'missing')),
                'warnings' => count($result['issues']),
            ],
        ];
    }

    public function confirm(array $request): array
    {
        $criteria = $this->validateCriteria($request);
        $assignments = $request['assignments'] ?? null;
        if (!is_array($assignments) || count($assignments) > self::MAX_PLAN_ASSIGNMENTS) {
            throw new InvalidArgumentException('Danh sách ca dự kiến không hợp lệ.');
        }
        if ($assignments === []) {
            throw new InvalidArgumentException('Không có ca hợp lệ để lưu.');
        }

        $this->pdo->beginTransaction();
        try {
            $employeeIds = [];
            foreach ($assignments as $assignment) {
                if (!is_array($assignment)) {
                    throw new InvalidArgumentException('Một ca dự kiến không hợp lệ.');
                }
                $employeeId = filter_var($assignment['employeeId'] ?? null, FILTER_VALIDATE_INT);
                if (!$employeeId || $employeeId < 1) {
                    throw new InvalidArgumentException('Nhân viên trong ca dự kiến không hợp lệ.');
                }
                $employeeIds[] = $employeeId;
            }
            $employeeIds = array_values(array_unique($employeeIds));
            $lockEmployeeRows = $this->pdo->prepare(
                'SELECT id FROM nhan_vien WHERE id = :id FOR UPDATE'
            );
            foreach ($employeeIds as $employeeId) {
                $lockEmployeeRows->execute(['id' => $employeeId]);
                if ($lockEmployeeRows->fetchColumn() === false) {
                    throw new InvalidArgumentException('Không tìm thấy một hồ sơ nhân viên trong lịch dự kiến.');
                }
            }

            $context = $this->loadContext($criteria, true);
            $employeesById = [];
            foreach ($context['employees'] as $employee) {
                $employeesById[(int) $employee['id']] = $employee;
            }
            $rulesByDepartmentShift = [];
            foreach ($context['rules'] as $rule) {
                $rulesByDepartmentShift[(int) $rule['department_id'] . ':' . (int) $rule['shift_id']] = $rule;
            }

            $engine = new SchedulePlanningEngine();
            $existingRequirementCounts = [];
            foreach ($context['events'] as $events) {
                foreach ($events as $event) {
                    if (($event['source'] ?? '') !== 'assignment') {
                        continue;
                    }
                    $key = $event['date'] . ':' . (int) $event['shift_id']
                        . ':' . (int) $event['department_id'];
                    $existingRequirementCounts[$key] = ($existingRequirementCounts[$key] ?? 0) + 1;
                }
            }
            $perRuleDateCounts = [];
            $insert = $this->pdo->prepare(
                'INSERT INTO phan_ca (nhan_vien_id, ca_lam_viec_id, ngay_lam, dia_diem, trang_thai)
                 VALUES (:employee_id, :shift_id, :work_date, :location, \'DA_PHAN\')'
            );
            $seenEmployees = [];
            foreach ($assignments as $assignment) {
                $date = $assignment['date'] ?? null;
                $shiftId = filter_var($assignment['shiftId'] ?? null, FILTER_VALIDATE_INT);
                $departmentId = filter_var($assignment['departmentId'] ?? null, FILTER_VALIDATE_INT);
                $employeeId = filter_var($assignment['employeeId'] ?? null, FILTER_VALIDATE_INT);
                $location = $assignment['location'] ?? $criteria['location'];
                if (
                    !is_string($date) || !$shiftId || !$departmentId || !$employeeId
                    || !is_string($location) || $location === '' || lumicineUtf8Length($location) > 100
                    || $date < $criteria['from_date'] || $date > $criteria['to_date']
                    || ($criteria['department_id'] !== null && $departmentId !== $criteria['department_id'])
                    || !in_array($shiftId, $criteria['shift_ids'], true)
                ) {
                    throw new InvalidArgumentException('Thông tin ca dự kiến không khớp với phạm vi đã chọn.');
                }
                $this->validateDate($date);
                $ruleKey = $departmentId . ':' . $shiftId;
                $rule = $rulesByDepartmentShift[$ruleKey] ?? null;
                $employee = $employeesById[$employeeId] ?? null;
                if ($rule === null || $employee === null) {
                    throw new InvalidArgumentException('Không tìm thấy quy tắc hoặc hồ sơ nhân viên phù hợp với ca dự kiến.');
                }
                if ((int) $employee['department_id'] !== $departmentId) {
                    throw new InvalidArgumentException('Nhân viên không thuộc phòng ban của quy tắc ca làm việc.');
                }
                $reasons = $engine->candidateReasons(
                    $employee,
                    $rule,
                    $date,
                    $context['events'][$employeeId] ?? [],
                    $context['leaves'][$employeeId] ?? [],
                    $context['policy']
                );
                if ($reasons !== []) {
                    throw new InvalidArgumentException(
                        'Không thể lưu ca của ' . $employee['name'] . ': ' . $this->reasonLabels($reasons) . '.'
                    );
                }

                $slotKey = $date . ':' . $shiftId . ':' . $departmentId;
                $perRuleDateCounts[$slotKey] = ($perRuleDateCounts[$slotKey] ?? 0) + 1;
                $existingCount = $existingRequirementCounts[$slotKey] ?? 0;
                if ($existingCount + $perRuleDateCounts[$slotKey] > (int) $rule['max_staff']) {
                    throw new InvalidArgumentException(
                        'Số nhân viên dự kiến vượt mức tối đa của ca ' . $rule['shift_name'] . '.'
                    );
                }
                if ($perRuleDateCounts[$slotKey] > max(0, (int) $rule['min_staff'] - $existingCount)) {
                    throw new InvalidArgumentException(
                        'Số ca dự kiến vượt số nhân viên còn thiếu theo mức tối thiểu của quy tắc.'
                    );
                }
                $duplicateKey = $date . ':' . $shiftId . ':' . $employeeId;
                if (isset($seenEmployees[$duplicateKey])) {
                    throw new InvalidArgumentException('Một nhân viên không thể được phân hai lần vào cùng ca trong cùng ngày.');
                }
                $seenEmployees[$duplicateKey] = true;
                $exists = $this->pdo->prepare(
                    'SELECT id FROM phan_ca
                     WHERE nhan_vien_id = :employee_id
                       AND ca_lam_viec_id = :shift_id
                       AND ngay_lam = :work_date
                     LIMIT 1'
                );
                $exists->execute([
                    'employee_id' => $employeeId,
                    'shift_id' => $shiftId,
                    'work_date' => $date,
                ]);
                if ($exists->fetchColumn() !== false) {
                    throw new InvalidArgumentException(
                        'Ca của ' . $employee['name'] . ' đã tồn tại hoặc từng bị hủy; hãy bỏ ca này hoặc chọn nhân viên khác.'
                    );
                }

                $insert->execute([
                    'employee_id' => $employeeId,
                    'shift_id' => $shiftId,
                    'work_date' => $date,
                    'location' => trim($location),
                ]);
                $context['events'][$employeeId][] = $this->eventForRule($employee, $rule, $date);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new InvalidArgumentException('Lịch vừa thay đổi hoặc ca bị trùng; hãy tạo lại bản xem trước.');
            }
            throw $exception;
        }

        return ['saved' => count($assignments)];
    }

    private function validateCriteria(array $request): array
    {
        $fromDate = $request['fromDate'] ?? null;
        $toDate = $request['toDate'] ?? null;
        $departmentValue = $request['departmentId'] ?? '';
        $shiftIds = $request['shiftIds'] ?? null;
        $location = $request['location'] ?? null;
        if (
            !is_string($fromDate) || !is_string($toDate)
            || !is_array($shiftIds) || count($shiftIds) < 1 || count($shiftIds) > 50
            || !is_string($location) || trim($location) === '' || lumicineUtf8Length(trim($location)) > 100
        ) {
            throw new InvalidArgumentException('Khoảng thời gian, ca làm việc hoặc địa điểm không hợp lệ.');
        }
        $this->validateDate($fromDate);
        $this->validateDate($toDate);
        $start = new DateTimeImmutable($fromDate);
        $end = new DateTimeImmutable($toDate);
        if (
            $end < $start
            || (int) $start->diff($end)->format('%a') >= self::MAX_RANGE_DAYS
            || $fromDate < date('Y-m-d')
        ) {
            throw new InvalidArgumentException('Chỉ có thể phân ca từ hôm nay và trong khoảng tối đa 31 ngày.');
        }

        $departmentId = null;
        if ($departmentValue !== '' && $departmentValue !== null) {
            $departmentId = filter_var($departmentValue, FILTER_VALIDATE_INT);
            if (!$departmentId || $departmentId < 1) {
                throw new InvalidArgumentException('Phòng ban không hợp lệ.');
            }
        }
        $normalizedShiftIds = [];
        foreach ($shiftIds as $shiftId) {
            $id = filter_var($shiftId, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Danh sách ca làm việc không hợp lệ.');
            }
            $normalizedShiftIds[] = $id;
        }
        $normalizedShiftIds = array_values(array_unique($normalizedShiftIds));

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'department_id' => $departmentId,
            'shift_ids' => $normalizedShiftIds,
            'location' => trim($location),
        ];
    }

    private function loadContext(array $criteria, bool $forUpdate = false): array
    {
        $policy = $this->getPolicy();
        $priorityRules = $this->getPriorityRules();
        if (!in_array('QT012', array_column($priorityRules, 'code'), true)) {
            $policy['max_consecutive_nights'] = null;
        }
        $shiftPlaceholders = implode(',', array_fill(0, count($criteria['shift_ids']), '?'));
        $sql = 'SELECT r.id, r.ca_lam_viec_id AS shift_id, r.phong_ban_id AS department_id,
                       r.min_staff, r.max_staff, s.ten_ca AS shift_name,
                       s.gio_bat_dau AS start, s.gio_ket_thuc AS end,
                       s.gio_nghi_bat_dau AS break_start, s.gio_nghi_ket_thuc AS break_end,
                       d.ten_phong_ban AS department_name
                FROM lumicine_schedule_requirements r
                INNER JOIN ca_lam_viec s ON s.id = r.ca_lam_viec_id AND s.trang_thai = 1
                INNER JOIN phong_ban d ON d.id = r.phong_ban_id AND d.trang_thai = 1
                WHERE r.trang_thai = 1 AND r.ca_lam_viec_id IN (' . $shiftPlaceholders . ')';
        $parameters = $criteria['shift_ids'];
        if ($criteria['department_id'] !== null) {
            $sql .= ' AND r.phong_ban_id = ?';
            $parameters[] = $criteria['department_id'];
        }
        $sql .= ' ORDER BY s.gio_bat_dau, r.phong_ban_id';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $rules = $statement->fetchAll();
        if ($rules === []) {
            throw new InvalidArgumentException('Chưa có quy tắc đang hoạt động cho phòng ban và ca đã chọn.');
        }

        $positions = $this->pdo->query(
            'SELECT requirement_id, chuc_vu FROM lumicine_schedule_requirement_positions ORDER BY chuc_vu'
        )->fetchAll();
        $positionsByRule = [];
        foreach ($positions as $row) {
            $positionsByRule[(int) $row['requirement_id']][] = $row['chuc_vu'];
        }
        foreach ($rules as &$rule) {
            $rule['id'] = (int) $rule['id'];
            $rule['shift_id'] = (int) $rule['shift_id'];
            $rule['department_id'] = (int) $rule['department_id'];
            $rule['min_staff'] = (int) $rule['min_staff'];
            $rule['max_staff'] = (int) $rule['max_staff'];
            $rule['positions'] = $positionsByRule[$rule['id']] ?? [];
        }
        unset($rule);

        $employees = $this->pdo->query(
            'SELECT e.id, e.ho_ten AS name, COALESCE(e.chuc_vu, \'\') AS position,
                    e.ma_phong_ban AS department_id, e.trang_thai AS active,
                    e.ngay_nghi_viec AS termination_date
             FROM nhan_vien e'
        )->fetchAll();
        foreach ($employees as &$employee) {
            $employee['id'] = (int) $employee['id'];
            $employee['department_id'] = $employee['department_id'] === null
                ? 0
                : (int) $employee['department_id'];
            $employee['active'] = (int) $employee['active'];
            $employee['blocked_slots'] = [];
        }
        unset($employee);

        $blockedSlotStatement = $this->pdo->prepare(
            'SELECT nhan_vien_id AS employee_id, ca_lam_viec_id AS shift_id, ngay_lam AS work_date
             FROM phan_ca
             WHERE trang_thai = \'HUY\'
               AND ngay_lam BETWEEN :from_date AND :to_date'
        );
        $blockedSlotStatement->execute([
            'from_date' => $criteria['from_date'],
            'to_date' => $criteria['to_date'],
        ]);
        $blockedSlotsByEmployee = [];
        foreach ($blockedSlotStatement->fetchAll() as $row) {
            $blockedSlotsByEmployee[(int) $row['employee_id']][] =
                $row['work_date'] . ':' . (int) $row['shift_id'];
        }
        foreach ($employees as &$employee) {
            $employee['blocked_slots'] = $blockedSlotsByEmployee[(int) $employee['id']] ?? [];
        }
        unset($employee);

        $fromWeek = (new DateTimeImmutable($criteria['from_date']))
            ->modify('monday this week')->modify('-1 day')->format('Y-m-d');
        $toWeek = (new DateTimeImmutable($criteria['to_date']))
            ->modify('sunday this week')->modify('+1 day')->format('Y-m-d');
        $nightHistoryStart = (new DateTimeImmutable($criteria['from_date']))
            ->modify('-' . (int) ($policy['max_consecutive_nights'] ?? 0) . ' days')
            ->format('Y-m-d');
        $scheduleFrom = min($fromWeek, $nightHistoryStart);
        $scheduleStatement = $this->pdo->prepare(
            'SELECT a.nhan_vien_id AS employee_id, e.ma_phong_ban AS department_id,
                    a.ngay_lam AS work_date,
                    a.ca_lam_viec_id AS shift_id, s.gio_bat_dau AS start,
                    s.gio_ket_thuc AS end, s.gio_nghi_bat_dau AS break_start,
                    s.gio_nghi_ket_thuc AS break_end
             FROM phan_ca a
             INNER JOIN ca_lam_viec s ON s.id = a.ca_lam_viec_id
             INNER JOIN nhan_vien e ON e.id = a.nhan_vien_id
             WHERE a.trang_thai = \'DA_PHAN\'
               AND a.ngay_lam BETWEEN :from_date AND :to_date'
        );
        $scheduleStatement->execute(['from_date' => $scheduleFrom, 'to_date' => $toWeek]);
        $events = [];
        foreach ($scheduleStatement->fetchAll() as $row) {
            $employeeId = (int) $row['employee_id'];
            $employee = ['id' => $employeeId, 'department_id' => (int) $row['department_id']];
            $rule = [
                'shift_id' => (int) $row['shift_id'],
                'start' => $row['start'],
                'end' => $row['end'],
                'break_start' => $row['break_start'],
                'break_end' => $row['break_end'],
            ];
            $event = $this->eventForRule($employee, $rule, (string) $row['work_date']);
            $events[$employeeId][] = $event;
        }

        $overtimeStatement = $this->pdo->prepare(
            'SELECT COALESCE(t.nhan_vien_id, c.nhan_vien_id) AS employee_id,
                    t.gio_bat_dau AS start_at, t.gio_ket_thuc AS end_at,
                    GREATEST(0, COALESCE(t.tong_phut_tang_ca,
                        TIMESTAMPDIFF(MINUTE, t.gio_bat_dau, t.gio_ket_thuc))) AS minutes
             FROM tang_ca t
             LEFT JOIN cham_cong c ON c.id = t.cham_cong_id
             WHERE t.trang_thai = \'DA_DUYET\'
               AND t.gio_bat_dau < :range_end AND t.gio_ket_thuc > :range_start'
        );
        $overtimeStatement->execute([
            'range_start' => $fromWeek . ' 00:00:00',
            'range_end' => $toWeek . ' 23:59:59',
        ]);
        foreach ($overtimeStatement->fetchAll() as $row) {
            $employeeId = (int) $row['employee_id'];
            if ($employeeId < 1) {
                continue;
            }
            $events[$employeeId][] = [
                'employee_id' => $employeeId,
                'department_id' => (int) ($this->employeeDepartment($employees, $employeeId)),
                'shift_id' => 0,
                'date' => substr($row['start_at'], 0, 10),
                'start_at' => $row['start_at'],
                'end_at' => $row['end_at'],
                'minutes' => (int) $row['minutes'],
                'source' => 'overtime',
            ];
        }

        $leaveStatement = $this->pdo->prepare(
            'SELECT nhan_vien_id AS employee_id, gio_bat_dau AS start_at, gio_ket_thuc AS end_at
             FROM lumicine_leave_requests
             WHERE trang_thai = \'DA_DUYET\'
               AND gio_bat_dau < :range_end AND gio_ket_thuc > :range_start'
        );
        $leaveStatement->execute([
            'range_start' => $fromWeek . ' 00:00:00',
            'range_end' => $toWeek . ' 23:59:59',
        ]);
        $leaves = [];
        foreach ($leaveStatement->fetchAll() as $row) {
            $leaves[(int) $row['employee_id']][] = [
                'start_at' => $row['start_at'],
                'end_at' => $row['end_at'],
            ];
        }

        return [
            'rules' => $rules,
            'employees' => $employees,
            'events' => $events,
            'leaves' => $leaves,
            'policy' => $policy,
            'priority_rules' => $priorityRules,
        ];
    }

    private function eventForRule(array $employee, array $rule, string $date): array
    {
        $start = new DateTimeImmutable($date . ' ' . $rule['start']);
        $end = new DateTimeImmutable($date . ' ' . $rule['end']);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }
        $minutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
        if (!empty($rule['break_start']) && !empty($rule['break_end'])) {
            $breakStart = new DateTimeImmutable($date . ' ' . $rule['break_start']);
            $breakEnd = new DateTimeImmutable($date . ' ' . $rule['break_end']);
            if ($breakStart < $start) {
                $breakStart = $breakStart->modify('+1 day');
            }
            if ($breakEnd <= $breakStart) {
                $breakEnd = $breakEnd->modify('+1 day');
            }
            if ($breakStart >= $start && $breakEnd <= $end) {
                $minutes -= (int) (($breakEnd->getTimestamp() - $breakStart->getTimestamp()) / 60);
            }
        }
        return [
            'employee_id' => (int) $employee['id'],
            'department_id' => (int) $employee['department_id'],
            'shift_id' => (int) $rule['shift_id'],
            'date' => $date,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $end->format('Y-m-d H:i:s'),
            'minutes' => max(0, $minutes),
            'source' => 'assignment',
        ];
    }

    private function employeeDepartment(array $employees, int $employeeId): int
    {
        foreach ($employees as $employee) {
            if ((int) $employee['id'] === $employeeId) {
                return (int) $employee['department_id'];
            }
        }
        return 0;
    }

    private function getPolicy(): array
    {
        $row = $this->pdo->query(
            'SELECT max_daily_minutes, max_weekly_minutes, min_rest_minutes,
                    max_consecutive_nights
             FROM lumicine_schedule_policy WHERE id = 1'
        )->fetch();
        if ($row === false) {
            throw new RuntimeException('Chưa áp dụng migration quy tắc tự động phân ca.');
        }
        return [
            'max_daily_minutes' => $row['max_daily_minutes'] === null
                ? null
                : (int) $row['max_daily_minutes'],
            'max_weekly_minutes' => $row['max_weekly_minutes'] === null
                ? null
                : (int) $row['max_weekly_minutes'],
            'min_rest_minutes' => (int) $row['min_rest_minutes'],
            'max_consecutive_nights' => $row['max_consecutive_nights'] === null
                ? null
                : (int) $row['max_consecutive_nights'],
        ];
    }

    private function getRuleSettings(): array
    {
        $rows = $this->pdo->query(
            'SELECT ma_quy_tac AS code, trang_thai AS active,
                    thu_tu_uu_tien AS priority_order
             FROM lumicine_schedule_rule_settings'
        )->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['code']] = [
                'active' => (int) $row['active'] === 1,
                'priority_order' => $row['priority_order'] === null
                    ? null
                    : (int) $row['priority_order'],
            ];
        }
        return $settings;
    }

    private function getPriorityRules(): array
    {
        return $this->pdo->query(
            'SELECT ma_quy_tac AS code, thu_tu_uu_tien AS priority_order
             FROM lumicine_schedule_rule_settings
             WHERE trang_thai = 1
               AND ma_quy_tac IN (\'QT009\', \'QT010\', \'QT012\')
             ORDER BY thu_tu_uu_tien, ma_quy_tac'
        )->fetchAll();
    }

    private function buildRuleCatalog(array $requirements, array $policy, array $settings): array
    {
        $activeRequirements = array_values(array_filter(
            $requirements,
            static fn (array $requirement): bool => $requirement['active']
        ));
        $activeRuleCount = count($activeRequirements);
        $configured = [
            'QT001' => $activeRuleCount > 0,
            'QT002' => $activeRuleCount > 0,
            'QT003' => true,
            'QT004' => true,
            'QT005' => $policy['max_daily_minutes'] !== null
                || $policy['max_weekly_minutes'] !== null,
            'QT006' => $policy['min_rest_minutes'] > 0,
            'QT007' => true,
            'QT008' => $activeRuleCount > 0,
            'QT009' => true,
            'QT010' => true,
            'QT011' => false,
            'QT012' => true,
        ];

        $catalog = [];
        foreach (self::RULE_DEFINITIONS as $code => $definition) {
            $mandatory = $definition['type'] === 'mandatory';
            $setting = $settings[$code] ?? ['active' => false, 'priority_order' => null];
            $enabled = $mandatory || $setting['active'];
            $available = $code !== 'QT011';
            if (!$available) {
                $status = 'unavailable';
            } elseif (!$enabled) {
                $status = 'inactive';
            } elseif (!($configured[$code] ?? false)) {
                $status = 'not_configured';
            } else {
                $status = 'active';
            }

            $configuration = match ($code) {
                'QT001', 'QT002', 'QT008' => $activeRuleCount . ' ca/phòng ban đang cấu hình',
                'QT005' => $this->formatHourLimits($policy),
                'QT006' => $policy['min_rest_minutes'] . ' phút nghỉ tối thiểu',
                'QT009', 'QT010', 'QT012' => $enabled
                    ? 'Ưu tiên số ' . (int) ($setting['priority_order'] ?? 0)
                    : 'Đang tắt',
                'QT011' => 'Chưa có module đăng ký nguyện vọng ca',
                default => 'Luôn được kiểm tra',
            };
            if ($code === 'QT012' && $policy['max_consecutive_nights'] !== null) {
                $configuration .= ' · tối đa ' . $policy['max_consecutive_nights'] . ' ca';
            }

            $catalog[] = [
                'code' => $code,
                'name' => $definition['name'],
                'type' => $definition['type'],
                'scope' => $definition['scope'],
                'description' => $definition['description'],
                'enabled' => $enabled,
                'available' => $available,
                'status' => $status,
                'status_label' => [
                    'active' => 'Đang áp dụng',
                    'inactive' => 'Đã tắt',
                    'not_configured' => 'Chưa cấu hình',
                    'unavailable' => 'Chưa hỗ trợ dữ liệu',
                ][$status],
                'configuration' => $configuration,
                'priority_order' => $setting['priority_order'],
                'can_manage' => in_array($code, ['QT009', 'QT010', 'QT012'], true),
                'target' => $definition['target'],
            ];
        }
        return $catalog;
    }

    private function formatHourLimits(array $policy): string
    {
        $daily = $policy['max_daily_minutes'] === null
            ? 'không giới hạn/ngày'
            : 'tối đa ' . ($policy['max_daily_minutes'] / 60) . ' giờ/ngày';
        $weekly = $policy['max_weekly_minutes'] === null
            ? 'không giới hạn/tuần'
            : 'tối đa ' . ($policy['max_weekly_minutes'] / 60) . ' giờ/tuần';
        return $daily . ' · ' . $weekly;
    }

    private function parseHours($value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new InvalidArgumentException('Giá trị ' . $label . ' không hợp lệ.');
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Giá trị ' . $label . ' không hợp lệ.');
        }
        $hours = (float) $value;
        if (!is_finite($hours) || $hours < 0) {
            throw new InvalidArgumentException('Giá trị ' . $label . ' không hợp lệ.');
        }
        return $hours;
    }

    private function reasonLabels(array $reasons): string
    {
        $labels = [
            'inactive' => 'QT007: nhân viên đã ngừng hoạt động',
            'terminated' => 'QT007: đã đến ngày nghỉ việc',
            'position' => 'QT001: chức vụ không phù hợp',
            'previously_cancelled' => 'QT003: ca này đã từng bị hủy',
            'approved_leave' => 'QT004: có lịch nghỉ phép đã duyệt',
            'schedule_conflict' => 'QT003: bị trùng lịch',
            'insufficient_rest' => 'QT006: không đủ thời gian nghỉ giữa ca',
            'daily_limit' => 'QT005: vượt giới hạn giờ trong ngày',
            'weekly_limit' => 'QT005: vượt giới hạn giờ trong tuần',
            'night_streak_limit' => 'QT012: vượt số ca đêm liên tiếp tối đa',
        ];
        return implode(', ', array_map(
            static fn (string $reason): string => $labels[$reason] ?? $reason,
            $reasons
        ));
    }

    private function validateDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Ngày không hợp lệ.');
        }
    }
}
