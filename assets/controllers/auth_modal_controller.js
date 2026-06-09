import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'loginPanel', 'registerPanel'];
    static values  = { open: String };

    connect() {
        this._escHandler = this._onEsc.bind(this);
        if (this.openValue) {
            this._show(this.openValue);
        }
    }

    disconnect() {
        document.removeEventListener('keydown', this._escHandler);
    }

    openLogin(e) {
        e.preventDefault();
        this._show('login');
    }

    openRegister(e) {
        e.preventDefault();
        this._show('register');
    }

    switchToLogin(e) {
        e.preventDefault();
        this._show('login');
    }

    switchToRegister(e) {
        e.preventDefault();
        this._show('register');
    }

    closeOverlay(e) {
        // Only close when clicking the backdrop itself, not the modal box
        if (e.target === e.currentTarget) {
            this._hide();
        }
    }

    close(e) {
        e.preventDefault();
        this._hide();
    }

    _show(panel) {
        this.overlayTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        if (panel === 'register') {
            this.registerPanelTarget.classList.remove('hidden');
            this.loginPanelTarget.classList.add('hidden');
        } else {
            this.loginPanelTarget.classList.remove('hidden');
            this.registerPanelTarget.classList.add('hidden');
        }

        document.addEventListener('keydown', this._escHandler);
    }

    _hide() {
        this.overlayTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this._escHandler);
    }

    _onEsc(e) {
        if (e.key === 'Escape') this._hide();
    }
}
