import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const css = getComputedStyle(document.documentElement);
const role = (name) => css.getPropertyValue(name).trim();

const canvas = document.getElementById('trendChart');
if (canvas) {
    const rows = JSON.parse(canvas.dataset.trend);
    const series = [
        { label: 'Reported', key: 'reported', color: role('--viz-series-1') },
        { label: 'Closed', key: 'closed', color: role('--viz-series-2') },
    ];

    // Direct value labels on top of each bar (two series → legend + direct labels; text in ink, not series colour).
    const valueLabels = {
        id: 'valueLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            ctx.save();
            ctx.font = '11px system-ui, sans-serif';
            ctx.fillStyle = role('--viz-text-secondary');
            ctx.textAlign = 'center';
            chart.data.datasets.forEach((ds, i) => {
                chart.getDatasetMeta(i).data.forEach((bar, j) => {
                    if (ds.data[j] > 0) ctx.fillText(ds.data[j], bar.x, bar.y - 4);
                });
            });
            ctx.restore();
        },
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.month),
            datasets: series.map((s) => ({
                label: s.label,
                data: rows.map((r) => r[s.key]),
                backgroundColor: s.color,
                borderRadius: { topLeft: 4, topRight: 4 },
                borderSkipped: 'bottom',
                maxBarThickness: 22,
                categoryPercentage: 0.6,
                barPercentage: 0.9, // leaves a small surface gap between the paired bars
            })),
        },
        plugins: [valueLabels],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false, // no motion; also renders correctly on resize/print
            layout: { padding: { top: 16 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, color: role('--viz-text-secondary') } },
                tooltip: { callbacks: { title: (items) => items[0].label } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: role('--viz-text-secondary') } },
                y: { beginAtZero: true, ticks: { precision: 0, color: role('--viz-text-secondary') }, grid: { color: role('--viz-grid') }, border: { display: false } },
            },
        },
    });
}
