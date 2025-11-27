<?php
// jadwal-handler.php - handle create/edit/delete/complete actions for jadwal
require_once 'config.php';
session_start();

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

$conn = getConnection();
$id_akun = $_SESSION['id_akun'];
$role = $_SESSION['role'] ?? 'pengguna';

$action = $_REQUEST['action'] ?? '';

function redirect_back($id_balita = 0, $msg = null) {
    if ($msg) $_SESSION['flash_jadwal'] = $msg;
    $url = 'jadwal.php';
    if ($id_balita) $url .= '?id_balita=' . (int)$id_balita;
    header('Location: ' . $url);
    exit;
}

try {
    if ($action === 'tambah') {
        // only tenaga_kesehatan can add
        if ($role !== 'tenaga_kesehatan') {
            redirect_back($_POST['id_balita'] ?? 0, 'Akses ditolak: tidak diizinkan menambah jadwal.');
        }

        // Ensure optional jadwal columns exist (safe attempts)
        try {
            $conn->exec("ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS judul VARCHAR(200) NULL AFTER jenis");
        } catch (Exception $e) {}
        try {
            $conn->exec("ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS deskripsi TEXT NULL AFTER lokasi");
        } catch (Exception $e) {}

        $id_balita = (int)($_POST['id_balita'] ?? 0);

        $id_balita = (int)($_POST['id_balita'] ?? 0);
        $jenis = $_POST['jenis'] ?? '';
        $judul = $_POST['judul'] ?? '';
        $tanggal = $_POST['tanggal'] ?? null;
        $lokasi = $_POST['lokasi'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';

        if (!$id_balita || !$jenis || !$tanggal) {
            redirect_back($id_balita, 'Data tidak lengkap: pastikan jenis, tanggal, dan balita terpilih.');
        }

        // Insert using available columns; if judul/deskripsi exist we'll insert them, otherwise store deskripsi into catatan_hasil
        $has_judul = true; $has_deskripsi = true;
        try {
            $cols = [];
            $placeholders = [];
            $values = [];

            $cols[] = 'id_balita'; $placeholders[] = '?'; $values[] = $id_balita;
            $cols[] = 'jenis'; $placeholders[] = '?'; $values[] = $jenis;
            $cols[] = 'tanggal'; $placeholders[] = '?'; $values[] = $tanggal;
            $cols[] = 'lokasi'; $placeholders[] = '?'; $values[] = $lokasi;
            // try to include judul/deskripsi if columns exist
            $cols[] = 'judul'; $placeholders[] = '?'; $values[] = $judul;
            $cols[] = 'deskripsi'; $placeholders[] = '?'; $values[] = $deskripsi;
            $cols[] = 'status'; $placeholders[] = '?'; $values[] = 'terjadwal';

            $sql = 'INSERT INTO jadwal (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmt = $conn->prepare($sql);
            $stmt->execute($values);
            redirect_back($id_balita, 'Jadwal berhasil ditambahkan.');
        } catch (Exception $e) {
            // fallback: insert without judul/deskripsi, store description in catatan_hasil
            try {
                $sql = 'INSERT INTO jadwal (id_balita, jenis, tanggal, lokasi, catatan_hasil, status) VALUES (?, ?, ?, ?, ?, ?)';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id_balita, $jenis, $tanggal, $lokasi, trim(($judul ? $judul . "\n" : '') . $deskripsi), 'terjadwal']);
                redirect_back($id_balita, 'Jadwal berhasil ditambahkan (fallback).');
            } catch (Exception $e2) {
                redirect_back($id_balita, 'Gagal menambah jadwal: ' . $e2->getMessage());
            }
        }

    } elseif ($action === 'delete') {
        // only tenaga_kesehatan can delete
        if ($role !== 'tenaga_kesehatan') {
            redirect_back($_GET['id_balita'] ?? 0, 'Akses ditolak: tidak diizinkan menghapus jadwal.');
        }
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) redirect_back($_GET['id_balita'] ?? 0, 'ID jadwal tidak valid.');
        $stmt = $conn->prepare('DELETE FROM jadwal WHERE id_jadwal = ?');
        $stmt->execute([$id]);
        redirect_back($_GET['id_balita'] ?? 0, 'Jadwal berhasil dihapus.');

    } elseif ($action === 'edit') {
        // only tenaga_kesehatan can edit
        if ($role !== 'tenaga_kesehatan') {
            redirect_back($_POST['id_balita'] ?? 0, 'Akses ditolak: tidak diizinkan mengedit jadwal.');
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) redirect_back($_POST['id_balita'] ?? 0, 'ID jadwal tidak valid.');

        // retrieve id_balita from POST (was missing previously)
        $id_balita = (int)($_POST['id_balita'] ?? 0);

        $jenis = $_POST['jenis'] ?? '';
        $judul = $_POST['judul'] ?? '';
        $tanggal = $_POST['tanggal'] ?? null;
        $lokasi = $_POST['lokasi'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';

        try {
            $sql = 'UPDATE jadwal SET id_balita = ?, jenis = ?, tanggal = ?, lokasi = ?, judul = ?, deskripsi = ? WHERE id_jadwal = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_balita, $jenis, $tanggal, $lokasi, $judul, $deskripsi, $id]);
            redirect_back($id_balita ?? ($_POST['id_balita'] ?? 0), 'Jadwal berhasil diperbarui.');
        } catch (Exception $e) {
            // fallback: update catatan_hasil
            try {
                $sql = 'UPDATE jadwal SET id_balita = ?, jenis = ?, tanggal = ?, lokasi = ?, catatan_hasil = ? WHERE id_jadwal = ?';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id_balita, $jenis, $tanggal, $lokasi, trim(($judul ? $judul . "\n" : '') . $deskripsi), $id]);
                redirect_back($id_balita ?? ($_POST['id_balita'] ?? 0), 'Jadwal berhasil diperbarui (fallback).');
            } catch (Exception $e2) {
                redirect_back($id_balita ?? ($_POST['id_balita'] ?? 0), 'Gagal memperbarui jadwal: ' . $e2->getMessage());
            }
        }

    } elseif ($action === 'complete') {
        // both tenaga_kesehatan and pengguna can mark complete, but pengguna only for their own balita
        $id = (int)($_GET['id'] ?? 0);
        $id_balita = (int)($_GET['id_balita'] ?? 0);
        if (!$id) redirect_back($id_balita, 'ID jadwal tidak valid.');

        // fetch jadwal and its balita owner
        $stmt = $conn->prepare('SELECT j.id_jadwal, j.id_balita, b.id_akun FROM jadwal j LEFT JOIN balita b ON j.id_balita = b.id_balita WHERE j.id_jadwal = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) redirect_back($id_balita, 'Jadwal tidak ditemukan.');

        $owner_akun = $row['id_akun'];
        if ($role !== 'tenaga_kesehatan' && $owner_akun != $id_akun) {
            redirect_back($id_balita, 'Akses ditolak: Anda tidak berwenang menandai jadwal ini.');
        }

        $stmt = $conn->prepare('UPDATE jadwal SET status = ? WHERE id_jadwal = ?');
        $stmt->execute(['selesai', $id]);
        redirect_back($id_balita, 'Jadwal ditandai selesai.');

    } else {
        redirect_back(0, 'Aksi tidak dikenali.');
    }
} catch (Exception $e) {
    redirect_back(0, 'Terjadi kesalahan: ' . $e->getMessage());
}

?>
