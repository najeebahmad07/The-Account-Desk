<?php
// File: config/database.php
// Database configuration for LIVE SERVER

// ============================================
// CHANGE THESE FOR YOUR LIVE SERVER
// ============================================
// define('DB_HOST', 'localhost');
// define('DB_USER', 'kkdental_njb');
// define('DB_PASS', 'Motihari@123');
// define('DB_NAME', 'kkdental_tad');
// define('DB_CHARSET', 'utf8mb4');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tad');
define('DB_CHARSET', 'utf8mb4');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("System is temporarily unavailable. Please try again later.");
    }

    $conn->set_charset(DB_CHARSET);
    return $conn;
}