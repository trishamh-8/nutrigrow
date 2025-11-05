<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

// Include config
require_once 'config.php';
$conn = getConnection();

// Ambil data user lengkap dari tabel akun
$user = getUserInfo($conn, $_SESSION['id_akun']);

if (!$user) {
    // Jika user tidak ditemukan, logout
    session_destroy();
    header('Location: login.php');
    exit;
}

// Ambil statistik (dummy data untuk demo - nanti bisa diganti dengan data real)
$total_balita = 0;
$status_gizi_baik = 0;
$jadwal_mendatang = 0;
$artikel_baru = 0;

// Jika role = pengguna, hitung data real dari database
if ($user['role'] == 'pengguna') {
    // Hitung total balita
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM balita WHERE id_akun = ?");
    $stmt->execute([$_SESSION['id_akun']]);
    $result = $stmt->fetch();
    $total_balita = $result['total'];
    
    // Hitung status gizi baik
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT b.id_balita) as total 
        FROM balita b
        JOIN pertumbuhan p ON b.id_balita = p.id_balita
        WHERE b.id_akun = ? 
        AND p.status_gizi = 'Normal'
        AND p.id_pertumbuhan IN (
            SELECT MAX(id_pertumbuhan) 
            FROM pertumbuhan 
            WHERE id_balita = b.id_balita
        )
    ");
    $stmt->execute([$_SESSION['id_akun']]);
    $result = $stmt->fetch();
    $status_gizi_baik = $result['total'];
    
    // Hitung jadwal mendatang
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM jadwal j
        JOIN balita b ON j.id_balita = b.id_balita
        WHERE b.id_akun = ? 
        AND j.tanggal >= NOW()
        AND j.status = 'terjadwal'
    ");
    $stmt->execute([$_SESSION['id_akun']]);
    $result = $stmt->fetch();
    $jadwal_mendatang = $result['total'];
}

// Hitung artikel baru (untuk semua user)
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM artikel 
    WHERE tgl_terbit >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND status = 'published'
");
$stmt->execute();
$result = $stmt->fetch();
$artikel_baru = $result['total'];

// Ambil aktivitas terbaru dari pertumbuhan, asupan, dan riwayat interaksi
$aktivitas = [];

