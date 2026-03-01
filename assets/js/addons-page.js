/**
 * Add-ons Page Scripts
 *
 * Handles client-side category filtering, search with debounce,
 * and keyboard accessibility for the add-ons grid.
 *
 * @package ArrayPress\RegisterAddons
 */

(function () {
    'use strict';

    /* =========================================================================
     * DOM REFERENCES
     * ========================================================================= */

    const grid = document.getElementById('addons-grid');
    const noResults = document.getElementById('addons-no-results');
    const searchInput = document.getElementById('addons-search');
    const categoryBtns = document.querySelectorAll('.addons-category-btn');

    if (!grid) {
        return;
    }

    const cards = grid.querySelectorAll('.addon-card');

    /* =========================================================================
     * STATE
     * ========================================================================= */

    const DEBOUNCE_MS = 200;

    let activeCategory = 'all';
    let searchTerm = '';
    let debounceTimer = null;

    /* =========================================================================
     * URL STATE
     * ========================================================================= */

    /**
     * Read initial state from URL parameters
     */
    function readUrlState() {
        const params = new URLSearchParams(window.location.search);
        const cat = params.get('category');
        const search = params.get('s');

        if (cat && cat !== 'all') {
            activeCategory = cat;

            categoryBtns.forEach(btn => {
                if (btn.getAttribute('data-category') === cat) {
                    btn.classList.add('active');
                    btn.setAttribute('aria-selected', 'true');
                } else {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-selected', 'false');
                }
            });
        }

        if (search && searchInput) {
            searchTerm = search.toLowerCase().trim();
            searchInput.value = search;
        }
    }

    /**
     * Update URL parameters without page reload
     */
    function updateUrl() {
        if (!window.history || !window.history.replaceState) {
            return;
        }

        const params = new URLSearchParams(window.location.search);

        if (activeCategory && activeCategory !== 'all') {
            params.set('category', activeCategory);
        } else {
            params.delete('category');
        }

        if (searchTerm) {
            params.set('s', searchTerm);
        } else {
            params.delete('s');
        }

        const newUrl = window.location.pathname + '?' + params.toString();
        window.history.replaceState(null, '', newUrl);
    }

    /* =========================================================================
     * FILTERING
     * ========================================================================= */

    /**
     * Filter cards based on active category and search term
     *
     * Uses the data-search attribute which contains a pre-built
     * searchable string of title, description, category label,
     * and badge text.
     */
    function filterCards() {
        let visibleCount = 0;

        cards.forEach(card => {
            const category = card.getAttribute('data-category');
            const searchText = card.getAttribute('data-search') || '';

            // Category match
            const matchCategory = (activeCategory === 'all' || category === activeCategory);

            // Search match — check against the composite search attribute
            let matchSearch = true;
            if (searchTerm) {
                const words = searchTerm.split(/\s+/);
                matchSearch = words.every(word => !word || searchText.indexOf(word) !== -1);
            }

            if (matchCategory && matchSearch) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Toggle no results message
        if (noResults) {
            noResults.style.display = visibleCount === 0 ? '' : 'none';
        }

        // Toggle grid visibility (prevents empty grid gap)
        grid.style.display = visibleCount === 0 ? 'none' : '';

        updateUrl();
    }

    /* =========================================================================
     * CATEGORY BUTTONS
     * ========================================================================= */

    categoryBtns.forEach((btn, index) => {
        btn.addEventListener('click', () => {
            categoryBtns.forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });

            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            activeCategory = btn.getAttribute('data-category');

            filterCards();
        });

        btn.addEventListener('keydown', (e) => {
            let next = -1;

            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                next = (index + 1) % categoryBtns.length;
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                next = (index - 1 + categoryBtns.length) % categoryBtns.length;
            } else if (e.key === 'Home') {
                next = 0;
            } else if (e.key === 'End') {
                next = categoryBtns.length - 1;
            }

            if (next >= 0) {
                e.preventDefault();
                categoryBtns[next].focus();
                categoryBtns[next].click();
            }
        });
    });

    /* =========================================================================
     * SEARCH INPUT
     * ========================================================================= */

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const value = searchInput.value.toLowerCase().trim();

            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            debounceTimer = setTimeout(() => {
                searchTerm = value;
                filterCards();
            }, DEBOUNCE_MS);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && searchInput.value) {
                e.preventDefault();
                searchInput.value = '';
                searchTerm = '';
                filterCards();
            }
        });
    }

    /* =========================================================================
     * INIT
     * ========================================================================= */

    readUrlState();

    if (activeCategory !== 'all' || searchTerm) {
        filterCards();
    }
})();
