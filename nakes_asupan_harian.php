<?php
// nakes_asupan_harian.php - Halaman Asupan Harian untuk Tenaga Kesehatan (View Only)
require_once 'config.php';
require_once 'auth.php';

$conn = getDBConnection();
$id_akun = $_SESSION['id_akun'];

// Filter berdasarkan balita yang dipilih
$selected_balita = isset($_GET['balita']) ? $_GET['balita'] : null;
$selected_date = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Ambil semua balita yang terdaftar (untuk dropdown filter)
$query_all_balita = "SELECT b.id_balita, b.nama_balita, p.id_akun, a.nama as nama_ortu
                     FROM balita b
                     JOIN pengguna p ON b.id_akun = p.id_akun
                     JOIN akun a ON p.id_akun = a.id_akun
                     ORDER BY b.nama_balita ASC";
$result_all_balita = $conn->query($query_all_balita);

// Jika belum pilih balita, ambil balita pertama
if (!$selected_balita && $result_all_balita->num_rows > 0) {
    $result_all_balita->data_seek(0);
    $first_balita = $result_all_balita->fetch_assoc();
    $selected_balita = $first_balita['id_balita'];
    $result_all_balita->data_seek(0);
}

// Ambil informasi balita yang dipilih
$balita_info = null;
if ($selected_balita) {
    $query_balita = "SELECT b.*, a.nama as nama_ortu, a.nomor_telepon
                     FROM balita b
                     JOIN pengguna p ON b.id_akun = p.id_akun
                     JOIN akun a ON p.id_akun = a.id_akun
                     WHERE b.id_balita = ?";
    $stmt = $conn->prepare($query_balita);
    $stmt->bind_param("i", $selected_balita);
    $stmt->execute();
    $result_balita = $stmt->get_result();
    $balita_info = $result_balita->fetch_assoc();
}

// Ambil data asupan untuk tanggal yang dipilih
$data_asupan = [];
$totals = ['total_kalori' => 0, 'total_protein' => 0, 'frekuensi' => 0];

if ($selected_balita) {
    $query_asupan = "SELECT * FROM asupan_harian 
                     WHERE id_balita = ? AND tanggal_catatan = ? 
                     ORDER BY waktu_makan ASC";
    $stmt = $conn->prepare($query_asupan);
    $stmt->bind_param("is", $selected_balita, $selected_date);
    $stmt->execute();
    $result_asupan = $stmt->get_result();

    // Hitung total kalori dan protein
    $query_total = "SELECT 
                    SUM(kalori_total) as total_kalori,
                    SUM(protein) as total_protein,
                    COUNT(*) as frekuensi
                    FROM asupan_harian 
                    WHERE id_balita = ? AND tanggal_catatan = ?";
    $stmt = $conn->prepare($query_total);
    $stmt->bind_param("is", $selected_balita, $selected_date);
    $stmt->execute();
    $result_total = $stmt->get_result();
    $totals = $result_total->fetch_assoc();
}

$total_kalori = $totals['total_kalori'] ?? 0;
$total_protein = $totals['total_protein'] ?? 0;
$frekuensi = $totals['frekuensi'] ?? 0;

// Target harian (bisa disesuaikan berdasarkan usia)
$target_kalori = 1200;
$target_protein = 35;

