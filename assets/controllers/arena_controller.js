import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['slider', 'input', 'elasticityBadge', 'saveBtn', 'statusMessage', 'projectedGold', 'projectionsRow'];
    static values = {
        updateUrl: String,
        currentPrice: Number,
        defaultPrice: { type: Number, default: 5 },
        updatedMessage: String,
        errorMessage: String,
    };

    connect() {
        if (this.hasSliderTarget && this.hasInputTarget) {
            this.syncValues(this.currentPriceValue || 5);
        }
    }

    onSliderChange(event) {
        const val = parseInt(event.target.value, 10) || 5;
        if (this.hasInputTarget) {
            this.inputTarget.value = val;
        }
        this.updateCalculatedDisplay(val);
    }

    onInputChange(event) {
        let val = parseInt(event.target.value, 10) || 5;
        val = Math.max(1, Math.min(50, val));
        if (this.hasSliderTarget) {
            this.sliderTarget.value = val;
        }
        this.updateCalculatedDisplay(val);
    }

    updateCalculatedDisplay(price) {
        const elasticity = Math.max(0.10, Math.min(2.00, Math.pow(5 / price, 0.75)));
        if (this.hasElasticityBadgeTarget) {
            const pct = Math.round(elasticity * 100);
            this.elasticityBadgeTarget.textContent = `${pct}% (${elasticity.toFixed(2)}x)`;
            
            if (elasticity > 1.0) {
                this.elasticityBadgeTarget.className = 'arena-elasticity-badge arena-elasticity-badge--high';
            } else if (elasticity < 0.7) {
                this.elasticityBadgeTarget.className = 'arena-elasticity-badge arena-elasticity-badge--low';
            } else {
                this.elasticityBadgeTarget.className = 'arena-elasticity-badge';
            }
        }

        if (this.hasProjectionsRowTargets) {
            this.projectionsRowTargets.forEach(row => {
                const rowPrice = parseInt(row.dataset.price, 10);
                if (rowPrice === price) {
                    row.classList.add('arena-projections-table__tr--active');
                } else {
                    row.classList.remove('arena-projections-table__tr--active');
                }
            });
        }
    }

    async saveTicketPrice(event) {
        event.preventDefault();
        const price = parseInt(this.hasInputTarget ? this.inputTarget.value : this.sliderTarget.value, 10);
        if (!price || price < 1 || price > 50) return;

        if (this.hasSaveBtnTarget) {
            this.saveBtnTarget.disabled = true;
        }

        try {
            const response = await fetch(this.updateUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ticket_price: price }),
            });

            const data = await response.json();
            if (response.ok && data.success) {
                this.currentPriceValue = price;
                this.showStatus(this.updatedMessageValue || 'Ticket price updated successfully.', 'success');
            } else {
                this.showStatus(data.error || this.errorMessageValue || 'Failed to update ticket price.', 'error');
            }
        } catch (err) {
            this.showStatus(this.errorMessageValue || 'Network error updating ticket price.', 'error');
        } finally {
            if (this.hasSaveBtnTarget) {
                this.saveBtnTarget.disabled = false;
            }
        }
    }

    showStatus(message, type) {
        if (!this.hasStatusMessageTarget) return;
        this.statusMessageTarget.textContent = message;
        this.statusMessageTarget.className = `arena-pricing-status arena-pricing-status--${type}`;
        this.statusMessageTarget.classList.remove('hidden');

        setTimeout(() => {
            if (this.hasStatusMessageTarget) {
                this.statusMessageTarget.classList.add('hidden');
            }
        }, 4000);
    }

    syncValues(val) {
        if (this.hasSliderTarget) this.sliderTarget.value = val;
        if (this.hasInputTarget) this.inputTarget.value = val;
        this.updateCalculatedDisplay(val);
    }
}
