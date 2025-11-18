<?php
session_start();
require_once 'config.php';
$conn = getConnection();

// Pastikan user login (gunakan id_akun konsisten dengan sistem)
if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

// Ambil id artikel dari query string
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: artikel.php');
    exit;
}

// Ambil detail artikel (alias kolom agar sesuai template)
$stmt = $conn->prepare("SELECT
    id_artikel AS id,
    judul_artikel AS judul,
    isi_artikel AS konten,
    tgl_terbit AS created_at,
    penulis,
    gambar_cover AS gambar,
    kategori,
    views
    FROM artikel
    WHERE id_artikel = ? AND status = 'published'");
$stmt->execute([$id]);
$artikel = $stmt->fetch();

if (!$artikel) {
    header('Location: artikel.php');
    exit;
}

// Update views
$stmtUpdate = $conn->prepare("UPDATE artikel SET views = views + 1 WHERE id_artikel = ?");
$stmtUpdate->execute([$artikel['id']]);

// Get related articles
// Related articles (alias kolom)
$stmtRelated = $conn->prepare("SELECT
    id_artikel AS id,
    judul_artikel AS judul,
    isi_artikel AS ringkasan,
    gambar_cover AS gambar,
    tgl_terbit AS created_at,
    views,
    kategori
    FROM artikel
    WHERE kategori = ? AND id_artikel != ? AND status = 'published'
    ORDER BY RAND()
    LIMIT 3");
$stmtRelated->execute([$artikel['kategori'], $artikel['id']]);
$artikelRelated = $stmtRelated->fetchAll();

// Ambil data user (akun) jika diperlukan
$stmtUser = $conn->prepare("SELECT * FROM akun WHERE id_akun = ?");
$stmtUser->execute([$_SESSION['id_akun']]);
$user = $stmtUser->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($artikel['judul']); ?> - NutriGrow</title>
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
        
        .menu-icon { font-size: 20px; }
        .menu-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }
        
        .logout-link { color: #f44336; }
        .logout-link:hover { background: #ffebee; }
        
        .main-content {
            margin-left: 240px;
            flex: 1;
            padding: 20px 40px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            color: #666;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            border-color: #4FC3F7;
            color: #4FC3F7;
        }
        
        .article-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .article-header {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .article-kategori {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        
        .kategori-gizi { background: #e8f5e9; color: #2e7d32; }
        .kategori-kesehatan { background: #e3f2fd; color: #1565c0; }
        .kategori-tumbuh_kembang { background: #f3e5f5; color: #6a1b9a; }
        .kategori-tips { background: #fff3e0; color: #e65100; }
        .kategori-resep { background: #fce4ec; color: #c2185b; }
        
        .article-title {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            line-height: 1.3;
            margin-bottom: 20px;
        }
        
        .article-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 30px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .article-actions {
            display: flex;
            gap: 15px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .action-btn:hover {
            border-color: #4FC3F7;
            color: #4FC3F7;
        }
        
        .action-btn.bookmarked {
            border-color: #4FC3F7;
            background: #e3f2fd;
            color: #4FC3F7;
        }
        
        .article-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .article-body {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .article-content {
            font-size: 16px;
            line-height: 1.8;
            color: #333;
        }
        
        .article-content p {
            margin-bottom: 20px;
        }
        
        .article-content h2 {
            font-size: 24px;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .article-content h3 {
            font-size: 20px;
            margin-top: 25px;
            margin-bottom: 12px;
            color: #333;
        }
        
        .article-content ul, .article-content ol {
            margin-left: 30px;
            margin-bottom: 20px;
        }
        
        .article-content li {
            margin-bottom: 10px;
        }
        
        .related-section {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .related-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .related-card {
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .related-card:hover {
            border-color: #4FC3F7;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .related-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: linear-gradient(135deg, #e3f2fd 0%, #e8f5e9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        
        .related-content {
            padding: 20px;
        }
        
        .related-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .related-card-title a {
            color: inherit;
            text-decoration: none;
        }
        
        .related-card-title a:hover {
            color: #4FC3F7;
        }
        
        .related-excerpt {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content {
                margin-left: 200px;
                padding: 20px;
            }
            .article-header, .article-body, .related-section {
                padding: 25px;
            }
            .article-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <main class="main-content">
        <a href="artikel.php" class="back-button">
            <span>←</span>
            <span>Kembali ke Artikel</span>
        </a>
        
        <div class="article-container">
            <article class="article-header">
                <span class="article-kategori kategori-<?php echo $artikel['kategori']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $artikel['kategori'])); ?>
                </span>
                
                <h1 class="article-title"><?php echo htmlspecialchars($artikel['judul']); ?></h1>
                
                <div class="article-meta">
                    <div class="meta-item">
                        <span>✍️</span>
                        <span><?php echo $artikel['penulis'] ?: $artikel['penulis_nama'] ?: 'Admin'; ?></span>
                    </div>
                    <div class="meta-item">
                        <span>📅</span>
                        <span><?php echo date('d F Y', strtotime($artikel['created_at'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <span>👁</span>
                        <span><?php echo $artikel['views']; ?> views</span>
                    </div>
                </div>
                
                <div class="article-actions">
                    <button class="action-btn <?php echo (isset($artikel['is_bookmarked']) && $artikel['is_bookmarked']) ? 'bookmarked' : ''; ?>" 
                            id="bookmarkBtn"
                            onclick="toggleBookmark(<?php echo $artikel['id']; ?>)">
                        <span id="bookmarkIcon"><?php echo (isset($artikel['is_bookmarked']) && $artikel['is_bookmarked']) ? '🔖' : '📑'; ?></span>
                        <span id="bookmarkText"><?php echo (isset($artikel['is_bookmarked']) && $artikel['is_bookmarked']) ? 'Tersimpan' : 'Simpan'; ?></span>
                    </button>
                    
                    <button class="action-btn" onclick="shareArticle()">
                        <span>🔗</span>
                        <span>Bagikan</span>
                    </button>
                </div>
            </article>
            
            <?php if ($artikel['gambar']): ?>
                <img src="<?php echo htmlspecialchars($artikel['gambar']); ?>" 
                     alt="<?php echo htmlspecialchars($artikel['judul']); ?>"
                     class="article-image">
            <?php endif; ?>
            
            <div class="article-body">
                <div class="article-content">
                    <?php echo $artikel['konten']; ?>
                </div>
            </div>
            
            <?php if (count($artikelRelated) > 0): ?>
                <div class="related-section">
                    <h2 class="related-title">Artikel Terkait</h2>
                    <div class="related-grid">
                        <?php foreach ($artikelRelated as $related): ?>
                            <div class="related-card">
                                <div class="related-image">
                                    <?php if ($related['gambar']): ?>
                                        <img src="<?php echo htmlspecialchars($related['gambar']); ?>" 
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        📄
                                    <?php endif; ?>
                                </div>
                                <div class="related-content">
                                    <h3 class="related-card-title">
                                        <a href="artikel_detail.php?id=<?php echo $related['id']; ?>">
                                            <?php echo htmlspecialchars($related['judul']); ?>
                                        </a>
                                    </h3>
                                    <p class="related-excerpt">
                                        <?php echo htmlspecialchars($related['ringkasan']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        function toggleBookmark(artikelId) {
            const btn = document.getElementById('bookmarkBtn');
            const icon = document.getElementById('bookmarkIcon');
            const text = document.getElementById('bookmarkText');
            const isBookmarked = btn.classList.contains('bookmarked');
            
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
                    if (data.bookmarked) {
                        btn.classList.add('bookmarked');
                        icon.textContent = '🔖';
                        text.textContent = 'Tersimpan';
                    } else {
                        btn.classList.remove('bookmarked');
                        icon.textContent = '📑';
                        text.textContent = 'Simpan';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function shareArticle() {
            const url = window.location.href;
            const title = document.querySelector('.article-title').textContent;
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                }).catch(err => console.log('Error sharing:', err));
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link artikel telah disalin ke clipboard!');
                });
            }
        }
    </script>
</body>
</html>