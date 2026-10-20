import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['slotFilter', 'styleFilter', 'item', 'emptyFiltered'];

    filter() {
        const slot = this.hasSlotFilterTarget ? this.slotFilterTarget.value : '';
        const style = this.hasStyleFilterTarget ? this.styleFilterTarget.value : '';
        let visible = 0;

        this.itemTargets.forEach((row) => {
            const slotMatch = !slot || row.dataset.slot === slot;
            const styleMatch = !style || row.dataset.subtype === style;
            const show = slotMatch && styleMatch;
            row.classList.toggle('hidden', !show);
            if (show) {
                visible += 1;
            }
        });

        if (this.hasEmptyFilteredTarget) {
            this.emptyFilteredTarget.classList.toggle('hidden', visible > 0 || this.itemTargets.length === 0);
        }
    }
}
