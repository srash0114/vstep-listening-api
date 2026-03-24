<?php
class Option {
    private $db;
    private $table = 'options';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get option by ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Get all options for a question
    public function getByQuestionId($question_id) {
        $query = "SELECT * FROM {$this->table} WHERE question_id = ? ORDER BY option_label ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $question_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get correct option for a question
    public function getCorrectOption($question_id) {
        $query = "SELECT * FROM {$this->table} WHERE question_id = ? AND is_correct = TRUE LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $question_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Create option
    public function create($data) {
        $question_id = $data['question_id'] ?? null;
        $content = $data['content'] ?? null;
        $option_label = $data['option_label'] ?? null;
        $is_correct = $data['is_correct'] ?? false;

        $query = "INSERT INTO {$this->table} (question_id, content, option_label, is_correct) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('issi', $question_id, $content, $option_label, $is_correct);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    // Batch create options
    public function batchCreate($options) {
        $results = [];
        foreach ($options as $option) {
            $id = $this->create($option);
            $results[] = [
                'question_id' => $option['question_id'],
                'option_label' => $option['option_label'],
                'id' => $id,
                'success' => $id !== false
            ];
        }
        return $results;
    }

    // Update option
    public function update($id, $data) {
        $updates = [];
        $params = [];
        $types = '';

        if (isset($data['content'])) {
            $updates[] = "content = ?";
            $params[] = $data['content'];
            $types .= 's';
        }
        if (isset($data['is_correct'])) {
            $updates[] = "is_correct = ?";
            $params[] = $data['is_correct'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($updates)) return false;

        $query = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    // Delete option
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // Delete all options for question
    public function deleteByQuestionId($question_id) {
        $query = "DELETE FROM {$this->table} WHERE question_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $question_id);
        return $stmt->execute();
    }
}
