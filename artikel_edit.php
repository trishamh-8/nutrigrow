<?php
session_start();
require_once 'config.php';
$conn = getConnection();

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: artikel.php');
    exit;
}

// Ambil user info
$stmt = $conn->prepare("SELECT * FROM akun WHERE id_akun = ?");
$stmt->execute([$_SESSION['id_akun']]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy(); header('Location: login.php'); exit;
}
$user['role'] = getUserRole($conn, $_SESSION['id_akun']);

// Ambil artikel
$stmt = $conn->prepare("SELECT * FROM artikel WHERE id_artikel = ? LIMIT 1");
$stmt->execute([$id]);
$artikel = $stmt->fetch();
if (!$artikel) {
    header('Location: artikel.php'); exit;
}

// Cek permission
$canManage = false;
if ($user['role'] === 'admin') $canManage = true;
if ($user['role'] === 'tenaga_kesehatan' && isset($artikel['penulis']) && $artikel['penulis'] === $user['nama']) $canManage = true;
if (!$canManage) {
    echo '<p>Akses ditolak: Anda tidak memiliki izin untuk mengedit artikel ini.</p>';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $gambar = trim($_POST['gambar'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'published';

    if (empty($judul) || empty($isi)) {
        $error = 'Judul dan isi wajib diisi.';
    } else {
        $stmtUp = $conn->prepare("UPDATE artikel SET kategori = ?, judul_artikel = ?, isi_artikel = ?, gambar_cover = ?, status = ? WHERE id_artikel = ?");
        $stmtUp->execute([$kategori ?: null, $judul, $isi, $gambar ?: null, $status, $id]);
        header('Location: artikel.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Artikel</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background:#f5f7fa; display:flex; min-height:100vh; }
        .main-content { margin-left: 240px; flex:1; padding: 20px 40px; }
        .page-header { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; }
        .page-header h2 { margin:0; font-size:24px; color:#333; }
        .back-btn { color:#666; text-decoration:none; padding:8px 12px; border:1px solid #e0e0e0; border-radius:8px; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
        .back-btn:hover { background:#f1f5f9; color:#333; }
        .box { max-width:800px; margin:0 auto; background:white; padding:24px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        h2 { margin-bottom:16px; font-size:24px; color:#333; }
        label { display:block; margin-bottom:6px; font-weight:600; color:#1e293b; }
        input[type=text], textarea, select { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:12px; font-size:14px; }
        .btn-save { padding:10px 14px; background:#06b6d4; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
        .btn-save:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(6,182,212,0.2); }
        a { color:#0891b2; text-decoration:none; }
        a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main-content">
<div class="page-header">
    <h2>Edit Artikel</h2>
    <a href="artikel.php" class="back-btn">← Kembali</a>
</div>
<div class="box">
    <?php if ($error): ?><div style="color:#b91c1c"><?php echo $error; ?></div><?php endif; ?>
    <form method="POST" action="">
        <label>Judul</label>
        <input type="text" name="judul" required value="<?php echo htmlspecialchars($artikel['judul_artikel']); ?>">

        <label>Kategori</label>
        <select name="kategori">
            <?php
            $categories = ['gizi' => 'Gizi', 'kesehatan' => 'Kesehatan', 'tumbuh_kembang' => 'Tumbuh Kembang', 'tips' => 'Tips', 'resep' => 'Resep', 'lainnya' => 'Lainnya'];
            $currentCat = $artikel['kategori'] ?? '';
            foreach ($categories as $key => $label) {
                $sel = ($key === $currentCat) ? 'selected' : '';
                echo "<option value=\"" . htmlspecialchars($key) . "\" $sel>" . htmlspecialchars($label) . "</option>";
            }
            ?>
        </select>

        <label>Isi</label>
        <textarea name="isi" rows="8"><?php echo htmlspecialchars($artikel['isi_artikel']); ?></textarea>

        <label>Gambar URL</label>
        <input type="text" name="gambar" value="<?php echo htmlspecialchars($artikel['gambar_cover']); ?>">

        <label>Status</label>
        <select name="status">
            <option value="published" <?php echo $artikel['status']==='published' ? 'selected' : ''; ?>>Published</option>
            <option value="draft" <?php echo $artikel['status']==='draft' ? 'selected' : ''; ?>>Draft</option>
            <option value="archived" <?php echo $artikel['status']==='archived' ? 'selected' : ''; ?>>Archived</option>
        </select>

        <div style="margin-top:12px">
            <button type="submit" class="btn-save">Simpan</button>
            <a href="artikel.php" style="margin-left:10px">Batal</a>
        </div>
    </form>
</div>
</main>
</body>
</html>
