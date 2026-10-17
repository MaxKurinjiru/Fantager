import { Controller } from '@hotwired/stimulus';
import { isCloseModalOnBackdropEnabled } from '../utils/user_preferences.js';
import { registerModalHistory, unregisterModalHistory } from '../utils/modal_history.js';

export default class extends Controller {
    static targets = [
        'overlay',
        'loginPanel',
        'registerPanel',
        'kingdomPicker',
        'kingdomTrigger',
        'kingdomId',
        'kingdomPlaceholder',
        'kingdomSummary',
        'kingdomLanguage',
        'kingdomFilterEmpty',
        'kingdomChoice',
    ];
    static values  = { open: String };

    connect() {
        this._escHandler = this._onEsc.bind(this);
        this._historyEntry = null;
        if (this.openValue) {
            this._show(this.openValue);
        }
    }

    disconnect() {
        document.removeEventListener('keydown', this._escHandler);
        this._clearHistory(false);
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
        if (e.target === e.currentTarget && isCloseModalOnBackdropEnabled()) {
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

        if (!this._historyEntry) {
            this._historyEntry = registerModalHistory((fromPopState) => this._hide(fromPopState));
        }

        const activePanel = panel === 'register' ? this.registerPanelTarget : this.loginPanelTarget;
        const focusable = activePanel.querySelector(
            'input:not([type="hidden"]), button, [href], select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        focusable?.focus();
    }

    _hide(fromPopState = false) {
        if (this.overlayTarget.classList.contains('hidden')) {
            return;
        }

        if (this._isKingdomPickerOpen()) {
            this.closeKingdomPicker(null, fromPopState);
        }

        this.overlayTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this._escHandler);
        this._clearHistory(fromPopState);
        this._returnFocusTo?.focus();
        this._returnFocusTo = null;
    }

    _onEsc(e) {
        if (e.key !== 'Escape') {
            return;
        }

        if (this._isKingdomPickerOpen()) {
            e.preventDefault();
            this.closeKingdomPicker();
            return;
        }

        this._hide();
    }

    openKingdomPicker(event) {
        if (event) {
            event.preventDefault();
        }
        if (!this.hasKingdomPickerTarget || this._isKingdomPickerOpen()) {
            return;
        }

        this.kingdomPickerTarget.classList.remove('hidden');
        this.kingdomPickerTarget.setAttribute('aria-hidden', 'false');
        this._setAuthDialogInert(true);

        if (this.hasKingdomLanguageTarget) {
            this.kingdomLanguageTarget.value = '';
        }
        this.filterKingdoms();

        if (!this._kingdomHistoryEntry) {
            this._kingdomHistoryEntry = registerModalHistory((fromPopState) => this.closeKingdomPicker(null, fromPopState));
        }

        const focusTarget = this.hasKingdomLanguageTarget
            ? this.kingdomLanguageTarget
            : this.kingdomPickerTarget.querySelector('button, [href], input, select, textarea');
        focusTarget?.focus();
    }

    closeKingdomPicker(event, fromPopState = false) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        if (!this._isKingdomPickerOpen()) {
            return;
        }

        this.kingdomPickerTarget.classList.add('hidden');
        this.kingdomPickerTarget.setAttribute('aria-hidden', 'true');
        this._setAuthDialogInert(false);
        this._clearKingdomHistory(fromPopState);
        this.kingdomTriggerTarget?.focus();
    }

    closeKingdomPickerBackdrop(event) {
        if (event.target === event.currentTarget && isCloseModalOnBackdropEnabled()) {
            this.closeKingdomPicker(event);
        }
    }

    filterKingdoms() {
        if (!this.hasKingdomChoiceTarget) {
            return;
        }

        const language = this.hasKingdomLanguageTarget ? this.kingdomLanguageTarget.value : '';
        let visible = 0;

        this.kingdomChoiceTargets.forEach((choice) => {
            const match = language === '' || choice.dataset.kingdomLanguage === language;
            choice.classList.toggle('hidden', !match);
            if (match) {
                visible += 1;
            }
        });

        if (this.hasKingdomFilterEmptyTarget) {
            this.kingdomFilterEmptyTarget.classList.toggle('hidden', visible !== 0);
        }
    }

    selectKingdom(event) {
        event.preventDefault();
        const choice = event.currentTarget;
        if (choice.disabled) {
            return;
        }

        const id = choice.dataset.kingdomId;
        if (!id || !this.hasKingdomIdTarget) {
            return;
        }

        this.kingdomIdTarget.value = id;
        if (this.hasKingdomPlaceholderTarget) {
            this.kingdomPlaceholderTarget.classList.add('hidden');
        }
        this.kingdomSummaryTargets.forEach((summary) => {
            summary.classList.toggle('hidden', summary.dataset.kingdomId !== id);
        });
        this.kingdomChoiceTargets.forEach((option) => {
            const selected = option.dataset.kingdomId === id;
            const card = option.querySelector('.kingdom-card');
            if (!card) {
                return;
            }
            card.classList.toggle('kingdom-card--selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        this.closeKingdomPicker();
    }

    _isKingdomPickerOpen() {
        return this.hasKingdomPickerTarget && !this.kingdomPickerTarget.classList.contains('hidden');
    }

    _setAuthDialogInert(inert) {
        if (!this.hasOverlayTarget) {
            return;
        }

        const dialog = this.overlayTarget.querySelector('.modal-box');
        if (!dialog) {
            return;
        }

        if (inert) {
            dialog.setAttribute('aria-hidden', 'true');
            dialog.setAttribute('inert', '');
            return;
        }

        dialog.removeAttribute('aria-hidden');
        dialog.removeAttribute('inert');
    }

    _clearKingdomHistory(fromPopState) {
        if (!this._kingdomHistoryEntry) {
            return;
        }

        unregisterModalHistory(this._kingdomHistoryEntry, fromPopState);
        this._kingdomHistoryEntry = null;
    }

    _clearHistory(fromPopState) {
        if (!this._historyEntry) {
            return;
        }

        unregisterModalHistory(this._historyEntry, fromPopState);
        this._historyEntry = null;
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

}
