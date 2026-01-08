<?php
session_start();
require_once __DIR__ . '/../app/config.php';

// initialize form values
$first_name = $last_name = $email = $department_id = $unit = $staff_id = '';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    $password_raw = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? '';
    $unit = $_POST['unit'] ?? '';

    // basic validation
    if ($first_name === '' || $last_name === '') {
        $error = "Please provide both first and last name.";
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif ($staff_id === '') {
        $error = "Please provide a Staff ID.";
    } elseif (!preg_match('/^[A-Za-z]{2}\/\d{2}\/\d{3}$/', $staff_id)) {
        $error = "Invalid Staff ID format. Example: CU/24/033";
    } elseif ($password_raw === '') {
        $error = "Please provide a password.";
    } elseif ($department_id === '' || $unit === '') {
        $error = "Please complete all required fields.";
    } else {
        $full_name = $first_name . ' ' . $last_name;
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            // Check if staff_id exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE staff_id = ?");
            $stmt->execute([$staff_id]);

            if ($stmt->fetch()) {
                $error = "Staff ID already registered.";
            } else {
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users 
                    (full_name, first_name, last_name, email, staff_id, password, department_id, unit, is_approved) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");

                $stmt->execute([
                    $full_name, 
                    $first_name, 
                    $last_name, 
                    $email, 
                    $staff_id, 
                    $password, 
                    $department_id, 
                    $unit
                ]);

                $success = "Registration successful! Your account is pending approval by your department admin.";

                // clear form values
                $first_name = $last_name = $email = $department_id = $unit = $staff_id = '';
            }
        }
    }
}

// Fetch departments for dropdown
$dept_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch units for dropdown
$unit_stmt = $pdo->query("SELECT id, name, department_id FROM units ORDER BY name ASC");
$units = $unit_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - KPI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function filterUnits() {
            const deptId = document.getElementById('department').value;
            const unitSelect = document.getElementById('unit');

            for (let option of unitSelect.options) {
                option.style.display =
                    option.getAttribute('data-dept') === deptId || option.value === ''
                    ? 'block' : 'none';
            }
            unitSelect.value = '';
        }

        window.addEventListener('DOMContentLoaded', filterUnits);
    </script>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card p-4">
                    <h3 class="text-center mb-3">Register</h3>

                    <?php if (!empty($error)) echo "<div class='alert alert-danger'>".htmlspecialchars($error)."</div>"; ?>
                    <?php if (!empty($success)) echo "<div class='alert alert-success'>".htmlspecialchars($success)."</div>"; ?>

                    <form method="POST">
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($first_name) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($last_name) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
                        </div>

                        <div class="mb-3">
                            <label>Staff ID (e.g., CU/24/033)</label>
                            <input type="text" name="staff_id" class="form-control" required value="<?= htmlspecialchars($staff_id) ?>">
                        </div>

                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Department</label>
                            <select id="department" name="department_id" class="form-select" required onchange="filterUnits()">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= ($department_id == $dept['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Unit</label>
                            <select id="unit" name="unit" class="form-select" required>
                                <option value="">-- Select Unit --</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>" data-dept="<?= $u['department_id'] ?>" <?= ($unit == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
                    </form>

                    <p class="mt-3 text-center">
                        Already have an account? <a href="login.php">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
