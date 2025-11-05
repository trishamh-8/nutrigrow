<?php
session_start();

// Redirect jika sudah login
if (isset($_SESSION['id_akun'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';
$conn = getConnection();

$error = '';
$selected_role = isset($_GET['role']) ? $_GET['role'] : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi!';
    } else {
        // Cari user berdasarkan email dari tabel AKUN
        $stmt = $conn->prepare("SELECT * FROM akun WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login berhasil - Dapatkan role user
            $role = getUserRole($conn, $user['id_akun']);
            
            // Set session (gunakan `id_akun` konsisten di seluruh aplikasi)
            $_SESSION['id_akun'] = $user['id_akun'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $role;
            
            // Update status akun menjadi aktif
            $stmt = $conn->prepare("UPDATE akun SET status_akun = 'aktif' WHERE id_akun = ?");
            $stmt->execute([$user['id_akun']]);
            
            // Redirect ke dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email atau password salah!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NutriGrow</title>
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
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: #4FC3F7;
            text-decoration: none;
            font-size: 13px;
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
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .register-link a {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">📈</div>
        <h1><span class="nutri">Nutri</span><span class="grow">Grow</span></h1>
        <p class="subtitle">Masuk untuk melanjutkan</p>
        <?php if ($selected_role): ?>
            <p style="text-align:center; color:#0f172a; font-weight:600; margin-bottom:12px;">Peran: <?php echo htmlspecialchars($selected_role); ?></p>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
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
                           placeholder="Masukkan password" required>
                </div>
                <div class="forgot-password">
                    <a href="#">Lupa password?</a>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">Masuk</button>
        </form>
        
        <div class="register-link">
            Belum punya akun? <a href="register.php<?php echo $selected_role ? '?role='.urlencode($selected_role) : ''; ?>">Daftar di sini</a>
        </div>
    </div>
</body>
</html>