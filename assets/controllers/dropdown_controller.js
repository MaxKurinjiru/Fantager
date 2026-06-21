import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this.closeHandler = this.closeOnOutsideClick.bind(this);
        document.addEventListener('click', this.closeHandler);
    }

    disconnect() {
        document.removeEventListener('click', this.closeHandler);
    }

    toggle(event) {
        event.stopPropagation();
        this.menuTarget.classList.toggle('hidden');
    }

    closeOnOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.add('hidden');
        }
    }
}
