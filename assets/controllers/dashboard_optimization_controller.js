import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = ['alert', 'alertMessage', 'raceSelect', 'optimizeBtn'];
    static values = {
        confirm: String,
        textSaving: String,
        errorSave: String,
        successSave: String
    };

    async optimize(e) {
        e.preventDefault();

        const confirmMsg = this.confirmValue || "Are you sure you want to change the race optimization? This scheduled change is not immediate and will apply next Sunday at 09:30.";
        if (!confirm(confirmMsg)) {
            return;
        }

        const selectedRace = this.raceSelectTarget.value;

        this.optimizeBtnTarget.disabled = true;
        const originalText = this.optimizeBtnTarget.textContent;
        this.optimizeBtnTarget.textContent = this.textSavingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/api/v1/hq/optimize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ race: selectedRace })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorSaveValue);
            }

            this.showAlert('success', this.successSaveValue);
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);

        } catch (error) {
            this.showAlert('error', error.message);
            this.optimizeBtnTarget.disabled = false;
            this.optimizeBtnTarget.textContent = originalText;
        }
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    closeAlert(e) {
        e.preventDefault();
        if (this.hasAlertTarget) {
            hideAlert(this.alertTarget);
        }
    }
}
