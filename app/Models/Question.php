<?php
require_once __DIR__ . '/BaseModel.php';

class Question extends BaseModel
{
    public function importFromCsv(string $path, int $categoryId, ?int $subcategoryId = null): int
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open CSV file: ' . $path);
        }

        $count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 8) {
                continue;
            }
            [$questionText, $a, $b, $c, $d, $e, $correct, $explanation] = $data;
            $this->create([
                'question_text' => $questionText,
                'option_a' => $a,
                'option_b' => $b,
                'option_c' => $c,
                'option_d' => $d,
                'option_e' => $e,
                'correct_option' => $correct,
                'explanation' => $explanation,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
            ]);
            $count++;
        }
        fclose($handle);
        return $count;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, option_e, correct_option, explanation, image_path, lab_values, category_id, subcategory_id) VALUES (:question_text, :option_a, :option_b, :option_c, :option_d, :option_e, :correct_option, :explanation, :image_path, :lab_values, :category_id, :subcategory_id)');
        $stmt->execute([
            'question_text' => $data['question_text'],
            'option_a' => $data['option_a'] ?? '',
            'option_b' => $data['option_b'] ?? '',
            'option_c' => $data['option_c'] ?? '',
            'option_d' => $data['option_d'] ?? '',
            'option_e' => $data['option_e'] ?? '',
            'correct_option' => $data['correct_option'] ?? 'A',
            'explanation' => $data['explanation'] ?? '',
            'image_path' => $data['image_path'] ?? null,
            'lab_values' => $data['lab_values'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'subcategory_id' => $data['subcategory_id'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
