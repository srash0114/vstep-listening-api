<?php
// API Response Handler
class Response {
    
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'statusCode' => $statusCode
        ];
    }

    public static function error($error, $message = 'Error', $statusCode = 400, $data = null) {
        http_response_code($statusCode);
        return [
            'success' => false,
            'error' => $error,
            'message' => $message,
            'statusCode' => $statusCode,
            'data' => $data
        ];
    }

    public static function send($response) {
        // Only set Content-Type if not already sent
        // CORS headers are set in index.php, don't override them here
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit();
    }

    public static function created($data, $message = 'Resource created') {
        return self::success($data, $message, 201);
    }

    public static function badRequest($error, $message, $data = null) {
        return self::error($error, $message, 400, $data);
    }

    public static function unauthorized($message = 'Unauthorized') {
        return self::error('unauthorized', $message, 401);
    }

    public static function notFound($message = 'Resource not found') {
        return self::error('not_found', $message, 404);
    }

    public static function serverError($message = 'Internal server error') {
        return self::error('server_error', $message, 500);
    }
}
?>
