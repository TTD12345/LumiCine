<?php

class HomeController
{
    private const ROLE_LABELS = [
        'employee' => 'Nhân viên',
        'accountant' => 'Kế toán',
        'hr' => 'HR',
        'chairman' => 'Chủ tịch',
    ];

    private const ADMIN_ROLES = ['accountant', 'hr', 'chairman'];

    public function index()
    {
        $currentUser = $this->requireUser();
        $workforce = new WorkforceModel();
        $isAdmin = in_array($currentUser['role'], self::ADMIN_ROLES, true);
        $employeeId = $workforce->findEmployeeIdByCode($currentUser['employee_code'] ?? null);
        if (!$isAdmin && $employeeId === null) {
            $this->showEmployeeMappingRequired();
            return;
        }

        $initialDate = date('Y-m-d');
        $shifts = $workforce->listAssignments($initialDate, $isAdmin ? null : $employeeId);
        $employees = $isAdmin ? $workforce->listEmployees() : [];
        $canManageShifts = $isAdmin;
        $roleLabel = self::ROLE_LABELS[$currentUser['role']];
        $csrfToken = $this->csrfToken();
        require_once PATH_VIEW . 'main.php';
    }


    public function admin()
    {
        $currentUser = $this->requireUser();
        if (!in_array($currentUser['role'], self::ADMIN_ROLES, true)) {
            http_response_code(403);
            $csrfToken = $this->csrfToken();
            require_once PATH_VIEW . 'forbidden.php';
            return;
        }

        $month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
        $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
        $month = ($month !== false && $month >= 1 && $month <= 12) ? $month : (int) date('n');
        $year = ($year !== false && $year >= 2020 && $year <= 2100) ? $year : (int) date('Y');
        $workforce = new WorkforceModel();
        $payrollStartDay = $workforce->payrollStartDay();
        $employees = $workforce->attendanceReport($month, $year);
        $roleLabel = self::ROLE_LABELS[$currentUser['role']];
        $csrfToken = $this->csrfToken();
        require_once PATH_VIEW . 'admin.php';
    }

    public function attendance()
    {
        $currentUser = $this->requireUser();
        $employeeId = (new WorkforceModel())->findEmployeeIdByCode($currentUser['employee_code'] ?? null);
        if ($employeeId === null) {
            $this->showEmployeeMappingRequired();
            return;
        }
        $roleLabel = self::ROLE_LABELS[$currentUser['role']];
        $csrfToken = $this->csrfToken();
        require_once PATH_VIEW . 'attendance.php';
    }

    public function employeeSection(string $section)
    {
        $currentUser = $this->requireUser();
        $workforce = new WorkforceModel();
        $isAdmin = in_array($currentUser['role'], self::ADMIN_ROLES, true);
        $employeeId = $workforce->findEmployeeIdByCode($currentUser['employee_code'] ?? null);
        if (!$isAdmin && $employeeId === null) {
            $this->showEmployeeMappingRequired();
            return;
        }
        if (!$isAdmin && in_array($section, ['employees', 'reports'], true)) {
            http_response_code(403);
            $csrfToken = $this->csrfToken();
            require_once PATH_VIEW . 'forbidden.php';
            return;
        }
        $employeeRecords = $isAdmin ? $workforce->listEmployees() : [];
        $employeeShifts = $workforce->listAssignments(
            date('Y-m-d'),
            $isAdmin ? null : $employeeId
        );
        $overtimeRequests = $workforce->overtimeRequests($isAdmin ? null : $employeeId);
        $overtimeCount = count($overtimeRequests);
        $canSubmitOvertime = $employeeId !== null;
        $attendanceToday = $employeeId === null
            ? null
            : $workforce->getAttendance($employeeId, date('Y-m-d'));
        $roleLabel = self::ROLE_LABELS[$currentUser['role']];
        $csrfToken = $this->csrfToken();
        require_once PATH_VIEW . 'employee_section.php';
    }

    public function adminSection(string $section)
    {
        $currentUser = $this->requireUser();
        if (!in_array($currentUser['role'], self::ADMIN_ROLES, true)) {
            http_response_code(403);
            $csrfToken = $this->csrfToken();
            require_once PATH_VIEW . 'forbidden.php';
            return;
        }

        $workforce = new WorkforceModel();
        $payrollStartDay = $section === 'settings' ? $workforce->payrollStartDay() : null;
        $employees = $workforce->attendanceReport((int) date('n'), (int) date('Y'));
        $employeeMappings = $section === 'employees'
            ? $workforce->listEmployeeAccountMappings()
            : [];
        $mappingEmployees = $section === 'employees'
            ? $workforce->listEmployees()
            : [];
        $scheduleDate = date('Y-m-d');
        $scheduleAssignments = $section === 'schedule'
            ? $workforce->listAssignments($scheduleDate)
            : [];
        $scheduleEmployees = $section === 'schedule'
            ? $workforce->listEmployees()
            : [];
        $scheduleShiftTypes = $section === 'schedule'
            ? $workforce->listShiftTypes()
            : [];
        $roleLabel = self::ROLE_LABELS[$currentUser['role']];
        $csrfToken = $this->csrfToken();
        require_once PATH_VIEW . 'admin_section.php';
    }

