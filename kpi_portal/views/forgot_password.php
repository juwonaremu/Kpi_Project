<?php
session_start();
require_once __DIR__ . '/../app/config.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Email not found (SECURE: still show generic message)
        $message = "If this email exists, a reset link has been sent.";
    } else {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // save token
        $save = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $save->execute([$token, $expires, $email]);

        $resetLink = "http://localhost/kpi_portal/views/reset_password.php?token=$token";

        // In real deployment: send email
        // For now, just show link for testing
        $message = "Password reset link: <br><a href='$resetLink'>$resetLink</a>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card p-4 mx-auto" style="max-width:400px;">
        <h4 class="text-center">Reset Password</h4>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
            <button class="btn btn-primary w-100 mt-3">Send Reset Link</button>
        </form>
    </div>
</div>
</body>
</html>
