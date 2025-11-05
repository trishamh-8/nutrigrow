<?php
// index.php - Landing page dengan pilihan role sebelum diarahkan ke login
// Pilih role: admin, tenaga_kesehatan, pengguna
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>NutriGrow - Pilih Peran</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial; background: #f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        .card { background: white; border-radius:12px; padding:28px; box-shadow:0 10px 30px rgba(2,6,23,0.08); width:100%; max-width:520px; }
        h1 { margin:0 0 6px; font-size:22px; color:#0f172a; }
        p { margin:0 0 20px; color:#64748b; }
        .roles { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px; }
        .role { padding:14px; border-radius:10px; text-align:center; cursor:pointer; border:1px solid #e6eef6; }
        .role input { display:none; }
        .role.selected { background: linear-gradient(90deg,#e6f6ff, #e6fff1); border-color:#bfe9ff; }
        .role h3 { margin:0 0 6px; font-size:15px; color:#0f172a; }
        .role p { margin:0; font-size:13px; color:#475569; }
        .btn { display:inline-block; padding:12px 18px; border-radius:8px; background: linear-gradient(90deg,#06b6d4,#0ea5a4); color:white; text-decoration:none; font-weight:600; }
        .note { margin-top:12px; font-size:13px; color:#475569; }
        @media (max-width:600px){ .roles{grid-template-columns:repeat(1,1fr);} }
    </style>
    <script>
        function selectRole(role) {
            document.querySelectorAll('.role').forEach(function(el){ el.classList.remove('selected'); });
            var sel = document.querySelector('[data-role="'+role+'"]');
            if (sel) sel.classList.add('selected');
            document.getElementById('selected_role').value = role;
        }
        function goToLogin(e){
            e.preventDefault();
            var role = document.getElementById('selected_role').value || 'pengguna';
            // Redirect ke login dengan param role
            window.location.href = 'login.php?role=' + encodeURIComponent(role);
        }
        window.addEventListener('DOMContentLoaded', function(){
            // default pilih pengguna
            selectRole('pengguna');
        });
    </script>
</head>
<body>
    <div class="card">
        <h1>Pilih Peran Anda</h1>
        <p>Pilih peran yang paling sesuai sebelum masuk atau mendaftar.</p>

        <div class="roles">
            <div class="role" data-role="admin" onclick="selectRole('admin')">
                <h3>Admin</h3>
                <p>Manajemen sistem dan konten</p>
            </div>
            <div class="role" data-role="tenaga_kesehatan" onclick="selectRole('tenaga_kesehatan')">
                <h3>Tenaga Kesehatan</h3>
                <p>Akses monitor pasien dan rekomendasi gizi</p>
            </div>
            <div class="role" data-role="pengguna" onclick="selectRole('pengguna')">
                <h3>Pengguna (Orang Tua)</h3>
                <p>Catat asupan dan pantau perkembangan balita</p>
            </div>
        </div>

        <input type="hidden" id="selected_role" value="pengguna">

        <div style="display:flex; gap:12px; align-items:center;">
            <a href="#" class="btn" onclick="goToLogin(event)">Lanjut ke Login</a>
            <a href="#" style="color:#0f172a; text-decoration:none; font-weight:600;" onclick="var role=document.getElementById('selected_role').value; window.location.href='register.php?role='+encodeURIComponent(role);">Daftar</a>
        </div>

        <div class="note">Catatan: Jika memilih <strong>Tenaga Kesehatan</strong>, pendaftaran akan mewajibkan mengisi nomor sertifikasi.</div>
    </div>
</body>
</html>
