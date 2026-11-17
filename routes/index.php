<?php

$action = $_GET['action'] ?? '/';

match ($action) {
    '/'         => (new HomeController)->index(),
    'admin'     => (new HomeController)->admin(),
    'attendance'=> (new HomeController)->attendance(),
};