<?php
require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel
{
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash, role, department_id, class_id, token) VALUES (:name, :email, :password_hash, :role, :department_id, :class_id, :token)');
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'] ?? 'student',
            'department_id' => $data['department_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
            'token' => $data['token'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
