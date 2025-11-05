<?php
// zscore_calculator.php - Library untuk Perhitungan Z-Score
// Berdasarkan WHO Child Growth Standards

/**
 * Menghitung Z-Score berdasarkan metode LMS (Lambda-Mu-Sigma)
 * Formula: Z = [(X/M)^L - 1] / (L * S)
 * 
 * @param float $value Nilai aktual (berat atau tinggi)
 * @param float $L Lambda (Box-Cox transformation)
 * @param float $M Median (nilai tengah)
 * @param float $S Coefficient of variation
 * @return float Z-Score
 */
function hitungZScore($value, $L, $M, $S) {
    if ($L == 0) {
        // Jika L = 0, gunakan formula alternatif
        return log($value / $M) / $S;
    }
    
    $zscore = (pow($value / $M, $L) - 1) / ($L * $S);
    return round($zscore, 2);
}

/**
 * Menghitung usia dalam bulan berdasarkan tanggal lahir dan tanggal ukur
 * 
 * @param string $tanggal_lahir Format: Y-m-d
 * @param string $tanggal_ukur Format: Y-m-d
 * @return int Usia dalam bulan
 */
function hitungUsiaBulan($tanggal_lahir, $tanggal_ukur) {
    $lahir = new DateTime($tanggal_lahir);
    $ukur = new DateTime($tanggal_ukur);
    $diff = $lahir->diff($ukur);
    
    $bulan = ($diff->y * 12) + $diff->m;
    
    // Tambah 1 bulan jika hari sudah lebih dari 15
    if ($diff->d >= 15) {
        $bulan++;
    }
    
    return $bulan;
}

/**
 * Mendapatkan nilai LMS dari tabel WHO berdasarkan usia dan jenis kelamin
 * Simplified version - dalam implementasi nyata, data ini harus dari database
 * 
 * @param int $usia_bulan Usia dalam bulan (0-60)
 * @param string $jenis_kelamin L atau P
 * @param string $indikator bb_u, tb_u, atau bb_tb
 * @return array ['L' => float, 'M' => float, 'S' => float]
 */
function getWHOReference($usia_bulan, $jenis_kelamin, $indikator) {
    // Data referensi WHO (simplified - beberapa bulan saja sebagai contoh)
    // Dalam implementasi nyata, data ini harus lengkap dari 0-60 bulan
    
    $referensi = [
        'bb_u' => [ // Berat Badan menurut Usia
            'L' => [
                0 => ['L' => 0.3487, 'M' => 3.3464, 'S' => 0.14602],
                12 => ['L' => 0.1738, 'M' => 9.6479, 'S' => 0.12401],
                24 => ['L' => 0.1950, 'M' => 12.2315, 'S' => 0.12050],
                36 => ['L' => 0.2306, 'M' => 14.3299, 'S' => 0.12187],
                48 => ['L' => 0.2551, 'M' => 16.3391, 'S' => 0.12619],
                60 => ['L' => 0.2713, 'M' => 18.3015, 'S' => 0.13180]
            ],
            'P' => [
                0 => ['L' => 0.3809, 'M' => 3.2322, 'S' => 0.14171],
                12 => ['L' => 0.0988, 'M' => 8.9481, 'S' => 0.12274],
                24 => ['L' => 0.1368, 'M' => 11.4800, 'S' => 0.12119],
                36 => ['L' => 0.1777, 'M' => 13.5573, 'S' => 0.12402],
                48 => ['L' => 0.2025, 'M' => 15.5338, 'S' => 0.13080],
                60 => ['L' => 0.2164, 'M' => 17.4208, 'S' => 0.13840]
            ]
        ],
        'tb_u' => [ // Tinggi Badan menurut Usia
            'L' => [
                0 => ['L' => 1, 'M' => 49.8842, 'S' => 0.03795],
                12 => ['L' => 1, 'M' => 75.7488, 'S' => 0.03370],
                24 => ['L' => 1, 'M' => 87.0764, 'S' => 0.03594],
                36 => ['L' => 1, 'M' => 96.1253, 'S' => 0.03750],
                48 => ['L' => 1, 'M' => 103.3174, 'S' => 0.03876],
                60 => ['L' => 1, 'M' => 109.9044, 'S' => 0.03967]
            ],
            'P' => [
                0 => ['L' => 1, 'M' => 49.1477, 'S' => 0.03790],
                12 => ['L' => 1, 'M' => 74.0974, 'S' => 0.03386],
                24 => ['L' => 1, 'M' => 85.7233, 'S' => 0.03619],
                36 => ['L' => 1, 'M' => 95.1379, 'S' => 0.03753],
                48 => ['L' => 1, 'M' => 102.7376, 'S' => 0.03844],
                60 => ['L' => 1, 'M' => 109.4393, 'S' => 0.03900]
            ]
        ]
    ];
    
    // Cari data terdekat (untuk usia yang tidak ada di tabel, gunakan interpolasi sederhana)
    $data = $referensi[$indikator][$jenis_kelamin];
    
    // Jika usia persis ada di tabel
    if (isset($data[$usia_bulan])) {
        return $data[$usia_bulan];
    }
    
    // Cari 2 titik terdekat untuk interpolasi
    $keys = array_keys($data);
    $lower_key = null;
    $upper_key = null;
    
    foreach ($keys as $key) {
        if ($key <= $usia_bulan) {
            $lower_key = $key;
        }
        if ($key >= $usia_bulan && $upper_key === null) {
            $upper_key = $key;
            break;
        }
    }
    
    // Jika di luar range, gunakan nilai terdekat
    if ($lower_key === null) return $data[$upper_key];
    if ($upper_key === null) return $data[$lower_key];
    if ($lower_key === $upper_key) return $data[$lower_key];
    
    // Interpolasi linear
    $ratio = ($usia_bulan - $lower_key) / ($upper_key - $lower_key);
    
    return [
        'L' => $data[$lower_key]['L'] + ($data[$upper_key]['L'] - $data[$lower_key]['L']) * $ratio,
        'M' => $data[$lower_key]['M'] + ($data[$upper_key]['M'] - $data[$lower_key]['M']) * $ratio,
        'S' => $data[$lower_key]['S'] + ($data[$upper_key]['S'] - $data[$lower_key]['S']) * $ratio
    ];
}

