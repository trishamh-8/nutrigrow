<?php
// partials/sidebar.php - shared sidebar partial
// Usage: include __DIR__ . '/partials/sidebar.php';
// Current script basename (e.g., 'asupan_harian.php')
$current = basename($_SERVER['SCRIPT_NAME']);

// Mapping of menu items to possible script names that should mark them as active.
$menuMap = [
    'dashboard' => ['dashboard.php'],
    'artikel' => ['artikel.php'],
    'pertumbuhan' => ['pertumbuhan.php', 'status_gizi.php'],
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
<style>
    /* Sidebar base styles (kept minimal so page styles can override) */
    .sidebar { width: 240px; background: white; padding: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.05); position: fixed; height: 100vh; overflow-y: auto; }
    .logo { display:flex; align-items:center; gap:10px; margin-bottom:18px; }
    .logo-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; background: linear-gradient(135deg,#4FC3F7 0%,#66BB6A 100%); color:#fff }
    /* Use consistent brand colors from common_head: primary part 'Nutri' and 'Grow' will inherit styles */
    .logo-text { font-size:18px; font-weight:700; color: #66BB6A; }
    .logo-text .nutri { color: #4FC3F7; }
    .menu { list-style:none; padding:0; margin: 10px 0; }
    .menu-item { margin-bottom:6px }
    .menu-link { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:10px; color:#475569; text-decoration:none; transition:all .18s ease }
    .menu-link:hover { background:#f1f5f9; color:#0f172a }
    .menu-link.active { background: linear-gradient(90deg,#4FC3F7 0%,#66BB6A 100%); color: #fff }
    .menu-icon { font-size:18px }
    .menu-divider { height:1px; background:#e6e6e6; margin:16px 0 }
</style>

<aside class="sidebar">
    <div class="logo">
        <div class="logo-icon">📈</div>
        <div class="logo-text"><span class="nutri">Nutri</span>Grow</div>
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
            <a href="rekomendasi.php" class="<?php echo isActiveFor($menuMap['rekomendasi']); ?>">
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
        <li class="menu-item nav-item">
            <a href="rekomendasi.php" class="<?php echo isActiveFor($menuMap['rekomendasi']); ?>">
                <span class="menu-icon">📝</span>
                <span>Rekomendasi Gizi</span>
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
