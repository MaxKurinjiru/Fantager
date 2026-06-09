import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'raceFilter', 'statusFilter', 'sortBy', 'grid', 'card'];

    filter() {
        const query = this.searchTarget.value.toLowerCase().trim();
        const race = this.raceFilterTarget.value;
        const status = this.statusFilterTarget.value;

        this.cardTargets.forEach(card => {
            const cardName = card.dataset.name || '';
            const cardRace = card.dataset.race || '';
            const cardStatus = card.dataset.status || '';

            const nameMatch = cardName.includes(query);
            const raceMatch = !race || cardRace === race;
            const statusMatch = !status || cardStatus === status;

            if (nameMatch && raceMatch && statusMatch) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    sort() {
        const sortValue = this.sortByTarget.value;
        const [field, direction] = sortValue.split('-');
        const isDesc = direction === 'desc';

        const cards = Array.from(this.cardTargets);

        cards.sort((a, b) => {
            let valA, valB;

            if (field === 'name') {
                valA = a.dataset.name || '';
                valB = b.dataset.name || '';
                return isDesc ? valB.localeCompare(valA) : valA.localeCompare(valB);
            }

            // Numeric sorting
            valA = parseFloat(a.dataset[field] || '0');
            valB = parseFloat(b.dataset[field] || '0');

            if (valA === valB) {
                // Secondary sort by name
                const nameA = a.dataset.name || '';
                const nameB = b.dataset.name || '';
                return nameA.localeCompare(nameB);
            }

            return isDesc ? valB - valA : valA - valB;
        });

        // Re-append sorted card elements to the grid
        const grid = this.gridTarget;
        cards.forEach(card => grid.appendChild(card));
    }
}
