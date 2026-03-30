<?php
class TestAccessController {
    private $user_exam_model;
    private $user_answer_model;
    private $question_model;
    private $option_model;

    public function __construct() {
        $this->user_exam_model = new UserExam();
        $this->user_answer_model = new UserAnswer();
        $this->question_model = new Question();
        $this->option_model = new Option();
    }

    /**
     * POST /api/v1/exams/{exam_id}/start
     * Start exam (user begins taking test)
     */
    public static function startExam($exam_id = null) {
        // Handle URL path parameter
        if ($exam_id === null) {
            $exam_id = $_GET['exam_id'] ?? null;
        }
        
        if (empty($exam_id)) {
            $response = Response::badRequest('missing_param', 'exam_id is required');
            Response::send($response);
            return;
        }

        $token = TokenManager::getTokenFromHeader();
        if (!$token) {
            $response = Response::unauthorized('No token provided');
            Response::send($response);
            return;
        } 

        $decoded = TokenManager::verify($token);
        if (!$decoded) {    
            $response = Response::unauthorized('Token invalid or expired');
            Response::send($response);
            return;
        }
        
        // Get user_id from auth (implement your auth logic)
        $user_id = $decoded['userId'];

        $exam_id = intval($exam_id);
        $controller = new self();

        // Check if there's an in-progress exam for this user + exam
        $existing = $controller->user_exam_model->getInProgress($user_id, $exam_id);
        if ($existing) {
            $existing['answers'] = $controller->user_answer_model->getByUserExamId($existing['id']);
            $response = Response::success($existing, 'Resuming existing exam');
            Response::send($response);
            return;
        }

        $user_exam_id = $controller->user_exam_model->startExam($user_id, $exam_id);

        if (!$user_exam_id) {
            $response = Response::serverError('Failed to start exam');
            Response::send($response);
            return;
        }

        $user_exam = $controller->user_exam_model->getById($user_exam_id);
        $response = Response::success($user_exam, 'Exam started successfully', 201);
        Response::send($response);
    }

    /**
     * POST /api/v1/user-exams/{user_exam_id}/answer
     * Save single answer while taking exam
     */
    public static function saveAnswer($user_exam_id = null) {
        // Handle URL path parameter
        if ($user_exam_id === null) {
            $user_exam_id = $_GET['user_exam_id'] ?? null;
        }
        
        if (empty($user_exam_id)) {
            $response = Response::badRequest('missing_param', 'user_exam_id is required');
            Response::send($response);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['question_id'])) {
            $response = Response::badRequest('missing_field', 'question_id is required');
            Response::send($response);
            return;
        }

        $user_exam_id = intval($user_exam_id);
        $question_id = intval($data['question_id']);
        $selected_option_id = isset($data['selected_option_id']) ? intval($data['selected_option_id']) : null;

        $controller = new self();

        // Verify user exam exists and not submitted
        $user_exam = $controller->user_exam_model->getById($user_exam_id);
        if (!$user_exam) {
            $response = Response::notFound('User exam not found');
            Response::send($response);
            return;
        }

        if ($user_exam['submitted_at']) {
            $response = Response::conflict('exam_submitted', 'This exam has already been submitted');
            Response::send($response);
            return;
        }

        // Save answer
        $result = $controller->user_answer_model->save($user_exam_id, $question_id, $selected_option_id);

        if ($result !== false) {
            $response = Response::success(['answer_id' => $result], 'Answer saved successfully');
        } else {
            $response = Response::serverError('Failed to save answer');
        }

