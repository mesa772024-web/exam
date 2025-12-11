<?php
$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../app/Controllers/HomeController.php';
require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Models/Exam.php';

$page = $_GET['page'] ?? 'home';

$homeController = new HomeController($config);
$authController = new AuthController($config);

switch ($page) {
    case 'home':
        $homeController->landing();
        break;
    case 'student_dashboard':
        $homeController->studentDashboard();
        break;
    case 'admin_dashboard':
        $homeController->adminDashboard();
        break;
    case 'exam_list':
        $examModel = new Exam($config);
        $exams = $examModel->allForClass(null);
        $homeController->render('exam_list.php', ['page' => 'exam_list', 'exams' => $exams]);
        break;
    case 'token_exam':
        $homeController->render('token_exam.php', ['page' => 'token_exam']);
        break;
    case 'exam_take':
        $homeController->render('exam_take.php', ['page' => 'exam_take']);
        break;
    case 'results':
        $homeController->render('results.php', ['page' => 'results']);
        break;
    case 'student_settings':
        $homeController->render('student_settings.php', ['page' => 'student_settings']);
        break;
    case 'login':
        $authController->login();
        break;
    case 'register':
        $authController->register();
        break;
    case 'create_exam':
        $homeController->render('create_exam.php', ['page' => 'create_exam']);
        break;
    case 'admin_users':
        $homeController->render('admin_users.php', ['page' => 'admin_users']);
        break;
    case 'settings':
        $homeController->render('settings.php', ['page' => 'settings']);
        break;
    default:
        http_response_code(404);
        $homeController->render('home.php', ['page' => 'home']);
}
