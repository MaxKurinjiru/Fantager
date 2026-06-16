import { Controller } from '@hotwired/stimulus';
import { isCloseModalOnBackdropEnabled } from '../utils/user_preferences.js';
import { registerModalHistory, unregisterModalHistory } from '../utils/modal_history.js';

export default class extends Controller {
    static targets = ['dialog'];
    static values = { eventName: String };

    connect() {
        this.escHandler = this.onEsc.bind(this);
        this._historyEntry = null;

        if (this.hasEventNameValue) {
            this.openHandler = this.open.bind(this);
            window.addEventListener(this.eventNameValue, this.openHandler);
        }

        if (this._usesOverlayAsRoot()) {
            this._backdropClickHandler = (event) => {
                if (event.target === this.element && isCloseModalOnBackdropEnabled()) {
                    this.close();
                }
            };
            this.element.addEventListener('click', this._backdropClickHandler);
        }
    }

    disconnect() {
        document.removeEventListener('keydown', this.escHandler);
        if (this.hasEventNameValue) {
            window.removeEventListener(this.eventNameValue, this.openHandler);
        }
        if (this._backdropClickHandler) {
            this.element.removeEventListener('click', this._backdropClickHandler);
        }
        this._clearHistory(false);
    }

    open(event) {
        if (event) event.preventDefault();
        this._returnFocusTo = document.activeElement;
        this._overlayElement().classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', this.escHandler);

        if (!this._historyEntry) {
            this._historyEntry = registerModalHistory((fromPopState) => this.close(null, fromPopState));
        }

        const focusable = this._overlayElement().querySelector(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        focusable?.focus();
    }

    close(event, fromPopState = false) {
        if (event) event.preventDefault();
        if (this._overlayElement().classList.contains('hidden')) {
            return;
        }

        this._overlayElement().classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this.escHandler);
        this._clearHistory(fromPopState);
        this._returnFocusTo?.focus();
    }

    closeBackdrop(event) {
        if (event.target === event.currentTarget && isCloseModalOnBackdropEnabled()) {
            this.close();
        }
    }

    onEsc(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    _usesOverlayAsRoot() {
        return this.element.classList.contains('modal-overlay')
            && this.hasDialogTarget
            && !this.dialogTarget.classList.contains('modal-overlay');
    }

    _overlayElement() {
        if (this._usesOverlayAsRoot()) {
            return this.element;
        }

        return this.dialogTarget;
    }

    _clearHistory(fromPopState) {
        if (!this._historyEntry) {
            return;
        }

        unregisterModalHistory(this._historyEntry, fromPopState);
        this._historyEntry = null;
    }
}
