/*
 * Grafik Dashboard Analitik admin (Chart.js, di-bundle Vite). Data dibaca dari
 * <script type="application/json" id="analytics-data"> yang dirender server.
 * Palet kategorikal tervalidasi (lightness, chroma, CVD, kontras): hijau ThriftLO di urutan pertama.
 */
import {
    Chart, LineController, BarController, DoughnutController, LineElement, BarElement, ArcElement,
    PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(LineController, BarController, DoughnutController, LineElement, BarElement, ArcElement,
    PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

const PALETTE = ['#047857', '#d97706', '#0369a1', '#be185d', '#65a30d', '#7c3aed'];
const INK = '#334155';
const GRID = '#e2e8f0';

const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
const number = new Intl.NumberFormat('id-ID');
const rp = (v) => rupiah.format(v).replace(/\s/g, '');
const rpShort = (v) => (v === 0 ? 'Rp0' : 'Rp' + compact.format(v));

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = INK;
Chart.defaults.animation = reduceMotion ? false : { duration: 400 };
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.plugins.legend.position = 'bottom';
Chart.defaults.plugins.legend.labels.boxWidth = 12;
Chart.defaults.plugins.legend.labels.boxHeight = 12;
Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';

const valueTicks = (money) => ({ maxTicksLimit: 5, precision: 0, callback: (v) => (money ? rpShort(v) : number.format(v)) });
const valueAxis = (money) => ({ beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: valueTicks(money) });
const categoryAxis = { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0, autoSkipPadding: 12 } };
const line = (label, data, color, fill = false) => ({
    label, data, borderColor: color, backgroundColor: fill ? color + '1f' : color, fill,
    borderWidth: 2, cubicInterpolationMode: 'monotone', pointRadius: data.length > 40 ? 0 : 3, pointHoverRadius: 5, pointBackgroundColor: color,
});

function render(id, config) {
    const canvas = document.getElementById('chart-' + id);
    if (canvas) {
        new Chart(canvas, config);
    }
}

const source = document.getElementById('analytics-data');
const data = source ? JSON.parse(source.textContent) : null;

if (data) {
    const { sales, revenue, categories, channels, sellers, users } = data;
    const hoverIndex = { mode: 'index', intersect: false };

    // (a) Tren total penjualan: satu seri, judul kartu sudah menamainya
    render('sales', {
        type: 'line',
        data: { labels: sales.labels, datasets: [line('Nilai penjualan', sales.total, PALETTE[0], true)] },
        options: {
            interaction: hoverIndex,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ' ' + rp(ctx.parsed.y),
                        afterLabel: (ctx) => ` ${sales.codCount[ctx.dataIndex]} COD · ${sales.kirimCount[ctx.dataIndex]} Kirim`,
                    },
                },
            },
            scales: { x: categoryAxis, y: valueAxis(true) },
        },
    });

    // (b) Pendapatan per sumber: batang bertumpuk, garis putih memisahkan segmen
    render('revenue', {
        type: 'bar',
        data: {
            labels: revenue.labels,
            datasets: Object.entries(revenue.series).map(([label, values], i) => ({
                label, data: values, backgroundColor: PALETTE[i], borderColor: '#ffffff', borderWidth: 1, borderRadius: 3, maxBarThickness: 28,
            })),
        },
        options: {
            interaction: hoverIndex,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${rp(ctx.parsed.y)}`,
                        footer: (items) => 'Total: ' + rp(items.reduce((s, i) => s + i.parsed.y, 0)),
                    },
                },
            },
            scales: { x: { ...categoryAxis, stacked: true }, y: { ...valueAxis(true), stacked: true } },
        },
    });

    // (c) Penjualan per kategori: donat, maksimal 6 irisan (sisanya "Lainnya" dari server)
    render('categories', {
        type: 'doughnut',
        data: {
            labels: categories.labels,
            datasets: [{ data: categories.values, backgroundColor: PALETTE.slice(0, categories.values.length), borderColor: '#ffffff', borderWidth: 2 }],
        },
        options: {
            cutout: '62%',
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                            const pct = (ctx.parsed / total * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 });
                            return ` ${ctx.label}: ${rp(ctx.parsed)} (${pct}%) · ${categories.counts[ctx.dataIndex]} transaksi`;
                        },
                    },
                },
            },
        },
    });

    // (d) Tren COD vs Kirim (jumlah transaksi selesai)
    render('channels', {
        type: 'line',
        data: { labels: channels.labels, datasets: [line('COD', channels.cod, PALETTE[0]), line('Kirim', channels.kirim, PALETTE[1])] },
        options: {
            interaction: hoverIndex,
            plugins: { tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${number.format(ctx.parsed.y)} transaksi` } } },
            scales: { x: categoryAxis, y: valueAxis(false) },
        },
    });

    // (e) 10 penjual teratas: batang horizontal, label memuat rating bila ada ulasan
    render('sellers', {
        type: 'bar',
        data: {
            // Nama toko dipendekkan, rating bintang selalu terlihat
            labels: sellers.rows.map((s) => {
                const max = (document.getElementById('chart-sellers')?.parentElement.clientWidth ?? 600) < 480 ? 12 : 20;
                const name = s.name.length > max ? s.name.slice(0, max - 1) + '…' : s.name;
                return s.rating === null ? name : `${name} ★${s.rating.toLocaleString('id-ID', { minimumFractionDigits: 1 })}`;
            }),
            datasets: [{ label: 'Nilai penjualan', data: sellers.values, backgroundColor: PALETTE[0], borderRadius: 4, maxBarThickness: 22 }],
        },
        options: {
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => sellers.rows[items[0].dataIndex].name,
                        label: (ctx) => ' ' + rp(ctx.parsed.x),
                        afterLabel: (ctx) => {
                            const s = sellers.rows[ctx.dataIndex];
                            return ` ${s.count} transaksi` + (s.rating === null ? '' : ` · ★${s.rating.toLocaleString('id-ID', { minimumFractionDigits: 1 })} (${s.reviews} ulasan)`);
                        },
                    },
                },
            },
            scales: {
                x: { ...valueAxis(true), ticks: { ...valueTicks(true), maxTicksLimit: 4 } },
                y: {
                    grid: { display: false },
                    ticks: { autoSkip: false },
                },
            },
        },
    });

    // (h) Pertumbuhan pengguna: pendaftar baru per periode
    render('users', {
        type: 'line',
        data: { labels: users.labels, datasets: [line('Pembeli baru', users.buyers, PALETTE[0]), line('Penjual baru', users.sellers, PALETTE[1])] },
        options: {
            interaction: hoverIndex,
            plugins: { tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${number.format(ctx.parsed.y)}` } } },
            scales: { x: categoryAxis, y: valueAxis(false) },
        },
    });
}
