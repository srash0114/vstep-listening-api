<?php
class UserExam {
    private $db;
    private $table = 'user_exams';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get user exam by ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Get user exam with answers
    public function getWithAnswers($id) {
        $user_exam = $this->getById($id);
        if (!$user_exam) return null;

        $user_answer_model = new UserAnswer();
        $user_exam['answers'] = $user_answer_model->getByUserExamId($id);

        return $user_exam;
    }

    // Get user's exam history
    public function getUserHistory($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY submitted_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('iii', $user_id, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Start exam (create user_exam record)
    public function startExam($user_id, $exam_id) {
        $query = "INSERT INTO {$this->table} (user_id, exam_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ii', $user_id, $exam_id);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    // Submit exam
    public function submitExam($id, $data) {
        $total_questions = $data['total_questions'] ?? null;
        $correct_answers = $data['correct_answers'] ?? null;
        $score = $data['score'] ?? null;
        $percentage = $data['percentage'] ?? null;
        $time_spent = $data['time_spent'] ?? null;
        $performance_level = $data['performance_level'] ?? null;

        $query = "UPDATE {$this->table} 
                  SET submitted_at = NOW(), 
                      total_questions = ?, 
                      correct_answers = ?, 
                      score = ?, 
                      percentage = ?, 
                      time_spent = ?,
                      performance_level = ?
                  WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('iiddssi', $total_questions, $correct_answers, $score, $percentage, $time_spent, $performance_level, $id);
        return $stmt->execute();
    }

    // Get exam result with detailed answers
    public function getResult($id) {
        $user_exam = $this->getWithAnswers($id);
        if (!$user_exam) return null;

        // Add question details
        foreach ($user_exam['answers'] as &$answer) {
            $question_model = new Question();
            $answer['question'] = $question_model->getById($answer['question_id']);
        }

        return $user_exam;
    }

    // Check if user already submitted this exam
    public function checkSubmitted($user_id, $exam_id) {
        $query = "SELECT id FROM {$this->table} WHERE user_id = ? AND exam_id = ? AND submitted_at IS NOT NULL LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ii', $user_id, $exam_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? true : false;
    }

    // Delete user exam
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}
