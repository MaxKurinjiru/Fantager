import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { showConfirm } from '../utils/confirm.js';

export default class extends Controller {
    static targets = [
        'formationSelect',
        'fixtureFormationSelect',
        'nameInput',
        'approachRadio',
        'defaultCheckbox',
        'movementGoalSelect',
        'gridSlot',
        'poolHero',
        'rosterDropZone',
        'alert',
        'alertMessage',
        'tacticsEmpty',
        'tacticsEditor',
        'tacticsSlotName',
        'tacticsHeroName',
        'targetOrderList',
        'addTargetSelect',
        'fallbackSelect',
        'spellPriorityList',
        'addSpellSelect',
        'addSpellRow',
        'spellsNeedHero',
        'spellsEmpty',
        'targetOrderRowTemplate',
        'spellPriorityRowTemplate',
        'spellTemplates',
        'templateHealer',
        'templateCc',
        'templateNuker',
        'templateBuff',
        'engineSummary',
        'engineTargeting',
        'engineSpells',
        'engineMovement',
        'slotMovementSelect'
    ];

    static values = {
        fixtureId: Number,
        isMatchPrep: Boolean,
        errorEmpty: String,
        textSaving: String,
        errorSave: String,
        successSave: String,
        confirmDelete: String,
        titleDelete: String,
        textDeleting: String,
        errorDelete: String,
        successDelete: String,
        successPromote: String,
        errorPromote: String,
        promptPromoteName: String,
        levelLabel: String,
        races: Object,
        emptyHero: String,
        addSpell: String,
        slotLabels: Object,
        slotTactics: Object,
        heroSpells: Object,
        healThresholds: Object,
        l0Orders: Object,
        summaryTargetingL0: String,
        summaryTargetingL0Defensive: String,
        summaryTargetingL1: String,
        summaryFallback: String,
        summarySpellsL0: String,
        summarySpellsL2: String,
        summaryMovement: String,
        orderArrow: String,
        fallbackLabels: Object,
        movementLabels: Object,
        movementShort: Object
    };

    connect() {
        this.slotsState = {};
        this.tacticsState = this.cloneTactics(this.hasSlotTacticsValue ? this.slotTacticsValue : {});
        this.selectedPosition = null;
        this.draggingElement = null;
        this.initializeSlotsState();
        this.renderTacticsPanel();
        this.refreshSlotBadges();
    }

    disconnect() {
        this.slotsState = {};
        this.tacticsState = {};
        this.selectedPosition = null;
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
        if (card.dataset.status && card.dataset.status !== 'available') {
            e.preventDefault();
            return;
        }
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

        const heroCard = this.poolHeroTargets.find(hc => parseInt(hc.dataset.heroId, 10) === heroId);
        if (heroCard && heroCard.dataset.status && heroCard.dataset.status !== 'available') {
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
            this.swapSpellPriorities(fromPosition, targetPosition);
        } else if (fromPosition && !targetHero) {
            this.slotsState[fromPosition] = null;
            this.slotsState[targetPosition] = heroId;
            this.moveSpellPriorities(fromPosition, targetPosition);
        } else if (targetHero) {
            this.slotsState[targetPosition] = heroId;
            if (fromPosition) {
                this.slotsState[fromPosition] = null;
            }
            this.filterSpellPriorities(targetPosition, heroId);
        } else {
            if (fromPosition) {
                this.slotsState[fromPosition] = null;
            }
            this.slotsState[targetPosition] = heroId;
        }

        this.syncUI();
        this.renderTacticsPanel();
    }

