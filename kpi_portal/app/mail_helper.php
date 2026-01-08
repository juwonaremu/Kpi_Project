<?php
// includes/mail_helper.php
// Optional helper using PHPMailer. Requires composer install: composer require phpmailer/phpmailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // adjust path if needed

function send_password_reset_email($to_email, $to_name, $token) {
    $resetLink = "https://your-domain.example/reset_password.php?token=" . urlencode($token);

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-smtp-username';
        $mail->Password = 'your-smtp-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('no-reply@your-domain.example', 'KPI Portal');
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password reset for KPI Portal';
        $mail->Body = "Hello " . htmlspecialchars($to_name) . ",<br><br>"
            . "An admin requested a password reset for your account. Click the secure link below to set a new password (link expires in 24 hours):<br>"
            . "<a href='{$resetLink}'>Reset your password</a><br><br>"
            . "If you did not request this, ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // You may log $mail->ErrorInfo
        return false;
    }
}
