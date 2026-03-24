<?php
class UserAnswer {
    private $db;
    private $table = 'user_answers';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all answers for a user exam
    public function getByUserExamId($user_exam_id) {
        $query = "SELECT * FROM {$this->table} WHERE user_exam_id = ? ORDER BY id ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $user_exam_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get a specific answer
    public function getByUserExamAndQuestion($user_exam_id, $question_id) {
        $query = "SELECT * FROM {$this->table} WHERE user_exam_id = ? AND question_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ii', $user_exam_id, $question_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Save an answer
    public function save($user_exam_id, $question_id, $selected_option_id = null) {
        // Check if answer already exists
        $existing = $this->getByUserExamAndQuestion($user_exam_id, $question_id);
        
        if ($existing) {
            // Update existing answer
            $query = "UPDATE {$this->table} SET selected_option_id = ? WHERE user_exam_id = ? AND question_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('iii', $selected_option_id, $user_exam_id, $question_id);
            return $stmt->execute();
        } else {
            // Create new answer
            $query = "INSERT INTO {$this->table} (user_exam_id, question_id, selected_option_id) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('iii', $user_exam_id, $question_id, $selected_option_id);
            
            if ($stmt->execute()) {
                return $this->db->insert_id;
            }
            return false;
        }
    }

    // Batch save answers
    public function batchSave($user_exam_id, $answers) {
        $results = [];
        foreach ($answers as $answer) {
            $question_id = $answer['question_id'];
            $selected_option_id = $answer['selected_option_id'] ?? null;
            
            $result = $this->save($user_exam_id, $question_id, $selected_option_id);
            $results[] = [
                'question_id' => $question_id,
                'success' => $result !== false
            ];
        }
        return $results;
    }

    // Delete answer
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // Clear all answers for user exam
    public function clearByUserExamId($user_exam_id) {
        $query = "DELETE FROM {$this->table} WHERE user_exam_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $user_exam_id);
        return $stmt->execute();
    }
}
