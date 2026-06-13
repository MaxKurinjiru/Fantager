import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'summonBtn',
        'chamberState',
        'portal',
        'reveal',
        'heroName',
        'heroRace',
        'heroLevel',
        'heroStats',
        'errorMessage',
        'errorAlert',
        'summonsUsed',
        'summonsMax',
        'goldDisplay'
    ];

    static values = {
        goldCost: Number,
        errorFailed: String,
        limitReached: String,
        levelLabel: String,
        races: Object
    };

    connect() {
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
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/api/v1/summoning', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
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

        // Map race icons
        const raceIcons = {
            human: '👨', elf: '🧝', dwarf: '🧔', orc: '👹', undead: '💀', giant: '🧱', ent: '🌳', genie: '🧞'
        };
        const icon = raceIcons[hero.race] || '👤';
        this.heroRaceTarget.previousElementSibling.textContent = icon;

        // Fill stats
        const statLabels = {
            str: 'Strength',
            dex: 'Dexterity',
            kon: 'Constitution',
            spd: 'Speed',
            int: 'Intelligence',
            wil: 'Willpower',
            cha: 'Charisma',
            lck: 'Luck'
        };

        this.heroStatsTarget.innerHTML = '';
        Object.entries(statLabels).forEach(([key, label]) => {
            const val = hero.attributes ? hero.attributes[key] : undefined;
            const statDiv = document.createElement('div');
            statDiv.className = 'bg-gray-950 border border-gray-850 p-2.5 rounded-lg text-center';
            const spanKey = document.createElement('span');
            spanKey.className = 'block text-[10px] uppercase font-bold text-gray-500 tracking-wider mb-0.5';
            spanKey.textContent = key.toUpperCase();
            
            const spanVal = document.createElement('span');
            spanVal.className = 'text-sm font-extrabold text-white';
            spanVal.textContent = val !== undefined ? val.toString() : '';
            
            statDiv.appendChild(spanKey);
            statDiv.appendChild(spanVal);
            this.heroStatsTarget.appendChild(statDiv);
        });

        // Set inspect link action
        const inspectLink = this.revealTarget.querySelector('#inspect-summoned');
        if (inspectLink) {
            inspectLink.href = `/app/heroes/${hero.id}`;
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
        const headerGold = document.querySelector('[title="Gold"] span:nth-child(2)');
        if (headerGold) {
            let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
            if (!isNaN(gold)) {
                const cost = this.hasGoldCostValue ? this.goldCostValue : 500;
                headerGold.textContent = (gold - cost).toLocaleString('cs-CZ'); // format with spaces
            }
        }
    }

    showError(message) {
        if (!this.hasErrorAlertTarget || !this.hasErrorMessageTarget) return;
        showAlert(this.errorAlertTarget, this.errorMessageTarget, 'error', message);
    }

    hideError() {
        if (this.hasErrorAlertTarget) {
            hideAlert(this.errorAlertTarget);
        }
    }
}
