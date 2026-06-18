import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { formatNumber } from '../utils/locale.js';

export default class extends Controller {
    static targets = ['totalLevel', 'alert', 'alertMessage', 'raceSelect', 'saveAdaptationBtn'];

    static values = {
        textUpgrading: String,
        textDowngrading: String,
        errorUpgrade: String,
        errorDowngrade: String,
        successUpgrade: String,
        successDowngrade: String,
        confirmDowngrade: String,
        textSaving: String,
        errorAdaptation: String,
        successAdaptation: String,
        levelLabel: String,
        goldFormat: String,
        bonuses: Object,
        activeFacility: String,
    };

    connect() {
        if (this.hasActiveFacilityValue && this.activeFacilityValue) {
            this.openFacilityModal(this.activeFacilityValue);
            this.cleanFacilityUrl();
        }
    }

    disconnect() {
        // No global listeners to clean up; defined for §9.1 compliance
    }

    async upgrade(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const facilityType = btn.dataset.facility;

        // Disable all upgrade buttons to prevent double-clicks
        this.setButtonsDisabled(true);
        const originalText = btn.textContent;
        btn.textContent = this.textUpgradingValue;

        try {
            const response = await fetch('/api/v1/hq/upgrade', {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
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
                    const formattedGold = formatNumber(result.upgrade_cost);
                    costElem.textContent = this.goldFormatValue.replace('%gold%', formattedGold);
                    btn.dataset.cost = result.upgrade_cost;
                }

                // Dynamically update the displayed passive bonuses
                const bonusesContainer = card.querySelector('[data-facility-bonuses]');
                if (bonusesContainer && result.passive_bonuses) {
                    bonusesContainer.replaceChildren();
                    const bonusTemplate = document.getElementById('template-facility-bonus-row');
                    Object.entries(result.passive_bonuses).forEach(([key, value]) => {
                        const bonusNode = bonusTemplate.content.cloneNode(true);
                        const cleanKey = (this.hasBonusesValue && this.bonusesValue[key]) || key.replace(/_/g, ' ').replace('pct', '%');
                        bonusNode.querySelector('.js-bonus-name').textContent = `${cleanKey}:`;
                        bonusNode.querySelector('.js-bonus-value').textContent = `+${value}%`;
                        bonusesContainer.appendChild(bonusNode);
                    });
                }
            }

            // Fetch current wallet gold from header resource bar
            const headerGold = document.querySelector('[data-resource="gold"] .resource-bar__item-value');
            if (headerGold) {
                let gold = parseInt(headerGold.textContent.replace(/\s/g, ''), 10);
                const spent = parseInt(btn.dataset.costOriginal || btn.dataset.cost || '0', 10);
                if (!isNaN(gold)) {
                    headerGold.textContent = formatNumber(gold - spent);
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

    async downgrade(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const facilityType = btn.dataset.facility;
        const refund = btn.dataset.refund || '0';

        if (!window.confirm(this.confirmDowngradeValue.replace('%refund%', formatNumber(refund)))) {
            return;
        }

        this.setButtonsDisabled(true);
        const originalText = btn.textContent;
        btn.textContent = this.textDowngradingValue;

        try {
            const response = await fetch('/api/v1/hq/downgrade', {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ facility: facilityType })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorDowngradeValue);
            }

            const successMsg = this.successDowngradeValue.replace('%level%', result.level);
            this.showAlert('success', successMsg);

            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            this.setButtonsDisabled(false);
            btn.textContent = originalText;
        }
    }

    /** Save a pending arena adaptation change (POST /api/v1/hq/optimize). */
    async saveArenaAdaptation(e) {
        e.preventDefault();
        const selectedRace = this.raceSelectTarget.value;

        this.saveAdaptationBtnTarget.disabled = true;
        const originalText = this.saveAdaptationBtnTarget.textContent;
        this.saveAdaptationBtnTarget.textContent = this.textSavingValue;

        try {
            const response = await fetch('/api/v1/hq/optimize', {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ race: selectedRace })
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorAdaptationValue);
            }

            this.showAlert('success', this.successAdaptationValue);
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            this.saveAdaptationBtnTarget.disabled = false;
            this.saveAdaptationBtnTarget.textContent = originalText;
        }
    }

    setButtonsDisabled(disabled) {
        this.element.querySelectorAll('[data-action="click->hq#upgrade"], [data-action="click->hq#downgrade"]').forEach(b => {
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

    openFacility(e) {
        e.preventDefault();
        const facility = e.params.facility || e.currentTarget.dataset.hqFacilityParam;
        if (!facility) return;
        this.openFacilityModal(facility);
    }

    openFacilityModal(facility) {
        window.dispatchEvent(new Event(`modal:open-hq-${facility}`));
    }

    cleanFacilityUrl() {
        const url = new URL(window.location.href);
        if (!url.searchParams.has('facility')) {
            return;
        }

        url.searchParams.delete('facility');
        window.history.replaceState({}, '', url);
    }
}
