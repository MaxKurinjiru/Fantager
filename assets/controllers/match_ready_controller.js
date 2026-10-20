import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { showConfirm } from '../utils/confirm.js';

export default class extends Controller {
    static targets = ['alert', 'alertMessage', 'fillButton'];

    static values = {
        fixtureId: Number,
        textFilling: String,
        errorFill: String,
        successFill: String,
        confirmTitle: String,
        confirmMessage: String,
        confirmBtn: String,
        cancelBtn: String
    };

    async fill(e) {
        e.preventDefault();
        if (!this.hasFixtureIdValue || !this.fixtureIdValue) {
            return;
        }

        if (this.hasConfirmMessageValue && this.confirmMessageValue) {
            const accepted = await showConfirm(
                this.confirmTitleValue || '',
                this.confirmMessageValue,
                this.confirmBtnValue || null,
                this.cancelBtnValue || null,
                'primary'
            );
            if (!accepted) {
                return;
            }
        }

        const btn = this.hasFillButtonTarget ? this.fillButtonTarget : e.currentTarget;
        const originalText = btn.textContent;
        btn.disabled = true;
        if (this.hasTextFillingValue) {
            btn.textContent = this.textFillingValue;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch(`/api/v1/fixtures/${this.fixtureIdValue}/formation/fill-roster`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();
            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorFillValue);
            }

            this.showAlert('success', this.successFillValue);
            setTimeout(() => window.location.reload(), 600);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    closeAlert() {
        if (!this.hasAlertTarget) {
            return;
        }

        hideAlert(this.alertTarget);
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) {
            return;
        }

        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }
}
