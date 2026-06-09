import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'formationSelect',
        'nameInput',
        'approachRadio',
        'defaultCheckbox',
        'gridSlot',
        'poolHero',
        'alert',
        'alertMessage'
    ];

    connect() {
        // Parse current state of slots based on DOM elements
        this.slotsState = {};
        this.initializeSlotsState();
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

    assignHero(e) {
        e.preventDefault();
        const heroId = parseInt(e.currentTarget.dataset.heroId, 10);
        const position = e.currentTarget.dataset.position;

        // Check if hero is already assigned to a different slot
        const oldPos = Object.keys(this.slotsState).find(pos => this.slotsState[pos] === heroId);
        if (oldPos) {
            this.slotsState[oldPos] = null;
        }

        this.slotsState[position] = heroId;
        this.syncUI();
    }

    removeHero(e) {
        e.preventDefault();
        const position = e.currentTarget.dataset.position;
        this.slotsState[position] = null;
        this.syncUI();
    }

    quickAssign(e) {
        e.preventDefault();
        const select = e.currentTarget;
        const heroId = select.value ? parseInt(select.value, 10) : null;
        const position = select.dataset.position;

        if (!heroId) {
            this.slotsState[position] = null;
            this.syncUI();
            return;
        }

        // If hero is already in another slot, remove them
        const oldPos = Object.keys(this.slotsState).find(pos => this.slotsState[pos] === heroId);
        if (oldPos) {
            this.slotsState[oldPos] = null;
        }

        this.slotsState[position] = heroId;
        this.syncUI();
    }

    syncUI() {
        // Redraw slot content based on slotsState
        this.gridSlotTargets.forEach(slot => {
            const position = slot.dataset.position;
            const heroId = this.slotsState[position];

            const emptyView = slot.querySelector('[data-empty-view]');
            const occupiedView = slot.querySelector('[data-occupied-view]');
            const select = slot.querySelector('[data-quick-select]');

            if (heroId) {
                if (emptyView) emptyView.classList.add('hidden');
                if (occupiedView) {
                    occupiedView.classList.remove('hidden');
                    
                    // Update name and details in occupied view
                    const nameElem = occupiedView.querySelector('[data-hero-name]');
                    const detailsElem = occupiedView.querySelector('[data-hero-details]');
                    const removeBtn = occupiedView.querySelector('[data-action="click->formation#removeHero"]');
                    
                    const heroCard = this.poolHeroTargets.find(hc => parseInt(hc.dataset.heroId, 10) === heroId);
                    if (heroCard) {
                        nameElem.textContent = heroCard.dataset.name;
                        detailsElem.textContent = `Lvl ${heroCard.dataset.level} ${heroCard.dataset.race}`;
                    }
                    if (removeBtn) {
                        removeBtn.dataset.position = position;
                    }
                }
            } else {
                if (emptyView) emptyView.classList.remove('hidden');
                if (occupiedView) occupiedView.classList.add('hidden');
            }

            // Sync dropdown values
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
                card.classList.add('opacity-40');
                if (badge) badge.classList.remove('hidden');
            } else {
                card.classList.remove('opacity-40');
                if (badge) badge.classList.add('hidden');
            }
        });

        // Sync options disabled state in quick-selects
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
            this.showAlert('error', 'Formation name cannot be empty.');
            return;
        }

        const approachRadio = this.approachRadioTargets.find(r => r.checked);
        const approach = approachRadio ? approachRadio.value : 'balanced';
        const isDefault = this.hasDefaultCheckboxTarget ? this.defaultCheckboxTarget.checked : false;

        // Compile slots payload
        const slots = Object.entries(this.slotsState).map(([pos, heroId]) => {
            return {
                position: pos,
                hero_id: heroId,
                strategy: {},
                spell_priorities: []
            };
        });

        // Validate: standard formations require at least one hero
        const assignedCount = slots.filter(s => s.hero_id !== null).length;
        if (assignedCount === 0) {
            this.showAlert('error', 'You must assign at least one hero to the formation.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Saving...';

        const formationId = this.hasFormationSelectTarget && this.formationSelectTarget.value 
            ? parseInt(this.formationSelectTarget.value, 10) 
            : null;

        const payload = {
            id: formationId,
            name: name,
            approach: approach,
            is_default: isDefault,
            slots: slots
        };

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

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
                throw new Error(result.error || 'Failed to save formation.');
            }

            this.showAlert('success', 'Formation lineup and tactics saved successfully.');
            
            setTimeout(() => {
                // Redirect to saved formation ID
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('formation_id', result.id);
                window.location.search = urlParams.toString();
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = 'Save Lineup';
        }
    }

    async delete(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const formationId = btn.dataset.formationId;

        if (!formationId) return;
        if (!confirm('Are you sure you want to delete this formation?')) return;

        btn.disabled = true;
        btn.textContent = 'Deleting...';

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const response = await fetch(`/api/v1/formations/${formationId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();

            if (!response.ok || result.error) {
                throw new Error(result.error || 'Failed to delete formation.');
            }

            this.showAlert('success', 'Formation deleted successfully.');
            setTimeout(() => {
                window.location.search = '';
            }, 1000);

        } catch (error) {
            this.showAlert('error', error.message);
            btn.disabled = false;
            btn.textContent = 'Delete';
        }
    }

    showAlert(type, message) {
        if (!this.hasAlertTarget || !this.hasAlertMessageTarget) return;
        this.alertMessageTarget.textContent = message;
        this.alertTarget.className = 'mb-6 rounded-lg px-4 py-3 text-sm flex items-center justify-between border ';

        if (type === 'success') {
            this.alertTarget.classList.add('bg-green-950/40', 'text-green-300', 'border-green-900/50');
        } else {
            this.alertTarget.classList.add('bg-red-950/40', 'text-red-300', 'border-red-900/50');
        }
        this.alertTarget.classList.remove('hidden');
    }

    closeAlert(e) {
        e.preventDefault();
        if (this.hasAlertTarget) {
            this.alertTarget.classList.add('hidden');
        }
    }
}
