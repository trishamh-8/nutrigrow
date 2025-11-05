<?php
// partials/sidebar.php - shared sidebar partial
// Usage: include __DIR__ . '/partials/sidebar.php';
// Current script basename (e.g., 'asupan_harian.php')
$current = basename($_SERVER['SCRIPT_NAME']);

// Mapping of menu items to possible script names that should mark them as active.
$menuMap = [
    'dashboard' => ['dashboard.php'],
    'artikel' => ['artikel.php'],
    'pertumbuhan' => ['pertumbuhan.php'],
    'asupan' => ['asupan_harian.php', 'asupan.php', 'nakes_asupan_harian.php'],
    'rekomendasi' => ['rekomendasi_gizi.php', 'nakes_rekomendasi_gizi.php'],
    'laporan' => ['laporan_kesehatan.php', 'laporan.php'],
    'jadwal' => ['jadwal.php'],
    'profil' => ['profil.php'],
    'pengaturan' => ['pengaturan.php'],
];

function isActiveFor(array $names) {
    global $current;
    foreach ($names as $n) {
        if ($current === $n) return 'menu-link active nav-link active';
    }
    return 'menu-link nav-link';
}

// Check if user is a healthcare worker
$isNakes = isset($_SESSION['role']) && $_SESSION['role'] === 'tenaga_kesehatan';
?>
<aside class="sidebar">
    <div class="logo">
        <div class="logo-icon">📈</div>
        <div class="logo-text" style="color: #0D9488;"><span class="nutri">Nutri</span>Grow</div>
    </div>

    <ul class="menu nav-menu">
        <li class="menu-item nav-item">
            <a href="dashboard.php" class="<?php echo isActiveFor($menuMap['dashboard']); ?>">
                <span class="menu-icon">📊</span>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="menu-item nav-item">
            <a href="artikel.php" class="<?php echo isActiveFor($menuMap['artikel']); ?>">
                <span class="menu-icon">📄</span>
                <span>Artikel</span>
            </a>
        </li>
        <li class="menu-item nav-item">
            <a href="status_gizi.php" class="<?php echo isActiveFor($menuMap['pertumbuhan']); ?>">
                <span class="menu-icon">📈</span>
                <span>Pertumbuhan Balita</span>
            </a>
        </li>
        <?php if ($isNakes): ?>
        <!-- Menu khusus untuk tenaga kesehatan -->
        <li class="menu-item nav-item">
            <a href="nakes_asupan_harian.php" class="<?php echo isActiveFor($menuMap['asupan']); ?>">
                <span class="menu-icon">🍽️</span>
                <span>Monitor Asupan</span>
            </a>
        </li>
        <li class="menu-item nav-item">
            <a href="nakes_rekomendasi_gizi.php" class="<?php echo isActiveFor($menuMap['rekomendasi']); ?>">
                <span class="menu-icon">📝</span>
                <span>Rekomendasi Gizi</span>
            </a>
        </li>
        <?php else: ?>
        <!-- Menu untuk pengguna biasa -->
        <li class="menu-item nav-item">
            <a href="asupan_harian.php" class="<?php echo isActiveFor($menuMap['asupan']); ?>">
                <span class="menu-icon">🍽️</span>
                <span>Asupan Harian</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="menu-item nav-item">
            <a href="laporan_kesehatan.php" class="<?php echo isActiveFor($menuMap['laporan']); ?>">
                <span class="menu-icon">📋</span>
                <span>Laporan Kesehatan</span>
            </a>
        </li>
        <li class="menu-item nav-item">
            <a href="jadwal.php" class="<?php echo isActiveFor($menuMap['jadwal']); ?>">
                <span class="menu-icon">📅</span>
                <span>Jadwal Program</span>
            </a>
        </li>
    </ul>

    <div class="menu-divider"></div>

    <ul class="menu nav-menu">
        <li class="menu-item nav-item">
            <a href="profil.php" class="<?php echo isActiveFor($menuMap['profil']); ?>">
                <span class="menu-icon">👤</span>
                <span>Profil</span>
            </a>
        </li>
        <li class="menu-item nav-item">
            <a href="pengaturan.php" class="<?php echo isActiveFor($menuMap['pengaturan']); ?>">
                <span class="menu-icon">⚙️</span>
                <span>Pengaturan</span>
            </a>
        </li>
        <li class="menu-item nav-item">
            <a href="logout.php" class="menu-link nav-link logout-link">
                <span class="menu-icon">🚪</span>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>
