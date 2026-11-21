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
            $workforce = new WorkforceModel();
            $linkedEmployeeId = $workforce->findEmployeeIdByCode(
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
                $this->respond(['employees' => $workforce->listEmployees()]);
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
            if ($resource === 'schedule') {
                $date = $_GET['date'] ?? '';
                if ($method === 'GET') {
                    if (!is_string($date)) {
                        throw new InvalidArgumentException('Ngày không hợp lệ.');
                    }
                    $this->respond([
                        'assignments' => $workforce->listAssignments($date, $employeeId),
                        'shiftTypes' => $workforce->listShiftTypes(),
                    ]);
                }
                $this->requireAdmin($user['role']);
                $payload = $this->readPayload();
                $operation = $payload['operation'] ?? 'save';
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
            if ($resource === 'overtime') {
                if ($method === 'GET') {
                    $this->respond(['requests' => $workforce->overtimeRequests($employeeId)]);
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
            if ($resource === 'report') {
                $this->requireAdmin($user['role']);
                $month = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);
                $year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
                if (!$month || !$year) {
                    throw new InvalidArgumentException('Kỳ báo cáo không hợp lệ.');
                }
                $this->respond(['employees' => $workforce->attendanceReport($month, $year)]);
            }

            $this->respond(['error' => 'Không tìm thấy chức năng.'], 404);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            error_log('LumiCine API error: ' . $exception->getMessage());
            $this->respond(['error' => 'Không thể xử lý yêu cầu. Kiểm tra cấu hình database và migration.'], 500);
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
