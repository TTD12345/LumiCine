<?php

class UserModel extends BaseModel
{
    public function listEmployeeAccounts(): array
    {
        return $this->pdo->query(
            'SELECT u.id, u.username, u.display_name, u.employee_code,
                    COALESCE(direct.id, legacy.id) AS employee_id,
                    COALESCE(direct.ma_nhan_vien, legacy.ma_nhan_vien, u.employee_code) AS code,
                    COALESCE(direct.ho_ten, legacy.ho_ten, u.display_name) AS employee_name
             FROM lumicine_users u
             LEFT JOIN nhan_vien direct ON direct.id = u.employee_id
             LEFT JOIN nhan_vien legacy
                    ON direct.id IS NULL
                   AND BINARY legacy.ma_nhan_vien = BINARY u.employee_code
             WHERE u.role = \'employee\'
             ORDER BY employee_name, u.username'
        )->fetchAll();
    }

    public function listEmployeesWithoutAccounts(): array
    {
        return $this->pdo->query(
            'SELECT e.id, e.ma_nhan_vien AS code, e.ho_ten AS name
             FROM nhan_vien e
             WHERE e.trang_thai = 1
               AND (e.ngay_nghi_viec IS NULL OR e.ngay_nghi_viec > CURDATE())
               AND NOT EXISTS (
                   SELECT 1
                   FROM lumicine_users u
                   WHERE u.employee_id = e.id
                      OR BINARY u.employee_code = BINARY e.ma_nhan_vien
               )
             ORDER BY e.ho_ten'
        )->fetchAll();
    }

    public function saveEmployeeAccount(array $data, int $currentUserId): int
    {
        $id = isset($data['id']) ? filter_var($data['id'], FILTER_VALIDATE_INT) : null;
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? '';

        if (
            ($id !== null && (!$id || $id < 1))
            || !is_string($username)
            || !is_string($password)
        ) {
            throw new InvalidArgumentException('Thông tin tài khoản không hợp lệ.');
        }

        $username = trim($username);
        if ($username === '' || lumicineUtf8Length($username) > 190) {
            throw new InvalidArgumentException('Tên đăng nhập phải có từ 1 đến 190 ký tự.');
        }
        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
            throw new InvalidArgumentException('Mật khẩu phải có từ 8 đến 72 byte.');
        }

        try {
            if ($id !== null) {
                if ($id === $currentUserId) {
                    throw new InvalidArgumentException('Không thể tự chỉnh sửa tài khoản đang đăng nhập.');
                }
                $account = $this->pdo->prepare(
                    'SELECT id FROM lumicine_users WHERE id = :id AND role = \'employee\' LIMIT 1'
                );
                $account->execute(['id' => $id]);
                if ($account->fetchColumn() === false) {
                    throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên.');
                }

                if ($password === '') {
                    $statement = $this->pdo->prepare(
                        'UPDATE lumicine_users SET username = :username WHERE id = :id'
                    );
                    $statement->execute(['username' => $username, 'id' => $id]);
                } else {
                    $statement = $this->pdo->prepare(
                        'UPDATE lumicine_users
                         SET username = :username,
                             password_hash = :password_hash,
                             must_change_password = 1
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'username' => $username,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $id,
                    ]);
                }

                return (int) $id;
            }

            $employeeId = filter_var($data['employeeId'] ?? null, FILTER_VALIDATE_INT);
            if (!$employeeId || $employeeId < 1 || $password === '') {
                throw new InvalidArgumentException('Chọn nhân viên và nhập mật khẩu có ít nhất 8 ký tự.');
            }

            $employeeStatement = $this->pdo->prepare(
                'SELECT e.id, e.ma_nhan_vien, e.ho_ten
                 FROM nhan_vien e
                 WHERE e.id = :id
                   AND e.trang_thai = 1
                   AND (e.ngay_nghi_viec IS NULL OR e.ngay_nghi_viec > CURDATE())
                 LIMIT 1'
            );
            $employeeStatement->execute(['id' => $employeeId]);
            $employee = $employeeStatement->fetch();
            if ($employee === false) {
                throw new InvalidArgumentException('Không tìm thấy nhân viên đang làm việc.');
            }

