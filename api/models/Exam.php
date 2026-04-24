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

    // Get exam with all parts, passages, questions, and options - OPTIMIZED with JOINs + CACHING
    public function getWithContent($id, $with_answers = false) {
        // Cache key: exam data is stable unless updated
        $cache_key = "exam_{$id}_" . ($with_answers ? 'full' : 'taking');
        $cache_file = sys_get_temp_dir() . '/vstep_' . $cache_key . '.cache';
        $cache_ttl = 3600; // 1 hour cache

        // Try to get from cache
        if (file_exists($cache_file)) {
            $age = time() - filemtime($cache_file);
            if ($age < $cache_ttl) {
                $cached = json_decode(file_get_contents($cache_file), true);
                if ($cached) {
                    error_log("Cache HIT for exam $id");
                    return $cached;
                }
            }
        }

        $exam = $this->getById($id);
        if (!$exam) return null;

        // Single JOIN query to get all data at once
        $answer_cols = $with_answers ? ', o.is_correct' : '';
        $query = "
            SELECT 
                p.id as part_id, p.part_number, p.title as part_title, p.description as part_description, p.audio_url as part_audio_url, p.duration as part_duration,
                pa.id as passage_id, pa.passage_order, pa.script as passage_content, pa.title as passage_title, pa.audio_url as passage_audio_url,
                q.id as question_id, q.content as question_content, q.order_index as question_order,
                o.id as option_id, o.option_label, o.content as option_content {$answer_cols}
            FROM parts p
            LEFT JOIN passages pa ON pa.part_id = p.id
            LEFT JOIN questions q ON (q.part_id = p.id AND q.passage_id IS NULL) OR q.passage_id = pa.id
            LEFT JOIN options o ON o.question_id = q.id
            WHERE p.exam_id = ?
            ORDER BY p.part_number ASC, pa.passage_order ASC, q.order_index ASC, o.option_label ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Restructure flat result into nested hierarchy
        $exam['parts'] = [];
        $parts_map = [];
        $passages_map = [];
        $questions_map = [];

        foreach ($rows as $row) {
            // Skip empty rows (can happen with LEFT JOINs)
            if (!$row['part_id']) {
                continue;
            }

            // Create or get part
            if (!isset($parts_map[$row['part_id']])) {
                $parts_map[$row['part_id']] = [
                    'id' => $row['part_id'],
                    'part_number' => $row['part_number'],
                    'title' => $row['part_title'],
                    'description' => $row['part_description'],
                    'audio_url' => $row['part_audio_url'],
                    'duration' => $row['part_duration'],
                    'passages' => $row['part_number'] > 1 ? [] : null,
                    'questions' => $row['part_number'] == 1 ? [] : null,
                ];
                $exam['parts'][] = &$parts_map[$row['part_id']];
            }

            // Handle passages (only for part > 1)
            if ($row['part_number'] > 1 && $row['passage_id']) {
                if (!isset($passages_map[$row['passage_id']])) {
                    $passages_map[$row['passage_id']] = [
                        'id' => $row['passage_id'],
                        'passage_order' => $row['passage_order'],
                        'title' => $row['passage_title'],
                        'content' => $row['passage_content'],
                        'audio_url' => $row['passage_audio_url'],
                        'questions' => [],
                    ];
                    $parts_map[$row['part_id']]['passages'][] = &$passages_map[$row['passage_id']];
                }
            }

            // Handle questions
            if ($row['question_id']) {
                if (!isset($questions_map[$row['question_id']])) {
                    $questions_map[$row['question_id']] = [
                        'id' => $row['question_id'],
                        'content' => $row['question_content'],
                        'order_index' => $row['question_order'],
                        'options' => [],
                    ];
                    
                    // Add question to appropriate parent (passage or part)
                    if ($row['part_number'] > 1 && $row['passage_id']) {
                        $passages_map[$row['passage_id']]['questions'][] = &$questions_map[$row['question_id']];
                    } else {
                        $parts_map[$row['part_id']]['questions'][] = &$questions_map[$row['question_id']];
                    }
                }

                // Handle options
                if ($row['option_id']) {
                    $option = [
                        'id' => $row['option_id'],
                        'option_label' => $row['option_label'],
                        'content' => $row['option_content'],
                    ];
                    if ($with_answers) {
                        $option['is_correct'] = $row['is_correct'];
                    }
                    $questions_map[$row['question_id']]['options'][] = $option;
                }
            }
        }

        // Save to cache
        @file_put_contents($cache_file, json_encode($exam));

        return $exam;
    }

    // Clear cache when exam is updated
    public function clearCache($id) {
        $cache_file1 = sys_get_temp_dir() . '/vstep_exam_' . $id . '_full.cache';
        $cache_file2 = sys_get_temp_dir() . '/vstep_exam_' . $id . '_taking.cache';
        @unlink($cache_file1);
        @unlink($cache_file2);
    }

    // Get exam content WITHOUT correct answers (for taking exam)
    public function getForTaking($id) {
        return $this->getWithContent($id, false);
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
