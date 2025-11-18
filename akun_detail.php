<?php
// akun_detail.php - Tampilkan detail akun (hanya bisa diakses oleh tenaga kesehatan dan admin)
require_once 'auth.php';
requireRole(['tenaga_kesehatan', 'admin']); // Batasi akses

$conn = getDBConnection();
$id_akun = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_akun) {
    die("<div style='text-align:center;padding:30px;'><p>ID akun tidak valid.</p><a href='javascript:history.back()'>← Kembali</a></div>");
}

// Ambil detail akun dan data pengguna jika ada
$query = "SELECT 
            a.*,
            p.*,
            (SELECT COUNT(*) FROM balita b WHERE b.id_akun = a.id_akun) as jumlah_balita,
            (SELECT GROUP_CONCAT(nama_balita SEPARATOR '||') 
             FROM balita b 
             WHERE b.id_akun = a.id_akun) as daftar_balita,
            (SELECT GROUP_CONCAT(id_balita SEPARATOR '||') 
             FROM balita b 
             WHERE b.id_akun = a.id_akun) as id_balita_list
          FROM akun a 
          LEFT JOIN pengguna p ON p.id_akun = a.id_akun 
          WHERE a.id_akun = ? 
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result = $stmt->get_result();
$akun = $result->fetch_assoc();

if (!$akun) {
    echo "<p>Akun tidak ditemukan.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Detail Akun - <?php echo htmlspecialchars($akun['nama']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .detail-card { 
            max-width: 800px; 
            margin: 30px auto; 
            background: #fff; 
            border-radius: 12px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .detail-header {
            padding: 30px;
            background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%);
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-content {
            padding: 30px;
        }
        .detail-row { 
            display: flex; 
            gap: 24px; 
            align-items: start;
        }
        .avatar { 
            width: 88px; 
            height: 88px; 
            border-radius: 50%; 
            background: linear-gradient(135deg,#4FC3F7,#66BB6A); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #fff; 
            font-weight: 700; 
            font-size: 32px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .info { flex: 1; }
        .info h2 { 
            margin: 0 0 8px 0; 
            font-size: 28px;
            color: #1e293b;
        }
        .meta { 
            color: #64748b;
            font-size: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .meta-item {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .meta-item strong {
            display: block;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            color: #475569;
        }
        .back { 
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 24px;
            padding: 10px 16px;
            background: #f1f5f9;
            border-radius: 8px;
            text-decoration: none;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.2s;
        }
        .back:hover {
            background: #e2e8f0;
        }
        .section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }
        .section-title {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .balita-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .balita-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .balita-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .balita-actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
        }
        .btn-link {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-primary {
            background: #0ea5e9;
            color: white;
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-primary:hover { background: #0284c7; }
        .btn-secondary:hover { background: #e2e8f0; }
        
        .contact-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        .contact-btn {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .wa-btn {
            background: #25D366;
            color: white;
        }
        .wa-btn:hover {
            background: #128C7E;
        }
        .email-btn {
            background: #4338ca;
            color: white;
        }
        .email-btn:hover {
            background: #3730a3;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main style="margin-left:240px; padding:30px;">
        <div class="detail-card">
            <div class="detail-header">
                <div class="detail-row">
                    <div class="avatar"><?php echo strtoupper(substr($akun['nama'],0,1)); ?></div>
                    <div class="info">
                        <h2><?php echo htmlspecialchars($akun['nama']); ?></h2>
                        <div class="contact-actions">
                                <?php
                                    $rawPhone = $akun['nomor_telepon'] ?? ($akun['telepon'] ?? '');
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
                                    if (strlen($cleanPhone) > 0) {
                                        if ($cleanPhone[0] === '0') {
                                            $cleanPhone = '62' . substr($cleanPhone, 1);
                                        }
                                    }
                                ?>
                                <?php if (!empty($cleanPhone)): ?>
                                <a href="https://wa.me/<?php echo $cleanPhone; ?>" target="_blank" class="contact-btn wa-btn">
                                    <i class="fab fa-whatsapp"></i>
                                    WhatsApp
                                </a>
                                <?php endif; ?>
                            <?php if (!empty($akun['email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($akun['email']); ?>" class="contact-btn email-btn">
                                <i class="far fa-envelope"></i>
                                Email
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="detail-content">
                <div class="meta">
                    <div class="meta-item">
                        <strong>Email</strong>
                        <?php echo htmlspecialchars($akun['email'] ?? '-'); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Username</strong>
                        <?php echo htmlspecialchars($akun['username'] ?? '-'); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Telepon</strong>
                        <?php echo htmlspecialchars($akun['nomor_telepon'] ?? ($akun['telepon'] ?? '-')); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Alamat</strong>
                        <?php echo htmlspecialchars($akun['alamat'] ?? ($akun['alamat_pengguna'] ?? '-')); ?>
                    </div>
                </div>

                <?php if ($akun['jumlah_balita'] > 0 && !empty($akun['daftar_balita']) && !empty($akun['id_balita_list'])): ?>
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-child"></i>
                        Data Balita (<?php echo $akun['jumlah_balita']; ?>)
                    </h3>
                    <div class="balita-grid">
                        <?php
                        $nama_balita_array = explode('||', $akun['daftar_balita']);
                        $id_balita_array = explode('||', $akun['id_balita_list']);
                        
                        for($i = 0; $i < count($nama_balita_array); $i++):
                            $nama_balita = trim($nama_balita_array[$i]);
                            $id_balita = trim($id_balita_array[$i]);
                        ?>
                        <div class="balita-card">
                            <div class="balita-name"><?php echo htmlspecialchars($nama_balita); ?></div>
                            <div class="balita-actions">
                                <a href="pertumbuhan.php?id=<?php echo $id_balita; ?>" class="btn-link btn-primary">
                                    <i class="fas fa-chart-line"></i>
                                    Pertumbuhan
                                </a>
                                <a href="asupan.php?id=<?php echo $id_balita; ?>" class="btn-link btn-secondary">
                                    <i class="fas fa-utensils"></i>
                                    Asupan
                                </a>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-top:24px;">
                    <a href="javascript:history.back()" class="back">
                        <i class="fas fa-arrow-left"></i>
                        Kembali
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
