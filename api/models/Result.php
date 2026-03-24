<?php
// Result Model
class Result {
    private $db;
    private $table = 'results';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $query = "SELECT * FROM {$this->table} ORDER BY submittedAt DESC LIMIT $offset, $limit";
        $result = $this->db->query($query);
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $row['detailedResults'] = json_decode($row['detailedResults'], true);
            $results[] = $row;
        }
        
        return $results;
    }

    public function getById($id) {
        $id = intval($id);
        $query = "SELECT * FROM {$this->table} WHERE id = {$id}";
        $result = $this->db->query($query);
        
        if ($result->num_rows == 0) {
            return null;
        }
        
        $row = $result->fetch_assoc();
        $row['detailedResults'] = json_decode($row['detailedResults'], true);
        return $row;
    }

    public function getByUserId($userId, $page = 1, $limit = 10) {
        $userId = intval($userId);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT * FROM {$this->table} WHERE userId = {$userId} ORDER BY submittedAt DESC LIMIT $offset, $limit";
        $result = $this->db->query($query);
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $row['detailedResults'] = json_decode($row['detailedResults'], true);
            $results[] = $row;
        }
        
        return $results;
    }

    public function create($data, $detailedResults) {
        $testId = intval($data['testId']);
        $userId = !empty($data['userId']) ? intval($data['userId']) : null;
        $totalQuestions = intval($data['totalQuestions']);
        $correctAnswers = intval($data['correctAnswers']);
        $score = floatval($data['score']);
        $percentage = intval($data['percentage']);
        $timeSpent = intval($data['timeSpent']);
        $performanceLevel = $this->db->real_escape_string($data['performanceLevel']);
        
        $answers = $this->db->real_escape_string(json_encode($data['answers']));
        $detailedResultsJson = $this->db->real_escape_string(json_encode($detailedResults));

        $userIdString = $userId ? $userId : "NULL";
        
        $query = "INSERT INTO {$this->table} 
                  (testId, userId, totalQuestions, correctAnswers, score, percentage, timeSpent, 
                   answers, detailedResults, performanceLevel) 
                  VALUES ($testId, $userIdString, $totalQuestions, $correctAnswers, $score, $percentage, 
                  $timeSpent, '$answers', '$detailedResultsJson', '$performanceLevel')";

        if ($this->db->query($query)) {
            return $this->db->insert_id;
        }

        return false;
    }

    public function getStats($testId = null) {
        $whereClause = $testId ? " WHERE testId = " . intval($testId) : "";
        
        $query = "SELECT 
                    COUNT(*) as totalResults,
                    AVG(score) as averageScore,
                    MAX(score) as maxScore,
                    MIN(score) as minScore,
                    AVG(timeSpent) as averageTimeSpent,
                    SUM(CASE WHEN performanceLevel = 'excellent' THEN 1 ELSE 0 END) as excellent,
                    SUM(CASE WHEN performanceLevel = 'good' THEN 1 ELSE 0 END) as good,
                    SUM(CASE WHEN performanceLevel = 'needsWork' THEN 1 ELSE 0 END) as needsWork
                  FROM {$this->table} $whereClause";

        $result = $this->db->query($query);
        return $result->fetch_assoc();
    }
}
?>
