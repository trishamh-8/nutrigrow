<?php
// Common styles used across dashboard and content pages
?>
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
    
    /* Sidebar */
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
    
    .logo-text .nutri {
        color: #4FC3F7;
    }
    
    .logo-text {
        color: #66BB6A;
    }
    
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
    
    /* Main Content */
    .main-content {
        margin-left: 240px;
        flex: 1;
        padding: 20px 40px;
    }
    
    /* Header */
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
    
    .user-avatar {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
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
    
    /* Page Title */
    .page-title { margin-bottom: 30px; }
    .page-title h1 { font-size: 32px; color: #333; margin-bottom: 5px; }
    .page-subtitle { color: #666; font-size: 14px; }

    /* Page header used on content pages (header + title area spacing) */
    .page-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; }
    .page-header h1 { font-size:22px; margin:0; color:#111827; }
    .page-header .page-subtitle { margin-top:4px; color:#6b7280; }

    /* Cards, Grids, and common utilities */
    .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 26px; }
    .stat-card { background: white; padding: 18px; border-radius: 12px; box-shadow: 0 6px 18px rgba(2,6,23,0.06); display:flex; justify-content:space-between; align-items:center; }
    .stat-info { display:flex; flex-direction:column; gap:6px; }
    .stat-info .stat-label { color:#6b7280; font-size:13px; font-weight:600; }
    .stat-value { font-size:28px; font-weight:700; color:#0f172a; }
    .stat-icon { width:56px; height:56px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
    .stat-icon.blue { background:#e3f2fd; }
    .stat-icon.green { background:#e8f5e9; }
    .stat-icon.purple { background:#f3e5f5; }
    .stat-icon.teal { background:#e0f2f1; }

    .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }

    /* Activity / schedule styles kept minimal here; pages may add specifics */

    /* Artikel styles */
    .artikel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
    .artikel-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.04); display: flex; flex-direction: column; }
    .artikel-image { height: 160px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; }
    .artikel-content { padding: 14px; display:flex; flex-direction:column; gap:8px; flex:1; }
    .artikel-kategori { font-size:12px; padding:6px 8px; border-radius:8px; background:#eef2ff; color:#2563eb; display:inline-block; }
    .artikel-title { font-size:16px; font-weight:700; color:#111827; margin:0; }
    .artikel-ringkasan { color:#6b7280; font-size:14px; flex:1; }
    .artikel-footer { display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .artikel-meta { display:flex; gap:8px; color:#9ca3af; font-size:13px; }
    .artikel-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(2,6,23,0.08); transition: all 0.18s ease; }
    .artikel-image img { width:100%; height:100%; object-fit:cover; display:block; }

    /* Jadwal / card list */
    .jadwal-list { display:flex; flex-direction:column; gap:14px; }
    .jadwal-card { background:white; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); overflow:hidden; }
    .jadwal-header { display:flex; justify-content:space-between; gap:12px; padding:14px; align-items:flex-start; }
    .jadwal-info { flex:1; }
    .jadwal-type { display:inline-flex; align-items:center; gap:8px; font-weight:600; color:#0f172a; }
    .jadwal-title { margin:6px 0 8px; font-size:16px; color:#111827; }
    .jadwal-desc { color:#6b7280; font-size:14px; margin-bottom:8px; }
    .jadwal-meta { display:flex; gap:12px; color:#9ca3af; font-size:13px; }
    .status-badge { padding:6px 10px; border-radius:12px; font-size:13px; font-weight:600; }
    .badge-warning { background:#fff7ed; color:#b45309; }
    .badge-success { background:#ecfdf5; color:#065f46; }
    .badge-danger { background:#fff1f2; color:#b91c1c; }
    .badge-secondary { background:#f3f4f6; color:#374151; }

    /* Modal basics used by jadwal */
    .modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(15,23,42,0.4); z-index:50; }
    .modal.active { display:flex; }
    .modal-content { width:100%; max-width:720px; background:white; border-radius:12px; padding:18px; box-shadow:0 8px 30px rgba(2,6,23,0.2); }
    .modal-header { margin-bottom:12px; }

    /* Jadwal-specific utilities to match markup in `jadwal.php` */
    .search-bar { flex: 1; max-width: 600px; position: relative; }
    .search-bar .search-input { width: 100%; padding: 12px 20px 12px 45px; border: 1px solid #e0e0e0; border-radius: 25px; font-size: 14px; }
    .user-profile { display:flex; align-items:center; gap:12px; }
    .user-info-header { display:flex; gap:12px; align-items:center; }

    .balita-selector { display:flex; align-items:center; gap:18px; margin-bottom:18px; }
    .balita-info { display:flex; gap:12px; align-items:center; }
    .balita-avatar { width:56px; height:56px; border-radius:50%; background:#eef2ff; display:flex; align-items:center; justify-content:center; font-size:24px; }
    .balita-details h3 { margin:0; font-size:18px; color:#111827; }
    .balita-details p { margin:0; color:#6b7280; font-size:13px; }

    .filter-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
    .filter-tab { padding:8px 12px; border-radius:12px; background:#f3f4f6; color:#374151; text-decoration:none; font-weight:600; font-size:14px; }
    .filter-tab.active { background: linear-gradient(90deg, #4FC3F7 0%, #66BB6A 100%); color:white; }

    .content-layout { display:grid; grid-template-columns: 2fr 1fr; gap:20px; }
    /* add breathing room at the top of main content sections */
    .main-content > * { margin-bottom: 18px; }
    .header + .page-header { margin-top: 8px; }
    .sidebar-widget { background:white; padding:14px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:16px; }
    .upcoming-item, .rekomendasi-item, .populer-item { display:flex; gap:10px; align-items:flex-start; padding:8px 0; border-bottom:1px solid #f1f5f9; }
    .upcoming-item:last-child, .rekomendasi-item:last-child, .populer-item:last-child { border-bottom: none; }

    .form-select, .form-input, .form-textarea { width:100%; padding:10px 12px; border:1px solid #e6e9ee; border-radius:8px; font-size:14px; }
    .btn-primary { background: linear-gradient(90deg,#4FC3F7,#66BB6A); color:white; padding:8px 12px; border-radius:8px; text-decoration:none; display:inline-flex; gap:8px; align-items:center; }
    .btn-secondary { background:#f3f4f6; color:#0f172a; padding:8px 12px; border-radius:8px; display:inline-flex; gap:8px; align-items:center; }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; text-decoration:none; cursor:pointer; }
    .btn-back { display:inline-flex; align-items:center; gap:8px; color:#334155; text-decoration:none; }
    .btn-icon-white { background: #fff; border: 1px solid #e6e9ef; padding:8px; border-radius:8px; cursor:pointer; }
    .action-btn-sm { background:#f3f4f6; border:none; padding:8px; border-radius:8px; cursor:pointer; }
    .bookmark-btn { background:transparent; border:none; cursor:pointer; font-size:18px; }

    /* Jadwal status and actions */
    .jadwal-status { display:flex; flex-direction:column; align-items:flex-end; gap:10px; }
    .jadwal-actions { display:flex; gap:8px; }
    .populer-image, .rekomendasi-icon { width:56px; height:56px; border-radius:8px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; }
    .populer-content, .rekomendasi-content { flex:1; }



    @media (max-width: 768px) {
        .sidebar { width: 200px; }
        .main-content { margin-left: 200px; padding: 20px; }
        .content-grid { grid-template-columns: 1fr; }
    }
</style>
