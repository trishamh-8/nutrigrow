<?php
// config.php - File konfigurasi database NutriGrow

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'nutrigrow_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Fungsi koneksi database
function getConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $conn->exec("SET NAMES utf8mb4");
        return $conn;
    } catch(PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

// Fungsi validasi email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Fungsi sanitize input
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk cek role user
function getUserRole($conn, $id_akun) {
    // Cek di tabel admin
    $stmt = $conn->prepare("SELECT 'admin' as role FROM admin WHERE id_akun = ?");
    $stmt->execute([$id_akun]);
    if ($stmt->fetch()) return 'admin';
    
    // Cek di tabel tenaga_kesehatan
    $stmt = $conn->prepare("SELECT 'tenaga_kesehatan' as role FROM tenaga_kesehatan WHERE id_akun = ?");
    $stmt->execute([$id_akun]);
    if ($stmt->fetch()) return 'tenaga_kesehatan';
    
    // Cek di tabel pengguna
    $stmt = $conn->prepare("SELECT 'pengguna' as role FROM pengguna WHERE id_akun = ?");
    $stmt->execute([$id_akun]);
    if ($stmt->fetch()) return 'pengguna';
    
    return 'pengguna'; // Default
}

// Fungsi untuk get user info lengkap
function getUserInfo($conn, $id_akun) {
    $stmt = $conn->prepare("SELECT * FROM akun WHERE id_akun = ?");
    $stmt->execute([$id_akun]);
    $user = $stmt->fetch();
    
    if ($user) {
        $user['role'] = getUserRole($conn, $id_akun);
    }
    
    return $user;
}
?>