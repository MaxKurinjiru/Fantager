import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { applyTeamColors } from '../utils/team_color.js';

function formatParticipantLabel(participant) {
    if (participant.team?.name) {
        return `${participant.display_name} (${participant.team.name})`;
    }

    return participant.display_name;
}

function formatRecipientOption(recipient) {
    if (recipient.team_name) {
        return `${recipient.display_name} (${recipient.team_name})`;
    }

    return recipient.display_name;
}

export default class extends Controller {
    static targets = [
        'folderBtn', 'mailFolderTitle', 'mailSenderColTitle', 'mailTableBody',
        'composeModal', 'composeRecipient', 'composeSubject', 'composeBody', 'submitComposeBtn',
        'readMessageModal', 'readSubject', 'readSenderLabel', 'readSenderColor', 'readSenderName', 'readDate', 'readBody', 'deleteMsgBtn',
        'alert', 'alertMessage', 'badge'
    ];

    static values = {
        unreadCount: Number,
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
            this.mailFolderTitleTarget.textContent = this.translationsValue.sent_title || '';
            this.mailSenderColTitleTarget.textContent = this.translationsValue.col_recipient || '';
        } else {
            this.mailFolderTitleTarget.textContent = this.translationsValue.inbox_title || '';
            this.mailSenderColTitleTarget.textContent = this.translationsValue.col_sender || '';
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
            this.showFeedback('error', this.translationsValue.error_load_mail || '');
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

            const participant = this.currentFolder === 'sent' ? msg.receiver : msg.sender;
            const teamColorEl = rowNode.querySelector('.js-team-color');
            if (participant.team?.colors) {
                applyTeamColors(teamColorEl, participant.team.colors);
                teamColorEl.classList.remove('hidden');
            } else {
                teamColorEl.classList.add('hidden');
            }
            rowNode.querySelector('.js-team-name').textContent = formatParticipantLabel(participant);

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
            const placeholder = this.translationsValue.select_recipient || '';
            select.innerHTML = `<option value="">-- ${placeholder} --</option>`;

            recipients.forEach(recipient => {
                const option = document.createElement('option');
                option.value = recipient.id;
                option.textContent = formatRecipientOption(recipient);
                select.appendChild(option);
            });
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_load_recipients || '');
        }
    }

    async submitCompose(e) {
        e.preventDefault();
        const receiver_user_id = parseInt(this.composeRecipientTarget.value);
        const subject = this.composeSubjectTarget.value.trim();
        const body = this.composeBodyTarget.value.trim();

        if (!receiver_user_id || !subject || !body) {
            this.showFeedback('warning', this.translationsValue.warning_compose_fields || '');
            return;
        }

        this.submitComposeBtnTarget.disabled = true;
        this.submitComposeBtnTarget.textContent = this.translationsValue.text_sending || '';

        try {
            const response = await fetch('/api/v1/messages', {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ receiver_user_id, subject, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_send_msg || ''));
            }

            this.composeModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', this.translationsValue.success_send_msg || '');

            if (this.currentFolder === 'sent') {
                await this.refreshMessages();
            }
        } catch (err) {
            this.showFeedback('error', err.message);
        } finally {
            this.submitComposeBtnTarget.disabled = false;
            this.submitComposeBtnTarget.textContent = this.translationsValue.send_msg || '';
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

            const participant = this.currentFolder === 'sent' ? msg.receiver : msg.sender;
            const labelKey = this.currentFolder === 'sent'
                ? this.translationsValue.col_recipient
                : this.translationsValue.col_sender;

            this.readSenderLabelTarget.textContent = `${labelKey}:`;
            this.readSenderNameTarget.textContent = formatParticipantLabel(participant);

            if (participant.team?.colors) {
                applyTeamColors(this.readSenderColorTarget, participant.team.colors);
                this.readSenderColorTarget.classList.remove('hidden');
            } else {
                this.readSenderColorTarget.classList.add('hidden');
            }

            window.dispatchEvent(new CustomEvent('modal:open-read-message'));

            const unreadIndicator = document.querySelector(`[data-mail-unread-indicator-id="${messageId}"]`);
            if (unreadIndicator && unreadIndicator.textContent.includes('✉️')) {
                unreadIndicator.textContent = '📖';
            }

            await this.refreshUnreadCount();
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_read_msg || '');
        }
    }

    async deleteCurrentMessage(e) {
        e.preventDefault();
        if (!this.activeMessageId) return;

        this.deleteMsgBtnTarget.disabled = true;
        this.deleteMsgBtnTarget.textContent = this.translationsValue.text_deleting || '';

        try {
            const response = await fetch(`/api/v1/messages/${this.activeMessageId}`, {
                method: 'DELETE',
                headers: csrfHeaders()
            });

            if (!response.ok) throw new Error();

            this.readMessageModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', this.translationsValue.success_delete_msg || '');
            await this.refreshMessages();
            await this.refreshUnreadCount();
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_delete_msg || '');
        } finally {
            this.deleteMsgBtnTarget.disabled = false;
            this.deleteMsgBtnTarget.textContent = `🗑️ ${this.translationsValue.delete_msg || ''}`;
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
