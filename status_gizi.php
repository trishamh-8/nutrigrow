<?php
// status_gizi.php - Perhitungan Z-Score dan Status Gizi (UC-005)
require_once 'config.php';
require_once 'who_reference.php';
require_once 'auth.php';

$conn = getConnection();
$id_akun = $_SESSION['id_akun'];

// Get user data from active session
$user = getUserInfo($conn, $id_akun);

if (!$user) {
    // If user data not found, redirect to login
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$mode = 'input'; // Mode: input, result
$zscore_result = null;

// Get all balita for this user
$query_balita = "SELECT id_balita, nama_balita, tanggal_lahir, jenis_kelamin FROM balita WHERE id_akun = ? ORDER BY nama_balita ASC";
$stmt = $conn->prepare($query_balita);
$stmt->execute([$id_akun]);
$balita_list = $stmt->fetchAll();

// Get selected balita or use first one
$selected_balita_id = isset($_GET['id_balita']) ? $_GET['id_balita'] : ($balita_list[0]['id_balita'] ?? null);

// Get selected balita details
$stmt = $conn->prepare("SELECT * FROM balita WHERE id_balita = ? AND id_akun = ?");
$stmt->execute([$selected_balita_id, $id_akun]);
$balita = $stmt->fetch();

// Fungsi untuk menghitung Z-Score
function hitungZScore($nilai_aktual, $median, $sd) {
    if ($sd == 0) return 0;
    return ($nilai_aktual - $median) / $sd;
}

// Fungsi untuk menentukan status gizi berdasarkan Z-Score
function tentukanStatusGizi($zscore, $indikator) {
    if ($indikator == 'BB/U') {
        if ($zscore < -3) return 'Gizi Buruk';
        if ($zscore < -2) return 'Gizi Kurang';
        if ($zscore >= -2 && $zscore <= 1) return 'Gizi Baik';
        if ($zscore > 1) return 'Berisiko Gizi Lebih';
    } elseif ($indikator == 'TB/U') {
        if ($zscore < -3) return 'Sangat Pendek (Stunting Berat)';
        if ($zscore < -2) return 'Pendek (Stunting)';
        if ($zscore >= -2) return 'Normal';
    } elseif ($indikator == 'BB/TB') {
        if ($zscore < -3) return 'Sangat Kurus (Wasting Berat)';
        if ($zscore < -2) return 'Kurus (Wasting)';
        if ($zscore >= -2 && $zscore <= 1) return 'Normal';
        if ($zscore > 1 && $zscore <= 2) return 'Berisiko Gemuk';
        if ($zscore > 2) return 'Gemuk (Obesitas)';
    }
    return 'Tidak Dapat Ditentukan';
}

// Fungsi untuk mendapatkan data referensi WHO (Simplified - harus diganti dengan tabel lengkap)
function getWHOReference($usia_bulan, $jenis_kelamin, $indikator) {
    // CATATAN: Ini adalah data sample. Harus diganti dengan tabel lengkap WHO
    // Idealnya data ini ada di database terpisah
    
    // Data sample untuk usia 24 bulan (2 tahun) - Laki-laki
    if ($jenis_kelamin == 'L' && $usia_bulan == 24) {
        if ($indikator == 'BB/U') {
            return ['median' => 12.2, 'sd' => 1.3];
        } elseif ($indikator == 'TB/U') {
            return ['median' => 86.7, 'sd' => 3.4];
        }
    }
    
    // Data sample untuk usia 24 bulan (2 tahun) - Perempuan
    if ($jenis_kelamin == 'P' && $usia_bulan == 24) {
        if ($indikator == 'BB/U') {
            return ['median' => 11.5, 'sd' => 1.3];
        } elseif ($indikator == 'TB/U') {
            return ['median' => 85.7, 'sd' => 3.4];
        }
    }
    
    // Default values (harus diganti dengan interpolasi data WHO yang sebenarnya)
    return ['median' => 0, 'sd' => 1];
}

// ========== PROSES PERHITUNGAN Z-SCORE ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'hitung') {
    if (!$balita) {
        $error = "Data balita tidak ditemukan. Silakan tambahkan data balita terlebih dahulu.";
    } else {
        $berat_badan = floatval($_POST['berat_badan']);
        $tinggi_badan = floatval($_POST['tinggi_badan']);
        $tanggal_pemeriksaan = $_POST['tanggal_pemeriksaan'];
        $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';
        
        // Fungsi validasi numerik
        function validateMeasurement($value, $min, $max, $field) {
            if (!is_numeric($value)) {
                return "$field harus berupa angka.";
            }
            $value = floatval($value);
            if ($value < $min || $value > $max) {
                return "$field harus antara $min - $max" . ($field === "Berat badan" ? " kg" : " cm");
            }
            return null;
        }
        
        // Validasi input
        if (empty($berat_badan) || empty($tinggi_badan) || empty($tanggal_pemeriksaan)) {
            $error = "Data tidak lengkap, mohon periksa kembali input.";
        } elseif (!is_numeric($berat_badan) || !is_numeric($tinggi_badan)) {
            $error = "Berat badan dan tinggi badan harus berupa angka.";
        } elseif (($berat_error = validateMeasurement($berat_badan, 0.1, 100, "Berat badan"))) {
            $error = $berat_error;
        } elseif (($tinggi_error = validateMeasurement($tinggi_badan, 20, 200, "Tinggi badan"))) {
            $error = $tinggi_error;
        } else {
            // Hitung usia dalam bulan
            // Hitung usia dalam bulan
            $lahir = new DateTime($balita['tanggal_lahir']);
            $ukur = new DateTime($tanggal_pemeriksaan);
            $usia_bulan = $lahir->diff($ukur)->y * 12 + $lahir->diff($ukur)->m;
            
            if ($usia_bulan < 0) {
                $error = "Tanggal pemeriksaan tidak boleh sebelum tanggal lahir.";
            } elseif ($usia_bulan > 60) {
                $error = "Sistem ini hanya untuk balita usia 0-60 bulan (5 tahun).";
            } else {
                // Ambil data referensi WHO
                $ref_bbu = getWHOReference($usia_bulan, $balita['jenis_kelamin'], 'BB/U');
                $ref_tbu = getWHOReference($usia_bulan, $balita['jenis_kelamin'], 'TB/U');
                
                // Hitung Z-Score untuk BB/U
                $zscore_bbu = hitungZScore($berat_badan, $ref_bbu['median'], $ref_bbu['sd']);
                
                // Hitung Z-Score untuk TB/U
                $zscore_tbu = hitungZScore($tinggi_badan, $ref_tbu['median'], $ref_tbu['sd']);
                
                // Hitung Z-Score untuk BB/TB (simplified)
                // Idealnya menggunakan tabel WHO BB/TB
                $bmi = $berat_badan / (($tinggi_badan / 100) * ($tinggi_badan / 100));
                $zscore_bbtb = ($bmi - 15) / 2; // Simplified calculation
                
                // Tentukan status gizi keseluruhan
                $status_bbu = tentukanStatusGizi($zscore_bbu, 'BB/U');
                $status_tbu = tentukanStatusGizi($zscore_tbu, 'TB/U');
                $status_bbtb = tentukanStatusGizi($zscore_bbtb, 'BB/TB');
                
                // Status gizi utama (berdasarkan BB/TB atau BB/U)
                $status_gizi = $status_bbtb;
                
                // Simpan ke database
                $query_insert = "INSERT INTO pertumbuhan 
                                (id_balita, id_akun, tanggal_pemeriksaan, berat_badan, tinggi_badan, 
                                 status_gizi, zscore, catatan) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                try {
                    $stmt = $conn->prepare($query_insert);
                    $zscore_avg = ($zscore_bbu + $zscore_tbu + $zscore_bbtb) / 3;
                    $stmt->execute([
                        $selected_balita_id,
                        $id_akun,
                        $tanggal_pemeriksaan,
                        $berat_badan,
                        $tinggi_badan,
                        $status_gizi,
                        $zscore_avg,
                        $catatan
                    ]);
                    
                    $mode = 'result';
                    $zscore_result = [
                        'berat_badan' => $berat_badan,
                        'tinggi_badan' => $tinggi_badan,
                        'tanggal_pemeriksaan' => $tanggal_pemeriksaan,
                        'usia_bulan' => $usia_bulan,
                        'zscore_bbu' => $zscore_bbu,
                        'zscore_tbu' => $zscore_tbu,
                        'zscore_bbtb' => $zscore_bbtb,
                        'status_bbu' => $status_bbu,
                        'status_tbu' => $status_tbu,
                        'status_bbtb' => $status_bbtb,
                        'status_gizi' => $status_gizi
                    ];
                    $message = "Status gizi berhasil dihitung dan disimpan!";
                } catch (PDOException $e) {
                    $error = "Gagal menyimpan data: " . $e->getMessage();
                }
            }
        }
    }
}

