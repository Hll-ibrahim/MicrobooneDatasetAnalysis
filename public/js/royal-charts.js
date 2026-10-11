/* ==========================================================================
   Royal Charts — Chart.js 4 için tema ve kısayollar
   Kullanım (Blade içinde):  RC.hist('canvasId', @json($histogram), {x: 'Eν [GeV]'})
   ========================================================================== */
(function () {
    'use strict';

    const C = {
        wine: '#4A0D1C', burgundy: '#7B1830', claret: '#9B2C42', rose: '#B4475E',
        gold: '#C9A646', goldDeep: '#A8862E', goldSoft: '#EBDDB4',
        plum: '#5B2A4E', navy: '#1F2E4A', teal: '#2F6F73',
        ivory: '#FBF8F1', ink: '#2B1B1F', muted: '#6E5A5F',
        grid: 'rgba(74, 13, 28, 0.08)',
    };
    const SERIES = [C.burgundy, C.gold, C.plum, C.teal, C.rose, C.navy, '#8C6D3F', '#A3485F', '#6B7F5E', '#9C7AA0', '#C27C4E', '#3E5C76'];
    const PLANE = { U: C.burgundy, V: C.teal, Y: C.gold };

    if (typeof Chart === 'undefined') {
        console.warn('Chart.js yüklenemedi.');
        return;
    }

    Chart.defaults.locale = 'tr-TR';   // eksenlerde 1.234,5 biçimi
    Chart.defaults.font.family = '"Source Sans 3", system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = C.muted;
    Chart.defaults.borderColor = C.grid;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.animation.duration = 450;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = C.wine;
    Chart.defaults.plugins.tooltip.titleColor = C.goldSoft;
    Chart.defaults.plugins.tooltip.bodyColor = '#fff';
    Chart.defaults.plugins.tooltip.borderColor = C.gold;
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.padding = 10;

    const nf = new Intl.NumberFormat('tr-TR');
    const alpha = (hex, a) => {
        const n = parseInt(hex.slice(1), 16);
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
    };
    const title = (t) => (t ? { display: true, text: t, color: C.ink, font: { weight: '600' } } : { display: false });

    function canvas(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        const old = Chart.getChart(el);
        if (old) old.destroy();
        return el;
    }

    function isEmpty(d) {
        return !d || !d.data || d.data.length === 0 || d.data.every((v) => !v);
    }

    function showEmpty(el, msg) {
        const box = el.parentElement;
        el.style.display = 'none';
        if (!box.querySelector('.chart-empty')) {
            const div = document.createElement('div');
            div.className = 'chart-empty empty-state py-5';
            div.innerHTML = `<i class="bi bi-bar-chart"></i>${msg || 'Gösterilecek veri yok'}`;
            box.appendChild(div);
        }
    }

    const RC = {
        colors: C,
        series: SERIES,
        planeColors: PLANE,
        nf,

        /** Histogram: d = {labels, data} */
        hist(id, d, o = {}) {
            const el = canvas(id);
            if (!el) return null;
            if (isEmpty(d)) return showEmpty(el, o.empty);
            const color = o.color || C.burgundy;
            return new Chart(el, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: o.label || 'Sayı',
                        data: d.data,
                        backgroundColor: alpha(color, 0.78),
                        borderColor: color,
                        borderWidth: 1,
                        barPercentage: 1.0,
                        categoryPercentage: 1.0,
                    }],
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { title: (it) => `${o.x || ''} ≈ ${it[0].label}`, label: (it) => ` ${nf.format(it.raw)} kayıt` } },
                    },
                    scales: {
                        x: { title: title(o.x), grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                        y: { title: title(o.y || 'Sayı'), type: o.logY ? 'logarithmic' : 'linear', beginAtZero: !o.logY },
                    },
                },
            });
        },

        /** Aynı kutulara sahip birden çok histogram (ör. U/V/Y düzlemleri) — basamaklı çizgi */
        histMulti(id, sets, o = {}) {
            const el = canvas(id);
            if (!el) return null;
            const keys = Object.keys(sets || {});
            if (!keys.length || keys.every((k) => isEmpty(sets[k]))) return showEmpty(el, o.empty);
            return new Chart(el, {
                type: 'line',
                data: {
                    labels: sets[keys[0]].labels,
                    datasets: keys.map((k, i) => {
                        const col = (o.colors && o.colors[k]) || PLANE[k] || SERIES[i % SERIES.length];
                        return {
                            label: o.prefix ? `${o.prefix} ${k}` : k,
                            data: sets[k].data,
                            stepped: 'middle',
                            borderColor: col,
                            backgroundColor: alpha(col, 0.12),
                            fill: true,
                            pointRadius: 0,
                            borderWidth: 2,
                        };
                    }),
                },
                options: {
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { title: title(o.x), grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 14 } },
                        y: { title: title(o.y || 'Sayı'), beginAtZero: true, type: o.logY ? 'logarithmic' : 'linear' },
                    },
                },
            });
        },

        /** Kategorik çubuk grafiği; o.horizontal = true yatay çizer */
        bar(id, d, o = {}) {
            const el = canvas(id);
            if (!el) return null;
            if (isEmpty(d)) return showEmpty(el, o.empty);
            const colors = o.multi ? d.data.map((_, i) => SERIES[i % SERIES.length]) : (o.color || C.burgundy);
            return new Chart(el, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: o.label || 'Sayı',
                        data: d.data,
                        backgroundColor: Array.isArray(colors) ? colors.map((c) => alpha(c, 0.85)) : alpha(colors, 0.82),
                        borderColor: colors,
                        borderWidth: 1,
                        borderRadius: 4,
                        maxBarThickness: o.thick || 46,
                    }],
                },
                options: {
                    indexAxis: o.horizontal ? 'y' : 'x',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (it) => ` ${nf.format(it.raw)}${o.unit ? ' ' + o.unit : ''}` } } },
                    scales: {
                        x: { title: title(o.horizontal ? (o.y || 'Sayı') : o.x), grid: { display: !!o.horizontal }, beginAtZero: true, ticks: { autoSkipPadding: 8 } },
                        y: { title: title(o.horizontal ? o.x : (o.y || 'Sayı')), grid: { display: !o.horizontal }, beginAtZero: true },
                    },
                },
            });
        },

        /** Halka grafiği */
        doughnut(id, d, o = {}) {
            const el = canvas(id);
            if (!el) return null;
            if (isEmpty(d)) return showEmpty(el, o.empty);
            const colors = o.colors || d.data.map((_, i) => SERIES[i % SERIES.length]);
            return new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{ data: d.data, backgroundColor: colors, borderColor: C.ivory, borderWidth: 3, hoverOffset: 8 }],
                },
                options: {
                    cutout: '62%',
                    plugins: {
                        legend: { position: o.legend || 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: (it) => {
                                    const tot = it.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                    return ` ${it.label}: ${nf.format(it.raw)} (%${(100 * it.raw / tot).toFixed(1)})`;
                                },
                            },
                        },
                    },
                },
            });
        },

        /** Dağılım grafiği; o.box = [[x0, x1], [y0, y1]] dedektör çerçevesi çizer */
        scatter(id, points, o = {}) {
            const el = canvas(id);
            if (!el) return null;
            if (!points || !points.length) return showEmpty(el, o.empty);
            const datasets = [{
                label: o.label || 'Olaylar',
                data: points,
                backgroundColor: alpha(o.color || C.burgundy, 0.45),
                borderColor: alpha(o.color || C.burgundy, 0.8),
                pointRadius: o.radius || 2.2,
                pointHoverRadius: 4,
            }];
            if (o.box) {
                const [[x0, x1], [y0, y1]] = o.box;
                datasets.push({
                    label: o.boxLabel || 'Dedektör sınırı',
                    data: [{ x: x0, y: y0 }, { x: x1, y: y0 }, { x: x1, y: y1 }, { x: x0, y: y1 }, { x: x0, y: y0 }],
                    showLine: true, fill: false, pointRadius: 0,
                    borderColor: C.goldDeep, borderDash: [6, 4], borderWidth: 1.5,
                });
            }
            if (o.diagonal) {
                const mx = Math.max(...points.map((p) => Math.max(p.x, p.y)));
                datasets.push({
                    label: 'y = x', data: [{ x: 0, y: 0 }, { x: mx, y: mx }], showLine: true, pointRadius: 0,
                    borderColor: C.goldDeep, borderDash: [4, 4], borderWidth: 1.2,
                });
            }
            return new Chart(el, {
                type: 'scatter',
                data: { datasets },
                options: {
                    plugins: { legend: { display: !!(o.box || o.diagonal) } },
                    scales: {
                        x: { title: title(o.x), min: o.xmin, max: o.xmax },
                        y: { title: title(o.y), min: o.ymin, max: o.ymax },
                    },
                },
            });
        },

        /**
         * Olay görüntüsü: points = [[tel, zaman, integral, nötrino?, en_iyi_dilim?], ...]
         * mode: 'truth' | 'slice' | 'charge'
         */
        eventDisplay(id, points, mode = 'truth', o = {}) {
            const el = canvas(id);
            if (!el) return null;
            if (!points || !points.length) return showEmpty(el, 'Bu düzlemde hit yok');

            const pt = (p) => ({ x: p[0], y: p[1], q: p[2] });
            let datasets;
            if (mode === 'charge') {
                const qs = points.map((p) => p[2]);
                const lo = Math.log(20), hi = Math.log(700);
                const ramp = ['#3a1325', '#6d1a33', '#9b2c42', '#c45a4a', '#c9a646', '#efe0b0', '#fffaf0'];
                const color = (q) => {
                    const t = Math.min(1, Math.max(0, (Math.log(Math.max(q, 1)) - lo) / (hi - lo)));
                    return ramp[Math.min(ramp.length - 1, Math.floor(t * (ramp.length - 1) + 0.5))];
                };
                datasets = [{
                    label: 'Hit (renk = integral)',
                    data: points.map(pt),
                    pointBackgroundColor: qs.map(color),
                    pointBorderWidth: 0,
                    pointRadius: 1.6,
                }];
            } else {
                const flag = mode === 'slice' ? 4 : 3;
                const on = points.filter((p) => p[flag] === 1).map(pt);
                const off = points.filter((p) => p[flag] !== 1).map(pt);
                datasets = [
                    {
                        label: mode === 'slice' ? 'Diğer hit\'ler' : 'Kozmik / gürültü',
                        data: off, backgroundColor: 'rgba(214, 200, 190, 0.32)', pointBorderWidth: 0, pointRadius: 1.3, order: 2,
                    },
                    {
                        label: mode === 'slice' ? 'En iyi ν dilimi (Pandora)' : 'Nötrino hit\'i (gerçek)',
                        data: on, backgroundColor: mode === 'slice' ? '#E9798F' : '#E8C35A', pointBorderWidth: 0, pointRadius: 1.9, order: 1,
                    },
                ];
            }

            return new Chart(el, {
                type: 'scatter',
                data: { datasets },
                options: {
                    animation: false,
                    parsing: true,
                    plugins: {
                        legend: { labels: { color: '#EBDDB4' }, position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: (it) => ` tel ${it.raw.x} · t ${it.raw.y} tick · integral ${it.raw.q}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Tel numarası', color: '#EBDDB4' },
                            ticks: { color: 'rgba(235,221,180,.75)' }, grid: { color: 'rgba(201,166,70,.10)' },
                            min: o.wmin, max: o.wmax,
                        },
                        y: {
                            title: { display: true, text: 'Zaman [tick]', color: '#EBDDB4' },
                            ticks: { color: 'rgba(235,221,180,.75)', stepSize: 800 }, grid: { color: 'rgba(201,166,70,.10)' },
                            min: 0, max: 6400,
                        },
                    },
                },
            });
        },
    };

    window.RC = RC;
})();
