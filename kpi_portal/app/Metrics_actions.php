<?php
session_start();
require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Role validation
$role = $_SESSION['role'];
if ($role !== 'admin' && $role !== 'super_admin') {
    header("Location: staff_dashboard.php");
    exit();
}

// CSRF validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token. Please refresh and try again.");
}

$metric_id = $_POST['metric_id'] ?? null;
if (!$metric_id) {
    die("Invalid metric ID.");
}

// Determine action
if (isset($_POST['edit'])) {
    // Redirect to edit form
    header("Location: edit_metric.php?id=" . urlencode($metric_id));
    exit();
}

if (isset($_POST['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM metric WHERE id = ?");
        $stmt->execute([$metric_id]);
        $_SESSION['success_message'] = "Metric deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting metric: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// If none matched
header("Location: admin_dashboard.php");
exit();
?>
