/**
 * Recherche en temps réel (live search) sur toutes les pages
 */
document.addEventListener('DOMContentLoaded', function() {
    // Ne pas interférer avec certains formulaires
    const formsToSkip = ['#loginForm', '#installForm', '.no-live-search form'];
    
    // Chercher tous les champs de recherche
    document.querySelectorAll('input[name="search"], input.search-input, input[type="search"]').forEach(function(input) {
        // Éviter les formulaires exclus
        const form = input.closest('form');
        if (form) {
            for (let selector of formsToSkip) {
                if (form.matches(selector)) return;
            }
        }
        
        // Si le champ a déjà un oninput personnalisé pour filterTable, on garde le comportement
        if (input.hasAttribute('oninput') && input.getAttribute('oninput').includes('filterTable')) {
            return;
        }
        
        // Ajouter un indicateur visuel
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('placeholder', input.getAttribute('placeholder') || 'Rechercher...');
        
        // Créer un conteneur pour les résultats si nécessaire
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim().toLowerCase();
            
            // Si le formulaire a une action différente, utiliser AJAX
            if (form && form.getAttribute('data-live-search') === 'ajax') {
                timeout = setTimeout(function() {
                    performAjaxSearch(form, query, input);
                }, 300);
                return;
            }
            
            // Recherche côté client sur les tableaux
            timeout = setTimeout(function() {
                performClientSearch(query, input);
            }, 200);
        });
    });
    
    function performClientSearch(query, input) {
        const form = input.closest('form');
        const container = form ? form.closest('.card, .container, main') : document;
        
        // Chercher dans les tableaux
        container.querySelectorAll('table tbody tr').forEach(function(row) {
            if (row.querySelector('th')) return; // En-têtes
            if (row.classList.contains('no-search')) return;
            
            if (query === '') {
                row.style.display = '';
                return;
            }
            
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Chercher dans les cartes/listes
        container.querySelectorAll('.list-group-item, .card-item, .user-item').forEach(function(item) {
            if (query === '') {
                item.style.display = '';
                return;
            }
            const text = item.textContent.toLowerCase();
            if (text.includes(query)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    function performAjaxSearch(form, query, input) {
        const formData = new FormData(form);
        formData.set('search', query);
        formData.set('ajax', '1');
        
        fetch(form.action || window.location.href, {
            method: form.method || 'GET',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Mettre à jour le contenu si possible
            const resultContainer = document.querySelector('#search-results');
            if (resultContainer) {
                resultContainer.innerHTML = html;
            }
        })
        .catch(err => console.error('Erreur recherche:', err));
    }
});
