/* =====================================================================
   Dança Carajás Captação — JS leve (app.js)
   Sem dependências. Apenas interações simples: menu mobile e mensagens.
   ===================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initIcons();
        initNavToggle();
        initDismissibleNotices();
        initAutoConfirm();
        initDashboardCharts();
    });

    /** Renderiza os ícones Lucide (<i data-lucide="nome">). */
    function initIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    /** Alterna o menu de navegação no mobile. */
    function initNavToggle() {
        var toggle = document.querySelector('[data-nav-toggle]');
        var nav = document.querySelector('[data-nav]');

        if (!toggle || !nav) {
            return;
        }

        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    /** Permite fechar avisos com [data-dismiss]. */
    function initDismissibleNotices() {
        document.querySelectorAll('[data-dismiss]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.closest('[data-dismissible]');
                if (target) {
                    target.style.display = 'none';
                }
            });
        });
    }

    /** Confirmação simples para ações sensíveis (uso futuro). */
    function initAutoConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                var message = el.getAttribute('data-confirm') || 'Tem certeza?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    /** Inicializa graficos gerenciais com Chart.js quando a tela fornece dados. */
    function initDashboardCharts() {
        var dataNode = document.getElementById('dashboard-chart-data');

        if (!dataNode || !window.Chart) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(dataNode.textContent || '{}');
        } catch (error) {
            return;
        }

        Chart.defaults.font.family = "'Nunito Sans', Arial, sans-serif";
        Chart.defaults.color = '#2f3742';
        Chart.defaults.borderColor = 'rgba(17, 17, 17, 0.08)';

        var yellow = '#ffc400';
        var yellowDark = '#b58d00';
        var black = '#111111';
        var green = '#55a868';
        var slate = '#64748b';

        renderChart('financial', function (ctx, data) {
            var gradient = ctx.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, 'rgba(255, 196, 0, 0.34)');
            gradient.addColorStop(1, 'rgba(255, 196, 0, 0.02)');

            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Valor',
                        data: data.values || [],
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: black,
                        pointBackgroundColor: yellow,
                        pointBorderColor: black,
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.38
                    }]
                },
                options: baseOptions({
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: formatMoneyCompact }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return formatMoney(context.parsed.y || 0);
                                }
                            }
                        }
                    }
                })
            });
        });

        renderChart('commercial', function (ctx, data) {
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Registros',
                        data: data.values || [],
                        backgroundColor: [black, yellow, '#f6d54a', '#8a6f00', slate],
                        borderRadius: 10,
                        borderSkipped: false,
                        maxBarThickness: 62
                    }]
                },
                options: baseOptions({
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return String(context.parsed.y || 0) + ' registro(s)';
                                }
                            }
                        }
                    }
                })
            });
        });

        renderChart('health', function (ctx, data) {
            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: [yellowDark, green],
                        borderColor: '#ffffff',
                        borderWidth: 6,
                        hoverOffset: 4
                    }]
                },
                options: baseOptions({
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                usePointStyle: true
                            }
                        }
                    }
                }, false)
            });
        });

        renderChart('operational', function (ctx, data) {
            return new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Volume operacional',
                        data: data.values || [],
                        backgroundColor: 'rgba(255, 196, 0, 0.22)',
                        borderColor: black,
                        pointBackgroundColor: yellow,
                        pointBorderColor: black,
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: baseOptions({
                    scales: {
                        r: {
                            beginAtZero: true,
                            ticks: {
                                backdropColor: 'transparent',
                                precision: 0
                            },
                            grid: { color: 'rgba(17, 17, 17, 0.10)' },
                            angleLines: { color: 'rgba(17, 17, 17, 0.10)' },
                            pointLabels: {
                                color: black,
                                font: { size: 11, weight: '700' }
                            }
                        }
                    }
                }, false)
            });
        });

        function renderChart(name, factory) {
            var canvas = document.querySelector('[data-dashboard-chart="' + name + '"]');
            var data = payload[name] || {};

            if (!canvas) {
                return;
            }

            factory(canvas.getContext('2d'), data);
        }

        function baseOptions(extra, showLegend) {
            var options = {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650 },
                plugins: {
                    legend: { display: showLegend === true },
                    tooltip: {
                        backgroundColor: '#111111',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#111111',
                            font: { size: 11, weight: '700' }
                        }
                    },
                    y: {
                        grid: { color: 'rgba(17, 17, 17, 0.08)' },
                        ticks: {
                            color: '#4b5563',
                            font: { size: 11, weight: '700' }
                        }
                    }
                }
            };

            return mergeOptions(options, extra || {});
        }

        function mergeOptions(target, source) {
            Object.keys(source).forEach(function (key) {
                if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                    target[key] = mergeOptions(target[key] || {}, source[key]);
                    return;
                }

                target[key] = source[key];
            });

            return target;
        }

        function formatMoney(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value || 0);
        }

        function formatMoneyCompact(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
                notation: 'compact',
                maximumFractionDigits: 1
            }).format(value || 0);
        }
    }
})();
