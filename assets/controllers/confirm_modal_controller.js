import { Controller } from '@hotwired/stimulus';
import { isCloseModalOnBackdropEnabled } from '../utils/user_preferences.js';
import { registerModalHistory, unregisterModalHistory } from '../utils/modal_history.js';

export default class extends Controller {
    static targets = ['dialog', 'title', 'message', 'confirmBtn', 'cancelBtn'];

    connect() {
        this.escHandler = this.onEsc.bind(this);
        this._historyEntry = null;
        this.resolvePromise = null;

        this.showHandler = this.show.bind(this);
        window.addEventListener('confirm:show', this.showHandler);
    }

    disconnect() {
        window.removeEventListener('confirm:show', this.showHandler);
        document.removeEventListener('keydown', this.escHandler);
        this._clearHistory(false);
    }

    show(event) {
        const { title, message, confirmText, cancelText, confirmStyle, resolve } = event.detail;
        this.resolvePromise = resolve;

        this.titleTarget.textContent = title;
        this.messageTarget.textContent = message;
        this.confirmBtnTarget.textContent = confirmText || 'Confirm';
        this.cancelBtnTarget.textContent = cancelText || 'Cancel';

        if (confirmStyle === 'danger') {
            this.confirmBtnTarget.classList.remove('btn-primary');
            this.confirmBtnTarget.classList.add('btn-danger');
        } else {
            this.confirmBtnTarget.classList.remove('btn-danger');
            this.confirmBtnTarget.classList.add('btn-primary');
        }

        this.dialogTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', this.escHandler);

        if (!this._historyEntry) {
            this._historyEntry = registerModalHistory((fromPopState) => this.cancel(null, fromPopState));
        }

        this.confirmBtnTarget.focus();
    }

    confirm(event) {
        if (event) event.preventDefault();
        this._close(true);
    }

    cancel(event, fromPopState = false) {
        if (event) event.preventDefault();
        this._close(false, fromPopState);
    }

    closeBackdrop(event) {
        if (event.target === event.currentTarget && isCloseModalOnBackdropEnabled()) {
            this.cancel();
        }
    }

    onEsc(event) {
        if (event.key === 'Escape') {
            this.cancel();
        }
    }

    _close(result, fromPopState = false) {
        if (this.dialogTarget.classList.contains('hidden')) {
            return;
        }

        this.dialogTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this.escHandler);
        this._clearHistory(fromPopState);

        if (this.resolvePromise) {
            this.resolvePromise(result);
            this.resolvePromise = null;
        }
    }

    _clearHistory(fromPopState) {
        if (!this._historyEntry) {
            return;
        }
        unregisterModalHistory(this._historyEntry, fromPopState);
        this._historyEntry = null;
    }
}
