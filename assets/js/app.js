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

    // ---------- Filtre de liste déroulante (recherche instantanée) ----------
    // Utilisé pour sélectionner un élève sans afficher toute la liste :
    //   <input ... oninput="filterSelect(this)">
    //   <select name="eleve_id" ...> ... </select>
    // Le champ de recherche et le <select> sont associés soit par
    // data-select-filter="#id", soit simplement parce qu'ils se suivent.
    window.filterSelect = function(input) {
        if (!input) return;

        let sel = null;
        const ref = input.getAttribute('data-select-filter');
        if (ref) sel = document.querySelector(ref);
        if (!sel) {
            const next = input.nextElementSibling;
            if (next && next.tagName === 'SELECT') sel = next;
        }
        if (!sel) return;

        // On mémorise la liste complète la première fois : la liste est ensuite
        // reconstruite (et non simplement masquée), car certains navigateurs
        // affichent quand même les <option hidden> — d'où l'impression que le
        // champ de recherche « ne fait rien ».
        if (!sel._allOptions) {
            sel._allOptions = Array.prototype.map.call(sel.options, function(o) {
                return { value: o.value, text: (o.textContent || '').trim() };
            });
        }

        const current = sel.value;
        const q = (input.value || '').toLowerCase().trim();

        const keep = sel._allOptions.filter(function(o) {
            // Le placeholder (« — Sélectionner — ») reste toujours présent.
            if (!o.value || q === '') return true;
            return o.text.toLowerCase().indexOf(q) !== -1;
        });

        while (sel.firstChild) sel.removeChild(sel.firstChild);
        keep.forEach(function(o) {
            const opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.text;
            sel.appendChild(opt);
        });

        // Si l'option sélectionnée n'est plus dans les résultats, on repart du placeholder.
        let found = false;
        Array.prototype.forEach.call(sel.options, function(o) {
            if (o.value === current) found = true;
        });
        sel.value = found ? current : '';
    };

    // ---------- Filtre de tableau (recherche instantanée) ----------
    // Utilisé pour filtrer les lignes d'un tableau à la frappe :
    //   <form method="get"> ... <input name="search" oninput="filterTable(this)"> ... </form>
    //   <div class="table-responsive"><table> ... </table></div>
    // Le champ se situe dans le <form> juste avant le tableau (même conteneur).
    window.filterTable = function(input) {
        if (!input) return;
        const q = (input.value || '').toLowerCase().trim();

        let root = null;
        const ref = input.getAttribute('data-table-filter');
        if (ref) root = document.querySelector(ref);
        if (!root && input.form && input.form.parentElement) root = input.form.parentElement;
        if (!root || !root.querySelector) return;

        const table = root.querySelector('table');
        if (!table || !table.tBodies || !table.tBodies.length) return;

        const rows = table.tBodies[0].rows;
        for (let i = 0; i < rows.length; i++) {
            const tr = rows[i];
            // Ligne « aucun résultat » (cellule unique avec colspan) : intouchée.
            if (tr.cells.length === 1 && tr.cells[0].hasAttribute('colspan')) continue;
            const match = q === '' || (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
            tr.style.display = match ? '' : 'none';
        }
    };
})();
