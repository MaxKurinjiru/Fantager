import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    toggleRound(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const card = button.closest('.battle-round-card');
        if (!card) return;
        
        const content = card.querySelector('.battle-round-content');
        const icon = card.querySelector('.battle-round-toggle-icon');

        if (content) {
            const isHidden = content.classList.toggle('hidden');
            if (icon) {
                icon.textContent = isHidden ? '▼' : '▲';
            }
        }
    }
}
