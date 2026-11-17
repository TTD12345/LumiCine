<?php

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
define('APP_BOOTSTRAPPED', true);

spl_autoload_register(function ($class) {
    $fileName = "$class.php";

    $fileModel = __DIR__ . '/models/' . $fileName;
    $fileController = __DIR__ . '/controllers/' . $fileName;

    if (is_readable($fileModel)) {
        require_once $fileModel;
    } elseif (is_readable($fileController)) {
        require_once $fileController;
    }
});

require_once __DIR__ . '/configs/env.php';
require_once __DIR__ . '/configs/helper.php';

// Điều hướng
require_once __DIR__ . '/routes/index.php';
