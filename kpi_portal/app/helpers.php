<?php
// endpoints/_helpers.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

function admin_log($pdo, $admin_id, $action, $target_type = null, $target_id = null, $details = null) {
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$admin_id, $action, $target_type, $target_id, $details]);
}
