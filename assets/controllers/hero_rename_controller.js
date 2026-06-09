import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['nameDisplay', 'nameInput', 'editBtn', 'actions', 'alert', 'alertMessage'];
    static values = { heroId: Number };

    connect() {
        this.originalName = this.nameDisplayTarget.textContent.trim();
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
        const newName = this.nameInputTarget.value.trim();
        if (!newName) {
            this.showAlert('Name cannot be empty.');
            return;
        }

        if (newName === this.originalName) {
            this.hideEditMode();
            return;
        }

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/heroes/${this.heroIdValue}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ name: newName })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || 'Failed to rename hero.');
            }

            this.originalName = result.name;
            this.nameDisplayTarget.textContent = result.name;
            this.hideEditMode();
            this.hideAlert();
        } catch (error) {
            this.showAlert(error.message);
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
