<?php
// pertumbuhan.php - Tampilkan riwayat pertumbuhan untuk satu balita
require_once 'config.php';
session_start();

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

$id_balita = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_balita <= 0) {
    echo "<p>ID balita tidak valid.</p>";
    exit;
}

$conn = null;
try {
    $conn = getConnection();
} catch (Exception $e) {
    echo "<p>Gagal koneksi database.</p>";
    exit;
}

// Ambil data balita
$stmt = $conn->prepare("SELECT b.*, a.nama as nama_ortu, a.id_akun as id_ortu_akun FROM balita b LEFT JOIN akun a ON b.id_akun = a.id_akun WHERE b.id_balita = ? LIMIT 1");
$stmt->execute([$id_balita]);
$balita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$balita) {
    echo "<p>Data balita tidak ditemukan.</p>";
    exit;
}

// Akses kontrol: jika role adalah pengguna, pastikan balita milik akun yang login
if (isset($_SESSION['role']) && $_SESSION['role'] === 'pengguna') {
    if ($balita['id_akun'] != $_SESSION['id_akun']) {
        echo "<p>Akses ditolak: Anda tidak memiliki izin melihat data ini.</p>";
        exit;
    }
}

$stmt2 = $conn->prepare("SELECT * FROM pertumbuhan WHERE id_balita = ? ORDER BY tanggal_pemeriksaan ASC");
$stmt2->execute([$id_balita]);
$rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

function formatTanggal($d) {
    if (!$d) return '-';
    return date('d M Y', strtotime($d));
}

function hitungUmur($tanggal_lahir) {
    $lahir = new DateTime($tanggal_lahir);
    $sekarang = new DateTime();
    $diff = $lahir->diff($sekarang);
    if ($diff->y > 0) {
        return $diff->y . ' tahun ' . $diff->m . ' bulan';
    } else {
        return $diff->m . ' bulan';
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pertumbuhan - <?php echo htmlspecialchars($balita['nama_balita']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background:#f8fafc; color:#0f172a; }
        .container { display:flex; }
        .main-content { margin-left:240px; padding:24px; flex:1; }
        .card { background:white; padding:20px; border-radius:12px; box-shadow:0 1px 4px rgba(2,6,23,0.06); margin-bottom:16px; }
        .balita-header { display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .balita-info { display:flex; gap:12px; align-items:center; }
        .avatar { width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg,#3b82f6,#06b6d4); color:white; display:flex; align-items:center; justify-content:center; font-size:28px; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:12px; border-bottom:1px solid #eef2f7; }
        th { background:#f8fafc; font-weight:600; }
        .empty { text-align:center; padding:40px; color:#64748b; }
        .btn { display:inline-flex; gap:8px; align-items:center; padding:8px 12px; border-radius:8px; text-decoration:none; background:#e2e8f0; color:#0f172a; }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <main class="main-content">
            <div class="card balita-header">
                <div class="balita-info">
                    <div class="avatar"><?php echo $balita['jenis_kelamin'] == 'L' ? '👦' : '👧'; ?></div>
                    <div>
                        <h2 style="margin:0"><?php echo htmlspecialchars($balita['nama_balita']); ?></h2>
                        <div style="color:#64748b; font-size:14px;">Lahir: <?php echo formatTanggal($balita['tanggal_lahir']); ?> • <?php echo hitungUmur($balita['tanggal_lahir']); ?></div>
                    </div>
                </div>
                <div>
                    <a class="btn" href="data_balita.php">&larr; Kembali</a>
                    <a class="btn" href="status_gizi.php?id_balita=<?php echo $balita['id_balita']; ?>">Lihat Status Gizi</a>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0;">Riwayat Pertumbuhan</h3>
                <?php if (count($rows) === 0): ?>
                    <div class="empty">
                        <i class="fas fa-chart-line" style="font-size:40px; color:#cbd5e1"></i>
                        <h4>Tidak ada data pertumbuhan</h4>
                        <p>Belum terdapat pencatatan pertumbuhan untuk balita ini.</p>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'pengguna'): ?>
                            <p><a class="btn" href="tambah_pertumbuhan.php?id_balita=<?php echo $balita['id_balita']; ?>">+ Tambah Data Pertumbuhan</a></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="overflow:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Berat (kg)</th>
                                <th>Tinggi (cm)</th>
                                <th>Lingkar Kepala (cm)</th>
                                <th>Z-Score</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r):
                            // gunakan kolom yang benar dari schema: tanggal_pemeriksaan, berat_badan, tinggi_badan, lingkar_kepala, zscore, catatan/status_gizi
                            $tanggal = $r['tanggal_pemeriksaan'] ?? $r['tanggal'] ?? $r['created_at'] ?? '-';
                            $berat = $r['berat_badan'] ?? $r['berat'] ?? $r['bb'] ?? '-';
                            $tinggi = $r['tinggi_badan'] ?? $r['panjang_badan'] ?? $r['tinggi'] ?? $r['tb'] ?? '-';
                            $lk = $r['lingkar_kepala'] ?? $r['lk'] ?? '-';
                            $z = $r['zscore'] ?? $r['bb_zscore'] ?? $r['tb_zscore'] ?? ($r['zscore_bb'] ?? '-');
                            $keterangan = $r['catatan'] ?? $r['status_gizi'] ?? $r['keterangan'] ?? '-';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars(formatTanggal($tanggal)); ?></td>
                                <td><?php echo htmlspecialchars($berat); ?></td>
                                <td><?php echo htmlspecialchars($tinggi); ?></td>
                                <td><?php echo htmlspecialchars($lk); ?></td>
                                <td><?php echo htmlspecialchars($z); ?></td>
                                <td><?php echo htmlspecialchars($keterangan); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
