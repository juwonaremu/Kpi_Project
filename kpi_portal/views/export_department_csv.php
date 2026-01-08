<?php
session_start();
require_once __DIR__ . '/../app/config.php';

$dept_id = $_SESSION['department_id'];
$month = $_POST['month'];
$year = $_POST['year'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=department_summary_' . $month . '_' . $year . '.csv');

$output = fopen("php://output", "w");

// column headers
fputcsv($output, ['Metric', 'Total Score', 'Target']);

$stmt = $pdo->prepare("
    SELECT 
        m.name AS metric_name,
        SUM(r.total_value) AS total_score,
        m.target_value
    FROM reports r
    INNER JOIN metrics m ON r.metric_id = m.id
    INNER JOIN users u ON r.user_id = u.id
    WHERE u.department_id = ?
      AND r.month = ?
      AND r.year = ?
    GROUP BY m.id
    ORDER BY m.name ASC
");

$stmt->execute([$dept_id, $month, $year]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
