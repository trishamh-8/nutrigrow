<?php
// asupan_harian.php - CRUD Asupan Harian (Lengkap dalam 1 file)
require_once 'config.php';
require_once 'auth.php';

$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];
$message = '';
$error = '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list'; // Mode: list, add, edit
$edit_data = null;

// Handle messages from redirects
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'add_success':
            $message = "Data asupan berhasil ditambahkan!";
            break;
        case 'update_success':
            $message = "Data asupan berhasil diperbarui!";
            break;
        case 'delete_success':
            $message = "Data asupan berhasil dihapus!";
            break;
    }
}

// Get user info
$stmt = $conn->prepare("
    SELECT a.nama,
           CASE 
               WHEN t.id_tenaga_kesehatan IS NOT NULL THEN 'tenaga_kesehatan'
               WHEN p.id_pengguna IS NOT NULL THEN 'pengguna'
               WHEN adm.id_admin IS NOT NULL THEN 'admin'
               ELSE 'pengguna'
           END as role
    FROM akun a
    LEFT JOIN tenaga_kesehatan t ON t.id_akun = a.id_akun
    LEFT JOIN pengguna p ON p.id_akun = a.id_akun
    LEFT JOIN admin adm ON adm.id_akun = a.id_akun
    WHERE a.id_akun = ?
");
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get all balita for this user
$query_balita = "SELECT id_balita, nama_balita, tanggal_lahir, jenis_kelamin FROM balita WHERE id_akun = ? ORDER BY nama_balita ASC";
$stmt = $conn->prepare($query_balita);
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result_balita = $stmt->get_result();
$balita_list = $result_balita->fetch_all(MYSQLI_ASSOC);

// Get selected balita or use first one
$selected_balita_id = isset($_GET['id_balita']) ? $_GET['id_balita'] : ($balita_list[0]['id_balita'] ?? null);

// Get selected balita details
$stmt = $conn->prepare("SELECT * FROM balita WHERE id_balita = ? AND id_akun = ?");
$stmt->bind_param("ii", $selected_balita_id, $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$balita = $result->fetch_assoc();

// ========== PROSES HAPUS DATA ==========
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_asupan = $_GET['id'];
    
    // Verify that this asupan belongs to a balita owned by the logged-in user
    $query_verify = "SELECT ah.id_asupan 
                     FROM asupan_harian ah
                     JOIN balita b ON ah.id_balita = b.id_balita
                     WHERE ah.id_asupan = ? 
                     AND b.id_akun = ? 
                     AND ah.id_balita = ?";
    
    $stmt = $conn->prepare($query_verify);
    $stmt->bind_param("iii", $id_asupan, $id_akun, $selected_balita_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $query_delete = "DELETE FROM asupan_harian WHERE id_asupan = ?";
        $stmt = $conn->prepare($query_delete);
        $stmt->bind_param("i", $id_asupan);
        
        if ($stmt->execute()) {
            $message = "Data asupan berhasil dihapus!";
        } else {
            $error = "Gagal menghapus data!";
        }
    }
}

// ========== PROSES TAMBAH/UPDATE DATA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal_catatan = $_POST['tanggal_catatan'];
    $waktu_makan = $_POST['waktu_makan']; // Now using ENUM values from the database
    $jenis_makanan = $_POST['jenis_makanan'];
    $porsi = $_POST['porsi'];
    $kalori_total = isset($_POST['kalori_total']) ? $_POST['kalori_total'] : 0;
    $protein = isset($_POST['protein']) ? $_POST['protein'] : 0;
    $karbohidrat = 0; // Removed from form since it's not in the database
    $lemak = 0; // Removed from form since it's not in the database
    $id_asupan_edit = isset($_POST['id_asupan']) ? $_POST['id_asupan'] : null;
    
    // Validasi input
    if (empty($tanggal_catatan) || empty($waktu_makan) || empty($jenis_makanan) || 
        empty($porsi) || $kalori_total < 0) {
        $error = "Semua field wajib diisi dengan benar!";
        $mode = $id_asupan_edit ? 'edit' : 'add';
    } else {
        if ($id_asupan_edit) {
            // UPDATE data
            $query_update = "UPDATE asupan_harian 
                           SET tanggal_catatan = ?, 
                               waktu_makan = ?, 
                               jenis_makanan = ?, 
                               porsi = ?, 
                               kalori_total = ?, 
                               protein = ?
                           WHERE id_asupan = ? AND id_balita = ?";
            
            $stmt = $conn->prepare($query_update);
            $stmt->bind_param("ssssddii", 
                           $tanggal_catatan, 
                           $waktu_makan, 
                           $jenis_makanan, 
                           $porsi, 
                           $kalori_total, 
                           $protein,
                           $id_asupan_edit, 
                           $selected_balita_id);
            
            if ($stmt->execute()) {
                header("Location: asupan_harian.php?id_balita=" . $selected_balita_id . "&message=update_success");
                exit;
            } else {
                $error = "Gagal memperbarui data: " . $conn->error;
                $mode = 'edit';
            }
        } else {
            // INSERT data baru
            $query_insert = "INSERT INTO asupan_harian 
                            (id_balita, tanggal_catatan, waktu_makan, jenis_makanan, porsi, kalori_total, protein) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query_insert);
            $stmt->bind_param("issssdd", 
                           $selected_balita_id, 
                           $tanggal_catatan, 
                           $waktu_makan, 
                           $jenis_makanan, 
                           $porsi, 
                           $kalori_total, 
                           $protein);
            
            if ($stmt->execute()) {
                header("Location: asupan_harian.php?id_balita=" . $selected_balita_id . "&message=add_success");
                exit;
            } else {
                $error = "Gagal menyimpan data: " . $conn->error;
                $mode = 'add';
            }
        }
    }
}

// Rest of the file remains unchanged