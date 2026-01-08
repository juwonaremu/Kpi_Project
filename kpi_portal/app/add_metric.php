<?php
session_start();
require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only admin or super_admin can access
$role = $_SESSION['role'];
if ($role !== 'admin' && $role !== 'super_admin') {
    header("Location: staff_dashboard.php");
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fetch departments for dropdown (super_admin only)
$departments = [];
if ($role === 'super_admin') {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    $metric_name = trim($_POST['metric_name']);
    $target_value = trim($_POST['target_value']);
    $department_id = ($role === 'super_admin') ? $_POST['department_id'] : $_SESSION['department_id'];

    if (empty($metric_name) || empty($target_value)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO metrics (name, target_value, department_id) VALUES (?, ?, ?)");
            $stmt->execute([$metric_name, $target_value, $department_id]);
            $_SESSION['success_message'] = "Metric added successfully.";
            header("Location: admin_dashboard.php");
            exit();
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add KPI Metric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Add New KPI Metric</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="mb-3">
                    <label for="metric_name" class="form-label">Metric Name</label>
                    <input type="text" class="form-control" name="metric_name" id="metric_name" required>
                </div>

                <div class="mb-3">
                    <label for="target_value" class="form-label">Target Value</label>
                    <input type="number" step="0.01" class="form-control" name="target_value" id="target_value" required>
                </div>

                <?php if ($role === 'super_admin'): ?>
                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select name="department_id" id="department_id" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between">
                    <a href="admin_dashboard.php" class="btn btn-secondary">Back</a>
                    <button type="submit" class="btn btn-success">Save Metric</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
