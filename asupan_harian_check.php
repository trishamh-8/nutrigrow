<?php
// asupan_harian.php - File khusus untuk role pengguna (orang tua)
require_once 'config.php';
require_once 'auth.php';

// Require login dan role pengguna
requireRole('pengguna');

// Redirect jika user adalah tenaga kesehatan
$role = getCurrentUserRole();
if ($role === 'tenaga_kesehatan') {
    header('Location: nakes_asupan_harian.php');
    exit();
}

header('Location: asupan_harian.php');
?>