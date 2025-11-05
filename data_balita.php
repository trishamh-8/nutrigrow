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

// ========== PROSES HAPUS DATA ==========
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_balita = $_GET['id'];
    
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

// ========== PROSES TAMBAH/UPDATE DATA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
    <style>
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
            position: absolute;
            left: 240px;
            right: 0;
            top: 0;
            bottom: 0;
            padding: 20px;
            overflow-y: auto;
        }

        /* Back Button */
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

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 20px;
            width: 100%;
        }

        /* Form Container */
        .form-container {
            max-width: none;
            width: 100%;
        }

        /* Custom Scrollbar */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        .main-content::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .main-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .main-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .form-group {/* Lines 167-168 omitted */}
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .form-label .required {
            color: #dc2626;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
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
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .btn-primary, .btn-secondary {
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 195, 247, 0.2);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        .balita-card {
            background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .balita-avatar {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        
        .balita-info {
            flex: 1;
        }
        
        .balita-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .balita-age {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .balita-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 15px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-icon {
            font-size: 18px;
        }
        
        .detail-content {
            flex: 1;
        }
        
        .detail-label {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .detail-value {
            font-size: 14px;
            font-weight: 600;
        }
        
        .balita-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon-white {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-icon-white:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 64px;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        
        .photo-upload {
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            margin: 0 auto 15px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #94a3b8;
            border: 2px dashed #e2e8f0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .photo-preview:hover {
            border-color: #06b6d4;
            background: #f0fdff;
        }
        
        .photo-link {
            background: #06b6d4;
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 20px;
            border-radius: 20px;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .photo-link:hover {
            background: #0891b2;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .balita-details {
                grid-template-columns: 1fr;
            }
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
            <div style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="location.href='data_balita.php?action=add'">
                    <i class="fas fa-plus"></i> Tambah Balita
                </button>
            </div>

            <!-- Data Balita -->
            <?php if ($result_balita->num_rows > 0): ?>
                <?php while ($row = $result_balita->fetch_assoc()): ?>
                <div class="balita-card">
                    <div class="balita-avatar">
                        <?php echo $row['jenis_kelamin'] == 'L' ? '👦' : '👧'; ?>
                    </div>
                    <div class="balita-info">
                        <div class="balita-name"><?php echo htmlspecialchars($row['nama_balita']); ?></div>
                        <div class="balita-age"><?php echo hitungUsia($row['tanggal_lahir']); ?></div>
                        
                        <div class="balita-details">
                            <div class="detail-item">
                                <div class="detail-icon">📅</div>
                                <div class="detail-content">
                                    <div class="detail-label">Tanggal Lahir</div>
                                    <div class="detail-value"><?php echo formatTanggalIndo($row['tanggal_lahir']); ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon">⚧</div>
                                <div class="detail-content">
                                    <div class="detail-label">Jenis Kelamin</div>
                                    <div class="detail-value"><?php echo $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon">📍</div>
                                <div class="detail-content">
                                    <div class="detail-label">Alamat</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($row['alamat_balita'] ?? 'Belum diisi'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="balita-actions">
                        <button class="btn-icon-white" onclick="location.href='data_balita.php?action=edit&id=<?php echo $row['id_balita']; ?>'" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon-white" onclick="hapusBalita(<?php echo $row['id_balita']; ?>)" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
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