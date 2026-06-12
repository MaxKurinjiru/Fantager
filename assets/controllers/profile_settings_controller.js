import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'emblemPreview',
        'colorPrimaryPicker',
        'colorPrimaryText',
        'colorSecondaryPicker',
        'colorSecondaryText',
        'alert',
        'alertMessage',
        'submitBtn'
    ];
    static values = {
        teamId: Number,
        textSaving: String,
        errorSave: String,
        successSave: String
    };

    connect() {
        // Synchronize color pickers with hex text inputs
        if (this.hasColorPrimaryPickerTarget && this.hasColorPrimaryTextTarget) {
            this.colorPrimaryPickerTarget.addEventListener('input', (e) => {
                this.colorPrimaryTextTarget.value = e.target.value.toUpperCase();
            });
            this.colorPrimaryTextTarget.addEventListener('change', (e) => {
                let val = e.target.value;
                if (!val.startsWith('#')) val = '#' + val;
                if (/^#[0-9A-F]{6}$/i.test(val)) {
                    this.colorPrimaryPickerTarget.value = val;
                }
            });
        }

        if (this.hasColorSecondaryPickerTarget && this.hasColorSecondaryTextTarget) {
            this.colorSecondaryPickerTarget.addEventListener('input', (e) => {
                this.colorSecondaryTextTarget.value = e.target.value.toUpperCase();
            });
            this.colorSecondaryTextTarget.addEventListener('change', (e) => {
                let val = e.target.value;
                if (!val.startsWith('#')) val = '#' + val;
                if (/^#[0-9A-F]{6}$/i.test(val)) {
                    this.colorSecondaryPickerTarget.value = val;
                }
            });
        }
    }

    selectEmblem(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const icon = btn.dataset.emblem;
        
        // Update hidden field
        const input = this.element.querySelector('#settings_emblem');
        if (input) {
            input.value = icon;
        }

        // Update preview
        if (this.hasEmblemPreviewTarget) {
            this.emblemPreviewTarget.textContent = icon;
        }

        // Highlight selected — uses .emblem-btn--selected from _buttons.scss
        btn.parentElement.querySelectorAll('button').forEach(b => {
            b.classList.remove('emblem-btn--selected');
        });
        btn.classList.add('emblem-btn--selected');
    }

    async save(e) {
        e.preventDefault();
        
        const form = e.currentTarget;
        const nameInput = form.querySelector('#settings_name');
        const emblemInput = form.querySelector('#settings_emblem');

        if (!nameInput || !emblemInput) return;

        const body = {
            name: nameInput.value,
            emblem: emblemInput.value,
            colors: {
                primary: this.colorPrimaryTextTarget.value,
                secondary: this.colorSecondaryTextTarget.value
            }
        };

        this.submitBtnTarget.disabled = true;
        const originalText = this.submitBtnTarget.textContent;
        this.submitBtnTarget.textContent = this.textSavingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/teams/${this.teamIdValue}/settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(body)
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorSaveValue);
            }

            this.showAlert('success', this.successSaveValue);
            
            // Proactively reload the page after a short delay to refresh layout team name / emblem
            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            this.submitBtnTarget.disabled = false;
            this.submitBtnTarget.textContent = originalText;
        }
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    closeAlert(e) {
        e.preventDefault();
        if (this.hasAlertTarget) {
            hideAlert(this.alertTarget);
        }
    }
}
