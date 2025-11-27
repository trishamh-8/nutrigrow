<?php
// tambah_pertumbuhan.php - Form untuk menambah data pertumbuhan balita
require_once 'auth.php';
requireLogin();

$conn = getDBConnection(); // mysqli
$id_akun = $_SESSION['id_akun'];

$id_balita = isset($_GET['id_balita']) ? (int)$_GET['id_balita'] : (isset($_POST['id_balita']) ? (int)$_POST['id_balita'] : 0);
if ($id_balita <= 0) {
    echo "<p>ID balita tidak valid.</p>";
    exit;
}

// Verifikasi balita milik pengguna (hanya pengguna boleh menambah dari halaman ini)
$user_role = $_SESSION['role'] ?? 'pengguna';
if ($user_role !== 'pengguna') {
    echo "<p>Akses ditolak: hanya orang tua (pengguna) yang dapat menambah data pertumbuhan melalui halaman ini.</p>";
    exit;
}

$stmt = $conn->prepare('SELECT id_balita FROM balita WHERE id_balita = ? AND id_akun = ?');
$stmt->bind_param('ii', $id_balita, $id_akun);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo "<p>Akses ditolak: balita tidak ditemukan atau bukan milik akun Anda.</p>";
    exit;
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal_pemeriksaan'] ?? '';
    $berat = $_POST['berat_badan'] ?? '';
    $tinggi = $_POST['tinggi_badan'] ?? '';
    $lk = $_POST['lingkar_kepala'] ?? null;
    $status_gizi = $_POST['status_gizi'] ?? null;
    $zscore = $_POST['zscore'] ?? null;
    $catatan = $_POST['catatan'] ?? null;

    // Validasi minimal
    if (empty($tanggal) || $berat === '' || $tinggi === '') {
        $error = 'Tanggal pemeriksaan, berat, dan tinggi wajib diisi.';
    } else {
        $query = "INSERT INTO pertumbuhan (id_balita, id_akun, tanggal_pemeriksaan, berat_badan, tinggi_badan, lingkar_kepala, status_gizi, zscore, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt2 = $conn->prepare($query);
        // Cast numeric values to proper types (allow nulls for optional fields)
        $berat_f = (float)$berat;
        $tinggi_f = (float)$tinggi;
        $lk_f = ($lk !== null && $lk !== '') ? (float)$lk : null;
        $zscore_f = ($zscore !== null && $zscore !== '') ? (float)$zscore : null;

        // Types: id_balita(i), id_akun(i), tanggal(s), berat(d), tinggi(d), lingkar_kepala(d), status_gizi(s), zscore(d), catatan(s)
        $types = 'iisdddsds';
        $stmt2->bind_param($types, $id_balita, $id_akun, $tanggal, $berat_f, $tinggi_f, $lk_f, $status_gizi, $zscore_f, $catatan);

        if ($stmt2->execute()) {
            $message = 'Data pertumbuhan berhasil disimpan.';
            header('Location: pertumbuhan.php?id=' . $id_balita);
            exit;
        } else {
            $error = 'Gagal menyimpan data: ' . $conn->error;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Pertumbuhan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background:#f8fafc; color:#0f172a; }
        .container { max-width:720px; margin:40px auto; padding:20px; }
        .card { background:white; padding:20px; border-radius:12px; box-shadow:0 1px 6px rgba(2,6,23,0.06); }
        .form-group { margin-bottom:12px; }
        label { display:block; font-weight:600; margin-bottom:6px; }
        input[type="text"], input[type="date"], textarea { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; }
        .actions { display:flex; gap:10px; margin-top:16px; }
        .btn { padding:10px 14px; border-radius:8px; text-decoration:none; }
        .btn-primary { background:#06b6d4; color:white; border:none; }
        .btn-secondary { background:#f1f5f9; color:#0f172a; border:1px solid #e2e8f0; }
        .alert { padding:10px 12px; border-radius:8px; margin-bottom:10px; }
        .alert-error { background:#fee2e2; color:#b91c1c; }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        <div class="card">
            <h2>Tambah Data Pertumbuhan</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="id_balita" value="<?php echo $id_balita; ?>">

                <div class="form-group">
                    <label>Tanggal Pemeriksaan</label>
                    <input type="date" name="tanggal_pemeriksaan" required value="<?php echo htmlspecialchars($_POST['tanggal_pemeriksaan'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Berat Badan (kg)</label>
                    <input type="text" name="berat_badan" required placeholder="misal: 7.50" value="<?php echo htmlspecialchars($_POST['berat_badan'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Tinggi / Panjang Badan (cm)</label>
                    <input type="text" name="tinggi_badan" required placeholder="misal: 65.0" value="<?php echo htmlspecialchars($_POST['tinggi_badan'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Lingkar Kepala (cm)</label>
                    <input type="text" name="lingkar_kepala" placeholder="opsional" value="<?php echo htmlspecialchars($_POST['lingkar_kepala'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Status Gizi</label>
                    <input type="text" name="status_gizi" placeholder="misal: Normal / Kurang" value="<?php echo htmlspecialchars($_POST['status_gizi'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Z-Score (opsional)</label>
                    <input type="text" name="zscore" placeholder="misal: -1.25" value="<?php echo htmlspecialchars($_POST['zscore'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="4"><?php echo htmlspecialchars($_POST['catatan'] ?? ''); ?></textarea>
                </div>

                <div class="actions">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Simpan</button>
                    <a class="btn btn-secondary" href="pertumbuhan.php?id=<?php echo $id_balita; ?>">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
