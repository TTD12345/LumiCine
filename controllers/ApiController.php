<?php

class ApiController
{
    private const ADMIN_ROLES = ['accountant', 'hr', 'chairman'];

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        $user = $_SESSION['user'] ?? null;
        if (!is_array($user) || !isset($user['id'], $user['role'])) {
            $this->respond(['error' => 'Phiên đăng nhập đã hết hạn.'], 401);
        }

        $resource = $_GET['resource'] ?? '';
        if (!is_string($resource)) {
            $this->respond(['error' => 'Yêu cầu không hợp lệ.'], 400);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'POST'], true)) {
            header('Allow: GET, POST');
            $this->respond(['error' => 'Phương thức không được hỗ trợ.'], 405);
        }
        if ($method !== 'GET' && !$this->validCsrfToken()) {
            $this->respond(['error' => 'Yêu cầu không hợp lệ hoặc phiên đã hết hạn.'], 400);
        }

        try {
            if ($resource === 'change-password') {
                if ($user['role'] !== 'employee') {
                    $this->respond(['error' => 'Chỉ nhân viên mới có thể đổi mật khẩu tại đây.'], 403);
                }

                $payload = $this->readPayload();
                $operation = $payload['operation'] ?? 'change';
                if ($operation === 'keep') {
                    $dontShowAgain = $payload['dontShowAgain'] ?? null;
                    if (!is_bool($dontShowAgain)) {
                        throw new InvalidArgumentException('Lựa chọn nhắc đổi mật khẩu không hợp lệ.');
                    }

                    (new UserModel())->dismissEmployeePasswordPrompt(
                        (int) $user['id'],
                        $dontShowAgain
                    );
                    $_SESSION['user']['must_change_password'] = false;
                    $this->respond(['success' => true]);
                }
                if ($operation !== 'change') {
                    throw new InvalidArgumentException('Thao tác đổi mật khẩu không hợp lệ.');
                }

                $currentPassword = $payload['currentPassword'] ?? null;
                $newPassword = $payload['newPassword'] ?? null;
                $confirmation = $payload['confirmation'] ?? null;
                if (
                    ($currentPassword !== null && !is_string($currentPassword))
                    || !is_string($newPassword)
                    || !is_string($confirmation)
                ) {
                    throw new InvalidArgumentException('Thông tin đổi mật khẩu không hợp lệ.');
                }

                (new UserModel())->changeEmployeePassword(
                    (int) $user['id'],
                    $currentPassword,
                    $newPassword,
                    $confirmation
                );
                $_SESSION['user']['must_change_password'] = false;
                $this->respond(['success' => true]);
            }
            if (
                $user['role'] === 'employee'
                && !empty($user['must_change_password'])
            ) {
                $this->respond([
                    'error' => 'Bạn cần đổi mật khẩu trước khi sử dụng chức năng này.',
                ], 403);
            }

            $workforce = new WorkforceModel();
            $linkedEmployeeId = $workforce->findEmployeeIdByUserEmployeeId(
                isset($user['employee_id']) ? (int) $user['employee_id'] : null
            ) ?? $workforce->findEmployeeIdByCode(
                isset($user['employee_code']) && is_string($user['employee_code'])
                    ? $user['employee_code']
                    : null
            );
            $employeeId = null;
            if (!in_array($user['role'], self::ADMIN_ROLES, true)) {
                if ($linkedEmployeeId === null) {
                    $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                }
                $employeeId = $linkedEmployeeId;
            }

            if ($resource === 'employees') {
                $this->requireAdmin($user['role']);
                if ($method === 'GET') {
                    $includeInactive = ($_GET['includeInactive'] ?? '') === '1';
                    $this->respond([
                        'employees' => $includeInactive
                            ? $workforce->listManagedEmployees()
                            : $workforce->listEmployees(),
                    ]);
                }

                $payload = $this->readPayload();
                $operation = $payload['operation'] ?? 'save';
                if ($operation === 'status') {
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    $active = $payload['active'] ?? null;
                    if (!$id || $id < 1 || !is_bool($active)) {
                        throw new InvalidArgumentException('Thông tin trạng thái nhân viên không hợp lệ.');
                    }
                    $workforce->setEmployeeActive($id, $active);
                    $this->respond(['success' => true]);
                }
                if ($operation !== 'save') {
                    throw new InvalidArgumentException('Thao tác nhân viên không hợp lệ.');
                }

                $this->respond(['id' => $workforce->saveEmployee($payload)], 201);
            }
            if ($resource === 'departments') {
                $this->requireAdmin($user['role']);
                if ($method === 'GET') {
                    $this->respond(['departments' => $workforce->listDepartments()]);
                }

                $payload = $this->readPayload();
                $operation = $payload['operation'] ?? 'save';
                if ($operation === 'delete') {
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || $id < 1) {
                        throw new InvalidArgumentException('Phòng ban không hợp lệ.');
                    }
                    $workforce->deleteDepartment($id);
                    $this->respond(['success' => true]);
                }
                if ($operation !== 'save') {
                    throw new InvalidArgumentException('Thao tác phòng ban không hợp lệ.');
                }

                $this->respond(['id' => $workforce->saveDepartment($payload)], 201);
            }
            if ($resource === 'user-mappings') {
                $this->requireAdmin($user['role']);
                if ($method === 'GET') {
                    $this->respond(['accounts' => $workforce->listEmployeeAccountMappings()]);
                }

                $payload = $this->readPayload();
                $userId = filter_var($payload['userId'] ?? null, FILTER_VALIDATE_INT);
                $employeeCode = $payload['employeeCode'] ?? null;
                if (!$userId || $userId < 1 || ($employeeCode !== null && !is_string($employeeCode))) {
                    throw new InvalidArgumentException('Thông tin liên kết tài khoản không hợp lệ.');
                }

                $workforce->updateEmployeeAccountMapping($userId, $employeeCode);
                $this->respond(['success' => true]);
            }
            if ($resource === 'employee-accounts') {
                $this->requireAdmin($user['role']);
                $accounts = new UserModel();
                if ($method === 'GET') {
                    $this->respond([
                        'accounts' => $accounts->listEmployeeAccounts(),
                        'employees' => $accounts->listEmployeesWithoutAccounts(),
                    ]);
                }

                $payload = $this->readPayload();
                if (($payload['operation'] ?? 'save') === 'delete') {
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || $id < 1) {
                        throw new InvalidArgumentException('Tài khoản cần xóa không hợp lệ.');
                    }
                    $accounts->deleteEmployeeAccount($id, (int) $user['id']);
                    $this->respond(['success' => true]);
                }

                $this->respond([
                    'id' => $accounts->saveEmployeeAccount($payload, (int) $user['id']),
                ], 201);
            }
            if ($resource === 'schedule') {
                $date = $_GET['date'] ?? '';
                if ($method === 'GET') {
                    $includeInactive = ($_GET['includeInactive'] ?? '') === '1';
                    if (!is_string($date)) {
                        throw new InvalidArgumentException('Ngày không hợp lệ.');
                    }
                    $this->respond([
                        'assignments' => $workforce->listAssignments($date, $employeeId),
                        'shiftTypes' => $workforce->listShiftTypes($includeInactive),
                    ]);
                }
                $this->requireAdmin($user['role']);
                $payload = $this->readPayload();
                $operation = $payload['operation'] ?? 'save';
                if ($operation === 'shift-status') {
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    $active = $payload['active'] ?? null;
                    if (!$id || $id < 1 || !is_bool($active)) {
                        throw new InvalidArgumentException('Thông tin trạng thái ca làm việc không hợp lệ.');
                    }
                    $workforce->setShiftTypeActive($id, $active);
                    $this->respond([
                        'success' => true,
                        'shiftTypes' => $workforce->listShiftTypes(true),
                    ]);
                }
                if ($operation === 'cancel') {
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || $id < 1) {
                        throw new InvalidArgumentException('Ca làm việc không hợp lệ.');
                    }
                    $workforce->cancelAssignment($id);
                    $this->respond(['success' => true]);
                }
                $this->respond(['assignment' => $workforce->saveAssignment($payload)], 201);
            }
            if ($resource === 'attendance') {
                if ($linkedEmployeeId === null) {
                    $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                }
                if ($method === 'GET') {
                    $date = $_GET['date'] ?? '';
                    if (!is_string($date)) {
                        throw new InvalidArgumentException('Ngày không hợp lệ.');
                    }
                    $this->respond([
                        'record' => $workforce->getAttendance($linkedEmployeeId, $date),
                        'history' => $workforce->attendanceHistory($linkedEmployeeId),
                    ]);
                }
                $payload = $this->readPayload();
                $date = $payload['date'] ?? null;
                if (!is_string($date)) {
                    throw new InvalidArgumentException('Ngày chấm công không hợp lệ.');
                }
                $this->respond($workforce->punch($linkedEmployeeId, $date));
            }
            if ($resource === 'employee-timesheet') {
                if ($linkedEmployeeId === null) {
                    $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                }
                $month = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);
                $year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
                if (!$month || !$year) {
                    throw new InvalidArgumentException('Tháng bảng công không hợp lệ.');
                }
                $this->respond($workforce->employeeTimesheet($linkedEmployeeId, $month, $year));
            }
            if ($resource === 'employee-profile') {
                if ($linkedEmployeeId === null) {
                    $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                }
                if ($method === 'GET') {
                    $this->respond(['profile' => $workforce->employeeProfile($linkedEmployeeId)]);
                }
                if ($user['role'] !== 'employee') {
                    $this->respond(['error' => 'Chỉ nhân viên mới có thể cập nhật thông tin liên hệ cá nhân.'], 403);
                }
                $payload = $this->readPayload();
                $phone = $payload['phone'] ?? null;
                $email = $payload['email'] ?? null;
                if (!is_string($phone) || !is_string($email)) {
                    throw new InvalidArgumentException('Thông tin liên hệ không hợp lệ.');
                }
                $workforce->saveEmployeeProfile($linkedEmployeeId, $phone, $email);
                $this->respond(['success' => true]);
            }
            if ($resource === 'overtime') {
                if ($method === 'GET') {
                    $personalOnly = ($_GET['mine'] ?? '') === '1';
                    if ($personalOnly && $linkedEmployeeId === null) {
                        $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                    }
                    $this->respond([
                        'requests' => $workforce->overtimeRequests(
                            $personalOnly ? $linkedEmployeeId : $employeeId
                        ),
                    ]);
                }
                $payload = $this->readPayload();
                $operation = $payload['operation'] ?? 'submit';
                if ($operation === 'review') {
                    $this->requireAdmin($user['role']);
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    $status = $payload['status'] ?? '';
                    if (!$id || $id < 1 || !in_array($status, ['approved', 'rejected'], true)) {
                        throw new InvalidArgumentException('Thông tin xử lý đơn tăng ca không hợp lệ.');
                    }
                    $workforce->reviewOvertime($id, $status, $linkedEmployeeId);
                    $this->respond(['success' => true]);
                }
                if ($linkedEmployeeId === null) {
                    $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                }
                $start = $payload['start'] ?? null;
                $end = $payload['end'] ?? null;
                $reason = $payload['reason'] ?? null;
                if (!is_string($start) || !is_string($end) || !is_string($reason)) {
                    throw new InvalidArgumentException('Thông tin đơn tăng ca không hợp lệ.');
                }
                $workforce->submitOvertime(
                    $linkedEmployeeId,
                    $start,
                    $end,
                    $reason
                );
                $this->respond(['success' => true], 201);
            }
            if ($resource === 'leave') {
                if ($method === 'GET') {
                    $personalOnly = ($_GET['mine'] ?? '') === '1';
                    if ($personalOnly && $linkedEmployeeId === null) {
                        $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                    }
                    $this->respond([
                        'requests' => $workforce->leaveRequests(
                            $personalOnly ? $linkedEmployeeId : $employeeId
                        ),
                        'types' => $workforce->leaveTypes(),
                    ]);
                }
                $payload = $this->readPayload();
                $operation = $payload['operation'] ?? 'submit';
                if ($operation === 'review') {
                    $this->requireAdmin($user['role']);
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    $status = $payload['status'] ?? '';
                    if (!$id || $id < 1 || !in_array($status, ['approved', 'rejected'], true)) {
                        throw new InvalidArgumentException('Thông tin xử lý đơn nghỉ phép không hợp lệ.');
                    }
                    $workforce->reviewLeave($id, $status, $linkedEmployeeId);
                    $this->respond(['success' => true]);
                }
                if ($operation === 'cancel') {
                    $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || $id < 1) {
                        throw new InvalidArgumentException('Đơn nghỉ phép không hợp lệ.');
                    }
                    if ($linkedEmployeeId === null) {
                        $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                    }
                    $workforce->cancelLeave($id, $linkedEmployeeId);
                    $this->respond(['success' => true]);
                }
                if ($linkedEmployeeId === null) {
                    $this->respond(['error' => 'Tài khoản chưa được liên kết với hồ sơ nhân viên.'], 403);
                }
                $start = $payload['start'] ?? null;
                $end = $payload['end'] ?? null;
                $reason = $payload['reason'] ?? null;
                $leaveType = $payload['leaveType'] ?? null;
                if (
                    !is_string($start)
                    || !is_string($end)
                    || !is_string($reason)
                    || !is_string($leaveType)
                ) {
                    throw new InvalidArgumentException('Thông tin đơn nghỉ phép không hợp lệ.');
                }
                $workforce->submitLeave($linkedEmployeeId, $start, $end, $reason, $leaveType);
                $this->respond(['success' => true], 201);
            }
            if ($resource === 'holidays') {
                $year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
                if ($method === 'GET') {
                    if (!$year || $year < 2020 || $year > 2100) {
                        throw new InvalidArgumentException('Năm không hợp lệ.');
                    }
                    $this->respond(['holidays' => $workforce->holidays($year)]);
                }
                $this->requireAdmin($user['role']);
                $payload = $this->readPayload();
                if (($payload['operation'] ?? '') === 'remove') {
                    $date = $payload['date'] ?? null;
                    if (!is_string($date)) {
                        throw new InvalidArgumentException('Ngày không hợp lệ.');
                    }
                    $workforce->removeHoliday($date);
                } else {
                    $date = $payload['date'] ?? null;
                    $name = $payload['name'] ?? null;
                    $type = $payload['type'] ?? null;
                    if (!is_string($date) || !is_string($name) || !is_string($type)) {
                        throw new InvalidArgumentException('Thông tin ngày nghỉ không hợp lệ.');
                    }
                    $workforce->addHoliday(
                        $date,
                        $name,
                        $type
                    );
                }
                $this->respond(['success' => true]);
            }
            if ($resource === 'payroll-settings') {
                $this->requireAdmin($user['role']);
                if ($method === 'GET') {
                    $this->respond(['payrollStartDay' => $workforce->payrollStartDay()]);
                }

                $payload = $this->readPayload();
                $rawStartDay = $payload['payrollStartDay'] ?? null;
                if (!is_int($rawStartDay) && !(is_string($rawStartDay) && ctype_digit($rawStartDay))) {
                    throw new InvalidArgumentException('Ngày bắt đầu kỳ lương không hợp lệ.');
                }
                $startDay = filter_var($rawStartDay, FILTER_VALIDATE_INT);
                if ($startDay === false) {
                    throw new InvalidArgumentException('Ngày bắt đầu kỳ lương không hợp lệ.');
                }
                $workforce->savePayrollStartDay($startDay);
                $this->respond([
                    'success' => true,
                    'payrollStartDay' => $workforce->payrollStartDay(),
                ]);
            }
            if ($resource === 'report') {
                $this->requireAdmin($user['role']);
                $month = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);
                $year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
                if (!$month || !$year) {
                    throw new InvalidArgumentException('Kỳ báo cáo không hợp lệ.');
                }
                $this->respond([
                    'employees' => $workforce->attendanceReport($month, $year),
                    'payrollStartDay' => $workforce->payrollStartDay(),
                ]);
            }

            $this->respond(['error' => 'Không tìm thấy chức năng.'], 404);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            error_log('LumiCine API error: ' . $exception->getMessage());
            $this->respond([
                'error' => 'Không thể xử lý yêu cầu. Kiểm tra cấu hình database và migration.',
                'detail' => $exception->getMessage(),
            ], 500);
        }
    }

    private function readPayload(): array
    {
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload === false ? '' : $rawPayload, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Dữ liệu gửi lên không hợp lệ.');
        }

        return $payload;
    }

    private function validCsrfToken(): bool
    {
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';

        return is_string($provided)
            && is_string($expected)
            && $expected !== ''
            && hash_equals($expected, $provided);
    }

    private function requireAdmin(string $role): void
    {
        if (!in_array($role, self::ADMIN_ROLES, true)) {
            $this->respond(['error' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
        }
    }

    private function respond(array $body, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
