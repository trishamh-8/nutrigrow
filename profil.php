<?php
// profil.php - Halaman Profil Pengguna
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

// Get role
$role_query = "SELECT 
    CASE 
        WHEN tk.id_tenaga_kesehatan IS NOT NULL THEN 'tenaga_kesehatan'
        WHEN p.id_pengguna IS NOT NULL THEN 'pengguna'
        WHEN adm.id_admin IS NOT NULL THEN 'admin'
        ELSE 'pengguna'
    END as role
    FROM akun a
    LEFT JOIN tenaga_kesehatan tk ON tk.id_akun = a.id_akun
    LEFT JOIN pengguna p ON p.id_akun = a.id_akun
    LEFT JOIN admin adm ON adm.id_akun = a.id_akun
    WHERE a.id_akun = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$role_data = $result->fetch_assoc();
$role = $role_data['role'] ?? 'pengguna';

// Format role display
$role_display = [
    'pengguna' => 'Orang Tua',
    'tenaga_kesehatan' => 'Tenaga Kesehatan',
    'admin' => 'Administrator'
];
$display_role = $role_display[$role] ?? 'Pengguna';

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $no_telepon = trim($_POST['no_telepon']);
    
    // Validasi
    if (empty($nama)) {
        $error = "Nama tidak boleh kosong!";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email tidak valid!";
    } else {
        // Check if email already exists (excluding current user)
        $check_email = $conn->prepare("SELECT id_akun FROM akun WHERE email = ? AND id_akun != ?");
        $check_email->bind_param("si", $email, $id_akun);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $error = "Email sudah digunakan oleh akun lain!";
        } else {
            $update = $conn->prepare("UPDATE akun SET nama = ?, email = ?, no_telepon = ? WHERE id_akun = ?");
            $update->bind_param("sssi", $nama, $email, $no_telepon, $id_akun);
            
            if ($update->execute()) {
                $message = "Profil berhasil diperbarui!";
                // Refresh user data
                $user['nama'] = $nama;
                $user['email'] = $email;
                $user['no_telepon'] = $no_telepon;
                $_SESSION['nama'] = $nama;
            } else {
                $error = "Gagal memperbarui profil!";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirm_password = $_POST['konfirm_password'];
    
    // Verify old password
    if (!password_verify($password_lama, $user['password'])) {
        $error = "Password lama tidak sesuai!";
    } elseif (empty($password_baru)) {
        $error = "Password baru tidak boleh kosong!";
    } elseif (strlen($password_baru) < 6) {
        $error = "Password baru minimal 6 karakter!";
    } elseif ($password_baru !== $konfirm_password) {
        $error = "Konfirmasi password tidak sesuai!";
    } else {
        $hashed_password = password_hash($password_baru, PASSWORD_BCRYPT);
        $update = $conn->prepare("UPDATE akun SET password = ? WHERE id_akun = ?");
        $update->bind_param("si", $hashed_password, $id_akun);
        
        if ($update->execute()) {
            $message = "Password berhasil diubah!";
        } else {
            $error = "Gagal mengubah password!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - NutriGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/partials/common_head.php'; ?>
    <style>
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .profile-header {
            background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            color: white;
            padding: 40px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 20px;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-role {
            font-size: 14px;
            opacity: 0.9;
            text-transform: capitalize;
        }

        .profile-section {
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

        .form-group {
            margin-bottom: 18px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }

        .form-input:disabled {
            background: #f8fafc;
            color: #64748b;
        }

        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .info-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 16px;
            color: #0f172a;
            font-weight: 600;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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

        .tab-list {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 14px 20px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .tab-button.active {
            color: #06b6d4;
            border-bottom-color: #06b6d4;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
                        <p><?php echo $display_role; ?></p>
                    </div>
                    <div class="avatar"><i class="fas fa-user"></i></div>
                </div>
            </header>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                <div class="profile-role"><?php echo $display_role; ?></div>
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

            <!-- Profile Section -->
            <div class="profile-section">
                <div class="section-title">
                    <i class="fas fa-user"></i>
                    Informasi Pribadi
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">Status Akun</div>
                            <div class="info-value"><?php echo htmlspecialchars($display_role); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tanggal Bergabung</div>
                            <div class="info-value"><?php echo formatTanggalIndo($user['tanggal_daftar'] ?? date('Y-m-d')); ?></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-input" value="<?php echo htmlspecialchars($user['nama']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="tel" name="no_telepon" class="form-input" value="<?php echo htmlspecialchars($user['no_telepon'] ?? ''); ?>" placeholder="+62 812-3456-7890">
                    </div>

                    <div class="btn-group" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Section -->
            <div class="profile-section">
                <div class="section-title">
                    <i class="fas fa-lock"></i>
                    Keamanan
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label">Password Lama</label>
                        <input type="password" name="password_lama" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password_baru" class="form-input" required>
                        <small style="color: #64748b; margin-top: 4px; display: block;">Minimal 6 karakter</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="konfirm_password" class="form-input" required>
                    </div>

                    <div class="btn-group" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

<?php $conn->close(); ?>
