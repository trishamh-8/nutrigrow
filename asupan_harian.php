<?php
// asupan_harian.php - CRUD Asupan Harian (Lengkap dalam 1 file)
require_once 'config.php';
require_once 'auth.php';

$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];
$message = '';
$error = '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list'; // Mode: list, add, edit
$edit_data = null;

// Handle messages from redirects
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'add_success':
            $message = "Data asupan berhasil ditambahkan!";
            break;
        case 'update_success':
            $message = "Data asupan berhasil diperbarui!";
            break;
        case 'delete_success':
            $message = "Data asupan berhasil dihapus!";
            break;
    }
}

// Get user info
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
$query_balita = "SELECT id_balita, nama_balita, tanggal_lahir, jenis_kelamin FROM balita WHERE id_akun = ? ORDER BY nama_balita ASC";
$stmt = $conn->prepare($query_balita);
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result_balita = $stmt->get_result();
$balita_list = $result_balita->fetch_all(MYSQLI_ASSOC);

// Get selected balita or use first one
$selected_balita_id = isset($_GET['id_balita']) ? $_GET['id_balita'] : ($balita_list[0]['id_balita'] ?? null);

// Get selected balita details
$stmt = $conn->prepare("SELECT * FROM balita WHERE id_balita = ? AND id_akun = ?");
$stmt->bind_param("ii", $selected_balita_id, $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$balita = $result->fetch_assoc();

// ========== PROSES HAPUS DATA ==========
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_asupan = $_GET['id'];
    
    // Verify that this asupan belongs to a balita owned by the logged-in user
    $query_verify = "SELECT ah.id_asupan 
                     FROM asupan_harian ah
                     JOIN balita b ON ah.id_balita = b.id_balita
                     WHERE ah.id_asupan = ? 
                     AND b.id_akun = ? 
                     AND ah.id_balita = ?";
    
    $stmt = $conn->prepare($query_verify);
    $stmt->bind_param("iii", $id_asupan, $id_akun, $selected_balita_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $query_delete = "DELETE FROM asupan_harian WHERE id_asupan = ?";
        $stmt = $conn->prepare($query_delete);
        $stmt->bind_param("i", $id_asupan);
        
        if ($stmt->execute()) {
            $message = "Data asupan berhasil dihapus!";
        } else {
            $error = "Gagal menghapus data!";
        }
    }
}

// ========== PROSES TAMBAH/UPDATE DATA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal_catatan = $_POST['tanggal_catatan'];
    $waktu_makan = $_POST['waktu_makan'];
    $jenis_makanan = $_POST['jenis_makanan'];
    $porsi = $_POST['porsi'];
    $kalori_total = isset($_POST['kalori_total']) ? $_POST['kalori_total'] : 0;
    $protein = isset($_POST['protein']) ? $_POST['protein'] : 0;
    $karbohidrat = isset($_POST['karbohidrat']) ? $_POST['karbohidrat'] : 0;
    $lemak = isset($_POST['lemak']) ? $_POST['lemak'] : 0;
    $id_asupan_edit = isset($_POST['id_asupan']) ? $_POST['id_asupan'] : null;
    
    // Validasi input
    if (empty($tanggal_catatan) || empty($waktu_makan) || empty($jenis_makanan) || 
        empty($porsi) || $kalori_total < 0) {
        $error = "Semua field wajib diisi dengan benar!";
        $mode = $id_asupan_edit ? 'edit' : 'add';
    } else {
        if ($id_asupan_edit) {
            // UPDATE data (include karbohidrat & lemak)
            $query_update = "UPDATE asupan_harian 
                           SET tanggal_catatan = ?, 
                               waktu_makan = ?, 
                               jenis_makanan = ?, 
                               porsi = ?, 
                               kalori_total = ?, 
                               protein = ?,
                               karbohidrat = ?,
                               lemak = ?
                           WHERE id_asupan = ? AND id_balita = ?";

            $stmt = $conn->prepare($query_update);
            // types: s(tanggal), s(waktu), s(jenis), s(porsi), d(kalori), d(protein), d(karbo), d(lemak), i(id_asupan), i(id_balita)
            $stmt->bind_param("ssssddddii", $tanggal_catatan, $waktu_makan, $jenis_makanan, $porsi, $kalori_total, $protein, $karbohidrat, $lemak, $id_asupan_edit, $selected_balita_id);

            if ($stmt->execute()) {
                header("Location: asupan_harian.php?id_balita=" . $selected_balita_id . "&message=update_success");
                exit;
            } else {
                $error = "Gagal memperbarui data: " . $conn->error;
                $mode = 'edit';
            }
        } else {
            // INSERT data baru (include karbohidrat & lemak)
            $query_insert = "INSERT INTO asupan_harian 
                            (id_balita, tanggal_catatan, waktu_makan, jenis_makanan, porsi, kalori_total, protein, karbohidrat, lemak) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($query_insert);
            // types: i(id_balita), s(tanggal), s(waktu), s(jenis), s(porsi), d(kalori), d(protein), d(karbo), d(lemak)
            $stmt->bind_param("issssdddd", $selected_balita_id, $tanggal_catatan, $waktu_makan, $jenis_makanan, $porsi, $kalori_total, $protein, $karbohidrat, $lemak);

            if ($stmt->execute()) {
                header("Location: asupan_harian.php?id_balita=" . $selected_balita_id . "&message=add_success");
                exit;
            } else {
                $error = "Gagal menyimpan data: " . $conn->error;
                $mode = 'add';
            }
        }
    }
}

