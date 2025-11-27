<?php
// pengaturan.php - Halaman Pengaturan
require_once 'auth.php';
requireLogin();

$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];
$message = '';
$error = '';

// Get user info
$stmt = $conn->prepare("SELECT * FROM akun WHERE id_akun = ?");
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get current settings (from session or defaults)
$notifikasi_email = isset($_SESSION['notifikasi_email']) ? $_SESSION['notifikasi_email'] : 1;
$notifikasi_jadwal = isset($_SESSION['notifikasi_jadwal']) ? $_SESSION['notifikasi_jadwal'] : 1;
$tema = isset($_SESSION['tema']) ? $_SESSION['tema'] : 'light';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_settings') {
    $notifikasi_email = isset($_POST['notifikasi_email']) ? 1 : 0;
    $notifikasi_jadwal = isset($_POST['notifikasi_jadwal']) ? 1 : 0;
    $tema = isset($_POST['tema']) ? $_POST['tema'] : 'light';

    // Save to session (in production, save to database)
    $_SESSION['notifikasi_email'] = $notifikasi_email;
    $_SESSION['notifikasi_jadwal'] = $notifikasi_jadwal;
    $_SESSION['tema'] = $tema;

    $message = "Pengaturan berhasil disimpan!";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - NutriGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/partials/common_head.php'; ?>
    <style>
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .settings-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #06b6d4;
            font-size: 20px;
        }

        .setting-item {
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .setting-item:last-child {
            margin-bottom: 0;
        }

        .setting-info {
            flex: 1;
        }

        .setting-label {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .setting-desc {
            font-size: 13px;
            color: #64748b;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 28px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #06b6d4;
        }

        input:checked + .slider:before {
            transform: translateX(22px);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(90deg, #06b6d4 0%, #0891b2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .danger-zone {
            background: #fef2f2;
            border: 2px solid #fecaca;
        }

        .danger-zone .section-title {
            color: #b91c1c;
        }

        .danger-zone .section-title i {
            color: #b91c1c;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #1e40af;
        }

        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
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
                    <input type="text" class="search-input" placeholder="Cari...">
                </div>
                
                <div class="user-info">
                    <div>
                        <h4><?php echo htmlspecialchars($user['nama']); ?></h4>
                        <p>Pengaturan</p>
                    </div>
                    <div class="avatar"><i class="fas fa-user"></i></div>
                </div>
            </header>

            <!-- Page Title -->
            <div class="page-title">
                <h1>Pengaturan</h1>
                <p class="page-subtitle">Kelola preferensi dan pengaturan akun Anda</p>
            </div>

            <!-- Messages -->
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

            <!-- Notification Settings -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-bell"></i>
                    Notifikasi
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_settings">

                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-label">Notifikasi Email</div>
                            <div class="setting-desc">Terima notifikasi penting melalui email</div>
                        </div>
                        <div class="toggle-switch">
                            <label class="switch">
                                <input type="checkbox" name="notifikasi_email" <?php echo $notifikasi_email ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-label">Notifikasi Jadwal</div>
                            <div class="setting-desc">Pengingat jadwal program kesehatan balita</div>
                        </div>
                        <div class="toggle-switch">
                            <label class="switch">
                                <input type="checkbox" name="notifikasi_jadwal" <?php echo $notifikasi_jadwal ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label class="form-label">Tema Aplikasi</label>
                        <select name="tema" class="form-select">
                            <option value="light" <?php echo ($tema == 'light') ? 'selected' : ''; ?>>Terang (Light)</option>
                            <option value="dark" <?php echo ($tema == 'dark') ? 'selected' : ''; ?>>Gelap (Dark)</option>
                            <option value="auto" <?php echo ($tema == 'auto') ? 'selected' : ''; ?>>Otomatis</option>
                        </select>
                        <div class="help-text">Pilih tema yang sesuai dengan preferensi Anda</div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Data Settings -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-database"></i>
                    Data & Privasi
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Data Anda dilindungi dengan enkripsi end-to-end dan sesuai dengan standar keamanan internasional.
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Riwayat Aktivitas</div>
                        <div class="setting-desc">Lihat riwayat aktivitas akun Anda</div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="alert('Fitur akan segera tersedia')">
                        <i class="fas fa-eye"></i> Lihat
                    </button>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Unduh Data</div>
                        <div class="setting-desc">Unduh salinan semua data pribadi Anda</div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="alert('Fitur akan segera tersedia')">
                        <i class="fas fa-download"></i> Unduh
                    </button>
                </div>
            </div>

            <!-- Account Information -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Akun
                </div>

                <div class="info-box">
                    <strong>Akun Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                </div>

                <div class="info-box">
                    <strong>ID Akun:</strong> #<?php echo $id_akun; ?>
                </div>

                <div class="info-box">
                    <strong>Tanggal Bergabung:</strong> <?php echo formatTanggalIndo($user['tanggal_daftar'] ?? date('Y-m-d')); ?>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="settings-section danger-zone">
                <div class="section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Area Berbahaya
                </div>

                <div class="setting-item" style="border-color: #fecaca;">
                    <div class="setting-info">
                        <div class="setting-label">Hapus Akun</div>
                        <div class="setting-desc">Operasi ini tidak dapat dibatalkan. Semua data akan dihapus secara permanen.</div>
                    </div>
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Anda yakin ingin menghapus akun? Tindakan ini tidak dapat dibatalkan.')) alert('Fitur akan segera tersedia')">
                        <i class="fas fa-trash"></i> Hapus Akun
                    </button>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<?php $conn->close(); ?>
