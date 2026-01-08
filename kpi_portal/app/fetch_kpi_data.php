<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year = date('Y');

$response = [
    'weekly' => [],
    'monthly' => []
];

if ($month > 0) {
    // Weekly KPIs
    $stmt = $pdo->prepare("
        SELECT w.week, w.value, w.year, w.month, m.name AS metric_name
        FROM weekly_kpis w
        JOIN metrics m ON w.metric_id = m.id
        WHERE w.user_id = ? AND w.month = ? AND w.year = ?
        ORDER BY w.week ASC
    ");
    $stmt->execute([$user_id, $month, $year]);
    $weekly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($weekly as &$row) {
        $row['month_name'] = date('F', mktime(0,0,0,$row['month'],1));
    }
    $response['weekly'] = $weekly;

    // Monthly summary
    $stmt2 = $pdo->prepare("
        SELECT m.name AS metric_name, r.total_value, r.year, r.month, m.target_value
        FROM reports r
        JOIN metrics m ON r.metric_id = m.id
        WHERE r.user_id = ? AND r.month = ? AND r.year = ?
    ");
    $stmt2->execute([$user_id, $month, $year]);
    $monthly = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($monthly as &$row) {
        $row['month_name'] = date('F', mktime(0,0,0,$row['month'],1));
    }
    $response['monthly'] = $monthly;
}

header('Content-Type: application/json');
echo json_encode($response);
