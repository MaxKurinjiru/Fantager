import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tableBody', 'emptyMessage'];
    static values = {
        translations: Object
    };

    connect() {
        this._onLedgerOpen = () => this.loadRecent();
        window.addEventListener('modal:open-ledger', this._onLedgerOpen);
    }

    disconnect() {
        window.removeEventListener('modal:open-ledger', this._onLedgerOpen);
    }

    openLedgerModal(event) {
        event?.preventDefault();
        window.dispatchEvent(new CustomEvent('modal:open-ledger'));
    }

    typeLabel(type) {
        const key = `type_${type}`;
        return (this.hasTranslationsValue && this.translationsValue[key]) || type;
    }

    async loadRecent() {
        if (!this.hasTableBodyTarget) return;

        this.tableBodyTarget.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-text-muted">…</td></tr>';

        try {
            const response = await fetch('/api/v1/finance/recent');
            if (!response.ok) throw new Error();
            const records = await response.json();
            this.renderRecords(records);
        } catch {
            this.tableBodyTarget.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-red-400">Error</td></tr>';
        }
    }

    renderRecords(records) {
        if (!records.length) {
            this.tableBodyTarget.innerHTML = '';
            if (this.hasEmptyMessageTarget) {
                this.emptyMessageTarget.classList.remove('hidden');
            }
            return;
        }

        if (this.hasEmptyMessageTarget) {
            this.emptyMessageTarget.classList.add('hidden');
        }

        this.tableBodyTarget.replaceChildren();
        records.forEach(record => {
            const row = document.createElement('tr');
            row.className = 'ledger-modal__row';
            const date = new Date(record.created_at);
            const goldClass = record.gold_change > 0 ? 'finance-changes__item--positive' : 'finance-changes__item--negative';
            const goldPrefix = record.gold_change > 0 ? '+' : '';
            row.innerHTML = `
                <td class="p-3 text-sm">${this.escapeHtml(this.typeLabel(record.type))}</td>
                <td class="p-3 text-sm ${goldClass}">${goldPrefix}${record.gold_change.toLocaleString('cs-CZ')} 🪙</td>
                <td class="p-3 text-xs text-text-secondary text-right">${date.toLocaleString()}</td>
            `;
            this.tableBodyTarget.appendChild(row);
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
