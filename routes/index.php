<?php

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$requestedAction = $_GET['action'] ?? '/';
$action = is_string($requestedAction) ? $requestedAction : '';

match ($action) {
    '/' => (new HomeController())->index(),
    'admin' => (new HomeController())->admin(),
    'attendance' => (new HomeController())->attendance(),
    'login' => (new HomeController())->login(),
    'logout' => (new HomeController())->logout(),
    default => http_response_code(404),
};
