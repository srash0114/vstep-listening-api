<?php
class PartController {
    private $part_model;
    private $passage_model;
    private $question_model;
    private $option_model;

    public function __construct() {
        $this->part_model = new Part();
        $this->passage_model = new Passage();
        $this->question_model = new Question();
        $this->option_model = new Option();
    }

    /**
     * GET /api/v1/exams/{exam_id}/parts
     * Get all parts for an exam
     */
    public static function getByExamId($exam_id = null) {
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

        $parts = $controller->part_model->getByExamId($exam_id);

        if (empty($parts)) {
            $response = Response::success([], 'No parts found for this exam');
        } else {
            $response = Response::success($parts, 'Parts retrieved successfully');
        }

        Response::send($response);
    }

    /**
     * GET /api/v1/admin/parts/{part_id}
     * Get part details
     */
    public static function getById($part_id = null) {
        // Handle URL path parameter
        if ($part_id === null) {
            $part_id = $_GET['id'] ?? null;
        }
        
        if (empty($part_id)) {
            $response = Response::badRequest('missing_id', 'Part ID is required');
            Response::send($response);
            return;
        }

        $part_id = intval($part_id);
        $controller = new self();

        $part = $controller->part_model->getWithContent($part_id);

        if (!$part) {
            $response = Response::notFound('Part not found');
            Response::send($response);
            return;
        }

        $response = Response::success($part, 'Part retrieved successfully');
        Response::send($response);
    }

    /**
     * POST /api/v1/admin/exams/{exam_id}/parts
     * Create new part
     */
    public static function create() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['exam_id']) || empty($data['part_number']) || empty($data['title'])) {
            $response = Response::badRequest('missing_fields', 'exam_id, part_number, and title are required');
            Response::send($response);
            return;
        }

        $controller = new self();
        $part_id = $controller->part_model->create($data);

        if (!$part_id) {
            $response = Response::serverError('Failed to create part');
            Response::send($response);
            return;
        }

        $part = $controller->part_model->getById($part_id);
        $response = Response::success($part, 'Part created successfully', 201);
        Response::send($response);
    }

    /**
     * PUT /api/v1/admin/parts/{part_id}
     * Update part
     */
    public static function update($part_id = null) {
        // Handle URL path parameter
        if ($part_id === null) {
            $part_id = $_GET['id'] ?? null;
        }
        
        if (empty($part_id)) {
            $response = Response::badRequest('missing_id', 'Part ID is required');
            Response::send($response);
            return;
        }

        $part_id = intval($part_id);
        $data = json_decode(file_get_contents("php://input"), true);

        $controller = new self();
        $part = $controller->part_model->getById($part_id);

        if (!$part) {
            $response = Response::notFound('Part not found');
            Response::send($response);
            return;
        }

        if ($controller->part_model->update($part_id, $data)) {
            $part = $controller->part_model->getById($part_id);
            $response = Response::success($part, 'Part updated successfully');
        } else {
            $response = Response::serverError('Failed to update part');
        }

        Response::send($response);
    }

    /**
     * POST /api/v1/admin/parts/{part_id}/upload-audio
     * Upload audio file for a part
     */
    public static function uploadAudio($part_id = null) {
        if ($part_id === null) {
            $part_id = $_GET['id'] ?? null;
        }

        if (empty($part_id)) {
            $response = Response::badRequest('missing_id', 'Part ID is required');
            Response::send($response);
            return;
        }

        $part_id = intval($part_id);
        $controller = new self();

        $part = $controller->part_model->getById($part_id);
        if (!$part) {
            $response = Response::notFound('Part not found');
            Response::send($response);
            return;
        }

        if (empty($_FILES['audio'])) {
            $response = Response::badRequest('missing_file', 'Audio file is required');
            Response::send($response);
            return;
        }

        $result = AppwriteAudioUpload::uploadAudio($_FILES['audio']);
        if (!$result['success']) {
            $response = Response::badRequest('upload_failed', $result['error']);
            Response::send($response);
            return;
        }

        // Delete old audio from Appwrite if exists
        if (!empty($part['audio_path'])) {
            AppwriteAudioUpload::deleteAudio($part['audio_path']);
        }

        $controller->part_model->update($part_id, [
            'audio_url' => $result['url'],
            'audio_path' => $result['path']
        ]);
        $part = $controller->part_model->getById($part_id);
        $response = Response::success($part, 'Audio uploaded successfully');
        Response::send($response);
    }

    /**
     * PUT /api/v1/admin/exams/{exam_id}/parts/{part_id}
     * Alias: ignore exam_id, delegate to update()
     */
    public static function updateNested($exam_id = null, $part_id = null) {
        self::update($part_id);
    }

    /**
     * DELETE /api/v1/admin/exams/{exam_id}/parts/{part_id}
     * Alias: ignore exam_id, delegate to delete()
     */
    public static function deleteNested($exam_id = null, $part_id = null) {
        self::delete($part_id);
    }

    /**
     * POST /api/v1/admin/exams/{exam_id}/parts/{part_id}/upload-audio
     * Alias: ignore exam_id, delegate to uploadAudio()
     */
    public static function uploadAudioNested($exam_id = null, $part_id = null) {
        self::uploadAudio($part_id);
    }

    /**
     * DELETE /api/v1/admin/parts/{part_id}
     * Delete part
     */
    public static function delete($part_id = null) {
        // Handle URL path parameter
        if ($part_id === null) {
            $part_id = $_GET['id'] ?? null;
        }
        
        if (empty($part_id)) {
            $response = Response::badRequest('missing_id', 'Part ID is required');
            Response::send($response);
            return;
        }

        $part_id = intval($part_id);
        $controller = new self();

        if ($controller->part_model->delete($part_id)) {
            $response = Response::success(null, 'Part deleted successfully');
        } else {
            $response = Response::serverError('Failed to delete part');
        }

        Response::send($response);
    }
}
