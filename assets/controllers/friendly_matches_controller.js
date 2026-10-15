import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';

export default class extends Controller {
    static targets = [
        'opponent', 'slots', 'slotsHint', 'submitBtn', 'alert', 'alertMessage',
    ];

    static values = {
        teamId: Number,
        slotsUrl: String,
        scheduleUrl: String,
        acceptUrlTemplate: String,
        declineUrlTemplate: String,
        cancelUrlTemplate: String,
        translations: Object,
    };

    hideAlert() {
        if (this.hasAlertTarget) {
            hideAlert(this.alertTarget);
        }
    }

    showFeedback(type, message) {
        if (this.hasAlertTarget && this.hasAlertMessageTarget) {
            showAlert(this.alertTarget, this.alertMessageTarget, type, message);
        }
    }

    urlWithId(template, id) {
        return template.replace(/\/0(\/|$)/, `/${id}$1`);
    }

    async loadSlots() {
        const opponentId = this.opponentTarget.value;
        this.slotsTarget.innerHTML = '';
        this.submitBtnTarget.disabled = true;

        if (!opponentId) {
            this.slotsHintTarget.textContent = this.translationsValue.select_opponent || '';
            return;
        }

        this.slotsHintTarget.textContent = this.translationsValue.loading_slots || '';

        try {
            const url = `${this.slotsUrlValue}?opponent_team_id=${encodeURIComponent(opponentId)}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!response.ok) {
                this.slotsHintTarget.textContent = data.error || this.translationsValue.action_failed || '';
                return;
            }

            const slots = data.slots || [];
            if (slots.length === 0) {
                this.slotsHintTarget.textContent = this.translationsValue.no_slots || '';
                return;
            }

            this.slotsHintTarget.textContent = '';
            slots.forEach((slot, index) => {
                const id = `friendly-slot-${index}`;
                const label = document.createElement('label');
                label.className = 'friendly-slot-option';
                label.setAttribute('for', id);

                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'scheduled_at';
                input.id = id;
                input.value = slot;
                input.required = true;
                input.dataset.action = 'change->friendly-matches#onSlotPicked';

                const span = document.createElement('span');
                span.textContent = this.formatSlot(slot);

                label.appendChild(input);
                label.appendChild(span);
                this.slotsTarget.appendChild(label);
            });
        } catch {
            this.slotsHintTarget.textContent = this.translationsValue.action_failed || '';
        }
    }

    onSlotPicked() {
        this.submitBtnTarget.disabled = false;
    }

    formatSlot(iso) {
        try {
            const date = new Date(iso);
            return date.toLocaleString(undefined, {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return iso;
        }
    }

    async challenge(event) {
        event.preventDefault();
        const opponentId = this.opponentTarget.value;
        const selected = this.slotsTarget.querySelector('input[name="scheduled_at"]:checked');
        if (!opponentId || !selected) {
            return;
        }

        this.submitBtnTarget.disabled = true;
        try {
            const response = await fetch(this.scheduleUrlValue, {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    opponent_team_id: Number(opponentId),
                    scheduled_at: selected.value,
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                this.showFeedback('error', data.error || this.translationsValue.action_failed || '');
                this.submitBtnTarget.disabled = false;
                return;
            }
            this.showFeedback('success', this.translationsValue.challenge_sent || '');
            window.location.reload();
        } catch {
            this.showFeedback('error', this.translationsValue.action_failed || '');
            this.submitBtnTarget.disabled = false;
        }
    }

    async accept(event) {
        await this.postAction(this.acceptUrlTemplateValue, event.params.id);
    }

    async decline(event) {
        await this.postAction(this.declineUrlTemplateValue, event.params.id);
    }

    async cancel(event) {
        if (!window.confirm(this.translationsValue.confirm_cancel || '')) {
            return;
        }
        await this.postAction(this.cancelUrlTemplateValue, event.params.id);
    }

    async postAction(template, id) {
        try {
            const response = await fetch(this.urlWithId(template, id), {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
            });
            const data = await response.json();
            if (!response.ok) {
                this.showFeedback('error', data.error || this.translationsValue.action_failed || '');
                return;
            }
            window.location.reload();
        } catch {
            this.showFeedback('error', this.translationsValue.action_failed || '');
        }
    }
}
