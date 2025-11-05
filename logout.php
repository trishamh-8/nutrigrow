<?php
session_start();

// Hapus semua session
session_unset();
session_destroy();

// Hapus cookie jika ada
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
}

// Redirect ke halaman login
header('Location: login.php');
exit;
?>