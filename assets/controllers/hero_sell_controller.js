import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';

export default class extends Controller {
    static targets = ['sellAlert', 'sellAlertMessage', 'modeSelect', 'buyoutContainer', 'priceLabel'];

    static values = {
        heroId: Number,
        confirmSell: String,
        errorSell: String,
        successSell: String,
        priceLabelBuyNow: String,
        priceLabelAuction: String,
    };

    connect() {
        this.onModeChange();
    }

    onModeChange() {
        if (!this.hasModeSelectTarget) return;

        const isAuction = this.modeSelectTarget.value === 'auction';
        if (this.hasBuyoutContainerTarget) {
            this.buyoutContainerTarget.classList.toggle('hidden', !isAuction);
        }
        if (this.hasPriceLabelTarget) {
            this.priceLabelTarget.textContent = isAuction
                ? (this.priceLabelAuctionValue || 'Starting bid')
                : (this.priceLabelBuyNowValue || 'Price');
        }
    }

    async submit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');

        const message = this.hasConfirmSellValue
            ? this.confirmSellValue
            : 'List this hero on the marketplace?';

        if (!window.confirm(message)) {
            return;
        }

        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = '…';

        const formData = new FormData(form);
        const payload = {
            type: 'hero',
            entity_id: this.heroIdValue,
            price_gold: parseInt(formData.get('price'), 10),
            buyout_price_gold: formData.get('buyout') ? parseInt(formData.get('buyout'), 10) : null,
            mode: formData.get('mode'),
            duration_days: parseInt(formData.get('duration'), 10),
        };

        try {
            const response = await fetch('/api/v1/marketplace/listings', {
                method: 'POST',
                headers: {
                    ...csrfHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorSellValue);
            }

            this.showAlert('success', this.successSellValue);

            setTimeout(() => {
                window.location.href = '/app/economy?tab=mylistings';
            }, 1200);
        } catch (error) {
            this.showAlert('error', error.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    showAlert(type, message) {
        if (!this.hasSellAlertTarget || !this.hasSellAlertMessageTarget) return;
        showAlert(this.sellAlertTarget, this.sellAlertMessageTarget, type, message);
    }

    hideAlert(e) {
        e.preventDefault();
        if (this.hasSellAlertTarget) {
            hideAlert(this.sellAlertTarget);
        }
    }
}
