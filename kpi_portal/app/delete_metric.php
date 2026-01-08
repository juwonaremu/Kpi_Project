<?php
// endpoints/delete_metric.php
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

$metric_id = intval($_POST['metric_id'] ?? 0);
$admin_id = $_SESSION['user_id'];
$admin_dept = $_SESSION['department_id'] ?? null;

if ($metric_id <= 0) {
    $_SESSION['error'] = "Invalid metric.";
    header('Location: ../admin_dashboard.php');
    exit();
}

// verify ownership
$chk = $pdo->prepare("SELECT id, name FROM metrics WHERE id = ? AND department_id = ?");
$chk->execute([$metric_id, $admin_dept]);
$row = $chk->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $_SESSION['error'] = "Metric not found in your department.";
    header('Location: ../admin_dashboard.php');
    exit();
}

// delete metric and its weekly entries (or adjust logic if you want soft-delete)
$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM weekly_kpis WHERE metric_id = ?")->execute([$metric_id]);
    $pdo->prepare("DELETE FROM metrics WHERE id = ?")->execute([$metric_id]);
    admin_log($pdo, $admin_id, 'delete_metric', 'metric', $metric_id, "Deleted metric '{$row['name']}' and related weekly entries");
    $pdo->commit();
    $_SESSION['message'] = "Metric deleted.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to delete metric.";
}

header('Location: ../admin_dashboard.php');
exit();
