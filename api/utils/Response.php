<?php
/**
 * API Response Handler
 */

class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function success($message = '', $data = null, $statusCode = 200) {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error($message = '', $data = null, $statusCode = 400) {
        self::json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function created($message = '', $data = null) {
        self::success($message, $data, 201);
    }

    public static function notFound($message = 'Resource not found') {
        self::error($message, null, 404);
    }

    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, null, 401);
    }

    public static function forbidden($message = 'Forbidden') {
        self::error($message, null, 403);
    }

    public static function validationError($errors) {
        self::error('Validation failed', $errors, 422);
    }
}

?>
