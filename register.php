<?php
session_start();

// Redirect jika sudah login
if (isset($_SESSION['id_akun'])) {
    header('Location: dashboard.php');
    exit;
}

// Include config
require_once 'config.php';
$conn = getConnection();

// Variabel untuk pesan
$error = '';
$success = '';
// Determine selected role from POST (when validation fails) or GET param
$selected_role = isset($_POST['role']) ? $_POST['role'] : (isset($_GET['role']) ? $_GET['role'] : null);

// Proses registrasi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $nomor_sertifikasi = trim($_POST['nomor_sertifikasi'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $posted_role = isset($_POST['role']) ? $_POST['role'] : $selected_role;

    $admin_code = isset($_POST['admin_code']) ? trim($_POST['admin_code']) : '';
    $valid_admin_code = 'NUTRIGROW2025'; // In production, this should be stored securely

    // Validasi input
    if (empty($nama_lengkap) || empty($email) || empty($password)) {
        $error = 'Nama lengkap, email, dan password wajib diisi!';
    } elseif ($posted_role == 'tenaga_kesehatan' && empty($nomor_sertifikasi)) {
        $error = 'Nomor sertifikasi wajib diisi untuk tenaga kesehatan!';
    } elseif ($posted_role == 'admin' && empty($admin_code)) {
        $error = 'Kode administrator wajib diisi!';
    } elseif ($posted_role == 'admin' && $admin_code !== $valid_admin_code) {
        $error = 'Kode administrator tidak valid!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        try {
            // Cek apakah email sudah terdaftar di tabel AKUN
            $stmt = $conn->prepare("SELECT id_akun FROM akun WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $error = 'Email sudah terdaftar!';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Generate username dari email
                $username = explode('@', $email)[0];

                // Cek username conflict
                $stmt = $conn->prepare("SELECT id_akun FROM akun WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $username = $username . rand(100, 999);
                }

                // Begin transaction untuk keamanan data
                $conn->beginTransaction();

                // Insert ke tabel AKUN
                $stmt = $conn->prepare(
                    "INSERT INTO akun (nama, email, password, username, status_akun, tanggal_aktif) VALUES (?, ?, ?, ?, 'aktif', NOW())"
                );
                $stmt->execute([$nama_lengkap, $email, $hashed_password, $username]);

                // Dapatkan ID akun yang baru dibuat
                $id_akun = $conn->lastInsertId();

                // Tentukan role berdasarkan pilihan
                if ($posted_role === 'admin') {
                    // Insert sebagai ADMIN
                    $stmt = $conn->prepare("INSERT INTO admin (id_akun, level_admin) VALUES (?, 'admin')");
                    $stmt->execute([$id_akun]);
                } elseif ($posted_role === 'tenaga_kesehatan') {
                    // Insert sebagai TENAGA KESEHATAN
                    $stmt = $conn->prepare("INSERT INTO tenaga_kesehatan (id_akun, sertifikasi) VALUES (?, ?)");
                    $stmt->execute([$id_akun, $nomor_sertifikasi]);
                } else {
                    // Insert sebagai PENGGUNA (orang tua) - simpan alamat jika ada
                    $stmt = $conn->prepare("INSERT INTO pengguna (id_akun, alamat) VALUES (?, ?)");
                    $stmt->execute([$id_akun, $alamat ?: null]);
                }

                // Commit transaction - semua berhasil!
                $conn->commit();

                $success = 'Registrasi berhasil! Silakan login.';

                // Redirect ke halaman login setelah 2 detik, sertakan role jika ada
                $roleParam = $selected_role ? '?role=' . urlencode($selected_role) : '';
                header("refresh:2;url=login.php" . $roleParam);
            }
        } catch(PDOException $e) {
            // Rollback jika terjadi error
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = 'Terjadi kesalahan saat registrasi. Silakan coba lagi.';
            // Log error untuk debugging (hapus di production)
            error_log("Registration Error: " . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - NutriGrow</title>
    <!-- Add validation script -->
    <script src="assets/js/register-validation.js" defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4FC3F7 0%, #66BB6A 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }
        
        h1 .nutri {
            color: #4FC3F7;
        }
        
        h1 .grow {
            color: #333;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .optional {
            color: #999;
            font-weight: 400;
            font-size: 12px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #4FC3F7;
        }
        
        input::placeholder {
            color: #bbb;
        }
        
        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .login-link a {
            color: #4FC3F7;
            text-decoration: none;
            font-weight: 600;
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #81c784;
        }

        select.form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
            appearance: none;
            background: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'%3E%3Cpath fill='%23999' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 15px center/16px 16px;
        }

        select.form-control:focus {
            outline: none;
            border-color: #4FC3F7;
        }

        .required-mark {
            color: #dc2626;
            margin-left: 4px;
        }

        input:invalid {
            border-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">👤+</div>
        <h1><span class="nutri">Nutri</span><span class="grow">Grow</span></h1>
        <p class="subtitle">Daftar akun baru untuk memulai</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="role">Peran</label>
                <select name="role" id="role" class="form-control" required>
                    <option value="">Pilih peran</option>
                    <option value="pengguna" <?php echo $selected_role === 'pengguna' ? 'selected' : ''; ?>>Orang Tua</option>
                    <option value="tenaga_kesehatan" <?php echo $selected_role === 'tenaga_kesehatan' ? 'selected' : ''; ?>>Tenaga Kesehatan</option>
                    <option value="admin" <?php echo $selected_role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                </select>
                <p class="help-text">Pilih administrator hanya jika Anda memiliki hak akses khusus.</p>
            </div>
            
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" 
                           placeholder="Masukkan nama lengkap" required 
                           value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">✉️</span>
                    <input type="email" id="email" name="email" 
                           placeholder="contoh@email.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" name="password" 
                           placeholder="Minimal 6 karakter" required>
                </div>
            </div>
            
            <div id="sertifikasi-container" class="form-group" style="display: <?php echo $selected_role === 'tenaga_kesehatan' ? 'block' : 'none'; ?>">
                <label for="nomor_sertifikasi">
                    Nomor Sertifikasi <span class="required-mark" style="color:#dc2626">*</span>
                </label>
                <div class="input-wrapper">
                    <span class="input-icon">🏥</span>
                    <input type="text" id="nomor_sertifikasi" name="nomor_sertifikasi" 
                           placeholder="Masukkan nomor sertifikasi"
                           value="<?php echo isset($_POST['nomor_sertifikasi']) ? htmlspecialchars($_POST['nomor_sertifikasi']) : ''; ?>"
                           <?php echo $selected_role === 'tenaga_kesehatan' ? 'required' : ''; ?>>
                </div>
                <p class="help-text">Wajib diisi untuk tenaga kesehatan</p>
            </div>

            <div id="admin-code-container" class="form-group" style="display: <?php echo $selected_role === 'admin' ? 'block' : 'none'; ?>">
                <label for="admin_code">
                    Kode Administrator <span class="required-mark" style="color:#dc2626">*</span>
                </label>
                <div class="input-wrapper">
                    <span class="input-icon">🔑</span>
                    <input type="password" id="admin_code" name="admin_code" 
                           placeholder="Masukkan kode administrator"
                           <?php echo $selected_role === 'admin' ? 'required' : ''; ?>>
                </div>
                <p class="help-text">Wajib diisi untuk administrator - hubungi super admin untuk mendapatkan kode</p>
            </div>
            
            <div id="alamat-container" class="form-group" style="display: <?php echo $selected_role === 'pengguna' ? 'block' : 'none'; ?>">
                <label for="alamat">Alamat</label>
                <div class="input-wrapper">
                    <span class="input-icon">🏠</span>
                    <input type="text" id="alamat" name="alamat"
                           placeholder="Masukkan alamat lengkap"
                           value="<?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?>"
                           <?php echo $selected_role === 'pengguna' ? 'required' : ''; ?>>
                </div>
                <p class="help-text">Alamat akan disimpan pada profil orang tua</p>
            </div>

            <button type="submit" class="btn-submit">Daftar</button>
        </form>
        
        <div class="login-link">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>
    <script>
        (function(){
            var roleSelect = document.getElementById('role');
            var sertifikasi = document.getElementById('sertifikasi-container');
            var adminCode = document.getElementById('admin-code-container');
            var alamat = document.getElementById('alamat-container');

            if (!roleSelect) return;

            function updateVisibility() {
                var role = roleSelect.value;
                if (sertifikasi) sertifikasi.style.display = role === 'tenaga_kesehatan' ? 'block' : 'none';
                if (adminCode) adminCode.style.display = role === 'admin' ? 'block' : 'none';
                if (alamat) alamat.style.display = role === 'pengguna' ? 'block' : 'none';

                var ns = document.getElementById('nomor_sertifikasi');
                if (ns) ns.required = role === 'tenaga_kesehatan';
                var ac = document.getElementById('admin_code');
                if (ac) ac.required = role === 'admin';
                var al = document.getElementById('alamat');
                if (al) al.required = role === 'pengguna';
            }

            roleSelect.addEventListener('change', updateVisibility);
            updateVisibility();
        })();
    </script>
</body>
</html>