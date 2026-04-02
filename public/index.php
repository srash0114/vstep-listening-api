<?php
// Main API Router

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

date_default_timezone_set('UTC');

ob_start();

// Load env.local
$envFile = __DIR__ . '/../env.local';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

$allowed_origins = array_filter(explode(',', getenv('ALLOWED_ORIGINS') ?: ''));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Config
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/token.php';
require_once __DIR__ . '/../config/cloudinary.php';
require_once __DIR__ . '/../config/upload.php';

// Models
require_once __DIR__ . '/../api/models/User.php';
require_once __DIR__ . '/../api/models/Result.php';
require_once __DIR__ . '/../api/models/Exam.php';
require_once __DIR__ . '/../api/models/Part.php';
require_once __DIR__ . '/../api/models/Passage.php';
require_once __DIR__ . '/../api/models/Question.php';
require_once __DIR__ . '/../api/models/Option.php';
require_once __DIR__ . '/../api/models/UserExam.php';
require_once __DIR__ . '/../api/models/UserAnswer.php';

// Controllers
require_once __DIR__ . '/../api/controllers/UserController.php';
require_once __DIR__ . '/../api/controllers/ResultController.php';
require_once __DIR__ . '/../api/controllers/ExamController.php';
require_once __DIR__ . '/../api/controllers/PartController.php';
require_once __DIR__ . '/../api/controllers/PassageController.php';
require_once __DIR__ . '/../api/controllers/QuestionController.php';
require_once __DIR__ . '/../api/controllers/OptionController.php';
require_once __DIR__ . '/../api/controllers/TestAccessController.php';
require_once __DIR__ . '/../api/controllers/AuthController.php';

// Parse request
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'];

$request_uri = str_replace('/public', '', $request_uri);
$request_uri = preg_replace('#^/+#', '/', $request_uri);

// Exact routes
$routes = [
    // Users / Auth
    'POST /api/users/register'        => ['UserController', 'register'],
    'GET /api/auth/google'            => ['AuthController', 'redirectToGoogle'],
    'GET /api/auth/google/callback'   => ['AuthController', 'handleGoogleCallback'],
    'POST /api/users/unlink-google'   => ['AuthController', 'unlinkGoogle'],
    'POST /api/users/login'           => ['UserController', 'login'],
    'POST /api/users/logout'          => ['UserController', 'logout'],
    'GET /api/users'                  => ['UserController', 'getById'],
    'GET /api/users/results'          => ['UserController', 'getUserResults'],
    'GET /api/users/check-status'     => ['UserController', 'checkStatus'],
    'PUT /api/users/profile'          => ['UserController', 'updateProfile'],
    'PUT /api/users/password'         => ['UserController', 'updatePassword'],

    // Results
    'POST /api/results'               => ['ResultController', 'submit'],
    'GET /api/results'                => ['ResultController', 'getAll'],
    'GET /api/results/detail'         => ['ResultController', 'get'],
    'GET /api/results/stats'          => ['ResultController', 'getStats'],

    // Public Exams
    'GET /api/v1/exams'               => ['ExamController', 'getAll'],

    // Test Access
    'GET /api/v1/users/exams/history' => ['TestAccessController', 'getUserHistory'],

    // Admin Exams
    'POST /api/v1/admin/exams'        => ['ExamController', 'create'],
];

