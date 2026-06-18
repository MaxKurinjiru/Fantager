import { Controller } from '@hotwired/stimulus';
import { formatDateTime, formatNumber } from '../utils/locale.js';

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

        this._renderLoadingRow();

        try {
            const response = await fetch('/api/v1/finance/recent');
            if (!response.ok) throw new Error();
            const records = await response.json();
            this.renderRecords(records);
        } catch {
            const errMsg = (this.hasTranslationsValue && this.translationsValue.error_fetch) || '';
            this._renderErrorRow(errMsg);
        }
    }

    renderRecords(records) {
        if (!records.length) {
            this.tableBodyTarget.replaceChildren();
            if (this.hasEmptyMessageTarget) {
                this.emptyMessageTarget.classList.remove('hidden');
            }
            return;
        }

        if (this.hasEmptyMessageTarget) {
            this.emptyMessageTarget.classList.add('hidden');
        }

        const rowTemplate = document.getElementById('template-ledger-row');
        if (!rowTemplate) {
            return;
        }

        this.tableBodyTarget.replaceChildren();
        records.forEach(record => {
            const rowNode = rowTemplate.content.cloneNode(true);
            const row = rowNode.querySelector('tr');
            const goldClass = record.gold_change > 0
                ? 'finance-changes__item--positive'
                : 'finance-changes__item--negative';
            const goldPrefix = record.gold_change > 0 ? '+' : '';
            const date = new Date(record.created_at);

            rowNode.querySelector('.js-type').textContent = this.typeLabel(record.type);

            const goldSpan = rowNode.querySelector('.js-gold');
            goldSpan.classList.add(goldClass);
            goldSpan.textContent = `${goldPrefix}${formatNumber(record.gold_change)} 🪙`;

            rowNode.querySelector('.js-date').textContent = formatDateTime(date);

            this.tableBodyTarget.appendChild(row);
        });
    }

    _renderLoadingRow() {
        const template = document.getElementById('template-ledger-loading');
        if (template) {
            this.tableBodyTarget.replaceChildren(template.content.cloneNode(true));
            return;
        }

        this.tableBodyTarget.replaceChildren();
    }

    _renderErrorRow(message) {
        const template = document.getElementById('template-ledger-error');
        if (!template) {
            this.tableBodyTarget.replaceChildren();
            return;
        }

        const node = template.content.cloneNode(true);
        const textTarget = node.querySelector('.js-error-text');
        if (textTarget) {
            textTarget.textContent = message;
        }
        this.tableBodyTarget.replaceChildren(node);
    }
}
