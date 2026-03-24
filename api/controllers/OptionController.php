<?php
/**
 * Incremental Option Management Controller
 * Handle individual option creation, update, deletion
 */
class OptionController {
    private $option_model;

    public function __construct() {
        $this->option_model = new Option();
    }

    /**
     * POST /api/v1/admin/exams/{exam_id}/questions/{question_id}/options
     * Add option to question
     */
    public static function create($exam_id = null, $question_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($question_id === null) {
            $question_id = $_GET['question_id'] ?? null;
        }
        
        if (empty($question_id)) {
            $response = Response::badRequest('missing_param', 'question_id is required');
            Response::send($response);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['content']) || !isset($data['is_correct'])) {
            $response = Response::badRequest('missing_fields', 'content and is_correct are required');
            Response::send($response);
            return;
        }

        $question_id = intval($question_id);
        $data['question_id'] = $question_id;

        // Auto-generate option_label if not provided
        if (empty($data['option_label'])) {
            $existing = (new self())->option_model->getByQuestionId($question_id);
            $data['option_label'] = chr(65 + count($existing)); // A, B, C, D
        }

        $controller = new self();
        $option_id = $controller->option_model->create($data);

        if (!$option_id) {
            $response = Response::serverError('Failed to create option');
            Response::send($response);
            return;
        }

        $option = $controller->option_model->getById($option_id);

        $response = Response::created($option, 'Option created successfully');
        Response::send($response);
    }

    /**
     * PUT /api/v1/admin/exams/{exam_id}/questions/{question_id}/options/{option_id}
     * Update option
     */
    public static function update($exam_id = null, $question_id = null, $option_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($question_id === null) {
            $question_id = $_GET['question_id'] ?? null;
        }
        if ($option_id === null) {
            $option_id = $_GET['option_id'] ?? null;
        }
        
        if (empty($option_id)) {
            $response = Response::badRequest('missing_param', 'option_id is required');
            Response::send($response);
            return;
        }

        $option_id = intval($option_id);
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data)) {
            $response = Response::badRequest('invalid_structure', 'No data provided');
            Response::send($response);
            return;
        }

        $controller = new self();
        $option = $controller->option_model->getById($option_id);

        if (!$option) {
            $response = Response::notFound('Option not found');
            Response::send($response);
            return;
        }

        $success = $controller->option_model->update($option_id, $data);

        if (!$success) {
            $response = Response::serverError('Failed to update option');
            Response::send($response);
            return;
        }

        $updated = $controller->option_model->getById($option_id);

        $response = Response::success($updated, 'Option updated successfully');
        Response::send($response);
    }

    /**
     * DELETE /api/v1/admin/exams/{exam_id}/questions/{question_id}/options/{option_id}
     * Delete option
     */
    public static function delete($exam_id = null, $question_id = null, $option_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($question_id === null) {
            $question_id = $_GET['question_id'] ?? null;
        }
        if ($option_id === null) {
            $option_id = $_GET['option_id'] ?? null;
        }
        
        if (empty($option_id)) {
            $response = Response::badRequest('missing_param', 'option_id is required');
            Response::send($response);
            return;
        }

        $option_id = intval($option_id);

        $controller = new self();
        $option = $controller->option_model->getById($option_id);

        if (!$option) {
            $response = Response::notFound('Option not found');
            Response::send($response);
            return;
        }

        $success = $controller->option_model->delete($option_id);

        if (!$success) {
            $response = Response::serverError('Failed to delete option');
            Response::send($response);
            return;
        }

        $response = Response::success(null, 'Option deleted successfully');
        Response::send($response);
    }

    /**
     * GET /api/v1/admin/exams/{exam_id}/questions/{question_id}/options
     * Get all options for question
     */
    public static function getByQuestion($exam_id = null, $question_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($question_id === null) {
            $question_id = $_GET['question_id'] ?? null;
        }
        
        if (empty($question_id)) {
            $response = Response::badRequest('missing_param', 'question_id is required');
            Response::send($response);
            return;
        }

        $question_id = intval($question_id);
        $controller = new self();

        $options = $controller->option_model->getByQuestionId($question_id);

        $response = Response::success($options, 'Options retrieved');
        Response::send($response);
    }
}
?>
