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

// Get statistik
// - If no specific balita selected (null) => global counts
// - If a specific balita selected (>0) => compute counts for that balita only
// - Otherwise (fallback) use the existing filtered whereClause
if ($selected_balita === null) {
    try {
        $stmt_stats = $conn->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'terjadwal' THEN 1 ELSE 0 END) as terjadwal,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
            SUM(CASE WHEN LOWER(jenis) LIKE '%imunisasi%' THEN 1 ELSE 0 END) as imunisasi,
            SUM(CASE WHEN LOWER(jenis) LIKE '%konsultasi%' THEN 1 ELSE 0 END) as konsultasi
            FROM jadwal");
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stats = ['total' => 0, 'terjadwal' => 0, 'selesai' => 0, 'imunisasi' => 0, 'konsultasi' => 0];
    }
} elseif (is_numeric($selected_balita) && (int)$selected_balita > 0) {
    // Specific balita counts
    try {
        $stmt_stats = $conn->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'terjadwal' THEN 1 ELSE 0 END) as terjadwal,
            SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
            SUM(CASE WHEN LOWER(jenis) LIKE '%imunisasi%' THEN 1 ELSE 0 END) as imunisasi,
            SUM(CASE WHEN LOWER(jenis) LIKE '%konsultasi%' THEN 1 ELSE 0 END) as konsultasi
            FROM jadwal WHERE id_balita = ?");
        $stmt_stats->execute([(int)$selected_balita]);
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stats = ['total' => 0, 'terjadwal' => 0, 'selesai' => 0, 'imunisasi' => 0, 'konsultasi' => 0];
    }
} else {
    // fallback to previous filtered behavior
    $query_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'terjadwal' THEN 1 ELSE 0 END) as terjadwal,
                SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN LOWER(jenis) LIKE '%imunisasi%' THEN 1 ELSE 0 END) as imunisasi,
                SUM(CASE WHEN LOWER(jenis) LIKE '%konsultasi%' THEN 1 ELSE 0 END) as konsultasi
                FROM jadwal 
                WHERE " . $whereClause;
    $stmt_stats = $conn->prepare($query_stats);
    try {
        $stmt_stats->execute($params);
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stats = ['total' => 0, 'terjadwal' => 0, 'selesai' => 0, 'imunisasi' => 0, 'konsultasi' => 0];
    }
}

