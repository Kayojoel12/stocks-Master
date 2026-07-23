/**
 * SPA navigation + live search for StockMaster
 */
(function () {
    'use strict';
    if (window.__stockSpaLoaded) return;
    window.__stockSpaLoaded = true;

    var FULL_RELOAD = [
        'logout.php',
        'login.php',
        'toggle_theme.php',
        'set_language.php',
        'invoice_pdf.php',
        'print_invoice.php',
        'send_order.php',
        'delete_supplier.php',
        'delete_site.php',
        'site_map.php',
        'add_site.php'
    ];

    var EXTERNAL_LIBS = /jquery|bootstrap|lucide|chart\.js|spa\.js|font-awesome|cdn\.jsdelivr|unpkg\.com|googleapis/i;
    var busy = false;
    var searchTimers = {};

    function showLoader() {
        var el = document.getElementById('spa-loader');
        if (!el) {
            el = document.createElement('div');
            el.id = 'spa-loader';
            el.innerHTML = '<div class="spa-loader-bar"></div>';
            document.body.appendChild(el);
        }
        el.classList.add('active');
    }

    function hideLoader() {
        var el = document.getElementById('spa-loader');
        if (el) el.classList.remove('active');
    }

    function sameOrigin(url) {
        try {
            var u = new URL(url, window.location.href);
            return u.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function shouldFullReload(href, anchor) {
        if (!href || href === '#' || href.indexOf('javascript:') === 0) return true;
        if (href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return true;
        if (anchor && (anchor.target === '_blank' || anchor.hasAttribute('download') || anchor.dataset.fullReload === '1' || anchor.dataset.spaIgnore === '1')) return true;
        if (!sameOrigin(href)) return true;

        var path = href.split('?')[0].split('#')[0];
        var file = path.split('/').pop();
        if (FULL_RELOAD.indexOf(file) !== -1) return true;
        if (/[?&]export=/.test(href)) return true;
        if (/\.pdf($|\?)/i.test(href)) return true;
        return false;
    }

    function updateSidebarActive(url) {
        var file = (url.split('?')[0].split('#')[0].split('/').pop() || 'index.php');
        document.querySelectorAll('#sidebar a[href]').forEach(function (a) {
            var href = (a.getAttribute('href') || '').split('?')[0].split('/').pop();
            a.classList.toggle('active', href === file);
            a.classList.toggle('active-sub', href === file);
        });
    }

    function executeScripts(container, doc) {
        // Scripts embedded in swapped content
        if (container) {
            container.querySelectorAll('script').forEach(function (old) {
                runScript(old);
            });
        }
        // Page-level body scripts (charts, maps) from fetched document
        if (doc) {
            doc.querySelectorAll('body > script, #content script').forEach(function (old) {
                if (old.closest && old.closest('#sidebar')) return;
                runScript(old);
            });
        }
    }

    function runScript(old) {
        if (!old) return;
        if (old.src) {
            if (EXTERNAL_LIBS.test(old.src)) return;
            if (document.querySelector('script[src="' + old.getAttribute('src') + '"]')) return;
            var s = document.createElement('script');
            s.src = old.src;
            s.async = false;
            document.body.appendChild(s);
            return;
        }
        var code = (old.textContent || '').trim();
        if (!code) return;
        // Skip shell helpers already defined
        if (/function\s+toggleTheme|var\s+__themeBusy|spa-loader|initStockMasterSpa/.test(code) && code.indexOf('Chart') === -1 && code.indexOf('L.map') === -1 && code.indexOf('leaflet') === -1) {
            if (/toggleTheme|__themeBusy|sidebarCollapse/.test(code) && !/new Chart|L\.map|leaflet|send-mail/.test(code)) return;
        }
        try {
            var s2 = document.createElement('script');
            s2.textContent = code;
            document.body.appendChild(s2);
            s2.remove();
        } catch (err) {
            console.warn('SPA script error', err);
        }
    }

    function swapContent(doc, url) {
        document.title = doc.title || document.title;

        var newSidebar = doc.querySelector('#sidebar ul.components');
        var curSidebar = document.querySelector('#sidebar ul.components');
        if (newSidebar && curSidebar) {
            curSidebar.innerHTML = newSidebar.innerHTML;
        }
        var newBadge = doc.querySelector('#sidebar .sidebar-header');
        var curBadge = document.querySelector('#sidebar .sidebar-header');
        if (newBadge && curBadge) {
            curBadge.innerHTML = newBadge.innerHTML;
        }

        var newContent = doc.querySelector('#content');
        var curContent = document.querySelector('#content');
        if (!newContent || !curContent) {
            window.location.href = url;
            return false;
        }

        curContent.innerHTML = newContent.innerHTML;
        executeScripts(curContent, doc);
        updateSidebarActive(url);
        return true;
    }

    function ensureLib(testFn, src, cb) {
        if (testFn()) {
            cb();
            return;
        }
        var existing = document.querySelector('script[src="' + src + '"]');
        if (existing) {
            existing.addEventListener('load', cb);
            setTimeout(cb, 400);
            return;
        }
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb;
        s.onerror = cb;
        document.head.appendChild(s);
    }

    function afterPageLoad(url) {
        initBehaviors(document.getElementById('content') || document);
        if (window.lucide) {
            try { lucide.createIcons(); } catch (e) {}
        }
        // Réinit dropdowns Bootstrap après swap SPA
        if (window.bootstrap && bootstrap.Dropdown) {
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
                try { bootstrap.Dropdown.getOrCreateInstance(el); } catch (e) {}
            });
        }
        var sideBtn = document.getElementById('sidebarCollapse');
        if (sideBtn && sideBtn.dataset.bound !== '1') {
            sideBtn.dataset.bound = '1';
            sideBtn.addEventListener('click', function () {
                var sidebar = document.getElementById('sidebar');
                if (sidebar) sidebar.classList.toggle('active');
                document.body.classList.toggle('sidebar-collapsed');
            });
        }

        var file = (url.split('?')[0].split('#')[0].split('/').pop() || '');

        // Réinit graphiques dashboard (SPA : sans rechargement)
        if (document.getElementById('stockChart') || document.getElementById('categoryChart') ||
            document.getElementById('dashboard-stock-data')) {
            ensureLib(
                function () { return typeof window.Chart !== 'undefined'; },
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                function () {
                    ensureLib(
                        function () { return typeof window.initDashboardCharts === 'function'; },
                        'js/script.js',
                        function () {
                            setTimeout(function () {
                                try {
                                    var stockEl = document.getElementById('dashboard-stock-data');
                                    var catEl = document.getElementById('dashboard-category-data');
                                    if (stockEl) {
                                        window.DASHBOARD_STOCK_CHART = JSON.parse(stockEl.textContent || 'null');
                                    }
                                    if (catEl) {
                                        window.DASHBOARD_CATEGORY_CHART = JSON.parse(catEl.textContent || 'null');
                                    }
                                } catch (e) {}
                                if (window.destroyDashboardCharts) window.destroyDashboardCharts();
                                if (window.initDashboardCharts) window.initDashboardCharts();
                            }, 80);
                        }
                    );
                }
            );
        }

        // Maps : déjà en full reload, mais sécurité si SPA
        if (document.getElementById('map') && (file === 'site_map.php' || file === 'add_site.php')) {
            window.location.href = url;
        }
    }

    function navigate(url, push) {
        if (busy) return;
        var pathFile = (url.split('?')[0].split('#')[0].split('/').pop() || '');
        if (FULL_RELOAD.indexOf(pathFile) !== -1) {
            window.location.href = url;
            return;
        }
        busy = true;
        showLoader();

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-SPA': '1' },
            cache: 'no-store'
        })
            .then(function (res) {
                if (res.redirected && /login\.php/i.test(res.url)) {
                    window.location.href = res.url;
                    return null;
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                if (html == null) return;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                if (!doc.querySelector('#content')) {
                    window.location.href = url;
                    return;
                }
                if (window.destroyDashboardCharts) window.destroyDashboardCharts();
                if (!swapContent(doc, url)) return;
                if (push !== false) {
                    history.pushState({ spa: true, url: url }, '', url);
                }
                afterPageLoad(url);
                window.scrollTo(0, 0);
            })
            .catch(function (err) {
                console.error(err);
                window.location.href = url;
            })
            .finally(function () {
                busy = false;
                hideLoader();
            });
    }

    function debounceKey(key, fn, wait) {
        clearTimeout(searchTimers[key]);
        searchTimers[key] = setTimeout(fn, wait || 180);
    }

    function findFilterableTables(root) {
        return Array.prototype.slice.call((root || document).querySelectorAll('table tbody'));
    }

    function filterTables(query, root) {
        var q = (query || '').toLowerCase().trim();
        findFilterableTables(root).forEach(function (tbody) {
            var rows = tbody.querySelectorAll('tr');
            var visible = 0;
            rows.forEach(function (tr) {
                // skip empty placeholder rows with colspan only message if needed
                var text = (tr.textContent || '').toLowerCase();
                var show = !q || text.indexOf(q) !== -1;
                tr.style.display = show ? '' : 'none';
                if (show) visible++;
            });
        });
    }

    function filterBySelect(select) {
        var selected = select.options[select.selectedIndex];
        var val = select.value ? ((selected && selected.text) || select.value).toLowerCase().trim() : '';
        var card = select.closest('.card, .container-fluid, #content') || document;
        var colIndex = select.dataset.filterCol ? parseInt(select.dataset.filterCol, 10) : -1;
        var table = card.querySelector('table');
        if (!table) return;

        table.querySelectorAll('tbody tr').forEach(function (tr) {
            if (!val) {
                tr.dataset.selectHide = '0';
                return;
            }
            var cells = tr.querySelectorAll('td');
            var hay = colIndex >= 0 && cells[colIndex]
                ? (cells[colIndex].textContent || '')
                : (tr.textContent || '');
            tr.dataset.selectHide = hay.toLowerCase().indexOf(val) !== -1 ? '0' : '1';
        });
        applyCombinedFilters(card);
    }

    function applyCombinedFilters(root) {
        var scope = root || document;
        var searchInput = scope.querySelector('input[name="search"], input#search, input#searchInput, .js-live-search');
        var q = searchInput ? (searchInput.value || '').toLowerCase().trim() : '';
        findFilterableTables(scope).forEach(function (tbody) {
            tbody.querySelectorAll('tr').forEach(function (tr) {
                if (tr.dataset.selectHide === '1' || tr.dataset.lowHide === '1') {
                    tr.style.display = 'none';
                    return;
                }
                var text = (tr.textContent || '').toLowerCase();
                tr.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    function bindLiveSearch(root) {
        var scope = root || document;
        var inputs = scope.querySelectorAll(
            'input[name="search"], input#search, input#searchInput, input[name="q"], .js-live-search'
        );
        inputs.forEach(function (input, idx) {
            if (input.dataset.liveBound === '1') return;
            input.dataset.liveBound = '1';
            input.setAttribute('autocomplete', 'off');

            var form = input.closest('form');
            if (form && form.method && form.method.toLowerCase() === 'get') {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    applyCombinedFilters(form.closest('.container-fluid, #content, .card') || scope);
                });
            }

            input.addEventListener('input', function () {
                var key = 's' + idx + (input.id || input.name || '');
                debounceKey(key, function () {
                    var area = input.closest('.container-fluid, #content, .card, .row') || scope;
                    // Navbar global search filters whole page
                    if (input.name === 'q' && input.closest('nav.navbar')) {
                        filterTables(input.value, document.getElementById('content'));
                    } else {
                        applyCombinedFilters(area);
                    }
                }, 120);
            });
        });

        scope.querySelectorAll('select[name="category"], select#category, select.js-live-filter').forEach(function (sel) {
            if (sel.dataset.liveBound === '1') return;
            sel.dataset.liveBound = '1';
            sel.addEventListener('change', function () {
                filterBySelect(sel);
                var area = sel.closest('.container-fluid, #content, .card') || scope;
                applyCombinedFilters(area);
            });
        });

        // Low stock switch: client filter on stock page
        var low = scope.querySelector('#low_stock, input[name="low_stock"]');
        if (low && low.dataset.liveBound !== '1') {
            low.dataset.liveBound = '1';
            low.addEventListener('change', function () {
                var area = low.closest('.container-fluid, #content') || scope;
                var tbody = area.querySelector('table tbody');
                if (!tbody) return;
                tbody.querySelectorAll('tr').forEach(function (tr) {
                    if (!low.checked) {
                        tr.dataset.lowHide = '0';
                    } else {
                        var isLow = tr.classList.contains('table-warning') || tr.classList.contains('table-danger')
                            || /stock faible|low stock|rupture|out of stock/i.test(tr.textContent || '');
                        tr.dataset.lowHide = isLow ? '0' : '1';
                    }
                });
                tbody.querySelectorAll('tr').forEach(function (tr) {
                    if (tr.dataset.lowHide === '1' || tr.dataset.selectHide === '1') {
                        tr.style.display = 'none';
                        return;
                    }
                    var searchInput = area.querySelector('input[name="search"], input#search, input#searchInput');
                    var q = searchInput ? (searchInput.value || '').toLowerCase().trim() : '';
                    var text = (tr.textContent || '').toLowerCase();
                    tr.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }
    }

    function initBehaviors(root) {
        bindLiveSearch(root || document);

        // Prevent GET filter forms from full reload — live filter instead
        (root || document).querySelectorAll('form[method="get"], form[method="GET"]').forEach(function (form) {
            if (form.dataset.spaBound === '1') return;
            if (form.closest('nav.navbar')) {
                form.dataset.spaBound = '1';
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var q = form.querySelector('input[name="q"]');
                    if (q) filterTables(q.value, document.getElementById('content'));
                });
                return;
            }
            // Keep forms that are not search (date ranges on reports) as SPA navigate
            var hasSearch = form.querySelector('input[name="search"], #search, #searchInput');
            if (hasSearch) {
                form.dataset.spaBound = '1';
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    applyCombinedFilters(form.closest('.container-fluid, #content') || document);
                });
            }
        });
    }

    function onClick(e) {
        var a = e.target.closest('a[href]');
        if (!a) return;
        if (e.defaultPrevented) return;
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var href = a.getAttribute('href');
        if (shouldFullReload(href, a)) return;

        // Bootstrap collapse toggles
        if (a.getAttribute('data-bs-toggle') === 'collapse') return;

        e.preventDefault();
        var url = a.href;
        navigate(url, true);
    }

    function onPopState(e) {
        var url = (e.state && e.state.url) || window.location.href;
        navigate(url, false);
    }

    // Theme without full reload
    window.toggleTheme = function () {
        if (window.__themeBusy) return;
        window.__themeBusy = true;
        var btn = document.getElementById('themeToggle');
        if (btn) btn.disabled = true;
        var currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        var newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        fetch('toggle_theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'theme=' + encodeURIComponent(newTheme),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().catch(function () { return { ok: r.ok, theme: newTheme }; }); })
            .then(function (data) {
                if (!(data && data.ok)) throw new Error('fail');
                var theme = data.theme || newTheme;
                document.documentElement.setAttribute('data-bs-theme', theme);
                try { localStorage.setItem('theme', theme); } catch (e) {}
                document.body.classList.toggle('bg-dark', theme === 'dark');
                document.body.classList.toggle('text-light', theme === 'dark');
                document.body.classList.toggle('bg-light', theme !== 'dark');
                // Soft refresh current view to restyle PHP theme classes
                navigate(window.location.pathname + window.location.search, false);
            })
            .catch(function () {
                alert('Theme switch failed');
            })
            .finally(function () {
                window.__themeBusy = false;
                if (btn) btn.disabled = false;
            });
    };

    window.StockMasterNavigate = navigate;

    document.addEventListener('click', onClick);
    window.addEventListener('popstate', onPopState);

    document.addEventListener('DOMContentLoaded', function () {
        history.replaceState({ spa: true, url: window.location.href }, '', window.location.href);
        initBehaviors(document);
        if (window.lucide) {
            try { lucide.createIcons(); } catch (e) {}
        }
        if (window.bootstrap && bootstrap.Dropdown) {
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
                try { bootstrap.Dropdown.getOrCreateInstance(el); } catch (e) {}
            });
        }
    });
})();
