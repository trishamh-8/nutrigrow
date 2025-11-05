<?php
// nakes_rekomendasi_gizi.php - Halaman Buat Rekomendasi Gizi untuk Tenaga Kesehatan
require_once 'config.php';
require_once 'auth.php';

$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];
$message = '';
$error = '';
$mode = 'list'; // Mode: list, add
$selected_balita = isset($_GET['balita']) ? $_GET['balita'] : null;

// ========== PROSES TAMBAH REKOMENDASI ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $id_balita = intval($_POST['id_balita']);
    $id_pertumbuhan = !empty($_POST['id_pertumbuhan']) ? intval($_POST['id_pertumbuhan']) : null;
    $isi_rekomendasi = trim($_POST['isi_rekomendasi']);
    $prioritas = $_POST['prioritas'];
    $kategori = $_POST['kategori'];
    $tanggal_rekomendasi = date('Y-m-d');
    $sumber = 'tenaga_kesehatan';
    
    // Validasi input
    if (empty($id_balita) || empty($isi_rekomendasi) || empty($prioritas) || empty($kategori)) {
        $error = "Semua field wajib diisi dengan benar!";
        $mode = 'add';
        $selected_balita = $id_balita;
    } else {
        // Verifikasi balita ada di database
        $query_check = "SELECT id_balita FROM balita WHERE id_balita = ?";
        $stmt = $conn->prepare($query_check);
        $stmt->bind_param("i", $id_balita);
        $stmt->execute();
        $result_check = $stmt->get_result();
        
        if ($result_check->num_rows == 0) {
            $error = "Balita tidak ditemukan!";
            $mode = 'add';
        } else {
            // Insert rekomendasi
            $query_insert = "INSERT INTO rekomendasi_gizi 
                            (id_balita, id_pertumbuhan, id_akun, sumber, isi_rekomendasi, 
                             tanggal_rekomendasi, prioritas, kategori) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query_insert);
            $stmt->bind_param("iiisssss", $id_balita, $id_pertumbuhan, $id_akun, $sumber, 
                             $isi_rekomendasi, $tanggal_rekomendasi, $prioritas, $kategori);
            
            if ($stmt->execute()) {
                // Berhasil - redirect dengan pesan sukses
                header("Location: nakes_rekomendasi_gizi.php?success=1&balita=" . $id_balita);
                exit();
            } else {
                $error = "Gagal menyimpan rekomendasi ke database: " . $stmt->error;
                $mode = 'add';
                $selected_balita = $id_balita;
            }
        }
    }
}

// ========== PROSES HAPUS REKOMENDASI ==========
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_rekomendasi = $_GET['id'];
    
    // Verifikasi bahwa rekomendasi ini dibuat oleh tenaga kesehatan yang login
    $query_verify = "SELECT id_rekomendasi FROM rekomendasi_gizi 
                     WHERE id_rekomendasi = ? AND id_akun = ?";
    $stmt = $conn->prepare($query_verify);
    $stmt->bind_param("ii", $id_rekomendasi, $id_akun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $query_delete = "DELETE FROM rekomendasi_gizi WHERE id_rekomendasi = ?";
        $stmt = $conn->prepare($query_delete);
        $stmt->bind_param("i", $id_rekomendasi);
        
        if ($stmt->execute()) {
            header("Location: nakes_rekomendasi_gizi.php?success=2");
            exit();
        }
    }
}

// ========== MENENTUKAN MODE TAMPILAN ==========
if (isset($_GET['action']) && $_GET['action'] == 'add') {
    $mode = 'add';
}

// ========== AMBIL DATA UNTUK LIST ==========
if ($mode == 'list') {
    // Ambil semua rekomendasi yang dibuat oleh tenaga kesehatan ini
    $query_rekomendasi = "SELECT 
        rg.*,
        b.nama_balita,
        a.nama as nama_ortu
        FROM rekomendasi_gizi rg
        JOIN balita b ON rg.id_balita = b.id_balita
        JOIN pengguna p ON b.id_akun = p.id_akun
        JOIN akun a ON p.id_akun = a.id_akun
        WHERE rg.id_akun = ?
        ORDER BY 
            CASE rg.prioritas
                WHEN 'Tinggi' THEN 1
                WHEN 'Sedang' THEN 2
                WHEN 'Rendah' THEN 3
            END,
            rg.tanggal_rekomendasi DESC";
            
    $stmt = $conn->prepare($query_rekomendasi);
    $stmt->bind_param("i", $id_akun);
    $stmt->execute();
    $result_rekomendasi = $stmt->get_result();

    // Hitung statistik
    $query_count = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN prioritas = 'Tinggi' THEN 1 ELSE 0 END) as tinggi,
        SUM(CASE WHEN prioritas = 'Sedang' THEN 1 ELSE 0 END) as sedang,
        SUM(CASE WHEN prioritas = 'Rendah' THEN 1 ELSE 0 END) as rendah
        FROM rekomendasi_gizi 
        WHERE id_akun = ?";
        
    $stmt = $conn->prepare($query_count);
    $stmt->bind_param("i", $id_akun);
    $stmt->execute();
    $result_count = $stmt->get_result();
    $counts = $result_count->fetch_assoc();
}

