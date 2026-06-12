import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'heroSelect',
        'trainerSelect',
        'attrGrid',
        'magicCost',
        'magicBtn',
        'recoveryCost',
        'recoveryBtn',
        'queueList',
        'alert',
        'alertMessage'
    ];

    static values = {
        successQueue: String,
        successCancel: String,
        errorFetch: String,
        errorQueue: String,
        errorCancel: String,
        textQueueing: String,
        textCancelling: String,
        textProcessing: String
    };

    connect() {
        // Initialize intervals for countdowns
        this.timers = [];
        this.startCountdowns();

        // If a hero is pre-selected, trigger loading of options
        if (this.hasHeroSelectTarget && this.heroSelectTarget.value) {
            this.loadHeroOptions();
        }
    }

    disconnect() {
        // Clear all timers
        this.timers.forEach(t => clearInterval(t));
    }

    async loadHeroOptions() {
        const heroId = this.heroSelectTarget.value;
        if (!heroId) {
            this.resetOptions();
            return;
        }

        try {
            const response = await fetch(`/api/v1/training?hero_id=${heroId}`);
            const options = await response.json();

            if (!response.ok || options.error) {
                throw new Error(options.error || this.errorFetchValue);
            }

            this.updateOptionsUI(options);
            this.hideAlert();
        } catch (error) {
            this.showAlert('error', error.message);
        }
    }

    resetOptions() {
        // Disable buttons
        this.element.querySelectorAll('[data-training-btn]').forEach(b => {
            b.disabled = true;
        });
    }

    updateOptionsUI(options) {
        // Map option cost texts
        options.forEach(opt => {
            if (opt.type === 'attribute') {
                const card = this.element.querySelector(`[data-attribute-card="${opt.attribute}"]`);
                if (card) {
                    const costText = card.querySelector('[data-cost-text]');
                    const btn = card.querySelector('[data-action="click->training#startAttributeTraining"]');
                    if (costText) {
                        costText.textContent = `Cost: 🪙 ${opt.gold_cost.toLocaleString('en-US')}`;
                    }
                    if (btn) {
                        btn.disabled = false;
                        btn.dataset.cost = opt.gold_cost;
                    }
                }
            } else if (opt.type === 'magic') {
                if (this.hasMagicCostTarget) {
                    this.magicCostTarget.textContent = `Cost: 🪙 ${opt.gold_cost.toLocaleString('en-US')}`;
                }
                if (this.hasMagicBtnTarget) {
                    this.magicBtnTarget.disabled = false;
                    this.magicBtnTarget.dataset.cost = opt.gold_cost;
                }
            } else if (opt.type === 'form') {
                if (this.hasRecoveryCostTarget) {
                    this.recoveryCostTarget.textContent = `Cost: 🪙 ${opt.gold_cost.toLocaleString('en-US')}`;
                }
                if (this.hasRecoveryBtnTarget) {
                    this.recoveryBtnTarget.disabled = false;
                    this.recoveryBtnTarget.dataset.cost = opt.gold_cost;
                }
            }
        });
    }

    async startAttributeTraining(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const attr = btn.dataset.attribute;
        const heroId = this.heroSelectTarget.value;
        const trainerId = this.hasTrainerSelectTarget && this.trainerSelectTarget.value ? this.trainerSelectTarget.value : null;

        if (!heroId || !attr) return;

        this.submitTrainingJob({
            hero_id: parseInt(heroId, 10),
            type: 'attribute',
            attribute: attr,
            trainer_id: trainerId ? parseInt(trainerId, 10) : null
        }, btn);
    }

    async startMagicTraining(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const heroId = this.heroSelectTarget.value;
        if (!heroId) return;

        this.submitTrainingJob({
            hero_id: parseInt(heroId, 10),
            type: 'magic',
            attribute: null,
            trainer_id: null
        }, btn);
    }

    async startRecoveryTraining(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const heroId = this.heroSelectTarget.value;
        if (!heroId) return;

        this.submitTrainingJob({
            hero_id: parseInt(heroId, 10),
            type: 'form',
            attribute: null,
            trainer_id: null
        }, btn);
    }

    async submitTrainingJob(payload, btn) {
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textQueueingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/api/v1/training-queue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorQueueValue);
            }

            // Deduct gold in header
            const headerGold = document.querySelector('[title="Gold"] span:nth-child(2)');
            if (headerGold) {
                let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
                const spent = parseInt(btn.dataset.cost || '0', 10);
                if (!isNaN(gold)) {
                    headerGold.textContent = (gold - spent).toLocaleString('cs-CZ');
                }
            }

            this.showAlert('success', this.successQueueValue);
            
            // Reload page to refresh queue list and hero statuses
            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async cancelTraining(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const jobId = btn.dataset.jobId;
        const refund = parseInt(btn.dataset.refund || '0', 10);

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textCancellingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/training-queue/${jobId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorCancelValue);
            }

            // Add gold refund in header
            const headerGold = document.querySelector('[title="Gold"] span:nth-child(2)');
            if (headerGold) {
                let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
                if (!isNaN(gold)) {
                    headerGold.textContent = (gold + refund).toLocaleString('cs-CZ');
                }
            }

            this.showAlert('success', this.successCancelValue);

            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    startCountdowns() {
        this.element.querySelectorAll('[data-countdown-target]').forEach(elem => {
            const targetTime = new Date(elem.dataset.countdownTarget).getTime();
            
            const updateTicker = () => {
                const now = new Date().getTime();
                const diff = targetTime - now;

                if (diff <= 0) {
                    elem.textContent = this.textProcessingValue;
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                let display = '';
                if (days > 0) display += `${days}d `;
                display += `${hours}h ${minutes}m ${seconds}s`;
                
                elem.textContent = display;
            };

            updateTicker();
            const interval = setInterval(updateTicker, 1000);
            this.timers.push(interval);
        });
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    hideAlert() {
        if (this.hasAlertTarget) {
            hideAlert(this.alertTarget);
        }
    }
}