// Get jadwal mendatang (7 hari ke depan)
$query_upcoming_base = "SELECT * FROM jadwal WHERE status = 'terjadwal' AND tanggal BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
if ($selected_balita !== null && $selected_balita > 0) {
    $query_upcoming = $query_upcoming_base . " AND id_balita = ? ORDER BY tanggal ASC LIMIT 3";
    $stmt_upcoming = $conn->prepare($query_upcoming);
    try {
        $stmt_upcoming->execute([$selected_balita]);
        $upcoming_list = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $upcoming_list = [];
    }
} else {
    // For nakes/admin showing all upcoming
    $query_upcoming = $query_upcoming_base . " ORDER BY tanggal ASC LIMIT 3";
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background: white;
            padding: 24px 16px;
            box-shadow: 2px 0 8px rgba(0,0,0,0.04);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            text-decoration: none;
            color: #64748b;
            transition: all 0.2s;
            font-size: 14px;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
            color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .nav-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 20px 0;
        }

        .nav-link.logout {
            color: #ef4444;
        }

        .nav-link.logout:hover {
            background: #fef2f2;
        }

        /* Main Content */
        .main-content {
            margin-left: 240px;
            flex: 1;
            padding: 24px 40px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .search-bar {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-bar::before {
            content: '\f002';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .lang-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #64748b;
            cursor: pointer;
        }

        .user-info-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-details {
            text-align: right;
        }

        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .user-details p {
            font-size: 12px;
            color: #64748b;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #64748b;
        }

        /* Balita Selector */
        .balita-selector {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .balita-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .balita-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .balita-details h3 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .balita-details p {
            font-size: 13px;
            color: #64748b;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-icon.blue { background: #dbeafe; color: #1e40af; }
        .stat-icon.green { background: #d1fae5; color: #065f46; }
        .stat-icon.purple { background: #f3e8ff; color: #6b21a8; }
        .stat-icon.yellow { background: #fef3c7; color: #92400e; }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .filter-tabs {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
            border-color: transparent;
            color: white;
        }

        /* Content Layout */
        .content-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }

        /* Jadwal List */
        .jadwal-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .jadwal-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s;
        }

        .jadwal-card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }

        .jadwal-header {
            padding: 20px;
            border-left: 4px solid;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .jadwal-header.imunisasi { border-color: #3b82f6; }
        .jadwal-header.konsultasi { border-color: #10b981; }
        .jadwal-header.pemeriksaan { border-color: #06b6d4; }
        .jadwal-header.posyandu { border-color: #f59e0b; }

        .jadwal-info {
            flex: 1;
        }

        .jadwal-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .type-primary { background: #dbeafe; color: #1e40af; }
        .type-success { background: #d1fae5; color: #065f46; }
        .type-info { background: #cffafe; color: #155e75; }
        .type-warning { background: #fef3c7; color: #92400e; }

        .jadwal-title {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .jadwal-desc {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .jadwal-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 13px;
            color: #64748b;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-item i {
            color: #94a3b8;
        }

        .jadwal-status {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f1f5f9; color: #64748b; }

        .jadwal-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .action-btn-sm {
            width: 32px;
            height: 32px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn-sm:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        /* Sidebar Widgets */
        .sidebar-widget {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .widget-title {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .upcoming-item {
            padding: 16px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 12px;
            border-left: 3px solid #3b82f6;
        }

        .upcoming-item:last-child {
            margin-bottom: 0;
        }

        .upcoming-date {
            font-size: 12px;
            color: #3b82f6;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .upcoming-title {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .upcoming-location {
            font-size: 12px;
            color: #64748b;
        }

        /* Rekomendasi List */
        .rekomendasi-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .rekomendasi-item:last-child {
            margin-bottom: 0;
        }

        .rekomendasi-icon {
            width: 40px;
            height: 40px;
            background: #dbeafe;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e40af;
            flex-shrink: 0;
        }

        .rekomendasi-content {
            flex: 1;
        }

        .rekomendasi-name {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .rekomendasi-age {
            font-size: 12px;
            color: #64748b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #94a3b8;
            font-size: 14px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 32px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 24px;
        }

        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .btn-secondary {
            padding: 10px 20px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        @media (max-width: 1024px) {
            .content-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar .logo-text,
            .sidebar .nav-link span {
                display: none;
            }

            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Cari jadwal imunisasi atau konsultasi...">
                </div>

                <div class="user-profile">
                    <button class="lang-btn">
                        <i class="fas fa-globe"></i>
                        <span>ID</span>
                    </button>

                    <div class="user-info-header">
                        <div class="user-details">
                            <h4><?php echo htmlspecialchars($user['nama'] ?? ($_SESSION['nama'] ?? '')); ?></h4>
                            <p><?php echo htmlspecialchars($user['role_label'] ?? 'Orang Tua'); ?></p>
                        </div>
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Header -->
            <div class="page-header">
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
                        <button class="btn-primary" onclick="openTambahModal()">
                            <i class="fas fa-plus"></i>
                            <span>Tambah Jadwal</span>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Total Jadwal</span>
                            <div class="stat-icon blue">
                                <i class="fas fa-calendar"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Terjadwal</span>
                            <div class="stat-icon yellow">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['terjadwal']; ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Imunisasi</span>
                            <div class="stat-icon purple">
                                <i class="fas fa-syringe"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['imunisasi']; ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-label">Selesai</span>
                            <div class="stat-icon green">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $stats['selesai']; ?></div>
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

                <!-- Content Layout -->
                <div class="content-layout">
                    <!-- Jadwal List -->
                    <div>
                        <?php if (count($jadwal_list) > 0): ?>
                            <div class="jadwal-list">
                                <?php foreach ($jadwal_list as $jadwal): 
                                    $status_info = getStatusBadge($jadwal['status']);
                                ?>
                                     <div class="jadwal-card" 
                                         data-id="<?php echo $jadwal['id_jadwal']; ?>"
                                         data-id_balita="<?php echo $jadwal['id_balita']; ?>"
                                         data-jenis="<?php echo htmlspecialchars($jadwal['jenis']); ?>"
                                         data-judul="<?php echo htmlspecialchars($jadwal['judul'] ?? ''); ?>"
                                         data-tanggal="<?php echo htmlspecialchars($jadwal['tanggal']); ?>"
                                         data-lokasi="<?php echo htmlspecialchars($jadwal['lokasi'] ?? ''); ?>"
                                         data-deskripsi="<?php echo htmlspecialchars($jadwal['deskripsi'] ?? $jadwal['catatan_hasil'] ?? ''); ?>">
                                        <div class="jadwal-header <?php echo $jadwal['jenis']; ?>">
                                            <div class="jadwal-info">
                                                <span class="jadwal-type type-<?php echo getJenisColor($jadwal['jenis']); ?>">
                                                    <i class="fas <?php echo getJenisIcon($jadwal['jenis']); ?>"></i>
                                                    <?php echo ucfirst($jadwal['jenis']); ?>
                                                </span>
                                                
                                                <h3 class="jadwal-title"><?php echo htmlspecialchars($jadwal['judul'] ?? ucfirst($jadwal['jenis'])); ?></h3>
                                                
                                                <?php $jadwal_deskripsi = $jadwal['deskripsi'] ?? $jadwal['catatan_hasil'] ?? ''; ?>
                                                <?php if (!empty($jadwal_deskripsi)): ?>
                                                    <p class="jadwal-desc"><?php echo htmlspecialchars($jadwal_deskripsi); ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="jadwal-meta">
                                                    <div class="meta-item">
                                                        <i class="far fa-calendar"></i>
                                                        <span><?php echo formatTanggal($jadwal['tanggal']); ?></span>
                                                    </div>
                                                    <div class="meta-item">
                                                        <i class="far fa-clock"></i>
                                                        <span><?php echo formatWaktu($jadwal['tanggal']); ?> WIB</span>
                                                    </div>
                                                    <div class="meta-item">
                                                        <i class="fas fa-location-dot"></i>
                                                        <span><?php echo htmlspecialchars($jadwal['lokasi']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="jadwal-status">
                                                <span class="status-badge badge-<?php echo $status_info['class']; ?>">
                                                    <?php echo $status_info['text']; ?>
                                                </span>
                                                
                                                <div class="jadwal-actions">
                                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'tenaga_kesehatan'): ?>
                                                        <button class="action-btn-sm" onclick="editJadwal(<?php echo $jadwal['id_jadwal']; ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="action-btn-sm" onclick="deleteJadwal(<?php echo $jadwal['id_jadwal']; ?>)" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <?php if ($jadwal['status'] == 'terjadwal'): ?>
                                                            <button class="action-btn-sm" onclick="markComplete(<?php echo $jadwal['id_jadwal']; ?>)" title="Tandai Selesai">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-xmark"></i>
                                    <h3>Belum ada jadwal</h3>
                                    <p>Tambahkan jadwal imunisasi atau konsultasi untuk balita Anda saja</p>
                                </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <aside>
                        <!-- Jadwal Mendatang -->
                        <?php if (count($upcoming_list) > 0): ?>
                            <div class="sidebar-widget">
                                <h3 class="widget-title">
                                    <i class="fas fa-bell"></i>
                                    Jadwal Mendatang
                                </h3>
                                <?php foreach ($upcoming_list as $upcoming): ?>
                                    <div class="upcoming-item">
                                        <div class="upcoming-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo formatTanggal($upcoming['tanggal']); ?> • <?php echo formatWaktu($upcoming['tanggal']); ?>
                                        </div>
                                        <div class="upcoming-title"><?php echo htmlspecialchars($upcoming['judul'] ?? ucfirst($upcoming['jenis'])); ?></div>
                                        <div class="upcoming-location">
                                            <i class="fas fa-location-dot"></i>
                                            <?php echo htmlspecialchars($upcoming['lokasi']); ?>
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
    </div>

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