import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { showConfirm } from '../utils/confirm.js';

export default class extends Controller {
    static targets = ['sellAlert', 'sellAlertMessage', 'modeSelect', 'buyoutContainer', 'priceLabel', 'priceInput', 'suggestedPriceRow', 'suggestedPriceValue'];

    static values = {
        heroId: Number,
        suggestedPrice: { type: Number, default: 0 },
        titleSell: String,
        confirmSell: String,
        errorSell: String,
        successSell: String,
        priceLabelBuyNow: String,
        priceLabelAuction: String,
        textLoading: String,
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
                ? this.priceLabelAuctionValue
                : this.priceLabelBuyNowValue;
        }
    }

    applySuggestedPrice(event) {
        event.preventDefault();
        if (!this.hasPriceInputTarget) {
            return;
        }

        const price = this.hasSuggestedPriceValue
            ? this.suggestedPriceValue
            : this._parseSuggestedPriceFromDom();

        if (price > 0) {
            this.priceInputTarget.value = String(price);
        }
    }

    _parseSuggestedPriceFromDom() {
        if (!this.hasSuggestedPriceValueTarget) {
            return 0;
        }

        const raw = this.suggestedPriceValueTarget.textContent.replace(/[^\d]/g, '');
        return parseInt(raw, 10) || 0;
    }

    async submit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');

        const message = this.hasConfirmSellValue
            ? this.confirmSellValue
            : '';

        if (!message) {
            return;
        }

        if (!await showConfirm(
            this.titleSellValue || 'Sell Hero',
            message,
            null, // Default to Confirm
            null // Default to Cancel
        )) {
            return;
        }

        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = this.textLoadingValue;

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
                window.location.href = '/app/marketplace?tab=mylistings';
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
