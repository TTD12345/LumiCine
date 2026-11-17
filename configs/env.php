<?php

define('BASE_URL',          'http://localhost/BaseExam/');

define('PATH_ROOT',         __DIR__ . '/../');

define('PATH_VIEW',         PATH_ROOT . 'views/');

define('PATH_VIEW_MAIN',    PATH_ROOT . 'views/main.php');

define('BASE_ASSETS_UPLOADS',   BASE_URL . 'assets/uploads/');

define('PATH_ASSETS_UPLOADS',   PATH_ROOT . 'assets/uploads/');

define('PATH_CONTROLLER',       PATH_ROOT . 'controllers/');

define('PATH_MODEL',            PATH_ROOT . 'models/');

define('DB_HOST', getenv('LUMICINE_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('LUMICINE_DB_PORT') ?: '3306');
define('DB_USERNAME', getenv('LUMICINE_DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('LUMICINE_DB_PASSWORD') ?: '');
define('DB_NAME', getenv('LUMICINE_DB_NAME') ?: 'data_employments');
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
