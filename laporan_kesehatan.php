<?php
require_once 'config.php';
require_once 'auth.php';

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

// Get all balita for this user
$stmt = $conn->prepare("SELECT id_balita, nama_balita, jenis_kelamin, tanggal_lahir FROM balita WHERE id_akun = ? ORDER BY nama_balita ASC");
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$balita_list = $result->fetch_all(MYSQLI_ASSOC);

// Get selected balita or use first one
$selected_balita_id = isset($_GET['id_balita']) ? $_GET['id_balita'] : ($balita_list[0]['id_balita'] ?? null);

// Get selected balita details
$stmt = $conn->prepare("SELECT * FROM balita WHERE id_balita = ? AND id_akun = ?");
$stmt->bind_param("ii", $selected_balita_id, $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$balita = $result->fetch_assoc();

if (!$balita) {
    // Redirect jika belum ada data balita
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Data Tidak Ditemukan</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; background: #f5f7fa; }
            .message { background: #fff3cd; padding: 30px; border-radius: 10px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #856404; margin-bottom: 15px; }
            a { color: #4FC3F7; text-decoration: none; font-weight: 600; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="message">
            <h2>⚠️ Data Balita Belum Ada</h2>
            <p>Silakan tambahkan data balita terlebih dahulu untuk melihat laporan kesehatan.</p>
            <br>
            <a href="dashboard.php">← Kembali ke Dashboard</a>
        </div>
    </body>
    <script src="assets/logout-confirm.js"></script>
    </html>';
    exit;
}

// Ambil data pertumbuhan terakhir
$stmt = $conn->prepare("
    SELECT p.*, 
           p.berat_badan - LAG(p.berat_badan) OVER (ORDER BY tanggal_pemeriksaan) as berat_badan_change,
           p.tinggi_badan - LAG(p.tinggi_badan) OVER (ORDER BY tanggal_pemeriksaan) as tinggi_badan_change
    FROM pertumbuhan p
    WHERE id_balita = ? AND id_akun = ?
    ORDER BY tanggal_pemeriksaan DESC 
    LIMIT 1
");
$stmt->bind_param("ii", $balita['id_balita'], $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$pertumbuhan = $result->fetch_assoc();

// Jika belum ada data pertumbuhan, set default
if (!$pertumbuhan) {
    $pertumbuhan = [
        'berat_badan' => 0,
        'tinggi_badan' => 0,
        'lingkar_kepala' => 0,
        'zscore' => 0,
        'status_gizi' => 'Belum Ada Data',
        'tanggal_pemeriksaan' => date('Y-m-d'),
        'berat_badan_change' => 0,
        'tinggi_badan_change' => 0
    ];
}

// Hitung Z-Score (simplified - dalam praktik perlu tabel WHO standar)
$zscore = $pertumbuhan ? $pertumbuhan['zscore'] : 0;
$status_gizi = $pertumbuhan ? $pertumbuhan['status_gizi'] : 'Normal';

// Ambil data asupan harian (rata-rata 7 hari terakhir)
$stmt = $conn->prepare("
    SELECT 
        COALESCE(AVG(kalori_total), 0) as avg_kalori,
        COALESCE(AVG(protein), 0) as avg_protein,
        COALESCE(AVG(karbohidrat), 0) as avg_karbohidrat,
        COALESCE(AVG(lemak), 0) as avg_lemak
    FROM asupan_harian 
    WHERE id_balita = ? 
    AND tanggal_catatan >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->bind_param("i", $balita['id_balita']);
$stmt->execute();
$result = $stmt->get_result();
$asupan = $result->fetch_assoc();

// If no data, set defaults
if (!$asupan) {
    $asupan = [
        'avg_kalori' => 0,
        'avg_protein' => 0,
        'avg_karbohidrat' => 0,
        'avg_lemak' => 0
    ];
}

// Target nutrisi berdasarkan usia (simplified)
$target_kalori = 1200;
$target_protein = 35;
$target_karbohidrat = 150;
$target_lemak = 40;

// Hitung persentase
$persen_kalori = $asupan['avg_kalori'] > 0 ? round(($asupan['avg_kalori'] / $target_kalori) * 100) : 0;
$persen_protein = $asupan['avg_protein'] > 0 ? round(($asupan['avg_protein'] / $target_protein) * 100) : 0;
$persen_karbohidrat = $asupan['avg_karbohidrat'] > 0 ? round(($asupan['avg_karbohidrat'] / $target_karbohidrat) * 100) : 0;
$persen_lemak = $asupan['avg_lemak'] > 0 ? round(($asupan['avg_lemak'] / $target_lemak) * 100) : 0;

// Ambil rekomendasi dari tenaga kesehatan
$stmt = $conn->prepare("
    SELECT r.*, a.nama as nama_tenaga_kesehatan, t.id_tenaga_kesehatan
    FROM rekomendasi_gizi r
    JOIN akun a ON r.id_akun = a.id_akun
    JOIN tenaga_kesehatan t ON t.id_akun = a.id_akun
    WHERE r.id_balita = ? AND r.status = 'aktif'
    ORDER BY r.prioritas DESC, r.tanggal_rekomendasi DESC
    LIMIT 3
");
$stmt->bind_param("i", $balita['id_balita']);
$stmt->execute();
$result = $stmt->get_result();
$rekomendasi_list = [];
while ($row = $result->fetch_assoc()) {
    $rekomendasi_list[] = $row;
}

// Hitung usia dalam bulan
$tanggal_lahir = new DateTime($balita['tanggal_lahir']);
$sekarang = new DateTime();
$diff = $tanggal_lahir->diff($sekarang);
$usia_bulan = ($diff->y * 12) + $diff->m;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kesehatan - NutriGrow</title>
    <link rel="stylesheet" href="assets/css/header.css">
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
        
        /* Copy semua CSS dari dashboard.php untuk sidebar */
        .sidebar {
            width: 240px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
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
        
        /* Main Content */
        .main-content {
            margin-left: 240px;
            flex: 1;
            padding: 20px 40px;
        }
        
        
        /* Page Header */
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
        
        .btn-download {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s;
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
        }
        
        /* Status Card */
        .status-card {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .status-icon {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }
        
        .status-content {
            flex: 1;
        }
        
        .status-title {
            font-size: 24px;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 5px;
        }
        
        .status-detail {
            color: #558b2f;
            font-size: 14px;
        }
        
        .status-badge {
            background: white;
            padding: 8px 20px;
            border-radius: 20px;
            color: #2e7d32;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .info-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .info-value.blue { color: #2196F3; }
        .info-value.green { color: #66BB6A; }
        .info-value.purple { color: #BA68C8; }
        .info-value.pink { color: #EC407A; }
        
        .info-change {
            font-size: 12px;
            color: #66BB6A;
        }
        
        .info-extra {
            font-size: 12px;
            color: #999;
        }
        
        /* Section */
        .section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .section-icon {
            font-size: 24px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        /* Nutrition Grid */
        .nutrition-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .nutrition-card {
            text-align: center;
        }
        
        .nutrition-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .nutrition-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .nutrition-target {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s;
        }
        
        .progress-fill.yellow { background: #FFC107; }
        .progress-fill.green { background: #66BB6A; }
        .progress-fill.orange { background: #FF9800; }
        
        .progress-text {
            font-size: 12px;
            font-weight: 600;
        }
        
        .progress-text.yellow { color: #F57C00; }
        .progress-text.green { color: #388E3C; }
        .progress-text.orange { color: #E65100; }
        
        /* Recommendation */
        .recommendation-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .recommendation-item {
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid;
            display: flex;
            gap: 15px;
        }
        
        .recommendation-item.red {
            background: #ffebee;
            border-color: #f44336;
        }
        
        .recommendation-item.orange {
            background: #fff3e0;
            border-color: #ff9800;
        }
        
        .recommendation-item.blue {
            background: #e3f2fd;
            border-color: #2196F3;
        }
        
        .recommendation-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .recommendation-icon.red { background: #ffcdd2; }
        .recommendation-icon.orange { background: #ffe0b2; }
        .recommendation-icon.blue { background: #bbdefb; }
        
        .recommendation-content {
            flex: 1;
        }
        
        .recommendation-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .recommendation-text {
            font-size: 14px;
            color: #666;
        }
        
        @media print {
            .sidebar, .header, .btn-download {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php include __DIR__ . '/partials/header.php'; ?>
        
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

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Laporan Kesehatan Balita</h1>
                <p class="page-subtitle">Ringkasan lengkap perkembangan dan status kesehatan</p>
            </div>
            <a href="export_pdf.php?id=<?php echo $balita['id_balita']; ?>" class="btn-download" target="_blank">
                <span>📄</span>
                <span>Unduh Laporan PDF</span>
            </a>
        </div>
        
        <!-- Status Gizi Card -->
        <div class="status-card">
            <div class="status-icon">📊</div>
            <div class="status-content">
                <div class="status-title">Status Gizi: <?php echo $status_gizi; ?></div>
                <div class="status-detail">Z-Score: <?php echo number_format($zscore, 1); ?> (Berat Badan menurut Tinggi Badan)</div>
            </div>
            <div class="status-badge">
                <span>✓</span>
                <span>Baik</span>
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">Berat Badan Saat Ini</div>
                <div class="info-value blue"><?php echo number_format($pertumbuhan['berat_badan'], 1); ?> kg</div>
                <div class="info-change">📈 +0.3 kg bulan ini</div>
            </div>
            
            <div class="info-card">
                <div class="info-label">Tinggi Badan Saat Ini</div>
                <div class="info-value green"><?php echo $pertumbuhan['tinggi_badan']; ?> cm</div>
                <div class="info-change">📈 +1 cm bulan ini</div>
            </div>
            
            <div class="info-card">
                <div class="info-label">Usia</div>
                <div class="info-value purple"><?php echo $usia_bulan; ?></div>
                <div class="info-extra">bulan (<?php echo floor($usia_bulan / 12); ?> tahun)</div>
            </div>
            
            <div class="info-card">
                <div class="info-label">Jenis Kelamin</div>
                <div class="info-value pink"><?php echo $balita['jenis_kelamin'] == 'L' ? 'L' : 'P'; ?></div>
                <div class="info-extra"><?php echo $balita['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></div>
            </div>
        </div>
        
        <!-- Ringkasan Asupan Harian -->
        <div class="section">
            <div class="section-header">
                <span class="section-icon">🍽️</span>
                <h2 class="section-title">Ringkasan Asupan Harian (Rata-rata 7 Hari)</h2>
            </div>
            
            <div class="nutrition-grid">
                <div class="nutrition-card">
                    <div class="nutrition-label">Kalori</div>
                    <div class="nutrition-value"><?php echo round($asupan['avg_kalori']); ?></div>
                    <div class="nutrition-target">/ <?php echo $target_kalori; ?> kcal</div>
                    <div class="progress-bar">
                        <div class="progress-fill yellow" style="width: <?php echo min($persen_kalori, 100); ?>%"></div>
                    </div>
                    <div class="progress-text yellow"><?php echo $persen_kalori; ?>% dari target</div>
                </div>
                
                <div class="nutrition-card">
                    <div class="nutrition-label">Protein</div>
                    <div class="nutrition-value"><?php echo round($asupan['avg_protein']); ?></div>
                    <div class="nutrition-target">/ <?php echo $target_protein; ?> g</div>
                    <div class="progress-bar">
                        <div class="progress-fill green" style="width: <?php echo min($persen_protein, 100); ?>%"></div>
                    </div>
                    <div class="progress-text green"><?php echo $persen_protein; ?>% dari target</div>
                </div>
                
                <div class="nutrition-card">
                    <div class="nutrition-label">Karbohidrat</div>
                    <div class="nutrition-value"><?php echo round($asupan['avg_karbohidrat']); ?></div>
                    <div class="nutrition-target">/ <?php echo $target_karbohidrat; ?> g</div>
                    <div class="progress-bar">
                        <div class="progress-fill orange" style="width: <?php echo min($persen_karbohidrat, 100); ?>%"></div>
                    </div>
                    <div class="progress-text orange"><?php echo $persen_karbohidrat; ?>% dari target</div>
                </div>
                
                <div class="nutrition-card">
                    <div class="nutrition-label">Lemak</div>
                    <div class="nutrition-value"><?php echo round($asupan['avg_lemak']); ?></div>
                    <div class="nutrition-target">/ <?php echo $target_lemak; ?> g</div>
                    <div class="progress-bar">
                        <div class="progress-fill orange" style="width: <?php echo min($persen_lemak, 100); ?>%"></div>
                    </div>
                    <div class="progress-text orange"><?php echo $persen_lemak; ?>% dari target</div>
                </div>
            </div>
        </div>
        
        <!-- Rekomendasi -->
        <div class="section">
            <div class="section-header">
                <span class="section-icon">📝</span>
                <h2 class="section-title">Rekomendasi dari Tenaga Kesehatan</h2>
            </div>
            
            <div class="recommendation-list">
                <?php if ($persen_kalori < 95): ?>
                <div class="recommendation-item red">
                    <div class="recommendation-icon red">⚠️</div>
                    <div class="recommendation-content">
                        <div class="recommendation-title">Tingkatkan Asupan Kalori</div>
                        <div class="recommendation-text">Asupan kalori masih <?php echo $persen_kalori; ?>% di bawah target. Tambahkan camilan sehat 2x sehari.</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($persen_protein > 100 && $persen_protein < 160): ?>
                <div class="recommendation-item orange">
                    <div class="recommendation-icon orange">💡</div>
                    <div class="recommendation-content">
                        <div class="recommendation-title">Pertahankan Asupan Protein</div>
                        <div class="recommendation-text">Asupan protein sudah sangat baik, <?php echo $persen_protein; ?>% dari target. Teruskan pola makan ini.</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php foreach ($rekomendasi_list as $rek): ?>
                <div class="recommendation-item blue">
                    <div class="recommendation-icon blue">👨‍⚕️</div>
                    <div class="recommendation-content">
                        <div class="recommendation-title"><?php echo htmlspecialchars($rek['sumber']); ?></div>
                        <div class="recommendation-text">
                            <?php echo htmlspecialchars($rek['isi_rekomendasi']); ?>
                            <br><small style="color: #999;">
                                oleh <?php echo htmlspecialchars($rek['nama_tenaga_kesehatan']); ?> - 
                                <?php echo date('d M Y', strtotime($rek['tanggal_rekomendasi'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($rekomendasi_list) && $persen_kalori >= 95 && ($persen_protein <= 100 || $persen_protein >= 160)): ?>
                <div class="recommendation-item blue">
                    <div class="recommendation-icon blue">ℹ️</div>
                    <div class="recommendation-content">
                        <div class="recommendation-title">Belum Ada Rekomendasi</div>
                        <div class="recommendation-text">Belum ada rekomendasi khusus dari tenaga kesehatan. Lanjutkan pola makan sehat dan rutin kontrol.</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body