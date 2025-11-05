<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'zscore_calculator.php';

// Ensure user is logged in
requireLogin();

$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];
$message = '';
$error = '';

// Get user info and role from database
$stmt = $conn->prepare("
    SELECT a.nama,
           CASE 
               WHEN t.id_tenaga_kesehatan IS NOT NULL THEN 'tenaga_kesehatan'
               WHEN p.id_pengguna IS NOT NULL THEN 'pengguna'
               WHEN adm.id_admin IS NOT NULL THEN 'admin'
               ELSE 'pengguna'
           END as role
    FROM akun a
    LEFT JOIN tenaga_kesehatan t ON t.id_akun = a.id_akun
    LEFT JOIN pengguna p ON p.id_akun = a.id_akun
    LEFT JOIN admin adm ON adm.id_akun = a.id_akun
    WHERE a.id_akun = ?
");
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get all balita
$stmt = $conn->prepare("SELECT id_balita, nama_balita, jenis_kelamin, tanggal_lahir FROM data_balita");
$stmt->execute();
$result = $stmt->get_result();
$balita_list = $result->fetch_all(MYSQLI_ASSOC);

// Get selected balita or use first one
$selected_balita_id = isset($_GET['id_balita']) ? $_GET['id_balita'] : ($balita_list[0]['id_balita'] ?? null);

// Calculate Z-Score if form is submitted
$zscore_result = null;
if (isset($_POST['hitung']) && isset($_POST['berat_badan']) && isset($_POST['tinggi_badan'])) {
    // Get selected balita data
    $stmt = $conn->prepare("SELECT * FROM data_balita WHERE id_balita = ?");
    $stmt->bind_param("i", $selected_balita_id);
    $stmt->execute();
    $balita = $stmt->get_result()->fetch_assoc();

    if ($balita) {
        $tanggal_lahir = $balita['tanggal_lahir'];
        $jenis_kelamin = $balita['jenis_kelamin'];
        $tanggal_ukur = date('Y-m-d'); // Use today's date
        $berat_badan = floatval($_POST['berat_badan']);
        $tinggi_badan = floatval($_POST['tinggi_badan']);

        $zscore_result = hitungZScoreLengkap(
            $tanggal_lahir,
            $jenis_kelamin,
            $tanggal_ukur,
            $berat_badan,
            $tinggi_badan
        );

        // Save measurement to pertumbuhan table
        $stmt = $conn->prepare("
            INSERT INTO pertumbuhan (
                id_balita,
                tanggal_pemeriksaan,
                berat_badan,
                tinggi_badan,
                zscore,
                status_gizi
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $zscore_avg = ($zscore_result['zscore_bb_u'] + $zscore_result['zscore_tb_u']) / 2;
        $stmt->bind_param("isddds",
            $selected_balita_id,
            $tanggal_ukur,
            $berat_badan,
            $tinggi_badan,
            $zscore_avg,
            $zscore_result['status_gizi']
        );
        
        if ($stmt->execute()) {
            $message = "Data pertumbuhan berhasil disimpan!";
        } else {
            $error = "Gagal menyimpan data pertumbuhan: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hitung Z-Score - NutriGrow</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 240px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 240px;
            flex: 1;
            padding: 20px 40px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        select.form-control {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .result-card {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
        }
        
        .result-header {
            font-size: 20px;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 15px;
        }
        
        .result-detail {
            margin-bottom: 10px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
        }
        
        .result-label {
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 5px;
        }
        
        .result-value {
            color: #333;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="user-info">
                <div>
                    <div style="font-weight: 600; font-size: 14px; text-align: right;">
                        <?php echo htmlspecialchars($user['nama']); ?>
                    </div>
                    <div style="font-size: 12px; color: #999; text-align: right;">
                        <?php echo $user['role'] == 'tenaga_kesehatan' ? 'Tenaga Kesehatan' : 'Orang Tua'; ?>
                    </div>
                </div>
                <div class="avatar">👤</div>
            </div>
        </header>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Balita Selector -->
        <div class="balita-selector" style="margin-bottom: 20px;">
            <form action="" method="GET" style="display: flex; gap: 10px; align-items: center;">
                <label for="id_balita" style="font-weight: 600;">Pilih Balita:</label>
                <select name="id_balita" id="id_balita" class="form-select" style="flex: 1; max-width: 300px;" onchange="this.form.submit()">
                    <?php foreach ($balita_list as $b): ?>
                        <option value="<?php echo $b['id_balita']; ?>" <?php echo $selected_balita_id == $b['id_balita'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['nama_balita']); ?> 
                            (<?php echo $b['jenis_kelamin']; ?>, <?php echo date('d/m/Y', strtotime($b['tanggal_lahir'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Hitung Z-Score</h1>
                <p class="page-subtitle">Masukkan pengukuran terbaru untuk menghitung Z-Score</p>
            </div>
        </div>
        
        <div class="section">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="berat_badan">Berat Badan (kg)</label>
                    <input type="number" step="0.1" min="0" max="100" class="form-control" id="berat_badan" name="berat_badan" required>
                </div>
                
                <div class="form-group">
                    <label for="tinggi_badan">Tinggi Badan (cm)</label>
                    <input type="number" step="0.1" min="0" max="200" class="form-control" id="tinggi_badan" name="tinggi_badan" required>
                </div>
                
                <button type="submit" name="hitung" class="btn btn-primary">Hitung Z-Score</button>
            </form>
            
            <?php if ($zscore_result): ?>
            <div class="result-card">
                <div class="result-header">Hasil Perhitungan Z-Score</div>
                
                <div class="result-detail">
                    <div class="result-label">Usia</div>
                    <div class="result-value"><?php echo $zscore_result['usia_bulan']; ?> bulan</div>
                </div>
                
                <div class="result-detail">
                    <div class="result-label">Z-Score BB/U</div>
                    <div class="result-value">
                        <?php 
                        echo number_format($zscore_result['zscore_bb_u'], 2);
                        echo " - ";
                        echo getInterprestasiZScore($zscore_result['zscore_bb_u'], 'bb_u');
                        ?>
                    </div>
                </div>
                
                <div class="result-detail">
                    <div class="result-label">Z-Score TB/U</div>
                    <div class="result-value">
                        <?php 
                        echo number_format($zscore_result['zscore_tb_u'], 2);
                        echo " - ";
                        echo getInterprestasiZScore($zscore_result['zscore_tb_u'], 'tb_u');
                        ?>
                    </div>
                </div>
                
                <div class="result-detail">
                    <div class="result-label">Status Gizi</div>
                    <div class="result-value"><?php echo $zscore_result['status_gizi']; ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>