    clearSlot(position) {
        this.slotsState[position] = null;
        this.clearSpellPriorities(position);
        this.syncUI();
        this.renderTacticsPanel();
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

                        // Update status badge & unavailable class based on heroCard status
                        const isAvailable = heroCard.dataset.status === 'available';
                        const badge = occupiedView.querySelector('.formation-slot__status-badge');
                        if (isAvailable) {
                            occupiedView.classList.remove('formation-slot__occupied--unavailable');
                            if (badge) badge.remove();
                        } else {
                            occupiedView.classList.add('formation-slot__occupied--unavailable');
                            if (!badge) {
                                const statusVal = heroCard.dataset.status;
                                const statusText = heroCard.querySelector('.formation-pool-item__status-badge')?.textContent || statusVal;
                                const newBadge = document.createElement('span');
                                newBadge.className = `formation-slot__status-badge formation-pool-item__status-badge--${statusVal}`;
                                newBadge.textContent = statusText;
                                occupiedView.insertBefore(newBadge, occupiedView.firstChild);
                            }
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
                    const isAssignedElsewhere = assignedIds.includes(optId) && optId.toString() !== currentVal;
                    const isUnavailable = opt.dataset.status !== 'available' && optId.toString() !== currentVal;
                    opt.disabled = isAssignedElsewhere || isUnavailable;
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
        const movementGoal = this.hasMovementGoalSelectTarget
            ? this.movementGoalSelectTarget.value
            : 'advance_to_melee';

        const slots = Object.entries(this.slotsState).map(([pos, heroId]) => {
            const tactics = this.ensureTactics(pos);
            const strategy = { movement_goal: tactics.movement_goal || movementGoal };
            if (tactics.target_order.length > 0) {
                strategy.target_order = [...tactics.target_order];
                strategy.fallback = tactics.fallback || 'approach';
            }

            return {
                position: pos,
                hero_id: heroId,
                strategy,
                spell_priorities: tactics.spell_priorities.map((row) => ({ ...row }))
            };
        });

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
            const formationId = this.hasFormationSelectTarget && this.formationSelectTarget.value && this.formationSelectTarget.value !== 'new'
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
        if (!await showConfirm(
            this.titleDeleteValue || 'Delete Formation',
            this.confirmDeleteValue,
            this.titleDeleteValue,
            null, // Default to Cancel
            'danger'
        )) return;

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

    selectSlot(e) {
        if (e.target.closest('[data-action*="removeHero"]') || e.target.closest('[data-quick-select]')) {
            return;
        }

        const slot = e.currentTarget.closest('[data-formation-target="gridSlot"]');
        if (!slot) {
            return;
        }

        this.selectedPosition = slot.dataset.position;
        this.gridSlotTargets.forEach((gridSlot) => {
            gridSlot.classList.toggle('formation-slot--selected', gridSlot.dataset.position === this.selectedPosition);
        });
        this.renderTacticsPanel();
    }

    addTarget(e) {
        e.preventDefault();
        if (!this.selectedPosition || !this.hasAddTargetSelectTarget) {
            return;
        }
        const value = this.addTargetSelectTarget.value;
        const tactics = this.ensureTactics(this.selectedPosition);
        if (!value || tactics.target_order.includes(value)) {
            return;
        }
        tactics.target_order.push(value);
        this.addTargetSelectTarget.value = '';
        this.renderTacticsPanel();
    }

    removeTarget(e) {
        e.preventDefault();
        const value = e.currentTarget.closest('[data-slot-value]')?.dataset.slotValue;
        const tactics = this.selectedTactics();
        if (!tactics || !value) {
            return;
        }
        tactics.target_order = tactics.target_order.filter((slot) => slot !== value);
        this.renderTacticsPanel();
    }

    moveTargetUp(e) {
        e.preventDefault();
        this.moveListItem('target_order', e.currentTarget.closest('[data-slot-value]')?.dataset.slotValue, -1);
    }

    moveTargetDown(e) {
        e.preventDefault();
        this.moveListItem('target_order', e.currentTarget.closest('[data-slot-value]')?.dataset.slotValue, 1);
    }

    changeFallback() {
        const tactics = this.selectedTactics();
        if (!tactics || !this.hasFallbackSelectTarget) {
            return;
        }
        tactics.fallback = this.fallbackSelectTarget.value || 'approach';
    }

    addSpell(e) {
        e.preventDefault();
        const tactics = this.selectedTactics();
        if (!tactics || !this.hasAddSpellSelectTarget) {
            return;
        }
        const spellId = parseInt(this.addSpellSelectTarget.value, 10);
        if (!spellId || tactics.spell_priorities.some((row) => row.spell_id === spellId)) {
            return;
        }
        const spell = this.heroSpells(this.slotsState[this.selectedPosition]).find((item) => item.id === spellId);
        let when = 'always';
        let target = 'priority';
        if (spell?.type === 'restore') {
            when = 'ally_hp_below';
            target = 'lowest_hp_ally';
        } else if (spell?.type === 'enhance') {
            target = 'self';
        }
        const row = {
            spell_id: spellId,
            when,
            target
        };
        if (when !== 'always') {
            row.threshold = 40;
        }
        tactics.spell_priorities.push(row);
        this.renderTacticsPanel();
    }

    applySpellTemplate(e) {
        e.preventDefault();
        const tactics = this.selectedTactics();
        const type = e.currentTarget.dataset.spellType;
        if (!tactics || !type) {
            return;
        }
        const spells = this.heroSpells(this.slotsState[this.selectedPosition]).filter((spell) => spell.type === type);
        if (spells.length === 0) {
            return;
        }
        const used = new Set();
        const rows = [];
        spells.forEach((spell) => {
            if (used.has(spell.id)) {
                return;
            }
            used.add(spell.id);
            if ('restore' === type) {
                rows.push({
                    spell_id: spell.id,
                    when: 'ally_hp_below',
                    threshold: 50,
                    target: 'lowest_hp_ally'
                });
            } else if ('enhance' === type) {
                rows.push({
                    spell_id: spell.id,
                    when: 'always',
                    target: 'self'
                });
            } else {
                rows.push({
                    spell_id: spell.id,
                    when: 'always',
                    target: 'priority'
                });
            }
        });
        tactics.spell_priorities = rows;
        this.renderTacticsPanel();
    }

    removeSpell(e) {
        e.preventDefault();
        const spellId = parseInt(e.currentTarget.closest('[data-spell-id]')?.dataset.spellId, 10);
        const tactics = this.selectedTactics();
        if (!tactics || !spellId) {
            return;
        }
        tactics.spell_priorities = tactics.spell_priorities.filter((row) => row.spell_id !== spellId);
        this.renderTacticsPanel();
    }

    moveSpellUp(e) {
        e.preventDefault();
        const spellId = parseInt(e.currentTarget.closest('[data-spell-id]')?.dataset.spellId, 10);
        this.moveSpell(spellId, -1);
    }

    moveSpellDown(e) {
        e.preventDefault();
        const spellId = parseInt(e.currentTarget.closest('[data-spell-id]')?.dataset.spellId, 10);
        this.moveSpell(spellId, 1);
    }

    changeSpellWhen(e) {
        const tactics = this.selectedTactics();
        const row = this.spellRowFromEvent(e);
        if (!tactics || !row) {
            return;
        }
        row.when = e.currentTarget.value;
        if (row.when === 'always') {
            delete row.threshold;
            if (row.target === 'lowest_hp_ally' || row.target === 'self') {
                row.target = 'priority';
            }
        } else {
            row.threshold = row.threshold || 40;
            if (row.when === 'ally_hp_below' && row.target === 'priority') {
                row.target = 'lowest_hp_ally';
            }
            if (row.when === 'self_hp_below' && row.target === 'priority') {
                row.target = 'self';
            }
        }
        this.renderTacticsPanel();
    }

    changeSpellThreshold(e) {
        const row = this.spellRowFromEvent(e);
        if (!row) {
            return;
        }
        const value = parseInt(e.currentTarget.value, 10);
        row.threshold = Number.isFinite(value) ? Math.max(1, Math.min(99, value)) : 40;
    }

    changeSpellTarget(e) {
        const row = this.spellRowFromEvent(e);
        if (!row) {
            return;
        }
        row.target = e.currentTarget.value;
    }

    renderTacticsPanel() {
        if (!this.hasTacticsEmptyTarget || !this.hasTacticsEditorTarget) {
            return;
        }

        const position = this.selectedPosition;
        if (!position) {
            this.tacticsEmptyTarget.classList.remove('hidden');
            this.tacticsEditorTarget.classList.add('hidden');
            return;
        }

        const tactics = this.ensureTactics(position);
        const heroId = this.slotsState[position];
        const heroCard = heroId
            ? this.poolHeroTargets.find((card) => parseInt(card.dataset.heroId, 10) === heroId)
            : null;

        this.tacticsEmptyTarget.classList.add('hidden');
        this.tacticsEditorTarget.classList.remove('hidden');
        this.tacticsSlotNameTarget.textContent = this.slotLabel(position);
        this.tacticsHeroNameTarget.textContent = heroCard
            ? heroCard.dataset.name
            : (this.hasEmptyHeroValue ? this.emptyHeroValue : '');

        if (this.hasFallbackSelectTarget) {
            this.fallbackSelectTarget.value = tactics.fallback || 'approach';
        }

        if (this.hasSlotMovementSelectTarget) {
            this.slotMovementSelectTarget.value = tactics.movement_goal || 'advance_to_melee';
        }

        this.renderTargetOrder(tactics);
        this.renderSpellPriorities(tactics, heroId);
        this.renderEngineSummary(tactics);
        this.refreshSlotBadges();
    }

    renderTargetOrder(tactics) {
        if (!this.hasTargetOrderListTarget || !this.hasTargetOrderRowTemplateTarget) {
            return;
        }

        this.targetOrderListTarget.replaceChildren();
        tactics.target_order.forEach((slot) => {
            const node = this.targetOrderRowTemplateTarget.content.firstElementChild.cloneNode(true);
            node.dataset.slotValue = slot;
            node.querySelector('[data-slot-label]').textContent = this.slotLabel(slot);
            this.targetOrderListTarget.appendChild(node);
        });

        if (this.hasAddTargetSelectTarget) {
            Array.from(this.addTargetSelectTarget.options).forEach((option) => {
                if (!option.value) {
                    return;
                }
                option.hidden = tactics.target_order.includes(option.value);
            });
        }
    }

    renderSpellPriorities(tactics, heroId) {
        if (!this.hasSpellPriorityListTarget || !this.hasSpellPriorityRowTemplateTarget) {
            return;
        }

        const spells = this.heroSpells(heroId);
        const hasHero = Boolean(heroId);

        if (this.hasSpellsNeedHeroTarget) {
            this.spellsNeedHeroTarget.classList.toggle('hidden', hasHero);
        }
        if (this.hasSpellsEmptyTarget) {
            this.spellsEmptyTarget.classList.toggle('hidden', !hasHero || spells.length > 0);
        }
        if (this.hasAddSpellRowTarget) {
            this.addSpellRowTarget.classList.toggle('hidden', !hasHero || spells.length === 0);
        }
        const showTemplates = hasHero && spells.length > 0;
        if (this.hasSpellTemplatesTarget) {
            this.spellTemplatesTarget.classList.toggle('hidden', !showTemplates);
        }
        const hasType = (type) => spells.some((spell) => spell.type === type);
        if (this.hasTemplateHealerTarget) {
            this.templateHealerTarget.classList.toggle('hidden', !hasType('restore'));
        }
        if (this.hasTemplateCcTarget) {
            this.templateCcTarget.classList.toggle('hidden', !hasType('hinder'));
        }
        if (this.hasTemplateNukerTarget) {
            this.templateNukerTarget.classList.toggle('hidden', !hasType('harm'));
        }
        if (this.hasTemplateBuffTarget) {
            this.templateBuffTarget.classList.toggle('hidden', !hasType('enhance'));
        }

        this.spellPriorityListTarget.replaceChildren();
        if (!hasHero) {
            return;
        }

        tactics.spell_priorities = tactics.spell_priorities.filter((row) =>
            spells.some((spell) => spell.id === row.spell_id)
        );

        tactics.spell_priorities.forEach((row) => {
            const spell = spells.find((item) => item.id === row.spell_id);
            const node = this.spellPriorityRowTemplateTarget.content.firstElementChild.cloneNode(true);
            node.dataset.spellId = String(row.spell_id);
            node.querySelector('[data-spell-name]').textContent = spell?.name || `#${row.spell_id}`;
            const gate = node.querySelector('[data-spell-gate]');
            if (gate) {
                gate.classList.toggle('hidden', spell?.castable !== false);
            }
            const whenSelect = node.querySelector('[data-when-select]');
            const targetSelect = node.querySelector('[data-target-select]');
            const thresholdWrap = node.querySelector('[data-threshold-wrap]');
            const thresholdInput = node.querySelector('[data-threshold-input]');
            whenSelect.value = row.when;
            targetSelect.value = row.target;
            const showThreshold = row.when !== 'always';
            thresholdWrap.classList.toggle('hidden', !showThreshold);
            if (showThreshold) {
                thresholdInput.value = String(row.threshold || 40);
            }
            this.spellPriorityListTarget.appendChild(node);
        });

        if (this.hasAddSpellSelectTarget) {
            const used = new Set(tactics.spell_priorities.map((row) => row.spell_id));
            this.addSpellSelectTarget.replaceChildren();
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = this.hasAddSpellValue ? this.addSpellValue : '';
            this.addSpellSelectTarget.appendChild(placeholder);
            spells.filter((spell) => !used.has(spell.id)).forEach((spell) => {
                const option = document.createElement('option');
                option.value = String(spell.id);
                option.textContent = spell.name;
                this.addSpellSelectTarget.appendChild(option);
            });
        }
    }

    ensureTactics(position) {
        if (!this.tacticsState[position]) {
            this.tacticsState[position] = {
                target_order: [],
                fallback: 'approach',
                spell_priorities: [],
                movement_goal: this.defaultMovementGoal()
            };
        }
        if (!Array.isArray(this.tacticsState[position].target_order)) {
            this.tacticsState[position].target_order = [];
        }
        if (!Array.isArray(this.tacticsState[position].spell_priorities)) {
            this.tacticsState[position].spell_priorities = [];
        }
        if (!this.tacticsState[position].fallback) {
            this.tacticsState[position].fallback = 'approach';
        }
        if (!this.tacticsState[position].movement_goal) {
            this.tacticsState[position].movement_goal = this.defaultMovementGoal();
        }
        return this.tacticsState[position];
    }

    selectedTactics() {
        return this.selectedPosition ? this.ensureTactics(this.selectedPosition) : null;
    }

    changeApproach() {
        this.approachRadioTargets.forEach((radio) => {
            radio.closest('.tactic-option')?.classList.toggle('tactic-option--selected', radio.checked);
        });
        this.renderTacticsPanel();
    }

    changeSlotMovement() {
        const tactics = this.selectedTactics();
        if (!tactics || !this.hasSlotMovementSelectTarget) {
            return;
        }
        tactics.movement_goal = this.slotMovementSelectTarget.value;
        this.renderEngineSummary(tactics);
        this.refreshSlotBadges();
    }

    applyMovementToAll() {
        const goal = this.defaultMovementGoal();
        Object.keys(this.slotsState).forEach((position) => {
            this.ensureTactics(position).movement_goal = goal;
        });
        this.renderTacticsPanel();
        this.refreshSlotBadges();
    }

    defaultMovementGoal() {
        if (this.hasMovementGoalSelectTarget && this.movementGoalSelectTarget.value) {
            return this.movementGoalSelectTarget.value;
        }
        return 'advance_to_melee';
    }

    currentApproach() {
        const selected = this.approachRadioTargets.find((radio) => radio.checked);
        return selected ? selected.value : 'balanced';
    }

    renderEngineSummary(tactics) {
        if (!this.hasEngineTargetingTarget || !this.hasEngineSpellsTarget || !this.hasEngineMovementTarget) {
            return;
        }

        const approach = this.currentApproach();
        let targeting;
        if (tactics.target_order.length > 0) {
            const order = tactics.target_order.map((slot) => this.slotLabel(slot)).join(this.orderArrowValue || ' → ');
            targeting = this.interpolate(this.summaryTargetingL1Value, { order });
            const fallbackLabel = this.fallbackLabelsValue?.[tactics.fallback] || tactics.fallback;
            targeting += ' — ' + this.interpolate(this.summaryFallbackValue, { fallback: fallbackLabel });
        } else if (approach === 'defensive') {
            targeting = this.summaryTargetingL0DefensiveValue;
        } else {
            const slots = this.l0OrdersValue?.[approach] || this.l0OrdersValue?.balanced || [];
            const order = slots.map((slot) => this.slotLabel(slot)).join(this.orderArrowValue || ' → ');
            targeting = this.interpolate(this.summaryTargetingL0Value, { order });
        }

        let spells;
        if (tactics.spell_priorities.length > 0) {
            spells = this.interpolate(this.summarySpellsL2Value, { count: String(tactics.spell_priorities.length) });
        } else {
            const threshold = this.healThresholdsValue?.[approach] ?? 40;
            spells = this.interpolate(this.summarySpellsL0Value, { threshold: String(threshold) });
        }

        const goal = tactics.movement_goal || 'advance_to_melee';
        const goalLabel = this.movementLabelsValue?.[goal] || goal;
        const movement = this.interpolate(this.summaryMovementValue, { goal: goalLabel });

        this.engineTargetingTarget.textContent = targeting;
        this.engineSpellsTarget.textContent = spells;
        this.engineMovementTarget.textContent = movement;
    }

    refreshSlotBadges() {
        this.gridSlotTargets.forEach((slot) => {
            const position = slot.dataset.position;
            if (!position) {
                return;
            }
            const tactics = this.ensureTactics(position);
            const hasL1 = tactics.target_order.length > 0;
            const hasL2 = tactics.spell_priorities.length > 0;
            slot.querySelector('[data-badge-l0]')?.classList.toggle('hidden', hasL1 || hasL2);
            slot.querySelector('[data-badge-l1]')?.classList.toggle('hidden', !hasL1);
            slot.querySelector('[data-badge-l2]')?.classList.toggle('hidden', !hasL2);
            const movement = slot.querySelector('[data-badge-movement]');
            if (movement) {
                const goal = tactics.movement_goal || 'advance_to_melee';
                movement.textContent = this.movementShortValue?.[goal] || goal;
            }
        });
    }

    interpolate(template, values) {
        return Object.entries(values).reduce((text, [key, value]) => {
            return text.replaceAll(`%${key}%`, value);
        }, template || '');
    }

    slotLabel(position) {
        if (this.hasSlotLabelsValue && this.slotLabelsValue[position]) {
            return this.slotLabelsValue[position];
        }
        return position;
    }

    heroSpells(heroId) {
        if (!heroId || !this.hasHeroSpellsValue) {
            return [];
        }
        return this.heroSpellsValue[heroId] || this.heroSpellsValue[String(heroId)] || [];
    }

    spellRowFromEvent(e) {
        const tactics = this.selectedTactics();
        const spellId = parseInt(e.currentTarget.closest('[data-spell-id]')?.dataset.spellId, 10);
        if (!tactics || !spellId) {
            return null;
        }
        return tactics.spell_priorities.find((row) => row.spell_id === spellId) || null;
    }

    moveListItem(key, value, direction) {
        const tactics = this.selectedTactics();
        if (!tactics || !value) {
            return;
        }
        const list = tactics[key];
        const index = list.indexOf(value);
        const next = index + direction;
        if (index < 0 || next < 0 || next >= list.length) {
            return;
        }
        [list[index], list[next]] = [list[next], list[index]];
        this.renderTacticsPanel();
    }

    moveSpell(spellId, direction) {
        const tactics = this.selectedTactics();
        if (!tactics || !spellId) {
            return;
        }
        const list = tactics.spell_priorities;
        const index = list.findIndex((row) => row.spell_id === spellId);
        const next = index + direction;
        if (index < 0 || next < 0 || next >= list.length) {
            return;
        }
        [list[index], list[next]] = [list[next], list[index]];
        this.renderTacticsPanel();
    }

    swapSpellPriorities(fromPosition, toPosition) {
        const from = this.ensureTactics(fromPosition);
        const to = this.ensureTactics(toPosition);
        const saved = from.spell_priorities;
        from.spell_priorities = to.spell_priorities;
        to.spell_priorities = saved;
    }

    moveSpellPriorities(fromPosition, toPosition) {
        const from = this.ensureTactics(fromPosition);
        const to = this.ensureTactics(toPosition);
        to.spell_priorities = from.spell_priorities;
        from.spell_priorities = [];
    }

    filterSpellPriorities(position, heroId) {
        const tactics = this.ensureTactics(position);
        const spells = this.heroSpells(heroId);
        tactics.spell_priorities = tactics.spell_priorities.filter((row) =>
            spells.some((spell) => spell.id === row.spell_id)
        );
    }

    clearSpellPriorities(position) {
        this.ensureTactics(position).spell_priorities = [];
    }

    cloneTactics(raw) {
        const cloned = {};
        Object.entries(raw || {}).forEach(([position, value]) => {
            cloned[position] = {
                target_order: Array.isArray(value?.target_order) ? [...value.target_order] : [],
                fallback: value?.fallback || 'approach',
                spell_priorities: Array.isArray(value?.spell_priorities)
                    ? value.spell_priorities.map((row) => ({ ...row }))
                    : [],
                movement_goal: value?.movement_goal || 'advance_to_melee'
            };
        });
        return cloned;
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
