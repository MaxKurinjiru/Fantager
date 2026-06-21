import { Controller } from '@hotwired/stimulus';

/**
 * player-profile controller
 *
 * Opens the player profile modal by fetching /api/v1/players/{userId}/profile.
 * The modal element with id="player-profile-modal" must be present in the DOM.
 *
 * Usage in Twig:
 *   <button
 *     data-controller="player-profile"
 *     data-action="click->player-profile#open"
 *     data-player-profile-user-id-param="{{ user.id }}"
 *     data-player-profile-own-user-id-value="{{ app.user.id }}"
 *     data-player-profile-translations-value="{{ {...}|json_encode|e('html_attr') }}"
 *   >Player name</button>
 */
export default class extends Controller {
    static values = {
        ownUserId: Number,
        translations: Object,
    };

    /**
     * @param {Event} event  — must carry `userId` or `teamId` param
     */
    async open(event) {
        event.preventDefault();
        event.stopPropagation();

        const userId = event.params?.userId ?? event.currentTarget?.dataset?.playerProfileUserIdParam;
        const teamId = event.params?.teamId ?? event.currentTarget?.dataset?.playerProfileTeamIdParam;
        if (!userId && !teamId) return;

        const modal = document.getElementById('player-profile-modal');
        if (!modal) return;

        this._showLoading(modal);
        window.dispatchEvent(new CustomEvent('modal:open-player-profile'));

        try {
            const url = userId
                ? `/api/v1/players/${userId}/profile`
                : `/api/v1/teams/${teamId}/profile`;
            const response = await fetch(url);
            if (!response.ok) {
                this._showError(modal);
                return;
            }
            const data = await response.json();
            this._render(modal, data);
        } catch {
            this._showError(modal);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    _showLoading(modal) {
        const content = modal.querySelector('.js-profile-content');
        const loading = modal.querySelector('.js-profile-loading');
        const error = modal.querySelector('.js-profile-error');
        if (content) content.classList.add('hidden');
        if (loading) loading.classList.remove('hidden');
        if (error) error.classList.add('hidden');
    }

    _showError(modal) {
        const content = modal.querySelector('.js-profile-content');
        const loading = modal.querySelector('.js-profile-loading');
        const error = modal.querySelector('.js-profile-error');
        if (content) content.classList.add('hidden');
        if (loading) loading.classList.add('hidden');
        if (error) error.classList.remove('hidden');
    }

    _render(modal, data) {
        const loading = modal.querySelector('.js-profile-loading');
        const error = modal.querySelector('.js-profile-error');
        const content = modal.querySelector('.js-profile-content');
        if (loading) loading.classList.add('hidden');
        if (error) error.classList.add('hidden');
        if (!content) return;

        // Team identity
        const emblem = modal.querySelector('.js-profile-emblem');
        const teamName = modal.querySelector('.js-profile-team-name');
        const playerName = modal.querySelector('.js-profile-player-name');
        const memberSince = modal.querySelector('.js-profile-member-since');

        if (emblem) emblem.textContent = data.team?.emblem ?? '🛡️';
        if (teamName) teamName.textContent = data.team?.name ?? '';
        if (playerName) playerName.textContent = data.user?.display_name ?? '';

        if (memberSince && data.user?.member_since) {
            const date = new Date(data.user.member_since);
            memberSince.textContent = date.toLocaleDateString(undefined, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        }

        // Team colors
        const colorBar = modal.querySelector('.js-profile-color-bar');
        if (colorBar && data.team?.colors) {
            colorBar.style.setProperty('--profile-color-primary', data.team.colors.primary ?? '');
            colorBar.style.setProperty('--profile-color-secondary', data.team.colors.secondary ?? '');
            colorBar.classList.remove('hidden');
        } else if (colorBar) {
            colorBar.classList.add('hidden');
        }

        // Stats
        this._setStat(modal, '.js-profile-fan-base', data.team?.fan_base);
        this._setStat(modal, '.js-profile-reputation', data.team?.reputation);
        this._setStat(modal, '.js-profile-combatants', data.team?.combatant_count);
        this._setStat(modal, '.js-profile-trainers', data.team?.trainer_count);

        // League
        const leagueSection = modal.querySelector('.js-profile-league');
        if (leagueSection) {
            if (data.league) {
                leagueSection.classList.remove('hidden');
                this._setStat(modal, '.js-profile-tier', `${data.league.tier_name} / ${data.league.group_name}`);
                this._setStat(modal, '.js-profile-position', `#${data.league.position}`);
                this._setStat(modal, '.js-profile-points', data.league.points);

                // Form badges
                const formContainer = modal.querySelector('.js-profile-form');
                if (formContainer) {
                    formContainer.innerHTML = '';
                    (data.league.form ?? []).forEach((result) => {
                        const badge = document.createElement('span');
                        badge.className = `league-form-badge league-form-badge--${result === 'W' ? 'win' : result === 'D' ? 'draw' : 'loss'}`;
                        badge.textContent = result;
                        formContainer.appendChild(badge);
                    });
                    if ((data.league.form ?? []).length === 0) {
                        const dash = document.createElement('span');
                        dash.className = 'league-form-empty';
                        dash.textContent = '—';
                        formContainer.appendChild(dash);
                    }
                }
            } else {
                leagueSection.classList.add('hidden');
            }
        }

        // Message button
        const msgBtn = modal.querySelector('.js-profile-message-btn');
        if (msgBtn) {
            if (data.can_message) {
                msgBtn.classList.remove('hidden');
                msgBtn.dataset.receiverUserId = data.user?.id ?? '';
                msgBtn.dataset.receiverName = data.user?.display_name ?? '';
            } else {
                msgBtn.classList.add('hidden');
            }
        }

        content.classList.remove('hidden');
    }

    _setStat(modal, selector, value) {
        const el = modal.querySelector(selector);
        if (!el) return;
        if (value === null || value === undefined) {
            el.textContent = '—';
        } else {
            el.textContent = value;
        }
    }

    /**
     * Triggered by the "Napsat zprávu" button inside the modal.
     * Dispatches the compose-message event with receiver pre-filled.
     */
    openCompose(event) {
        event.preventDefault();
        const btn = event.currentTarget;
        const userId = btn.dataset.receiverUserId;
        const name = btn.dataset.receiverName;
        if (!userId) return;

        window.dispatchEvent(new CustomEvent('modal:open-compose', {
            detail: { prefillUserId: Number(userId), prefillName: name },
        }));
    }
}
