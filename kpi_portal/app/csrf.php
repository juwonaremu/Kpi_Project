<?php
// includes/csrf.php
if (session_status() === PHP_SESSION_NONE) session_start();

function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_input_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'utf-8');
    return "<input type='hidden' name='_csrf' value='{$token}'>";
}

function csrf_validate() {
    if (!isset($_POST['_csrf']) || !isset($_SESSION['_csrf_token'])) {
        return false;
    }
    $valid = hash_equals($_SESSION['_csrf_token'], $_POST['_csrf']);
    // regenerate token after a successful validation to prevent reuse
    if ($valid) {
        unset($_SESSION['_csrf_token']);
    }
    return $valid;
}
