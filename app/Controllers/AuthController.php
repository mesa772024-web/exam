<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/User.php';

class AuthController extends BaseController
{
    public function login(): void
    {
        $this->render('login.php', ['page' => 'login']);
    }

    public function register(): void
    {
        $this->render('register.php', ['page' => 'register']);
    }
}
