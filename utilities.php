<?php
// utilities.php - Common utility functions used across the application

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
 * Hitung usia dalam format tahun dan bulan
 * @param string $tanggal_lahir format Y-m-d
 * @return string usia dalam format "X tahun Y bulan" atau "Y bulan"
 */
function hitungUsia($tanggal_lahir) {
    $lahir = new DateTime($tanggal_lahir);
    $sekarang = new DateTime();
    $diff = $sekarang->diff($lahir);
    
    if ($diff->y > 0) {
        return $diff->y . " tahun " . $diff->m . " bulan";
    } else {
        return $diff->m . " bulan";
    }
}

/**
 * Hitung usia dalam bulan
 * @param string $tanggal_lahir format Y-m-d
 * @param string $tanggal_ukur format Y-m-d
 * @return int usia dalam bulan
 */
function hitungUsiaBulan($tanggal_lahir, $tanggal_ukur = null) {
    $lahir = new DateTime($tanggal_lahir);
    $ukur = $tanggal_ukur ? new DateTime($tanggal_ukur) : new DateTime();
    $diff = $ukur->diff($lahir);
    
    return ($diff->y * 12) + $diff->m + ($diff->d >= 15 ? 1 : 0);
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
 * Generate badge class berdasarkan status gizi
 * @param string $status status gizi
 * @return string nama class untuk badge
 */
function getStatusBadgeClass($status) {
    if (strpos($status, 'Baik') !== false || strpos($status, 'Normal') !== false) {
        return 'badge-success';
    } elseif (strpos($status, 'Kurang') !== false || strpos($status, 'Stunting') !== false) {
        return 'badge-warning';
    } else {
        return 'badge-danger';
    }
}
?>