<?php
date_default_timezone_set('Asia/Manila');

// Try environment variables first (Coolify/Docker), then fall back to .env file (local)
$env_file_path = __DIR__ . '/../.env';

if (getenv('DB_HOST') !== false) {
    // Production: Read from environment variables set by Coolify
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
} elseif (file_exists($env_file_path)) {
    // Local development: Read from .env file
    $env = parse_ini_file($env_file_path);
    define('DB_HOST', $env['DB_HOST']);
    define('DB_USER', $env['DB_USER']);
    define('DB_PASS', $env['DB_PASS']);
    define('DB_NAME', $env['DB_NAME']);
    define('DB_PORT', $env['DB_PORT'] ?? 3306);
} else {
    // No configuration available
    error_log("ERROR: No database configuration found");
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Database configuration error']));
}

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Connect to DB using .env values
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");

// Set wait_timeout and interactive_timeout
$conn->query("SET session wait_timeout=28800");
$conn->query("SET session interactive_timeout=28800");
