<?php
// jadwal.php - Halaman Jadwal Imunisasi & Konsultasi
require_once 'config.php';
session_start();

// Use canonical session key `id_akun` (consistent across app)
if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

$conn = getConnection();
$id_akun = $_SESSION['id_akun'];
$role = $_SESSION['role'] ?? 'pengguna';

// Flash message (set by handler)
$flash_jadwal = $_SESSION['flash_jadwal'] ?? null;
if (isset($_SESSION['flash_jadwal'])) unset($_SESSION['flash_jadwal']);

// Fetch basic user info for header display (name + role label)
$user = ['nama' => '', 'role_label' => 'Orang Tua'];
try {
    $stmtUser = $conn->prepare("SELECT nama FROM akun WHERE id_akun = ?");
    $stmtUser->execute([$id_akun]);
    $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($rowUser && !empty($rowUser['nama'])) {
        $user['nama'] = $rowUser['nama'];
    }
} catch (Exception $e) {
    // ignore and keep default user name
}
// Role label from session if available
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'tenaga_kesehatan') $user['role_label'] = 'Tenaga Kesehatan';
    elseif ($_SESSION['role'] === 'admin') $user['role_label'] = 'Administrator';
    else $user['role_label'] = 'Orang Tua';
}

// Get balita list
try {
    if ($role === 'tenaga_kesehatan' || $role === 'admin') {
        // health workers and admins can see all balita
        $stmt = $conn->prepare("SELECT * FROM balita ORDER BY nama_balita ASC");
        $stmt->execute([]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM balita WHERE id_akun = ? ORDER BY tanggal_lahir DESC");
        $stmt->execute([$id_akun]);
    }
    $balita_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $balita_list = [];
}

// Pilih balita: accept 'all' (null) for tenaga_kesehatan/admin, otherwise default to first user's balita
$raw_selected = $_GET['id_balita'] ?? null;
if ($raw_selected === null) {
    if ($role === 'tenaga_kesehatan' || $role === 'admin') {
        $selected_balita = null; // null => show all
    } else {
        $selected_balita = $balita_list[0]['id_balita'] ?? 0;
    }
} else {
    // allow 'all' string or numeric id
    if ($raw_selected === 'all') {
        $selected_balita = null;
    } else {
        $selected_balita = (int)$raw_selected;
    }
}

// Filter
$filter_jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query untuk jadwal
$where = [];
$params = [];

// If a specific balita is selected, filter by it. If null and user is nakes/admin, show all.
if ($selected_balita !== null && $selected_balita > 0) {
    $where[] = "j.id_balita = ?";
    $params[] = $selected_balita;
}

if ($filter_jenis) {
    // match either exact token or substring (case-insensitive) so stored values like
    // 'Imunisasi DPT 4' will match when filter_jenis=imunisasi
    $k = strtolower($filter_jenis);
    $where[] = "(LOWER(j.jenis) = ? OR LOWER(j.jenis) LIKE ?)";
    $params[] = $k;
    $params[] = '%' . $k . '%';
}

if ($filter_status) {
    $where[] = "j.status = ?";
    $params[] = $filter_status;
}

$whereClause = count($where) ? implode(' AND ', $where) : '1';

// Get jadwal
$query_jadwal = "SELECT j.*, b.nama_balita
                 FROM jadwal j
                 LEFT JOIN balita b ON j.id_balita = b.id_balita
                 WHERE " . $whereClause . "
                 ORDER BY j.tanggal ASC";
$stmt = $conn->prepare($query_jadwal);
try {
    $stmt->execute($params);
    $jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $jadwal_list = [];
}

// Compute statistik from the already-fetched $jadwal_list to ensure counts match displayed rows
$stats = ['total' => 0, 'terjadwal' => 0, 'selesai' => 0, 'imunisasi' => 0, 'konsultasi' => 0];
if (is_array($jadwal_list)) {
    foreach ($jadwal_list as $r) {
        $stats['total']++;
        $st = $r['status'] ?? '';
        if ($st === 'terjadwal') $stats['terjadwal']++;
        if ($st === 'selesai') $stats['selesai']++;

        $j = strtolower($r['jenis'] ?? '');
        if (strpos($j, 'imunisasi') !== false) $stats['imunisasi']++;
        if (strpos($j, 'konsultasi') !== false) $stats['konsultasi']++;
    }
}

// Get jadwal mendatang (7 hari ke depan) and include balita name
$query_upcoming_base = "SELECT j.*, b.nama_balita
                       FROM jadwal j
                       LEFT JOIN balita b ON j.id_balita = b.id_balita
                       WHERE j.status = 'terjadwal' AND j.tanggal BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
if ($selected_balita !== null && $selected_balita > 0) {
    $query_upcoming = $query_upcoming_base . " AND j.id_balita = ? ORDER BY j.tanggal ASC LIMIT 3";
    $stmt_upcoming = $conn->prepare($query_upcoming);
    try {
        $stmt_upcoming->execute([$selected_balita]);
        $upcoming_list = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $upcoming_list = [];
    }
} else {
    // For nakes/admin showing all upcoming
    $query_upcoming = $query_upcoming_base . " ORDER BY j.tanggal ASC LIMIT 3";
    $stmt_upcoming = $conn->prepare($query_upcoming);
    try {
        $stmt_upcoming->execute([]);
        $upcoming_list = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $upcoming_list = [];
    }
}

// Get rekomendasi imunisasi berdasarkan usia
if ($selected_balita > 0) {
    $balita_data = array_filter($balita_list, function($b) use ($selected_balita) {
        return $b['id_balita'] == $selected_balita;
    });
    $balita_data = reset($balita_data);
    
    if ($balita_data) {
        $tanggal_lahir = new DateTime($balita_data['tanggal_lahir']);
        $sekarang = new DateTime();
        $usia_bulan = $tanggal_lahir->diff($sekarang)->m + ($tanggal_lahir->diff($sekarang)->y * 12);
        
        // Get rekomendasi imunisasi
        $query_rekomendasi = "SELECT * FROM jenis_imunisasi 
                             WHERE usia_bulan <= ? 
                             ORDER BY usia_bulan ASC";
        $stmt_rek = $conn->prepare($query_rekomendasi);
        try {
            $stmt_rek->execute([$usia_bulan]);
            $rekomendasi_list = $stmt_rek->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $rekomendasi_list = [];
        }
    }
}

function formatTanggal($datetime) {
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $timestamp = strtotime($datetime);
    return date('d', $timestamp) . ' ' . $bulan[date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function formatWaktu($datetime) {
    return date('H:i', strtotime($datetime));
}

function getJenisIcon($jenis) {
    $icons = [
        'imunisasi' => 'fa-syringe',
        'konsultasi' => 'fa-user-doctor',
        'pemeriksaan' => 'fa-stethoscope',
        'posyandu' => 'fa-hospital'
    ];
    return $icons[$jenis] ?? 'fa-calendar';
}

function getJenisColor($jenis) {
    $colors = [
        'imunisasi' => 'primary',
        'konsultasi' => 'success',
        'pemeriksaan' => 'info',
        'posyandu' => 'warning'
    ];
    return $colors[$jenis] ?? 'secondary';
}

function getStatusBadge($status) {
    $badges = [
        'terjadwal' => ['class' => 'warning', 'text' => 'Terjadwal'],
        'selesai' => ['class' => 'success', 'text' => 'Selesai'],
        'dibatalkan' => ['class' => 'danger', 'text' => 'Dibatalkan'],
        'ditunda' => ['class' => 'secondary', 'text' => 'Ditunda']
    ];
    return $badges[$status] ?? ['class' => 'secondary', 'text' => $status];
}

function hitungUmur($tanggal_lahir) {
    $lahir = new DateTime($tanggal_lahir);
    $sekarang = new DateTime();
    $diff = $lahir->diff($sekarang);
    
    if ($diff->y > 0) {
        return $diff->y . ' tahun ' . $diff->m . ' bulan';
    } else {
        return $diff->m . ' bulan';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Imunisasi & Konsultasi - NutriGrow</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/partials/common_head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" placeholder="Cari jadwal imunisasi atau konsultasi...">
                </div>

                <div class="user-info">
                    <div class="lang-selector">
                        <span>🌐</span>
                        <span>ID</span>
                    </div>

                    <div class="user-avatar">
                        <div>
                            <div style="font-weight: 600; font-size: 14px; text-align: right;">
                                <?php echo htmlspecialchars($user['nama'] ?? ($_SESSION['nama'] ?? '')); ?>
                            </div>
                            <div style="font-size: 12px; color: #999; text-align: right;">
                                <?php echo htmlspecialchars($user['role_label'] ?? 'Orang Tua'); ?>
                            </div>
                        </div>
                        <div class="avatar">👤</div>
                    </div>
                </div>
            </header>

            <!-- Page Title -->
            <div class="page-title">
                <h1>Jadwal Imunisasi & Konsultasi</h1>
                <p class="page-subtitle">Kelola jadwal imunisasi dan konsultasi kesehatan balita Anda</p>
            </div>

            <!-- Balita Selector -->
            <?php if (count($balita_list) > 0): ?>
                <?php
                // Determine current balita safely. If $selected_balita is null (showing all), $current_balita will be null.
                $current_balita = null;
                if ($selected_balita !== null && $selected_balita > 0) {
                    $filtered = array_filter($balita_list, function($b) use ($selected_balita) {
                        return $b['id_balita'] == $selected_balita;
                    });
                    $current_balita = reset($filtered);
                    if ($current_balita === false) $current_balita = null;
                }
                ?>
                <div class="balita-selector">
                    <div class="balita-info">
                            <div class="balita-avatar">
                                <i class="fas fa-baby"></i>
                            </div>
                            <div class="balita-details">
                                <?php if ($current_balita): ?>
                                    <h3><?php echo htmlspecialchars($current_balita['nama_balita']); ?></h3>
                                    <p><?php echo ($current_balita['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan'; ?> • <?php echo hitungUmur($current_balita['tanggal_lahir']); ?></p>
                                <?php else: ?>
                                    <h3>Semua Balita</h3>
                                    <p>Menampilkan jadwal untuk semua balita</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-left: 20px;">
                            <form method="GET" action="" style="display:flex; gap:8px; align-items:center;">
                                <label for="filter_balita" style="font-weight:600;">Filter Balita:</label>
                                <select id="filter_balita" name="id_balita" class="form-select" style="min-width:220px; padding:8px;" onchange="this.form.submit()">
                                    <?php if ($role === 'tenaga_kesehatan' || $role === 'admin'): ?>
                                        <option value="all" <?php echo ($selected_balita === null) ? 'selected' : ''; ?>>Semua Balita</option>
                                    <?php endif; ?>
                                    <?php foreach ($balita_list as $b): ?>
                                        <option value="<?php echo $b['id_balita']; ?>" <?php echo ($b['id_balita'] == $selected_balita) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['nama_balita']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($filter_jenis): ?><input type="hidden" name="jenis" value="<?php echo htmlspecialchars($filter_jenis); ?>"><?php endif; ?>
                                <?php if ($filter_status): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>"><?php endif; ?>
                            </form>
                        </div>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'tenaga_kesehatan'): ?>
                        <button class="btn-primary" style="border:none;" onclick="openTambahModal()">
                            <i class="fas fa-plus"></i>
                            <span>Tambah Jadwal</span>
                        </button>
                    <?php endif; ?>
                    <style>
                        .balita-avatar {
                            width: 50px;
                            height: 50px;
                            border-radius: 50%;
                            background: linear-gradient(135deg, #4FC3F7 0%, #66BB6A 100%);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            font-size: 24px;
                            flex-shrink: 0;
                        }

                        /* Jadwal card styles to match dashboard palette */
                        .jadwal-card {
                            background: white;
                            padding: 18px;
                            border-radius: 12px;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
                            margin-bottom: 16px;
                        }

                        .jadwal-card .card-header {
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-start;
                            margin-bottom: 8px;
                        }

                        .jadwal-for {
                            font-size: 12px;
                            font-weight: 600;
                            color: #64748b; /* dashboard gray */
                            text-transform: uppercase;
                            letter-spacing: .5px;
                            margin-bottom: 6px;
                        }

                        .jadwal-type {
                            font-size: 12px;
                            padding: 4px 10px;
                            border-radius: 20px;
                            background: #f1f5f9;
                            color: #334155;
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                        }

                        .jadwal-title {
                            font-size: 18px;
                            font-weight: 600;
                            color: #111827; /* darker title */
                            margin: 8px 0;
                            line-height: 1.4;
                        }

                        .jadwal-desc {
                            font-size: 14px;
                            color: #64748b;
                            margin: 0 0 12px 0;
                            line-height: 1.5;
                        }

                        .jadwal-meta .meta-item {
                            font-size: 13px;
                            color: #475569;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            margin-bottom: 6px;
                        }

                        .status-badge { padding: 6px 10px; border-radius: 8px; font-weight: 600; font-size:12px; display:inline-block; }
                        .status-badge.badge-warning { background: #FFFBEB; color: #92400E; }
                        .status-badge.badge-success { background: #ECFDF5; color: #166534; }
                        .status-badge.badge-danger { background: #FEF2F2; color: #991B1B; }
                        .status-badge.badge-secondary { background: #F8FAFC; color: #475569; }

                        .jadwal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }

                        .card .action-btn-sm { font-size:13px; padding:8px 12px; border-radius:6px; border:none; cursor:pointer; }

                        /* Ensure primary buttons are borderless and without heavy shadow */
                        .btn-primary { border: none !important; box-shadow: none !important; }
                        .btn { box-shadow: none !important; }

                        /* Toning down upcoming item colors and improve placement */
                        .upcoming-title { color: #0f172a !important; }
                        .date-text { color: #64748b !important; }
                        /* Upcoming / Jadwal Mendatang widget styles */
                        .sidebar-widget .upcoming-item {
                            display: flex;
                            gap: 14px;
                            align-items: center;
                            background: #ffffff;
                            padding: 12px;
                            border-radius: 12px;
                            box-shadow: 0 2px 8px rgba(2,6,23,0.04);
                            margin-bottom: 12px;
                            transition: background .12s ease, transform .06s ease;
                        }

                        .sidebar-widget .upcoming-item:hover {
                            background: #fbfdff;
                            transform: translateY(-2px);
                        }

                        .upcoming-left { flex: 0 0 56px; display:flex; align-items:center; justify-content:center; }

                        .upcoming-date {
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            gap: 6px;
                        }

                        .date-icon {
                            width: 44px;
                            height: 44px;
                            border-radius: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            background: linear-gradient(135deg, #f1f5f9 0%, #f8fafc 100%);
                            color: #0f172a;
                            font-size: 16px;
                        }

                        .date-text { font-size: 12px; color: #64748b; text-align:center; }

                        .upcoming-body { flex: 1 1 auto; min-width:0; }

                        .upcoming-title { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 4px; line-height:1.25; }

                        .upcoming-sub { font-size: 13px; color: #475569; margin-bottom: 6px; }

                        .upcoming-location { font-size: 13px; color: #94a3b8; display:flex; align-items:center; gap:8px; }
                        
                        /* Stats cards styling (so numbers feel softer and aligned with dashboard) */
                        .stats-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                            gap: 20px;
                            margin-bottom: 20px;
                        }

                        .stat-card {
                            position: relative;
                            background: white;
                            padding: 20px;
                            border-radius: 12px;
                            box-shadow: 0 2px 8px rgba(2,6,23,0.04);
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }

                        .stat-card .stat-info h3 {
                            margin: 0 0 8px 0;
                            font-size: 14px;
                            color: #64748b; /* softer gray like dashboard */
                            font-weight: 600;
                        }

                        .stat-card .stat-info .stat-value {
                            font-size: 32px;
                            font-weight: 700;
                            color: #111827; /* prominent but not harsh black */
                            line-height: 1;
                        }

                        .stat-icon {
                            width: 56px;
                            height: 56px;
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 26px;
                            flex-shrink: 0;
                        }

                        .stat-icon.blue { background: #e3f2fd; }
                        .stat-icon.green { background: #e8f5e9; }
                        .stat-icon.purple { background: #f3e5f5; }
                        .stat-icon.teal { background: #e0f2f1; }

                        /* Empty state card */
                        .empty-card.card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(2,6,23,0.04); margin: 12px 0; }
                        .empty-icon { width:72px; height:72px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:34px; background: linear-gradient(135deg,#eef2ff 0%, #e6fffa 100%); color:#044E54; margin:auto; }
                    </style>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Jadwal</h3>
                            <div class="stat-value"><?php echo $stats['total']; ?></div>
                        </div>
                        <div class="stat-icon purple">📅</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Terjadwal</h3>
                            <div class="stat-value"><?php echo $stats['terjadwal']; ?></div>
                        </div>
                        <div class="stat-icon blue">⏰</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Imunisasi</h3>
                            <div class="stat-value"><?php echo $stats['imunisasi']; ?></div>
                        </div>
                        <div class="stat-icon green">💉</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Selesai</h3>
                            <div class="stat-value"><?php echo $stats['selesai']; ?></div>
                        </div>
                        <div class="stat-icon teal">✔️</div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-tabs">
                        <a href="?id_balita=<?php echo $selected_balita; ?>" 
                           class="filter-tab <?php echo !$filter_jenis && !$filter_status ? 'active' : ''; ?>">
                            Semua
                        </a>
                        <a href="?id_balita=<?php echo $selected_balita; ?>&jenis=imunisasi" 
                           class="filter-tab <?php echo $filter_jenis == 'imunisasi' ? 'active' : ''; ?>">
                            <i class="fas fa-syringe"></i> Imunisasi
                        </a>
                        <a href="?id_balita=<?php echo $selected_balita; ?>&jenis=konsultasi" 
                           class="filter-tab <?php echo $filter_jenis == 'konsultasi' ? 'active' : ''; ?>">
                            <i class="fas fa-user-doctor"></i> Konsultasi
                        </a>
                        <a href="?id_balita=<?php echo $selected_balita; ?>&status=terjadwal" 
                           class="filter-tab <?php echo $filter_status == 'terjadwal' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i> Terjadwal
                        </a>
                        <a href="?id_balita=<?php echo $selected_balita; ?>&status=selesai" 
                           class="filter-tab <?php echo $filter_status == 'selesai' ? 'active' : ''; ?>">
                            <i class="fas fa-check"></i> Selesai
                        </a>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Jadwal List -->
                    <div>
                        <?php if (count($jadwal_list) > 0): ?>
                            <div class="jadwal-list">
                                <?php foreach ($jadwal_list as $jadwal):
                                    $status_info = getStatusBadge($jadwal['status']);
                                ?>
                                    <div class="jadwal-card" data-id="<?php echo $jadwal['id_jadwal']; ?>">
                                        <div class="card-header">
                                            <div>
                                                <div class="jadwal-for">Untuk: <?php echo htmlspecialchars($jadwal['nama_balita'] ?? 'Balita'); ?></div>
                                                <div class="jadwal-type type-<?php echo getJenisColor($jadwal['jenis']); ?>">
                                                    <i class="fas <?php echo getJenisIcon($jadwal['jenis']); ?>"></i>
                                                    <?php echo ucfirst($jadwal['jenis']); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="status-badge badge-<?php echo $status_info['class']; ?>"><?php echo $status_info['text']; ?></span>
                                            </div>
                                        </div>

                                        <div class="card-body">
                                            <h3 class="jadwal-title"><?php echo htmlspecialchars($jadwal['judul'] ?? ucfirst($jadwal['jenis'])); ?></h3>
                                            <?php $jadwal_deskripsi = $jadwal['deskripsi'] ?? $jadwal['catatan_hasil'] ?? ''; ?>
                                            <?php if (!empty($jadwal_deskripsi)): ?>
                                                <p class="jadwal-desc"><?php echo htmlspecialchars($jadwal_deskripsi); ?></p>
                                            <?php endif; ?>

                                            <div class="jadwal-meta">
                                                <div class="meta-item"><i class="far fa-calendar"></i><span><?php echo formatTanggal($jadwal['tanggal']); ?></span></div>
                                                <div class="meta-item"><i class="far fa-clock"></i><span><?php echo formatWaktu($jadwal['tanggal']); ?> WIB</span></div>
                                                <div class="meta-item"><i class="fas fa-location-dot"></i><span><?php echo htmlspecialchars($jadwal['lokasi']); ?></span></div>
                                            </div>
                                        </div>

                                        <div class="jadwal-actions">
                                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'tenaga_kesehatan'): ?>
                                                <button class="action-btn-sm" onclick="editJadwal(<?php echo $jadwal['id_jadwal']; ?>)" title="Edit" style="background:#e0e7ff; color:#3730a3;"><i class="fas fa-edit"></i></button>
                                                <button class="action-btn-sm" onclick="deleteJadwal(<?php echo $jadwal['id_jadwal']; ?>)" title="Hapus" style="background:#fee2e2; color:#dc2626;"><i class="fas fa-trash"></i></button>
                                            <?php else: ?>
                                                <?php if ($jadwal['status'] == 'terjadwal'): ?>
                                                    <button class="action-btn-sm" onclick="markComplete(<?php echo $jadwal['id_jadwal']; ?>)" title="Tandai Selesai" style="background:#dcfce7; color:#16a34a;"><i class="fas fa-check"></i> Selesai</button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                                <div class="empty-card card">
                                    <div style="text-align:center; padding:28px 18px;">
                                        <div class="empty-icon" style="margin:0 auto 12px;">
                                            📅
                                        </div>
                                        <h3 style="color:#111827; margin-bottom:8px;">Belum ada jadwal</h3>
                                        <p style="color:#64748b; margin-bottom:16px;">Tambahkan jadwal imunisasi atau konsultasi untuk balita Anda saja</p>
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'tenaga_kesehatan'): ?>
                                            <button class="btn btn-primary" onclick="openTambahModal()" style="padding:8px 18px; border-radius:20px; border:none!important; box-shadow:none!important;"> 
                                                <i class="fas fa-plus" style="margin-right:8px;"></i> Tambah Jadwal
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <aside>
                        <!-- Jadwal Mendatang -->
                        <?php if (count($upcoming_list) > 0): ?>
                            <div class="sidebar-widget">
                                <div style="display:flex; justify-content:flex-start; align-items:center; margin-bottom:8px;">
                                    <h3 class="widget-title" style="margin:0; display:flex; gap:8px; align-items:center;">
                                        <i class="fas fa-bell"></i>
                                        Jadwal Mendatang
                                    </h3>
                                </div>
                                <?php foreach ($upcoming_list as $upcoming): ?>
                                            <div class="upcoming-item">
                                                <div class="upcoming-left">
                                                    <div class="upcoming-date">
                                                        <span class="date-icon">📅</span>
                                                        <div class="date-text"><?php echo formatTanggal($upcoming['tanggal']); ?> - <?php echo formatWaktu($upcoming['tanggal']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="upcoming-body">
                                                    <div class="upcoming-title"><?php echo htmlspecialchars($upcoming['judul'] ?? ucfirst($upcoming['jenis'])); ?></div>
                                                    <div class="upcoming-sub" style="font-size:13px; color:#64748b; margin-bottom:6px;"><?php echo htmlspecialchars($upcoming['nama_balita'] ?? ''); ?></div>
                                                    <div class="upcoming-location"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($upcoming['lokasi']); ?></div>
                                                </div>
                                            </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Rekomendasi Imunisasi -->
                        <?php if (isset($rekomendasi_list) && count($rekomendasi_list) > 0): ?>
                            <div class="sidebar-widget">
                                <h3 class="widget-title">
                                    <i class="fas fa-lightbulb"></i>
                                    Rekomendasi Imunisasi
                                </h3>
                                <?php foreach ($rekomendasi_list as $rek): ?>
                                    <div class="rekomendasi-item">
                                        <div class="rekomendasi-icon">
                                            <i class="fas fa-syringe"></i>
                                        </div>
                                        <div class="rekomendasi-content">
                                            <div class="rekomendasi-name"><?php echo htmlspecialchars($rek['nama_imunisasi']); ?></div>
                                            <div class="rekomendasi-age">Usia: <?php echo $rek['usia_bulan']; ?> bulan</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-baby"></i>
                    <h3>Belum ada data balita</h3>
                    <p>Silakan tambahkan data balita terlebih dahulu untuk mengelola jadwal</p>
                    <a href="pertumbuhan.php" class="btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i>
                        <span>Tambah Data Balita</span>
                    </a>
                </div>
            <?php endif; ?>
    </main>

    <!-- Modal Tambah Jadwal -->
    <div id="tambahJadwal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Tambah Jadwal Baru</h2>
            </div>
            
            <form id="formTambahJadwal" method="POST" action="jadwal-handler.php">
                <input type="hidden" name="action" value="tambah" id="formAction">
                <input type="hidden" name="id" value="" id="formId">
                <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'tenaga_kesehatan' || $_SESSION['role'] === 'admin')): ?>
                    <div class="form-group">
                        <label class="form-label">Pilih Balita</label>
                        <select name="id_balita" id="selectBalita" class="form-select" required>
                            <option value="">-- Pilih Balita --</option>
                            <?php foreach ($balita_list as $b): ?>
                                <option value="<?php echo $b['id_balita']; ?>"><?php echo htmlspecialchars($b['nama_balita'] . ' (' . ($b['jenis_kelamin'] ?? '') . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="id_balita" value="<?php echo $selected_balita; ?>" id="selectBalitaHidden">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Jenis Jadwal</label>
                    <select name="jenis" id="inputJenis" class="form-select" required>
                        <option value="">Pilih jenis...</option>
                        <option value="imunisasi">Imunisasi</option>
                        <option value="konsultasi">Konsultasi</option>
                        <option value="pemeriksaan">Pemeriksaan</option>
                        <option value="posyandu">Posyandu</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Judul</label>
                    <input type="text" name="judul" id="inputJudul" class="form-input" placeholder="Contoh: Imunisasi DPT" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Tanggal & Waktu</label>
                    <input type="datetime-local" name="tanggal" id="inputTanggal" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="lokasi" id="inputLokasi" class="form-input" placeholder="Contoh: Posyandu Melati 3" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi (Opsional)</label>
                    <textarea name="deskripsi" id="inputDeskripsi" class="form-textarea" placeholder="Catatan tambahan..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('tambahJadwal')">Batal</button>
                    <button type="submit" class="btn-primary" id="formSubmit">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openTambahModal() {
            // reset form for tambah
            document.getElementById('modalTitle').innerText = 'Tambah Jadwal Baru';
            document.getElementById('formAction').value = 'tambah';
            document.getElementById('formId').value = '';
            document.getElementById('formSubmit').innerText = 'Simpan Jadwal';
            // clear inputs
            document.getElementById('inputJenis').value = '';
            document.getElementById('inputJudul').value = '';
            document.getElementById('inputTanggal').value = '';
            document.getElementById('inputLokasi').value = '';
            document.getElementById('inputDeskripsi').value = '';
            // if select exists, set to current selected balita if any
            var sel = document.getElementById('selectBalita');
            if (sel) {
                sel.value = '<?php echo $selected_balita !== null ? $selected_balita : ''; ?>';
            }
            openModal('tambahJadwal');
        }

        function markComplete(id) {
            if (confirm('Tandai jadwal ini sebagai selesai?')) {
                window.location.href = 'jadwal-handler.php?action=complete&id=' + id + '&id_balita=<?php echo $selected_balita; ?>';
            }
        }

        function deleteJadwal(id) {
            if (confirm('Apakah Anda yakin ingin menghapus jadwal ini?')) {
                window.location.href = 'jadwal-handler.php?action=delete&id=' + id + '&id_balita=<?php echo $selected_balita; ?>';
            }
        }

        function editJadwal(id) {
            // find card with data-id
            var card = document.querySelector('.jadwal-card[data-id="' + id + '"]');
            if (!card) return alert('Data jadwal tidak ditemukan');

            var id_balita = card.getAttribute('data-id_balita') || '';
            var jenis = card.getAttribute('data-jenis') || '';
            var judul = card.getAttribute('data-judul') || '';
            var tanggal = card.getAttribute('data-tanggal') || '';
            var lokasi = card.getAttribute('data-lokasi') || '';
            var deskripsi = card.getAttribute('data-deskripsi') || '';

            // set form to edit mode
            document.getElementById('modalTitle').innerText = 'Edit Jadwal';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = id;
            document.getElementById('formSubmit').innerText = 'Perbarui Jadwal';

            // set balita select or hidden
            var sel = document.getElementById('selectBalita');
            if (sel) {
                sel.value = id_balita;
            } else {
                var hid = document.getElementById('selectBalitaHidden');
                if (hid) hid.value = id_balita;
            }

            document.getElementById('inputJenis').value = jenis;
            document.getElementById('inputJudul').value = judul;
            // convert 'YYYY-MM-DD HH:MM:SS' to 'YYYY-MM-DDTHH:MM'
            if (tanggal) {
                var dt = tanggal.replace(' ', 'T');
                // remove seconds if present
                dt = dt.replace(/:\d{2}$/, '');
                document.getElementById('inputTanggal').value = dt;
            } else {
                document.getElementById('inputTanggal').value = '';
            }
            document.getElementById('inputLokasi').value = lokasi;
            document.getElementById('inputDeskripsi').value = deskripsi;

            openModal('tambahJadwal');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>