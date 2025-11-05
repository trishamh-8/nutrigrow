<?php
// who_reference.php - Data Referensi WHO Child Growth Standards
// File ini berisi fungsi helper untuk mendapatkan nilai referensi WHO

/**
 * Mendapatkan data referensi WHO untuk perhitungan Z-Score
 * 
 * @param int $usia_bulan Usia anak dalam bulan (0-60)
 * @param string $jenis_kelamin 'L' untuk laki-laki, 'P' untuk perempuan
 * @param string $indikator 'BB/U', 'TB/U', atau 'BB/TB'
 * @return array ['median' => float, 'sd' => float]
 */
function getWHOReferenceComplete($usia_bulan, $jenis_kelamin, $indikator) {
    
    // Data referensi WHO - Berat Badan menurut Usia (BB/U) - LAKI-LAKI
    // Format: usia_bulan => [median, sd]
    $bbu_laki = [
        0 => [3.3, 0.4],
        1 => [4.5, 0.5],
        2 => [5.6, 0.6],
        3 => [6.4, 0.7],
        4 => [7.0, 0.7],
        5 => [7.5, 0.8],
        6 => [7.9, 0.8],
        12 => [9.6, 1.0],
        18 => [10.9, 1.2],
        24 => [12.2, 1.3],
        30 => [13.3, 1.5],
        36 => [14.3, 1.6],
        42 => [15.3, 1.7],
        48 => [16.3, 1.8],
        54 => [17.3, 1.9],
        60 => [18.3, 2.0]
    ];
    
    // Data referensi WHO - Berat Badan menurut Usia (BB/U) - PEREMPUAN
    $bbu_perempuan = [
        0 => [3.2, 0.4],
        1 => [4.2, 0.5],
        2 => [5.1, 0.6],
        3 => [5.8, 0.7],
        4 => [6.4, 0.7],
        5 => [6.9, 0.7],
        6 => [7.3, 0.8],
        12 => [9.0, 1.0],
        18 => [10.2, 1.1],
        24 => [11.5, 1.3],
        30 => [12.7, 1.4],
        36 => [13.9, 1.5],
        42 => [15.0, 1.7],
        48 => [16.1, 1.8],
        54 => [17.2, 1.9],
        60 => [18.3, 2.1]
    ];
    
    // Data referensi WHO - Tinggi Badan menurut Usia (TB/U) - LAKI-LAKI
    $tbu_laki = [
        0 => [49.9, 1.9],
        1 => [54.7, 2.0],
        2 => [58.4, 2.1],
        3 => [61.4, 2.2],
        4 => [63.9, 2.3],
        5 => [65.9, 2.3],
        6 => [67.6, 2.4],
        12 => [75.7, 2.9],
        18 => [82.3, 3.2],
        24 => [86.7, 3.4],
        30 => [91.4, 3.6],
        36 => [95.7, 3.8],
        42 => [99.9, 4.0],
        48 => [103.3, 4.1],
        54 => [106.7, 4.3],
        60 => [110.0, 4.4]
    ];
    
    // Data referensi WHO - Tinggi Badan menurut Usia (TB/U) - PEREMPUAN
    $tbu_perempuan = [
        0 => [49.1, 1.9],
        1 => [53.7, 2.0],
        2 => [57.1, 2.1],
        3 => [59.8, 2.2],
        4 => [62.1, 2.2],
        5 => [64.0, 2.3],
        6 => [65.7, 2.4],
        12 => [74.0, 2.8],
        18 => [80.7, 3.1],
        24 => [85.7, 3.4],
        30 => [90.3, 3.6],
        36 => [94.4, 3.8],
        42 => [98.4, 4.0],
        48 => [101.6, 4.1],
        54 => [104.7, 4.3],
        60 => [108.0, 4.4]
    ];
    
    // Pilih tabel yang sesuai
    $tabel = null;
    if ($indikator == 'BB/U') {
        $tabel = ($jenis_kelamin == 'L') ? $bbu_laki : $bbu_perempuan;
    } elseif ($indikator == 'TB/U') {
        $tabel = ($jenis_kelamin == 'L') ? $tbu_laki : $tbu_perempuan;
    }
    
    // Jika tidak ada tabel yang cocok
    if (!$tabel) {
        return ['median' => 0, 'sd' => 1];
    }
    
    // Jika usia tepat ada di tabel
    if (isset($tabel[$usia_bulan])) {
        return [
            'median' => $tabel[$usia_bulan][0],
            'sd' => $tabel[$usia_bulan][1]
        ];
    }
    
    // Jika tidak ada, lakukan interpolasi linear
    $usia_keys = array_keys($tabel);
    sort($usia_keys);
    
    // Cari usia terdekat
    $usia_sebelum = null;
    $usia_sesudah = null;
    
    foreach ($usia_keys as $key) {
        if ($key < $usia_bulan) {
            $usia_sebelum = $key;
        } elseif ($key > $usia_bulan) {
            $usia_sesudah = $key;
            break;
        }
    }
    
    // Jika usia di bawah atau di atas range
    if ($usia_sebelum === null) {
        return [
            'median' => $tabel[$usia_keys[0]][0],
            'sd' => $tabel[$usia_keys[0]][1]
        ];
    }
    if ($usia_sesudah === null) {
        return [
            'median' => $tabel[$usia_keys[count($usia_keys) - 1]][0],
            'sd' => $tabel[$usia_keys[count($usia_keys) - 1]][1]
        ];
    }
    
    // Interpolasi linear
    $nilai_sebelum = $tabel[$usia_sebelum];
    $nilai_sesudah = $tabel[$usia_sesudah];
    
    $rasio = ($usia_bulan - $usia_sebelum) / ($usia_sesudah - $usia_sebelum);
    
    $median_interpolasi = $nilai_sebelum[0] + ($nilai_sesudah[0] - $nilai_sebelum[0]) * $rasio;
    $sd_interpolasi = $nilai_sebelum[1] + ($nilai_sesudah[1] - $nilai_sebelum[1]) * $rasio;
    
    return [
        'median' => round($median_interpolasi, 2),
        'sd' => round($sd_interpolasi, 2)
    ];
}

