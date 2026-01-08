<?php
require_once 'config.php'; // Assuming your config code is in config.php

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database: " . DB_NAME;
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>