// Hitung persentase
$persen_kalori = $target_kalori > 0 ? ($total_kalori / $target_kalori) * 100 : 0;
$persen_protein = $target_protein > 0 ? ($total_protein / $target_protein) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Asupan Harian - NutriGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Ensure main content doesn't overlap the fixed sidebar */
        .main-content { margin-left: 240px; }
        @media (max-width: 768px) { .main-content { margin-left: 70px; } }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }
        
        .filter-select, .filter-input {
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        
        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #06b6d4;
        }
        
        .info-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #0891b2;
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #0891b2;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .readonly-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
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
                <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Cari data balita atau informasi...">
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($_SESSION['nama']); ?></h4>
                        <p>Tenaga Kesehatan</p>
                    </div>
                    <div class="user-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
            </header>

            <!-- Page Header -->
            <div class="page-header">
                <h1>Monitor Asupan Harian</h1>
                <p>Pantau asupan makanan balita yang tercatat oleh orang tua</p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">Pilih Balita</label>
                            <select name="balita" class="filter-select" onchange="this.form.submit()">
                                <option value="">-- Pilih Balita --</option>
                                <?php 
                                $result_all_balita->data_seek(0);
                                while($bal = $result_all_balita->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $bal['id_balita']; ?>" 
                                        <?php echo $selected_balita == $bal['id_balita'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bal['nama_balita']); ?> 
                                    (Ortu: <?php echo htmlspecialchars($bal['nama_ortu']); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Tanggal</label>
                            <input type="date" name="tanggal" class="filter-input" 
                                   value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
                        </div>
                        
                        <div class="filter-group">
                            <span class="readonly-badge">
                                <i class="fas fa-eye"></i> View Only
                            </span>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($balita_info): ?>
            <!-- Info Balita -->
            <div class="info-box">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-baby"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Nama Balita</div>
                            <div class="info-value"><?php echo htmlspecialchars($balita_info['nama_balita']); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Orang Tua</div>
                            <div class="info-value"><?php echo htmlspecialchars($balita_info['nama_ortu']); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Tanggal Lahir</div>
                            <div class="info-value"><?php echo formatTanggalIndo($balita_info['tanggal_lahir']); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-venus-mars"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Jenis Kelamin</div>
                            <div class="info-value"><?php echo $balita_info['jenis_kelamin']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top:10px; margin-bottom:20px;">
                <?php if (!empty($balita_info['id_akun'])): ?>
                <a class="btn btn-secondary" href="akun_detail.php?id=<?php echo $balita_info['id_akun']; ?>">
                    <i class="fas fa-user"></i> Lihat Akun Orang Tua
                </a>
                <?php endif; ?>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">🍽️</div>
                    <div class="stat-label">Total Kalori</div>
                    <div class="stat-value"><?php echo number_format($total_kalori, 0); ?> <span class="unit">kcal</span></div>
                    <div class="stat-target">Target: <?php echo $target_kalori; ?> kcal</div>
                    <div class="progress-bar">
                        <div class="progress-fill blue" style="width: <?php echo min($persen_kalori, 100); ?>%"></div>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-icon">🥩</div>
                    <div class="stat-label">Total Protein</div>
                    <div class="stat-value"><?php echo number_format($total_protein, 0); ?><span class="unit">g</span></div>
                    <div class="stat-target">Target: <?php echo $target_protein; ?>g</div>
                    <div class="progress-bar">
                        <div class="progress-fill green" style="width: <?php echo min($persen_protein, 100); ?>%"></div>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Frekuensi Makan</div>
                    <div class="stat-value"><?php echo $frekuensi; ?><span class="unit">x</span></div>
                    <div class="stat-target"><?php echo formatTanggalIndo($selected_date); ?></div>
                </div>
            </div>

            <!-- Data Asupan -->
            <div class="content-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Data Asupan Harian</h2>
                        <p class="card-subtitle"><?php echo formatHariIndo($selected_date) . ', ' . formatTanggalIndo($selected_date); ?></p>
                    </div>
                </div>

                <?php if ($result_asupan && $result_asupan->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis Makanan</th>
                            <th>Porsi</th>
                            <th>Kalori</th>
                            <th>Protein</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $result_asupan->data_seek(0);
                        while ($row = $result_asupan->fetch_assoc()): 
                        ?>
                        <tr>
                            <td>
                                <span class="time-badge">
                                    <?php echo date('H:i', strtotime($row['waktu_makan'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['jenis_makanan']); ?></td>
                            <td><?php echo $row['porsi']; ?> <?php echo strpos($row['jenis_makanan'], 'Susu') !== false ? 'ml' : 'porsi'; ?></td>
                            <td><strong><?php echo number_format($row['kalori_total'], 0); ?> kcal</strong></td>
                            <td><strong><?php echo number_format($row['protein'], 0); ?>g</strong></td>
                            <td>
                                <span class="badge badge-nutrisi">
                                    <i class="fas fa-check-circle"></i> Tercatat
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; text-align: center;">
                    <p style="color: #64748b; margin-bottom: 10px;">
                        <i class="fas fa-info-circle"></i> 
                        Data asupan ini dicatat oleh orang tua. Anda dapat memberikan rekomendasi gizi berdasarkan data ini.
                    </p>
                    <button class="btn btn-primary" onclick="location.href='nakes_rekomendasi_gizi.php?balita=<?php echo $selected_balita; ?>'">
                        <i class="fas fa-plus-circle"></i> Buat Rekomendasi untuk Balita Ini
                    </button>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 60px; color: #64748b;">
                    <i class="fas fa-utensils" style="font-size: 64px; margin-bottom: 20px; opacity: 0.2;"></i>
                    <h3 style="margin-bottom: 10px;">Belum Ada Data Asupan</h3>
                    <p>Belum ada catatan asupan untuk tanggal yang dipilih. Orang tua dapat menambahkan data melalui aplikasi mereka.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="content-card" style="text-align: center; padding: 60px;">
                <i class="fas fa-baby" style="font-size: 64px; color: #e2e8f0; margin-bottom: 20px;"></i>
                <h3 style="color: #64748b; margin-bottom: 10px;">Pilih Balita</h3>
                <p style="color: #94a3b8;">Silakan pilih balita dari dropdown di atas untuk melihat data asupan harian.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

<?php $conn->close(); ?>