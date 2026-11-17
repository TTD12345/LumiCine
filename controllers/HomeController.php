<?php

class HomeController
{
    public function index() 
    {
        require_once PATH_VIEW . 'main.php';
    }

    public function admin()
    {
        require_once PATH_VIEW . 'admin.php';
    }

    public function attendance()
    {
        require_once PATH_VIEW . 'attendance.php';
    }

}