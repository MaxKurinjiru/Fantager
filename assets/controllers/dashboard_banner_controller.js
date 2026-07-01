import { Controller } from '@hotwired/stimulus';

/** Dashboard team banner — profile modal and history modal trigger. */
export default class extends Controller {
    connect() {
        console.log('DashboardBannerController connected');
    }

    openProfileModal(e) {
        e.preventDefault();
        console.log('openProfileModal clicked');
        window.dispatchEvent(new CustomEvent('modal:open-team-profile'));
    }

    openHistoryModal(e) {
        e.preventDefault();
        const card = e.target.closest('[data-metric]');
        const metric = card ? card.dataset.metric : null;
        console.log('openHistoryModal clicked. Card:', card, 'Metric:', metric);
        if (metric) {
            window.dispatchEvent(new CustomEvent('modal:open-team-history', {
                detail: { metric: metric }
            }));
        }
    }
}
