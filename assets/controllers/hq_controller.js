import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = ['totalLevel', 'alert', 'alertMessage', 'raceSelect', 'optimizeBtn'];

    static values = {
        textUpgrading: String,
        errorUpgrade: String,
        successUpgrade: String,
        textSaving: String,
        errorOptimize: String,
        successOptimize: String,
        levelLabel: String,
        goldFormat: String,
        bonuses: Object
    };

    async upgrade(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const facilityType = btn.dataset.facility;

        // Disable all upgrade buttons to prevent double-clicks
        this.setButtonsDisabled(true);
        const originalText = btn.textContent;
        btn.textContent = this.textUpgradingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/api/v1/hq/upgrade', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ facility: facilityType })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorUpgradeValue);
            }

            // Update Level & Cost text on target elements
            const card = btn.closest('[data-facility-card]');
            if (card) {
                const levelElem = card.querySelector('[data-facility-level]');
                const costElem = card.querySelector('[data-facility-cost]');
                
                if (levelElem) {
                    levelElem.textContent = `${this.levelLabelValue} ${result.level}`;
                }
                
                if (costElem) {
                    const formattedGold = result.upgrade_cost.toLocaleString('cs-CZ');
                    costElem.textContent = this.goldFormatValue.replace('%gold%', formattedGold);
                    btn.dataset.cost = result.upgrade_cost;
                }

                // Dynamically update the displayed passive bonuses
                const bonusesContainer = card.querySelector('[data-facility-bonuses]');
                if (bonusesContainer && result.passive_bonuses) {
                    bonusesContainer.innerHTML = '';
                    Object.entries(result.passive_bonuses).forEach(([key, value]) => {
                        const li = document.createElement('li');
                        li.className = 'flex justify-between text-xs text-gray-400';
                        
                        // Human readable bonus names
                        const cleanKey = (this.hasBonusesValue && this.bonusesValue[key]) || key.replace(/_/g, ' ').replace('pct', '%');
                        li.innerHTML = `
                            <span class="capitalize">${cleanKey}:</span>
                            <span class="text-emerald-450 font-bold">+${value}%</span>
                        `;
                        bonusesContainer.appendChild(li);
                    });
                }
            }

            // Fetch current wallet gold from header resource bar
            const headerGold = document.querySelector('[title="Gold"] span:nth-child(2)');
            if (headerGold) {
                let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
                const spent = parseInt(btn.dataset.costOriginal || btn.dataset.cost || '0', 10);
                if (!isNaN(gold)) {
                    headerGold.textContent = (gold - spent).toLocaleString('cs-CZ');
                }
            }

            // Update Total level in header
            if (this.hasTotalLevelTarget) {
                const currentTotal = parseInt(this.totalLevelTarget.textContent, 10);
                this.totalLevelTarget.textContent = (currentTotal + 1).toString();
            }

            const successMsg = this.successUpgradeValue.replace('%level%', result.level);
            this.showAlert('success', successMsg);
            
            // Reload page after a brief delay to refresh state and ensure clean calculation/synergy
            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            this.setButtonsDisabled(false);
            btn.textContent = originalText;
        }
    }

    async optimize(e) {
        e.preventDefault();
        const selectedRace = this.raceSelectTarget.value;

        this.optimizeBtnTarget.disabled = true;
        const originalText = this.optimizeBtnTarget.textContent;
        this.optimizeBtnTarget.textContent = this.textSavingValue;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch('/api/v1/hq/optimize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ race: selectedRace })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorOptimizeValue);
            }

            this.showAlert('success', this.successOptimizeValue);
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            this.optimizeBtnTarget.disabled = false;
            this.optimizeBtnTarget.textContent = originalText;
        }
    }

    setButtonsDisabled(disabled) {
        this.element.querySelectorAll('[data-action="click->hq#upgrade"]').forEach(b => {
            b.disabled = disabled;
        });
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