// 1. Pertumbuhan terbaru
$stmt = $conn->prepare("
    SELECT p.*, b.nama_balita,
           p.tanggal_pemeriksaan as tanggal_pengukuran, 
           p.berat_badan,
           p.tinggi_badan
    FROM pertumbuhan p
    JOIN balita b ON p.id_balita = b.id_balita
    WHERE b.id_akun = ?
    ORDER BY p.tanggal_pemeriksaan DESC
    LIMIT 3
");
$stmt->execute([$_SESSION['id_akun']]);
while ($row = $stmt->fetch()) {
    $aktivitas[] = [
        'judul' => 'Pemeriksaan Pertumbuhan ' . htmlspecialchars($row['nama_balita']),
        'detail' => sprintf(
            'BB: %.1f kg, TB: %.1f cm, Status: %s',
            $row['berat_badan'],
            $row['tinggi_badan'],
            $row['status_gizi']
        ),
        'waktu' => getTimeAgo(strtotime($row['tanggal_pengukuran'])),
        'icon' => '📊',
        'color' => '#e8f5e9'
    ];
}

// 2. Asupan harian terbaru
$stmt = $conn->prepare("
    SELECT ah.*, b.nama_balita,
           DATE_FORMAT(CONCAT(ah.tanggal_catatan, ' ', ah.waktu_makan), '%Y-%m-%d %H:%i:00') as full_datetime 
    FROM asupan_harian ah
    JOIN balita b ON ah.id_balita = b.id_balita
    WHERE b.id_akun = ?
    ORDER BY ah.tanggal_catatan DESC, ah.waktu_makan DESC
    LIMIT 3
");
$stmt->execute([$_SESSION['id_akun']]);
while ($row = $stmt->fetch()) {
    $aktivitas[] = [
        'judul' => 'Asupan ' . htmlspecialchars($row['nama_balita']),
        'detail' => sprintf(
            '%s - %s (%.0f kkal)',
            date('H:i', strtotime($row['waktu_makan'])),
            htmlspecialchars($row['jenis_makanan']),
            $row['kalori_total']
        ),
        'waktu' => getTimeAgo(strtotime($row['full_datetime'])),
        'icon' => '🍽️',
        'color' => '#e3f2fd'
    ];
}

// Urutkan aktivitas berdasarkan waktu
usort($aktivitas, function($a, $b) {
    return strcmp($b['waktu'], $a['waktu']);
});

// Ambil jadwal mendatang untuk user yang login
$stmt = $conn->prepare("
    SELECT j.*, b.nama_balita, b.id_balita,
           DATE_FORMAT(j.tanggal, '%d %b %Y - %H:%i') as formatted_date
    FROM jadwal j
    JOIN balita b ON j.id_balita = b.id_balita
    WHERE b.id_akun = ? 
    AND j.tanggal >= CURDATE()
    AND j.status = 'terjadwal'
    ORDER BY j.tanggal ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['id_akun']]);
$jadwal = [];
while ($row = $stmt->fetch()) {
    $jadwal[] = [
        'judul' => htmlspecialchars($row['jenis_kegiatan']),
        'tanggal' => $row['formatted_date'],
        'lokasi' => htmlspecialchars($row['lokasi']),
        'balita' => htmlspecialchars($row['nama_balita'])
    ];
}

// Fungsi helper untuk format "time ago"
function getTimeAgo($timestamp) {
    $current_time = time();
    $time_difference = $current_time - $timestamp;
    
    if ($time_difference < 60) {
        return "Baru saja";
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . " menit yang lalu";
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . " jam yang lalu";
    } else {
        $days = floor($time_difference / 86400);
        return $days . " hari yang lalu";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NutriGrow</title>
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
        
        /* Sidebar */
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
        
        .logo-text {
            color: #66BB6A;
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
        
        /* Main Content */
        .main-content {
            margin-left: 240px;
            flex: 1;
            padding: 20px 40px;
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
            font-size: 18px;
        }
        
        /* Page Title */
        .page-title {
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-info h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-icon.blue { background: #e3f2fd; }
        .stat-icon.green { background: #e8f5e9; }
        .stat-icon.purple { background: #f3e5f5; }
        .stat-icon.teal { background: #e0f2f1; }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .view-all {
            color: #4FC3F7;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Activity Item */
        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: background 0.2s;
        }
        
        .activity-item:hover {
            background: #f9f9f9;
        }
        
        .activity-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .activity-detail {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .activity-time {
            font-size: 12px;
            color: #999;
        }
        
        /* Schedule Item */
        .schedule-item {
            padding: 15px;
            border-left: 3px solid #4FC3F7;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .schedule-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .schedule-date {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .schedule-location {
            font-size: 13px;
            color: #999;
        }
        
        /* Info Cards */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .info-card {
            padding: 30px;
            border-radius: 15px;
            color: white;
            background: linear-gradient(135deg, #4FC3F7 0%, #66BB6A 100%);
        }
        
        .info-card.purple {
            background: linear-gradient(135deg, #BA68C8 0%, #9C27B0 100%);
        }
        
        .info-card-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .info-card h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }
        
        .info-card p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .info-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" placeholder="Cari artikel, data balita, atau informasi...">
            </div>
            
            <div class="user-info">
                <div class="lang-selector">
                    <span>🌐</span>
                    <span>ID</span>
                </div>
                
                <div class="user-avatar">
                    <div>
                        <div style="font-weight: 600; font-size: 14px; text-align: right;">
                            <?php echo htmlspecialchars($user['nama']); ?>
                        </div>
                        <div style="font-size: 12px; color: #999; text-align: right;">
                            <?php 
                            if ($user['role'] == 'tenaga_kesehatan') {
                                echo 'Tenaga Kesehatan';
                            } elseif ($user['role'] == 'admin') {
                                echo 'Administrator';
                            } else {
                                echo 'Orang Tua';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="avatar">👤</div>
                </div>
            </div>
        </header>
        
        <!-- Page Title -->
        <div class="page-title">
            <h1>Dashboard</h1>
            <p class="page-subtitle">Selamat datang kembali! Berikut ringkasan informasi terkini</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Balita</h3>
                    <div class="stat-value"><?php echo $total_balita; ?></div>
                    <div style="margin-top:12px; display:flex; gap:8px;">
                        <a href="data_balita.php" class="view-all" style="padding:6px 10px; background:#eef2ff; border-radius:8px; color:#334155; text-decoration:none; font-size:13px;">Lihat Data</a>
                        <a href="data_balita.php?action=add" class="view-all" style="padding:6px 10px; background:#e6fffa; border-radius:8px; color:#065f46; text-decoration:none; font-size:13px;">Tambah Balita</a>
                    </div>
                </div>
                <div class="stat-icon blue">😊</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Status Gizi Baik</h3>
                    <div class="stat-value"><?php echo $status_gizi_baik; ?></div>
                </div>
                <div class="stat-icon green">📊</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Jadwal Mendatang</h3>
                    <div class="stat-value"><?php echo $jadwal_mendatang; ?></div>
                </div>
                <div class="stat-icon purple">📅</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Artikel Baru</h3>
                    <div class="stat-value"><?php echo $artikel_baru; ?></div>
                </div>
                <div class="stat-icon teal">📄</div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Aktivitas Terbaru -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Aktivitas Terbaru</h2>
                </div>
                
                <?php foreach ($aktivitas as $item): ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: <?php echo $item['color']; ?>">
                        <?php echo $item['icon']; ?>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title"><?php echo $item['judul']; ?></div>
                        <div class="activity-detail"><?php echo $item['detail']; ?></div>
                        <div class="activity-time"><?php echo $item['waktu']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Jadwal Mendatang -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Jadwal Mendatang</h2>
                    <a href="jadwal.php" class="view-all">Lihat Semua Jadwal →</a>
                </div>
                
                <?php foreach ($jadwal as $item): ?>
                <div class="schedule-item">
                    <div class="schedule-title"><?php echo $item['judul']; ?></div>
                    <div style="font-size: 13px; color: #666; margin-bottom: 3px;">
                        <?php echo $item['balita']; ?>
                    </div>
                    <div class="schedule-date">
                        <span>📅</span>
                        <span><?php echo $item['tanggal']; ?></span>
                    </div>
                    <div class="schedule-location"><?php echo $item['lokasi']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Info Cards -->
        <div class="info-cards">
            <div class="info-card" onclick="location.href='status_gizi.php'" style="cursor: pointer; transition: transform 0.2s;">
                <div class="info-card-icon" style="position: relative;">
                    <div style="font-size: 24px;">📈</div>
                    <?php if ($total_balita > 0): ?>
                    <div style="position: absolute; top: -5px; right: -5px; background: #4CAF50; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 2px solid white;">
                        <?php echo $total_balita; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <h3>Z-Score & Status Gizi</h3>
                <p>Hitung dan pantau status gizi balita berdasarkan berat dan tinggi badan terkini</p>
                <?php if ($total_balita > 0): ?>
                <div style="margin-top: 15px; background: rgba(255,255,255,0.2); padding: 10px 15px; border-radius: 8px; font-size: 13px;">
                    <div style="margin-bottom: 5px;">Status Gizi Terkini:</div>
                    <div style="font-weight: 600;">
                        <?php echo $status_gizi_baik; ?> dari <?php echo $total_balita; ?> balita memiliki gizi baik
                    </div>
                </div>
                <?php else: ?>
                <div style="margin-top: 15px; background: rgba(255,255,255,0.2); padding: 10px 15px; border-radius: 8px; font-size: 13px;">
                    Klik untuk mulai menghitung status gizi balita Anda
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-card purple" onclick="location.href='artikel.php'" style="cursor: pointer; transition: transform 0.2s;">
                <div class="info-card-icon" style="position: relative;">
                    <div style="font-size: 24px;">📚</div>
                    <?php if ($artikel_baru > 0): ?>
                    <div style="position: absolute; top: -5px; right: -5px; background: #E91E63; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 2px solid white;">
                        <?php echo $artikel_baru; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <h3>Artikel & Tips</h3>
                <p>Akses informasi dan tips seputar nutrisi dan kesehatan balita dari ahli</p>
                <?php if ($artikel_baru > 0): ?>
                <div style="margin-top: 15px; background: rgba(255,255,255,0.2); padding: 10px 15px; border-radius: 8px; font-size: 13px;">
                    <?php echo $artikel_baru; ?> artikel baru dalam minggu ini
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .info-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            .info-card:active {
                transform: translateY(0);
            }
        </style>
    </main>
</body>
<script src="assets/logout-confirm.js"></script>
</html>