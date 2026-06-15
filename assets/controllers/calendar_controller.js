import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['feedContainer', 'rangeLabel', 'teamOnlyToggle', 'systemToggle'];
    static values = {
        kingdomId: Number,
        teamId: Number,
        timezone: String,
        translations: Object,
    };

    connect() {
        this.weekOffset = 0;
        this.reload();
    }

    disconnect() {
        this.weekOffset = 0;
    }

    prevWeek() {
        this.weekOffset -= 1;
        this.reload();
    }

    nextWeek() {
        this.weekOffset += 1;
        this.reload();
    }

    showLoading() {
        this.feedContainerTarget.replaceChildren();
        const template = document.getElementById('template-calendar-loading');
        this.feedContainerTarget.appendChild(template.content.cloneNode(true));
    }

    async reload() {
        const start = new Date();
        start.setHours(0, 0, 0, 0);
        start.setDate(start.getDate() + this.weekOffset * 7);

        const end = new Date(start);
        end.setDate(end.getDate() + 7);

        this.rangeLabelTarget.textContent = `${this.formatDate(start)} – ${this.formatDate(end)}`;
        this.showLoading();

        const params = new URLSearchParams({
            start: start.toISOString(),
            end: end.toISOString(),
            teamId: String(this.teamIdValue),
        });

        if (this.systemToggleTarget.checked) {
            params.set('include_system', 'true');
        }

        try {
            const response = await fetch(`/api/v1/kingdom/${this.kingdomIdValue}/calendar?${params}`);
            if (!response.ok) throw new Error();
            let events = await response.json();

            if (this.teamOnlyToggleTarget.checked) {
                events = events.filter(e => e.visibility === 'team_only');
            }

            this.renderFeed(events);
        } catch (error) {
            this.feedContainerTarget.replaceChildren();
            const errorNode = document.getElementById('template-calendar-error').content.cloneNode(true);
            errorNode.querySelector('.js-cal-error').textContent = this.translationsValue.error;
            this.feedContainerTarget.appendChild(errorNode);
        }
    }

    renderFeed(events) {
        this.feedContainerTarget.replaceChildren();

        if (events.length === 0) {
            const emptyNode = document.getElementById('template-calendar-empty').content.cloneNode(true);
            emptyNode.querySelector('.js-cal-empty').textContent = this.translationsValue.empty;
            this.feedContainerTarget.appendChild(emptyNode);
            return;
        }

        const template = document.getElementById('template-calendar-event');

        events.forEach(event => {
            const node = template.content.cloneNode(true);
            const date = new Date(event.scheduledAt);

            node.querySelector('.js-cal-weekday').textContent = date.toLocaleDateString(undefined, { weekday: 'short' });
            node.querySelector('.js-cal-day').textContent = date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
            node.querySelector('.js-cal-time').textContent = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

            node.querySelector('.js-cal-type').textContent = this.typeLabel(event.type);

            const statusEl = node.querySelector('.js-cal-status');
            statusEl.textContent = this.statusLabel(event.status);
            statusEl.classList.add(`cal-event__status--${event.status}`);

            node.querySelector('.js-cal-title').textContent = event.title;
            node.querySelector('.js-cal-description').textContent = event.description || '';

            this.feedContainerTarget.appendChild(node);
        });
    }

    typeLabel(type) {
        const key = {
            system_tick: 'type_system_tick',
            league_match: 'type_league_match',
            world_event: 'type_world_event',
            training_queue: 'type_training_queue',
        }[type];
        return key ? this.translationsValue[key] : type;
    }

    statusLabel(status) {
        const key = `status_${status}`;
        return this.translationsValue[key] ?? status;
    }

    formatDate(date) {
        return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    }
}
