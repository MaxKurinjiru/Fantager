import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { applyTeamColors } from '../utils/team_color.js';

export default class extends Controller {
    static targets = [
        'folderBtn', 'mailFolderTitle', 'mailSenderColTitle', 'mailTableBody',
        'composeModal', 'composeRecipient', 'composeSubject', 'composeBody', 'submitComposeBtn',
        'readMessageModal', 'readSubject', 'readSenderLabel', 'readSenderColor', 'readSenderName', 'readDate', 'readBody', 'deleteMsgBtn',
        'alert', 'alertMessage', 'badge'
    ];

    static values = {
        unreadCount: Number,
        activeTeamId: Number,
        emptyInboxMsg: String,
        emptySentMsg: String,
        translations: Object
    };

    connect() {
        this.currentFolder = 'inbox';
        this.activeMessageId = null;
        this.updateBadges();
        this._onMailOpen = () => this.refreshMessages();
        window.addEventListener('modal:open-mail', this._onMailOpen);
    }

    disconnect() {
        window.removeEventListener('modal:open-mail', this._onMailOpen);
    }


    openMailModal(event) {
        event?.preventDefault();
        window.dispatchEvent(new CustomEvent('modal:open-mail'));
    }

    openAccountSettingsModal(event) {
        event?.preventDefault();
        window.dispatchEvent(new CustomEvent('modal:open-account-settings'));
    }

    hideAlert() {
        hideAlert(this.alertTarget);
    }

    showFeedback(type, message) {
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    async switchFolder(e) {
        e.preventDefault();
        const folder = e.currentTarget.dataset.folder;
        this.currentFolder = folder;

        this.folderBtnTargets.forEach(btn => {
            const isCurrent = btn.dataset.folder === folder;
            btn.classList.toggle('mail-folder-tab--active', isCurrent);
        });

        if (folder === 'sent') {
            this.mailFolderTitleTarget.textContent = this.translationsValue.sent_title || 'Odeslané zprávy';
            this.mailSenderColTitleTarget.textContent = this.translationsValue.col_recipient || 'Příjemce';
        } else {
            this.mailFolderTitleTarget.textContent = this.translationsValue.inbox_title || 'Doručené zprávy';
            this.mailSenderColTitleTarget.textContent = this.translationsValue.col_sender || 'Odesílatel';
        }

        await this.refreshMessages();
    }

    async refreshMessages() {
        try {
            const response = await fetch(`/api/v1/messages?folder=${this.currentFolder}`);
            if (!response.ok) throw new Error();

            const messages = await response.json();
            this.renderMessages(messages);
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_load_mail || 'Nepodařilo se načíst poštu.');
        }
    }

    renderMessages(messages) {
        this.mailTableBodyTarget.innerHTML = '';

        if (messages.length === 0) {
            const emptyMsg = this.currentFolder === 'sent' ? this.emptySentMsgValue : this.emptyInboxMsgValue;
            const emptyTemplate = document.getElementById('template-mail-empty-row');
            const emptyNode = emptyTemplate.content.cloneNode(true);
            emptyNode.querySelector('.js-empty-text').textContent = emptyMsg;
            this.mailTableBodyTarget.appendChild(emptyNode);
            return;
        }

        const template = document.getElementById('template-mail-message-row');

        messages.forEach(msg => {
            const rowNode = template.content.cloneNode(true);
            const row = rowNode.querySelector('.mail-row');
            row.dataset.action = 'click->mail#readMessage';
            row.dataset.messageId = msg.id;

            const indicator = msg.readAt ? '📖' : '✉️';
            const unreadSpan = rowNode.querySelector('.js-unread-indicator');
            unreadSpan.dataset.mailUnreadIndicatorId = msg.id;
            unreadSpan.textContent = this.currentFolder === 'sent' ? '📤' : indicator;

            rowNode.querySelector('.js-subject').textContent = msg.subject;

            const targetTeam = this.currentFolder === 'sent' ? msg.receiver_team : msg.sender_team;
            applyTeamColors(rowNode.querySelector('.js-team-color'), targetTeam.colors);
            rowNode.querySelector('.js-team-name').textContent = targetTeam.name;

            const formattedDate = new Date(msg.sentAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            rowNode.querySelector('.js-sent-at').textContent = formattedDate;

            this.mailTableBodyTarget.appendChild(rowNode);
        });
    }

    async openComposeModal() {
        this.composeRecipientTarget.value = '';
        this.composeSubjectTarget.value = '';
        this.composeBodyTarget.value = '';
        await this.loadRecipients();
        window.dispatchEvent(new CustomEvent('modal:open-compose'));
    }

    async loadRecipients() {
        try {
            const response = await fetch('/api/v1/messages/recipients');
            if (!response.ok) throw new Error();

            const recipients = await response.json();
            const select = this.composeRecipientTarget;
            const placeholder = this.translationsValue.select_recipient || 'Vyberte příjemce';
            select.innerHTML = `<option value="">-- ${placeholder} --</option>`;

            recipients.forEach(recipient => {
                const option = document.createElement('option');
                option.value = recipient.id;
                option.textContent = recipient.name;
                select.appendChild(option);
            });
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_load_recipients || 'Nepodařilo se načíst seznam příjemců.');
        }
    }

