<?php
// auth.php - File untuk fungsi-fungsi autentikasi

// Include konfigurasi database
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['id_akun']) && !empty($_SESSION['id_akun']);
}

/**
 * Require login untuk mengakses halaman
 * Redirect ke login jika belum login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Require role tertentu untuk mengakses halaman
 * @param string|array $allowedRoles role yang diizinkan
 */
function requireRole($allowedRoles) {
    requireLogin();
    
    $currentRole = getCurrentUserRole();
    $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    
    if (!in_array($currentRole, $allowed)) {
        // Redirect berdasarkan role
        switch ($currentRole) {
            case 'tenaga_kesehatan':
                header("Location: nakes_dashboard.php");
                break;
            case 'admin':
                header("Location: admin_dashboard.php");
                break;
            default:
                header("Location: dashboard.php");
        }
        exit();
    }
}

/**
 * Redirect ke halaman yang sesuai berdasarkan role untuk halaman asupan
 */
function redirectAsupanBasedOnRole() {
    $role = getCurrentUserRole();
    
    switch ($role) {
        case 'tenaga_kesehatan':
            header("Location: nakes_asupan_harian.php");
            break;
        case 'pengguna':
            header("Location: asupan_harian.php");
            break;
        default:
            header("Location: dashboard.php");
    }
    exit();
}

/**
 * Redirect ke halaman yang sesuai berdasarkan role untuk halaman rekomendasi
 */
function redirectRekomendasiBasedOnRole() {
    $role = getCurrentUserRole();
    
    switch ($role) {
        case 'tenaga_kesehatan':
            header("Location: nakes_rekomendasi_gizi.php");
            break;
        case 'pengguna':
            header("Location: rekomendasi_gizi.php");
            break;
        default:
            header("Location: dashboard.php");
    }
    exit();
}

/**
 * Cek role user yang sedang login
 * @return string role user (admin/tenaga_kesehatan/pengguna)
 */
function getCurrentUserRole() {
    if (!isLoggedIn()) return null;
    
    $conn = getDBConnection();
    return getUserRole($conn, $_SESSION['id_akun']);
}

/**
 * Mendapatkan koneksi database dengan mysqli
 * @return mysqli connection object
 */
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Cek apakah sebuah balita dimiliki oleh akun tertentu
 * @param mysqli $conn koneksi mysqli
 * @param int $id_akun id akun
 * @param int $id_balita id balita
 * @return bool true jika balita dimiliki oleh akun
 */
function ownsBalita($conn, $id_akun, $id_balita) {
    $query = "SELECT 1 FROM balita WHERE id_balita = ? AND id_akun = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) return false;
    $stmt->bind_param("ii", $id_balita, $id_akun);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Format tanggal ke format Indonesia
 * @param string $tanggal format Y-m-d
 * @return string format d Month Year dalam bahasa Indonesia
 */
function formatTanggalIndo($tanggal) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

/**
 * Kembalikan nama hari dalam bahasa Indonesia dari tanggal (YYYY-MM-DD)
 * Ditambahkan ke auth.php sehingga helper tersedia di seluruh halaman yang
 * melakukan require_once 'auth.php'.
 */
function formatHariIndo($date) {
    $days = [
        'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
    ];
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $w = (int) date('w', $ts); // 0 (Minggu) - 6 (Sabtu)
    return $days[$w];
}

/**
 * Sanitize input string
 * @param string $str input yang akan dibersihkan
 * @return string input yang sudah dibersihkan
 */
function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Validasi format tanggal Y-m-d
 * @param string $date tanggal yang akan divalidasi
 * @return bool true jika valid
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Generate random string untuk token
 * @param int $length panjang string yang diinginkan
 * @return string random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}
?>

<!-- Use external logout confirmation script for consistency across pages -->
<script src="assets/logout-confirm.js"></script>