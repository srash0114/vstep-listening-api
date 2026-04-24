<?php
// Token Management Class
class TokenManager {
    private static $secret = 'your_secret_key_change_this';
    private static $expiry = 86400; // 24 hours
    
    /**
     * Generate a simple token
     */
    public static function generate($userId, $email, $role = 'user', $username = null, $full_name = null) {
        $payload = [
            'userId'   => $userId,
            'email'    => $email,
            'role'     => $role,
            'username' => $username,
            'full_name' => $full_name,
            'createdAt' => time(),
            'expiresAt' => time() + self::$expiry,
        ];
        
        // Base64 encode the JSON payload first
        $base64Payload = base64_encode(json_encode($payload));
        
        // Sign the base64 payload (same as verify() will do)
        $signature = self::sign($base64Payload);
        
        return $base64Payload . '.' . $signature;
    }
    
    /**
     * Verify and decode token
     */
    public static function verify($token) {
        if (empty($token)) {
            return null;
        }
        
        $parts = explode('.', $token);
        
        if (count($parts) !== 2) {
            return null;
        }
        
        $payload = $parts[0];
        $signature = $parts[1];
        
        // Verify signature
        $expectedSig = self::sign($payload);
        
        if ($signature !== $expectedSig) {
            return null;
        }
        
        // Decode payload
        $decoded = json_decode(base64_decode($payload), true);
        
        // Check expiry
        if ($decoded['expiresAt'] < time()) {
            return null;
        }
        
        return $decoded;
    }
    
    /**
     * Generate signature
     */
    private static function sign($payload) {
        return hash_hmac('sha256', $payload, self::$secret);
    }
    
    /**
     * Verify token and check admin role
     */
    public static function verifyAdmin() {
        $token = self::getTokenFromHeader();
        if (!$token) return null;

        $decoded = self::verify($token);
        if (!$decoded) return null;

        if (($decoded['role'] ?? 'user') !== 'admin') return null;

        return $decoded;
    }

    /**
     * Extract token from Authorization header or Cookie
     */
    public static function getTokenFromHeader() {
        // Try Authorization header first
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        // Fall back to cookie
        if (isset($_COOKIE['auth_token'])) {
            return $_COOKIE['auth_token'];
        }
        
        return null;
    }
    
    /**
     * Set token as HttpOnly cookie
     */
    public static function setCookie($token) {
        // Railway terminates SSL at proxy level — check X-Forwarded-Proto
        $isSecure = !empty($_SERVER['HTTPS']) ||
                    ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        setcookie(
            'auth_token',
            $token,
            [
                'expires'  => time() + self::$expiry,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => $isSecure ? 'None' : 'Lax'
            ]
        );
    }
    
    /**
     * Clear auth cookie
     */
    public static function clearCookie() {
        $isSecure = !empty($_SERVER['HTTPS']) ||
                    ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        setcookie(
            'auth_token',
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => $isSecure ? 'None' : 'Lax'
            ]
        );
    }
}
?>
