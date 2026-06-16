import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';

export default class extends Controller {
    static targets = [
        'lockBtn', 'lockBadge', 'replyInput', 'submitReplyBtn', 'replyFormContainer', 'lockedNotice',
        'alert', 'alertMessage'
    ];

    static values = {
        threadId: Number,
        isLocked: Boolean,
        translations: Object
    };

    hideAlert() {
        hideAlert(this.alertTarget);
    }

    showFeedback(type, message) {
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    async submitReply(e) {
        e.preventDefault();
        const body = this.replyInputTarget.value.trim();
        if (!body) {
            this.showFeedback('warning', this.translationsValue.warning_fill_fields || 'Vyplňte prosím obsah odpovědi.');
            return;
        }

        this.submitReplyBtnTarget.disabled = true;
        this.submitReplyBtnTarget.textContent = this.translationsValue.text_sending || 'Odesílám...';

        try {
            const response = await fetch(`/api/v1/forum/threads/${this.threadIdValue}/posts`, {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_send_reply || 'Nepodařilo se odeslat odpověď.'));
            }

            window.location.reload();
        } catch (err) {
            this.showFeedback('error', err.message);
            this.submitReplyBtnTarget.disabled = false;
            this.submitReplyBtnTarget.textContent = this.translationsValue.post_reply || 'Odeslat odpověď';
        }
    }

    async toggleLock(e) {
        e.preventDefault();
        const newLockState = !this.isLockedValue;
        const btn = this.lockBtnTarget;
        const originalText = btn.textContent;

        btn.disabled = true;

        try {
            const response = await fetch(`/api/v1/forum/threads/${this.threadIdValue}/lock`, {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ lock: newLockState })
            });

            if (!response.ok) {
                throw new Error();
            }

            this.isLockedValue = newLockState;
            this.lockBadgeTarget.classList.toggle('hidden', !newLockState);
            this.replyFormContainerTarget.classList.toggle('hidden', newLockState);
            this.lockedNoticeTarget.classList.toggle('hidden', !newLockState);
            btn.textContent = newLockState
                ? `🔓 ${this.translationsValue.btn_unlock || 'Odemknout'}`
                : `🔒 ${this.translationsValue.btn_lock || 'Zamknout'}`;
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_toggle_lock || 'Nepodařilo se změnit zámek tématu.');
            btn.textContent = originalText;
        } finally {
            btn.disabled = false;
        }
    }
}
