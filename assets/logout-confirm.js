(function(){
    if (window.__logoutConfirmInstalled) return;
    window.__logoutConfirmInstalled = true;

    function createModal(href) {
        var existing = document.getElementById('logout-confirm-modal');
        if (existing) return existing;

        var overlay = document.createElement('div');
        overlay.id = 'logout-confirm-modal';
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.background = 'rgba(0,0,0,0.5)';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '9999';

        var box = document.createElement('div');
        box.style.width = '420px';
        box.style.maxWidth = '90%';
        box.style.background = '#fff';
        box.style.borderRadius = '10px';
        box.style.boxShadow = '0 6px 24px rgba(0,0,0,0.2)';
        box.style.padding = '20px';
        box.style.fontFamily = 'Arial, sans-serif';

        box.innerHTML = '<h3 style="margin:0 0 10px 0;">Yakin ingin keluar?</h3>' +
                        '<p style="color:#444; margin:0 0 20px 0;">Anda akan keluar dari akun Anda dan kembali ke halaman login.</p>' +
                        '<div style="display:flex; justify-content:flex-end; gap:10px;">' +
                        '<button id="logout-cancel" style="padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; cursor:pointer;">Batal</button>' +
                        '<button id="logout-confirm" style="padding:8px 14px; border-radius:8px; border:none; background:#ef4444; color:#fff; cursor:pointer;">Logout</button>' +
                        '</div>';

        overlay.appendChild(box);
        document.body.appendChild(overlay);
        return overlay;
    }

    function showModal(href) {
        var modal = createModal(href);
        var cancel = modal.querySelector('#logout-cancel');
        var confirm = modal.querySelector('#logout-confirm');

        confirm.onclick = function() {
            window.location.href = href;
        };

        cancel.onclick = function() {
            modal.remove();
        };
    }

    document.addEventListener('click', function(e){
        var el = e.target;
        if (!el) return;
        var anchor = el.closest ? el.closest('a') : null;
        if (!anchor) return;
        var href = anchor.getAttribute('href') || '';
        var isLogout = anchor.classList.contains('logout-link') || href.indexOf('logout.php') !== -1;
        if (!isLogout) return;
        e.preventDefault();
        showModal(href);
    }, false);
})();
