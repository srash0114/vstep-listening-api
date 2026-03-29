<?php
/**
 * Incremental Question Management Controller
 * Handle individual question creation, update, deletion with audio upload
 */
class QuestionController {
    private $question_model;
    private $option_model;

    public function __construct() {
        $this->question_model = new Question();
        $this->option_model = new Option();
    }

    /**
     * POST /api/v1/admin/exams/{exam_id}/questions
     * Create single question with optional audio upload
     * 
     * Multipart: 
     * - question_json: {"content": "...", "difficulty_level": "...", ...}
     * - audio: optional MP3 file
     */
    public static function create($exam_id = null) {
        // Handle URL path parameter
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        
        if (empty($exam_id)) {
            $response = Response::badRequest('missing_param', 'exam_id is required');
            Response::send($response);
            return;
        }

        $exam_id = intval($exam_id);
        $question_data = null;
        $audio_file = null;

        // Parse JSON or form data
        if (!empty($_POST['question_json'])) {
            $question_data = json_decode($_POST['question_json'], true);
        } else {
            $json_body = file_get_contents("php://input");
            $question_data = json_decode($json_body, true);
        }

        if (empty($question_data)) {
            $response = Response::badRequest('invalid_structure', 'question_data is required');
            Response::send($response);
            return;
        }

        $controller = new self();

        // Upload audio if provided
        if (!empty($_FILES['audio'])) {
            $upload = CloudinaryAudioUpload::uploadAudio($_FILES['audio']);
            if (!$upload['success']) {
                $response = Response::badRequest('upload_failed', $upload['error']);
                Response::send($response);
                return;
            }
            $question_data['audio_url'] = $upload['url'];
            $question_data['audio_path'] = $upload['path'];
        }

        $question_data['exam_id'] = $exam_id;

        $question_id = $controller->question_model->create($question_data);

        if (!$question_id) {
            $response = Response::serverError('Failed to create question');
            Response::send($response);
            return;
        }

        $question = $controller->question_model->getById($question_id);

        $response = Response::created($question, 'Question created successfully');
        Response::send($response);
    }

    /**
     * PUT /api/v1/admin/exams/{exam_id}/questions/{question_id}
     * Update question (content, difficulty, script, audio)
     */
    public static function update($exam_id = null, $question_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($question_id === null) {
            $question_id = $_GET['question_id'] ?? null;
        }
        
        if (empty($exam_id) || empty($question_id)) {
            $response = Response::badRequest('missing_param', 'exam_id and question_id required');
            Response::send($response);
            return;
        }

        $question_id = intval($question_id);
        $question_data = null;

        // Parse JSON or form data
        if (!empty($_POST['question_json'])) {
            $question_data = json_decode($_POST['question_json'], true);
        } else {
            $json_body = file_get_contents("php://input");
            $question_data = json_decode($json_body, true);
        }

        if (empty($question_data)) {
            $response = Response::badRequest('invalid_structure', 'question_data is required');
            Response::send($response);
            return;
        }

        $controller = new self();
        $question = $controller->question_model->getById($question_id);

        if (!$question) {
            $response = Response::notFound('Question not found');
            Response::send($response);
            return;
        }

        // Upload new audio if provided
        if (!empty($_FILES['audio'])) {
            $upload = CloudinaryAudioUpload::uploadAudio($_FILES['audio']);
            if ($upload['success']) {
                // Delete old audio if exists
                if (!empty($question['audio_path'])) {
                    CloudinaryAudioUpload::deleteAudio($question['audio_path']);
                }
                $question_data['audio_url'] = $upload['url'];
                $question_data['audio_path'] = $upload['path'];
            }
        }

        $success = $controller->question_model->update($question_id, $question_data);

        if (!$success) {
            $response = Response::serverError('Failed to update question');
            Response::send($response);
            return;
        }

        $updated_question = $controller->question_model->getById($question_id);

        $response = Response::success($updated_question, 'Question updated successfully');
        Response::send($response);
    }

    /**
     * DELETE /api/v1/admin/exams/{exam_id}/questions/{question_id}
     * Delete question and related options
     */
    public static function delete($exam_id = null, $question_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($question_id === null) {
            $question_id = $_GET['question_id'] ?? null;
        }
        
        if (empty($exam_id) || empty($question_id)) {
            $response = Response::badRequest('missing_param', 'exam_id and question_id required');
            Response::send($response);
            return;
        }

        $question_id = intval($question_id);
        $controller = new self();

        $question = $controller->question_model->getById($question_id);
        if (!$question) {
            $response = Response::notFound('Question not found');
            Response::send($response);
            return;
        }

        // Delete audio from Cloudinary
        if (!empty($question['audio_path'])) {
            CloudinaryAudioUpload::deleteAudio($question['audio_path']);
        }

        // Delete options
        $controller->option_model->deleteByQuestionId($question_id);

        // Delete question
        $success = $controller->question_model->delete($question_id);

        if (!$success) {
            $response = Response::serverError('Failed to delete question');
            Response::send($response);
            return;
        }

        $response = Response::success(null, 'Question deleted successfully');
        Response::send($response);
    }

    /**
     * GET /api/v1/admin/exams/{exam_id}/questions
     * List all questions for exam
     */
    public static function getByExam($exam_id = null) {
        // Handle URL path parameter
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        
        if (empty($exam_id)) {
            $response = Response::badRequest('missing_param', 'exam_id is required');
            Response::send($response);
            return;
        }

        $exam_id = intval($exam_id);
        $controller = new self();

        $questions = $controller->question_model->getByExamId($exam_id);

        $response = Response::success($questions, 'Questions retrieved');
        Response::send($response);
    }
}
?>
