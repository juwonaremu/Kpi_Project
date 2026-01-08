<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json'); // ✅ JSON response

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!empty($csrf_token) && hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(["status" => "success", "message" => "User approved successfully."]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid CSRF token or missing data."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>
