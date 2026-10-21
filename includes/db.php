<?php
// Database configuration with .env support
require_once __DIR__ . '/env.php';

// Ensure consistent local timezone (Philippine Standard Time UTC+8)
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Manila'));

$host     = env('DB_HOST', 'localhost');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$database = env('DB_NAME', 'philippine_retail_system');

// Reuse connection if already established in $GLOBALS
if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli && @$GLOBALS['conn']->ping()) {
    $conn = $GLOBALS['conn'];
} else {
    // Create connection
    $conn = new mysqli($host, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");

    // Synchronize MySQL session timezone with Philippine Standard Time (+08:00)
    $conn->query("SET time_zone = '+08:00'");

    // Persist globally so it's always accessible in any scope
    $GLOBALS['conn'] = $conn;
}
