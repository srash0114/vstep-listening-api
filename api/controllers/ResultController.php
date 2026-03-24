<?php
// Result Controller
class ResultController {
    private $result;
    private $test;
    private $question;

    public function __construct() {
        $this->result = new Result();
        $this->test = new Test();
        $this->question = new Question();
    }

    public static function submit() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate required fields
        if (empty($data['testId']) || empty($data['answers']) || empty($data['timeSpent'])) {
            $response = Response::badRequest('missing_fields', 
                'testId, answers, and timeSpent are required');
            Response::send($response);
        }

        $controller = new self();
        $test = $controller->test->getById($data['testId']);

        if (!$test) {
            $response = Response::notFound('Test not found');
            Response::send($response);
        }

        // Get correct answers
        $correctAnswersMap = $controller->question->getCorrectAnswers($data['testId']);
        
        // Calculate score
        $correctCount = 0;
        $totalQuestions = count($correctAnswersMap);
        $detailedResults = [];

        foreach ($correctAnswersMap as $questionId => $correctAnswerIndex) {
            $userAnswerIndex = $data['answers'][$questionId] ?? null;
            $isCorrect = $userAnswerIndex == $correctAnswerIndex;

            if ($isCorrect) {
                $correctCount++;
            }

            $question = $controller->question->getById($questionId);
            $options = [$question['optionA'], $question['optionB'], $question['optionC'], $question['optionD']];

            $detailedResults[] = [
                'questionId' => $questionId,
                'question' => $question['question'],
                'userAnswer' => $userAnswerIndex,
                'userAnswerText' => $userAnswerIndex !== null ? $options[$userAnswerIndex] : null,
                'correctAnswer' => $correctAnswerIndex,
                'correctAnswerText' => $options[$correctAnswerIndex],
                'isCorrect' => $isCorrect
            ];
        }

        // Calculate percentage and performance level
        $percentage = ($totalQuestions > 0) ? round(($correctCount / $totalQuestions) * 100) : 0;
        $score = round(($percentage / 100) * 100, 2);

        // Determine performance level
        if ($percentage >= 80) {
            $performanceLevel = 'excellent';
        } elseif ($percentage >= 60) {
            $performanceLevel = 'good';
        } else {
            $performanceLevel = 'needsWork';
        }

        // Prepare result data
        $resultData = [
            'testId' => $data['testId'],
            'userId' => $data['userId'] ?? null,
            'totalQuestions' => $totalQuestions,
            'correctAnswers' => $correctCount,
            'score' => $score,
            'percentage' => $percentage,
            'timeSpent' => $data['timeSpent'],
            'answers' => $data['answers'],
            'performanceLevel' => $performanceLevel
        ];

        // Save result
        $resultId = $controller->result->create($resultData, $detailedResults);

        if (!$resultId) {
            $response = Response::serverError('Failed to save test result');
            Response::send($response);
        }

        // Prepare response
        $resultDetail = $controller->result->getById($resultId);
        $response = Response::success($resultDetail, 'Test submitted successfully', 201);
        Response::send($response);
    }

    public static function get() {
        if (empty($_GET['id'])) {
            $response = Response::badRequest('missing_fields', 'Result ID is required');
            Response::send($response);
        }

        $controller = new self();
        $result = $controller->result->getById($_GET['id']);

        if (!$result) {
            $response = Response::notFound('Result not found');
            Response::send($response);
        }

        $response = Response::success($result, 'Result retrieved');
        Response::send($response);
    }

    public static function getAll() {
        $page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = !empty($_GET['limit']) ? intval($_GET['limit']) : 20;

        $controller = new self();
        $results = $controller->result->getAll($page, $limit);

        $response = Response::success($results, 'Results retrieved');
        Response::send($response);
    }

    public static function getStats() {
        $testId = !empty($_GET['testId']) ? $_GET['testId'] : null;

        $controller = new self();
        $stats = $controller->result->getStats($testId);

        // Format performance breakdown
        $data = [
            'totalResults' => intval($stats['totalResults']),
            'averageScore' => round(floatval($stats['averageScore']), 2),
            'maxScore' => floatval($stats['maxScore']),
            'minScore' => floatval($stats['minScore']),
            'averageTimeSpent' => intval($stats['averageTimeSpent']),
            'performanceBreakdown' => [
                'excellent' => intval($stats['excellent']),
                'good' => intval($stats['good']),
                'needsWork' => intval($stats['needsWork'])
            ]
        ];

        $response = Response::success($data, 'Statistics retrieved');
        Response::send($response);
    }
}
?>
