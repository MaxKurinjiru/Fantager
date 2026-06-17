import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';

export default class extends Controller {
    static targets = [
        'heroSelect',
        'alert',
        'alertMessage'
    ];

    static values = {
        textLearning: String,
        errorLearn: String,
        successLearn: String,
        errorEquip: String,
        successEquip: String,
        errorUnequip: String,
        successUnequip: String,
        textEquipping: String
    };

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
        const originalText = btn.textContent;
        btn.textContent = this.textLearningValue;

        try {
            const response = await fetch(`/api/v1/heroes/${heroId}/spells/learn`, {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ spell_id: parseInt(spellId, 10) })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorLearnValue);
            }

            // Deduct Gold in header
            const headerGold = document.querySelector('[data-resource="gold"] .resource-bar__item-value');
            if (headerGold && goldCost > 0) {
                let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
                if (!isNaN(gold)) {
                    headerGold.textContent = (gold - goldCost).toLocaleString('cs-CZ');
                }
            }

            this.showAlert('success', this.successLearnValue);
            setTimeout(() => window.location.reload(), 800);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async equip(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const heroSpellId = btn.dataset.heroSpellId;
        const slot = btn.value || btn.dataset.slot;
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !heroSpellId || !slot) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textEquippingValue;

        try {
            const response = await fetch(`/api/v1/heroes/${heroId}/spells/equip`, {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ hero_spell_id: parseInt(heroSpellId, 10), slot: parseInt(slot, 10) })
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
        const heroSpellId = btn.dataset.heroSpellId;
        const heroId = this.heroSelectTarget.value;

        if (!heroId || !heroSpellId) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = '...';

        try {
            const response = await fetch(`/api/v1/heroes/${heroId}/spells/unequip`, {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ hero_spell_id: parseInt(heroSpellId, 10) })
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