// Dynamic routes (regex)
$dynamic_routes = [
    // Public
    '/^GET \/api\/v1\/exams\/(\d+)$/'                                                          => ['ExamController', 'getById'],
    '/^GET \/api\/v1\/exams\/(\d+)\/part\/(\d+)$/'                                            => ['ExamController', 'getPart'],
    '/^GET \/api\/v1\/exams\/(\d+)\/parts$/'                                                   => ['PartController', 'getByExamId'],

    // Test Access
    '/^POST \/api\/v1\/exams\/(\d+)\/start$/'                                                  => ['TestAccessController', 'startExam'],
    '/^POST \/api\/v1\/user-exams\/(\d+)\/answer$/'                                            => ['TestAccessController', 'saveAnswer'],
    '/^POST \/api\/v1\/user-exams\/(\d+)\/submit$/'                                            => ['TestAccessController', 'submitExam'],
    '/^POST \/api\/v1\/user-exams\/(\d+)\/pause$/'                                             => ['TestAccessController', 'pauseExam'],
    '/^GET \/api\/v1\/user-exams\/(\d+)\/result$/'                                             => ['TestAccessController', 'getResult'],
    '/^DELETE \/api\/v1\/user-exams\/(\d+)$/'                                                  => ['TestAccessController', 'deleteUserExam'],

    // Admin Exams
    '/^GET \/api\/v1\/admin\/exams\/(\d+)$/'                                                   => ['ExamController', 'getByIdAdmin'],
    '/^PUT \/api\/v1\/admin\/exams\/(\d+)$/'                                                   => ['ExamController', 'update'],
    '/^DELETE \/api\/v1\/admin\/exams\/(\d+)$/'                                                => ['ExamController', 'delete'],

    // Admin Parts
    '/^GET \/api\/v1\/admin\/exams\/(\d+)\/parts$/'                                            => ['PartController', 'getByExamId'],
    '/^GET \/api\/v1\/admin\/parts\/(\d+)$/'                                                   => ['PartController', 'getById'],
    '/^POST \/api\/v1\/admin\/exams\/(\d+)\/parts$/'                                           => ['PartController', 'create'],
    '/^PUT \/api\/v1\/admin\/parts\/(\d+)$/'                                                   => ['PartController', 'update'],
    '/^PUT \/api\/v1\/admin\/exams\/(\d+)\/parts\/(\d+)$/'                                    => ['PartController', 'updateNested'],
    '/^DELETE \/api\/v1\/admin\/parts\/(\d+)$/'                                                => ['PartController', 'delete'],
    '/^DELETE \/api\/v1\/admin\/exams\/(\d+)\/parts\/(\d+)$/'                                 => ['PartController', 'deleteNested'],
    '/^POST \/api\/v1\/admin\/parts\/(\d+)\/upload-audio$/'                                    => ['PartController', 'uploadAudio'],
    '/^POST \/api\/v1\/admin\/exams\/(\d+)\/parts\/(\d+)\/upload-audio$/'                     => ['PartController', 'uploadAudioNested'],

    // Admin Passages
    '/^GET \/api\/v1\/admin\/exams\/(\d+)\/parts\/(\d+)\/passages$/'                          => ['PassageController', 'getByPart'],
    '/^POST \/api\/v1\/admin\/exams\/(\d+)\/parts\/(\d+)\/passages$/'                         => ['PassageController', 'create'],
    '/^PUT \/api\/v1\/admin\/exams\/(\d+)\/parts\/(\d+)\/passages\/(\d+)$/'                   => ['PassageController', 'update'],
    '/^DELETE \/api\/v1\/admin\/exams\/(\d+)\/parts\/(\d+)\/passages\/(\d+)$/'                => ['PassageController', 'delete'],

    // Admin Questions
    '/^GET \/api\/v1\/admin\/exams\/(\d+)\/questions$/'                                        => ['QuestionController', 'getByExam'],
    '/^POST \/api\/v1\/admin\/exams\/(\d+)\/questions$/'                                       => ['QuestionController', 'create'],
    '/^PUT \/api\/v1\/admin\/exams\/(\d+)\/questions\/(\d+)$/'                                => ['QuestionController', 'update'],
    '/^DELETE \/api\/v1\/admin\/exams\/(\d+)\/questions\/(\d+)$/'                             => ['QuestionController', 'delete'],

    // Admin Options
    '/^GET \/api\/v1\/admin\/exams\/(\d+)\/questions\/(\d+)\/options$/'                       => ['OptionController', 'getByQuestion'],
    '/^POST \/api\/v1\/admin\/exams\/(\d+)\/questions\/(\d+)\/options$/'                      => ['OptionController', 'create'],
    '/^PUT \/api\/v1\/admin\/exams\/(\d+)\/questions\/(\d+)\/options\/(\d+)$/'                => ['OptionController', 'update'],
    '/^DELETE \/api\/v1\/admin\/exams\/(\d+)\/questions\/(\d+)\/options\/(\d+)$/'             => ['OptionController', 'delete'],
];

$matched = false;
$route_key = "$request_method $request_uri";

// Admin route protection
if (strpos($request_uri, '/admin/') !== false) {
    $adminDecoded = TokenManager::verifyAdmin();
    if (!$adminDecoded) {
        $response = Response::forbidden('Admin access required');
        Response::send($response);
    }
}

try {
    foreach ($routes as $pattern => $handler) {
        if ($pattern == $route_key) {
            $matched = true;
            call_user_func([$handler[0], $handler[1]]);
            break;
        }
    }

    if (!$matched) {
        foreach ($dynamic_routes as $pattern => $handler) {
            if (preg_match($pattern, $route_key, $matches)) {
                $matched = true;
                $GLOBALS['route_params'] = array_slice($matches, 1);
                if (count($GLOBALS['route_params']) > 0) {
                    call_user_func([$handler[0], $handler[1]], ...$GLOBALS['route_params']);
                } else {
                    call_user_func([$handler[0], $handler[1]]);
                }
                break;
            }
        }
    }

    if (!$matched) {
        $response = Response::notFound("Endpoint not found: $request_method $request_uri");
        Response::send($response);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $response = Response::serverError($e->getMessage());
    Response::send($response);
}

$output = ob_get_clean();
if (!empty($output)) {
    error_log("Unexpected output: " . $output);
}
?>
