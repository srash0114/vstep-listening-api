<?php
// User Controller
class UserController {
    private $user;
    private $result;

    public function __construct() {
        $this->user = new User();
        $this->result = new Result();
    }

    public static function register() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['full_name'])) {
            $response = Response::badRequest('missing_fields', 'username, email, password and full_name are required');
            Response::send($response);
        }

        $controller = new self();

        // Check if email exists
        if ($controller->user->getByEmail($data['email'])) {
            $response = Response::badRequest('email_exists', 'Email already registered');
            Response::send($response);
        }

        // Check if username exists
        if ($controller->user->getByUsername($data['username'])) {
            $response = Response::badRequest('username_exists', 'Username already taken');
            Response::send($response);
        }

        $user = $controller->user->create($data);

        if (!$user) {
            $response = Response::serverError('Failed to create user');
            Response::send($response);
        }

        $response = Response::created($user, 'User registered successfully');
        Response::send($response);
    }

    public static function login() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['password']) || (empty($data['email']) && empty($data['username']))) {
            $response = Response::badRequest('missing_fields', 'password and email or username are required');
            Response::send($response);
        }

        $controller = new self();

        $user = !empty($data['email'])
            ? $controller->user->getByEmail($data['email'])
            : $controller->user->getByUsername($data['username']);

        if (!$user || !$controller->user->verifyPassword($data['password'], $user['password_hash'])) {
            $response = Response::unauthorized('Invalid email/username or password');
            Response::send($response);
        }

        $controller->user->updateLastLogin($user['id']);

        // Generate token with HttpOnly cookie
        $token = TokenManager::generate($user['id'], $user['email'], $user['role'] ?? 'user', $user['username'], $user['full_name']);
        TokenManager::setCookie($token);

        // Return user WITHOUT token in response (stored in HttpOnly cookie)
        $userData = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'createdAt' => $user['created_at']
        ];

        $response = Response::success($userData, 'Login successful');
        Response::send($response);
    }
    
    public static function checkStatus() {
        error_log('[CHECKSTATUS] Starting...');
        
        // Get token from Authorization header or Cookie
        $token = TokenManager::getTokenFromHeader();
        error_log('[CHECKSTATUS] Token from header: ' . ($token ? 'YES' : 'NO'));
        
        // Debug: Check if token exists in cookie
        if (!$token && isset($_COOKIE['auth_token'])) {
            $rawCookie = $_COOKIE['auth_token'];
            error_log('[CHECKSTATUS] Raw cookie: ' . $rawCookie);
            error_log('[CHECKSTATUS] Raw cookie length: ' . strlen($rawCookie));
            
            // Try URL decode
            $token = urldecode($rawCookie);
            error_log('[CHECKSTATUS] After urldecode: ' . $token);
            error_log('[CHECKSTATUS] Decoded length: ' . strlen($token));
            error_log('[CHECKSTATUS] Are they equal: ' . ($rawCookie === $token ? 'NO' : 'YES'));
        }
        
        if (!$token) {
            error_log('[CHECKSTATUS] No token found');
            $response = Response::unauthorized('No token provided');
            Response::send($response);
        }
        
        // Verify token
        $decoded = TokenManager::verify($token);
        
        if (!$decoded) {
            $response = Response::unauthorized('Token invalid or expired');
            Response::send($response);
        }
        
        // Get user data
        $controller = new self();
        $user = $controller->user->getById($decoded['userId']);
        
        if (!$user) {
            $response = Response::unauthorized('User not found');
            Response::send($response);
        }
        
        // Return user status
        $userData = [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'email'        => $user['email'],
            'full_name'    => $user['full_name'],
            'isActive'     => $user['is_active'],
            'createdAt'    => $user['created_at'],
            'lastLogin'    => $user['last_login'],
            'role'         => $user['role'] ?? 'user',
            'has_password' => !empty($user['password_hash']),
            'has_google'   => !empty($user['google_id']),
            'avatar_url'   => $user['avatar_url'] ?? null
        ];
        
        $response = Response::success($userData, 'User is logged in');
        Response::send($response);
    }
    
    public static function updateProfile() {
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

        $data = json_decode(file_get_contents("php://input"), true);
        $update = [];

        if (isset($data['username'])) $update['username']  = trim($data['username']);
        if (isset($data['full_name'])) $update['full_name'] = trim($data['full_name']);

        if (empty($update)) {
            $response = Response::badRequest('missing_fields', 'username or full_name is required');
            Response::send($response);
            return;
        }

        $controller = new self();

        // Check username not taken by another user
        if (!empty($update['username'])) {
            $existing = $controller->user->getByUsername($update['username']);
            if ($existing && (int)$existing['id'] !== (int)$decoded['userId']) {
                $response = Response::conflict('username_taken', 'Username already taken');
                Response::send($response);
                return;
            }
        }

        $controller->user->updateProfile($decoded['userId'], $update);
        $user = $controller->user->getById($decoded['userId']);

        $response = Response::success([
            'id'        => $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
        ], 'Profile updated successfully');
        Response::send($response);
    }

    public static function updatePassword() {
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

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['new_password'])) {
            $response = Response::badRequest('missing_fields', 'new_password is required');
            Response::send($response);
            return;
        }

        $controller = new self();
        $user = $controller->user->findById($decoded['userId']);

        // If user has existing password, require current_password to verify
        if (!empty($user['password_hash'])) {
            if (empty($data['current_password'])) {
                $response = Response::badRequest('missing_fields', 'current_password is required');
                Response::send($response);
                return;
            }
            if (!$controller->user->verifyPassword($data['current_password'], $user['password_hash'])) {
                $response = Response::unauthorized('Current password is incorrect');
                Response::send($response);
                return;
            }
        }

        $controller->user->updatePassword($decoded['userId'], $data['new_password']);
        $response = Response::success(null, 'Password updated successfully');
        Response::send($response);
    }

    public static function logout() {
        // Clear HttpOnly cookie
        TokenManager::clearCookie();
        
        $response = Response::success(null, 'Logged out successfully');
        Response::send($response);
    }

    public static function getById() {
        if (empty($_GET['id'])) {
            $response = Response::badRequest('missing_fields', 'User ID is required');
            Response::send($response);
        }

        $controller = new self();
        $user = $controller->user->getById($_GET['id']);

        if (!$user) {
            $response = Response::notFound('User not found');
            Response::send($response);
        }

        $response = Response::success($user, 'User retrieved');
        Response::send($response);
    }

    public static function getUserResults() {
        if (empty($_GET['userId'])) {
            $response = Response::badRequest('missing_fields', 'User ID is required');
            Response::send($response);
        }

        $userId = intval($_GET['userId']);
        $page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = !empty($_GET['limit']) ? intval($_GET['limit']) : 10;

        $controller = new self();
        $user = $controller->user->getById($userId);

        if (!$user) {
            $response = Response::notFound('User not found');
            Response::send($response);
        }

        $results = $controller->result->getByUserId($userId, $page, $limit);

        // Calculate statistics
        $stats = ['totalTests' => count($results), 'averageScore' => 0, 'bestScore' => 0];

        if (count($results) > 0) {
            $scores = array_column($results, 'score');
            $stats['averageScore'] = round(array_sum($scores) / count($scores), 2);
            $stats['bestScore'] = max($scores);
        }

        $userData = array_merge($user, $stats);
        $userData['results'] = $results;

        $response = Response::success($userData, 'User results retrieved');
        Response::send($response);
    }
}
?>
