import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { formatNumber } from '../utils/locale.js';
import { showConfirm } from '../utils/confirm.js';

export default class extends Controller {
    static targets = ['dismissAlert', 'dismissAlertMessage'];

    static values = {
        heroId: Number,
        titleDismiss: String,
        confirmDismiss: String,
        errorDismiss: String,
        successDismiss: String,
        compensationHint: String,
        textLoading: String,
    };

    async dismiss(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const message = this.hasConfirmDismissValue
            ? this.confirmDismissValue
            : '';

        if (!message) {
            return;
        }

        if (!await showConfirm(
            this.titleDismissValue || 'Dismiss Hero',
            message,
            null, // Default to Confirm
            null, // Default to Cancel
            'danger'
        )) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textLoadingValue;

        try {
            const response = await fetch(`/api/v1/heroes/${this.heroIdValue}/dismiss`, {
                method: 'POST',
                headers: csrfHeaders(),
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorDismissValue);
            }

            const compensation = result.compensation ?? 0;
            const successMsg = this.successDismissValue.replace('%gold%', formatNumber(compensation));
            this.showAlert('success', successMsg);

            setTimeout(() => {
                window.location.href = '/app/heroes';
            }, 1200);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    showAlert(type, message) {
        if (!this.hasDismissAlertTarget || !this.hasDismissAlertMessageTarget) return;
        showAlert(this.dismissAlertTarget, this.dismissAlertMessageTarget, type, message);
    }

    hideAlert(e) {
        e.preventDefault();
        if (this.hasDismissAlertTarget) {
            hideAlert(this.dismissAlertTarget);
        }
    }
}
