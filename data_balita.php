<?php
// data_balita.php - CRUD Data Identitas Balita (UC-004)
require_once 'config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Buat koneksi database
$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];
$message = '';
$error = '';
$mode = 'list'; // Mode: list, add, edit
$edit_data = null;

// Ensure $user is defined (some pages expect this)
$user = ['nama' => '', 'role' => $_SESSION['role'] ?? 'pengguna'];
// Optional redirect target (passed when opening the add form).
// If provided, after creating a new balita we will redirect to that target with the new id.
$redirect = '';
if (isset($_GET['redirect'])) $redirect = $_GET['redirect'];
if (isset($_POST['redirect'])) $redirect = $_POST['redirect'];
try {
    $s = $conn->prepare('SELECT nama FROM akun WHERE id_akun = ?');
    $s->bind_param('i', $id_akun);
    $s->execute();
    $r = $s->get_result();
    $rowu = $r->fetch_assoc();
    if ($rowu && !empty($rowu['nama'])) $user['nama'] = $rowu['nama'];
} catch (Exception $e) {
    // ignore
}

// ========== PROSES HAPUS DATA ==========
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_balita = $_GET['id'];
    // Only 'pengguna' can delete their balita
    if (($user['role'] ?? '') !== 'pengguna') {
        $error = 'Akses ditolak: hanya orang tua (pengguna) yang dapat menghapus balita.';
    } else {
        // Verifikasi bahwa balita ini milik user yang login
        $query_verify = "SELECT id_balita FROM balita WHERE id_balita = ? AND id_akun = ?";
        $stmt = $conn->prepare($query_verify);
        $stmt->bind_param("ii", $id_balita, $id_akun);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
    // Hapus data balita (akan menghapus data pertumbuhan terkait karena CASCADE)
    // Batasi penghapusan hanya pada baris milik akun yang login untuk menghindari
    // kondisi di mana id_balita tertukar dengan id_akun lain.
    $query_delete = "DELETE FROM balita WHERE id_balita = ? AND id_akun = ?";
    $stmt = $conn->prepare($query_delete);
    $stmt->bind_param("ii", $id_balita, $id_akun);
        
        if ($stmt->execute()) {
            $message = "Data balita berhasil dihapus beserta seluruh riwayat pertumbuhannya!";
        } else {
            $error = "Gagal menghapus data!";
        }
        } else {
            $error = "Data tidak ditemukan atau Anda tidak memiliki akses!";
        }
    }
}

