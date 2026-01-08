<?php
session_start();
require_once __DIR__ . '/../app/config.php';

$token = $_GET['token'] ?? '';
$error = "";

// verify token
$stmt = $pdo->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || strtotime($user['reset_expires']) < time()) {
    die("<h3>Invalid or expired token.</h3>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = $_POST['password'] ?? '';

    if (strlen($newPass) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);

        // update password and clear token
        $update = $pdo->prepare("UPDATE users 
            SET password = ?, reset_token = NULL, reset_expires = NULL 
            WHERE id = ?");
        $update->execute([$hashed, $user['id']]);

        echo "<h3>Password updated successfully. <a href='login.php'>Login</a></h3>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Set New Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 mx-auto" style="max-width:400px;">
        <h4 class="text-center">Create New Password</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="password" name="password" class="form-control" placeholder="New password" required>
            <button class="btn btn-success w-100 mt-3">Reset Password</button>
        </form>
    </div>
</div>
</body>
</html>