// ========== AMBIL DATA UNTUK FORM ==========
if ($mode == 'add') {
    // Ambil daftar balita
    $query_balita = "SELECT b.id_balita, b.nama_balita, a.nama as nama_ortu
                     FROM balita b
                     JOIN pengguna p ON b.id_akun = p.id_akun
                     JOIN akun a ON p.id_akun = a.id_akun
                     ORDER BY b.nama_balita ASC";
    $result_balita = $conn->query($query_balita);
    
    // Jika ada balita yang dipilih dari parameter, ambil data pertumbuhannya
    $pertumbuhan_list = [];
    if ($selected_balita) {
        $query_pertumbuhan = "SELECT id_pertumbuhan, tanggal_pemeriksaan, status_gizi
                             FROM pertumbuhan 
                             WHERE id_balita = ?
                             ORDER BY tanggal_pemeriksaan DESC
                             LIMIT 5";
        $stmt = $conn->prepare($query_pertumbuhan);
        $stmt->bind_param("i", $selected_balita);
        $stmt->execute();
        $result_pertumbuhan = $stmt->get_result();
        while ($row = $result_pertumbuhan->fetch_assoc()) {
            $pertumbuhan_list[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekomendasi Gizi - NutriGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-group {
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
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
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
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .rekomendasi-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .rekomendasi-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .rekomendasi-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .rekomendasi-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .rekomendasi-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            color: #64748b;
        }
        
        .rekomendasi-content {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        
        .form-help {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .form-row {
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
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Cari data balita atau rekomendasi...">
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($_SESSION['nama']); ?></h4>
                        <p>Tenaga Kesehatan</p>
                    </div>
                    <div class="user-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
            </header>

            <?php if ($mode == 'list'): ?>
            <!-- ========== MODE LIST ========== -->
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>Rekomendasi Gizi</h1>
                <p>Kelola rekomendasi gizi yang Anda berikan kepada orang tua</p>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <?php 
                if ($_GET['success'] == '1') {
                    echo "Rekomendasi berhasil dikirim dan dapat dilihat oleh orang tua!";
                } elseif ($_GET['success'] == '2') {
                    echo "Rekomendasi berhasil dihapus!";
                }
                ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Priority Summary -->
            <div class="priority-grid">
                <div class="priority-card total">
                    <div class="priority-label">Total Rekomendasi</div>
                    <div class="priority-value"><?php echo $counts['total']; ?></div>
                </div>
                <div class="priority-card high">
                    <div class="priority-label">Prioritas Tinggi</div>
                    <div class="priority-value"><?php echo $counts['tinggi']; ?></div>
                </div>
                <div class="priority-card medium">
                    <div class="priority-label">Prioritas Sedang</div>
                    <div class="priority-value"><?php echo $counts['sedang']; ?></div>
                </div>
                <div class="priority-card low">
                    <div class="priority-label">Prioritas Rendah</div>
                    <div class="priority-value"><?php echo $counts['rendah']; ?></div>
                </div>
            </div>

            <!-- Action Button -->
            <div style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="location.href='nakes_rekomendasi_gizi.php?action=add'">
                    <i class="fas fa-plus"></i> Buat Rekomendasi Baru
                </button>
            </div>

            <!-- Rekomendasi List -->
            <?php if ($result_rekomendasi->num_rows > 0): ?>
            <div class="recommendation-list">
                <?php while ($row = $result_rekomendasi->fetch_assoc()): 
                    $prioritas_class = strtolower($row['prioritas']);
                    if ($prioritas_class == 'sedang') $prioritas_class = 'medium';
                    if ($prioritas_class == 'tinggi') $prioritas_class = 'high';
                ?>
                <div class="rekomendasi-item">
                    <div class="rekomendasi-header">
                        <div style="flex: 1;">
                            <div class="rekomendasi-title">
                                Rekomendasi untuk <?php echo htmlspecialchars($row['nama_balita']); ?>
                            </div>
                            <div class="rekomendasi-meta">
                                <span><i class="fas fa-user"></i> Ortu: <?php echo htmlspecialchars($row['nama_ortu']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo formatTanggalIndo($row['tanggal_rekomendasi']); ?></span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: start;">
                            <span class="badge badge-<?php echo $prioritas_class; ?>">
                                <i class="fas fa-exclamation-circle"></i> 
                                Prioritas <?php echo $row['prioritas']; ?>
                            </span>
                            <span class="badge badge-nutrisi">
                                <?php echo htmlspecialchars($row['kategori'] ?? 'Nutrisi'); ?>
                            </span>
                            <button class="btn-icon btn-delete" onclick="hapusRekomendasi(<?php echo $row['id_rekomendasi']; ?>)" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="rekomendasi-content">
                        <?php echo nl2br(htmlspecialchars($row['isi_rekomendasi'])); ?>
                    </div>
                    <?php if ($row['id_pertumbuhan']): ?>
                    <div style="padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 12px; color: #64748b;">
                        <i class="fas fa-link"></i> Terkait dengan data pertumbuhan #<?php echo $row['id_pertumbuhan']; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="content-card" style="text-align: center; padding: 60px;">
                <i class="fas fa-clipboard-list" style="font-size: 64px; color: #e2e8f0; margin-bottom: 20px;"></i>
                <h3 style="color: #64748b; margin-bottom: 10px;">Belum Ada Rekomendasi</h3>
                <p style="color: #94a3b8; margin-bottom: 20px;">Anda belum membuat rekomendasi gizi untuk balita manapun.</p>
                <button class="btn btn-primary" onclick="location.href='nakes_rekomendasi_gizi.php?action=add'">
                    <i class="fas fa-plus"></i> Buat Rekomendasi Pertama
                </button>
            </div>
            <?php endif; ?>

            <?php elseif ($mode == 'add'): ?>
            <!-- ========== MODE ADD ========== -->
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>Buat Rekomendasi Gizi</h1>
                <p>Berikan rekomendasi gizi personal untuk balita</p>
            </div>

            <!-- Form -->
            <div class="content-card">
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="formRekomendasi">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Pilih Balita <span class="required">*</span>
                            </label>
                            <select name="id_balita" id="selectBalita" class="form-select" required onchange="loadPertumbuhan(this.value)">
                                <option value="">-- Pilih Balita --</option>
                                <?php while($bal = $result_balita->fetch_assoc()): ?>
                                <option value="<?php echo $bal['id_balita']; ?>" 
                                        <?php echo $selected_balita == $bal['id_balita'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bal['nama_balita']); ?> 
                                    (Ortu: <?php echo htmlspecialchars($bal['nama_ortu']); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-help">Pilih balita yang akan diberikan rekomendasi</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data Pertumbuhan (Opsional)
                            </label>
                            <select name="id_pertumbuhan" class="form-select">
                                <option value="">-- Tidak Terkait dengan Pertumbuhan --</option>
                                <?php foreach($pertumbuhan_list as $pert): ?>
                                <option value="<?php echo $pert['id_pertumbuhan']; ?>">
                                    <?php echo formatTanggalIndo($pert['tanggal_pemeriksaan']); ?> 
                                    (<?php echo $pert['status_gizi']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">Hubungkan rekomendasi dengan data pemeriksaan tertentu</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Prioritas <span class="required">*</span>
                            </label>
                            <select name="prioritas" class="form-select" required>
                                <option value="">-- Pilih Prioritas --</option>
                                <option value="Tinggi">Tinggi (Perlu Perhatian Segera)</option>
                                <option value="Sedang">Sedang (Perlu Diperhatikan)</option>
                                <option value="Rendah">Rendah (Saran Umum)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Kategori <span class="required">*</span>
                            </label>
                            <select name="kategori" class="form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Nutrisi" selected>Nutrisi</option>
                                <option value="Pertumbuhan">Pertumbuhan</option>
                                <option value="Kesehatan Umum">Kesehatan Umum</option>
                                <option value="Pola Makan">Pola Makan</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Isi Rekomendasi <span class="required">*</span>
                        </label>
                        <textarea name="isi_rekomendasi" class="form-textarea" required 
                                  placeholder="Contoh:&#10;&#10;Berdasarkan data pertumbuhan terbaru, direkomendasikan untuk:&#10;&#10;1. Meningkatkan asupan protein hewani menjadi 35-40g per hari&#10;2. Fokus pada sumber protein seperti telur, ikan, dan ayam&#10;3. Tambahkan sayuran hijau dalam setiap waktu makan"></textarea>
                        <div class="form-help">Tuliskan rekomendasi yang spesifik, jelas, dan dapat diikuti oleh orang tua</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Kirim Rekomendasi ke Orang Tua
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="location.href='nakes_rekomendasi_gizi.php'">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #0891b2; border-radius: 8px;">
                    <p style="color: #0369a1; font-size: 13px; margin: 0;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Catatan:</strong> Setelah rekomendasi dikirim, orang tua akan dapat melihatnya pada halaman Rekomendasi Gizi mereka.
                    </p>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>

    <script>
    function hapusRekomendasi(id) {
        if (confirm('Apakah Anda yakin ingin menghapus rekomendasi ini?')) {
            window.location.href = 'nakes_rekomendasi_gizi.php?action=delete&id=' + id;
        }
    }

    function loadPertumbuhan(idBalita) {
        if (idBalita) {
            window.location.href = 'nakes_rekomendasi_gizi.php?action=add&balita=' + idBalita;
        }
    }
    </script>
</body>
</html>

<?php $conn->close(); ?>