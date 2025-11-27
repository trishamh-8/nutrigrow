<?php
session_start();

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
$conn = getConnection();

$stmt = $conn->prepare("SELECT * FROM akun WHERE id_akun = ?");
$stmt->execute([$_SESSION['id_akun']]);
$user = $stmt->fetch();

// Determine role (uses config helper)
$user['role'] = getUserRole($conn, $_SESSION['id_akun']);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Pagination
$limit = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = ["status = 'published'"];
$params = [];

if ($kategori) {
    $where[] = "kategori = ?";
    $params[] = $kategori;
}

if ($search) {
    $where[] = "(judul_artikel LIKE ? OR isi_artikel LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Get total articles
$stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM artikel WHERE $whereClause");
$stmtCount->execute($params);
$totalArtikel = $stmtCount->fetch()['total'];
$totalPages = ceil($totalArtikel / $limit);

// Get articles
$params2 = $params;
$params2[] = $limit;
$params2[] = $offset;

// Select columns and alias to match existing template keys (judul, ringkasan, created_at, id)
$sql = "SELECT id_artikel AS id,
                judul_artikel AS judul,
                isi_artikel AS ringkasan,
                tgl_terbit AS created_at,
                penulis,
                gambar_cover AS gambar,
                views,
                    kategori
            FROM artikel
            WHERE $whereClause
            ORDER BY tgl_terbit DESC
            LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
// Bind filter params (positional) then bind limit/offset as integers to avoid them being treated as strings
$paramIndex = 1;
foreach ($params as $p) {
    $stmt->bindValue($paramIndex, $p);
    $paramIndex++;
}
$stmt->bindValue($paramIndex, (int)$limit, PDO::PARAM_INT);
$paramIndex++;
$stmt->bindValue($paramIndex, (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$artikelList = $stmt->fetchAll();

// Get artikel populer (sidebar) - use actual column names and alias to template keys
$stmtPopuler = $conn->prepare("SELECT id_artikel AS id, judul_artikel AS judul, gambar_cover AS gambar, views FROM artikel WHERE status = 'published' ORDER BY views DESC LIMIT 5");
$stmtPopuler->execute();
$artikelPopuler = $stmtPopuler->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel - NutriGrow</title>
    <?php include __DIR__ . '/partials/common_head.php'; ?>
    <style>
        /* Polished sidebar 'Artikel Populer' visuals */
        .sidebar-widget .widget-title { display:flex; align-items:center; gap:8px; font-size:16px; margin-bottom:12px; color:#0f172a; }
        .sidebar-widget .populer-item {
            display:flex;
            gap:12px;
            align-items:center;
            background:#ffffff;
            padding:10px;
            border-radius:10px;
            box-shadow: 0 2px 8px rgba(2,6,23,0.04);
            margin-bottom:10px;
            transition: transform .12s ease, box-shadow .12s ease;
        }
        .sidebar-widget .populer-item:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(2,6,23,0.06); }
        .populer-image { width:64px; height:64px; flex:0 0 64px; border-radius:8px; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#f8fafc; }
        .populer-image img { width:100%; height:100%; object-fit:cover; display:block; }
        .populer-content { flex:1; min-width:0; }
        .populer-title { font-size:14px; font-weight:600; color:#0f172a; margin:0 0 6px; line-height:1.25; }
        .populer-title a { color:inherit; text-decoration:none; }
        .populer-views { font-size:13px; color:#64748b; display:flex; align-items:center; gap:8px; }
        /* Empty state card for no results */
        .no-article-card.card { background: #ffffff; border-radius: 12px; box-shadow: 0 2px 10px rgba(2,6,23,0.04); padding: 32px; text-align:center; max-width:560px; margin: 24px auto; }
        .no-article-icon { font-size:48px; margin-bottom:12px; }
        .no-article-card h3 { color:#0f172a; margin-bottom:8px; font-size:20px; }
        .no-article-card p { color:#64748b; margin-bottom:16px; }
        .no-article-card .btn { padding:8px 18px; border-radius:20px; background:linear-gradient(90deg,#4FC3F7 0%,#66BB6A 100%); color:white; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="header">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" name="search" placeholder="Cari artikel, data balita, atau informasi..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="user-info">
                <div class="lang-selector">
                    <span>🌐</span>
                    <span>ID</span>
                </div>

                <div class="user-avatar">
                    <div>
                        <div style="font-weight: 600; font-size: 14px; text-align: right;">
                            <?php echo htmlspecialchars($user['nama'] ?? ($_SESSION['nama'] ?? '')); ?>
                        </div>
                        <div style="font-size: 12px; color: #999; text-align: right;">
                            <?php
                                if (($user['role'] ?? '') === 'tenaga_kesehatan') echo 'Tenaga Kesehatan';
                                elseif (($user['role'] ?? '') === 'admin') echo 'Administrator';
                                else echo 'Orang Tua';
                            ?>
                        </div>
                    </div>
                    <div class="avatar">👤</div>
                </div>
            </div>
        </header>
        
        <div class="page-title">
            <h1>Artikel & Tips Kesehatan</h1>
            <p class="page-subtitle">Informasi terkini seputar nutrisi dan tumbuh kembang bayi</p>
        </div>
            <?php if (in_array($user['role'], ['tenaga_kesehatan','admin'])): ?>
            <div style="margin-top:8px;">
                <a href="artikel_create.php" class="btn" style="padding:8px 12px;border-radius:8px;background:linear-gradient(90deg,#4FC3F7,#66BB6A);color:white;text-decoration:none;">+ Buat Artikel</a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="filter-section">
            <div class="filter-tabs">
                <a href="artikel.php<?php echo $search ? '?search='.urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo !$kategori ? 'active' : ''; ?>">
                    Semua Artikel
                </a>
                <a href="?kategori=gizi<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $kategori == 'gizi' ? 'active' : ''; ?>">
                    Gizi
                </a>
                <a href="?kategori=kesehatan<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $kategori == 'kesehatan' ? 'active' : ''; ?>">
                    Kesehatan
                </a>
                <a href="?kategori=tumbuh_kembang<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $kategori == 'tumbuh_kembang' ? 'active' : ''; ?>">
                    Tumbuh Kembang
                </a>
                <a href="?kategori=tips<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $kategori == 'tips' ? 'active' : ''; ?>">
                    Tips
                </a>
                <a href="?kategori=resep<?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $kategori == 'resep' ? 'active' : ''; ?>">
                    Resep
                </a>
            </div>
        </div>
        
        <div class="content-grid">
            <div>
                <?php if (count($artikelList) > 0): ?>
                    <div class="artikel-grid">
                        <?php foreach ($artikelList as $artikel): ?>
                            <div class="artikel-card">
                                <div class="artikel-image">
                                    <?php if ($artikel['gambar']): ?>
                                        <img src="<?php echo htmlspecialchars($artikel['gambar']); ?>" 
                                             alt="<?php echo htmlspecialchars($artikel['judul']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        📄
                                    <?php endif; ?>
                                </div>
                                
                                <div class="artikel-content">
                                    <span class="artikel-kategori kategori-<?php echo $artikel['kategori']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $artikel['kategori'])); ?>
                                    </span>
                                    
                                    <h3 class="artikel-title">
                                                                <a href="artikel_detail.php?id=<?php echo $artikel['id']; ?>">
                                                                    <?php echo htmlspecialchars($artikel['judul']); ?>
                                                                </a>
                                    </h3>
                                    
                                    <p class="artikel-ringkasan">
                                        <?php echo htmlspecialchars($artikel['ringkasan']); ?>
                                    </p>
                                    
                                    <div class="artikel-footer">
                                        <div class="artikel-meta">
                                            <span class="meta-item">
                                                <span>👁</span>
                                                <span><?php echo $artikel['views']; ?></span>
                                            </span>
                                            <span class="meta-item">
                                                <span>📅</span>
                                                <span><?php echo date('d M Y', strtotime($artikel['created_at'])); ?></span>
                                            </span>
                                        </div>
                                        
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <?php
                                            // Determine if current user may edit/delete this article
                                            $canManage = false;
                                            if ($user['role'] === 'admin') {
                                                $canManage = true;
                                            } elseif ($user['role'] === 'tenaga_kesehatan') {
                                                // Tenaga may manage articles they authored (penulis = nama akun)
                                                $canManage = (isset($artikel['penulis']) && $artikel['penulis'] === $user['nama']);
                                            }
                                            ?>
                                            <?php if ($canManage): ?>
                                                <a href="artikel_edit.php?id=<?php echo $artikel['id']; ?>" title="Edit" style="text-decoration:none; font-size:16px;">✏️</a>
                                                <a href="artikel_delete.php?id=<?php echo $artikel['id']; ?>" title="Hapus" onclick="return confirm('Hapus artikel ini?')" style="text-decoration:none; font-size:16px;">🗑️</a>
                                            <?php else: ?>
                                                <button class="bookmark-btn" 
                                                        onclick="toggleBookmark(<?php echo $artikel['id']; ?>, this)"
                                                        data-bookmarked="<?php echo isset($artikel['is_bookmarked']) ? (int)$artikel['is_bookmarked'] : 0; ?>">
                                                    <?php echo (isset($artikel['is_bookmarked']) && $artikel['is_bookmarked']) ? '🔖' : '📑'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?><?php echo $kategori ? '&kategori='.$kategori : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                                   class="page-link">← Prev</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $kategori ? '&kategori='.$kategori : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page+1; ?><?php echo $kategori ? '&kategori='.$kategori : ''; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                                   class="page-link">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-article-card card">
                        <div class="no-article-icon">📭</div>
                        <h3>Tidak ada artikel ditemukan</h3>
                        <p>Coba ubah filter atau kata kunci pencarian Anda</p>
                        <a href="artikel.php" class="btn" style="margin-top:6px; display:inline-block;">Reset Filter</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <aside>
                <div class="sidebar-widget">
                    <h3 class="widget-title">📈 Artikel Populer</h3>
                    <?php foreach ($artikelPopuler as $populer): ?>
                        <div class="populer-item">
                                        <div class="populer-image">
                                            <?php if (!empty($populer['gambar'])): ?>
                                                <img src="<?php echo htmlspecialchars($populer['gambar']); ?>" 
                                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                            <?php else: ?>
                                                📄
                                            <?php endif; ?>
                                        </div>
                                        <div class="populer-content">
                                            <h4 class="populer-title">
                                                <a href="artikel_detail.php?id=<?php echo $populer['id']; ?>">
                                                    <?php echo htmlspecialchars($populer['judul']); ?>
                                                </a>
                                            </h4>
                                            <div class="populer-views">👁 <?php echo $populer['views'] ?? 0; ?> views</div>
                                        </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </main>
    
    <script>
        function toggleBookmark(artikelId, button) {
            const isBookmarked = button.getAttribute('data-bookmarked') === '1';
            
            fetch('bookmark-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'artikel_id=' + artikelId + '&action=' + (isBookmarked ? 'remove' : 'add')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = data.bookmarked ? '🔖' : '📑';
                    button.setAttribute('data-bookmarked', data.bookmarked ? '1' : '0');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>