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
        this._returnFocusTo = document.activeElement;
        this._show('login');
    }

    openRegister(e) {
        e.preventDefault();
        this._returnFocusTo = document.activeElement;
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

        // §9.5 — focus first interactive element inside the visible panel
        const activePanel = panel === 'register' ? this.registerPanelTarget : this.loginPanelTarget;
        const focusable = activePanel.querySelector(
            'input:not([type="hidden"]), button, [href], select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        focusable?.focus();
    }

    _hide() {
        this.overlayTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this._escHandler);
        // §9.5 — restore focus to the element that triggered the modal
        this._returnFocusTo?.focus();
        this._returnFocusTo = null;
    }

    _onEsc(e) {
        if (e.key === 'Escape') this._hide();
    }

    togglePassword(e) {
        e.preventDefault();
        const inputId = e.currentTarget.dataset.inputId;
        const input = document.getElementById(inputId);
        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        const eyeOpen = e.currentTarget.querySelector('.eye-open');
        const eyeClosed = e.currentTarget.querySelector('.eye-closed');
        if (eyeOpen && eyeClosed) {
            if (isPassword) {
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
    }

    syncKingdomSelect(e) {
        const val = e.target.value;
        const radios = this.element.querySelectorAll('input[type="radio"][name="kingdom_id"]');
        radios.forEach(radio => {
            radio.checked = (radio.value === val);

            const label = radio.closest('label');
            if (label) {
                if (radio.checked) {
                    label.classList.add('border-brand-400');
                } else {
                    label.classList.remove('border-brand-400');
                }
            }
        });
    }

    syncKingdomRadio(e) {
        const val = e.target.value;
        const select = this.element.querySelector('select[name="kingdom_id"]');
        if (select) {
            select.value = val;
        }

        const radios = this.element.querySelectorAll('input[type="radio"][name="kingdom_id"]');
        radios.forEach(radio => {
            const label = radio.closest('label');
            if (label) {
                if (radio.checked) {
                    label.classList.add('border-brand-400');
                } else {
                    label.classList.remove('border-brand-400');
                }
            }
        });
    }
}
