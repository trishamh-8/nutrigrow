<?php
// test_rekomendasi.php - File untuk testing insert rekomendasi
require_once 'config.php';

$conn = getDBConnection();

echo "<h2>Testing Insert Rekomendasi Gizi</h2>";
echo "<hr>";

// Set session sebagai tenaga kesehatan
$_SESSION['id_akun'] = 2;
$_SESSION['nama'] = 'dr. Sarah Wijaya, Sp.A';
$_SESSION['role'] = 'tenaga_kesehatan';

echo "<p><strong>Session saat ini:</strong></p>";
echo "<ul>";
echo "<li>ID Akun: " . $_SESSION['id_akun'] . "</li>";
echo "<li>Nama: " . $_SESSION['nama'] . "</li>";
echo "<li>Role: " . $_SESSION['role'] . "</li>";
echo "</ul>";

// Cek balita yang ada
echo "<h3>1. Cek Balita yang Tersedia</h3>";
$query_balita = "SELECT b.*, a.nama as nama_ortu 
                 FROM balita b
                 JOIN pengguna p ON b.id_akun = p.id_akun
                 JOIN akun a ON p.id_akun = a.id_akun";
$result_balita = $conn->query($query_balita);

if ($result_balita->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nama Balita</th><th>Tanggal Lahir</th><th>Orang Tua</th></tr>";
    while ($row = $result_balita->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id_balita'] . "</td>";
        echo "<td>" . $row['nama_balita'] . "</td>";
        echo "<td>" . $row['tanggal_lahir'] . "</td>";
        echo "<td>" . $row['nama_ortu'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Tidak ada balita di database!</p>";
}

// Test insert rekomendasi
echo "<h3>2. Test Insert Rekomendasi Baru</h3>";

$id_balita = 1;
$id_pertumbuhan = null;
$id_akun = $_SESSION['id_akun'];
$sumber = 'tenaga_kesehatan';
$isi_rekomendasi = "TEST REKOMENDASI:\n\nBerdasarkan pemeriksaan, direkomendasikan untuk:\n1. Meningkatkan asupan protein\n2. Tambahkan sayuran hijau\n3. Rutin kontrol setiap bulan";
$tanggal_rekomendasi = date('Y-m-d');
$prioritas = 'Tinggi';
$kategori = 'Nutrisi';

echo "<p><strong>Data yang akan diinsert:</strong></p>";
echo "<ul>";
echo "<li>ID Balita: " . $id_balita . "</li>";
echo "<li>ID Pertumbuhan: " . ($id_pertumbuhan ?? 'NULL') . "</li>";
echo "<li>ID Akun (Nakes): " . $id_akun . "</li>";
echo "<li>Sumber: " . $sumber . "</li>";
echo "<li>Prioritas: " . $prioritas . "</li>";
echo "<li>Kategori: " . $kategori . "</li>";
echo "<li>Tanggal: " . $tanggal_rekomendasi . "</li>";
echo "<li>Isi: <pre>" . htmlspecialchars($isi_rekomendasi) . "</pre></li>";
echo "</ul>";

$query_insert = "INSERT INTO rekomendasi_gizi 
                (id_balita, id_pertumbuhan, id_akun, sumber, isi_rekomendasi, 
                 tanggal_rekomendasi, prioritas, kategori) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($query_insert);
$stmt->bind_param("iiisssss", $id_balita, $id_pertumbuhan, $id_akun, $sumber, 
                 $isi_rekomendasi, $tanggal_rekomendasi, $prioritas, $kategori);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    echo "<p style='color: green; font-weight: bold;'>✓ INSERT BERHASIL! ID Rekomendasi: " . $new_id . "</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ INSERT GAGAL!</p>";
    echo "<p>Error: " . $stmt->error . "</p>";
}

// Cek data yang baru diinsert
echo "<h3>3. Verifikasi Data yang Tersimpan</h3>";

$query_check = "SELECT 
    rg.*,
    b.nama_balita,
    a.nama as nama_nakes
    FROM rekomendasi_gizi rg
    JOIN balita b ON rg.id_balita = b.id_balita
    LEFT JOIN akun a ON rg.id_akun = a.id_akun
    WHERE rg.id_akun = ?
    ORDER BY rg.id_rekomendasi DESC
    LIMIT 5";

$stmt = $conn->prepare($query_check);
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result_check = $stmt->get_result();

if ($result_check->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>Balita</th><th>Nakes</th><th>Prioritas</th><th>Kategori</th><th>Tanggal</th><th>Isi (Preview)</th></tr>";
    while ($row = $result_check->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id_rekomendasi'] . "</td>";
        echo "<td>" . $row['nama_balita'] . "</td>";
        echo "<td>" . $row['nama_nakes'] . "</td>";
        echo "<td>" . $row['prioritas'] . "</td>";
        echo "<td>" . $row['kategori'] . "</td>";
        echo "<td>" . $row['tanggal_rekomendasi'] . "</td>";
        echo "<td>" . substr($row['isi_rekomendasi'], 0, 50) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='color: green;'>✓ Data berhasil tersimpan dan dapat dibaca!</p>";
} else {
    echo "<p style='color: red;'>✗ Tidak ada data rekomendasi dari tenaga kesehatan ini!</p>";
}

// Cek apakah orang tua bisa melihat
echo "<h3>4. Cek Visibilitas untuk Orang Tua</h3>";

$query_ortu = "SELECT 
    rg.*,
    CASE 
        WHEN rg.sumber = 'tenaga_kesehatan' THEN a.nama
        ELSE 'Sistem Otomatis'
    END as pembuat
    FROM rekomendasi_gizi rg
    LEFT JOIN akun a ON rg.id_akun = a.id_akun
    WHERE rg.id_balita = ?
    ORDER BY rg.tanggal_rekomendasi DESC";

$stmt = $conn->prepare($query_ortu);
$stmt->bind_param("i", $id_balita);
$stmt->execute();
$result_ortu = $stmt->get_result();

if ($result_ortu->num_rows > 0) {
    echo "<p style='color: green;'>✓ Orang tua dapat melihat " . $result_ortu->num_rows . " rekomendasi untuk balita ini</p>";
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>Pembuat</th><th>Sumber</th><th>Prioritas</th><th>Tanggal</th></tr>";
    while ($row = $result_ortu->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id_rekomendasi'] . "</td>";
        echo "<td>" . $row['pembuat'] . "</td>";
        echo "<td>" . $row['sumber'] . "</td>";
        echo "<td>" . $row['prioritas'] . "</td>";
        echo "<td>" . $row['tanggal_rekomendasi'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ Orang tua tidak dapat melihat rekomendasi!</p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<ul>";
echo "<li><a href='nakes_rekomendasi_gizi.php'>Lihat di Halaman Tenaga Kesehatan</a></li>";
echo "<li><a href='rekomendasi_gizi.php'>Lihat di Halaman Orang Tua</a> (ubah session di config.php)</li>";
echo "</ul>";

$conn->close();
?>

<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; margin: 10px 0; }
    th { background: #0891b2; color: white; }
    tr:nth-child(even) { background: #f0f9ff; }
    pre { background: #f1f5f9; padding: 10px; border-radius: 5px; }
</style>