import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { showConfirm } from '../utils/confirm.js';

export default class extends Controller {
    static targets = [
        'tabButton', 'tabContent',
        'browseContainer', 'myListingsContainer', 'historyContainer',
        'browseFilterForm', 'browseTypeInput',
        'raceFilterContainer', 'rarityFilterContainer', 'heroEntityFilter',
        'sellCatButton', 'sellEntitiesContainer',
        'selectedEntityLabel', 'sellIdInput',
        'listingModeSelect', 'buyoutFieldContainer', 'priceLabel',
        'suggestedPriceRow', 'suggestedPriceValue', 'listingPriceInput',
        'alert', 'alertMessage', 'sellSearchInput', 'sellSortSelect', 'sellSortOption',
        'browsePagination', 'browsePrevBtn', 'browseNextBtn', 'browsePageInfo',
        'myListingsPagination', 'myListingsPrevBtn', 'myListingsNextBtn', 'myListingsPageInfo',
        'historyPagination', 'historyPrevBtn', 'historyNextBtn', 'historyPageInfo'
    ];

    static values = {
        taxRate: Number,
        csrfToken: String,
        currentTeamId: Number,
        races: Object,
        rarities: Object,
        slots: Object,
        statuses: Object,
        traits: Object,
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

        // Initialize page trackers
        this.browsePage = 1;
        this.browseTotalPages = 1;
        this.myListingsPage = 1;
        this.myListingsTotalPages = 1;
        this.historyPage = 1;
        this.historyTotalPages = 1;

        if (['browse', 'sell', 'mylistings', 'history', 'basic_equipment'].includes(this.activeTab)) {
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
            this.browsePage = 1;
            this.loadListings({ type: this.activeBrowseCategory });
        } else if (tabName === 'mylistings') {
            this.myListingsPage = 1;
            this.loadMyListings();
        } else if (tabName === 'history') {
            this.historyPage = 1;
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

        const isItem = catName === 'item';

        if (catName === 'item') {
            this.raceFilterContainerTarget.classList.add('hidden');
            this.rarityFilterContainerTarget.classList.remove('hidden');
        } else {
            this.raceFilterContainerTarget.classList.remove('hidden');
            this.rarityFilterContainerTarget.classList.add('hidden');
        }

        if (this.hasHeroEntityFilterTarget) {
            this.heroEntityFilterTargets.forEach(el => {
                el.classList.toggle('hidden', isItem);
            });
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
        this.selectedEntityLabelTarget.textContent = this.translationsValue.none_selected || '';

        // Unhighlight any selected card
        this.sellEntitiesContainerTargets.forEach(container => {
            container.querySelectorAll('.sell-card').forEach(card => {
                card.classList.remove('sell-card--selected');
            });
        });

        // Reset filter search input
        if (this.hasSellSearchInputTarget) {
            this.sellSearchInputTarget.value = '';
        }

        // Reset sort select
        if (this.hasSellSortSelectTarget) {
            this.sellSortSelectTarget.value = 'name_asc';
        }

        // Show/hide sort options based on category
        if (this.hasSellSortOptionTargets) {
            this.sellSortOptionTargets.forEach(opt => {
                const cats = opt.dataset.sellCatOnly ? opt.dataset.sellCatOnly.split(',') : [];
                if (cats.length > 0) {
                    const isVisible = cats.includes(catName);
                    opt.classList.toggle('hidden', !isVisible);
                    opt.disabled = !isVisible;
                }
            });
        }

        // Reset visibility of all cards in current container
        this.sellEntitiesContainerTargets.forEach(container => {
            container.querySelectorAll('.sell-card').forEach(card => {
                card.classList.remove('hidden');
            });
        });
    }

    filterSellEntities(event) {
        const query = event.target.value.toLowerCase().trim();
        const activeContainer = this.sellEntitiesContainerTargets.find(
            c => c.dataset.sellCatName === this.activeSellCategory
        );
        if (!activeContainer) return;

        const cards = activeContainer.querySelectorAll('.sell-card');
        cards.forEach(card => {
            const name = card.dataset.entityName.toLowerCase();
            const matchesQuery = name.includes(query);
            card.classList.toggle('hidden', !matchesQuery);
        });
    }

    sortSellEntities(event) {
        const sortBy = this.hasSellSortSelectTarget ? this.sellSortSelectTarget.value : 'name_asc';
        const activeContainer = this.sellEntitiesContainerTargets.find(
            c => c.dataset.sellCatName === this.activeSellCategory
        );
        if (!activeContainer) return;

        const cards = Array.from(activeContainer.querySelectorAll('.sell-card'));
        
        cards.sort((a, b) => {
            if (sortBy === 'name_asc') {
                return a.dataset.entityName.localeCompare(b.dataset.entityName);
            } else if (sortBy === 'name_desc') {
                return b.dataset.entityName.localeCompare(a.dataset.entityName);
            } else if (sortBy === 'level_desc') {
                const lvlA = parseInt(a.dataset.entityLevel || 0);
                const lvlB = parseInt(b.dataset.entityLevel || 0);
                return lvlB - lvlA;
            } else if (sortBy === 'age_asc') {
                const ageA = parseInt(a.dataset.entityAge || 0);
                const ageB = parseInt(b.dataset.entityAge || 0);
                return ageA - ageB;
            } else if (sortBy === 'rarity_desc') {
                const raritiesOrder = { 'mythic': 6, 'legendary': 5, 'epic': 4, 'rare': 3, 'uncommon': 2, 'common': 1 };
                const rarA = raritiesOrder[a.dataset.entityRarity] || 0;
                const rarB = raritiesOrder[b.dataset.entityRarity] || 0;
                return rarB - rarA;
            }
            return 0;
        });

        cards.forEach(card => activeContainer.appendChild(card));
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
        const suggestedPrice = parseInt(card.dataset.suggestedPrice || '0', 10);

        this.selectedEntityId = id;
        this.sellIdInputTarget.value = id;
        this.selectedEntityLabelTarget.textContent = name;
        this._updateSuggestedPrice(suggestedPrice);

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
        this._updateSuggestedPrice(parseInt(card.dataset.suggestedPrice || '0', 10));

        heroContainer.querySelectorAll('.sell-card').forEach(c => {
            c.classList.toggle('sell-card--selected', c === card);
        });
    }

    applySuggestedPrice(event) {
        event.preventDefault();
        if (!this.hasListingPriceInputTarget || !this.hasSuggestedPriceValueTarget) {
            return;
        }

        const raw = this.suggestedPriceValueTarget.textContent.replace(/[^\d]/g, '');
        const price = parseInt(raw, 10);
        if (price > 0) {
            this.listingPriceInputTarget.value = String(price);
        }
    }

    _updateSuggestedPrice(price) {
        if (!this.hasSuggestedPriceRowTarget || !this.hasSuggestedPriceValueTarget) {
            return;
        }

        if (price > 0) {
            this.suggestedPriceRowTarget.classList.remove('hidden');
            this.suggestedPriceValueTarget.textContent = `🪙 ${price.toLocaleString()}`;
        } else {
            this.suggestedPriceRowTarget.classList.add('hidden');
            this.suggestedPriceValueTarget.textContent = '';
        }
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

        if (!params.page) {
            params.page = this.browsePage;
        } else {
            this.browsePage = parseInt(params.page);
        }

        let url = '/api/v1/marketplace';
        const queryParams = new URLSearchParams(params);
        if (queryParams.toString()) {
            url += '?' + queryParams.toString();
        }

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(this.translationsValue.error_fetch_listings);
            const data = await response.json();
            this.renderBrowse(data.items);
            this.renderBrowsePagination(data.page, data.total_pages);
        } catch (error) {
            this.browseContainerTarget.innerHTML = '';
            const errorTemplate = document.getElementById('template-error-grid');
            const errorNode = errorTemplate.content.cloneNode(true);
            const errText = errorNode.querySelector('.js-error-text');
            errText.textContent = this.translationsValue.error_fetch_listings
                .replace('%error%', error.message);
            this.browseContainerTarget.appendChild(errorNode);
            if (this.hasBrowsePaginationTarget) {
                this.browsePaginationTarget.classList.add('hidden');
            }
        }
    }

    renderBrowsePagination(page, totalPages) {
        this.browsePage = page;
        this.browseTotalPages = totalPages;

        if (!this.hasBrowsePaginationTarget) return;

        if (totalPages <= 1) {
            this.browsePaginationTarget.classList.add('hidden');
            return;
        }

        this.browsePaginationTarget.classList.remove('hidden');

        if (this.hasBrowsePrevBtnTarget) {
            this.browsePrevBtnTarget.disabled = page <= 1;
        }

        if (this.hasBrowseNextBtnTarget) {
            this.browseNextBtnTarget.disabled = page >= totalPages;
        }

        if (this.hasBrowsePageInfoTarget) {
            const infoPattern = this.translationsValue.page_info || 'Strana %page% z %total%';
            this.browsePageInfoTarget.textContent = infoPattern
                .replace('%page%', page)
                .replace('%total%', totalPages);
        }
    }

    async browsePrevPage(e) {
        e.preventDefault();
        if (this.browsePage > 1) {
            this.browsePage--;
            const params = {};
            if (this.hasBrowseFilterFormTarget) {
                const formData = new FormData(this.browseFilterFormTarget);
                for (const [key, val] of formData.entries()) {
                    if (val !== '') {
                        params[key] = val;
                    }
                }
            }
            params.page = this.browsePage;
            await this.loadListings(params);
        }
    }

    async browseNextPage(e) {
        e.preventDefault();
        if (this.browsePage < this.browseTotalPages) {
            this.browsePage++;
            const params = {};
            if (this.hasBrowseFilterFormTarget) {
                const formData = new FormData(this.browseFilterFormTarget);
                for (const [key, val] of formData.entries()) {
                    if (val !== '') {
                        params[key] = val;
                    }
                }
            }
            params.page = this.browsePage;
            await this.loadListings(params);
        }
    }

    applyFilters(event) {
        event.preventDefault();
        this.browsePage = 1;
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
        this.browsePage = 1;
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

                card.querySelector('.js-level').textContent = (this.translationsValue.hero_level_short || '%level%')
                    .replace('%level%', hero.level);
                if (hero.ratings) {
                    card.querySelector('.js-base-ovr').textContent = hero.ratings.base_ovr;
                    card.querySelector('.js-complex-rating').textContent = hero.ratings.complex_rating;
                } else {
                    card.querySelector('.js-rating-row')?.classList.add('hidden');
                }
                card.querySelector('.js-name').textContent = hero.name;
                card.querySelector('.js-race').textContent = this.racesValue[hero.race] || hero.race;
                card.querySelector('.js-age').textContent = `${hero.age} ${this.translationsValue.years_suffix || ''}`;
                card.querySelector('.js-form').textContent = `${hero.form}%`;
                card.querySelector('.js-fatigue').textContent = `${hero.fatigue}%`;
                this.renderHeroTrait(card, hero.trait);

                card.querySelector('.js-str').textContent = hero.str;
                card.querySelector('.js-dex').textContent = hero.dex;
                card.querySelector('.js-kon').textContent = hero.kon;
                card.querySelector('.js-spd').textContent = hero.spd;
                card.querySelector('.js-int').textContent = hero.intel;
                card.querySelector('.js-wil').textContent = hero.wil;
                card.querySelector('.js-cha').textContent = hero.cha;
                card.querySelector('.js-lck').textContent = hero.lck;

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
                if (trainer.ratings) {
                    card.querySelector('.js-base-ovr').textContent = trainer.ratings.base_ovr;
                    card.querySelector('.js-complex-rating').textContent = trainer.ratings.complex_rating;
                } else {
                    card.querySelector('.js-rating-row')?.classList.add('hidden');
                }
                card.querySelector('.js-name').textContent = trainer.name;
                card.querySelector('.js-race').textContent = this.racesValue[trainer.race] || trainer.race;

                card.querySelector('.js-str').textContent = trainer.str;
                card.querySelector('.js-dex').textContent = trainer.dex;
                card.querySelector('.js-kon').textContent = trainer.kon;
                card.querySelector('.js-spd').textContent = trainer.spd;
                card.querySelector('.js-int').textContent = trainer.intel;
                card.querySelector('.js-wil').textContent = trainer.wil;
                card.querySelector('.js-cha').textContent = trainer.cha;
                card.querySelector('.js-lck').textContent = trainer.lck;
            }

            const isSeller = listing.seller_team.id === currentTeamId;
            const buyoutPrice = listing.buyout_price_gold;
            const currentHighestBid = listing.highest_bid ? listing.highest_bid.amount : null;

            card.querySelector('.js-seller-name').textContent = listing.seller_team.name;
            const sellerRepEl = card.querySelector('.js-seller-reputation');
            if (sellerRepEl) {
                const repLabel = this.translationsValue.label_seller_reputation || '%value%';
                sellerRepEl.textContent = repLabel.replace('%value%', listing.seller_team.reputation ?? 0);
            }

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

        const confirmMsg = (this.translationsValue.confirm_buyout || '')
            .replace('%amount%', price);

        if (!await showConfirm(
            this.translationsValue.btn_buyout || 'Buy Now',
            confirmMsg,
            this.translationsValue.btn_buyout,
            this.translationsValue.cancel || 'Cancel'
        )) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = this.translationsValue.text_loading || '';

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

        const confirmMsg = (this.translationsValue.confirm_bid || '')
            .replace('%amount%', bidAmount);

        if (!await showConfirm(
            this.translationsValue.btn_bid || 'Place Bid',
            confirmMsg,
            this.translationsValue.btn_bid,
            this.translationsValue.cancel || 'Cancel'
        )) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = this.translationsValue.text_loading || '';

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
            const response = await fetch(`/api/v1/marketplace/my-listings?page=${this.myListingsPage}`);
            if (!response.ok) throw new Error(this.translationsValue.error_fetch_listings);
            const data = await response.json();
            this.renderMyListings(data.items);
            this.renderMyListingsPagination(data.page, data.total_pages);
        } catch (error) {
            this.myListingsContainerTarget.innerHTML = '';
            const errorTemplate = document.getElementById('template-error-row');
            const errorNode = errorTemplate.content.cloneNode(true);
            errorNode.querySelector('.js-error-text').textContent =
                this.translationsValue.error_fetch_my_listings.replace('%error%', error.message);
            this.myListingsContainerTarget.appendChild(errorNode);
            if (this.hasMyListingsPaginationTarget) {
                this.myListingsPaginationTarget.classList.add('hidden');
            }
        }
    }

    renderMyListingsPagination(page, totalPages) {
        this.myListingsPage = page;
        this.myListingsTotalPages = totalPages;

        if (!this.hasMyListingsPaginationTarget) return;

        if (totalPages <= 1) {
            this.myListingsPaginationTarget.classList.add('hidden');
            return;
        }

        this.myListingsPaginationTarget.classList.remove('hidden');

        if (this.hasMyListingsPrevBtnTarget) {
            this.myListingsPrevBtnTarget.disabled = page <= 1;
        }

        if (this.hasMyListingsNextBtnTarget) {
            this.myListingsNextBtnTarget.disabled = page >= totalPages;
        }

        if (this.hasMyListingsPageInfoTarget) {
            const infoPattern = this.translationsValue.page_info || 'Strana %page% z %total%';
            this.myListingsPageInfoTarget.textContent = infoPattern
                .replace('%page%', page)
                .replace('%total%', totalPages);
        }
    }

    async myListingsPrevPage(e) {
        e.preventDefault();
        if (this.myListingsPage > 1) {
            this.myListingsPage--;
            await this.loadMyListings();
        }
    }

    async myListingsNextPage(e) {
        e.preventDefault();
        if (this.myListingsPage < this.myListingsTotalPages) {
            this.myListingsPage++;
            await this.loadMyListings();
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

        const confirmMsg = this.translationsValue.confirm_cancel || '';

        if (!await showConfirm(
            this.translationsValue.btn_cancel || 'Cancel Listing',
            confirmMsg,
            this.translationsValue.btn_cancel,
            this.translationsValue.cancel || 'Cancel',
            'danger'
        )) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = this.translationsValue.text_loading || '';

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
            const response = await fetch(`/api/v1/marketplace/history?page=${this.historyPage}`);
            if (!response.ok) throw new Error(this.translationsValue.error_fetch_listings);
            const data = await response.json();
            this.renderHistory(data.items);
            this.renderHistoryPagination(data.page, data.total_pages);
        } catch (error) {
            this.historyContainerTarget.innerHTML = '';
            const errorTemplate = document.getElementById('template-error-row');
            const errorNode = errorTemplate.content.cloneNode(true);
            errorNode.querySelector('.js-error-text').textContent =
                this.translationsValue.error_fetch_my_listings.replace('%error%', error.message);
            this.historyContainerTarget.appendChild(errorNode);
            if (this.hasHistoryPaginationTarget) {
                this.historyPaginationTarget.classList.add('hidden');
            }
        }
    }

    renderHistoryPagination(page, totalPages) {
        this.historyPage = page;
        this.historyTotalPages = totalPages;

        if (!this.hasHistoryPaginationTarget) return;

        if (totalPages <= 1) {
            this.historyPaginationTarget.classList.add('hidden');
            return;
        }

        this.historyPaginationTarget.classList.remove('hidden');

        if (this.hasHistoryPrevBtnTarget) {
            this.historyPrevBtnTarget.disabled = page <= 1;
        }

        if (this.hasHistoryNextBtnTarget) {
            this.historyNextBtnTarget.disabled = page >= totalPages;
        }

        if (this.hasHistoryPageInfoTarget) {
            const infoPattern = this.translationsValue.page_info || 'Strana %page% z %total%';
            this.historyPageInfoTarget.textContent = infoPattern
                .replace('%page%', page)
                .replace('%total%', totalPages);
        }
    }

    async historyPrevPage(e) {
        e.preventDefault();
        if (this.historyPage > 1) {
            this.historyPage--;
            await this.loadHistory();
        }
    }

    async historyNextPage(e) {
        e.preventDefault();
        if (this.historyPage < this.historyTotalPages) {
            this.historyPage++;
            await this.loadHistory();
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
        if (diffMs <= 0) return this.translationsValue.time_expired || '';

        const diffHrs = Math.floor(diffMs / 3600000);
        if (diffHrs < 24) {
            const mins = Math.floor((diffMs % 3600000) / 60000);
            return (this.translationsValue.time_remaining_hours || '')
                .replace('%hours%', diffHrs)
                .replace('%minutes%', mins);
        }

        const diffDays = Math.floor(diffHrs / 24);
        const remainingHrs = diffHrs % 24;
        return (this.translationsValue.time_remaining || '')
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

    renderHeroTrait(card, traitKey) {
        const row = card.querySelector('.js-trait-row');
        const badge = card.querySelector('.js-trait-badge');
        if (!row || !badge) {
            return;
        }

        if (!traitKey || !this.hasTraitsValue || !this.traitsValue[traitKey]) {
            row.classList.add('hidden');
            badge.replaceChildren();
            badge.className = 'js-trait-badge';
            badge.removeAttribute('title');
            return;
        }

        const trait = this.traitsValue[traitKey];
        row.classList.remove('hidden');
        badge.className = `hero-trait-badge hero-trait-badge--${trait.category} hero-trait-badge--compact js-trait-badge`;
        badge.title = trait.desc;
        badge.replaceChildren();

        const icon = document.createElement('span');
        icon.className = 'hero-trait-badge__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = trait.icon;

        const label = document.createElement('span');
        label.className = 'hero-trait-badge__label';
        label.textContent = trait.name;

        badge.append(icon, label);
    }
}
