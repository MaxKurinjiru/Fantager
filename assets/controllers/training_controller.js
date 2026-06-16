import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'alert',
        'alertMessage'
    ];

    static values = {
        successConfigure: String,
        successAssign: String,
        successUnassign: String,
        errorFetch: String,
        errorQueue: String,
        errorCancel: String,
        textProcessing: String,
        isLocked: Boolean,
        textSaving: String,
        textRemoving: String,
        errorSaveFocus: String,
        errorAssignHero: String,
        errorRemoveHero: String,
        confirmDismiss: String,
        errorDismiss: String,
        successDismiss: String
    };

    connect() {
        this.timers = [];
        this.startCountdowns();
    }

    disconnect() {
        this.timers.forEach(t => clearInterval(t));
    }

    onFocusTypeChange(e) {
        const select = e.currentTarget;
        const trainerId = select.dataset.trainerId;
        const container = this.element.querySelector(`[data-training-attr-select-container="${trainerId}"]`);
        
        if (container) {
            if (select.value === 'attribute') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }
    }

    async saveTrainerFocus(e) {
        e.preventDefault();
        if (this.isLockedValue) return;

        const btn = e.currentTarget;
        const trainerId = btn.dataset.trainerId;
        if (!trainerId) return;

        // Find selects
        const typeSelect = this.element.querySelector(`select[data-trainer-id="${trainerId}"][data-action*="onFocusTypeChange"]`);
        const attrSelect = this.element.querySelector(`[data-training-attr-select-container="${trainerId}"] select`);

        const type = typeSelect ? typeSelect.value : null;
        const attribute = attrSelect ? attrSelect.value : null;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textSavingValue || 'Ukládání...';

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/training/trainers/${trainerId}/configure`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ type, attribute })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorSaveFocusValue);
            }

            this.showAlert('success', this.successConfigureValue);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async assignTrainee(e) {
        e.preventDefault();
        if (this.isLockedValue) return;

        const select = e.currentTarget;
        const trainerId = select.dataset.trainerId;
        const heroId = select.value;

        if (!trainerId || !heroId) return;

        select.disabled = true;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/training/trainers/${trainerId}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ hero_id: parseInt(heroId, 10) })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorAssignHeroValue);
            }

            this.showAlert('success', this.successAssignValue);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            select.disabled = false;
            select.value = '';
        }
    }

    async unassignTrainee(e) {
        e.preventDefault();
        if (this.isLockedValue) return;

        const btn = e.currentTarget;
        const trainerId = btn.dataset.trainerId;
        const heroId = btn.dataset.heroId;

        if (!trainerId || !heroId) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textRemovingValue || 'Odebírání...';

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/training/trainers/${trainerId}/unassign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ hero_id: parseInt(heroId, 10) })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorRemoveHeroValue);
            }

            this.showAlert('success', this.successUnassignValue);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async dismissTrainer(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const trainerId = btn.dataset.trainerId;
        if (!trainerId) return;

        const message = this.hasConfirmDismissValue
            ? this.confirmDismissValue
            : 'Dismiss this trainer?';

        if (!window.confirm(message)) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textProcessingValue || '…';

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/training/trainers/${trainerId}/dismiss`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                },
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorDismissValue);
            }

            const compensation = result.compensation ?? 0;
            const successMsg = (this.successDismissValue || 'Trainer dismissed. Compensation: %gold% gold.')
                .replace('%gold%', compensation.toLocaleString('cs-CZ'));
            this.showAlert('success', successMsg);

            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    startCountdowns() {
        this.element.querySelectorAll('[data-countdown-target]').forEach(elem => {
            const targetTime = new Date(elem.dataset.countdownTarget).getTime();
            
            let interval;
            const updateTicker = () => {
                const now = new Date().getTime();
                const diff = targetTime - now;

                if (diff <= 0) {
                    elem.textContent = this.textProcessingValue || 'Zpracování...';
                    if (interval) {
                        clearInterval(interval);
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
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
            interval = setInterval(updateTicker, 1000);
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
