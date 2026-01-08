<?php
require_once 'config.php';
$password = 'staffpassword';
$hash = '$2y$10$Z8e9q1w3r5t7y9u2i4o6p.k8j0l2m4n6q8r0s2t4u6v8w0x2y4z6';
if (verifyPassword($password, $hash)) {
    echo 'Password verified!';
} else {
    echo 'Password verification failed.';
}
?>