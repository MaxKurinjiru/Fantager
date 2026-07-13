import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item'];

    toggle(e) {
        if (e) {
            e.preventDefault();
            const btn = e.currentTarget;
            if (btn.dataset.toggleText) {
                const currentText = btn.textContent;
                btn.textContent = btn.dataset.toggleText;
                btn.dataset.toggleText = currentText;
            }
            btn.classList.toggle('btn--active');
        }

        this.itemTargets.forEach(el => {
            el.classList.toggle('hidden');
        });
    }
}
