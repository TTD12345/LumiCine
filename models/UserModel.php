<?php

class UserModel extends BaseModel
{
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
            . ', password_hash, role, is_active
               FROM lumicine_users
               WHERE username = :username
               LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }
}
