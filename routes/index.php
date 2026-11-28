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
    'schedule' => (new HomeController())->index(),
    'dashboard' => (new HomeController())->employeeSection('dashboard'),
    'timesheet' => (new HomeController())->employeeSection('timesheet'),
    'shift' => (new HomeController())->employeeSection('shift'),
    'overtime' => (new HomeController())->employeeSection('overtime'),
    'leave' => (new HomeController())->employeeSection('leave'),
    'requests' => (new HomeController())->employeeSection('requests'),
    'notifications' => (new HomeController())->employeeSection('notifications'),
    'profile' => (new HomeController())->employeeSection('profile'),
    'change-password' => (new HomeController())->forcePasswordChange(),
    'employees' => (new HomeController())->employeeSection('employees'),
    'reports' => (new HomeController())->employeeSection('reports'),
    'admin-summary' => (new HomeController())->admin(),
    'admin-overview' => (new HomeController())->adminSection('overview'),
    'admin-employees' => (new HomeController())->adminSection('employees'),
    'admin-schedule' => (new HomeController())->adminSection('schedule'),
    'admin-attendance' => (new HomeController())->adminSection('attendance'),
    'admin-overtime' => (new HomeController())->adminSection('overtime'),
    'admin-reports' => (new HomeController())->adminSection('reports'),
    'admin-settings' => (new HomeController())->adminSection('settings'),
    'admin-accounts' => (new HomeController())->adminSection('accounts'),
    'login' => (new HomeController())->login(),
    'logout' => (new HomeController())->logout(),
    'api' => (new ApiController())->handle(),
    default => http_response_code(404),
};
