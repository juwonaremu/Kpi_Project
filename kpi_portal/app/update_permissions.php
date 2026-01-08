<?php
session_start();
require_once 'config.php';

// Ensure only super admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $user_id = $_POST['user_id'];
    $can_add_metrics = isset($_POST['can_add_metrics']) ? 1 : 0;
    $can_delete_metrics = isset($_POST['can_delete_metrics']) ? 1 : 0;
    $can_manage_users = isset($_POST['can_manage_users']) ? 1 : 0;
    $can_edit_kpis = isset($_POST['can_edit_kpis']) ? 1 : 0;

    // Check if user already has a permission record
    $check = $pdo->prepare("SELECT id FROM user_permissions WHERE user_id = ?");
    $check->execute([$user_id]);

    if ($check->fetch()) {
        // Update existing
        $stmt = $pdo->prepare("
            UPDATE user_permissions 
            SET can_add_metrics=?, can_delete_metrics=?, can_manage_users=?, can_edit_kpis=?
            WHERE user_id=?
        ");
        $stmt->execute([$can_add_metrics, $can_delete_metrics, $can_manage_users, $can_edit_kpis, $user_id]);
        $success = "Permissions updated successfully.";
    } else {
        // Insert new
        $stmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, can_add_metrics, can_delete_metrics, can_manage_users, can_edit_kpis)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $can_add_metrics, $can_delete_metrics, $can_manage_users, $can_edit_kpis]);
        $success = "Permissions assigned successfully.";
    }
}

// Fetch all non-super-admin users
$user_stmt = $pdo->query("SELECT id, full_name, email, role FROM users WHERE role != 'super_admin' ORDER BY full_name ASC");
$users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch permissions for each user
$perm_stmt = $pdo->query("SELECT * FROM user_permissions");
$permissions = [];
foreach ($perm_stmt->fetchAll(PDO::FETCH_ASSOC) as $perm) {
    $permissions[$perm['user_id']] = $perm;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Permissions - KPI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 900px;
            margin-top: 50px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #004085;
            color: white;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-4 text-center text-primary fw-bold">Manage User Permissions</h3>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label class="form-label">Select User</label>
                <select name="user_id" class="form-select" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label d-block">Assign Permissions</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="can_add_metrics" id="add">
                    <label class="form-check-label" for="add">Add Metrics</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="can_delete_metrics" id="delete">
                    <label class="form-check-label" for="delete">Delete Metrics</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="can_manage_users" id="manage">
                    <label class="form-check-label" for="manage">Manage Users</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="can_edit_kpis" id="edit">
                    <label class="form-check-label" for="edit">Edit KPIs</label>
                </div>
            </div>

            <button type="submit" name="update_permissions" class="btn btn-primary w-100">Save Permissions</button>
        </form>

        <hr>
        <h5 class="text-secondary mb-3">Current User Permissions</h5>
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Add Metrics</th>
                    <th>Delete Metrics</th>
                    <th>Manage Users</th>
                    <th>Edit KPIs</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $perm = $permissions[$u['id']] ?? ['can_add_metrics'=>0, 'can_delete_metrics'=>0, 'can_manage_users'=>0, 'can_edit_kpis'=>0]; ?>
                    <tr>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><?= $perm['can_add_metrics'] ? '✅' : '❌' ?></td>
                        <td><?= $perm['can_delete_metrics'] ? '✅' : '❌' ?></td>
                        <td><?= $perm['can_manage_users'] ? '✅' : '❌' ?></td>
                        <td><?= $perm['can_edit_kpis'] ? '✅' : '❌' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="text-center mt-4">
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
