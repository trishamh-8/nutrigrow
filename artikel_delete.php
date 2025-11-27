<?php
session_start();
require_once 'config.php';
$conn = getConnection();

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php'); exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: artikel.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM artikel WHERE id_artikel = ? LIMIT 1");
$stmt->execute([$id]);
$artikel = $stmt->fetch();
if (!$artikel) { header('Location: artikel.php'); exit; }

$stmtUser = $conn->prepare("SELECT * FROM akun WHERE id_akun = ?");
$stmtUser->execute([$_SESSION['id_akun']]);
$user = $stmtUser->fetch();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }
$user['role'] = getUserRole($conn, $_SESSION['id_akun']);

$canManage = false;
if ($user['role'] === 'admin') $canManage = true;
if ($user['role'] === 'tenaga_kesehatan' && isset($artikel['penulis']) && $artikel['penulis'] === $user['nama']) $canManage = true;
if (!$canManage) {
    echo '<p>Akses ditolak: Anda tidak memiliki izin untuk menghapus artikel ini.</p>'; exit;
}

$stmtDel = $conn->prepare("DELETE FROM artikel WHERE id_artikel = ?");
$stmtDel->execute([$id]);
header('Location: artikel.php'); exit;