    public function login()
    {
        $currentUser = $this->authenticatedUser();
        if ($currentUser !== null) {
            $this->redirectForRole($currentUser['role']);
        }

        $errorMessage = '';
        $loginUsername = '';
        $csrfToken = $this->csrfToken();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validCsrfToken()) {
                http_response_code(400);
                $errorMessage = 'Phiên đăng nhập không hợp lệ. Hãy tải lại trang và thử lại.';
            } else {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                if (!is_string($username) || !is_string($password)) {
                    $errorMessage = 'Thông tin đăng nhập không hợp lệ.';
                } else {
                    $loginUsername = trim($username);

                    try {
                        $user = (new UserModel())->findByUsername($loginUsername);
                    } catch (PDOException | RuntimeException $exception) {
                        error_log('LumiCine login database error: ' . $exception->getMessage());
                        $user = null;
                        $errorMessage = 'Không thể truy cập dữ liệu đăng nhập. Kiểm tra cấu hình MySQL và chạy database/migrations/2026-11-21-add-workforce-storage.sql.';
                    }

                    if ($errorMessage === '') {
                        if (
                            $user !== null
                            && isset($user['id'], $user['username'], $user['display_name'], $user['role'], $user['password_hash'], $user['is_active'])
                            && is_string($user['username'])
                            && is_string($user['display_name'])
                            && is_string($user['role'])
                            && is_string($user['password_hash'])
                            && (int) $user['is_active'] === 1
                            && array_key_exists($user['role'], self::ROLE_LABELS)
                            && password_verify($password, $user['password_hash'])
                        ) {
                            session_regenerate_id(true);
                            $_SESSION['user'] = [
                                'id' => (int) $user['id'],
                                'username' => $user['username'],
                                'display_name' => $user['display_name'],
                                'employee_code' => $user['employee_code'] === null
                                    ? null
                                    : (string) $user['employee_code'],
                                'role' => $user['role'],
                            ];
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            $this->redirectForRole($user['role']);
                        }

                        $errorMessage = 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.';
                    }
                }
            }

            $csrfToken = $this->csrfToken();
        }

        require_once PATH_VIEW . 'login.php';
    }

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Phương thức không được hỗ trợ.');
        }

        if (!$this->validCsrfToken()) {
            http_response_code(400);
            exit('Yêu cầu đăng xuất không hợp lệ.');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();

        header('Location: index.php?action=login&logged_out=1');
        exit;
    }

    private function requireUser(): array
    {
        $user = $this->authenticatedUser();
        if ($user === null) {
            header('Location: index.php?action=login');
            exit;
        }

        header('Cache-Control: no-store, private');

        return $user;
    }

    private function authenticatedUser(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        if (
            !is_array($user)
            || !isset($user['id'])
            || !is_string($user['username'] ?? null)
            || !is_string($user['display_name'] ?? null)
            || !is_string($user['role'] ?? null)
            || !array_key_exists($user['role'], self::ROLE_LABELS)
        ) {
            return null;
        }

        $nameParts = preg_split('/\s+/u', trim($user['display_name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (array_slice($nameParts, 0, 2) as $part) {
            if (preg_match('/^./u', $part, $match) === 1) {
                $initials .= $match[0];
            }
        }

        $user['initials'] = $initials !== '' ? $initials : 'LC';

        return $user;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    private function validCsrfToken(): bool
    {
        $provided = $_POST['csrf_token'] ?? '';
        if (!is_string($provided)) {
            return false;
        }
        $expected = (string) ($_SESSION['csrf_token'] ?? '');

        return $expected !== '' && hash_equals($expected, $provided);
    }

    private function redirectForRole(string $role): void
    {
        header('Location: ' . (in_array($role, self::ADMIN_ROLES, true) ? 'admin.php' : 'index.php'));
        exit;
    }

    private function showEmployeeMappingRequired(): void
    {
        http_response_code(409);
        $csrfToken = $this->csrfToken();
        require_once PATH_VIEW . 'employee_unlinked.php';
    }
}