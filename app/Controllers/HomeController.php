<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Exam.php';
require_once __DIR__ . '/../Models/User.php';

class HomeController extends BaseController
{
    public function landing(): void
    {
        $this->render('home.php', ['page' => 'home']);
    }

    public function studentDashboard(): void
    {
        $examModel = new Exam($this->config);
        $exams = $examModel->allForClass(null);
        $this->render('student_dashboard.php', [
            'page' => 'student_dashboard',
            'exams' => $exams,
        ]);
    }

    public function adminDashboard(): void
    {
        $this->render('admin_dashboard.php', ['page' => 'admin_dashboard']);
    }
}