    async submitCompose(e) {
        e.preventDefault();
        const receiver_team_id = parseInt(this.composeRecipientTarget.value);
        const subject = this.composeSubjectTarget.value.trim();
        const body = this.composeBodyTarget.value.trim();

        if (!receiver_team_id || !subject || !body) {
            this.showFeedback('warning', this.translationsValue.warning_compose_fields || 'Prosím zvolte příjemce a vyplňte předmět i obsah zprávy.');
            return;
        }

        this.submitComposeBtnTarget.disabled = true;
        this.submitComposeBtnTarget.textContent = this.translationsValue.text_sending || 'Odesílám...';

        try {
            const response = await fetch('/api/v1/messages', {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ receiver_team_id, subject, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_send_msg || 'Nepodařilo se odeslat zprávu.'));
            }

            this.composeModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', this.translationsValue.success_send_msg || 'Zpráva byla úspěšně odeslána.');

            if (this.currentFolder === 'sent') {
                await this.refreshMessages();
            }
        } catch (err) {
            this.showFeedback('error', err.message);
        } finally {
            this.submitComposeBtnTarget.disabled = false;
            this.submitComposeBtnTarget.textContent = this.translationsValue.send_msg || 'Odeslat zprávu';
        }
    }

    async readMessage(e) {
        const messageId = e.currentTarget.dataset.messageId;
        this.activeMessageId = messageId;

        try {
            const response = await fetch(`/api/v1/messages/${messageId}`);
            if (!response.ok) throw new Error();

            const msg = await response.json();
            const formattedDate = new Date(msg.sentAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            this.readSubjectTarget.textContent = msg.subject;
            this.readBodyTarget.textContent = msg.body;
            this.readDateTarget.textContent = formattedDate;

            if (this.currentFolder === 'sent') {
                this.readSenderLabelTarget.textContent = `${this.translationsValue.col_recipient || 'Recipient'}:`;
                this.readSenderNameTarget.textContent = msg.receiver_team.name;
                applyTeamColors(this.readSenderColorTarget, msg.receiver_team.colors);
            } else {
                this.readSenderLabelTarget.textContent = `${this.translationsValue.col_sender || 'Sender'}:`;
                this.readSenderNameTarget.textContent = msg.sender_team.name;
                applyTeamColors(this.readSenderColorTarget, msg.sender_team.colors);
            }
            this.readSenderColorTarget.classList.remove('hidden');

            window.dispatchEvent(new CustomEvent('modal:open-read-message'));

            const unreadIndicator = document.querySelector(`[data-mail-unread-indicator-id="${messageId}"]`);
            if (unreadIndicator && unreadIndicator.textContent.includes('✉️')) {
                unreadIndicator.textContent = '📖';
            }

            await this.refreshUnreadCount();
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_read_msg || 'Nepodařilo se přečíst zprávu.');
        }
    }

    async deleteCurrentMessage(e) {
        e.preventDefault();
        if (!this.activeMessageId) return;

        this.deleteMsgBtnTarget.disabled = true;
        this.deleteMsgBtnTarget.textContent = this.translationsValue.text_deleting || 'Mažu...';

        try {
            const response = await fetch(`/api/v1/messages/${this.activeMessageId}`, {
                method: 'DELETE',
                headers: csrfHeaders()
            });

            if (!response.ok) throw new Error();

            this.readMessageModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', this.translationsValue.success_delete_msg || 'Zpráva byla smazána.');
            await this.refreshMessages();
            await this.refreshUnreadCount();
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_delete_msg || 'Nepodařilo se smazat zprávu.');
        } finally {
            this.deleteMsgBtnTarget.disabled = false;
            this.deleteMsgBtnTarget.textContent = `🗑️ ${this.translationsValue.delete_msg || 'Smazat zprávu'}`;
            this.activeMessageId = null;
        }
    }

    async refreshUnreadCount() {
        try {
            const response = await fetch('/api/v1/messages/unread-count');
            if (!response.ok) throw new Error();

            const data = await response.json();
            this.unreadCountValue = data.count;
            this.updateBadges();
        } catch (err) {
            // Silently ignore badge refresh failures
        }
    }

    updateBadges() {
        const count = this.unreadCountValue;
        this.badgeTargets.forEach(badge => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
    }
}
