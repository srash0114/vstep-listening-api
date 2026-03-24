<?php
class Part {
    private $db;
    private $table = 'parts';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get part by ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Get all parts for an exam
    public function getByExamId($exam_id) {
        $query = "SELECT * FROM {$this->table} WHERE exam_id = ? ORDER BY part_number ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $exam_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get part with all questions and options
    public function getWithContent($id) {
        $part = $this->getById($id);
        if (!$part) return null;

        if ($part['part_number'] > 1) {
            // Part 2 & 3: Has passages
            $passage_model = new Passage();
            $part['passages'] = $passage_model->getByPartId($part['id']);
        } else {
            // Part 1: No passages
            $question_model = new Question();
            $part['questions'] = $question_model->getByPartId($part['id']);
        }

        return $part;
    }

    // Create part
    public function create($data) {
        $exam_id = $data['exam_id'] ?? null;
        $part_number = $data['part_number'] ?? null;
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $audio_url = $data['audio_url'] ?? null;
        $duration = $data['duration'] ?? null;
        $question_count = $data['question_count'] ?? null;

        $query = "INSERT INTO {$this->table} (exam_id, part_number, title, description, audio_url, duration, question_count) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('iisssii', $exam_id, $part_number, $title, $description, $audio_url, $duration, $question_count);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    // Update part
    public function update($id, $data) {
        $updates = [];
        $params = [];
        $types = '';

        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = $data['title'];
            $types .= 's';
        }
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
            $types .= 's';
        }
        if (isset($data['audio_url'])) {
            $updates[] = "audio_url = ?";
            $params[] = $data['audio_url'];
            $types .= 's';
        }
        if (isset($data['audio_path'])) {
            $updates[] = "audio_path = ?";
            $params[] = $data['audio_path'];
            $types .= 's';
        }
        if (isset($data['duration'])) {
            $updates[] = "duration = ?";
            $params[] = $data['duration'];
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

    // Delete part
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}
