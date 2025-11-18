<?php
session_start();

// Require login and use consistent session key `id_akun`
if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
$conn = getConnection();

$id_balita = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data balita - jika tenaga_kesehatan/admin boleh mengakses semua, jika pengguna hanya milik mereka
$role = $_SESSION['role'] ?? 'pengguna';
if ($role === 'tenaga_kesehatan' || $role === 'admin') {
    $stmt = $conn->prepare("SELECT * FROM balita WHERE id_balita = ?");
    $stmt->execute([$id_balita]);
} else {
    $stmt = $conn->prepare("SELECT * FROM balita WHERE id_balita = ? AND id_akun = ?");
    $stmt->execute([$id_balita, $_SESSION['id_akun']]);
}
$balita = $stmt->fetch();

if (!$balita) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Error</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; background: #f5f5f5; }
            .error { background: #ffebee; padding: 30px; border-radius: 10px; display: inline-block; color: #c62828; }
            a { color: #4FC3F7; text-decoration: none; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Data balita tidak ditemukan atau tidak memiliki akses</h2>
            <br>
            <a href="dashboard.php">← Kembali ke Dashboard</a>
        </div>
    </body>
    </html>
    ');
}

// Ambil data pertumbuhan terakhir
$stmt = $conn->prepare("
    SELECT * FROM pertumbuhan 
    WHERE id_balita = ? 
    ORDER BY tanggal_pemeriksaan DESC 
    LIMIT 1
");
$stmt->execute([$id_balita]);
$pertumbuhan = $stmt->fetch();

if (!$pertumbuhan) {
    $pertumbuhan = [
        'berat_badan' => 0,
        'tinggi_badan' => 0,
        'lingkar_kepala' => 0,
        'zscore' => 0,
        'status_gizi' => 'Belum Ada Data',
        'tanggal_pemeriksaan' => date('Y-m-d')
    ];
}

// Ambil data asupan
$stmt = $conn->prepare("
    SELECT 
        COALESCE(AVG(kalori_total), 0) as avg_kalori,
        COALESCE(AVG(protein), 0) as avg_protein,
        COALESCE(AVG(karbohidrat), 0) as avg_karbohidrat,
        COALESCE(AVG(lemak), 0) as avg_lemak
    FROM asupan_harian 
    WHERE id_balita = ? 
    AND tanggal_catatan >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$id_balita]);
$asupan = $stmt->fetch();

// Ambil rekomendasi
$stmt = $conn->prepare("
    SELECT r.*, a.nama as nama_tenaga_kesehatan
    FROM rekomendasi_gizi r
    JOIN akun a ON r.id_akun = a.id_akun
    WHERE r.id_balita = ?
    ORDER BY r.tanggal_rekomendasi DESC
    LIMIT 3
");
$stmt->execute([$id_balita]);
$rekomendasi_list = $stmt->fetchAll();

// Hitung usia
$tanggal_lahir = new DateTime($balita['tanggal_lahir']);
$sekarang = new DateTime();
$diff = $tanggal_lahir->diff($sekarang);

// Set header untuk tampilan print-friendly (bisa di-save as PDF dari browser)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kesehatan - <?php echo htmlspecialchars($balita['nama_balita']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #4FC3F7;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #4FC3F7;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            background: #4FC3F7;
            color: white;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #f0f0f0;
            font-weight: 600;
        }
        .status-box {
            background: #e8f5e9;
            border: 2px solid #66BB6A;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-box h3 {
            color: #2e7d32;
            margin: 0 0 10px 0;
        }
        .recommendation {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        .recommendation-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        .recommendation-text {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #4FC3F7;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn-print:hover {
            background: #0288D1;
        }
        @media print {
            .btn-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>

    <div class="header">
        <h1>📊 LAPORAN KESEHATAN BALITA</h1>
        <p class="subtitle"><strong>NutriGrow</strong> - Platform Monitoring Gizi Balita</p>
        <p class="subtitle">Tanggal Cetak: <?php echo date('d F Y'); ?></p>
    </div>

    <div class="section">
        <h2>📋 Informasi Balita</h2>
        <table>
            <tr>
                <th width="30%">Nama Balita</th>
                <td><?php echo htmlspecialchars($balita['nama_balita']); ?></td>
            </tr>
            <tr>
                <th>Tanggal Lahir</th>
                <td><?php echo date('d F Y', strtotime($balita['tanggal_lahir'])); ?></td>
            </tr>
            <tr>
                <th>Jenis Kelamin</th>
                <td><?php echo $balita['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
            </tr>
            <tr>
                <th>Usia</th>
                <td><?php echo $diff->y; ?> tahun <?php echo $diff->m; ?> bulan</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>📊 Status Gizi & Pertumbuhan</h2>
        <div class="status-box">
            <h3>Status Gizi: <?php echo $pertumbuhan['status_gizi']; ?></h3>
            <p style="margin: 0;"><strong>Z-Score:</strong> <?php echo number_format($pertumbuhan['zscore'], 1); ?> (Berat Badan menurut Tinggi Badan)</p>
        </div>
        
        <table>
            <tr>
                <th>Parameter</th>
                <th>Nilai</th>
                <th>Tanggal Pengukuran</th>
            </tr>
            <tr>
                <td>Berat Badan</td>
                <td><strong><?php echo number_format($pertumbuhan['berat_badan'], 1); ?> kg</strong></td>
                <td><?php echo date('d F Y', strtotime($pertumbuhan['tanggal_pemeriksaan'])); ?></td>
            </tr>
            <tr>
                <td>Tinggi Badan</td>
                <td><strong><?php echo $pertumbuhan['tinggi_badan']; ?> cm</strong></td>
                <td><?php echo date('d F Y', strtotime($pertumbuhan['tanggal_pemeriksaan'])); ?></td>
            </tr>
            <tr>
                <td>Lingkar Kepala</td>
                <td><strong><?php echo $pertumbuhan['lingkar_kepala']; ?> cm</strong></td>
                <td><?php echo date('d F Y', strtotime($pertumbuhan['tanggal_pemeriksaan'])); ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>🍽️ Ringkasan Asupan Harian (7 Hari Terakhir)</h2>
        <table>
            <tr>
                <th>Nutrisi</th>
                <th>Rata-rata Asupan</th>
                <th>Target</th>
                <th>Persentase</th>
            </tr>
            <tr>
                <td>Kalori</td>
                <td><?php echo round($asupan['avg_kalori']); ?> kcal</td>
                <td>1200 kcal</td>
                <td><?php echo round(($asupan['avg_kalori'] / 1200) * 100); ?>%</td>
            </tr>
            <tr>
                <td>Protein</td>
                <td><?php echo round($asupan['avg_protein']); ?> g</td>
                <td>35 g</td>
                <td><?php echo round(($asupan['avg_protein'] / 35) * 100); ?>%</td>
            </tr>
            <tr>
                <td>Karbohidrat</td>
                <td><?php echo round($asupan['avg_karbohidrat']); ?> g</td>
                <td>150 g</td>
                <td><?php echo round(($asupan['avg_karbohidrat'] / 150) * 100); ?>%</td>
            </tr>
            <tr>
                <td>Lemak</td>
                <td><?php echo round($asupan['avg_lemak']); ?> g</td>
                <td>40 g</td>
                <td><?php echo round(($asupan['avg_lemak'] / 40) * 100); ?>%</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>💡 Rekomendasi dari Tenaga Kesehatan</h2>
        <?php if (count($rekomendasi_list) > 0): ?>
            <?php foreach ($rekomendasi_list as $rek): ?>
            <div class="recommendation">
                <div class="recommendation-title"><?php echo htmlspecialchars($rek['sumber']); ?></div>
                <div class="recommendation-text">
                    <?php echo htmlspecialchars($rek['isi_rekomendasi']); ?>
                </div>
                <div style="margin-top: 5px; font-size: 12px; color: #999;">
                    oleh <?php echo htmlspecialchars($rek['nama_tenaga_kesehatan']); ?> - 
                    <?php echo date('d F Y', strtotime($rek['tanggal_rekomendasi'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #666; font-style: italic;">Belum ada rekomendasi dari tenaga kesehatan.</p>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p><strong>Dokumen ini dibuat secara otomatis oleh sistem NutriGrow</strong></p>
        <p>© <?php echo date('Y'); ?> NutriGrow. Semua hak dilindungi.</p>
        <p style="margin-top: 10px;"><em>Untuk informasi lebih lanjut, hubungi tenaga kesehatan Anda</em></p>
    </div>

    <script>
        // Auto print dialog (optional - uncomment jika ingin langsung muncul print dialog)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>

<?php
/* 
 * CATATAN IMPLEMENTASI PDF YANG LEBIH BAIK:
 * 
 * File ini menghasilkan HTML yang print-friendly. User bisa:
 * 1. Klik tombol "Print / Save as PDF" di kanan atas
 * 2. Atau tekan Ctrl+P
 * 3. Pilih "Save as PDF" di printer destination
 * 
 * Untuk implementasi PDF library yang lebih profesional, gunakan:
 * 
 * 1. mPDF (Recommended):
 *    composer require mpdf/mpdf
 * 
 *    require_once __DIR__ . '/vendor/autoload.php';
 *    $mpdf = new \Mpdf\Mpdf();
 *    $html = '...'; // HTML content
 *    $mpdf->WriteHTML($html);
 *    $mpdf->Output('laporan.pdf', 'D'); // D = download, I = inline view
 * 
 * 2. TCPDF:
 *    composer require tecnickcom/tcpdf
 * 
 * 3. DOMPDF:
 *    composer require dompdf/dompdf
 * 
 * Untuk sekarang, metode print-to-PDF dari browser sudah cukup untuk demo.
 */
?>