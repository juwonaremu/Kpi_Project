<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Update if you set a MySQL password
define('DB_NAME', 'kpi_portal');

// Debug: Verify constant
if (!defined('DB_NAME')) {
    die("Error: DB_NAME constant is not defined!");
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optional: Confirm connection
    // echo "Connected to database: " . DB_NAME;
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>