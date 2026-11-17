<?php

class BaseModel
{
    protected $table;
    protected $pdo;

    // Kết nối CSDL
    public function __construct()
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

        try {
            $this->pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, DB_OPTIONS);
        } catch (PDOException $e) {
            throw new RuntimeException('Không thể kết nối cơ sở dữ liệu.', 0, $e);
        }
    }

    // Hủy kết nối CSDL
    public function __destruct()
    {
        $this->pdo = null;
    }
}
