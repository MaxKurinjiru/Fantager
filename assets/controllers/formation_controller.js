import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'formationSelect',
        'fixtureFormationSelect',
        'nameInput',
        'approachRadio',
        'defaultCheckbox',
        'gridSlot',
        'poolHero',
        'rosterDropZone',
        'alert',
        'alertMessage'
    ];

    static values = {
        fixtureId: Number,
        isMatchPrep: Boolean,
        errorEmpty: String,
        errorAssign: String,
        textSaving: String,
        errorSave: String,
        successSave: String,
        confirmDelete: String,
        textDeleting: String,
        errorDelete: String,
        successDelete: String,
        successPromote: String,
        errorPromote: String,
        promptPromoteName: String,
        levelLabel: String,
        races: Object
    };

    connect() {
        this.slotsState = {};
        this.draggingElement = null;
        this.initializeSlotsState();
    }

    disconnect() {
        this.slotsState = {};
        this.draggingElement = null;
    }

    initializeSlotsState() {
        this.gridSlotTargets.forEach(slot => {
            const position = slot.dataset.position;
            const heroId = slot.dataset.heroId;
            this.slotsState[position] = heroId ? parseInt(heroId, 10) : null;
        });
        this.updateHeroPoolAvailability();
    }

    changeFormation() {
        const formationId = this.formationSelectTarget.value;
        const urlParams = new URLSearchParams(window.location.search);
        if (formationId) {
            urlParams.set('formation_id', formationId);
        } else {
            urlParams.delete('formation_id');
        }
        window.location.search = urlParams.toString();
    }

    async changeFixtureFormation(e) {
        const select = e.currentTarget;
        const value = select.value;

        select.disabled = true;

        try {
            let payload;
            if (value === 'default') {
                payload = { mode: 'default' };
            } else if (value === 'custom') {
                select.disabled = false;
                return;
            } else {
                payload = { mode: 'saved', formation_id: parseInt(value, 10) };
            }

            await this.putFixtureFormation(payload);
            window.location.reload();
        } catch (error) {
            this.showAlert('error', error.message);
            select.disabled = false;
        }
    }

    assignHero(e) {
        e.preventDefault();
        const heroId = parseInt(e.currentTarget.dataset.heroId, 10);
        const position = e.currentTarget.dataset.position;
        this.assignHeroToPosition(position, heroId);
    }

    removeHero(e) {
        e.preventDefault();
        const position = e.currentTarget.dataset.position;
        this.clearSlot(position);
    }

    quickAssign(e) {
        e.preventDefault();
        const select = e.currentTarget;
        const heroId = select.value ? parseInt(select.value, 10) : null;
        const position = select.dataset.position;

        if (!heroId) {
            this.clearSlot(position);
            return;
        }

        this.assignHeroToPosition(position, heroId);
    }

    poolDragStart(e) {
        const card = e.currentTarget;
        const heroId = parseInt(card.dataset.heroId, 10);
        this.startDrag(e, { heroId, sourcePosition: null });
        card.classList.add('formation-pool-item--dragging');
        this.draggingElement = card;
    }

    slotDragStart(e) {
        const slot = e.currentTarget.closest('[data-formation-target="gridSlot"]');
        if (!slot) {
            e.preventDefault();
            return;
        }

        const position = slot.dataset.position;
        const heroId = this.slotsState[position];
        if (!heroId) {
            e.preventDefault();
            return;
        }

        this.startDrag(e, { heroId, sourcePosition: position });
        slot.classList.add('formation-slot--dragging');
        this.draggingElement = slot;
    }

    slotDragOver(e) {
        if (!this.isFormationDrag(e)) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    slotDragEnter(e) {
        if (!this.isFormationDrag(e)) {
            return;
        }
        e.preventDefault();
        const slot = e.currentTarget.closest('[data-formation-target="gridSlot"]');
        if (slot) {
            slot.classList.add('formation-slot--drag-over');
        }
    }

    slotDragLeave(e) {
        const slot = e.currentTarget.closest('[data-formation-target="gridSlot"]');
        if (slot && !slot.contains(e.relatedTarget)) {
            slot.classList.remove('formation-slot--drag-over');
        }
    }

    slotDrop(e) {
        if (!this.isFormationDrag(e)) {
            return;
        }
        e.preventDefault();

        const slot = e.currentTarget.closest('[data-formation-target="gridSlot"]');
        if (!slot) {
            return;
        }

        const data = this.parseDragData(e);
        if (!data) {
            this.clearDragVisualState();
            return;
        }

        this.assignHeroToPosition(slot.dataset.position, data.heroId, data.sourcePosition);
        this.clearDragVisualState();
    }

    rosterDragOver(e) {
        if (!this.isFormationDrag(e)) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    rosterDragEnter(e) {
        if (!this.isFormationDrag(e)) {
            return;
        }
        e.preventDefault();
        if (this.hasRosterDropZoneTarget) {
            this.rosterDropZoneTarget.classList.add('formation-hero-roster--drag-over');
        }
    }

    rosterDragLeave(e) {
        if (!this.hasRosterDropZoneTarget) {
            return;
        }
        if (!this.rosterDropZoneTarget.contains(e.relatedTarget)) {
            this.rosterDropZoneTarget.classList.remove('formation-hero-roster--drag-over');
        }
    }

    rosterDrop(e) {
        if (!this.isFormationDrag(e)) {
            return;
        }
        e.preventDefault();

        const data = this.parseDragData(e);
        if (data?.sourcePosition) {
            this.clearSlot(data.sourcePosition);
        }

        this.clearDragVisualState();
    }

    dragEnd() {
        this.clearDragVisualState();
    }

    assignHeroToPosition(targetPosition, heroId, sourcePosition = null) {
        if (!heroId) {
            this.clearSlot(targetPosition);
            return;
        }

        const fromPosition = sourcePosition
            ?? Object.keys(this.slotsState).find(pos => this.slotsState[pos] === heroId);
        const targetHero = this.slotsState[targetPosition];

        if (fromPosition === targetPosition) {
            return;
        }

        if (fromPosition && targetHero) {
            this.slotsState[targetPosition] = heroId;
            this.slotsState[fromPosition] = targetHero;
        } else if (fromPosition && !targetHero) {
            this.slotsState[fromPosition] = null;
            this.slotsState[targetPosition] = heroId;
        } else if (targetHero) {
            this.slotsState[targetPosition] = heroId;
            if (fromPosition) {
                this.slotsState[fromPosition] = null;
            }
        } else {
            if (fromPosition) {
                this.slotsState[fromPosition] = null;
            }
            this.slotsState[targetPosition] = heroId;
        }

        this.syncUI();
    }

    clearSlot(position) {
        this.slotsState[position] = null;
        this.syncUI();
    }

    startDrag(e, payload) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('application/x-fantager-formation', JSON.stringify(payload));
        e.dataTransfer.setData('text/plain', String(payload.heroId));
    }

    isFormationDrag(e) {
        return Array.from(e.dataTransfer.types).includes('application/x-fantager-formation');
    }

    parseDragData(e) {
        const raw = e.dataTransfer.getData('application/x-fantager-formation');
        if (!raw) {
            return null;
        }

        try {
            const data = JSON.parse(raw);
            if (!data.heroId) {
                return null;
            }

            return {
                heroId: parseInt(data.heroId, 10),
                sourcePosition: data.sourcePosition ?? null
            };
        } catch {
            return null;
        }
    }

    clearDragVisualState() {
        this.gridSlotTargets.forEach(slot => {
            slot.classList.remove('formation-slot--drag-over', 'formation-slot--dragging');
        });

        if (this.hasRosterDropZoneTarget) {
            this.rosterDropZoneTarget.classList.remove('formation-hero-roster--drag-over');
        }

        this.poolHeroTargets.forEach(card => {
            card.classList.remove('formation-pool-item--dragging');
        });

        if (this.draggingElement) {
            this.draggingElement.classList.remove('formation-pool-item--dragging', 'formation-slot--dragging');
            this.draggingElement = null;
        }
    }

    syncUI() {
        this.gridSlotTargets.forEach(slot => {
            const position = slot.dataset.position;
            const heroId = this.slotsState[position];

            slot.dataset.heroId = heroId ? heroId.toString() : '';

            const emptyView = slot.querySelector('[data-empty-view]');
            const occupiedView = slot.querySelector('[data-occupied-view]');
            const select = slot.querySelector('[data-quick-select]');

            if (heroId) {
                if (emptyView) emptyView.classList.add('hidden');
                if (occupiedView) {
                    occupiedView.classList.remove('hidden');

                    const nameElem = occupiedView.querySelector('[data-hero-name]');
                    const detailsElem = occupiedView.querySelector('[data-hero-details]');
                    const avatarElem = occupiedView.querySelector('[data-hero-avatar]');
                    const removeBtn = occupiedView.querySelector('[data-action="click->formation#removeHero"]');

                    const heroCard = this.poolHeroTargets.find(hc => parseInt(hc.dataset.heroId, 10) === heroId);
                    if (heroCard) {
                        nameElem.textContent = heroCard.dataset.name;
                        const raceKey = heroCard.dataset.race;
                        const raceTranslated = (this.hasRacesValue && this.racesValue[raceKey]) || raceKey;
                        detailsElem.textContent = `${this.levelLabelValue} ${heroCard.dataset.level} ${raceTranslated}`;
                        if (avatarElem) {
                            avatarElem.textContent = heroCard.dataset.raceIcon || '👤';
                            avatarElem.title = raceTranslated;
                        }
                    }
                    if (removeBtn) {
                        removeBtn.dataset.position = position;
                    }
                }
            } else {
                if (emptyView) emptyView.classList.remove('hidden');
                if (occupiedView) occupiedView.classList.add('hidden');
            }

            if (select) {
                select.value = heroId ? heroId.toString() : '';
            }
        });

        this.updateHeroPoolAvailability();
    }

    updateHeroPoolAvailability() {
        const assignedIds = Object.values(this.slotsState).filter(id => id !== null);

        this.poolHeroTargets.forEach(card => {
            const heroId = parseInt(card.dataset.heroId, 10);
            const badge = card.querySelector('[data-assigned-badge]');

            if (assignedIds.includes(heroId)) {
                card.classList.add('formation-pool-item--assigned');
                if (badge) badge.classList.remove('hidden');
            } else {
                card.classList.remove('formation-pool-item--assigned');
                if (badge) badge.classList.add('hidden');
            }
        });

        this.gridSlotTargets.forEach(slot => {
            const select = slot.querySelector('[data-quick-select]');
            if (select) {
                const currentVal = select.value;
                Array.from(select.options).forEach(opt => {
                    if (!opt.value) return;
                    const optId = parseInt(opt.value, 10);
                    opt.disabled = assignedIds.includes(optId) && optId.toString() !== currentVal;
                });
            }
        });
    }

    async save(e) {
        e.preventDefault();
        const btn = e.currentTarget;

        const name = this.nameInputTarget.value.trim();
        if (!name) {
            this.showAlert('error', this.errorEmptyValue);
            return;
        }

        const approachRadio = this.approachRadioTargets.find(r => r.checked);
        const approach = approachRadio ? approachRadio.value : 'balanced';

        const slots = Object.entries(this.slotsState).map(([pos, heroId]) => ({
            position: pos,
            hero_id: heroId,
            strategy: {},
            spell_priorities: []
        }));

        const assignedCount = slots.filter(s => s.hero_id !== null).length;
        if (assignedCount === 0) {
            this.showAlert('error', this.errorAssignValue);
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textSavingValue;

        try {
            if (this.isMatchPrepValue) {
                await this.putFixtureFormation({
                    mode: 'custom',
                    name,
                    approach,
                    slots
                });
                this.showAlert('success', this.successSaveValue);
                setTimeout(() => window.location.reload(), 1000);
                return;
            }

            const isDefault = this.hasDefaultCheckboxTarget ? this.defaultCheckboxTarget.checked : false;
            const formationId = this.hasFormationSelectTarget && this.formationSelectTarget.value
                ? parseInt(this.formationSelectTarget.value, 10)
                : null;

            const payload = {
                id: formationId,
                name,
                approach,
                is_default: isDefault,
                slots
            };

            const csrfToken = this.getCsrfToken();
            const response = await fetch('/api/v1/formations', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorSaveValue);
            }

            this.showAlert('success', this.successSaveValue);
            setTimeout(() => {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('formation_id', result.id);
                window.location.search = urlParams.toString();
            }, 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async promoteFixtureFormation(e) {
        e.preventDefault();
        const btn = e.currentTarget;

        const name = prompt(this.nameInputTarget.value.trim() || this.promptPromoteNameValue);
        if (!name || !name.trim()) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textSavingValue;

        try {
            const csrfToken = this.getCsrfToken();
            const response = await fetch(`/api/v1/fixtures/${this.fixtureIdValue}/formation/promote`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ name: name.trim(), is_default: false })
            });

            const result = await response.json();
            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorPromoteValue);
            }

            this.showAlert('success', this.successPromoteValue);
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async delete(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const formationId = btn.dataset.formationId;

        if (!formationId) return;
        if (!confirm(this.confirmDeleteValue)) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = this.textDeletingValue;

        try {
            const csrfToken = this.getCsrfToken();
            const response = await fetch(`/api/v1/formations/${formationId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();
            if (!response.ok || result.error) {
                throw new Error(result.error || this.errorDeleteValue);
            }

            this.showAlert('success', this.successDeleteValue);
            setTimeout(() => {
                window.location.search = '';
            }, 1000);
        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async putFixtureFormation(payload) {
        const csrfToken = this.getCsrfToken();
        const response = await fetch(`/api/v1/fixtures/${this.fixtureIdValue}/formation`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        if (!response.ok || result.error) {
            throw new Error(result.error || this.errorSaveValue);
        }

        return result;
    }

    getCsrfToken() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        return csrfMeta ? csrfMeta.getAttribute('content') : '';
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
