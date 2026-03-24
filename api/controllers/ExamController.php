<?php
class ExamController {
    private $exam_model;
    private $part_model;

    public function __construct() {
        $this->exam_model = new Exam();
        $this->part_model = new Part();
    }

    /**
     * GET /api/v1/exams
     * Get all exams (list view)
     */
    public static function getAll() {
        $controller = new self();
        
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        
        if ($limit > 100) $limit = 100; // Max limit protection
        
        $offset = ($page - 1) * $limit;
        
        $exams = $controller->exam_model->getAll($limit, $offset);
        $total = $controller->exam_model->count();
        
        if (empty($exams)) {
            $response = Response::success([], 'No exams found');
        } else {
            $response = Response::success([
                'data' => $exams,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ], 'Exams retrieved successfully');
        }
        
        Response::send($response);
    }

    /**
     * GET /api/v1/exams/{exam_id}
     * Get exam with all content (Parts, Passages, Questions, Options)
     * Used when taking exam or viewing detailed content
     */
    public static function getById($exam_id = null) {
        // Handle both:
        // 1. URL path parameter: /api/v1/exams/4 (exam_id passed as argument)
        // 2. Query parameter: /api/v1/exams?id=4 (exam_id in $_GET)
        if ($exam_id === null) {
            $exam_id = $_GET['id'] ?? null;
        }
        
        if (empty($exam_id)) {
            $response = Response::badRequest('missing_id', 'Exam ID is required');
            Response::send($response);
            return;
        }

        $exam_id = intval($exam_id);
        $controller = new self();
        
        // Check if this is for taking exam (hide answers and scripts)
        $for_taking = isset($_GET['for_taking']) && $_GET['for_taking'] == '1';
        
        if ($for_taking) {
            $exam = $controller->exam_model->getForTaking($exam_id);
        } else {
            $exam = $controller->exam_model->getWithContent($exam_id);
        }

        if (!$exam) {
            $response = Response::notFound('Exam not found');
            Response::send($response);
            return;
        }

        $response = Response::success($exam, 'Exam retrieved successfully');
        Response::send($response);
    }

    /**
     * GET /api/v1/exams/{exam_id}/part/{part_number}
     * Get specific part (1, 2, or 3)
     */
    public static function getPart($exam_id = null, $part_number = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($part_number === null) {
            $part_number = $_GET['part_number'] ?? null;
        }
        
        if (empty($exam_id) || !isset($part_number)) {
            $response = Response::badRequest('missing_params', 'exam_id and part_number are required');
            Response::send($response);
            return;
        }

        $exam_id = intval($exam_id);
        $part_number = intval($part_number);
        $controller = new self();

        // Get part
        $parts = $controller->part_model->getByExamId($exam_id);
        $part = null;
        
        foreach ($parts as $p) {
            if ($p['part_number'] == $part_number) {
                $part = $p;
                break;
            }
        }

        if (!$part) {
            $response = Response::notFound('Part not found');
            Response::send($response);
            return;
        }

        // Get part content
        $part = $controller->part_model->getWithContent($part['id']);

        // Remove sensitive data for taking exam
        if (isset($_GET['for_taking']) && $_GET['for_taking'] == '1') {
            if ($part['passages'] ?? null) {
                foreach ($part['passages'] as &$passage) {
                    unset($passage['script']);
                    foreach ($passage['questions'] as &$question) {
                        unset($question['script']);
                        unset($question['difficulty_level']);
                        foreach ($question['options'] as &$option) {
                            unset($option['is_correct']);
                        }
                    }
                }
            } elseif ($part['questions'] ?? null) {
                foreach ($part['questions'] as &$question) {
                    unset($question['script']);
                    unset($question['difficulty_level']);
                    foreach ($question['options'] as &$option) {
                        unset($option['is_correct']);
                    }
                }
            }
        }

        $response = Response::success($part, 'Part retrieved successfully');
        Response::send($response);
    }

    /**
     * POST /api/v1/admin/exams
     * Create new exam
     */
    public static function create() {
        // Check admin permission (implement your auth logic)
        // if (!Auth::isAdmin()) { Response::forbidden(); return; }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['title'])) {
            $response = Response::badRequest('missing_field', 'title is required');
            Response::send($response);
            return;
        }

        $controller = new self();
        $exam_id = $controller->exam_model->create($data);

        if (!$exam_id) {
            $response = Response::serverError('Failed to create exam');
            Response::send($response);
            return;
        }

        $exam = $controller->exam_model->getById($exam_id);
        $response = Response::success($exam, 'Exam created successfully', 201);
        Response::send($response);
    }

    /**
     * PUT /api/v1/admin/exams/{exam_id}
     * Update exam
     */
    public static function update($exam_id = null) {
        // Handle URL path parameter
        if ($exam_id === null) {
            $exam_id = $_GET['id'] ?? null;
        }
        
        if (empty($exam_id)) {
            $response = Response::badRequest('missing_id', 'Exam ID is required');
            Response::send($response);
            return;
        }

        $exam_id = intval($exam_id);
        $data = json_decode(file_get_contents("php://input"), true);

        $controller = new self();
        $exam = $controller->exam_model->getById($exam_id);

        if (!$exam) {
            $response = Response::notFound('Exam not found');
            Response::send($response);
            return;
        }

        if ($controller->exam_model->update($exam_id, $data)) {
            $exam = $controller->exam_model->getById($exam_id);
            $response = Response::success($exam, 'Exam updated successfully');
        } else {
            $response = Response::serverError('Failed to update exam');
        }

        Response::send($response);
    }

    /**
     * DELETE /api/v1/admin/exams/{exam_id}
     * Delete exam
     */
    public static function delete($exam_id = null) {
        // Handle URL path parameter
        if ($exam_id === null) {
            $exam_id = $_GET['id'] ?? null;
        }
        
        if (empty($exam_id)) {
            $response = Response::badRequest('missing_id', 'Exam ID is required');
            Response::send($response);
            return;
        }

        $exam_id = intval($exam_id);
        $controller = new self();
        
        $exam = $controller->exam_model->getById($exam_id);
        if (!$exam) {
            $response = Response::notFound('Exam not found');
            Response::send($response);
            return;
        }

        if ($controller->exam_model->delete($exam_id)) {
            $response = Response::success(null, 'Exam deleted successfully');
        } else {
            $response = Response::serverError('Failed to delete exam');
        }

        Response::send($response);
    }
}
