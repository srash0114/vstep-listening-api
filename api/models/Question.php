<?php
class Question {
    private $db;
    private $table = 'questions';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get question by ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $question = $stmt->get_result()->fetch_assoc();
        
        if ($question) {
            $option_model = new Option();
            $question['options'] = $option_model->getByQuestionId($question['id']);
        }

        return $question;
    }

    // Get all questions for a part (Part 1)
    public function getByPartId($part_id) {
        $query = "SELECT * FROM {$this->table} WHERE part_id = ? AND passage_id IS NULL ORDER BY order_index ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $part_id);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($questions as &$question) {
            $option_model = new Option();
            $question['options'] = $option_model->getByQuestionId($question['id']);
        }

        return $questions;
    }

    // Get all questions for a passage (Part 2 & 3)
    public function getByPassageId($passage_id) {
        $query = "SELECT * FROM {$this->table} WHERE passage_id = ? ORDER BY order_index ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $passage_id);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($questions as &$question) {
            $option_model = new Option();
            $question['options'] = $option_model->getByQuestionId($question['id']);
        }

        return $questions;
    }

    // Create question
    public function create($data) {
        $part_id = $data['part_id'] ?? null;
        $passage_id = $data['passage_id'] ?? null;
        $order_index = $data['order_index'] ?? null;

        // Auto-assign question_number if not provided
        if (!empty($data['question_number'])) {
            $question_number = intval($data['question_number']);
        } else {
            // Get max question_number for this part's exam and increment
            $row = $this->db->query(
                "SELECT COALESCE(MAX(question_number), 0) + 1 AS next_num FROM {$this->table} WHERE part_id = " . intval($part_id)
            )->fetch_assoc();
            $question_number = $row['next_num'];
        }

        // Auto-assign order_index if not provided
        if ($order_index === null) {
            $scope = $passage_id ? "passage_id = " . intval($passage_id) : "part_id = " . intval($part_id) . " AND passage_id IS NULL";
            $row = $this->db->query(
                "SELECT COALESCE(MAX(order_index), 0) + 1 AS next_idx FROM {$this->table} WHERE $scope"
            )->fetch_assoc();
            $order_index = $row['next_idx'];
        }
        $content = $data['content'] ?? null;
        $difficulty_level = $data['difficulty_level'] ?? null;
        $script = $data['script'] ?? null;

        $query = "INSERT INTO {$this->table} (part_id, passage_id, question_number, order_index, content, difficulty_level, script) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('iiiisss', $part_id, $passage_id, $question_number, $order_index, $content, $difficulty_level, $script);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    // Batch create questions
    public function batchCreate($questions) {
        $results = [];
        foreach ($questions as $question) {
            $id = $this->create($question);
            $results[] = [
                'question_number' => $question['question_number'],
                'id' => $id,
                'success' => $id !== false
            ];
        }
        return $results;
    }

    // Update question
    public function update($id, $data) {
        $updates = [];
        $params = [];
        $types = '';

        if (isset($data['content'])) {
            $updates[] = "content = ?";
            $params[] = $data['content'];
            $types .= 's';
        }
        if (isset($data['difficulty_level'])) {
            $updates[] = "difficulty_level = ?";
            $params[] = $data['difficulty_level'];
            $types .= 's';
        }
        if (isset($data['script'])) {
            $updates[] = "script = ?";
            $params[] = $data['script'];
            $types .= 's';
        }

        if (empty($updates)) return false;

        $query = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    // Delete question
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // Get questions by exam (all parts)
    public function getByExamId($exam_id) {
        $query = "SELECT q.* FROM {$this->table} q
                  JOIN parts p ON q.part_id = p.id
                  WHERE p.exam_id = ?
                  ORDER BY q.question_number ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $exam_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

