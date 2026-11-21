<?php

class UserModel extends BaseModel
{
    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name, employee_code, password_hash, role, is_active
             FROM lumicine_users
             WHERE username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }
}
