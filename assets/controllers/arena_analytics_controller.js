import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';
import { formatDateTime } from '../utils/locale';

export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        history: Array,
        labelAttendance: String,
        labelGold: String,
        axisAttendance: String,
        axisGold: String,
        eventName: String,
    };

    connect() {
        this.chart = null;
        this.openHandler = () => {
            requestAnimationFrame(() => this.ensureChart());
        };

        if (this.hasEventNameValue && this.eventNameValue) {
            window.addEventListener(this.eventNameValue, this.openHandler);
        }

        requestAnimationFrame(() => {
            if (this.hasCanvasTarget && this.canvasTarget.offsetParent !== null) {
                this.ensureChart();
            }
        });
    }

    disconnect() {
        if (this.hasEventNameValue && this.eventNameValue) {
            window.removeEventListener(this.eventNameValue, this.openHandler);
        }
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }

    ensureChart() {
        if (!this.hasCanvasTarget || this.historyValue.length === 0) {
            return;
        }

        if (!this.chart) {
            this.initChart();
            return;
        }

        this.chart.resize();
    }

    initChart() {
        const chronological = [...this.historyValue].reverse();
        const labels = chronological.map((row) => formatDateTime(row.created_at, {
            day: 'numeric',
            month: 'short',
        }));
        const muted = this.cssVar('--color-text-muted', '#9ca3af');
        const grid = this.cssVar('--color-border', '#374151');
        const attendanceColor = this.cssVar('--color-brand-400', '#34d399');
        const goldColor = this.cssVar('--color-amber-400', '#fbbf24');

        this.chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: this.labelAttendanceValue,
                        data: chronological.map((row) => row.attendance),
                        borderColor: attendanceColor,
                        backgroundColor: attendanceColor,
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.3,
                        spanGaps: true,
                        yAxisID: 'y',
                    },
                    {
                        label: this.labelGoldValue,
                        data: chronological.map((row) => row.amount),
                        borderColor: goldColor,
                        backgroundColor: goldColor,
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.3,
                        spanGaps: true,
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: muted },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: muted },
                        grid: { color: grid },
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 0,
                        title: {
                            display: true,
                            text: this.axisAttendanceValue,
                            color: muted,
                        },
                        ticks: { color: muted },
                        grid: { color: grid },
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        min: 0,
                        title: {
                            display: true,
                            text: this.axisGoldValue,
                            color: muted,
                        },
                        ticks: { color: muted },
                        grid: { drawOnChartArea: false },
                    },
                },
            },
        });
    }

    cssVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

        return value || fallback;
    }
}
