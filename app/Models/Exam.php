<?php
require_once __DIR__ . '/BaseModel.php';

class Exam extends BaseModel
{
    public function allForClass(?int $classId = null): array
    {
        $sql = 'SELECT * FROM exams';
        $params = [];
        if ($classId) {
            $sql .= ' WHERE class_id = :class_id';
            $params['class_id'] = $classId;
        }
        $sql .= ' ORDER BY scheduled_date DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO exams (name, course, lecturer, department_id, class_id, number_of_questions, time_minutes, description, scheduled_date, token, randomize_questions) VALUES (:name, :course, :lecturer, :department_id, :class_id, :number_of_questions, :time_minutes, :description, :scheduled_date, :token, :randomize_questions)');
        $stmt->execute([
            'name' => $data['name'],
            'course' => $data['course'],
            'lecturer' => $data['lecturer'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
            'number_of_questions' => $data['number_of_questions'] ?? 0,
            'time_minutes' => $data['time_minutes'] ?? 0,
            'description' => $data['description'] ?? '',
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'token' => $data['token'] ?? null,
            'randomize_questions' => (int) ($data['randomize_questions'] ?? 0),
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