/**
 * Menghitung Z-Score
 * 
 * @param float $nilai_aktual Nilai pengukuran aktual
 * @param float $median Nilai median dari WHO
 * @param float $sd Standard Deviation dari WHO
 * @return float Z-Score
 */
function hitungZScoreHelper($nilai_aktual, $median, $sd) {
    if ($sd == 0) return 0;
    return round(($nilai_aktual - $median) / $sd, 2);
}

/**
 * Menentukan status gizi berdasarkan Z-Score dan indikator
 * 
 * @param float $zscore Nilai Z-Score
 * @param string $indikator 'BB/U', 'TB/U', atau 'BB/TB'
 * @return string Status gizi
 */
function tentukanStatusGiziHelper($zscore, $indikator) {
    if ($indikator == 'BB/U') {
        if ($zscore < -3) return 'Gizi Buruk';
        if ($zscore < -2) return 'Gizi Kurang';
        if ($zscore >= -2 && $zscore <= 1) return 'Gizi Baik';
        if ($zscore > 1 && $zscore <= 2) return 'Berisiko Gizi Lebih';
        if ($zscore > 2) return 'Gizi Lebih';
    } elseif ($indikator == 'TB/U') {
        if ($zscore < -3) return 'Sangat Pendek (Stunting Berat)';
        if ($zscore < -2) return 'Pendek (Stunting)';
        if ($zscore >= -2 && $zscore <= 3) return 'Normal';
        if ($zscore > 3) return 'Tinggi';
    } elseif ($indikator == 'BB/TB') {
        if ($zscore < -3) return 'Sangat Kurus (Wasting Berat)';
        if ($zscore < -2) return 'Kurus (Wasting)';
        if ($zscore >= -2 && $zscore <= 1) return 'Normal';
        if ($zscore > 1 && $zscore <= 2) return 'Berisiko Gemuk';
        if ($zscore > 2 && $zscore <= 3) return 'Gemuk';
        if ($zscore > 3) return 'Obesitas';
    }
    return 'Tidak Dapat Ditentukan';
}

