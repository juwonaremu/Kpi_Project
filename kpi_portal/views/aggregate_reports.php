<?php
require_once 'config.php';

// ✅ Aggregate weekly KPIs into monthly reports
try {
    $query = "
        INSERT INTO reports (user_id, metric_id, month, year, total_value, created_at)
        SELECT 
            user_id, 
            metric_id, 
            month, 
            year, 
            SUM(value) AS total_value, 
            NOW()
        FROM weekly_kpis
        GROUP BY user_id, metric_id, month, year
        ON DUPLICATE KEY UPDATE 
            total_value = VALUES(total_value),
            created_at = NOW();
    ";

    $pdo->query($query);

    echo "✅ Reports table updated successfully.";
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
}
?>
