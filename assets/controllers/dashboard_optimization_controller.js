import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['alert', 'alertMessage', 'raceSelect', 'optimizeBtn'];
    static values = {
        confirm: String
    };

    async optimize(e) {
        e.preventDefault();

        const confirmMsg = this.confirmValue || "Are you sure you want to change the race optimization? This scheduled change is not immediate and will apply next Sunday at 09:30.";
        if (!confirm(confirmMsg)) {
            return;
        }

        const selectedRace = this.raceSelectTarget.value;

        this.optimizeBtnTarget.disabled = true;
        this.optimizeBtnTarget.textContent = 'Saving...';

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
                throw new Error(result.error || 'Failed to update optimization.');
            }

            this.showAlert('success', 'Optimization change scheduled successfully. It will apply next Sunday at 09:30.');
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);

        } catch (error) {
            this.showAlert('error', error.message);
            this.optimizeBtnTarget.disabled = false;
            this.optimizeBtnTarget.textContent = 'Save Optimization';
        }
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        this.alertMessageTarget.textContent = message;
        this.alertTarget.className = 'rounded-lg px-4 py-3 text-xs flex items-center justify-between border ';

        if (type === 'success') {
            this.alertTarget.classList.add('bg-green-950/40', 'text-green-300', 'border-green-900/50');
        } else {
            this.alertTarget.classList.add('bg-red-950/40', 'text-red-300', 'border-red-900/50');
        }
        this.alertTarget.classList.remove('hidden');
    }

    closeAlert(e) {
        e.preventDefault();
        if (this.hasAlertTarget) {
            this.alertTarget.classList.add('hidden');
        }
    }
}
