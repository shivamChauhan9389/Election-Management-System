<?php
// Load configuration
$config = [];
if (file_exists(__DIR__ . '/config_local.php')) {
    $config = require __DIR__ . '/config_local.php';
} else {
    $config = require __DIR__ . '/config.example.php';
}

// Database configuration
$dbConfig = $config['db'] ?? [];
$host = $dbConfig['host'] ?? 'localhost';
$username = $dbConfig['username'] ?? 'root';
$password = $dbConfig['password'] ?? '';
$database = $dbConfig['database'] ?? 'user_auth';
$charset = $dbConfig['charset'] ?? 'utf8';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset($charset);
?>
