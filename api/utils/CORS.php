<?php
/**
 * CORS Headers Handler
 */

class CORS {
    public static function handle() {
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:8080',
            'http://localhost:3000',
            'http://localhost',
            'https://yourdomain.com',
            'https://www.yourdomain.com'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}

?>
