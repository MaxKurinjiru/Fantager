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

    prevWeek() {
        this.weekOffset -= 1;
        this.reload();
    }

    nextWeek() {
        this.weekOffset += 1;
        this.reload();
    }

    async reload() {
        const start = new Date();
        start.setHours(0, 0, 0, 0);
        start.setDate(start.getDate() + this.weekOffset * 7);

        const end = new Date(start);
        end.setDate(end.getDate() + 7);

        this.rangeLabelTarget.textContent = `${this.formatDate(start)} – ${this.formatDate(end)}`;
        this.feedContainerTarget.innerHTML = `<div class="flex justify-center py-12"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-emerald-500"></div></div>`;

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
            if (!response.ok) throw new Error('Failed to load calendar');
            let events = await response.json();

            if (this.teamOnlyToggleTarget.checked) {
                events = events.filter(e => e.visibility === 'team_only');
            }

            this.renderFeed(events);
        } catch (error) {
            this.feedContainerTarget.innerHTML = `<p class="text-center text-red-400 py-8">${this.translationsValue.error || 'Failed to load calendar.'}</p>`;
        }
    }

    renderFeed(events) {
        if (events.length === 0) {
            this.feedContainerTarget.innerHTML = `<p class="text-center text-gray-550 py-12">${this.translationsValue.empty || 'No events in this period.'}</p>`;
            return;
        }

        this.feedContainerTarget.innerHTML = events.map(event => {
            const date = new Date(event.scheduledAt);
            const typeLabel = this.typeLabel(event.type);
            const statusClass = event.status === 'completed' ? 'text-green-400' : 'text-amber-400';

            return `
                <article class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center gap-3 backdrop-blur-md">
                    <div class="shrink-0 text-center md:w-24">
                        <div class="text-xs uppercase text-gray-500 font-bold">${date.toLocaleDateString(undefined, { weekday: 'short' })}</div>
                        <div class="text-lg font-extrabold text-white">${date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}</div>
                        <div class="text-xs text-gray-550">${date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}</div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-gray-950 border border-gray-850 text-gray-400">${typeLabel}</span>
                            <span class="text-[10px] font-bold ${statusClass}">${event.status}</span>
                        </div>
                        <h3 class="font-bold text-gray-100 truncate">${this.escapeHtml(event.title)}</h3>
                        <p class="text-xs text-gray-550 truncate">${this.escapeHtml(event.description || '')}</p>
                    </div>
                </article>
            `;
        }).join('');
    }

    typeLabel(type) {
        const map = {
            system_tick: this.translationsValue.type_system_tick || 'System',
            league_match: this.translationsValue.type_league_match || 'League',
            world_event: this.translationsValue.type_world_event || 'Event',
            training_queue: this.translationsValue.type_training_queue || 'Training',
        };
        return map[type] || type;
    }

    formatDate(date) {
        return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
