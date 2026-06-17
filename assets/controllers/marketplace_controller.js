import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'tabButton', 'tabContent',
        'browseContainer', 'myListingsContainer', 'historyContainer',
        'browseFilterForm', 'browseTypeInput',
        'raceFilterContainer', 'rarityFilterContainer',
        'sellCatButton', 'sellEntitiesContainer',
        'selectedEntityLabel', 'sellIdInput',
        'listingModeSelect', 'buyoutFieldContainer', 'priceLabel',
        'alert', 'alertMessage'
    ];

    static values = {
        taxRate: Number,
        csrfToken: String,
        currentTeamId: Number,
        races: Object,
        rarities: Object,
        slots: Object,
        statuses: Object,
        translations: Object,
        initialTab: { type: String, default: 'browse' },
        initialBrowseCategory: { type: String, default: 'hero' },
        initialHeroId: { type: Number, default: 0 }
    };

    connect() {
        this.activeTab = this.hasInitialTabValue ? this.initialTabValue : 'browse';
        this.activeBrowseCategory = this.hasInitialBrowseCategoryValue ? this.initialBrowseCategoryValue : 'hero';
        this.activeSellCategory = 'hero';
        this.selectedEntityId = null;

        if (['browse', 'sell', 'mylistings', 'history'].includes(this.activeTab)) {
            this._showTab(this.activeTab);
        }

        if (this.initialHeroIdValue > 0 && this.activeTab === 'sell') {
            this._preselectHero(this.initialHeroIdValue);
        }
    }

    switchTab(event) {
        const button = event.currentTarget;
        const tabName = button.dataset.tab;
        this._showTab(tabName);
    }

    _showTab(tabName) {
        this.activeTab = tabName;

        this.tabButtonTargets.forEach(btn => {
            btn.classList.toggle('tab-btn--active', btn.dataset.tab === tabName);
        });

        this.tabContentTargets.forEach(content => {
            content.classList.toggle('hidden', content.dataset.tabName !== tabName);
        });

        if (tabName === 'browse') {
            this.loadListings({ type: this.activeBrowseCategory });
        } else if (tabName === 'mylistings') {
            this.loadMyListings();
        } else if (tabName === 'history') {
            this.loadHistory();
        }
    }

    _setBrowseCategory(catName) {
        this.activeBrowseCategory = catName;

        if (this.hasBrowseTypeInputTarget) {
            this.browseTypeInputTarget.value = catName;
        }

        if (!this.hasRaceFilterContainerTarget || !this.hasRarityFilterContainerTarget) {
            return;
        }

        if (catName === 'item') {
            this.raceFilterContainerTarget.classList.add('hidden');
            this.rarityFilterContainerTarget.classList.remove('hidden');
        } else {
            this.raceFilterContainerTarget.classList.remove('hidden');
            this.rarityFilterContainerTarget.classList.add('hidden');
        }
    }

    switchSellCategory(event) {
        const button = event.currentTarget;
        const catName = button.dataset.sellCat;
        this.activeSellCategory = catName;

        // Button styles
        this.sellCatButtonTargets.forEach(btn => {
            btn.classList.toggle('segmented-btn--active', btn === button);
        });

        // Containers
        this.sellEntitiesContainerTargets.forEach(container => {
            container.classList.toggle('hidden', container.dataset.sellCatName !== catName);
        });

        // Clear selection
        this.selectedEntityId = null;
        this.sellIdInputTarget.value = '';
        this.selectedEntityLabelTarget.textContent = this.translationsValue.none_selected || '-- Nic --';

        // Unhighlight any selected card
        this.sellEntitiesContainerTargets.forEach(container => {
            container.querySelectorAll('.sell-card').forEach(card => {
                card.classList.remove('sell-card--selected');
            });
        });
    }

    selectEntityToSell(event) {
        const card = event.currentTarget;
        if (card.dataset.locked === '1') {
            const hint = card.getAttribute('title');
            if (hint) {
                this.showAlert('error', hint);
            }
            return;
        }

        const id = card.dataset.entityId;
        const name = card.dataset.entityName;

        this.selectedEntityId = id;
        this.sellIdInputTarget.value = id;
        this.selectedEntityLabelTarget.textContent = name;

        // Highlight selection
        const container = card.closest('[data-marketplace-target="sellEntitiesContainer"]');
        container.querySelectorAll('.sell-card').forEach(c => {
            c.classList.toggle('sell-card--selected', c === card);
        });
    }

    _preselectHero(heroId) {
        const heroContainer = this.sellEntitiesContainerTargets.find(
            c => c.dataset.sellCatName === 'hero'
        );
        if (!heroContainer) return;

        const card = heroContainer.querySelector(`.sell-card[data-entity-id="${heroId}"]`);
        if (!card || card.dataset.locked === '1') return;

        this.selectedEntityId = String(heroId);
        this.sellIdInputTarget.value = String(heroId);
        this.selectedEntityLabelTarget.textContent = card.dataset.entityName || '';

        heroContainer.querySelectorAll('.sell-card').forEach(c => {
            c.classList.toggle('sell-card--selected', c === card);
        });
    }

    onListingModeChange(event) {
        const mode = event.target.value;
        if (mode === 'buy_now') {
            this.buyoutFieldContainerTarget.classList.add('hidden');
            this.priceLabelTarget.textContent = this.translationsValue.label_buyout_price || '';
        } else {
            this.buyoutFieldContainerTarget.classList.remove('hidden');
            this.priceLabelTarget.textContent = this.translationsValue.label_min_bid || '';
        }
    }

    async loadListings(params = {}) {
        this.browseContainerTarget.innerHTML = '';
        const loadingTemplate = document.getElementById('template-loading-grid');
        const loadingNode = loadingTemplate.content.cloneNode(true);
        this.browseContainerTarget.appendChild(loadingNode);

        let url = '/api/v1/marketplace';
        const queryParams = new URLSearchParams(params);
        if (queryParams.toString()) {
            url += '?' + queryParams.toString();
        }

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(this.translationsValue.error_fetch_listings);
            const listings = await response.json();
            this.renderBrowse(listings);
        } catch (error) {
            this.browseContainerTarget.innerHTML = '';
            const errorTemplate = document.getElementById('template-error-grid');
            const errorNode = errorTemplate.content.cloneNode(true);
            const errText = errorNode.querySelector('.js-error-text');
            errText.textContent = this.translationsValue.error_fetch_listings
                .replace('%error%', error.message);
            this.browseContainerTarget.appendChild(errorNode);
        }
    }

    applyFilters(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const params = {};
        for (const [key, val] of formData.entries()) {
            if (val !== '') {
                params[key] = val;
            }
        }
        this.loadListings(params);
    }

    resetFilters(event) {
        const form = event.currentTarget.closest('form');
        form.reset();
        this._setBrowseCategory(this.activeBrowseCategory);
        this.loadListings({ type: this.activeBrowseCategory });
    }

    renderBrowse(listings) {
        if (listings.length === 0) {
            this.browseContainerTarget.innerHTML = '';
            const emptyTemplate = document.getElementById('template-empty-grid');
            const emptyNode = emptyTemplate.content.cloneNode(true);
            emptyNode.querySelector('.js-empty-text').textContent = this.translationsValue.empty_listings || '';
            this.browseContainerTarget.appendChild(emptyNode);
            return;
        }

        const currentTeamId = this.currentTeamIdValue;
        this.browseContainerTarget.innerHTML = ''; // Clear layout

        listings.forEach(listing => {
            let card;
            if (listing.listing_type === 'hero') {
                const template = document.getElementById('template-card-hero');
                card = template.content.cloneNode(true).querySelector('.marketplace-card');
                const hero = listing.entity;

                card.querySelector('.js-level').textContent = `Lvl ${hero.level}`;
                card.querySelector('.js-name').textContent = hero.name;
                card.querySelector('.js-race').textContent = this.racesValue[hero.race] || hero.race;
                card.querySelector('.js-age').textContent = `${hero.age} ${this.translationsValue.years_suffix || ''}`;
                card.querySelector('.js-form').textContent = `${hero.form}%`;
                card.querySelector('.js-fatigue').textContent = `${hero.fatigue}%`;

                card.querySelector('.js-str').textContent = hero.str;
                card.querySelector('.js-dex').textContent = hero.dex;
                card.querySelector('.js-kon').textContent = hero.kon;
                card.querySelector('.js-spd').textContent = hero.spd;

            } else if (listing.listing_type === 'item') {
                const template = document.getElementById('template-card-item');
                card = template.content.cloneNode(true).querySelector('.marketplace-card');
                const item = listing.entity;

                card.classList.add(`marketplace-card--item-${item.rarity}`);

                const rarityTag = card.querySelector('.js-rarity');
                rarityTag.textContent = this.raritiesValue[item.rarity] || item.rarity;
                rarityTag.className = `rarity-tag rarity-tag--${item.rarity}`;

                card.querySelector('.js-name').textContent = item.name;
                card.querySelector('.js-slot').textContent = this.slotsValue[item.slot_type] || item.slot_type;
                card.querySelector('.js-durability').textContent = `${item.durability}%`;

                const bonusesContainer = card.querySelector('.js-bonuses');
                bonusesContainer.innerHTML = '';
                const bonusEntries = Object.entries(item.bonuses);
                if (bonusEntries.length > 0) {
                    const bonusTemplate = document.getElementById('template-item-bonus');
                    bonusEntries.forEach(([stat, val]) => {
                        const bonusNode = bonusTemplate.content.cloneNode(true);
                        bonusNode.querySelector('.js-bonus-text').textContent = `+${val} ${stat.toUpperCase()}`;
                        bonusesContainer.appendChild(bonusNode);
                    });
                } else {
                    const emptyBonusTemplate = document.getElementById('template-item-no-bonuses');
                    const emptyBonusNode = emptyBonusTemplate.content.cloneNode(true);
                    emptyBonusNode.querySelector('.js-no-bonuses-text').textContent = this.translationsValue.label_no_bonuses;
                    bonusesContainer.appendChild(emptyBonusNode);
                }

            } else if (listing.listing_type === 'trainer') {
                const template = document.getElementById('template-card-trainer');
                card = template.content.cloneNode(true).querySelector('.marketplace-card');
                const trainer = listing.entity;

                const ageLabel = this.translationsValue.label_age;
                card.querySelector('.js-age').textContent = `${ageLabel}: ${trainer.age} ${this.translationsValue.years_suffix || ''}`;
                card.querySelector('.js-name').textContent = trainer.name;
                card.querySelector('.js-race').textContent = this.racesValue[trainer.race] || trainer.race;

                card.querySelector('.js-str').textContent = trainer.str;
                card.querySelector('.js-dex').textContent = trainer.dex;
                card.querySelector('.js-kon').textContent = trainer.kon;
                card.querySelector('.js-spd').textContent = trainer.spd;
            }

            const isSeller = listing.seller_team.id === currentTeamId;
            const buyoutPrice = listing.buyout_price_gold;
            const currentHighestBid = listing.highest_bid ? listing.highest_bid.amount : null;

            card.querySelector('.js-seller-name').textContent = listing.seller_team.name;

            const expiresAt = new Date(listing.expires_at);
            card.querySelector('.js-time-remaining').textContent = this.formatTimeRemaining(expiresAt);

            // Buyout Section
            if (buyoutPrice !== null) {
                const buyoutSec = card.querySelector('.js-buyout-section');
                buyoutSec.classList.remove('hidden');
                buyoutSec.querySelector('.js-buyout-price').textContent = buyoutPrice;
            }

            // Bid Section
            if (listing.listing_mode === 'auction') {
                const bidSec = card.querySelector('.js-bid-section');
                bidSec.classList.remove('hidden');
                const highestBidSpan = bidSec.querySelector('.js-highest-bid');
                if (currentHighestBid !== null) {
                    const bidText = (this.translationsValue.highest_bid_format || '🪙 %amount% (%bidder%)')
                        .replace('%amount%', currentHighestBid)
                        .replace('%bidder%', listing.highest_bid.bidder_name);
                    highestBidSpan.textContent = bidText;
                } else {
                    const bidText = (this.translationsValue.no_bids || '🪙 %amount% (starting)')
                        .replace('%amount%', listing.price_gold);
                    highestBidSpan.textContent = bidText;
                }
            }

            // Actions Section
            const actionsSec = card.querySelector('.js-actions-section');
            if (isSeller) {
                const noticeDiv = document.createElement('div');
                noticeDiv.className = 'marketplace-notice';
                noticeDiv.textContent = this.translationsValue.own_listing_hint || '';
                actionsSec.appendChild(noticeDiv);
            } else {
                if (buyoutPrice !== null) {
                    const buyBtn = document.createElement('button');
                    buyBtn.type = 'button';
                    buyBtn.className = 'btn btn-primary w-full';
                    buyBtn.textContent = this.translationsValue.btn_buyout || '';
                    buyBtn.dataset.action = 'click->marketplace#buyoutItem';
                    buyBtn.dataset.id = listing.id;
                    buyBtn.dataset.price = buyoutPrice;
                    actionsSec.appendChild(buyBtn);
                }

                if (listing.listing_mode === 'auction') {
                    const currentHighest = currentHighestBid !== null ? currentHighestBid : listing.price_gold;
                    const minIncrement = Math.ceil((currentHighest * 0.05) / 10) * 10;
                    const minNextBid = currentHighest + minIncrement;

                    const bidFormTemplate = document.getElementById('template-bid-form');
                    const bidFormNode = bidFormTemplate.content.cloneNode(true);

                    const bidInput = bidFormNode.querySelector('.js-bid-input');
                    bidInput.min = minNextBid;
                    bidInput.value = minNextBid;
                    bidInput.id = `bid-input-${listing.id}`;

                    const bidBtn = bidFormNode.querySelector('.js-bid-btn');
                    bidBtn.textContent = this.translationsValue.btn_bid || '';
                    bidBtn.dataset.action = 'click->marketplace#submitBid';
                    bidBtn.dataset.id = listing.id;

                    actionsSec.appendChild(bidFormNode);
                }
            }

            this.browseContainerTarget.appendChild(card);
        });
    }

    async submitListing(event) {
        event.preventDefault();
        if (!this.selectedEntityId) {
            this.showAlert('error', this.translationsValue.select_entity_first);
            return;
        }

        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = this.translationsValue.label_processing;
        }

        const formData = new FormData(form);
        const payload = {
            type: this.activeSellCategory,
            entity_id: parseInt(this.selectedEntityId),
            price_gold: parseInt(formData.get('price')),
            buyout_price_gold: formData.get('buyout') ? parseInt(formData.get('buyout')) : null,
            mode: formData.get('mode'),
            duration_days: parseInt(formData.get('duration'))
        };

        try {
            const response = await fetch('/api/v1/marketplace/listings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.error || this.translationsValue.error_listing);

            this.showAlert('success', this.translationsValue.flash_created);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }

    async buyoutItem(event) {
        const button = event.currentTarget;
        const listingId = button.dataset.id;
        const price = button.dataset.price;

        const confirmMsg = (this.translationsValue.confirm_buyout || 'Opravdu chcete koupit tuto nabídku okamžitě za 🪙 %amount% gold?')
            .replace('%amount%', price);

        if (!confirm(confirmMsg)) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = '...';

        try {
            const response = await fetch('/api/v1/marketplace/purchase', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue
                },
                body: JSON.stringify({ listing_id: parseInt(listingId) })
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.error || this.translationsValue.error_purchase);

            this.showAlert('success', this.translationsValue.flash_purchased);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    async submitBid(event) {
        const button = event.currentTarget;
        const listingId = button.dataset.id;
        const input = document.getElementById(`bid-input-${listingId}`);
        const bidAmount = parseInt(input.value);

        const confirmMsg = (this.translationsValue.confirm_bid || 'Opravdu chcete na tuto aukci přihodit 🪙 %amount% gold (tato částka bude okamžitě rezervována)?')
            .replace('%amount%', bidAmount);

        if (!confirm(confirmMsg)) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = '...';

        try {
            const response = await fetch('/api/v1/marketplace/bid', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfTokenValue
                },
                body: JSON.stringify({
                    listing_id: parseInt(listingId),
                    bid_amount: bidAmount
                })
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.error || this.translationsValue.error_bid);

            this.showAlert('success', this.translationsValue.flash_bid);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    async loadMyListings() {
        this.myListingsContainerTarget.innerHTML = '';
        const loadingTemplate = document.getElementById('template-loading-row');
        this.myListingsContainerTarget.appendChild(loadingTemplate.content.cloneNode(true));

        try {
            const response = await fetch('/api/v1/marketplace/my-listings');
            if (!response.ok) throw new Error(this.translationsValue.error_fetch_listings);
            const listings = await response.json();
            this.renderMyListings(listings);
        } catch (error) {
            this.myListingsContainerTarget.innerHTML = '';
            const errorTemplate = document.getElementById('template-error-row');
            const errorNode = errorTemplate.content.cloneNode(true);
            errorNode.querySelector('.js-error-text').textContent =
                this.translationsValue.error_fetch_my_listings.replace('%error%', error.message);
            this.myListingsContainerTarget.appendChild(errorNode);
        }
    }

    renderMyListings(listings) {
        if (listings.length === 0) {
            this.myListingsContainerTarget.innerHTML = '';
            const emptyTemplate = document.getElementById('template-empty-row');
            const emptyNode = emptyTemplate.content.cloneNode(true);
            emptyNode.querySelector('.js-empty-text').textContent = this.translationsValue.empty_my_listings || '';
            this.myListingsContainerTarget.appendChild(emptyNode);
            return;
        }

        this.myListingsContainerTarget.innerHTML = '';

        listings.forEach(listing => {
            const template = document.getElementById('template-listing-row');
            const row = template.content.cloneNode(true).querySelector('tr');

            let name = listing.entity ? listing.entity.name : this.translationsValue.unknown_entity;
            let category = listing.listing_type === 'hero'
                ? `👥 ${this.translationsValue.type_hero || ''}`
                : (listing.listing_type === 'item' ? `🗡️ ${this.translationsValue.type_item || ''}` : `🏋️ ${this.translationsValue.type_trainer || ''}`);
            let mode = listing.listing_mode === 'auction'
                ? this.translationsValue.mode_auction
                : this.translationsValue.direct_buy;

            row.querySelector('.js-name').textContent = name;
            row.querySelector('.js-type-mode').textContent = `${category} • ${mode}`;
            row.querySelector('.js-price').textContent = `🪙 ${listing.price_gold}`;

            const highestBid = listing.highest_bid
                ? (this.translationsValue.highest_bid_format || '🪙 %amount% (%bidder%)')
                    .replace('%amount%', listing.highest_bid.amount)
                    .replace('%bidder%', listing.highest_bid.bidder_name)
                : (this.translationsValue.no_bids || '🪙 %amount% (starting)')
                    .replace('%amount%', listing.price_gold);
            row.querySelector('.js-highest-bid').textContent = highestBid;

            const expiresAt = new Date(listing.expires_at);
            const timeString = this.formatTimeRemaining(expiresAt);
            row.querySelector('.js-expires').textContent = listing.status === 'active' ? timeString : '--';

            // Status tag
            const statusSpan = row.querySelector('.js-status');
            statusSpan.textContent = this.statusesValue[listing.status] || listing.status;
            statusSpan.className = `status-tag status-tag--${listing.status}`;

            // Cancel action button
            const actionTd = row.querySelector('.js-action');
            if (listing.status === 'active' && !listing.highest_bid) {
                const cancelBtnTemplate = document.getElementById('template-cancel-btn');
                const cancelNode = cancelBtnTemplate.content.cloneNode(true);
                const cancelBtn = cancelNode.querySelector('.js-cancel-btn');
                cancelBtn.textContent = this.translationsValue.btn_cancel || '';
                cancelBtn.dataset.action = 'click->marketplace#cancelListing';
                cancelBtn.dataset.id = listing.id;
                actionTd.appendChild(cancelNode);
            } else if (listing.status === 'active' && listing.highest_bid) {
                const activeSpanTemplate = document.getElementById('template-active-auction-span');
                const activeNode = activeSpanTemplate.content.cloneNode(true);
                activeNode.querySelector('.js-active-span').textContent = this.translationsValue.active_auction || '';
                actionTd.appendChild(activeNode);
            }

            this.myListingsContainerTarget.appendChild(row);
        });
    }

    async cancelListing(event) {
        const button = event.currentTarget;
        const listingId = button.dataset.id;

        const confirmMsg = this.translationsValue.confirm_cancel || 'Opravdu chcete zrušit tuto nabídku a vrátit entitu zpět?';

        if (!confirm(confirmMsg)) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = '...';

        try {
            const response = await fetch(`/api/v1/marketplace/listings/${listingId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': this.csrfTokenValue
                }
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.error || this.translationsValue.error_cancel);

            this.showAlert('success', this.translationsValue.flash_cancelled);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    async loadHistory() {
        this.historyContainerTarget.innerHTML = '';
        const loadingTemplate = document.getElementById('template-loading-row');
        this.historyContainerTarget.appendChild(loadingTemplate.content.cloneNode(true));

        try {
            const response = await fetch('/api/v1/marketplace/history');
            if (!response.ok) throw new Error(this.translationsValue.error_fetch_listings);
            const history = await response.json();
            this.renderHistory(history);
        } catch (error) {
            this.historyContainerTarget.innerHTML = '';
            const errorTemplate = document.getElementById('template-error-row');
            const errorNode = errorTemplate.content.cloneNode(true);
            errorNode.querySelector('.js-error-text').textContent =
                this.translationsValue.error_fetch_my_listings.replace('%error%', error.message);
            this.historyContainerTarget.appendChild(errorNode);
        }
    }

    renderHistory(history) {
        if (history.length === 0) {
            this.historyContainerTarget.innerHTML = '';
            const emptyTemplate = document.getElementById('template-empty-row');
            const emptyNode = emptyTemplate.content.cloneNode(true);
            emptyNode.querySelector('.js-empty-text').textContent = this.translationsValue.empty_history || '';
            this.historyContainerTarget.appendChild(emptyNode);
            return;
        }

        this.historyContainerTarget.innerHTML = '';

        history.forEach(tx => {
            const template = document.getElementById('template-history-row');
            const row = template.content.cloneNode(true).querySelector('tr');

            const date = new Date(tx.created_at);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            let category = tx.listing_type === 'hero'
                ? `👥 ${this.translationsValue.type_hero || ''}`
                : (tx.listing_type === 'item' ? `🗡️ ${this.translationsValue.type_item || ''}` : `🏋️ ${this.translationsValue.type_trainer || ''}`);

            row.querySelector('.js-id').textContent = `#${tx.id}`;
            row.querySelector('.js-name').textContent = tx.entity_name;
            row.querySelector('.js-category').textContent = category;
            row.querySelector('.js-seller').textContent = tx.seller_name;
            row.querySelector('.js-buyer').textContent = tx.buyer_name;
            row.querySelector('.js-amount').textContent = `🪙 ${tx.amount}`;
            row.querySelector('.js-fee').textContent = `🪙 ${tx.fee_amount}`;
            row.querySelector('.js-date').textContent = formattedDate;

            this.historyContainerTarget.appendChild(row);
        });
    }

    formatTimeRemaining(expiresAt) {
        const now = new Date();
        const diffMs = expiresAt - now;
        if (diffMs <= 0) return this.translationsValue.time_expired || 'Vypršelo';

        const diffHrs = Math.floor(diffMs / 3600000);
        if (diffHrs < 24) {
            const mins = Math.floor((diffMs % 3600000) / 60000);
            return (this.translationsValue.time_remaining_hours || '%hours%h %minutes%m zbývá')
                .replace('%hours%', diffHrs)
                .replace('%minutes%', mins);
        }

        const diffDays = Math.floor(diffHrs / 24);
        const remainingHrs = diffHrs % 24;
        return (this.translationsValue.time_remaining || '%days%d %hours%h zbývá')
            .replace('%days%', diffDays)
            .replace('%hours%', remainingHrs);
    }

    showAlert(type, message) {
        if (this.hasAlertTarget && this.hasAlertMessageTarget) {
            showAlert(this.alertTarget, this.alertMessageTarget, type, message);
        }
    }

    hideAlert() {
        if (this.hasAlertTarget) {
            hideAlert(this.alertTarget);
        }
    }
}
