<?php

class NotificationModel
{
    public static function sendToEmployee(
        PDO $pdo,
        int $employeeId,
        string $title,
        string $message,
        string $type
    ): void {
        $statement = $pdo->prepare(
            'INSERT INTO lumicine_notifications (user_id, title, message, type)
             SELECT u.id, :title, :message, :type
             FROM nhan_vien e
             INNER JOIN lumicine_users u
                 ON u.role = \'employee\'
                AND u.is_active = 1
                AND (
                    u.employee_id = e.id
                    OR (
                        u.employee_id IS NULL
                        AND BINARY u.employee_code = BINARY e.ma_nhan_vien
                    )
                )
             WHERE e.id = :employee_id'
        );
        $statement->execute([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'employee_id' => $employeeId,
        ]);
    }
}
