<?php
// endpoints/delete_user.php
require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_dashboard.php');
    exit();
}

if (!csrf_validate()) {
    $_SESSION['error'] = "Invalid CSRF token.";
    header('Location: ../admin_dashboard.php');
    exit();
}

$user_id = intval($_POST['user_id'] ?? 0);
$admin_id = $_SESSION['user_id'];
$admin_dept = $_SESSION['department_id'] ?? null;

if ($user_id <= 0) {
    $_SESSION['error'] = "Invalid user.";
    header('Location: ../admin_dashboard.php');
    exit();
}

if ($user_id === $admin_id) {
    $_SESSION['error'] = "You cannot delete your own account.";
    header('Location: ../admin_dashboard.php');
    exit();
}

// ensure user in admin dept
$chk = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND department_id = ?");
$chk->execute([$user_id, $admin_dept]);
$row = $chk->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $_SESSION['error'] = "User not found in your department.";
    header('Location: ../admin_dashboard.php');
    exit();
}

// delete
$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM weekly_kpis WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    admin_log($pdo, $admin_id, 'delete_user', 'user', $user_id, "Deleted user {$row['full_name']}");
    $pdo->commit();
    $_SESSION['message'] = "User deleted.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to delete user.";
}
header('Location: ../admin_dashboard.php');
exit();
