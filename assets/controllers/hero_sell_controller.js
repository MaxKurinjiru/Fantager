import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { showConfirm } from '../utils/confirm.js';
import { formatNumber } from '../utils/locale.js';

export default class extends Controller {
    static targets = ['sellAlert', 'sellAlertMessage', 'modeSelect', 'buyoutContainer', 'priceLabel', 'priceInput', 'buyoutInput', 'suggestedPriceRow', 'suggestedPriceValue'];

    static values = {
        heroId: Number,
        suggestedPrice: { type: Number, default: 0 },
        priceFloor: { type: Number, default: 0 },
        priceCeiling: { type: Number, default: 0 },
        titleSell: String,
        confirmSell: String,
        errorSell: String,
        successSell: String,
        priceLabelBuyNow: String,
        priceLabelAuction: String,
        textLoading: String,
        priceOutOfRange: String,
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

    _pricesInBand(price, buyout) {
        const floor = this.priceFloorValue || 0;
        const ceiling = this.priceCeilingValue || 0;
        if (floor <= 0 || ceiling <= 0) {
            return price > 0;
        }
        if (price < floor || price > ceiling) {
            return false;
        }
        if (buyout !== null && !Number.isNaN(buyout) && (buyout < floor || buyout > ceiling)) {
            return false;
        }

        return true;
    }

    _priceOutOfRangeMessage() {
        const template = this.hasPriceOutOfRangeValue ? this.priceOutOfRangeValue : '';

        return template
            .replace('%min%', formatNumber(this.priceFloorValue))
            .replace('%max%', formatNumber(this.priceCeilingValue));
    }

    async submit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const priceGold = parseInt(formData.get('price'), 10);
        const buyoutRaw = formData.get('buyout');
        const buyoutPriceGold = buyoutRaw ? parseInt(buyoutRaw, 10) : null;
        if (!this._pricesInBand(priceGold, buyoutPriceGold)) {
            this.showAlert('error', this._priceOutOfRangeMessage());
            return;
        }

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

        const payload = {
            type: 'hero',
            entity_id: this.heroIdValue,
            price_gold: priceGold,
            buyout_price_gold: buyoutPriceGold,
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