// ========== PROSES TAMBAH/UPDATE DATA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Only 'pengguna' (parents) may add or update balita via this form
    if (($user['role'] ?? '') !== 'pengguna') {
        $error = 'Akses ditolak: Anda tidak memiliki izin untuk menambah atau mengedit data balita di halaman ini.';
        // keep mode as list to avoid processing
        $mode = 'list';
    } else {
    $nama_balita = trim($_POST['nama_balita']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $id_balita_edit = isset($_POST['id_balita']) ? $_POST['id_balita'] : null;
    
    $alamat_balita = trim($_POST['alamat_balita']);
    
    // Validasi input
    if (empty($nama_balita) || empty($tanggal_lahir) || empty($jenis_kelamin) || empty($alamat_balita)) {
        $error = "Semua field wajib diisi dengan benar!";
        $mode = $id_balita_edit ? 'edit' : 'add';
    } else {
        // Validasi format tanggal
        $date_check = DateTime::createFromFormat('Y-m-d', $tanggal_lahir);
        if (!$date_check || $date_check->format('Y-m-d') !== $tanggal_lahir) {
            $error = "Format tanggal tidak valid!";
            $mode = $id_balita_edit ? 'edit' : 'add';
        } else {
            // Validasi tanggal tidak boleh di masa depan
            if (strtotime($tanggal_lahir) > time()) {
                $error = "Tanggal lahir tidak boleh di masa depan!";
                $mode = $id_balita_edit ? 'edit' : 'add';
            } else {
                if ($id_balita_edit) {
                    // UPDATE data
                    $query_update = "UPDATE balita 
                                   SET nama_balita = ?, tanggal_lahir = ?, jenis_kelamin = ?, alamat_balita = ?
                                   WHERE id_balita = ? AND id_akun = ?";
                    
                    $stmt = $conn->prepare($query_update);
                    $stmt->bind_param("ssssii", $nama_balita, $tanggal_lahir, $jenis_kelamin, $alamat_balita,
                                    $id_balita_edit, $id_akun);
                    
                    if ($stmt->execute()) {
                        // Tandai bahwa perlu recalculate Z-Score jika ada perubahan tanggal lahir/jenis kelamin
                        // (Akan diimplementasikan di UC-005)
                        $message = "Data balita berhasil diperbarui! Data pertumbuhan akan dihitung ulang.";
                        $mode = 'list';
                    } else {
                        $error = "Gagal memperbarui data: " . $conn->error;
                        $mode = 'edit';
                    }
                } else {
                    // INSERT data baru
                    $query_insert = "INSERT INTO balita (id_akun, nama_balita, tanggal_lahir, jenis_kelamin, alamat_balita) 
                                    VALUES (?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($query_insert);
                    $stmt->bind_param("issss", $id_akun, $nama_balita, $tanggal_lahir, $jenis_kelamin, $alamat_balita);
                    
                            if ($stmt->execute()) {
                                // Newly inserted id (mysqli)
                                $newId = $conn->insert_id;
                                // If a redirect target was provided, build the target URL and redirect
                                if (!empty($_POST['redirect'])) {
                                    $target = $_POST['redirect'];
                                    // If the target contains a {id} placeholder, replace it
                                    if (strpos($target, '{id}') !== false) {
                                        $target = str_replace('{id}', $newId, $target);
                                    } else {
                                        // Append id_balita param
                                        if (strpos($target, '?') !== false) {
                                            $target .= '&id_balita=' . $newId;
                                        } else {
                                            $target .= '?id_balita=' . $newId;
                                        }
                                    }
                                    header('Location: ' . $target);
                                    exit;
                                }

                                $message = "Data balita berhasil ditambahkan!";
                                $mode = 'list';
                            } else {
                                $error = "Gagal menyimpan data: " . $conn->error;
                                $mode = 'add';
                            }
        }
                }
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
        $id_balita = $_GET['id'];
        
        // Ambil data untuk edit
        $query_edit = "SELECT * FROM balita WHERE id_balita = ? AND id_akun = ?";
        $stmt = $conn->prepare($query_edit);
        $stmt->bind_param("ii", $id_balita, $id_akun);
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
    if (($user['role'] ?? '') === 'pengguna') {
        $query_balita = "SELECT b.id_balita, b.nama_balita, b.tanggal_lahir, 
                         b.jenis_kelamin, b.alamat_balita, b.created_at,
                         TIMESTAMPDIFF(MONTH, b.tanggal_lahir, CURDATE()) as usia_bulan
                         FROM balita b 
                         WHERE b.id_akun = ?
                         ORDER BY b.created_at DESC";
        $stmt = $conn->prepare($query_balita);
        $stmt->bind_param("i", $id_akun);
        $stmt->execute();
        $result_balita = $stmt->get_result();
    } else {
        // Tenaga Kesehatan: show registered balita (all balita) for viewing only
        $query_balita = "SELECT b.id_balita, b.nama_balita, b.tanggal_lahir, 
                         b.jenis_kelamin, b.alamat_balita, b.created_at,
                         TIMESTAMPDIFF(MONTH, b.tanggal_lahir, CURDATE()) as usia_bulan,
                         a.nama as nama_ortu, a.id_akun as id_ortu_akun
                         FROM balita b
                         LEFT JOIN akun a ON b.id_akun = a.id_akun
                         ORDER BY b.created_at DESC";
        $result_balita = $conn->query($query_balita);
    }
}

// Fungsi untuk menghitung usia
function hitungUsia($tanggal_lahir) {
    $lahir = new DateTime($tanggal_lahir);
    $sekarang = new DateTime();
    $diff = $sekarang->diff($lahir);
    
    if ($diff->y > 0) {
        return $diff->y . " tahun " . $diff->m . " bulan";
    } else {
        return $diff->m . " bulan";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Identitas Balita - NutriGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/partials/common_head.php'; ?>
    <style>
        /* Wrapper layout matching body flex structure */
        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Balita card with enhanced gradient and spacing */
        .balita-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #f0fdf4 100%);
            border: 1px solid #e0f2fe;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(2,6,23,0.06), 0 0 20px rgba(79,195,247,0.08);
            margin-bottom: 0;
            width: 100%;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .balita-card:hover {
            box-shadow: 0 4px 20px rgba(2,6,23,0.1), 0 0 30px rgba(79,195,247,0.12);
            transform: translateY(-2px);
        }

        .balita-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .balita-avatar {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            flex-shrink: 0;
            margin-right: 16px;
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
        }

        .balita-info-block {
            flex: 1;
        }

        .balita-name { 
            font-size: 20px; 
            font-weight: 700; 
            color: #0f172a; 
            margin-bottom: 6px;
        }
        .balita-age { 
            color: #64748b; 
            font-size: 14px; 
            margin-bottom: 14px;
        }

        .balita-details-row { 
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-bottom: 18px;
            padding: 16px 0;
            border-top: 1px solid rgba(203,213,225,0.3);
            border-bottom: 1px solid rgba(203,213,225,0.3);
        }

        .balita-detail { 
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .detail-icon { 
            font-size: 20px; 
            flex-shrink: 0;
            margin-top: 2px;
        }
        .detail-content { 
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label { 
            font-size: 12px; 
            color: #64748b; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-value { 
            font-size: 15px; 
            color: #1e293b; 
            font-weight: 600;
        }

        .balita-actions { 
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .btn-icon-sm { 
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); 
            border: none; 
            padding: 10px 16px; 
            border-radius: 10px; 
            cursor: pointer; 
            color: white;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.2);
        }
        .btn-icon-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4);
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
        }
        .btn { 
            padding: 10px 18px; 
            border-radius: 10px; 
            cursor: pointer; 
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
            color: #fff; 
            border: none;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }
        .btn-secondary { 
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); 
            color: white; 
            border: none;
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.2);
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4);
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
        }

        /* Form styling */
        .form-container {
            width: 100%;
            box-sizing: border-box;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 24px;
        }

        .form-label {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .required {
            color: #dc2626;
            font-size: 14px;
        }

        .form-input,
        .form-select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: white;
            color: #1e293b;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
            background: #f0f9ff;
        }

        .form-input::placeholder {
            color: #94a3b8;
        }

        .form-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        .photo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px;
            background: linear-gradient(135deg, #f0f9ff 0%, #f0fdf4 100%);
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
        }

        .photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-preview:hover {
            transform: scale(1.05);
        }

        .photo-link {
            color: #06b6d4;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .photo-link:hover {
            color: #0891b2;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        /* Ensure content-card/empty-state stays consistent */
        .content-card .empty-state { text-align:center; padding:40px; }
        .empty-icon { font-size:48px; margin-bottom:16px; }

        /* Alert styling */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #dbeafe;
            color: #0c4a6e;
            border: 1px solid #bae6fd;
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fecaca;
        }

        /* Back button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #06b6d4;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #f0f9ff;
            color: #0891b2;
        }

        /* Page header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .page-header p {
            font-size: 15px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php if ($mode == 'list'): ?>
            <!-- ========== MODE LIST ========== -->
            
            <!-- Back Button -->
            <div style="margin-bottom: 20px;">
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Dashboard</span>
                </a>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1>Kelola Identitas Balita</h1>
                <p>Tambah, edit, dan kelola data identitas balita Anda</p>
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

            <!-- Add Button -->
                    <?php if (($user['role'] ?? '') == 'pengguna'): ?>
                    <?php
                        // Build add URL optionally preserving redirect target
                        $addUrl = 'data_balita.php?action=add';
                        if (!empty($redirect)) {
                            $addUrl .= '&redirect=' . urlencode($redirect);
                        }
                    ?>
                    <div style="margin-bottom: 20px;">
                        <a class="btn btn-primary" href="<?php echo $addUrl; ?>" style="border:none!important; box-shadow:none!important;">
                            <i class="fas fa-plus"></i> Tambah Balita
                        </a>
                    </div>
                    <?php endif; ?>

            <!-- Data Balita -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php if ($result_balita->num_rows > 0): ?>
                <?php while ($row = $result_balita->fetch_assoc()): ?>
                <div class="balita-card">
                    <div class="balita-card-header">
                        <div style="display: flex; gap: 16px; flex: 1;">
                            <div class="balita-avatar">
                                <?php echo $row['jenis_kelamin'] == 'L' ? '👦' : '👧'; ?>
                            </div>
                            <div class="balita-info-block">
                                <div class="balita-name"><?php echo htmlspecialchars($row['nama_balita']); ?></div>
                                <div class="balita-age"><?php echo hitungUsia($row['tanggal_lahir']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="balita-details-row">
                        <div class="balita-detail">
                            <div class="detail-icon">📅</div>
                            <div class="detail-content">
                                <div class="detail-label">Tanggal Lahir</div>
                                <div class="detail-value"><?php echo formatTanggalIndo($row['tanggal_lahir']); ?></div>
                            </div>
                        </div>
                        
                        <div class="balita-detail">
                            <div class="detail-icon">⚧</div>
                            <div class="detail-content">
                                <div class="detail-label">Jenis Kelamin</div>
                                <div class="detail-value"><?php echo $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></div>
                            </div>
                        </div>
                        
                        <div class="balita-detail">
                            <div class="detail-icon">📍</div>
                            <div class="detail-content">
                                <div class="detail-label">Alamat</div>
                                <div class="detail-value"><?php echo htmlspecialchars($row['alamat_balita'] ?? 'Belum diisi'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="balita-actions">
                        <?php if (($user['role'] ?? '') == 'pengguna'): ?>
                        <button class="btn-icon-sm" onclick="location.href='data_balita.php?action=edit&id=<?php echo $row['id_balita']; ?>'">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-icon-sm" onclick="hapusBalita(<?php echo $row['id_balita']; ?>)">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                        <button class="btn btn-secondary" onclick="location.href='pertumbuhan.php?id=<?php echo $row['id_balita']; ?>'">
                            <i class="fas fa-chart-line"></i> Pertumbuhan
                        </button>
                        <?php else: ?>
                        <!-- Tenaga Kesehatan: view-only controls -->
                        <button class="btn btn-secondary" onclick="location.href='status_gizi.php?id_balita=<?php echo $row['id_balita']; ?>'">
                            <i class="fas fa-heartbeat"></i> Lihat Status Gizi
                        </button>
                        <a class="btn btn-secondary" href="akun_detail.php?id=<?php echo $row['id_ortu_akun'] ?? ($row['id_akun'] ?? ''); ?>">
                            <i class="fas fa-user"></i> Lihat Orang Tua
                        </a>
                        <a class="btn btn-secondary" href="pertumbuhan.php?id=<?php echo $row['id_balita']; ?>">
                            <i class="fas fa-chart-line"></i> Lihat Pertumbuhan
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
            <div class="content-card">
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-baby"></i></div>
                    <h3 style="color: #64748b; margin-bottom: 10px;">Belum Ada Data Balita</h3>
                    <p style="color: #94a3b8; margin-bottom: 20px;">Silakan tambahkan data balita Anda terlebih dahulu</p>
                </div>
            </div>
            <?php endif; ?>
            </div>

            <?php elseif ($mode == 'add' || $mode == 'edit'): ?>
            <!-- ========== MODE ADD / EDIT ========== -->
            
            <!-- Back Button -->
            <div style="margin-bottom: 20px;">
                <a href="data_balita.php" class="btn-back" onclick="return confirmBack()">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali</span>
                </a>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1><?php echo $mode == 'add' ? 'Tambah' : 'Edit'; ?> Data Balita</h1>
                <p><?php echo $mode == 'add' ? 'Tambah data identitas balita baru' : 'Perbarui data identitas balita'; ?></p>
            </div>

            <!-- Form -->
            <div class="form-container">
                <div class="content-card">
                    <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                    <?php if ($mode == 'edit'): ?>
                    <input type="hidden" name="id_balita" value="<?php echo $edit_data['id_balita']; ?>">
                    <?php endif; ?>

                    <div class="photo-upload">
                        <input type="file" id="photo-input" accept="image/*" style="display: none;">
                        <div class="photo-preview" onclick="document.getElementById('photo-input').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                        <a href="javascript:void(0)" onclick="document.getElementById('photo-input').click()" class="photo-link">
                            <i class="fas fa-upload"></i> Upload Photo
                        </a>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Nama Lengkap <span class="required">*</span>
                            </label>
                            <input type="text" name="nama_balita" class="form-input" 
                                   placeholder="Enter your child's name" 
                                   value="<?php echo $mode == 'edit' ? htmlspecialchars($edit_data['nama_balita']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Tanggal Lahir <span class="required">*</span>
                            </label>
                            <input type="date" name="tanggal_lahir" class="form-input" 
                                   value="<?php echo $mode == 'edit' ? $edit_data['tanggal_lahir'] : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Jenis Kelamin <span class="required">*</span>
                        </label>
                        <select name="jenis_kelamin" class="form-select" required>
                            <option value="">Pilih Jenis Kelamin</option>
                            <option value="L" <?php echo ($mode == 'edit' && $edit_data['jenis_kelamin'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="P" <?php echo ($mode == 'edit' && $edit_data['jenis_kelamin'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Alamat <span class="required">*</span>
                        </label>
                        <input type="text" name="alamat_balita" class="form-input" 
                               placeholder="Masukkan alamat balita" 
                               value="<?php echo $mode == 'edit' ? htmlspecialchars($edit_data['alamat_balita']) : ''; ?>" required>
                        <small style="color: #64748b; font-size: 12px;">Masukkan alamat tempat tinggal balita</small>
                    </div>

                    <div class="form-actions" style="justify-content: center;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $mode == 'add' ? 'Tambah Balita' : 'Simpan Perubahan'; ?>
                        </button>
                    </div>
                </form>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>

    <script>
    function hapusBalita(id) {
        if (confirm('Apakah Anda yakin ingin menghapus data ini? Setelah terhapus, data tidak dapat dikembalikan.')) {
            window.location.href = 'data_balita.php?action=delete&id=' + id;
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
            return confirm('Anda yakin ingin kembali? Data yang sudah diubah tidak akan tersimpan.');
        }
        return true;
    }
    </script>
</body>
</html>

<?php $conn->close(); ?>