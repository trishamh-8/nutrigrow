<?php
// rekomendasi_gizi.php - Halaman Rekomendasi Gizi
require_once 'auth.php';
requireLogin();

$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];

// Ambil data balita untuk user yang login
$query_balita = "SELECT id_balita, nama_balita FROM balita WHERE id_akun = ?";
$stmt = $conn->prepare($query_balita);
$stmt->bind_param("i", $id_akun);
$stmt->execute();
$result_balita = $stmt->get_result();
$balita = $result_balita->fetch_assoc();
$id_balita = $balita['id_balita'];

// Ambil semua rekomendasi untuk balita
$query_rekomendasi = "SELECT 
    rg.*,
    CASE 
        WHEN rg.sumber = 'tenaga_kesehatan' THEN a.nama
        ELSE 'Sistem Otomatis'
    END as pembuat
    FROM rekomendasi_gizi rg
    LEFT JOIN akun a ON rg.id_akun = a.id_akun
    WHERE rg.id_balita = ?
    ORDER BY 
        CASE prioritas
            WHEN 'Tinggi' THEN 1
            WHEN 'Sedang' THEN 2
            WHEN 'Rendah' THEN 3
        END,
        rg.tanggal_rekomendasi DESC";
        
$stmt = $conn->prepare($query_rekomendasi);
$stmt->bind_param("i", $id_balita);
$stmt->execute();
$result_rekomendasi = $stmt->get_result();

// Hitung jumlah berdasarkan prioritas
$query_count = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN prioritas = 'Tinggi' THEN 1 ELSE 0 END) as tinggi,
    SUM(CASE WHEN prioritas = 'Sedang' THEN 1 ELSE 0 END) as sedang,
    SUM(CASE WHEN prioritas = 'Rendah' THEN 1 ELSE 0 END) as rendah
    FROM rekomendasi_gizi 
    WHERE id_balita = ?";
    
$stmt = $conn->prepare($query_count);
$stmt->bind_param("i", $id_balita);
$stmt->execute();
$result_count = $stmt->get_result();
$counts = $result_count->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekomendasi Gizi - NutriGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/partials/common_head.php'; ?>
    <style>
        /* Wrapper layout matching body flex structure */
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" placeholder="Cari rekomendasi gizi...">
                </div>
                <div class="user-info">
                    <div>
                        <h4><?php echo htmlspecialchars($_SESSION['nama']); ?></h4>
                        <p>Orang Tua</p>
                    </div>
                    <div class="avatar"><i class="fas fa-user"></i></div>
                </div>
            </header>

            <!-- Page Header -->
            <div class="page-header">
                <h1>Rekomendasi Gizi</h1>
                <p>Saran dan rekomendasi dari tenaga kesehatan untuk balita Anda</p>
            </div>

            <!-- Priority Summary -->
            <div class="priority-grid">
                <div class="priority-card total">
                    <div class="priority-label">Total Rekomendasi</div>
                    <div class="priority-value"><?php echo $counts['total']; ?></div>
                </div>
                <div class="priority-card high">
                    <div class="priority-label">Prioritas Tinggi</div>
                    <div class="priority-value"><?php echo $counts['tinggi']; ?></div>
                </div>
                <div class="priority-card medium">
                    <div class="priority-label">Prioritas Sedang</div>
                    <div class="priority-value"><?php echo $counts['sedang']; ?></div>
                </div>
                <div class="priority-card low">
                    <div class="priority-label">Prioritas Rendah</div>
                    <div class="priority-value"><?php echo $counts['rendah']; ?></div>
                </div>
            </div>

            <!-- Recommendation List -->
            <?php if ($result_rekomendasi->num_rows > 0): ?>
            <div class="recommendation-list">
                <?php while ($row = $result_rekomendasi->fetch_assoc()): 
                    $prioritas_class = strtolower($row['prioritas']);
                    if ($prioritas_class == 'sedang') $prioritas_class = 'medium';
                    if ($prioritas_class == 'tinggi') $prioritas_class = 'high';
                ?>
                <div class="recommendation-card">
                    <div class="recommendation-header">
                        <div style="flex: 1;">
                            <h3 class="recommendation-title">
                                <?php 
                                if ($row['sumber'] == 'tenaga_kesehatan') {
                                    echo "Rekomendasi Personal dari " . htmlspecialchars($row['pembuat']);
                                } else {
                                    echo "Rekomendasi Sistem Otomatis";
                                }
                                ?>
                            </h3>
                            <div class="recommendation-meta">
                                <span>
                                    <i class="fas fa-user-md"></i> 
                                    <?php echo htmlspecialchars($row['pembuat']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo formatTanggalIndo($row['tanggal_rekomendasi']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <span class="badge badge-<?php echo $prioritas_class; ?>">
                                <i class="fas fa-exclamation-circle"></i> 
                                Prioritas <?php echo $row['prioritas']; ?>
                            </span>
                            <span class="badge badge-nutrisi">
                                <?php echo htmlspecialchars($row['kategori'] ?? 'Nutrisi'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="recommendation-content">
                        <?php 
                        $isi = htmlspecialchars($row['isi_rekomendasi']);
                        
                        // Pisahkan paragraf utama dan daftar
                        $parts = explode("\n", $isi);
                        $paragraf = [];
                        $daftar = [];
                        $in_list = false;
                        
                        foreach ($parts as $part) {
                            $part = trim($part);
                            if (empty($part)) continue;
                            
                            // Deteksi jika ini adalah item list (mulai dengan angka atau bullet)
                            if (preg_match('/^[\d]+[\.\)]/', $part) || preg_match('/^[-•]/', $part)) {
                                $in_list = true;
                                $daftar[] = preg_replace('/^[\d]+[\.\)]\s*/', '', $part);
                                $daftar[] = preg_replace('/^[-•]\s*/', '', $part);
                            } else if (!$in_list) {
                                $paragraf[] = $part;
                            }
                        }
                        
                        // Tampilkan paragraf
                        if (!empty($paragraf)) {
                            echo '<p>' . implode('</p><p>', $paragraf) . '</p>';
                        } else {
                            echo '<p>' . $isi . '</p>';
                        }
                        
                        // Tampilkan daftar jika ada
                        if (!empty($daftar)) {
                            echo '<div class="action-list">';
                            echo '<div class="action-list-title"><i class="fas fa-clipboard-check"></i> Saran Tindakan:</div>';
                            echo '<ol>';
                            foreach ($daftar as $item) {
                                if (!empty(trim($item))) {
                                    echo '<li>' . trim($item) . '</li>';
                                }
                            }
                            echo '</ol>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <?php if ($row['sumber'] == 'tenaga_kesehatan' && !empty($row['id_pertumbuhan'])): ?>
                    <div style="margin-top: 10px; padding: 10px; background: #f8fafc; border-radius: 8px; font-size: 13px; color: #64748b;">
                        <i class="fas fa-link"></i> Terkait dengan data pertumbuhan #<?php echo $row['id_pertumbuhan']; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="content-card" style="text-align: center; padding: 60px;">
                <i class="fas fa-clipboard-list" style="font-size: 64px; color: #e2e8f0; margin-bottom: 20px;"></i>
                <h3 style="color: #64748b; margin-bottom: 10px;">Belum Ada Rekomendasi</h3>
                <p style="color: #94a3b8;">Rekomendasi gizi akan muncul setelah tenaga kesehatan memberikan saran atau sistem menganalisis data pertumbuhan dan asupan balita Anda.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

<?php $conn->close(); ?>