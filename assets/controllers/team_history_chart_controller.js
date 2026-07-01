import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

export default class extends Controller {
    static targets = ['dialog', 'canvas', 'btn'];
    static values = {
        url: String,
        eventName: String,
        moraleAxis: String,
        fanbaseAxis: String,
        labelMorale: String,
        labelChemistry: String,
        labelReputation: String,
        labelFanbase: String
    };

    connect() {
        this.chart = null;
        this.historyData = null;
        this.activeMetric = null;

        console.log('TeamHistoryChartController connected. EventName:', this.hasEventNameValue ? this.eventNameValue : 'no event name value');

        if (this.hasEventNameValue) {
            this.openHandler = (e) => this.open(e.detail?.metric || 'morale');
            window.addEventListener(this.eventNameValue, this.openHandler);
        }
    }

    disconnect() {
        console.log('TeamHistoryChartController disconnected');
        if (this.hasEventNameValue) {
            window.removeEventListener(this.eventNameValue, this.openHandler);
        }
        if (this.chart) {
            this.chart.destroy();
        }
    }

    async open(initialMetric) {
        console.log('TeamHistoryChartController open() called. Metric:', initialMetric);
        this.dialogTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        if (!this.historyData) {
            console.log('Fetching history data from:', this.urlValue);
            const response = await fetch(this.urlValue);
            this.historyData = await response.json();
            console.log('Fetched data:', this.historyData);
            this.initChart();
        }

        this.highlightMetric(initialMetric);
    }

    close() {
        this.dialogTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    closeBackdrop(e) {
        if (e.target === this.dialogTarget) {
            this.close();
        }
    }

    selectMetric(e) {
        const metric = e.currentTarget.dataset.metric;
        this.highlightMetric(metric);
    }

    initChart() {
        const labels = this.historyData.map(d => d.date);

        this.metricMeta = {
            morale: { label: this.labelMoraleValue || 'Morale', color: 'rgba(16, 185, 129, 1)', key: 'morale', yAxisID: 'y' },
            chemistry: { label: this.labelChemistryValue || 'Chemistry', color: 'rgba(59, 130, 246, 1)', key: 'chemistry', yAxisID: 'y' },
            reputation: { label: this.labelReputationValue || 'Reputation', color: 'rgba(245, 158, 11, 1)', key: 'reputation', yAxisID: 'y' },
            fanBase: { label: this.labelFanbaseValue || 'Fan Club', color: 'rgba(236, 72, 153, 1)', key: 'fanBase', yAxisID: 'y1' }
        };

        const datasets = Object.keys(this.metricMeta).map(key => {
            const meta = this.metricMeta[key];
            return {
                label: meta.label,
                data: this.historyData.map(d => d[meta.key]),
                borderColor: meta.color,
                backgroundColor: meta.color.replace('1)', '0.1)'),
                borderWidth: 2,
                pointRadius: 2,
                tension: 0.3,
                yAxisID: meta.yAxisID,
                originalColor: meta.color,
                id: key
            };
        });

        this.chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 0,
                        max: 100,
                        title: { display: true, text: this.moraleAxisValue || 'Morale / Chemistry / Reputation (0-100)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: this.fanbaseAxisValue || 'Fan Club' }
                    }
                }
            }
        });
    }

    highlightMetric(selectedMetric) {
        this.activeMetric = selectedMetric;

        this.chart.data.datasets.forEach(dataset => {
            if (dataset.id === selectedMetric) {
                dataset.borderColor = dataset.originalColor;
                dataset.borderWidth = 3;
                dataset.pointRadius = 4;
            } else {
                dataset.borderColor = 'rgba(156, 163, 175, 0.18)';
                dataset.borderWidth = 1;
                dataset.pointRadius = 0;
            }
        });

        this.chart.update();

        this.btnTargets.forEach(btn => {
            if (btn.dataset.metric === selectedMetric) {
                btn.classList.add('btn-primary');
                btn.classList.remove('btn-outline');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline');
            }
        });
    }
}
