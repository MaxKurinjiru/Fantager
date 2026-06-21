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
        const start = this.getWeekMonday(new Date(), this.weekOffset);
        const end = new Date(start);
        end.setDate(end.getDate() + 7);

        const sunday = new Date(start);
        sunday.setDate(sunday.getDate() + 6);

        this.weekStart = start;
        this.rangeLabelTarget.textContent = `${this.formatDate(start)} – ${this.formatDate(sunday)}`;
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

        const dayTemplate = document.getElementById('template-calendar-day');
        const eventTemplate = document.getElementById('template-calendar-event');
        const eventsByDay = this.groupEventsByDay(events);
        const todayKey = this.dayKey(new Date());

        this.iterWeekDays().forEach(dayDate => {
            const dayNode = dayTemplate.content.cloneNode(true);
            const key = this.dayKey(dayDate);
            const dayEvents = eventsByDay.get(key) ?? [];

            dayNode.querySelector('.js-cal-day-weekday').textContent = dayDate.toLocaleDateString(undefined, { weekday: 'long' });
            dayNode.querySelector('.js-cal-day-date').textContent = dayDate.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });

            const section = dayNode.querySelector('.cal-day-group');
            if (key === todayKey) {
                section.classList.add('cal-day-group--today');
                dayNode.querySelector('.js-cal-day-today').classList.remove('hidden');
            }

            const eventsContainer = dayNode.querySelector('.js-cal-day-events');
            const emptyEl = dayNode.querySelector('.js-cal-day-empty');

            if (dayEvents.length === 0) {
                emptyEl.textContent = this.translationsValue.day_empty;
                emptyEl.classList.remove('hidden');
            } else {
                this.renderDayEvents(dayEvents, eventsContainer, eventTemplate);
            }

            this.feedContainerTarget.appendChild(dayNode);
        });
    }

    renderDayEvents(dayEvents, container, eventTemplate) {
        this.consolidateLeagueMatches(dayEvents).forEach(item => {
            if (item.kind === 'single') {
                container.appendChild(this.buildEventNode(item.event, eventTemplate));
                return;
            }

            container.appendChild(this.buildLeagueBatchNode(item.events));
        });
    }

    consolidateLeagueMatches(events) {
        const result = [];
        let index = 0;

        while (index < events.length) {
            const event = events[index];

            if (event.type !== 'league_match') {
                result.push({ kind: 'single', event });
                index += 1;
                continue;
            }

            const batch = [event];
            const slotKey = event.scheduledAt;
            index += 1;

            while (
                index < events.length
                && events[index].type === 'league_match'
                && events[index].scheduledAt === slotKey
            ) {
                batch.push(events[index]);
                index += 1;
            }

            result.push(
                batch.length === 1
                    ? { kind: 'single', event: batch[0] }
                    : { kind: 'batch', events: batch },
            );
        }

        return result;
    }

    buildLeagueBatchNode(events) {
        const template = document.getElementById('template-calendar-league-batch');
        const fixtureTemplate = document.getElementById('template-calendar-fixture');
        const node = template.content.cloneNode(true);
        const first = events[0];
        const date = new Date(first.scheduledAt);

        node.querySelector('.js-cal-time').textContent = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        node.querySelector('.js-cal-time').dateTime = first.scheduledAt;
        node.querySelector('.js-cal-type').textContent = this.typeLabel('league_match');
        node.querySelector('.js-cal-batch-count').textContent = this.translationsValue.league_round_count.replace(
            '%count%',
            String(events.length),
        );
        node.querySelector('.js-cal-batch-title').textContent = this.translationsValue.league_round_title;

        const statusEl = node.querySelector('.js-cal-status');
        statusEl.textContent = this.statusLabel(first.status);
        statusEl.classList.add(`cal-event__status--${first.status}`);

        const details = node.querySelector('.cal-event__batch');
        if (events.some(event => this.isOwnFixture(event))) {
            details.open = true;
        }

        const list = node.querySelector('.js-cal-fixtures');
        events.forEach(event => {
            const fixtureNode = fixtureTemplate.content.cloneNode(true);
            const row = fixtureNode.querySelector('.cal-event__fixture');

            fixtureNode.querySelector('.js-cal-fixture-match').textContent = event.title;
            fixtureNode.querySelector('.js-cal-fixture-group').textContent = event.description || '';

            if (this.isOwnFixture(event)) {
                row.classList.add('cal-event__fixture--own');
            }

            list.appendChild(fixtureNode);
        });

        return node;
    }

    isOwnFixture(event) {
        if (event.type !== 'league_match') {
            return false;
        }

        const teamId = this.teamIdValue;
        const homeId = event.metadata?.homeTeam?.id;
        const awayId = event.metadata?.awayTeam?.id;

        return homeId === teamId || awayId === teamId;
    }

    buildEventNode(event, template) {
        const node = template.content.cloneNode(true);
        const date = new Date(event.scheduledAt);

        node.querySelector('.js-cal-time').textContent = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        node.querySelector('.js-cal-time').dateTime = event.scheduledAt;

        node.querySelector('.js-cal-type').textContent = this.typeLabel(event.type);

        const statusEl = node.querySelector('.js-cal-status');
        statusEl.textContent = this.statusLabel(event.status);
        statusEl.classList.add(`cal-event__status--${event.status}`);

        node.querySelector('.js-cal-title').textContent = event.title;
        node.querySelector('.js-cal-description').textContent = event.description || '';

        return node;
    }

    groupEventsByDay(events) {
        const grouped = new Map();

        events.forEach(event => {
            const key = this.dayKey(new Date(event.scheduledAt));
            if (!grouped.has(key)) {
                grouped.set(key, []);
            }
            grouped.get(key).push(event);
        });

        grouped.forEach(dayEvents => {
            dayEvents.sort((a, b) => new Date(a.scheduledAt) - new Date(b.scheduledAt));
        });

        return grouped;
    }

    iterWeekDays() {
        const days = [];
        for (let i = 0; i < 7; i++) {
            const day = new Date(this.weekStart);
            day.setDate(day.getDate() + i);
            days.push(day);
        }
        return days;
    }

    getWeekMonday(referenceDate, weekOffset) {
        const monday = new Date(referenceDate);
        monday.setHours(0, 0, 0, 0);

        const weekday = monday.getDay();
        const daysFromMonday = weekday === 0 ? -6 : 1 - weekday;
        monday.setDate(monday.getDate() + daysFromMonday + weekOffset * 7);

        return monday;
    }

    dayKey(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    typeLabel(type) {
        const key = {
            system_tick: 'type_system_tick',
            league_match: 'type_league_match',
            hero_training_history: 'type_hero_training_history',
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
