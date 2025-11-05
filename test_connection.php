<?php
/**
 * File untuk testing koneksi database dan struktur tabel
 * Akses: http://localhost/nutrigrow/test_connection.php
 * HAPUS FILE INI setelah development selesai!
 */

require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Koneksi Database - NutriGrow</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4FC3F7;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #e3f2fd;
            color: #1565c0;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #4FC3F7;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4FC3F7;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0288D1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test Koneksi Database NutriGrow V3</h1>
        
        <?php
        try {
            $conn = getConnection();
            echo '<div class="success">✅ Koneksi database BERHASIL!</div>';
            
            // Test query tabel
            echo '<h2>📋 Daftar Tabel</h2>';
            $stmt = $conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                echo '<table>';
                echo '<tr><th>No</th><th>Nama Tabel</th><th>Jumlah Rows</th></tr>';
                $no = 1;
                foreach ($tables as $table) {
                    $stmt = $conn->query("SELECT COUNT(*) as total FROM `$table`");
                    $result = $stmt->fetch();
                    echo '<tr>';
                    echo '<td>' . $no++ . '</td>';
                    echo '<td><strong>' . $table . '</strong></td>';
                    echo '<td>' . $result['total'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="error">⚠️ Tidak ada tabel. Silakan import database.sql terlebih dahulu!</div>';
            }
            
            // Test data akun
            echo '<h2>👥 Data Akun (Sample)</h2>';
            $stmt = $conn->query("SELECT id_akun, nama, email, username, status_akun FROM akun LIMIT 5");
            $users = $stmt->fetchAll();
            
            if (count($users) > 0) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Nama</th><th>Email</th><th>Username</th><th>Status</th></tr>';
                foreach ($users as $user) {
                    echo '<tr>';
                    echo '<td>' . $user['id_akun'] . '</td>';
                    echo '<td>' . htmlspecialchars($user['nama']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                    echo '<td>' . $user['status_akun'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                echo '<div class="info">💡 Gunakan akun di atas untuk testing login (password default: <strong>123456</strong>)</div>';
            } else {
                echo '<div class="error">⚠️ Belum ada data akun. Data dummy akan di-insert saat import database.sql</div>';
            }
            
            // Test data balita
            echo '<h2>👶 Data Balita (Sample)</h2>';
            $stmt = $conn->query("
                SELECT b.id_balita, b.nama_balita, b.tanggal_lahir, b.jenis_kelamin, a.nama as nama_orangtua 
                FROM balita b 
                JOIN akun a ON b.id_akun = a.id_akun 
                LIMIT 5
            ");
            $balitas = $stmt->fetchAll();
            
            if (count($balitas) > 0) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Nama Balita</th><th>Tanggal Lahir</th><th>JK</th><th>Orang Tua</th></tr>';
                foreach ($balitas as $balita) {
                    echo '<tr>';
                    echo '<td>' . $balita['id_balita'] . '</td>';
                    echo '<td>' . htmlspecialchars($balita['nama_balita']) . '</td>';
                    echo '<td>' . date('d/m/Y', strtotime($balita['tanggal_lahir'])) . '</td>';
                    echo '<td>' . $balita['jenis_kelamin'] . '</td>';
                    echo '<td>' . htmlspecialchars($balita['nama_orangtua']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="info">ℹ️ Belum ada data balita.</div>';
            }
            
            // Test role detection
            echo '<h2>🔐 Test Role Detection</h2>';
            $stmt = $conn->query("SELECT id_akun, nama, email FROM akun LIMIT 3");
            $test_users = $stmt->fetchAll();
            
            if (count($test_users) > 0) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th></tr>';
                foreach ($test_users as $test_user) {
                    $role = getUserRole($conn, $test_user['id_akun']);
                    echo '<tr>';
                    echo '<td>' . $test_user['id_akun'] . '</td>';
                    echo '<td>' . htmlspecialchars($test_user['nama']) . '</td>';
                    echo '<td>' . htmlspecialchars($test_user['email']) . '</td>';
                    echo '<td><strong>' . $role . '</strong></td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            
            // Informasi konfigurasi
            echo '<h2>⚙️ Konfigurasi Database</h2>';
            echo '<table>';
            echo '<tr><th>Parameter</th><th>Value</th></tr>';
            echo '<tr><td>Host</td><td>' . DB_HOST . '</td></tr>';
            echo '<tr><td>Database</td><td>' . DB_NAME . '</td></tr>';
            echo '<tr><td>User</td><td>' . DB_USER . '</td></tr>';
            echo '<tr><td>PHP Version</td><td>' . phpversion() . '</td></tr>';
            echo '<tr><td>PDO Driver</td><td>' . implode(', ', PDO::getAvailableDrivers()) . '</td></tr>';
            echo '</table>';
            
        } catch (Exception $e) {
            echo '<div class="error">❌ ERROR: ' . $e->getMessage() . '</div>';
            echo '<div class="info">';
            echo '<strong>Troubleshooting:</strong><br>';
            echo '1. Pastikan MySQL/MariaDB sudah running<br>';
            echo '2. Cek kredensial di config.php<br>';
            echo '3. Pastikan database <strong>nutrigrow_db</strong> sudah dibuat<br>';
            echo '4. Import file database.sql ke dalam database<br>';
            echo '</div>';
        }
        ?>
        
        <hr style="margin: 30px 0;">
        
        <h2>🚀 Quick Actions</h2>
        <a href="register.php" class="btn">📝 Registrasi</a>
        <a href="login.php" class="btn">🔐 Login</a>
        <a href="dashboard.php" class="btn">📊 Dashboard</a>
        
        <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 5px;">
            <strong>⚠️ PENTING:</strong> Hapus file ini (<code>test_connection.php</code>) setelah development selesai untuk keamanan!
        </div>
    </div>
</body>
</html>