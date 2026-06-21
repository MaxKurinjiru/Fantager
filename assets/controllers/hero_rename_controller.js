import { Controller } from '@hotwired/stimulus';
import { csrfHeaders } from '../utils/csrf.js';

export default class extends Controller {
    static targets = ['nameDisplay', 'nameInput', 'editBtn', 'actions', 'alert', 'alertMessage'];
    static values = {
        heroId: Number,
        errorEmpty: String,
        errorRename: String
    };

    connect() {
        this.originalName = this.nameDisplayTarget.textContent.trim();
    }

    disconnect() {
        this.originalName = null;
    }

    edit(e) {
        e.preventDefault();
        this.nameDisplayTarget.classList.add('hidden');
        this.editBtnTarget.classList.add('hidden');
        this.nameInputTarget.classList.remove('hidden');
        this.actionsTarget.classList.remove('hidden');
        this.nameInputTarget.value = this.originalName;
        this.nameInputTarget.focus();
        this.nameInputTarget.select();
    }

    cancel(e) {
        e.preventDefault();
        this.hideEditMode();
    }

    async save(e) {
        e.preventDefault();
        const saveBtn = e.currentTarget;
        const newName = this.nameInputTarget.value.trim();
        if (!newName) {
            this.showAlert(this.errorEmptyValue);
            return;
        }

        if (newName === this.originalName) {
            this.hideEditMode();
            return;
        }

        const originalDisabled = saveBtn.disabled;
        saveBtn.disabled = true;

        try {
            const response = await fetch(`/api/v1/heroes/${this.heroIdValue}`, {
                method: 'PUT',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ name: newName })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorRenameValue);
            }

            this.originalName = result.name;
            this.nameDisplayTarget.textContent = result.name;
            this.hideEditMode();
            this.hideAlert();
        } catch (error) {
            this.showAlert(error.message);
        } finally {
            saveBtn.disabled = originalDisabled;
        }
    }

    hideEditMode() {
        this.nameDisplayTarget.classList.remove('hidden');
        this.editBtnTarget.classList.remove('hidden');
        this.nameInputTarget.classList.add('hidden');
        this.actionsTarget.classList.add('hidden');
    }

    showAlert(message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        this.alertMessageTarget.textContent = message;
        this.alertTarget.classList.remove('hidden');
    }

    hideAlert() {
        if (this.hasAlertTarget) {
            this.alertTarget.classList.add('hidden');
        }
    }
}