// Ambil riwayat pertumbuhan
$riwayat_pertumbuhan = [];
if ($selected_balita_id) {
    $query_riwayat = "SELECT * FROM pertumbuhan 
                      WHERE id_balita = ? 
                      ORDER BY tanggal_pemeriksaan DESC 
                      LIMIT 5";
    $stmt = $conn->prepare($query_riwayat);
    $stmt->execute([$selected_balita_id]);
    $riwayat_pertumbuhan = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Score & Status Gizi - NutriGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>

        .form-group {
            margin-bottom: 20px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .search-box {
            flex: 1;
            max-width: 600px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .lang-selector {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 14px;
        }
        
        .user-avatar {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4FC3F7 0%, #66BB6A 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .avatar i {
            font-size: 16px;
            color: white;
        }

        /* Back button (copied style from data_balita.php for consistent sidebar/button look) */
        .btn-back {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        
        /* Sidebar visuals copied from dashboard.php to match appearance */
        .sidebar {
            width: 240px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        /* Main content spacing so it doesn't overlap with fixed sidebar */
        .main-content {
            margin-left: 280px; 
            padding: 30px;
            box-sizing: border-box;
            min-height: 100vh;
            background: transparent;
        }

        /* Ensure content cards fill available width inside main area */
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        /* Responsive: on smaller screens, make sidebar flow and remove left margin */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 18px;
            }
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                box-shadow: none;
                padding-bottom: 10px;
            }
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            padding: 10px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4FC3F7 0%, #66BB6A 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: 700;
        }
        
        .logo-text .nutri {
            color: #4FC3F7;
        }
        
        .menu {
            list-style: none;
        }
        
        .menu-item {
            margin-bottom: 5px;
        }
        
        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
        }
        
        .menu-link:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .menu-link.active {
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            color: white;
        }
        
        .menu-icon {
            font-size: 20px;
        }
        
        .menu-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }
        
        .logout-link {
            color: #f44336;
        }
        
        .logout-link:hover {
            background: #ffebee;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .input-icon .form-input {
            padding-left: 45px;
        }
        
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-box {
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #0891b2;
        }
        
        .info-title {
            font-size: 14px;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 10px;
        }
        
        .info-list {
            font-size: 13px;
            color: #0c4a6e;
            line-height: 1.8;
        }
        
        .result-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .result-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .result-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
            color: white;
        }
        
        .result-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .result-subtitle {
            font-size: 14px;
            color: #64748b;
        }
        
        .result-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .result-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .result-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .result-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .result-unit {
            font-size: 14px;
            color: #64748b;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .status-baik {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-kurang {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-buruk {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .empty-placeholder {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 50px;
            color: white;
            opacity: 0.8;
        }
        
        .history-table {
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            
            .result-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
        <!-- Header -->
            <header class="header">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" placeholder="Cari data asupan...">
                </div>
                
                <div class="user-info">
                    <div>
                        <h4><?php echo htmlspecialchars($user['nama']); ?></h4>
                        <p><?php echo ucfirst($user['role']); ?></p>
                    </div>
                    <div class="avatar"><i class="fas fa-user"></i></div>
                </div>
            </header>

            <!-- Page Header -->
            <div class="page-header">
                <h1>Z-Score & Status Gizi</h1>
                <p>Hitung dan pantau status gizi balita berdasarkan berat dan tinggi badan</p>
            </div>

            <!-- Balita Selector -->
            <div class="balita-selector" style="margin-bottom: 30px; margin-top: 15px;">
                <form action="" method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <label for="id_balita" style="font-weight: 600;">Pilih Balita:</label>
                    <select name="id_balita" id="id_balita" class="form-select" style="flex: 1; max-width: 300px; padding: 10px; border-radius: 8px; border: 1px solid #e0e0e0;" onchange="this.form.submit()">
                        <?php foreach ($balita_list as $b): ?>
                            <option value="<?php echo $b['id_balita']; ?>" <?php echo ($b['id_balita'] == $selected_balita_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['nama_balita'] . ' (' . $b['jenis_kelamin'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($mode == 'input'): ?>
            <!-- Form Input -->
            <div class="two-column">
                <div class="content-card">
                    <div style="background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%); padding: 20px; border-radius: 12px; color: white; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="font-size: 32px;">📊</div>
                            <div>
                                <h3 style="margin-bottom: 5px;">Input Data Pengukuran</h3>
                                <p style="font-size: 13px; opacity: 0.9;">Masukkan data terbaru</p>
                            </div>
                        </div>
                    </div>

                    <?php if (!$balita): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                        <i class="fas fa-baby" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>Belum ada data balita. Silakan tambahkan data balita terlebih dahulu.</p>
                        <button class="btn btn-primary" style="margin-top: 15px;" onclick="location.href='balita.php?action=add'">
                            <i class="fas fa-plus"></i> Tambah Data Balita
                        </button>
                    </div>
                    <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="hitung">
                        
                        <div class="form-group">
                            <label class="form-label">Berat Badan (kg)</label>
                            <div class="input-icon">
                                <i class="fas fa-weight"></i>
                                <input type="number" step="0.01" min="0.1" max="100" name="berat_badan" class="form-input" 
                                       placeholder="Contoh: 12.5" required 
                                       oninput="validateInput(this, 0.1, 100, 'berat')"
                                       onkeypress="return isNumberKey(event)">
                            </div>
                            <div class="error-message" id="berat-error"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tinggi Badan (cm)</label>
                            <div class="input-icon">
                                <i class="fas fa-ruler-vertical"></i>
                                <input type="number" step="0.01" min="20" max="200" name="tinggi_badan" class="form-input" 
                                       placeholder="Contoh: 89" required
                                       oninput="validateInput(this, 20, 200, 'tinggi')"
                                       onkeypress="return isNumberKey(event)">
                            </div>
                            <div class="error-message" id="tinggi-error"></div>
                        </div>
                        
                        <style>
                            .error-message {
                                color: #dc2626;
                                font-size: 12px;
                                margin-top: 4px;
                                min-height: 18px;
                            }
                            
                            .form-input.error {
                                border-color: #dc2626;
                            }
                            
                            .form-input.error:focus {
                                box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
                            }
                        </style>
                        
                        <script>
                            // Validasi input numerik saat pengetikan
                            function isNumberKey(evt) {
                                var charCode = (evt.which) ? evt.which : evt.keyCode;
                                if (charCode == 46 || charCode == 44) { // titik atau koma
                                    return true;
                                }
                                if (charCode > 31 && (charCode < 48 || charCode > 57)) {
                                    return false;
                                }
                                return true;
                            }
                            
                            // Validasi range dan format input
                            function validateInput(input, min, max, type) {
                                const value = parseFloat(input.value);
                                const errorElement = document.getElementById(type + '-error');
                                let errorMessage = '';
                                
                                input.classList.remove('error');
                                
                                if (input.value === '') {
                                    errorMessage = `${type === 'berat' ? 'Berat badan' : 'Tinggi badan'} harus diisi`;
                                } else if (isNaN(value)) {
                                    errorMessage = 'Masukkan angka yang valid';
                                    input.classList.add('error');
                                } else if (value < min || value > max) {
                                    errorMessage = `${type === 'berat' ? 'Berat badan' : 'Tinggi badan'} harus antara ${min} - ${max} ${type === 'berat' ? 'kg' : 'cm'}`;
                                    input.classList.add('error');
                                }
                                
                                errorElement.textContent = errorMessage;
                                
                                // Disable tombol submit jika ada error
                                const submitButton = document.querySelector('button[type="submit"]');
                                const allErrors = document.querySelectorAll('.error-message');
                                const hasErrors = Array.from(allErrors).some(el => el.textContent !== '');
                                submitButton.disabled = hasErrors;
                            }
                            
                            // Validasi form sebelum submit
                            document.querySelector('form').addEventListener('submit', function(e) {
                                const beratInput = document.querySelector('input[name="berat_badan"]');
                                const tinggiInput = document.querySelector('input[name="tinggi_badan"]');
                                
                                validateInput(beratInput, 0.1, 100, 'berat');
                                validateInput(tinggiInput, 20, 200, 'tinggi');
                                
                                const allErrors = document.querySelectorAll('.error-message');
                                const hasErrors = Array.from(allErrors).some(el => el.textContent !== '');
                                
                                if (hasErrors) {
                                    e.preventDefault();
                                }
                            });
                        </script>

                        <div class="form-group">
                            <label class="form-label">Tanggal Pengukuran</label>
                            <input type="date" name="tanggal_pemeriksaan" class="form-input" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea name="catatan" class="form-input" rows="3" 
                                      placeholder="Tambahkan catatan jika diperlukan"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-calculator"></i> Hitung Status Gizi
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="empty-placeholder">
                        <div class="empty-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <h3 style="color: #1e293b; margin-bottom: 10px;">Siap Menghitung Status Gizi?</h3>
                        <p style="color: #64748b; line-height: 1.6;">
                            Masukkan data pengukuran berat dan tinggi badan balita pada form 
                            di samping untuk melihat hasil analisis Z-Score dan status gizi.
                        </p>
                    </div>

                    <div class="info-box">
                        <div class="info-title">Informasi Z-Score:</div>
                        <div class="info-list">
                            • Z-Score < -3: Gizi Buruk<br>
                            • Z-Score -3 s/d < -2: Gizi Kurang<br>
                            • Z-Score -2 s/d +1: Gizi Baik<br>
                            • Z-Score > +1 s/d +2: Berisiko Gizi Lebih<br>
                            • Z-Score > +2: Gizi Lebih
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($mode == 'result'): ?>
            <!-- Hasil Perhitungan -->
            <div class="result-card">
                <div class="result-header">
                    <div class="result-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="result-title">Hasil Perhitungan Z-Score</div>
                    <div class="result-subtitle">
                        <?php echo htmlspecialchars($balita['nama_balita']); ?> - 
                        <?php echo formatTanggalIndo($zscore_result['tanggal_pemeriksaan']); ?>
                    </div>
                </div>

                <div class="result-grid">
                    <div class="result-item">
                        <div class="result-label">Berat Badan</div>
                        <div class="result-value">
                            <?php echo number_format($zscore_result['berat_badan'], 1); ?>
                            <span class="result-unit">kg</span>
                        </div>
                    </div>
                    
                    <div class="result-item">
                        <div class="result-label">Tinggi Badan</div>
                        <div class="result-value">
                            <?php echo number_format($zscore_result['tinggi_badan'], 1); ?>
                            <span class="result-unit">cm</span>
                        </div>
                    </div>
                    
                    <div class="result-item">
                        <div class="result-label">Usia</div>
                        <div class="result-value">
                            <?php echo $zscore_result['usia_bulan']; ?>
                            <span class="result-unit">bulan</span>
                        </div>
                    </div>
                </div>

                <div style="background: #f8fafc; padding: 25px; border-radius: 12px; margin-bottom: 20px;">
                    <h3 style="font-size: 16px; color: #1e293b; margin-bottom: 20px;">Nilai Z-Score</h3>
                    
                    <div style="display: grid; gap: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 13px; color: #64748b;">BB/U (Berat Badan menurut Usia)</div>
                                <div style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 5px;">
                                    <?php echo number_format($zscore_result['zscore_bbu'], 2); ?>
                                </div>
                            </div>
                            <span class="status-badge <?php 
                                if ($zscore_result['zscore_bbu'] >= -2 && $zscore_result['zscore_bbu'] <= 1) echo 'status-baik';
                                elseif ($zscore_result['zscore_bbu'] < -2) echo 'status-kurang';
                                else echo 'status-buruk';
                            ?>">
                                <?php echo $zscore_result['status_bbu']; ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 13px; color: #64748b;">TB/U (Tinggi Badan menurut Usia)</div>
                                <div style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 5px;">
                                    <?php echo number_format($zscore_result['zscore_tbu'], 2); ?>
                                </div>
                            </div>
                            <span class="status-badge <?php 
                                if ($zscore_result['zscore_tbu'] >= -2) echo 'status-baik';
                                else echo 'status-kurang';
                            ?>">
                                <?php echo $zscore_result['status_tbu']; ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 13px; color: #64748b;">BB/TB (Berat Badan menurut Tinggi Badan)</div>
                                <div style="font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 5px;">
                                    <?php echo number_format($zscore_result['zscore_bbtb'], 2); ?>
                                </div>
                            </div>
                            <span class="status-badge <?php 
                                if ($zscore_result['zscore_bbtb'] >= -2 && $zscore_result['zscore_bbtb'] <= 1) echo 'status-baik';
                                elseif ($zscore_result['zscore_bbtb'] < -2) echo 'status-kurang';
                                else echo 'status-buruk';
                            ?>">
                                <?php echo $zscore_result['status_bbtb']; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%); border-radius: 12px; color: white;">
                    <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px;">Status Gizi Keseluruhan</div>
                    <div style="font-size: 28px; font-weight: 700;">
                        <?php echo $zscore_result['status_gizi']; ?>
                    </div>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <button class="btn btn-primary" onclick="location.href='status_gizi.php'">
                        <i class="fas fa-plus"></i> Input Data Baru
                    </button>
                    <button class="btn btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Cetak Hasil
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Riwayat Pertumbuhan -->
            <?php if (count($riwayat_pertumbuhan) > 0): ?>
            <div class="content-card history-table">
                <div class="card-header">
                    <h2 class="card-title">Riwayat Pertumbuhan</h2>
                    <p class="card-subtitle">5 data pemeriksaan terakhir</p>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Berat (kg)</th>
                            <th>Tinggi (cm)</th>
                            <th>Z-Score</th>
                            <th>Status Gizi</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat_pertumbuhan as $row): ?>
                        <tr>
                            <td><?php echo formatTanggalIndo($row['tanggal_pemeriksaan']); ?></td>
                            <td><strong><?php echo number_format($row['berat_badan'], 1); ?> kg</strong></td>
                            <td><strong><?php echo number_format($row['tinggi_badan'], 1); ?> cm</strong></td>
                            <td>
                                <span style="font-weight: 600; color: <?php 
                                    if ($row['zscore'] >= -2 && $row['zscore'] <= 1) echo '#059669';
                                    elseif ($row['zscore'] < -2) echo '#d97706';
                                    else echo '#dc2626';
                                ?>">
                                    <?php echo number_format($row['zscore'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    if (strpos($row['status_gizi'], 'Baik') !== false || strpos($row['status_gizi'], 'Normal') !== false) echo 'badge-nutrisi';
                                    else echo 'badge-medium';
                                ?>">
                                    <?php echo $row['status_gizi']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['catatan'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

<?php 
// PDO connections are automatically closed when the script ends
$conn = null;
?>