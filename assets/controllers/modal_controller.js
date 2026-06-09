import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];
    static values = { eventName: String };

    connect() {
        this.escHandler = this.onEsc.bind(this);
        if (this.hasEventNameValue) {
            this.openHandler = this.open.bind(this);
            window.addEventListener(this.eventNameValue, this.openHandler);
        }
    }

    disconnect() {
        document.removeEventListener('keydown', this.escHandler);
        if (this.hasEventNameValue) {
            window.removeEventListener(this.eventNameValue, this.openHandler);
        }
    }

    open(event) {
        if (event) event.preventDefault();
        this.dialogTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', this.escHandler);
    }

    close(event) {
        if (event) event.preventDefault();
        this.dialogTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this.escHandler);
    }

    closeBackdrop(event) {
        if (event.target === event.currentTarget) {
            this.close();
        }
    }

    onEsc(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
