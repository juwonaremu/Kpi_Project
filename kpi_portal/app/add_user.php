<?php
// endpoints/add_user.php
require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_dashboard.php');
    exit();
}

if (!csrf_validate()) {
    $_SESSION['error'] = "Invalid CSRF token.";
    header('Location: ../admin_dashboard.php');
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'staff';
$admin_id = $_SESSION['user_id'];
$admin_dept = $_SESSION['department_id'] ?? null;

if ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Valid full name and email required.";
    header('Location: ../admin_dashboard.php');
    exit();
}

// check exist
$chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$chk->execute([$email]);
if ($chk->fetch()) {
    $_SESSION['error'] = "Email already exists.";
    header('Location: ../admin_dashboard.php');
    exit();
}

// create user with temporary reset token flow rather than showing password
$random_pw = bin2hex(random_bytes(6)); // 12 hex chars — just for initial hash if you want to create a random pw
$hash = password_hash($random_pw, PASSWORD_DEFAULT);

// insert user
$ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, department_id) VALUES (?, ?, ?, ?, ?)");
$ok = $ins->execute([$full_name, $email, $hash, $role, $admin_dept]);

if (!$ok) {
    $_SESSION['error'] = "Failed to add user.";
    header('Location: ../admin_dashboard.php');
    exit();
}

$new_user_id = $pdo->lastInsertId();

// create a password reset token for the user and email it (so they set their own password)
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
$pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")->execute([$new_user_id, $token, $expires]);

// log action
admin_log($pdo, $admin_id, 'add_user', 'user', $new_user_id, "Added user {$full_name} ({$email}), created reset token.");

// Try to email reset link (optional)
$resetUrl = "https://your-domain.example/reset_password.php?token=" . urlencode($token);
$emailed = false;
if (file_exists(__DIR__ . '/../includes/mail_helper.php')) {
    require_once __DIR__ . '/../includes/mail_helper.php';
    $emailed = send_password_reset_email($email, $full_name, $token);
}

if ($emailed) {
    $_SESSION['message'] = "User added. A password set link has been sent to the user.";
} else {
    // For dev fallback only: show the one-time link to admin (do NOT do this in production)
    $_SESSION['message'] = "User added. Password set link (dev only): {$resetUrl}";
}

header('Location: ../admin_dashboard.php');
exit();
