<?php
session_start();

require_once 'config.php';
$conn = getConnection();

// Hanya untuk tenaga_kesehatan
if (!isset($_SESSION['id_akun']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tenaga_kesehatan') {
    header('Location: artikel.php');
    exit;
}

// Ambil data akun dan data tenaga_kesehatan untuk nama penulis
$stmt = $conn->prepare("SELECT a.*, tk.sertifikasi FROM akun a LEFT JOIN tenaga_kesehatan tk ON tk.id_akun = a.id_akun WHERE a.id_akun = ?");
$stmt->execute([$_SESSION['id_akun']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Cari satu admin default untuk memenuhi foreign key id_admin
$stmtAdmin = $conn->prepare("SELECT id_admin FROM admin LIMIT 1");
$stmtAdmin->execute();
$adminRow = $stmtAdmin->fetch();
$default_admin_id = $adminRow ? $adminRow['id_admin'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $gambar = trim($_POST['gambar'] ?? '');
    $status = isset($_POST['status']) && in_array($_POST['status'], ['draft','published','archived']) ? $_POST['status'] : 'published';

    if (empty($judul) || empty($isi)) {
        $error = 'Judul dan isi artikel wajib diisi.';
    } elseif (!$default_admin_id) {
        $error = 'Belum ada admin terdaftar di sistem. Hubungi administrator.';
    } else {
        try {
            $tgl = date('Y-m-d');
            // Simpan penulis sebagai nama tenaga_kesehatan (dari akun)
            $penulis = $user['nama'];

            $stmtIns = $conn->prepare("INSERT INTO artikel (id_admin, kategori, judul_artikel, isi_artikel, penulis, tgl_terbit, gambar_cover, status, views) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmtIns->execute([$default_admin_id, $kategori ?: null, $judul, $isi, $penulis, $tgl, $gambar ?: null, $status]);

            $success = 'Artikel berhasil dibuat dan akan tampil jika berstatus published.';
            header('Location: artikel.php');
            exit;
        } catch (PDOException $e) {
            error_log('Artikel Create Error: ' . $e->getMessage());
            $error = 'Terjadi kesalahan saat menyimpan artikel.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Buat Artikel - NutriGrow</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background:#f5f7fa; }
        .container { max-width:800px; margin:40px auto; background:white; padding:24px; border-radius:12px; box-shadow:0 6px 24px rgba(0,0,0,0.06); }
        label{display:block;margin-bottom:6px;font-weight:600}
        input[type=text], textarea, select{width:100%;padding:10px;border:1px solid #e0e0e0;border-radius:8px;margin-bottom:12px}
        .btn{background:linear-gradient(90deg,#4FC3F7,#66BB6A);color:white;padding:10px 18px;border:none;border-radius:8px;cursor:pointer}
        .alert{padding:10px;border-radius:8px;margin-bottom:12px}
        .alert-error{background:#ffebee;color:#c62828}
        .alert-success{background:#e8f5e9;color:#2e7d32}
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="container">
        <h2>Buat Artikel Baru</h2>
        <p>Anda masuk sebagai <strong><?php echo htmlspecialchars($user['nama']); ?></strong> (Tenaga Kesehatan).</p>

        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <form method="POST" action="">
            <label for="judul">Judul</label>
            <input type="text" id="judul" name="judul" required value="<?php echo isset($_POST['judul']) ? htmlspecialchars($_POST['judul']) : ''; ?>">

            <label for="kategori">Kategori (opsional)</label>
            <input type="text" id="kategori" name="kategori" value="<?php echo isset($_POST['kategori']) ? htmlspecialchars($_POST['kategori']) : ''; ?>">

            <label for="isi">Isi Artikel</label>
            <textarea id="isi" name="isi" rows="10" required><?php echo isset($_POST['isi']) ? htmlspecialchars($_POST['isi']) : ''; ?></textarea>

            <label for="gambar">URL Gambar Cover (opsional)</label>
            <input type="text" id="gambar" name="gambar" value="<?php echo isset($_POST['gambar']) ? htmlspecialchars($_POST['gambar']) : ''; ?>">

            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="archived">Archived</option>
            </select>

            <div style="margin-top:12px">
                <button class="btn" type="submit">Simpan Artikel</button>
                <a href="artikel.php" style="margin-left:10px; color:#666; text-decoration:none">Batal</a>
            </div>
        </form>
    </div>
</body>
</html>
