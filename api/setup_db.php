<?php
// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Database connection
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'turi_arts_shop';
$port = getenv('DB_PORT') ?: 3306;

echo "Connecting to database...\n";
$mysqli = new mysqli($host, $user, $pass, '', $port);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

// Create database if not exists
echo "Creating database if not exists...\n";
$mysqli->query("CREATE DATABASE IF NOT EXISTS `$name`");
$mysqli->select_db($name);

// Read SQL file
echo "Reading schema file...\n";
$sql = file_get_contents(__DIR__ . '/database_schema.sql');

// Execute multi query
echo "Executing SQL commands...\n";
if ($mysqli->multi_query($sql)) {
    do {
        // store first result set
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        // print divider
        if ($mysqli->more_results()) {
            // echo "-----------------\n";
        }
    } while ($mysqli->next_result());
    echo "Database setup completed successfully!\n";
} else {
    echo "Error executing SQL: " . $mysqli->error . "\n";
}

$mysqli->close();
?>
