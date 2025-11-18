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
      LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
// Bind filter params
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i+1, $params[$i]);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 240px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            padding: 10px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4FC3F7 0%, #66BB6A 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: 700;
        }
        
        .logo-text .nutri { color: #4FC3F7; }
        
        .menu {
            list-style: none;
        }
        
        .menu-item {
            margin-bottom: 5px;
        }
        
        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
        }
        
        .menu-link:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .menu-link.active {
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            color: white;
        }
        
        .menu-icon {
            font-size: 20px;
        }
        
        .menu-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }
        
        .logout-link {
            color: #f44336;
        }
        
        .logout-link:hover {
            background: #ffebee;
        }
        
        .main-content {
            margin-left: 240px;
            flex: 1;
            padding: 20px 40px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .search-box {
            flex: 1;
            max-width: 600px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4FC3F7 0%, #66BB6A 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            background: white;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            border-color: #4FC3F7;
            color: #4FC3F7;
        }
        
        .filter-tab.active {
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            border-color: transparent;
            color: white;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
        }
        
        .artikel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .artikel-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .artikel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .artikel-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: linear-gradient(135deg, #e3f2fd 0%, #e8f5e9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        
        .artikel-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .artikel-kategori {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .kategori-gizi { background: #e8f5e9; color: #2e7d32; }
        .kategori-kesehatan { background: #e3f2fd; color: #1565c0; }
        .kategori-tumbuh_kembang { background: #f3e5f5; color: #6a1b9a; }
        .kategori-tips { background: #fff3e0; color: #e65100; }
        .kategori-resep { background: #fce4ec; color: #c2185b; }
        
        .artikel-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .artikel-title a {
            color: inherit;
            text-decoration: none;
        }
        
        .artikel-title a:hover {
            color: #4FC3F7;
        }
        
        .artikel-ringkasan {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }
        
        .artikel-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .artikel-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #999;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .bookmark-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .bookmark-btn:hover {
            transform: scale(1.2);
        }
        
        .sidebar-widget {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .widget-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4FC3F7;
        }
        
        .populer-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .populer-item:last-child {
            border-bottom: none;
        }
        
        .populer-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: linear-gradient(135deg, #e3f2fd 0%, #e8f5e9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .populer-content {
            flex: 1;
        }
        
        .populer-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .populer-title a {
            color: inherit;
            text-decoration: none;
        }
        
        .populer-title a:hover {
            color: #4FC3F7;
        }
        
        .populer-views {
            font-size: 12px;
            color: #999;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .page-link {
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            border-color: #4FC3F7;
            color: #4FC3F7;
        }
        
        .page-link.active {
            background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%);
            border-color: transparent;
            color: white;
        }
        
        .no-artikel {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-artikel-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px;
            }
            
            .artikel-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="header">
            <form class="search-box" method="GET" action="">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" class="search-input" 
                       placeholder="Cari artikel..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <?php if ($kategori): ?>
                    <input type="hidden" name="kategori" value="<?php echo htmlspecialchars($kategori); ?>">
                <?php endif; ?>
            </form>
            
            <div class="user-info">
                <div class="avatar">👤</div>
            </div>
        </header>
        
        <div class="page-header">
            <h1>Artikel & Tips Kesehatan</h1>
            <p class="page-subtitle">Informasi terkini seputar nutrisi dan tumbuh kembang bayi</p>
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
                                        
                                        <button class="bookmark-btn" 
                                                onclick="toggleBookmark(<?php echo $artikel['id']; ?>, this)"
                                                data-bookmarked="<?php echo isset($artikel['is_bookmarked']) ? (int)$artikel['is_bookmarked'] : 0; ?>">
                                            <?php echo (isset($artikel['is_bookmarked']) && $artikel['is_bookmarked']) ? '🔖' : '📑'; ?>
                                        </button>
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
                    <div class="no-artikel">
                        <div class="no-artikel-icon">📭</div>
                        <h3>Tidak ada artikel ditemukan</h3>
                        <p>Coba ubah filter atau kata kunci pencarian Anda</p>
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
</html