/**
 * Menentukan status gizi berdasarkan Z-Score
 * 
 * @param float $zscore_bb_u Z-Score Berat Badan menurut Usia
 * @param float $zscore_tb_u Z-Score Tinggi Badan menurut Usia
 * @return string Status gizi
 */
function tentukanStatusGizi($zscore_bb_u, $zscore_tb_u) {
    // Klasifikasi berdasarkan WHO
    $status = [];
    
    // Status Berat Badan
    if ($zscore_bb_u < -3) {
        $status[] = "Gizi Buruk";
    } elseif ($zscore_bb_u >= -3 && $zscore_bb_u < -2) {
        $status[] = "Gizi Kurang";
    } elseif ($zscore_bb_u >= -2 && $zscore_bb_u <= 2) {
        $status[] = "Gizi Baik";
    } else {
        $status[] = "Gizi Lebih";
    }
    
    // Status Tinggi Badan (Stunting)
    if ($zscore_tb_u < -3) {
        $status[] = "Stunting Berat";
    } elseif ($zscore_tb_u >= -3 && $zscore_tb_u < -2) {
        $status[] = "Stunting";
    }
    
    // Gabungkan status
    if (count($status) == 1 && $status[0] == "Gizi Baik") {
        return "Gizi Baik";
    }
    
    return implode(" + ", $status);
}

/**
 * Menghitung Z-Score lengkap untuk satu pemeriksaan
 * 
 * @param string $tanggal_lahir Format: Y-m-d
 * @param string $jenis_kelamin L atau P
 * @param string $tanggal_ukur Format: Y-m-d
 * @param float $berat_badan Dalam kg
 * @param float $tinggi_badan Dalam cm
 * @return array ['usia_bulan', 'zscore_bb_u', 'zscore_tb_u', 'status_gizi']
 */
function hitungZScoreLengkap($tanggal_lahir, $jenis_kelamin, $tanggal_ukur, $berat_badan, $tinggi_badan) {
    // Hitung usia dalam bulan
    $usia_bulan = hitungUsiaBulan($tanggal_lahir, $tanggal_ukur);
    
    // Batasi usia maksimal 60 bulan (5 tahun)
    if ($usia_bulan > 60) {
        $usia_bulan = 60;
    }
    
    // Ambil referensi WHO untuk BB/U
    $ref_bb = getWHOReference($usia_bulan, $jenis_kelamin, 'bb_u');
    $zscore_bb_u = hitungZScore($berat_badan, $ref_bb['L'], $ref_bb['M'], $ref_bb['S']);
    
    // Ambil referensi WHO untuk TB/U
    $ref_tb = getWHOReference($usia_bulan, $jenis_kelamin, 'tb_u');
    $zscore_tb_u = hitungZScore($tinggi_badan, $ref_tb['L'], $ref_tb['M'], $ref_tb['S']);
    
    // Tentukan status gizi
    $status_gizi = tentukanStatusGizi($zscore_bb_u, $zscore_tb_u);
    
    return [
        'usia_bulan' => $usia_bulan,
        'zscore_bb_u' => $zscore_bb_u,
        'zscore_tb_u' => $zscore_tb_u,
        'status_gizi' => $status_gizi
    ];
}

/**
 * Mendapatkan interpretasi Z-Score untuk ditampilkan ke user
 * 
 * @param float $zscore
 * @param string $indikator bb_u atau tb_u
 * @return string Interpretasi
 */
function getInterprestasiZScore($zscore, $indikator) {
    if ($indikator == 'bb_u') {
        if ($zscore < -3) return "Gizi Buruk (sangat rendah)";
        if ($zscore >= -3 && $zscore < -2) return "Gizi Kurang (rendah)";
        if ($zscore >= -2 && $zscore <= 2) return "Gizi Baik (normal)";
        if ($zscore > 2) return "Gizi Lebih (berisiko obesitas)";
    } else {
        if ($zscore < -3) return "Stunting Berat (sangat pendek)";
        if ($zscore >= -3 && $zscore < -2) return "Stunting (pendek)";
        if ($zscore >= -2 && $zscore <= 2) return "Normal";
        if ($zscore > 2) return "Tinggi";
    }
    return "Tidak dapat ditentukan"; // Default return value
}
?>