        Response::send($response);
    }

    /**
     * POST /api/v1/user-exams/{user_exam_id}/submit
     * Submit exam and calculate score
     */
    public static function submitExam($user_exam_id = null) {
        // Handle URL path parameter
        if ($user_exam_id === null) {
            $user_exam_id = $_GET['user_exam_id'] ?? null;
        }

        if (empty($user_exam_id)) {
            $response = Response::badRequest('missing_param', 'user_exam_id is required');
            Response::send($response);
            return;
        }

        // Extract user_id from token
        $token = TokenManager::getTokenFromHeader();
        if (!$token) {
            $response = Response::unauthorized('No token provided');
            Response::send($response);
            return;
        }
        $decoded = TokenManager::verify($token);
        if (!$decoded) {
            $response = Response::unauthorized('Token invalid or expired');
            Response::send($response);
            return;
        }
        $user_id = $decoded['userId'];

        $data = json_decode(file_get_contents("php://input"), true);
        $time_spent = $data['time_spent'] ?? 0;

        $user_exam_id = intval($user_exam_id);
        $controller = new self();

        $user_exam = $controller->user_exam_model->getById($user_exam_id);
        if (!$user_exam) {
            $response = Response::notFound('User exam not found');
            Response::send($response);
            return;
        }

        // Verify ownership
        if ((int)$user_exam['user_id'] !== (int)$user_id) {
            $response = Response::forbidden('Access denied');
            Response::send($response);
            return;
        }

        if ($user_exam['submitted_at']) {
            $response = Response::conflict('exam_submitted', 'This exam has already been submitted');
            Response::send($response);
            return;
        }

        // Get all answers
        $user_answers = $controller->user_answer_model->getByUserExamId($user_exam_id);

        // Calculate score
        $total_questions = 35; // Standard VSTEP
        $correct_answers = 0;

        foreach ($user_answers as &$answer) {
            $question = $controller->question_model->getById($answer['question_id']);
            
            if ($question) {
                $correct_option = $controller->option_model->getCorrectOption($answer['question_id']);
                
                if ($correct_option && $answer['selected_option_id'] == $correct_option['id']) {
                    $correct_answers++;
                    $answer['is_correct'] = true;
                } else {
                    $answer['is_correct'] = false;
                }
            }
        }

        $score = ($correct_answers / $total_questions) * 100;
        $percentage = $correct_answers; // For VSTEP, percentage = number of correct answers
        $performance_level = $controller->calculatePerformanceLevel($correct_answers);

        // Update user exam record
        $submit_data = [
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers,
            'score' => $score,
            'percentage' => $percentage,
            'time_spent' => intval($time_spent),
            'performance_level' => $performance_level
        ];

        $controller->user_exam_model->submitExam($user_exam_id, $submit_data);

        $result = [
            'user_exam_id' => $user_exam_id,
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers,
            'score' => round($score, 2),
            'percentage' => $percentage,
            'performance_level' => $performance_level,
            'time_spent' => intval($time_spent)
        ];

        $response = Response::success($result, 'Exam submitted successfully');
        Response::send($response);
    }

    /**
     * GET /api/v1/user-exams/{user_exam_id}/result
     * Get detailed exam result with answers and scripts
     */
    public static function getResult($user_exam_id = null) {
        // Handle URL path parameter
        if ($user_exam_id === null) {
            $user_exam_id = $_GET['user_exam_id'] ?? null;
        }
        
        if (empty($user_exam_id)) {
            $response = Response::badRequest('missing_param', 'user_exam_id is required');
            Response::send($response);
            return;
        }

        $user_exam_id = intval($user_exam_id);
        $controller = new self();

        $user_exam = $controller->user_exam_model->getWithAnswers($user_exam_id);
        if (!$user_exam) {
            $response = Response::notFound('User exam not found');
            Response::send($response);
            return;
        }

        // Add full question details and scripts
        foreach ($user_exam['answers'] as &$answer) {
            $question = $controller->question_model->getById($answer['question_id']);
            
            if ($question) {
                $correct_option = $controller->option_model->getCorrectOption($answer['question_id']);
                $selected_option = $answer['selected_option_id'] ? $controller->option_model->getById($answer['selected_option_id']) : null;
                
                $answer['question_content'] = $question['content'];
                $answer['difficulty_level'] = $question['difficulty_level'];
                $answer['script'] = $question['script'];
                $answer['options'] = $question['options'];
                $answer['correct_option'] = $correct_option;
                $answer['selected_option'] = $selected_option;
                $answer['is_correct'] = $selected_option && $correct_option && $selected_option['id'] == $correct_option['id'];
            }
        }

        $response = Response::success($user_exam, 'Result retrieved successfully');
        Response::send($response);
    }

    /**
     * GET /api/v1/users/exams/history
     * Get user's exam history
     */
    public static function getUserHistory() {
        $token = TokenManager::getTokenFromHeader();
        if (!$token) {
            $response = Response::unauthorized('No token provided');
            Response::send($response);
            return;
        }

        $decoded = TokenManager::verify($token);
        if (!$decoded) {
            $response = Response::unauthorized('Token invalid or expired');
            Response::send($response);
            return;
        }

        $user_id = $decoded['userId'];
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

        $controller = new self();
        $history = $controller->user_exam_model->getUserHistory($user_id, $limit, $offset);

        $response = Response::success($history, 'History retrieved successfully');
        Response::send($response);
    }

    /** 
     * DELETE /api/v1/user-exams/{user_exam_id}
     * Delete a user exam from history
     */
    public static function deleteUserExam($user_exam_id = null) {
        // Handle URL path parameter
        if ($user_exam_id === null) {
            $user_exam_id = $_GET['user_exam_id'] ?? null;
        }
        
        if (empty($user_exam_id)) {
            $response = Response::badRequest('missing_param', 'user_exam_id is required');
            Response::send($response);
            return;
        }

        $token = TokenManager::getTokenFromHeader();
        if (!$token) {
            $response = Response::unauthorized('No token provided');
            Response::send($response);
            return;
        }

        $decoded = TokenManager::verify($token);
        if (!$decoded) {
            $response = Response::unauthorized('Token invalid or expired');
            Response::send($response);
            return;
        }

        $user_id = $decoded['userId'];
        $user_exam_id = intval($user_exam_id);

        $controller = new self();
        $user_exam = $controller->user_exam_model->getById($user_exam_id);

        if (!$user_exam) {
            $response = Response::notFound('User exam not found');
            Response::send($response);
            return;
        }

        // Verify ownership
        if ((int)$user_exam['user_id'] !== (int)$user_id) {
            $response = Response::forbidden('Access denied');
            Response::send($response);
            return;
        }

        // Delete user exam
        $controller->user_exam_model->delete($user_exam_id);

        $response = Response::success(null, 'User exam deleted successfully');
        Response::send($response);
    }
    
    /**
     * POST /api/v1/user-exams/{user_exam_id}/pause
     */
    public static function pauseExam($user_exam_id = null) {
        if ($user_exam_id === null) {
            $user_exam_id = $_GET['user_exam_id'] ?? null;
        }

        if (empty($user_exam_id)) {
            $response = Response::badRequest('missing_param', 'user_exam_id is required');
            Response::send($response);
            return;
        }

        $token = TokenManager::getTokenFromHeader();
        if (!$token) {
            $response = Response::unauthorized('No token provided');
            Response::send($response);
            return;
        }
        $decoded = TokenManager::verify($token);
        if (!$decoded) {
            $response = Response::unauthorized('Token invalid or expired');
            Response::send($response);
            return;
        }
        $user_id = $decoded['userId'];

        $data = json_decode(file_get_contents("php://input"), true);
        $time_spent = $data['time_spent'] ?? 0;
        $answers = $data['answers'] ?? [];

        $user_exam_id = intval($user_exam_id);
        $controller = new self();

        $user_exam = $controller->user_exam_model->getById($user_exam_id);
        if (!$user_exam) {
            $response = Response::notFound('User exam not found');
            Response::send($response);
            return;
        }

        if ((int)$user_exam['user_id'] !== (int)$user_id) {
            $response = Response::forbidden('Access denied');
            Response::send($response);
            return;
        }

        if ($user_exam['submitted_at']) {
            $response = Response::conflict('exam_submitted', 'This exam has already been submitted');
            Response::send($response);
            return;
        }

        // Save answers if provided
        if (!empty($answers)) {
            $controller->user_answer_model->batchSave($user_exam_id, $answers);
        }

        $controller->user_exam_model->pause($user_exam_id, intval($time_spent));

        $updated = $controller->user_exam_model->getById($user_exam_id);
        $response = Response::success($updated, 'Exam paused successfully');
        Response::send($response);
    }

    private function calculatePerformanceLevel($correct_answers) {
        if ($correct_answers >= 27) return 'excellent'; // 27-35
        if ($correct_answers >= 20) return 'good';      // 20-26
        if ($correct_answers >= 12) return 'average';   // 12-19
        return 'needs_improvement'; // 0-11
    }
}
