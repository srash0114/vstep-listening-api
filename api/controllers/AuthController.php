<?php
class AuthController {

    /**
     * GET /api/auth/google?action=link
     * Redirect to Google. If action=link, passes current user_id in state.
     */
    public static function redirectToGoogle() {
        $action = $_GET['action'] ?? 'login';
        $state  = 'login';

        if ($action === 'link') {
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
            $state = 'link:' . $decoded['userId'];
        }

        $params = http_build_query([
            'client_id'     => getenv('GOOGLE_CLIENT_ID'),
            'redirect_uri'  => getenv('GOOGLE_REDIRECT_URI'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'state'         => $state,
        ]);
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
        exit;
    }

    /**
     * GET /api/auth/google/callback
     */
    public static function handleGoogleCallback() {
        $code  = $_GET['code']  ?? null;
        $state = $_GET['state'] ?? 'login';

        if (!$code) {
            $response = Response::badRequest('missing_code', 'No authorization code provided');
            Response::send($response);
            return;
        }

        $tokenRes = self::httpPost('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => getenv('GOOGLE_CLIENT_ID'),
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
            'redirect_uri'  => getenv('GOOGLE_REDIRECT_URI'),
            'grant_type'    => 'authorization_code',
        ]);

        if (!$tokenRes || empty($tokenRes['access_token'])) {
            $response = Response::serverError('Failed to exchange code for token');
            Response::send($response);
            return;
        }

        $googleUser = self::httpGet(
            'https://www.googleapis.com/oauth2/v3/userinfo',
            $tokenRes['access_token']
        );

        if (!$googleUser || empty($googleUser['sub'])) {
            $response = Response::serverError('Failed to get user info from Google');
            Response::send($response);
            return;
        }

        $frontend   = rtrim(getenv('FRONTEND_URL') ?: 'http://localhost:3000', '/');
        $user_model = new User();

        // --- Link flow ---
        if (str_starts_with($state, 'link:')) {
            $user_id = intval(substr($state, 5));

            // Check google_id not already used by another account
            $existing = $user_model->findByGoogleId($googleUser['sub']);
            if ($existing && (int)$existing['id'] !== $user_id) {
                header('Location: ' . $frontend . '/profile?error=google_already_linked');
                exit;
            }

            $avatar = $googleUser['picture'] ?? null;
            $user_model->linkGoogleId($user_id, $googleUser['sub'], $googleUser['email'], $avatar);
            header('Location: ' . $frontend . '/profile?google_linked=1');
            exit;
        }

        // --- Login flow ---
        $user = $user_model->findByGoogleId($googleUser['sub']);

        $googleAvatar = $googleUser['picture'] ?? null;

        if (!$user) {
            $user = $user_model->getByEmail($googleUser['email']);
            if ($user) {
                $user_model->linkGoogleId($user['id'], $googleUser['sub'], $googleUser['email'], $googleAvatar);
            } else {
                $userId = $user_model->createFromGoogle([
                    'google_id'  => $googleUser['sub'],
                    'email'      => $googleUser['email'],
                    'full_name'  => $googleUser['name'] ?? '',
                    'avatar_url' => $googleAvatar ?? '',
                ]);
                if (!$userId) {
                    $response = Response::serverError('Failed to create user');
                    Response::send($response);
                    return;
                }
                $user = $user_model->findByGoogleId($googleUser['sub']);
            }
        } elseif ($googleAvatar && $googleAvatar !== $user['avatar_url']) {
            $user_model->updateAvatar($user['id'], $googleAvatar);
        }

        $user_model->updateLastLogin($user['id']);
        $token = TokenManager::generate($user['id'], $user['email'], $user['role'] ?? 'user', $user['username'], $user['full_name']);
        TokenManager::setCookie($token);

        // Pass token in URL for frontend to store (works across different domains)
        $redirectUrl = $frontend . '?token=' . urlencode($token);
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * POST /api/users/unlink-google
     * Remove Google link from current account
     */
    public static function unlinkGoogle() {
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

        $user_model = new User();
        $user = $user_model->findById($decoded['userId']);

        if (!$user || !$user['google_id']) {
            $response = Response::badRequest('not_linked', 'No Google account linked');
            Response::send($response);
            return;
        }

        // Must have username if unlinking (used for login if no Google)
        if (empty($user['username'])) {
            $response = Response::badRequest('no_username', 'Username is required before unlinking Google');
            Response::send($response);
            return;
        }

        // Must have password if unlinking (can't lock themselves out)
        if (empty($user['password_hash'])) {
            $response = Response::badRequest('no_password', 'Set a password before unlinking Google');
            Response::send($response);
            return;
        }

        $user_model->unlinkGoogleId($decoded['userId']);
        $response = Response::success(null, 'Google account unlinked');
        Response::send($response);
    }

    private static function httpPost($url, $data) {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res ? json_decode($res, true) : null;
    }

    private static function httpGet($url, $access_token) {
        $ctx = stream_context_create(['http' => [
            'header' => 'Authorization: Bearer ' . $access_token,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res ? json_decode($res, true) : null;
    }
}
