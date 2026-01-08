<?php
session_start();
require_once 'config.php';

// Ensure only super_admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// CSRF token validation
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("CSRF validation failed.");
}

$user_id = $_POST['user_id'] ?? null;

if ($user_id) {
    $stmt = $pdo->prepare("UPDATE users SET can_approve_users = NOT can_approve_users WHERE id = ?");
    $stmt->execute([$user_id]);
}

header('Location: admin_dashboard.php');
exit();
?>
