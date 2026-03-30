<?php
class Exam {
    private $db;
    private $table = 'exams';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all exams
    public function getAll($limit = 50, $offset = 0) {
        $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get single exam
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Get exam with all parts, passages, questions, and options
    public function getWithContent($id, $with_answers = false) {
        $exam = $this->getById($id);
        if (!$exam) return null;

        $query = "SELECT * FROM parts WHERE exam_id = ? ORDER BY part_number ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $parts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $exam['parts'] = [];
        foreach ($parts as $part) {
            $part = $this->enrichPart($part, $with_answers);
            $exam['parts'][] = $part;
        }

        return $exam;
    }

    // Get exam content WITHOUT correct answers (for taking exam)
    public function getForTaking($id) {
        return $this->getWithContent($id, false);
    }

    private function enrichPart($part, $with_answers = false) {
        if ($part['part_number'] > 1) {
            $query = "SELECT * FROM passages WHERE part_id = ? ORDER BY passage_order ASC";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('i', $part['id']);
            $stmt->execute();
            $part['passages'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($part['passages'] as &$passage) {
                $passage['questions'] = $this->getPassageQuestions($passage['id'], $with_answers);
            }
        } else {
            $part['passages'] = null;
            $part['questions'] = $this->getPartQuestions($part['id'], $with_answers);
        }

        return $part;
    }

    private function getPartQuestions($part_id, $with_answers = false) {
        $query = "SELECT * FROM questions WHERE part_id = ? AND passage_id IS NULL ORDER BY order_index ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $part_id);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($questions as &$question) {
            $question['options'] = $this->getQuestionOptions($question['id'], $with_answers);
        }

        return $questions;
    }

    private function getPassageQuestions($passage_id, $with_answers = false) {
        $query = "SELECT * FROM questions WHERE passage_id = ? ORDER BY order_index ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $passage_id);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($questions as &$question) {
            $question['options'] = $this->getQuestionOptions($question['id'], $with_answers);
        }

        return $questions;
    }

    private function getQuestionOptions($question_id, $with_answers = false) {
        $cols = $with_answers
            ? "id, question_id, content, option_label, is_correct"
            : "id, question_id, content, option_label";
        $query = "SELECT $cols FROM options WHERE question_id = ? ORDER BY option_label ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $question_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Create exam
    public function create($data) {
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $level = $data['level'] ?? null;
        $total_duration = $data['total_duration'] ?? 60;

        $query = "INSERT INTO {$this->table} (title, description, level, total_duration) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('sssi', $title, $description, $level, $total_duration);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    // Update exam
    public function update($id, $data) {
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $level = $data['level'] ?? null;
        $total_duration = $data['total_duration'] ?? null;

        $query = "UPDATE {$this->table} SET";
        $params = [];
        $types = '';

        if ($title !== null) {
            $query .= " title = ?,";
            $params[] = $title;
            $types .= 's';
        }
        if ($description !== null) {
            $query .= " description = ?,";
            $params[] = $description;
            $types .= 's';
        }
        if ($level !== null) {
            $query .= " level = ?,";
            $params[] = $level;
            $types .= 's';
        }
        if ($total_duration !== null) {
            $query .= " total_duration = ?,";
            $params[] = $total_duration;
            $types .= 'i';
        }

        $query = rtrim($query, ',');
        $query .= " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    // Delete exam
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // Count total exams
    public function count() {
        $query = "SELECT COUNT(*) as count FROM {$this->table}";
        $result = $this->db->query($query);
        return $result->fetch_assoc()['count'];
    }
}
