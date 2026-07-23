/**
 * StockMaster dashboard helpers
 * Charts use embedded PHP data when available (faster), else API fallback.
 */
(function () {
    'use strict';

    function chartLabels() {
        var el = document.getElementById('chartI18n');
        return {
            entries: (el && el.dataset.entries) || 'Entries',
            exits: (el && el.dataset.exits) || 'Exits'
        };
    }

    /** Distinct colors for N categories (HSL spread) */
    function distinctColors(n) {
        var colors = [];
        var hover = [];
        var palette = [
            '#4e73df', '#1cc88a', '#e74a3b', '#f6c23e', '#36b9cc',
            '#9b59b6', '#e67e22', '#2ecc71', '#3498db', '#e91e63',
            '#00bcd4', '#8bc34a', '#ff5722', '#607d8b', '#795548'
        ];
        for (var i = 0; i < n; i++) {
            if (i < palette.length) {
                colors.push(palette[i]);
            } else {
                var h = Math.round((i * 137.508) % 360); // golden angle
                colors.push('hsl(' + h + ', 70%, 48%)');
            }
            hover.push(colors[i]);
        }
        return { bg: colors, hover: hover };
    }

    window.destroyDashboardCharts = function () {
        if (window._stockChartInstance) {
            try { window._stockChartInstance.destroy(); } catch (e) {}
            window._stockChartInstance = null;
        }
        if (window._categoryChartInstance) {
            try { window._categoryChartInstance.destroy(); } catch (e) {}
            window._categoryChartInstance = null;
        }
    };

    window.initStockChart = function (payload) {
        var canvas = document.getElementById('stockChart');
        if (!canvas || typeof Chart === 'undefined') return;

        function render(data) {
            if (!data || !data.labels) return;
            if (window._stockChartInstance) {
                try { window._stockChartInstance.destroy(); } catch (e) {}
            }
            var L = chartLabels();
            var ctx = canvas.getContext('2d');
            window._stockChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: L.entries,
                        data: data.entrees || [],
                        backgroundColor: 'rgba(78, 115, 223, 0.08)',
                        borderColor: '#4e73df',
                        pointBackgroundColor: '#4e73df',
                        tension: 0.3
                    }, {
                        label: L.exits,
                        data: data.sorties || [],
                        backgroundColor: 'rgba(231, 74, 59, 0.08)',
                        borderColor: '#e74a3b',
                        pointBackgroundColor: '#e74a3b',
                        tension: 0.3
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: true, position: 'top' } }
                }
            });
        }

        if (payload) {
            render(payload);
            return;
        }
        if (window.DASHBOARD_STOCK_CHART) {
            render(window.DASHBOARD_STOCK_CHART);
            return;
        }
        $.ajax({
            url: 'api/stock_movements.php',
            method: 'GET',
            dataType: 'json',
            timeout: 8000,
            success: render,
            error: function () { console.error('Stock chart load failed'); }
        });
    };

    window.initCategoryChart = function (payload) {
        var canvas = document.getElementById('categoryChart');
        if (!canvas || typeof Chart === 'undefined') return;

        function render(data) {
            if (!data || !data.labels) return;
            if (window._categoryChartInstance) {
                try { window._categoryChartInstance.destroy(); } catch (e) {}
            }
            var n = data.labels.length;
            var cols = distinctColors(n);
            var ctx = canvas.getContext('2d');
            window._categoryChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: cols.bg,
                        hoverBackgroundColor: cols.hover,
                        hoverBorderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { displayColors: true }
                    },
                    cutout: '65%'
                }
            });

            var legendHtml = '';
            data.labels.forEach(function (label, index) {
                legendHtml +=
                    '<span class="me-2 d-inline-block mb-1">' +
                    '<i class="fas fa-circle" style="color:' + cols.bg[index] + '"></i> ' +
                    label + '</span>';
            });
            var legend = document.getElementById('categoryLegend');
            if (legend) legend.innerHTML = legendHtml;
        }

        if (payload) {
            render(payload);
            return;
        }
        if (window.DASHBOARD_CATEGORY_CHART) {
            render(window.DASHBOARD_CATEGORY_CHART);
            return;
        }
        $.ajax({
            url: 'api/category_distribution.php',
            method: 'GET',
            dataType: 'json',
            timeout: 8000,
            success: render,
            error: function () { console.error('Category chart load failed'); }
        });
    };

    window.initDashboardCharts = function () {
        function readJson(id) {
            var el = document.getElementById(id);
            if (!el) return null;
            try { return JSON.parse(el.textContent || 'null'); } catch (e) { return null; }
        }
        var stock = readJson('dashboard-stock-data') || window.DASHBOARD_STOCK_CHART;
        var cat = readJson('dashboard-category-data') || window.DASHBOARD_CATEGORY_CHART;
        if (stock) window.DASHBOARD_STOCK_CHART = stock;
        if (cat) window.DASHBOARD_CATEGORY_CHART = cat;

        if (!document.getElementById('stockChart') && !document.getElementById('categoryChart')) return;
        window.initStockChart(stock || undefined);
        window.initCategoryChart(cat || undefined);
    };

    function bootCharts() {
        window.initDashboardCharts();
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function ($) {
            $('#sidebarCollapse').off('click.sm').on('click.sm', function () {
                $('#sidebar').toggleClass('active');
            });
            bootCharts();
        });
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCharts);
    } else {
        bootCharts();
    }
})();
