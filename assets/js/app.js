/* ============================================================
   My_School - JavaScript principal
   ============================================================ */

(function() {
    'use strict';

    // ---------- Toggle sidebar (mobile) ----------
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        const topbarToggle = document.getElementById('topbarToggle');
        const overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('open');
            if (overlay) overlay.classList.add('open');
        }
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        }

        if (toggle) toggle.addEventListener('click', openSidebar);
        if (topbarToggle) topbarToggle.addEventListener('click', openSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
    });

    // ---------- Confirmation de suppression ----------
    window.confirmDelete = function(message) {
        if (!message) message = 'Êtes-vous sûr de vouloir supprimer cet élément ?';
        return confirm(message);
    };

    // ---------- Fetch helper ----------
    window.apiFetch = function(url, options) {
        options = options || {};
        options.headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest'
        }, options.headers || {});
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.body = JSON.stringify(options.body);
            options.headers['Content-Type'] = 'application/json';
        }
        return fetch(url, options).then(function(r) {
            return r.json().catch(function() { return { error: 'Réponse invalide' }; });
        });
    };

    // ---------- Autofocus premier champ ----------
    document.addEventListener('DOMContentLoaded', function() {
        const first = document.querySelector('input[autofocus], .auth-card input:first-of-type');
        // Ne pas autofocus sur les écrans tactiles problématiques
    });

    // ---------- Délai de disparition des alertes ----------
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                if (alert && alert.parentNode) {
                    alert.style.transition = 'opacity .5s';
                    alert.style.opacity = '0';
                    setTimeout(function() { if (alert.parentNode) alert.remove(); }, 600);
                }
            }, 5000);
        });
    });

    // ---------- Prévisualisation avatar ----------
    window.previewImage = function(input, imgId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById(imgId);
                if (img) img.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    };
})();
