import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'tabButton', 'tabContent', 
        'browseContainer', 'myListingsContainer', 'historyContainer',
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
        translations: Object
    };

    connect() {
        this.activeTab = 'browse';
        this.activeSellCategory = 'hero';
        this.selectedEntityId = null;
        this.loadListings();
    }

    switchTab(event) {
        const button = event.currentTarget;
        const tabName = button.dataset.tab;
        this.activeTab = tabName;

        // Switch active states on buttons
        this.tabButtonTargets.forEach(btn => {
            btn.classList.toggle('tab-btn--active', btn === button);
        });

        // Show/hide contents
        this.tabContentTargets.forEach(content => {
            content.classList.toggle('hidden', content.dataset.tabName !== tabName);
        });

        // Trigger reload
        if (tabName === 'browse') {
            this.loadListings();
        } else if (tabName === 'mylistings') {
            this.loadMyListings();
        } else if (tabName === 'history') {
            this.loadHistory();
        }
    }

    onTypeChange(event) {
        const type = event.target.value;
        if (type === 'item') {
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

    onListingModeChange(event) {
        const mode = event.target.value;
        if (mode === 'buy_now') {
            this.buyoutFieldContainerTarget.classList.add('hidden');
            this.priceLabelTarget.textContent = this.translationsValue.label_buyout_price || 'Pevná cena (Buyout)';
        } else {
            this.buyoutFieldContainerTarget.classList.remove('hidden');
            this.priceLabelTarget.textContent = this.translationsValue.label_min_bid || 'Počáteční příhoz / Min bid';
        }
    }

    async loadListings(params = {}) {
        this.browseContainerTarget.innerHTML = `
            <div class="col-span-3 flex justify-center py-12">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-emerald-500"></div>
            </div>
        `;

        let url = '/api/v1/marketplace';
        const queryParams = new URLSearchParams(params);
        if (queryParams.toString()) {
            url += '?' + queryParams.toString();
        }

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to fetch');
            const listings = await response.json();
            this.renderBrowse(listings);
        } catch (error) {
            this.browseContainerTarget.innerHTML = `<div class="col-span-3 text-center text-red-500 py-8">Chyba při načítání nabídek: ${error.message}</div>`;
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
        this.onTypeChange({ target: { value: '' } });
        this.loadListings();
    }

    renderBrowse(listings) {
        if (listings.length === 0) {
            this.browseContainerTarget.innerHTML = `<div class="col-span-3 text-center text-gray-500 py-12">${this.translationsValue.empty_listings || 'Žádné nabídky neodpovídají filtrům.'}</div>`;
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
                card.querySelector('.js-age').textContent = `${hero.age} ${this.translationsValue.years_suffix || 'let'}`;
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
                    bonusEntries.forEach(([stat, val]) => {
                        const span = document.createElement('span');
                        span.className = 'bg-gray-950 text-gray-300 px-2 py-0.5 rounded border border-gray-850 font-semibold';
                        span.textContent = `+${val} ${stat.toUpperCase()}`;
                        bonusesContainer.appendChild(span);
                    });
                } else {
                    const span = document.createElement('span');
                    span.className = 'text-gray-500 font-medium';
                    span.textContent = this.translationsValue.label_no_bonuses || 'Bez bonusů';
                    bonusesContainer.appendChild(span);
                }

            } else if (listing.listing_type === 'trainer') {
                const template = document.getElementById('template-card-trainer');
                card = template.content.cloneNode(true).querySelector('.marketplace-card');
                const trainer = listing.entity;

                card.querySelector('.js-age').textContent = `Věk: ${trainer.age} ${this.translationsValue.years_suffix || 'let'}`;
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
                    const bidText = (this.translationsValue.no_bids || '🪙 %amount% (počáteční)')
                        .replace('%amount%', listing.price_gold);
                    highestBidSpan.textContent = bidText;
                }
            }

            // Actions Section
            const actionsSec = card.querySelector('.js-actions-section');
            if (isSeller) {
                const noticeDiv = document.createElement('div');
                noticeDiv.className = 'marketplace-notice';
                noticeDiv.textContent = this.translationsValue.own_listing_hint || 'Tato nabídka patří vašemu cechu.';
                actionsSec.appendChild(noticeDiv);
            } else {
                if (buyoutPrice !== null) {
                    const buyBtn = document.createElement('button');
                    buyBtn.type = 'button';
                    buyBtn.className = 'btn btn-primary w-full';
                    buyBtn.textContent = this.translationsValue.btn_buyout || 'Koupit ihned';
                    buyBtn.dataset.action = 'click->marketplace#buyoutItem';
                    buyBtn.dataset.id = listing.id;
                    buyBtn.dataset.price = buyoutPrice;
                    actionsSec.appendChild(buyBtn);
                }

                if (listing.listing_mode === 'auction') {
                    const currentHighest = currentHighestBid !== null ? currentHighestBid : listing.price_gold;
                    const minIncrement = Math.ceil((currentHighest * 0.05) / 10) * 10;
                    const minNextBid = currentHighest + minIncrement;

                    const bidFormDiv = document.createElement('div');
                    bidFormDiv.className = 'flex items-center space-x-2 w-full mt-2 border-t border-gray-800 pt-3';

                    const bidInput = document.createElement('input');
                    bidInput.type = 'number';
                    bidInput.min = minNextBid;
                    bidInput.value = minNextBid;
                    bidInput.id = `bid-input-${listing.id}`;
                    bidInput.className = 'form-input text-center w-24';

                    const bidBtn = document.createElement('button');
                    bidBtn.type = 'button';
                    bidBtn.className = 'btn btn-outline whitespace-nowrap';
                    bidBtn.textContent = this.translationsValue.btn_bid || 'Přihodit';
                    bidBtn.dataset.action = 'click->marketplace#submitBid';
                    bidBtn.dataset.id = listing.id;

                    bidFormDiv.appendChild(bidInput);
                    bidFormDiv.appendChild(bidBtn);
                    actionsSec.appendChild(bidFormDiv);
                }
            }

            this.browseContainerTarget.appendChild(card);
        });
    }

    async submitListing(event) {
        event.preventDefault();
        if (!this.selectedEntityId) {
            this.showAlert('error', this.translationsValue.select_entity_first || 'Nejprve vyberte hrdinu, předmět nebo trenéra k prodeji.');
            return;
        }

        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Zpracování...';
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
            if (!response.ok) throw new Error(result.error || 'Failed to list');

            this.showAlert('success', this.translationsValue.flash_created || 'Nabídka byla úspěšně vytvořena.');
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
            if (!response.ok) throw new Error(result.error || 'Failed to purchase');

            this.showAlert('success', this.translationsValue.flash_purchased || 'Nákup byl úspěšně dokončen!');
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
            if (!response.ok) throw new Error(result.error || 'Failed to place bid');

            this.showAlert('success', this.translationsValue.flash_bid || 'Příhoz byl úspěšně zaznamenán.');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    async loadMyListings() {
        this.myListingsContainerTarget.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto"></div>
                </td>
            </tr>
        `;

        try {
            const response = await fetch('/api/v1/marketplace/my-listings');
            if (!response.ok) throw new Error('Failed to fetch');
            const listings = await response.json();
            this.renderMyListings(listings);
        } catch (error) {
            this.myListingsContainerTarget.innerHTML = `<tr><td colspan="7" class="text-center text-red-500 py-8">Chyba při načítání: ${error.message}</td></tr>`;
        }
    }

    renderMyListings(listings) {
        if (listings.length === 0) {
            this.myListingsContainerTarget.innerHTML = `<tr><td colspan="7" class="text-center text-gray-500 py-8">${this.translationsValue.empty_my_listings || 'Nemáte žádné vystavené nabídky.'}</td></tr>`;
            return;
        }

        this.myListingsContainerTarget.innerHTML = '';

        listings.forEach(listing => {
            const template = document.getElementById('template-listing-row');
            const row = template.content.cloneNode(true).querySelector('tr');

            let name = listing.entity ? listing.entity.name : 'Unknown';
            let category = listing.listing_type === 'hero' ? '👥 Hrdina' : (listing.listing_type === 'item' ? '🗡️ Předmět' : '🏋️ Trenér');
            let mode = listing.listing_mode === 'auction' ? 'Aukce' : 'Direct Buy';

            row.querySelector('.js-name').textContent = name;
            row.querySelector('.js-type-mode').textContent = `${category} • ${mode}`;
            row.querySelector('.js-price').textContent = `🪙 ${listing.price_gold}`;

            const highestBid = listing.highest_bid 
                ? `🪙 ${listing.highest_bid.amount} (${listing.highest_bid.bidder_name})` 
                : (this.translationsValue.no_bids || 'Bez příhozů');
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
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'btn btn-danger btn--sm';
                cancelBtn.textContent = this.translationsValue.btn_cancel || 'Zrušit';
                cancelBtn.dataset.action = 'click->marketplace#cancelListing';
                cancelBtn.dataset.id = listing.id;
                actionTd.appendChild(cancelBtn);
            } else if (listing.status === 'active' && listing.highest_bid) {
                const activeSpan = document.createElement('span');
                activeSpan.className = 'text-[10px] text-gray-550 font-medium';
                activeSpan.textContent = this.translationsValue.active_auction || 'Aktivní aukce';
                actionTd.appendChild(activeSpan);
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
            if (!response.ok) throw new Error(result.error || 'Failed to cancel');

            this.showAlert('success', this.translationsValue.flash_cancelled || 'Nabídka byla úspěšně zrušena.');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    async loadHistory() {
        this.historyContainerTarget.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto"></div>
                </td>
            </tr>
        `;

        try {
            const response = await fetch('/api/v1/marketplace/history');
            if (!response.ok) throw new Error('Failed to fetch');
            const history = await response.json();
            this.renderHistory(history);
        } catch (error) {
            this.historyContainerTarget.innerHTML = `<tr><td colspan="7" class="text-center text-red-500 py-8">Chyba při načítání: ${error.message}</td></tr>`;
        }
    }

    renderHistory(history) {
        if (history.length === 0) {
            this.historyContainerTarget.innerHTML = `<tr><td colspan="7" class="text-center text-gray-500 py-8">${this.translationsValue.empty_history || 'Žádná historie transakcí.'}</td></tr>`;
            return;
        }

        this.historyContainerTarget.innerHTML = '';

        history.forEach(tx => {
            const template = document.getElementById('template-history-row');
            const row = template.content.cloneNode(true).querySelector('tr');

            const date = new Date(tx.created_at);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            let category = tx.listing_type === 'hero' ? '👥 Hrdina' : (tx.listing_type === 'item' ? '🗡️ Předmět' : '🏋️ Trenér');

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
