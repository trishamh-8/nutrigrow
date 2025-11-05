<header class="header">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" class="search-input" placeholder="Cari artikel, data balita, atau informasi...">
    </div>
    
    <div class="user-info">
        <div class="lang-selector">
            <span>🌐</span>
            <span>ID</span>
        </div>
        
        <div class="user-avatar">
            <div>
                <div style="font-weight: 600; font-size: 14px; text-align: right;">
                    <?php echo htmlspecialchars($user['nama']); ?>
                </div>
                <div style="font-size: 12px; color: #999; text-align: right;">
                    <?php 
                    if ($user['role'] == 'tenaga_kesehatan') {
                        echo 'Tenaga Kesehatan';
                    } elseif ($user['role'] == 'admin') {
                        echo 'Administrator';
                    } else {
                        echo 'Orang Tua';
                    }
                    ?>
                </div>
            </div>
            <div class="avatar">👤</div>
        </div>
    </div>
</header>