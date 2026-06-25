import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['round', 'roundSelect', 'prevBtn', 'nextBtn'];
    static values = {
        activeRound: Number
    };

    connect() {
        this.updateView();
    }

    activeRoundValueChanged() {
        this.updateView();
    }

    previous() {
        if (this.activeRoundValue > 1) {
            this.activeRoundValue--;
        }
    }

    next() {
        if (this.activeRoundValue < this.roundTargets.length) {
            this.activeRoundValue++;
        }
    }

    changeRound(e) {
        const val = parseInt(e.currentTarget.value, 10);
        if (!isNaN(val) && val >= 1 && val <= this.roundTargets.length) {
            this.activeRoundValue = val;
        }
    }

    updateView() {
        const activeRoundNum = this.activeRoundValue;
        const totalRounds = this.roundTargets.length;

        // Toggle visibility of each round container
        this.roundTargets.forEach(roundEl => {
            const roundNum = parseInt(roundEl.dataset.roundNumber, 10);
            roundEl.classList.toggle('hidden', roundNum !== activeRoundNum);
        });

        // Update the select dropdown value
        if (this.hasRoundSelectTarget) {
            this.roundSelectTarget.value = activeRoundNum;
        }

        // Enable/disable navigation buttons at the boundaries
        if (this.hasPrevBtnTarget) {
            this.prevBtnTarget.disabled = (activeRoundNum <= 1);
        }
        if (this.hasNextBtnTarget) {
            this.nextBtnTarget.disabled = (activeRoundNum >= totalRounds);
        }
    }
}
