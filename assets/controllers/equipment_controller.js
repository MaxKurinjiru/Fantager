import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { showConfirm } from '../utils/confirm.js';

export default class extends Controller {
    static targets = [
        'heroSelect',
        'paperdoll',
        'itemList',
        'alert',
        'alertMessage'
    ];

    static values = {
        textEquipping: String,
        errorEquip: String,
        successEquip: String,
        textUnequipping: String,
        errorUnequip: String,
        successUnequip: String,
        confirmDismantle: String,
        titleDismantle: String,
        textDismantling: String,
        errorDismantle: String,
        successDismantle: String,
        textRepairing: String,
        errorRepair: String,
        successRepair: String
    };

    connect() {
        if (this.hasHeroSelectTarget && this.heroSelectTarget.value) {
            this.loadHeroEquipment();
        }
    }

    async loadHeroEquipment() {
        const heroId = this.heroSelectTarget.value;
        if (!heroId) {
            window.location.search = '';
            return;
        }

        // Redirect to URL with query param to let Twig render paperdoll and filter list correctly
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('hero_id') !== heroId) {
            urlParams.set('hero_id', heroId);
            window.location.search = urlParams.toString();
        }
    }

    async equip(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;
        const slot = btn.dataset.slot;
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !itemId || !slot) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textEquippingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/heroes/${heroId}/equipment`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ item_id: parseInt(itemId, 10), slot: slot })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorEquipValue);
            }

            this.showAlert('success', this.successEquipValue);
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async unequip(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !itemId) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textUnequippingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/heroes/${heroId}/equipment`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ item_id: parseInt(itemId, 10), slot: null })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorUnequipValue);
            }

            this.showAlert('success', this.successUnequipValue);
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async dismantle(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;

        if (!await showConfirm(
            this.titleDismantleValue || 'Dismantle Item',
            this.confirmDismantleValue,
            this.titleDismantleValue,
            null, // Default to Cancel
            'danger'
        )) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textDismantlingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/api/v1/items/dismantle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ item_id: parseInt(itemId, 10) })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorDismantleValue);
            }

            const successMsg = this.successDismantleValue.replace('%essence%', result.essence_gained || 5);
            this.showAlert('success', successMsg);
            setTimeout(() => window.location.reload(), 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async repair(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textRepairingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/items/${itemId}/repair`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorRepairValue);
            }

            const successMsg = this.successRepairValue.replace('%gold%', result.gold_spent || 0);
            this.showAlert('success', successMsg);
            setTimeout(() => window.location.reload(), 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    hideAlert() {
        if (this.hasAlertTarget) {
            hideAlert(this.alertTarget);
        }
    }
}
