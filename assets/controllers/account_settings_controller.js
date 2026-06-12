import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'emailInput',
        'emailError',
        'emailSuccess',
        'emailSubmitBtn',
        'cancelConfirmPanel',
        'cancelSubmitBtn',
        'cancelSuccess',
        'cancelError'
    ];

    static values = {
        textProcessing: String,
        errorEmail: String,
        errorCancel: String
    };

    connect() {
        this.clearFeedback();
    }

    clearFeedback() {
        if (this.hasEmailErrorTarget) this.emailErrorTarget.classList.add('hidden');
        if (this.hasEmailSuccessTarget) this.emailSuccessTarget.classList.add('hidden');
        if (this.hasCancelConfirmPanelTarget) this.cancelConfirmPanelTarget.classList.add('hidden');
        if (this.hasCancelSuccessTarget) this.cancelSuccessTarget.classList.add('hidden');
        if (this.hasCancelErrorTarget) this.cancelErrorTarget.classList.add('hidden');
    }

    async changeEmail(e) {
        e.preventDefault();
        this.clearFeedback();

        const newEmail = this.emailInputTarget.value.trim();
        if (!newEmail) return;

        this.emailSubmitBtnTarget.disabled = true;
        const originalText = this.emailSubmitBtnTarget.textContent;
        this.emailSubmitBtnTarget.textContent = this.textProcessingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/app/settings/change-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ email: newEmail })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorEmailValue);
            }

            this.emailSuccessTarget.textContent = result.message;
            this.emailSuccessTarget.classList.remove('hidden');
            this.emailInputTarget.value = '';

        } catch (error) {
            this.emailErrorTarget.textContent = error.message;
            this.emailErrorTarget.classList.remove('hidden');
        } finally {
            this.emailSubmitBtnTarget.disabled = false;
            this.emailSubmitBtnTarget.textContent = originalText;
        }
    }

    showCancelConfirm(e) {
        e.preventDefault();
        this.clearFeedback();
        if (this.hasCancelConfirmPanelTarget) {
            this.cancelConfirmPanelTarget.classList.remove('hidden');
        }
    }

    hideCancelConfirm(e) {
        e.preventDefault();
        if (this.hasCancelConfirmPanelTarget) {
            this.cancelConfirmPanelTarget.classList.add('hidden');
        }
    }

    async submitCancelAccount(e) {
        e.preventDefault();
        this.clearFeedback();

        this.cancelSubmitBtnTarget.disabled = true;
        const originalText = this.cancelSubmitBtnTarget.textContent;
        this.cancelSubmitBtnTarget.textContent = this.textProcessingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/app/settings/cancel-account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorCancelValue);
            }

            this.cancelSuccessTarget.textContent = result.message;
            this.cancelSuccessTarget.classList.remove('hidden');
            if (this.hasCancelConfirmPanelTarget) {
                this.cancelConfirmPanelTarget.classList.add('hidden');
            }

        } catch (error) {
            this.cancelErrorTarget.textContent = error.message;
            this.cancelErrorTarget.classList.remove('hidden');
            this.cancelSubmitBtnTarget.disabled = false;
            this.cancelSubmitBtnTarget.textContent = originalText;
        }
    }
}
