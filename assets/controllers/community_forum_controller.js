import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';

export default class extends Controller {
    static targets = [
        'newThreadModal', 'newThreadCategory', 'newThreadTitle', 'newThreadBody', 'submitNewThreadBtn',
        'alert', 'alertMessage'
    ];

    static values = {
        translations: Object
    };

    hideAlert() {
        hideAlert(this.alertTarget);
    }

    showFeedback(type, message) {
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    openNewThreadModal() {
        this.newThreadTitleTarget.value = '';
        this.newThreadBodyTarget.value = '';
        window.dispatchEvent(new CustomEvent('modal:open-new-thread'));
    }

    async submitNewThread(e) {
        e.preventDefault();
        const category = this.newThreadCategoryTarget.value;
        const title = this.newThreadTitleTarget.value.trim();
        const body = this.newThreadBodyTarget.value.trim();

        if (!title || !body) {
            this.showFeedback('warning', this.translationsValue.warning_fill_fields || 'Vyplňte prosím předmět i obsah zprávy.');
            return;
        }

        this.submitNewThreadBtnTarget.disabled = true;
        this.submitNewThreadBtnTarget.textContent = this.translationsValue.text_sending || 'Odesílám...';

        try {
            const response = await fetch('/api/v1/forum/threads', {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ category, title, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_create_thread || 'Nepodařilo se založit téma.'));
            }

            this.newThreadModalTarget.querySelector('.modal-close').click();
            window.location.href = `/app/community/threads/${data.id}`;
        } catch (err) {
            this.showFeedback('error', err.message);
            this.submitNewThreadBtnTarget.disabled = false;
            this.submitNewThreadBtnTarget.textContent = this.translationsValue.create_thread || 'Vytvořit téma';
        }
    }
}
