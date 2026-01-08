<?php
$password = 'adminpass';
$hashed = '$2y$10$UxQbzIOsYRa/Brwk1ew8f.Pm7kifzqCT4bkIXgJNZRj8PF4WqHiyO';
if (password_verify($password, $hashed)) {
    echo "Password matches!";
} else {
    echo "Password does NOT match!";
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    echo "<br>New hash for verification: $newHash";
}
?>