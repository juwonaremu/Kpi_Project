<?php
// ensure cookie is valid for the project root so it is sent to /kpi_portal/views/...
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/kpi_portal',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
require_once __DIR__ . '/../app/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // fetch user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        // pending approval check
        if (isset($user['is_approved']) && $user['is_approved'] == 0) {
            $error = "Your account is pending approval by your department admin.";
        } else {
            // successful login — set session
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)($user['id'] ?? 0);
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['role'] = $user['role'] ?? '';
            $_SESSION['department_id'] = $user['department_id'] ?? null;
            $_SESSION['full_name'] = $user['full_name'] ?? '';

            // debug: write session to php error log (remove in production)
            error_log('login: session after auth = ' . print_r($_SESSION, true));

            // compute absolute redirect and log it for debugging
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $projectRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // -> /kpi_portal
            if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') {
                $target = $proto . '://' . $host . $projectRoot . '/views/admin_dashboard.php';
            } else {
                $target = $proto . '://' . $host . $projectRoot . '/views/staff_dashboard.php';
            }
            error_log('LOGIN redirect to: ' . $target . ' session_id=' . session_id());
            header('Location: ' . $target);
            exit();
        }

    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - KPI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, Helvetica, sans-serif;
        }
        .login-container {
            background-color: white;
            padding: 2rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .form-control {
            margin-bottom: 1rem;
        }
        .btn-primary {
            width: 100%;
            margin-top: 1rem;
        }
        .error {
            color: #dc3545;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>KPI Portal Login</h2>

        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>

        <form method="POST">
            <input type="email" name="email" class="form-control" placeholder="Email" required>
            <input type="password" name="password" class="form-control" placeholder="Password" required>
            <button type="submit" name="login" class="btn btn-primary">Login</button>
        </form>

        <p class="mt-3">Don't have an account? <a href="register.php">Register</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>