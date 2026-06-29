import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { formatNumber } from '../utils/locale.js';

export default class extends Controller {
    static targets = [
        'summonBtn',
        'chamberState',
        'portal',
        'reveal',
        'heroName',
        'heroRace',
        'heroLevel',
        'heroTrait',
        'heroStats',
        'errorMessage',
        'errorAlert',
        'summonsUsed',
        'summonsMax',
        'goldDisplay',
        'inspectBtn'
    ];

    static values = {
        goldCost: Number,
        errorFailed: String,
        limitReached: String,
        levelLabel: String,
        races: Object,
        statLabels: Object,
        traits: Object
    };

    connect() {
    }

    disconnect() {
    }

    async summon(e) {
        e.preventDefault();

        this.hideError();
        this.summonBtnTarget.disabled = true;
        this.portalTarget.classList.remove('hidden');
        this.revealTarget.classList.add('hidden');

        // Scroll to portal view smoothly
        this.portalTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });

        try {
            const response = await fetch('/api/v1/summoning', {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({})
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorFailedValue);
            }

            // Simulate a premium portal animation delay
            setTimeout(() => {
                this.displaySummonedHero(result);
            }, 2000);

        } catch (error) {
            this.portalTarget.classList.add('hidden');
            this.showError(error.message);
            this.summonBtnTarget.disabled = false;
        }
    }

    displaySummonedHero(hero) {
        // Hide portal loading
        this.portalTarget.classList.add('hidden');

        // Fill hero details
        this.heroNameTarget.textContent = hero.name;
        const raceName = (this.hasRacesValue && this.racesValue[hero.race]) || (hero.race.charAt(0).toUpperCase() + hero.race.slice(1));
        this.heroRaceTarget.textContent = raceName;
        this.heroLevelTarget.textContent = `${this.levelLabelValue} ${hero.level}`;
        this.renderTraitBadge(this.heroTraitTarget, hero.trait);

        // Map race icons
        const raceIcons = {
            human: '👨', elf: '🧝', dwarf: '🧔', orc: '👹', undead: '💀', giant: '🧱', ent: '🌳', genie: '🧞'
        };
        const icon = raceIcons[hero.race] || '👤';
        this.heroRaceTarget.previousElementSibling.textContent = icon;

        // Fill stats
        const statLabels = this.hasStatLabelsValue ? this.statLabelsValue : {};
        const statTemplate = document.getElementById('template-summoning-stat');

        this.heroStatsTarget.replaceChildren();
        Object.entries(statLabels).forEach(([key, label]) => {
            const val = hero.attributes ? hero.attributes[key] : undefined;
            const statNode = statTemplate.content.cloneNode(true);
            statNode.querySelector('.js-stat-key').textContent = key.toUpperCase();
            statNode.querySelector('.js-stat-label').textContent = label;
            statNode.querySelector('.js-stat-value').textContent = val !== undefined ? val.toString() : '';
            this.heroStatsTarget.appendChild(statNode);
        });

        // Enable inspect action
        if (this.hasInspectBtnTarget) {
            this.inspectBtnTarget.dataset.heroId = String(hero.id);
            this.inspectBtnTarget.disabled = false;
        }

        // Show reveal card with animation
        this.revealTarget.classList.remove('hidden');
        this.revealTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Update summons counter
        if (this.hasSummonsUsedTarget) {
            const currentUsed = parseInt(this.summonsUsedTarget.textContent, 10);
            const newUsed = currentUsed + 1;
            this.summonsUsedTarget.textContent = newUsed.toString();

            const max = parseInt(this.summonsMaxTarget.textContent, 10);
            if (newUsed >= max) {
                this.summonBtnTarget.dataset.available = 'false';
                this.summonBtnTarget.disabled = true;
                this.summonBtnTarget.textContent = this.limitReachedValue;
            } else {
                this.summonBtnTarget.disabled = false;
            }
        }

        // Proactively refresh team wallet if header resource bar is visible
        const headerGold = document.querySelector('[data-resource="gold"] .resource-bar__item-value');
        if (headerGold) {
            let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
            if (!isNaN(gold)) {
                const cost = this.hasGoldCostValue ? this.goldCostValue : 500;
                headerGold.textContent = formatNumber(gold - cost);
            }
        }
    }

    showError(message) {
        if (!this.hasErrorAlertTarget || !this.hasErrorMessageTarget) return;
        showAlert(this.errorAlertTarget, this.errorMessageTarget, 'error', message);
    }

    reloadPage(event) {
        event?.preventDefault();
        window.location.reload();
    }

    inspectHero(event) {
        event?.preventDefault();
        const heroId = this.inspectBtnTarget?.dataset.heroId;
        if (heroId) {
            window.location.href = `/app/heroes/${heroId}`;
        }
    }

    hideError() {
        if (this.hasErrorAlertTarget) {
            hideAlert(this.errorAlertTarget);
        }
    }

    renderTraitBadge(container, traitKey) {
        if (!container) {
            return;
        }

        if (!traitKey || !this.hasTraitsValue || !this.traitsValue[traitKey]) {
            container.classList.add('hidden');
            container.replaceChildren();
            return;
        }

        const trait = this.traitsValue[traitKey];
        container.className = `summoning-reveal-card__trait hero-trait-badge hero-trait-badge--${trait.category}`;
        container.title = trait.desc;
        container.replaceChildren();

        const icon = document.createElement('span');
        icon.className = 'hero-trait-badge__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = trait.icon;

        const label = document.createElement('span');
        label.className = 'hero-trait-badge__label';
        label.textContent = trait.name;

        container.append(icon, label);
        container.classList.remove('hidden');
    }
}