            $accountCheck = $this->pdo->prepare(
                'SELECT id FROM lumicine_users
                 WHERE employee_id = :employee_id
                    OR BINARY employee_code = BINARY :employee_code
                 LIMIT 1'
            );
            $accountCheck->execute([
                'employee_id' => $employeeId,
                'employee_code' => $employee['ma_nhan_vien'],
            ]);
            if ($accountCheck->fetchColumn() !== false) {
                throw new InvalidArgumentException('Nhân viên này đã có tài khoản đăng nhập.');
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO lumicine_users
                    (username, display_name, employee_code, employee_id, password_hash, role, must_change_password)
                 VALUES
                    (:username, :display_name, :employee_code, :employee_id, :password_hash, \'employee\', 1)'
            );
            $statement->execute([
                'username' => $username,
                'display_name' => $employee['ho_ten'],
                'employee_code' => $employee['ma_nhan_vien'],
                'employee_id' => $employeeId,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new InvalidArgumentException('Tên đăng nhập đã tồn tại hoặc nhân viên đã được cấp tài khoản.');
            }
            throw $exception;
        }
    }

    public function deleteEmployeeAccount(int $id, int $currentUserId): void
    {
        if ($id < 1 || $id === $currentUserId) {
            throw new InvalidArgumentException('Tài khoản cần xóa không hợp lệ.');
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM lumicine_users WHERE id = :id AND role = \'employee\''
        );
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên.');
        }
    }

    public function changeEmployeePassword(
        int $userId,
        ?string $currentPassword,
        string $newPassword,
        string $confirmation
    ): void {
        if ($userId < 1) {
            throw new InvalidArgumentException('Tài khoản không hợp lệ.');
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            throw new InvalidArgumentException('Mật khẩu mới phải có từ 8 đến 72 byte.');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('Mật khẩu xác nhận không khớp.');
        }

        $account = $this->pdo->prepare(
            'SELECT password_hash, must_change_password
             FROM lumicine_users
             WHERE id = :id AND role = \'employee\' AND is_active = 1
             LIMIT 1'
        );
        $account->execute(['id' => $userId]);
        $accountRow = $account->fetch();
        if ($accountRow === false) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên.');
        }
        $passwordHash = $accountRow['password_hash'] ?? null;
        $mustChangePassword = (int) ($accountRow['must_change_password'] ?? 0) === 1;
        if (
            !is_string($passwordHash)
            || (!$mustChangePassword
                && (!is_string($currentPassword) || !password_verify($currentPassword, $passwordHash)))
        ) {
            throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
        }
        if (password_verify($newPassword, $passwordHash)) {
            throw new InvalidArgumentException('Mật khẩu mới phải khác mật khẩu hiện tại.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE lumicine_users
             SET password_hash = :password_hash, must_change_password = 0
             WHERE id = :id
               AND role = \'employee\'
               AND is_active = 1
               AND must_change_password = :must_change_password
               AND password_hash = :current_hash'
        );
        $statement->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
            'must_change_password' => $mustChangePassword ? 1 : 0,
            'current_hash' => $passwordHash,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Tài khoản đã thay đổi. Hãy đăng nhập lại rồi thử lại.');
        }
    }

    public function dismissEmployeePasswordPrompt(int $userId, bool $dontShowAgain): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Tài khoản không hợp lệ.');
        }

        $account = $this->pdo->prepare(
            'SELECT must_change_password
             FROM lumicine_users
             WHERE id = :id AND role = \'employee\' AND is_active = 1
             LIMIT 1'
        );
        $account->execute(['id' => $userId]);
        $mustChangePassword = $account->fetchColumn();
        if ($mustChangePassword === false) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên đang hoạt động.');
        }

        if ($dontShowAgain && (int) $mustChangePassword === 1) {
            $statement = $this->pdo->prepare(
                'UPDATE lumicine_users
                 SET must_change_password = 0
                 WHERE id = :id
                   AND role = \'employee\'
                   AND is_active = 1
                   AND must_change_password = 1'
            );
            $statement->execute(['id' => $userId]);
            if ($statement->rowCount() !== 1) {
                throw new InvalidArgumentException('Trạng thái tài khoản đã thay đổi. Hãy tải lại trang rồi thử lại.');
            }
        }
    }

    public function findByUsername(string $username): ?array
    {
        $column = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'lumicine_users\'
               AND COLUMN_NAME = \'employee_id\''
        );
        $column->execute();
        $employeeIdColumn = (int) $column->fetchColumn() > 0
            ? 'employee_id'
            : 'NULL AS employee_id';
        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name, employee_code, ' . $employeeIdColumn
            . ', password_hash, role, is_active, must_change_password
               FROM lumicine_users
               WHERE username = :username
               LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }
}