// ========== MENENTUKAN MODE TAMPILAN ==========
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'add') {
        $mode = 'add';
    } elseif ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $mode = 'edit';
        $id_asupan = $_GET['id'];
        
        // Get data for edit with security check
        $query_edit = "SELECT ah.* 
                       FROM asupan_harian ah
                       JOIN balita b ON ah.id_balita = b.id_balita
                       WHERE ah.id_asupan = ? 
                       AND ah.id_balita = ? 
                       AND b.id_akun = ?";
        $stmt = $conn->prepare($query_edit);
        $stmt->bind_param("iii", $id_asupan, $selected_balita_id, $id_akun);
        $stmt->execute();
        $result_edit = $stmt->get_result();
        $edit_data = $result_edit->fetch_assoc();
        
        if (!$edit_data) {
            $error = "Data tidak ditemukan!";
            $mode = 'list';
        }
    }
}

// ========== AMBIL DATA UNTUK LIST ==========
if ($mode == 'list') {
    // Get today's intake data with security check
    $tanggal_hari_ini = date('Y-m-d');
    $query_asupan = "SELECT ah.*,
                     CASE ah.waktu_makan
                        WHEN 'sarapan' THEN 'Sarapan'
                        WHEN 'makan_siang' THEN 'Makan Siang'
                        WHEN 'makan_malam' THEN 'Makan Malam'
                        WHEN 'camilan' THEN 'Camilan'
                     END as waktu_makan_text
                     FROM asupan_harian ah
                     JOIN balita b ON ah.id_balita = b.id_balita
                     WHERE ah.id_balita = ? 
                     AND ah.tanggal_catatan = ? 
                     AND b.id_akun = ?
                     ORDER BY FIELD(ah.waktu_makan, 'sarapan', 'makan_siang', 'makan_malam', 'camilan')";
    $stmt = $conn->prepare($query_asupan);
    $stmt->bind_param("isi", $selected_balita_id, $tanggal_hari_ini, $id_akun);
    $stmt->execute();
    $result_asupan = $stmt->get_result();

    // Calculate today's totals with security check
    $query_total = "SELECT 
                    SUM(ah.kalori_total) as total_kalori,
                    SUM(ah.protein) as total_protein,
                    COUNT(*) as frekuensi
                    FROM asupan_harian ah
                    JOIN balita b ON ah.id_balita = b.id_balita
                    WHERE ah.id_balita = ? 
                    AND ah.tanggal_catatan = ?
                    AND b.id_akun = ?";
    $stmt = $conn->prepare($query_total);
    $stmt->bind_param("isi", $selected_balita_id, $tanggal_hari_ini, $id_akun);
    $stmt->execute();
    $result_total = $stmt->get_result();
    $totals = $result_total->fetch_assoc();

    $total_kalori = isset($totals['total_kalori']) ? $totals['total_kalori'] : 0;
    $total_protein = isset($totals['total_protein']) ? $totals['total_protein'] : 0;
    $frekuensi = isset($totals['frekuensi']) ? $totals['frekuensi'] : 0;

    // Target harian
    $target_kalori = 1200;
    $target_protein = 35;

    // Hitung persentase
    $persen_kalori = $target_kalori > 0 ? ($total_kalori / $target_kalori) * 100 : 0;
    $persen_protein = $target_protein > 0 ? ($total_protein / $target_protein) * 100 : 0;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asupan Harian - NutriGrow</title>
        <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            min-width: 100vw;
            overflow-x: hidden;
        }
        
        .container {
            display: flex;
            width: 100%;
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
            color: #0D9488;
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
        
        /* Main Content */
        .main-content {
            /* sidebar is 240px; keep consistent spacing */
            margin-left: 240px;
            flex: 1;
            padding: 30px;
            box-sizing: border-box;
            min-height: 100vh;
            width: calc(100% - 240px);
            max-width: 100%;
            background: transparent;
        }

        /* Responsive: collapse sidebar into normal flow under 992px */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 18px;
                width: 100%;
            }
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                box-shadow: none;
                padding-bottom: 10px;
            }
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
        
        /* User Info Styles */
        .user-info h4 {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin: 0;
        }
        
        .user-info p {
            font-size: 12px;
            color: #666;
            margin: 0;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-target {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .progress-bar {
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .progress-fill.blue { background: #4FC3F7; }
        .progress-fill.green { background: #66BB6A; }
        
        /* Content Card */
        .content-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            width: 100%;
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
            margin: 0;
        }
        
        .card-subtitle {
            font-size: 14px;
            color: #666;
            margin: 5px 0 0 0;
        }
        
        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            padding: 12px;
            background: #f9fafb;
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }
        
        .data-table td {
            padding: 12px;
            border-top: 1px solid #f0f0f0;
            color: #666;
            font-size: 14px;
        }
        
        .time-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .btn-icon:hover {
            filter: brightness(0.95);
        }

        /* Back Button */
        .btn-back {
            display: inline-flex;
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
        
        /* Form */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-label .required {
            color: #dc2626;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4FC3F7;
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }
        
        .btn:hover {
            filter: brightness(0.95);
        }
        
        @media (max-width: 1280px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px;
                width: calc(100vw - 200px);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .search-bar {
                display: none;
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

            <?php if ($mode == 'list'): ?>
            <!-- Page Title -->
            <div class="page-title">
                <h1>Asupan Harian</h1>
                <p class="page-subtitle">Catat dan pantau asupan makanan balita sehari-hari</p>
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
            <?php endif; ?>

            <?php if ($mode == 'list'): ?>
            <!-- ========== MODE LIST ========== -->
    

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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">🍽️</div>
                    <div class="stat-label">Total Kalori Hari Ini</div>
                    <div class="stat-value"><?php echo number_format($total_kalori, 0); ?> <span class="unit">kcal</span></div>
                    <div class="stat-target">Target: <?php echo $target_kalori; ?> kcal</div>
                    <div class="progress-bar">
                        <div class="progress-fill blue" style="width: <?php echo min($persen_kalori, 100); ?>%"></div>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-icon">🥩</div>
                    <div class="stat-label">Total Protein</div>
                    <div class="stat-value"><?php echo number_format($total_protein, 0); ?><span class="unit">g</span></div>
                    <div class="stat-target">Target: <?php echo $target_protein; ?>g</div>
                    <div class="progress-bar">
                        <div class="progress-fill green" style="width: <?php echo min($persen_protein, 100); ?>%"></div>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Frekuensi Makan</div>
                    <div class="stat-value"><?php echo $frekuensi; ?><span class="unit">x</span></div>
                    <div class="stat-target">Hari ini</div>
                </div>
            </div>

            <!-- Data Asupan -->
            <div class="content-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Daftar Asupan Hari Ini</h2>
                        <p class="card-subtitle"><?php echo formatHariIndo($tanggal_hari_ini) . ', ' . formatTanggalIndo($tanggal_hari_ini); ?></p>
                    </div>
                    <button class="btn btn-primary" onclick="location.href='asupan_harian.php?action=add&id_balita=<?php echo $selected_balita_id; ?>'">
                        <i class="fas fa-plus"></i> Tambah Asupan
                    </button>
                </div>

                <?php if ($result_asupan->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis Makanan</th>
                            <th>Porsi</th>
                            <th>Kalori</th>
                            <th>Protein</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result_asupan->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="time-badge">
                                    <?php echo $row['waktu_makan_text']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['jenis_makanan']); ?></td>
                            <td><?php echo $row['porsi']; ?> <?php echo strpos($row['jenis_makanan'], 'Susu') !== false ? 'ml' : 'porsi'; ?></td>
                            <td><strong><?php echo number_format($row['kalori_total'], 0); ?> kcal</strong></td>
                            <td><strong><?php echo number_format($row['protein'], 0); ?>g</strong></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-edit" onclick="location.href='asupan_harian.php?action=edit&id=<?php echo $row['id_asupan']; ?>&id_balita=<?php echo $selected_balita_id; ?>'" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-delete" onclick="hapusAsupan(<?php echo $row['id_asupan']; ?>, <?php echo $selected_balita_id; ?>)" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <i class="fas fa-utensils" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>Belum ada catatan asupan hari ini</p>
                    <button class="btn btn-primary" style="margin-top: 15px;" onclick="location.href='asupan_harian.php?action=add&id_balita=<?php echo $selected_balita_id; ?>'">
                        <i class="fas fa-plus"></i> Tambah Asupan Pertama
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($mode == 'add' || $mode == 'edit'): ?>
            <!-- ========== MODE ADD / EDIT ========== -->
            
            <!-- Back Button -->
            <div style="margin-bottom: 20px;">
                <a href="asupan_harian.php?id_balita=<?php echo $selected_balita_id; ?>" class="btn-back" onclick="return confirmBack()">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali</span>
                </a>
            </div>

            <!-- Form -->
            <div class="content-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?php echo $mode == 'add' ? 'Tambah' : 'Edit'; ?> Asupan Harian</h2>
                        <p class="card-subtitle"><?php echo $mode == 'add' ? 'Catat makanan dan minuman yang dikonsumsi balita' : 'Perbarui data asupan yang sudah tercatat'; ?></p>
                    </div>
                </div>
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php if ($mode == 'edit'): ?>
                    <input type="hidden" name="id_asupan" value="<?php echo $edit_data['id_asupan']; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="id_balita" value="<?php echo $selected_balita_id; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Nama Balita
                            </label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($balita['nama_balita']); ?>" readonly style="background: #f8fafc;">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Tanggal Catatan <span class="required">*</span>
                            </label>
                            <input type="date" name="tanggal_catatan" class="form-input" 
                                   value="<?php echo $mode == 'edit' ? $edit_data['tanggal_catatan'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Waktu Makan <span class="required">*</span>
                        </label>
                        <select name="waktu_makan" class="form-input" required>
                            <option value="sarapan" <?php echo ($mode == 'edit' && $edit_data['waktu_makan'] == 'sarapan') ? 'selected' : ''; ?>>Sarapan</option>
                            <option value="makan_siang" <?php echo ($mode == 'edit' && $edit_data['waktu_makan'] == 'makan_siang') ? 'selected' : ''; ?>>Makan Siang</option>
                            <option value="makan_malam" <?php echo ($mode == 'edit' && $edit_data['waktu_makan'] == 'makan_malam') ? 'selected' : ''; ?>>Makan Malam</option>
                            <option value="camilan" <?php echo ($mode == 'edit' && $edit_data['waktu_makan'] == 'camilan') ? 'selected' : ''; ?>>Camilan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Jenis Makanan/Minuman <span class="required">*</span>
                        </label>
                        <input type="text" name="jenis_makanan" class="form-input" 
                               placeholder="Contoh: Nasi Tim + Ayam" 
                               value="<?php echo $mode == 'edit' ? htmlspecialchars($edit_data['jenis_makanan']) : ''; ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Porsi <span class="required">*</span>
                            </label>
                            <input type="number" step="0.01" name="porsi" class="form-input" 
                                   placeholder="Contoh: 1 atau 200 (ml)" 
                                   value="<?php echo $mode == 'edit' ? $edit_data['porsi'] : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Kalori Total (kcal) <span class="required">*</span>
                            </label>
                            <input type="number" step="0.01" name="kalori_total" class="form-input" 
                                   placeholder="Contoh: 250" 
                                   value="<?php echo $mode == 'edit' ? $edit_data['kalori_total'] : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Protein (gram)
                            </label>
                            <input type="number" step="0.01" name="protein" class="form-input" 
                                   placeholder="Contoh: 15" 
                                   value="<?php echo $mode == 'edit' ? $edit_data['protein'] : '0'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Karbohidrat (gram)
                            </label>
                            <input type="number" step="0.01" name="karbohidrat" class="form-input" 
                                   placeholder="Contoh: 45" 
                                   value="<?php echo $mode == 'edit' ? $edit_data['karbohidrat'] : '0'; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Lemak (gram)
                        </label>
                        <input type="number" step="0.01" name="lemak" class="form-input" 
                               placeholder="Contoh: 10" 
                               value="<?php echo $mode == 'edit' ? $edit_data['lemak'] : '0'; ?>">
                    </div>

                    <div class="form-actions" style="justify-content: center;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $mode == 'add' ? 'Simpan' : 'Update'; ?> Asupan
                        </button>
                    </div>
                </form>
            </div>

            <?php endif; ?>
        </main>
    </div>

    <script>
    function hapusAsupan(id, balitaId) {
        if (confirm('Apakah Anda yakin ingin menghapus catatan asupan ini?')) {
            window.location.href = 'asupan_harian.php?action=delete&id=' + id + '&id_balita=' + balitaId;
        }
    }

    function confirmBack() {
        const form = document.querySelector('form');
        if (!form) return true;
        
        const hasChanges = Array.from(form.elements).some(element => {
            if (element.type === 'submit' || element.type === 'button') return false;
            if (element.type === 'radio' || element.type === 'checkbox') {
                return element.checked !== element.defaultChecked;
            }
            return element.value !== element.defaultValue;
        });
        
        if (hasChanges) {
            return confirm('Anda yakin ingin kembali? Data yang sudah diisi akan hilang.');
        }
        return true;
    }
    </script>
</body>
</html>

<?php $conn->close(); ?>