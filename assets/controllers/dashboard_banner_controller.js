import { Controller } from '@hotwired/stimulus';

/** Dashboard team banner — profile modal trigger. */
export default class extends Controller {
    openProfileModal(e) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('modal:open-team-profile'));
    }
}
