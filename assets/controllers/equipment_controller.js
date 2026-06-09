import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'heroSelect',
        'paperdoll',
        'itemList',
        'alert',
        'alertMessage'
    ];

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
        btn.textContent = 'Equipping...';

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
                throw new Error(result.error || 'Failed to equip item.');
            }

            this.showAlert('success', 'Item equipped successfully.');
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = 'Equip';
        }
    }

    async unequip(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !itemId) return;

        btn.disabled = true;
        btn.textContent = 'Unequipping...';

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
                throw new Error(result.error || 'Failed to unequip item.');
            }

            this.showAlert('success', 'Item unequipped.');
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = '✕';
        }
    }

    async dismantle(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;

        if (!confirm('Are you sure you want to dismantle this item? It will be permanently destroyed.')) return;

        btn.disabled = true;
        btn.textContent = 'Dismantling...';

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
                throw new Error(result.error || 'Failed to dismantle item.');
            }

            this.showAlert('success', `Item dismantled! Gained ${result.essence_gained || 5} essence.`);
            setTimeout(() => window.location.reload(), 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = 'Dismantle';
        }
    }

    async repair(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const itemId = btn.dataset.itemId;

        btn.disabled = true;
        btn.textContent = 'Repairing...';

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
                throw new Error(result.error || 'Failed to repair item.');
            }

            this.showAlert('success', `Item repaired! Cost: 🪙 ${result.gold_spent || 0}.`);
            setTimeout(() => window.location.reload(), 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = 'Repair';
        }
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        this.alertMessageTarget.textContent = message;
        this.alertTarget.className = 'mb-6 rounded-lg px-4 py-3 text-sm flex items-center justify-between border ';

        if (type === 'success') {
            this.alertTarget.classList.add('bg-green-950/40', 'text-green-300', 'border-green-900/50');
        } else {
            this.alertTarget.classList.add('bg-red-950/40', 'text-red-300', 'border-red-900/50');
        }
        this.alertTarget.classList.remove('hidden');
    }

    hideAlert() {
        if (this.hasAlertTarget) {
            this.alertTarget.classList.add('hidden');
        }
    }
}
