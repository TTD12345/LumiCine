<?php

$action = $_GET['action'] ?? '/';

match ($action) {
    '/'         => (new HomeController)->index(),
    'admin'     => (new HomeController)->admin(),
    'attendance'=> (new HomeController)->attendance(),
    default     => http_response_code(404),
};