/**
 * Menghitung usia dalam bulan dari tanggal lahir ke tanggal pengukuran
 * 
 * @param string $tanggal_lahir Format: Y-m-d
 * @param string $tanggal_ukur Format: Y-m-d
 * @return int Usia dalam bulan
 */
function hitungUsiaBulan($tanggal_lahir, $tanggal_ukur) {
    $lahir = new DateTime($tanggal_lahir);
    $ukur = new DateTime($tanggal_ukur);
    $diff = $lahir->diff($ukur);
    
    return ($diff->y * 12) + $diff->m;
}

/**
 * Mendapatkan interpretasi lengkap status gizi
 * 
 * @param array $zscore_data Array berisi zscore_bbu, zscore_tbu, zscore_bbtb
 * @return array Interpretasi lengkap
 */
function getInterpretasiLengkap($zscore_data) {
    $interpretasi = [
        'status_utama' => '',
        'rekomendasi' => [],
        'keterangan' => ''
    ];
    
    // Tentukan status utama berdasarkan BB/TB
    if ($zscore_data['zscore_bbtb'] < -2) {
        $interpretasi['status_utama'] = 'Perlu Perhatian Khusus';
        $interpretasi['rekomendasi'][] = 'Konsultasi dengan tenaga kesehatan';
        $interpretasi['rekomendasi'][] = 'Tingkatkan asupan kalori dan protein';
        $interpretasi['rekomendasi'][] = 'Pantau berat badan secara rutin';
    } elseif ($zscore_data['zscore_bbtb'] > 2) {
        $interpretasi['status_utama'] = 'Perlu Perhatian Khusus';
        $interpretasi['rekomendasi'][] = 'Konsultasi dengan tenaga kesehatan';
        $interpretasi['rekomendasi'][] = 'Atur pola makan seimbang';
        $interpretasi['rekomendasi'][] = 'Tingkatkan aktivitas fisik';
    } else {
        $interpretasi['status_utama'] = 'Kondisi Baik';
        $interpretasi['rekomendasi'][] = 'Pertahankan pola makan seimbang';
        $interpretasi['rekomendasi'][] = 'Pantau pertumbuhan secara berkala';
    }
    
    // Tambahkan keterangan stunting jika ada
    if ($zscore_data['zscore_tbu'] < -2) {
        $interpretasi['keterangan'] = 'Terdapat indikasi stunting. Perlu perhatian khusus pada asupan nutrisi dan stimulasi tumbuh kembang.';
    }
    
    return $interpretasi;
}

/**
 * Validasi data input untuk perhitungan Z-Score
 * 
 * @param float $berat_badan Berat badan dalam kg
 * @param float $tinggi_badan Tinggi badan dalam cm
 * @param string $tanggal_lahir Format: Y-m-d
 * @param string $tanggal_ukur Format: Y-m-d
 * @return array ['valid' => bool, 'error' => string]
 */
function validasiInputZScore($berat_badan, $tinggi_badan, $tanggal_lahir, $tanggal_ukur) {
    // Validasi berat badan
    if ($berat_badan <= 0 || $berat_badan > 50) {
        return ['valid' => false, 'error' => 'Berat badan tidak valid (harus antara 0-50 kg)'];
    }
    
    // Validasi tinggi badan
    if ($tinggi_badan <= 0 || $tinggi_badan > 150) {
        return ['valid' => false, 'error' => 'Tinggi badan tidak valid (harus antara 0-150 cm)'];
    }
    
    // Validasi tanggal
    $lahir = new DateTime($tanggal_lahir);
    $ukur = new DateTime($tanggal_ukur);
    
    if ($lahir > $ukur) {
        return ['valid' => false, 'error' => 'Tanggal pengukuran tidak boleh sebelum tanggal lahir'];
    }
    
    // Validasi usia (0-60 bulan)
    $usia_bulan = hitungUsiaBulan($tanggal_lahir, $tanggal_ukur);
    if ($usia_bulan < 0 || $usia_bulan > 60) {
        return ['valid' => false, 'error' => 'Sistem ini hanya untuk balita usia 0-60 bulan (0-5 tahun)'];
    }
    
    return ['valid' => true, 'error' => ''];
}
?>