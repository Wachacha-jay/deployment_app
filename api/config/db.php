<?php
/**
 * Database Configuration
 * For Hostinger shared hosting
 */

// Load environment variables from .env file
function loadEnv() {
    $envPath = dirname(__DIR__) . '/.env';
    if (!file_exists($envPath)) {
        die('Error: .env file not found');
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $_ENV[$key] = $value;
    }
}

loadEnv();

// Database credentials
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? 'turi_arts_shop';
$DB_PORT = $_ENV['DB_PORT'] ?? 3306;

class Database {
    private $conn;
    private $host;
    private $user;
    private $pass;
    private $db;
    private $port;

    public function __construct() {
        global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;
        
        $this->host = $DB_HOST;
        $this->user = $DB_USER;
        $this->pass = $DB_PASS;
        $this->db = $DB_NAME;
        $this->port = $DB_PORT;
    }

    public function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db, $this->port);

        if ($this->conn->connect_error) {
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $this->conn->connect_error
            ]));
        }

        $this->conn->set_charset('utf8mb4');
        return $this->conn;
    }

    public function getConnection() {
        if (!$this->conn) {
            $this->connect();
        }
        return $this->conn;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Create global database instance
$database = new Database();
$mysqli = $database->connect();

?>
