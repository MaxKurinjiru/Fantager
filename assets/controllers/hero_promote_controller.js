import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { showConfirm } from '../utils/confirm.js';

export default class extends Controller {
    static targets = ['promoteAlert', 'promoteAlertMessage'];

    static values = {
        heroId: Number,
        titlePromote: String,
        confirmPromote: String,
        errorPromote: String,
        successPromote: String,
        textLoading: String
    };

    async promote(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const message = this.hasConfirmPromoteValue
            ? this.confirmPromoteValue
            : '';

        if (!message) {
            return;
        }

        if (!await showConfirm(
            this.titlePromoteValue || 'Promote Hero',
            message,
            null, // Default to Confirm
            null, // Default to Cancel
            'warning'
        )) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textLoadingValue;

        try {
            const response = await fetch('/api/v1/training/trainers/promote', {
                method: 'POST',
                headers: {
                    ...csrfHeaders(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ hero_id: this.heroIdValue })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorPromoteValue);
            }

            this.showAlert('success', this.successPromoteValue);

            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    showAlert(type, message) {
        if (!this.hasPromoteAlertTarget || !this.hasPromoteAlertMessageTarget) return;
        showAlert(this.promoteAlertTarget, this.promoteAlertMessageTarget, type, message);
    }

    hideAlert(e) {
        e.preventDefault();
        if (this.hasPromoteAlertTarget) {
            hideAlert(this.promoteAlertTarget);
        }
    }
}
