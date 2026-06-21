import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'backdrop'];

    open() {
        this.menuTarget.classList.add('mobile-menu--open');
        this.backdropTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.menuTarget.classList.remove('mobile-menu--open');
        this.backdropTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    toggle() {
        if (this.menuTarget.classList.contains('mobile-menu--open')) {
            this.close();
        } else {
            this.open();
        }
    }
}
