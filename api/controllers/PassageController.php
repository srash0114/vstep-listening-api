<?php
/**
 * Incremental Passage Management Controller
 * Handle passages (conversations/lectures) for Part 2 & 3
 */
class PassageController {
    private $passage_model;
    private $question_model;

    public function __construct() {
        $this->passage_model = new Passage();
        $this->question_model = new Question();
    }

    /**
     * POST /api/v1/admin/exams/{exam_id}/parts/{part_id}/passages
     * Create passage with optional audio
     * 
     * Multipart:
     * - passage_json: {"title": "...", "script": "...", ...}
     * - audio: optional MP3 file
     */
    public static function create($exam_id = null, $part_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($part_id === null) {
            $part_id = $_GET['part_id'] ?? null;
        }
        
        if (empty($part_id)) {
            $response = Response::badRequest('missing_param', 'part_id is required');
            Response::send($response);
            return;
        }

        $passage_data = null;

        // Parse JSON or form data
        if (!empty($_POST['passage_json'])) {
            $passage_data = json_decode($_POST['passage_json'], true);
        } else {
            $json_body = file_get_contents("php://input");
            $passage_data = json_decode($json_body, true);
        }

        if (empty($passage_data)) {
            $response = Response::badRequest('invalid_structure', 'passage_data is required');
            Response::send($response);
            return;
        }

        $part_id = intval($part_id);
        $passage_data['part_id'] = $part_id;

        $controller = new self();

        // Upload audio if provided
        if (!empty($_FILES['audio'])) {
            $upload = AppwriteAudioUpload::uploadAudio($_FILES['audio']);
            if (!$upload['success']) {
                $response = Response::badRequest('upload_failed', $upload['error']);
                Response::send($response);
                return;
            }
            $passage_data['audio_url'] = $upload['url'];
            $passage_data['audio_path'] = $upload['path'];
        }

        $passage_id = $controller->passage_model->create($passage_data);

        if (!$passage_id) {
            $response = Response::serverError('Failed to create passage');
            Response::send($response);
            return;
        }

        $passage = $controller->passage_model->getById($passage_id);

        $response = Response::created($passage, 'Passage created successfully');
        Response::send($response);
    }

    /**
     * PUT /api/v1/admin/exams/{exam_id}/parts/{part_id}/passages/{passage_id}
     * Update passage
     */
    public static function update($exam_id = null, $part_id = null, $passage_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($part_id === null) {
            $part_id = $_GET['part_id'] ?? null;
        }
        if ($passage_id === null) {
            $passage_id = $_GET['passage_id'] ?? null;
        }
        
        if (empty($passage_id)) {
            $response = Response::badRequest('missing_param', 'passage_id is required');
            Response::send($response);
            return;
        }

        $passage_id = intval($passage_id);
        $passage_data = null;

        // Parse JSON or form data
        if (!empty($_POST['passage_json'])) {
            $passage_data = json_decode($_POST['passage_json'], true);
        } else {
            $json_body = file_get_contents("php://input");
            $passage_data = json_decode($json_body, true);
        }

        if (empty($passage_data)) {
            $response = Response::badRequest('invalid_structure', 'passage_data is required');
            Response::send($response);
            return;
        }

        $controller = new self();
        $passage = $controller->passage_model->getById($passage_id);

        if (!$passage) {
            $response = Response::notFound('Passage not found');
            Response::send($response);
            return;
        }

        // Upload new audio if provided
        if (!empty($_FILES['audio'])) {
            $upload = AppwriteAudioUpload::uploadAudio($_FILES['audio']);
            if ($upload['success']) {
                // Delete old audio
                if (!empty($passage['audio_path'])) {
                    AppwriteAudioUpload::deleteAudio($passage['audio_path']);
                }
                $passage_data['audio_url'] = $upload['url'];
                $passage_data['audio_path'] = $upload['path'];
            }
        }

        $success = $controller->passage_model->update($passage_id, $passage_data);

        if (!$success) {
            $response = Response::serverError('Failed to update passage');
            Response::send($response);
            return;
        }

        $updated = $controller->passage_model->getById($passage_id);

        $response = Response::success($updated, 'Passage updated successfully');
        Response::send($response);
    }

    /**
     * DELETE /api/v1/admin/exams/{exam_id}/parts/{part_id}/passages/{passage_id}
     * Delete passage and related questions
     */
    public static function delete($exam_id = null, $part_id = null, $passage_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($part_id === null) {
            $part_id = $_GET['part_id'] ?? null;
        }
        if ($passage_id === null) {
            $passage_id = $_GET['passage_id'] ?? null;
        }
        
        if (empty($passage_id)) {
            $response = Response::badRequest('missing_param', 'passage_id is required');
            Response::send($response);
            return;
        }

        $passage_id = intval($passage_id);

        $controller = new self();
        $passage = $controller->passage_model->getById($passage_id);

        if (!$passage) {
            $response = Response::notFound('Passage not found');
            Response::send($response);
            return;
        }

        // Delete audio from Appwrite
        if (!empty($passage['audio_path'])) {
            AppwriteAudioUpload::deleteAudio($passage['audio_path']);
        }

        // Delete associated questions
        $questions = $controller->question_model->getByPassageId($passage_id);
        foreach ($questions as $q) {
            $controller->question_model->delete($q['id']);
        }

        // Delete passage
        $success = $controller->passage_model->delete($passage_id);

        if (!$success) {
            $response = Response::serverError('Failed to delete passage');
            Response::send($response);
            return;
        }

        $response = Response::success(null, 'Passage deleted successfully');
        Response::send($response);
    }

    /**
     * GET /api/v1/admin/exams/{exam_id}/parts/{part_id}/passages
     * List passages for part
     */
    public static function getByPart($exam_id = null, $part_id = null) {
        // Handle URL path parameters
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        if ($part_id === null) {
            $part_id = $_GET['part_id'] ?? null;
        }
        
        if (empty($part_id)) {
            $response = Response::badRequest('missing_param', 'part_id is required');
            Response::send($response);
            return;
        }

        $part_id = intval($part_id);
        $controller = new self();

        $passages = $controller->passage_model->getByPartId($part_id);

        $response = Response::success($passages, 'Passages retrieved');
        Response::send($response);
    }
}
?>
