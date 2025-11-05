<?php
// who_standards.php - File untuk menyimpan standar WHO untuk penilaian status gizi balita

/**
 * Kelas untuk menyediakan standar WHO dan fungsi-fungsi terkait
 * untuk perhitungan status gizi balita
 */
class WHOStandards {
    // Standar WHO untuk BB/U (Weight-for-Age)
    public static function getWHOReference($usia_bulan, $jenis_kelamin, $indikator) {
        $standards = self::getStandards($indikator);
        
        // Batasi usia maksimal 60 bulan
        if ($usia_bulan > 60) {
            $usia_bulan = 60;
        }

        if (isset($standards[$jenis_kelamin][$usia_bulan])) {
            return $standards[$jenis_kelamin][$usia_bulan];
        }
        
        // Jika usia tidak tepat ada di standar, lakukan interpolasi
        return self::interpolateWHOValues($standards[$jenis_kelamin], $usia_bulan);
    }

    /**
     * Mendapatkan standar WHO berdasarkan indikator
     */
    private static function getStandards($indikator) {
        if ($indikator == 'bb_u') {
            return [
                'L' => [ // Laki-laki
                    0  => ['L' => 0.3487, 'M' => 3.3464, 'S' => 0.14602],
                    12 => ['L' => 0.1738, 'M' => 9.6479, 'S' => 0.12401],
                    24 => ['L' => 0.1950, 'M' => 12.2315, 'S' => 0.12050],
                    36 => ['L' => 0.2306, 'M' => 14.3299, 'S' => 0.12187],
                    48 => ['L' => 0.2551, 'M' => 16.3391, 'S' => 0.12619],
                    60 => ['L' => 0.2713, 'M' => 18.3015, 'S' => 0.13180]
                ],
                'P' => [ // Perempuan
                    0  => ['L' => 0.3809, 'M' => 3.2322, 'S' => 0.14171],
                    12 => ['L' => 0.0988, 'M' => 8.9481, 'S' => 0.12274],
                    24 => ['L' => 0.1368, 'M' => 11.4800, 'S' => 0.12119],
                    36 => ['L' => 0.1777, 'M' => 13.5573, 'S' => 0.12402],
                    48 => ['L' => 0.2025, 'M' => 15.5338, 'S' => 0.13080],
                    60 => ['L' => 0.2164, 'M' => 17.4208, 'S' => 0.13840]
                ]
            ];
        } else {
            return [
                'L' => [ // Laki-laki
                    0  => ['L' => 1, 'M' => 49.8842, 'S' => 0.03795],
                    12 => ['L' => 1, 'M' => 75.7488, 'S' => 0.03370],
                    24 => ['L' => 1, 'M' => 87.0764, 'S' => 0.03594],
                    36 => ['L' => 1, 'M' => 96.1253, 'S' => 0.03750],
                    48 => ['L' => 1, 'M' => 103.3174, 'S' => 0.03876],
                    60 => ['L' => 1, 'M' => 109.9044, 'S' => 0.03967]
                ],
                'P' => [ // Perempuan
                    0  => ['L' => 1, 'M' => 49.1477, 'S' => 0.03790],
                    12 => ['L' => 1, 'M' => 74.0974, 'S' => 0.03386],
                    24 => ['L' => 1, 'M' => 85.7233, 'S' => 0.03619],
                    36 => ['L' => 1, 'M' => 95.1379, 'S' => 0.03753],
                    48 => ['L' => 1, 'M' => 102.7376, 'S' => 0.03844],
                    60 => ['L' => 1, 'M' => 109.4393, 'S' => 0.03900]
                ]
            ];
        }
    }

    /**
     * Melakukan interpolasi linear untuk nilai-nilai WHO
     * 
     * @param array $data Data WHO yang tersedia
     * @param int $usia_bulan Usia dalam bulan yang dicari
     * @return array Array berisi nilai L, M, dan S hasil interpolasi
     */
    private static function interpolateWHOValues($data, $usia_bulan) {
        $keys = array_keys($data);
        sort($keys);
        
        // Cari dua titik terdekat untuk interpolasi
        $lower_age = null;
        $upper_age = null;
        
        foreach ($keys as $age) {
            if ($age <= $usia_bulan) {
                $lower_age = $age;
            }
            if ($age >= $usia_bulan && $upper_age === null) {
                $upper_age = $age;
                break;
            }
        }
        
        // Jika di luar range, gunakan nilai terdekat
        if ($lower_age === null) return $data[$upper_age];
        if ($upper_age === null) return $data[$lower_age];
        if ($lower_age === $upper_age) return $data[$lower_age];
        
        // Interpolasi linear
        $ratio = ($usia_bulan - $lower_age) / ($upper_age - $lower_age);
        
        return [
            'L' => $data[$lower_age]['L'] + ($data[$upper_age]['L'] - $data[$lower_age]['L']) * $ratio,
            'M' => $data[$lower_age]['M'] + ($data[$upper_age]['M'] - $data[$lower_age]['M']) * $ratio,
            'S' => $data[$lower_age]['S'] + ($data[$upper_age]['S'] - $data[$lower_age]['S']) * $ratio
        ];
    }

    /**
     * Menentukan status gizi berdasarkan Z-Score
     * 
     * @param float $zscore_bb_u Z-Score BB/U
     * @param float $zscore_tb_u Z-Score TB/U
     * @return string Status gizi lengkap
     */
    public static function tentukanStatusGizi($zscore_bb_u, $zscore_tb_u) {
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
     * Mendapatkan interpretasi Z-Score untuk ditampilkan ke user
     * 
     * @param float $zscore Nilai Z-Score
     * @param string $indikator bb_u atau tb_u
     * @return string Interpretasi status gizi
     */
    public static function getInterprestasiZScore($zscore, $indikator) {
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

        // Pastikan fungsi selalu mengembalikan nilai string
        return "Tidak Diketahui";
    }
}
?>