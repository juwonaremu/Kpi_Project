<?php
// reset_password.php
require_once 'config.php';

$token = $_GET['token'] ?? null;
$valid = false;
$message = null;

if ($token) {
    $stmt = $pdo->prepare("SELECT pr.id, pr.user_id, pr.expires_at, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && strtotime($row['expires_at']) > time()) {
        $valid = true;
        $prid = $row['id'];
        $user_id = $row['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if ($password === '' || $password !== $confirm) {
                $message = "Passwords must match and not be empty.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
                    // delete all tokens for this user (or just this token)
                    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user_id]);
                    $pdo->commit();
                    $message = "Password updated. You can now login.";
                    $valid = false;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Failed to update password.";
                }
            }
        }
    } else {
        $message = "Invalid or expired token.";
    }
} else {
    $message = "Invalid request.";
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Reset Password</title></head>
<body>
    <div style="max-width:500px;margin:40px auto">
        <h2>Reset Password</h2>
        <?php if ($message) echo "<p>{$message}</p>"; ?>

        <?php if ($valid): ?>
            <form method="POST">
                <div><label>New password</label><br><input type="password" name="password" required></div>
                <div><label>Confirm password</label><br><input type="password" name="confirm_password" required></div>
                <div style="margin-top:10px;"><button type="submit">Set password</button></div>
            </form>
        <?php else: ?>
            <p><a href="login.php">Return to login</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
