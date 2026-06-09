import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'backdrop'];

    open() {
        this.menuTarget.classList.remove('-translate-x-full');
        this.backdropTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.menuTarget.classList.add('-translate-x-full');
        this.backdropTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    toggle() {
        if (this.menuTarget.classList.contains('-translate-x-full')) {
            this.open();
        } else {
            this.close();
        }
    }
}
