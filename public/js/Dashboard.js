(function () {
    const dashboardData = window.dashboardData || {};
    const ChartLib = window.Chart;
    if (!ChartLib) return;

    function safeArray(value) {
        return Array.isArray(value) ? value : [];
    }

    function renderTrendChart() {
        const canvas = document.getElementById('ordersTrendChart');
        if (!canvas) return;

        const existingChart = ChartLib.getChart(canvas);
        if (existingChart) existingChart.destroy();

        const data = safeArray(dashboardData.trend);
        if (!data.length) return;

        const labels = data.map(item => item.label || '');
        const values = data.map(item => Number(item.total || 0));

        new ChartLib(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Orders',
                    data: values,
                    borderColor: '#1f73be',
                    backgroundColor: 'rgba(31, 115, 190, 0.10)',
                    fill: true,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#1f73be',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    tension: 0.35,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { displayColors: false },
                },
            },
        });
    }

    function renderDonutChart(canvasId, source, labelKey, valueKey) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const existingChart = ChartLib.getChart(canvas);
        if (existingChart) existingChart.destroy();

        const data = safeArray(source);
        if (!data.length) return;

        const labels = data.map(item => item[labelKey] || '');
        const values = data.map(item => Number(item[valueKey] || 0));
        const palette = ['#1f73be', '#2bb673', '#f7b731', '#ef5f5f', '#5dade2', '#8c6dfc'];

        new ChartLib(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map((_, index) => palette[index % palette.length]),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8 },
                    },
                    tooltip: { displayColors: false },
                },
            },
        });
    }

    function renderCharts() {
        renderTrendChart();
        renderDonutChart('orderStatusChart', dashboardData.orderStatus, 'label', 'value');
        renderDonutChart('inventoryStatusChart', dashboardData.inventoryStatus, 'label', 'value');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderCharts, { once: true });
    } else {
        renderCharts();
    }
})();
