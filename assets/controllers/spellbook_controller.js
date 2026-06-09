import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'heroSelect',
        'alert',
        'alertMessage'
    ];

    connect() {
        if (this.hasHeroSelectTarget && this.heroSelectTarget.value) {
            // Setup initial view
        }
    }

    changeHero() {
        const heroId = this.heroSelectTarget.value;
        if (!heroId) {
            window.location.search = '';
            return;
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('hero_id') !== heroId) {
            urlParams.set('hero_id', heroId);
            window.location.search = urlParams.toString();
        }
    }

    async learn(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const spellId = btn.dataset.spellId;
        const goldCost = parseInt(btn.dataset.gold || '0', 10);
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !spellId) return;

        btn.disabled = true;
        btn.textContent = 'Learning...';

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/heroes/${heroId}/spells/learn`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ spell_id: parseInt(spellId, 10) })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || 'Failed to learn spell.');
            }

            // Deduct Gold in header
            const headerGold = document.querySelector('[title="Gold"] span:nth-child(2)');
            if (headerGold && goldCost > 0) {
                let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
                if (!isNaN(gold)) {
                    headerGold.textContent = (gold - goldCost).toLocaleString('cs-CZ');
                }
            }

            this.showAlert('success', 'Spell successfully learned!');
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = 'Learn Spell';
        }
    }

    async equip(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const heroSpellId = btn.dataset.heroSpellId;
        const slot = btn.value || btn.dataset.slot;
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !heroSpellId || !slot) return;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/heroes/${heroId}/spells/equip`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ hero_spell_id: parseInt(heroSpellId, 10), slot: parseInt(slot, 10) })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || 'Failed to equip spell.');
            }

            this.showAlert('success', 'Spell equipped to slot.');
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
        }
    }

    async unequip(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const heroSpellId = btn.dataset.heroSpellId;
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !heroSpellId) return;

        btn.disabled = true;
        btn.textContent = '...';

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/heroes/${heroId}/spells/unequip`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ hero_spell_id: parseInt(heroSpellId, 10) })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || 'Failed to unequip spell.');
            }

            this.showAlert('success', 'Spell unequipped from slot.');
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = '✕';
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
