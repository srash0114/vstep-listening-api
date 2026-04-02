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
        
        error_log('[TOKEN-GENERATE] Base64: ' . $base64Payload);
        error_log('[TOKEN-GENERATE] Signature: ' . $signature);
        
        return $base64Payload . '.' . $signature;
    }
    
    /**
     * Verify and decode token
     */
    public static function verify($token) {
        if (empty($token)) {
            error_log('[TOKEN] Empty token');
            return null;
        }
        
        error_log('[TOKEN] Full token: ' . $token);
        
        $parts = explode('.', $token);
        error_log('[TOKEN] Parts count: ' . count($parts));
        
        if (count($parts) !== 2) {
            error_log('[TOKEN] Invalid format: ' . count($parts) . ' parts');
            return null;
        }
        
        $payload = $parts[0];
        $signature = $parts[1];
        
        error_log('[TOKEN] Payload part: ' . $payload);
        error_log('[TOKEN] Signature part: ' . $signature);
        
        // Verify signature
        $expectedSig = self::sign($payload);
        error_log('[TOKEN] Expected sig: ' . $expectedSig);
        error_log('[TOKEN] Got sig: ' . $signature);
        error_log('[TOKEN] Match: ' . ($signature === $expectedSig ? 'YES' : 'NO'));
        
        if ($signature !== $expectedSig) {
            error_log('[TOKEN] Signature mismatch. Got: ' . $signature . ', Expected: ' . $expectedSig);
            return null;
        }
        
        // Decode payload
        $decoded = json_decode(base64_decode($payload), true);
        error_log('[TOKEN] Decoded: ' . json_encode($decoded));
        
        // Check expiry
        if ($decoded['expiresAt'] < time()) {
            error_log('[TOKEN] Expired: ' . $decoded['expiresAt'] . ' < ' . time());
            return null;
        }
        
        error_log('[TOKEN] Valid token for user: ' . $decoded['userId']);
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
