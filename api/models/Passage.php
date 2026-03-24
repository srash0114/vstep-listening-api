<?php
class Passage {
    private $db;
    private $table = 'passages';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get passage by ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Get all passages for a part
    public function getByPartId($part_id) {
        $query = "SELECT * FROM {$this->table} WHERE part_id = ? ORDER BY passage_order ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $part_id);
        $stmt->execute();
        $passages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Add questions to each passage
        foreach ($passages as &$passage) {
            $question_model = new Question();
            $passage['questions'] = $question_model->getByPassageId($passage['id']);
        }

        return $passages;
    }

    // Create passage
    public function create($data) {
        $part_id = $data['part_id'] ?? null;
        $title = $data['title'] ?? null;
        $script = $data['script'] ?? null;
        $audio_url = $data['audio_url'] ?? null;
        $passage_order = $data['passage_order'] ?? null;

        $query = "INSERT INTO {$this->table} (part_id, title, script, audio_url, passage_order) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('isssi', $part_id, $title, $script, $audio_url, $passage_order);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }

    // Update passage
    public function update($id, $data) {
        $updates = [];
        $params = [];
        $types = '';

        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = $data['title'];
            $types .= 's';
        }
        if (isset($data['script'])) {
            $updates[] = "script = ?";
            $params[] = $data['script'];
            $types .= 's';
        }
        if (isset($data['audio_url'])) {
            $updates[] = "audio_url = ?";
            $params[] = $data['audio_url'];
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

    // Delete passage
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // Get passages without scripts (for taking exam)
    public function getByPartIdForTaking($part_id) {
        $passages = $this->getByPartId($part_id);
        foreach ($passages as &$passage) {
            unset($passage['script']);
            foreach ($passage['questions'] as &$question) {
                unset($question['script']);
                unset($question['difficulty_level']);
                foreach ($question['options'] as &$option) {
                    unset($option['is_correct']);
                }
            }
        }
        return $passages;
    }
}
