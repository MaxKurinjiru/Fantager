import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';

export default class extends Controller {
    static targets = [
        'threadsContainer', 'threadsTableBody', 'threadDetailContainer',
        'threadTitle', 'threadCategory', 'threadLockBadge', 'lockBtn',
        'postsTimeline', 'replyInput', 'submitReplyBtn', 'replyFormContainer',
        'newThreadModal', 'newThreadCategory', 'newThreadTitle', 'newThreadBody', 'submitNewThreadBtn',
        'folderBtn', 'categoryBtn', 'mailFolderTitle', 'mailSenderColTitle', 'mailTableBody',
        'composeModal', 'composeRecipient', 'composeSubject', 'composeBody', 'submitComposeBtn',
        'readMessageModal', 'readSubject', 'readSenderLabel', 'readSenderColor', 'readSenderName', 'readDate', 'readBody', 'deleteMsgBtn',
        'alert', 'alertMessage'
    ];

    static values = {
        activeTeamId: Number,
        kingdomId: Number,
        emptyThreadsMsg: String,
        emptyInboxMsg: String,
        emptySentMsg: String
    };

    connect() {
        this.currentFolder = 'inbox';
        this.currentCategory = 'all';
        this.activeThreadId = null;
        this.activeMessageId = null;
        this.isLockedThread = false;
    }

    hideAlert() {
        hideAlert(this.alertTarget);
    }

    showFeedback(type, message) {
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    onTabSwitch() {
        this.hideAlert();
        this.backToThreads();
    }

    // =============================================================================
    //  FORUM LOGIC
    // =============================================================================

    async filterCategory(e) {
        e.preventDefault();
        const category = e.currentTarget.dataset.category;
        this.currentCategory = category;

        // Toggle buttons style semantically
        this.categoryBtnTargets.forEach(btn => {
            const isCurrent = btn.dataset.category === category;
            btn.classList.toggle('community-tab-btn--active', isCurrent);
        });

        await this.refreshThreads();
    }

    async refreshThreads() {
        try {
            const response = await fetch(`/api/v1/forum/threads?category=${this.currentCategory}`);
            if (!response.ok) throw new Error();

            const threads = await response.json();
            this.renderThreads(threads);
        } catch (err) {
            this.showFeedback('error', 'Nepodařilo se načíst témata.');
        }
    }

    renderThreads(threads) {
        if (threads.length === 0) {
            this.threadsTableBodyTarget.innerHTML = `
                <tr>
                    <td colspan="4" class="py-12 text-center text-gray-550 select-none">
                        ${this.emptyThreadsMsgValue}
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        threads.forEach(thread => {
            const primaryColor = thread.author_team.colors?.primary ?? '#10b981';
            const secondaryColor = thread.author_team.colors?.secondary ?? '#0f1720';
            const formattedDate = new Date(thread.createdAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            html += `
                <tr class="community-row" data-action="click->community#viewThread" data-thread-id="${thread.id}">
                    <td class="py-4 font-bold text-white">
                        <div class="flex items-center gap-2">
                            ${thread.isLocked ? '<span class="text-gray-550" title="Uzamčeno">🔒</span>' : ''}
                            <span>${thread.title}</span>
                        </div>
                        <span class="text-[9px] uppercase font-bold text-emerald-400 tracking-wider bg-emerald-950/20 border border-emerald-900/40 px-2 py-0.5 rounded-full mt-1 inline-block">
                            ${thread.category}
                        </span>
                    </td>
                    <td class="py-4 text-gray-400">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full border border-gray-700" style="background-color: ${primaryColor}; border-color: ${secondaryColor};"></span>
                            <span>${thread.author_team.name}</span>
                        </div>
                    </td>
                    <td class="py-4 text-center text-gray-300 font-semibold">${thread.posts_count}</td>
                    <td class="py-4 text-right text-gray-500">${formattedDate}</td>
                </tr>
            `;
        });

        this.threadsTableBodyTarget.innerHTML = html;
    }

    openNewThreadModal() {
        this.newThreadTitleTarget.value = '';
        this.newThreadBodyTarget.value = '';
        window.dispatchEvent(new CustomEvent('modal:open-new-thread'));
    }

    async submitNewThread(e) {
        e.preventDefault();
        const category = this.newThreadCategoryTarget.value;
        const title = this.newThreadTitleTarget.value.trim();
        const body = this.newThreadBodyTarget.value.trim();

        if (!title || !body) {
            this.showFeedback('warning', 'Vyplňte prosím předmět i obsah zprávy.');
            return;
        }

        this.submitNewThreadBtnTarget.disabled = true;
        this.submitNewThreadBtnTarget.textContent = 'Odesílám...';

        try {
            const response = await fetch('/api/v1/forum/threads', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ category, title, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? 'Nepodařilo se založit téma.');
            }

            // Close modal
            this.newThreadModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', 'Téma bylo úspěšně založeno.');
            await this.refreshThreads();
        } catch (err) {
            this.showFeedback('error', err.message);
        } finally {
            this.submitNewThreadBtnTarget.disabled = false;
            this.submitNewThreadBtnTarget.textContent = 'Vytvořit téma';
        }
    }

    async viewThread(e) {
        const threadId = e.currentTarget.dataset.threadId;
        this.activeThreadId = threadId;

        try {
            const response = await fetch(`/api/v1/forum/threads/${threadId}`);
            if (!response.ok) throw new Error();

            const thread = await response.json();
            this.isLockedThread = thread.isLocked;

            // Render Thread Detail
            this.threadTitleTarget.textContent = thread.title;
            this.threadCategoryTarget.textContent = thread.category;

            // Handle lock controls
            this.threadLockBadgeTarget.classList.toggle('hidden', !thread.isLocked);
            
            const isAuthor = thread.author_team.id === this.activeTeamIdValue;
            this.lockBtnTarget.classList.toggle('hidden', !isAuthor);
            this.lockBtnTarget.textContent = thread.isLocked ? '🔓 Odemknout' : '🔒 Zamknout';

            // Reply Form Container Visibility
            this.replyFormContainerTarget.classList.toggle('hidden', thread.isLocked);

            // Render Posts Timeline
            this.renderPosts(thread.posts);

            // Switch views
            this.threadsContainerTarget.classList.add('hidden');
            this.threadDetailContainerTarget.classList.remove('hidden');
        } catch (err) {
            this.showFeedback('error', 'Nepodařilo se načíst detail tématu.');
        }
    }

    renderPosts(posts) {
        let html = '';
        posts.forEach(post => {
            const primaryColor = post.author_team.colors?.primary ?? '#10b981';
            const secondaryColor = post.author_team.colors?.secondary ?? '#0f1720';
            const formattedDate = new Date(post.createdAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            html += `
                <div class="community-post">
                    <div class="community-post__header">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full border border-gray-700" style="background-color: ${primaryColor}; border-color: ${secondaryColor};"></span>
                            <strong class="text-white">${post.author_team.name}</strong>
                        </div>
                        <span>${formattedDate}</span>
                    </div>
                    <div class="community-post__body">${post.body}</div>
                </div>
            `;
        });

        this.postsTimelineTarget.innerHTML = html;
        // Scroll to bottom
        this.postsTimelineTarget.scrollTop = this.postsTimelineTarget.scrollHeight;
    }

    backToThreads() {
        this.threadsContainerTarget.classList.remove('hidden');
        this.threadDetailContainerTarget.classList.add('hidden');
        this.activeThreadId = null;
        this.refreshThreads();
    }

    async submitReply(e) {
        e.preventDefault();
        const body = this.replyInputTarget.value.trim();
        if (!body) return;

        this.submitReplyBtnTarget.disabled = true;
        this.submitReplyBtnTarget.textContent = 'Odesílám...';

        try {
            const response = await fetch(`/api/v1/forum/threads/${this.activeThreadId}/posts`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? 'Nepodařilo se odeslat odpověď.');
            }

            this.replyInputTarget.value = '';
            // Reload thread replies
            await this.reloadThreadReplies();
        } catch (err) {
            this.showFeedback('error', err.message);
        } finally {
            this.submitReplyBtnTarget.disabled = false;
            this.submitReplyBtnTarget.textContent = 'Odeslat odpověď';
        }
    }

    async reloadThreadReplies() {
        try {
            const response = await fetch(`/api/v1/forum/threads/${this.activeThreadId}`);
            if (!response.ok) throw new Error();

            const thread = await response.json();
            this.renderPosts(thread.posts);
        } catch (err) {
            this.showFeedback('error', 'Nepodařilo se obnovit příspěvky.');
        }
    }

    async toggleLock(e) {
        e.preventDefault();
        const newLockState = !this.isLockedThread;

        try {
            const response = await fetch(`/api/v1/forum/threads/${this.activeThreadId}/lock`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lock: newLockState })
            });

            if (!response.ok) throw new Error();

            this.isLockedThread = newLockState;
            this.threadLockBadgeTarget.classList.toggle('hidden', !newLockState);
            this.lockBtnTarget.textContent = newLockState ? '🔓 Odemknout' : '🔒 Zamknout';
            this.replyFormContainerTarget.classList.toggle('hidden', newLockState);
        } catch (err) {
            this.showFeedback('error', 'Nepodařilo se změnit zámek tématu.');
        }
    }

    // =============================================================================
    //  MAIL LOGIC
    // =============================================================================

    async switchFolder(e) {
        e.preventDefault();
        const folder = e.currentTarget.dataset.folder;
        this.currentFolder = folder;

        // Toggle buttons style semantically
        this.folderBtnTargets.forEach(btn => {
            const isCurrent = btn.dataset.folder === folder;
            btn.classList.toggle('community-tab-btn--active', isCurrent);
        });

        // Set column and title names
        if (folder === 'sent') {
            this.mailFolderTitleTarget.textContent = 'Odeslané zprávy';
            this.mailSenderColTitleTarget.textContent = 'Příjemce';
        } else {
            this.mailFolderTitleTarget.textContent = 'Doručené zprávy';
            this.mailSenderColTitleTarget.textContent = 'Odesílatel';
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
            this.showFeedback('error', 'Nepodařilo se načíst poštu.');
        }
    }

    renderMessages(messages) {
        if (messages.length === 0) {
            const emptyMsg = this.currentFolder === 'sent' ? this.emptySentMsgValue : this.emptyInboxMsgValue;
            this.mailTableBodyTarget.innerHTML = `
                <tr>
                    <td colspan="4" class="py-12 text-center text-gray-550 select-none">
                        ${emptyMsg}
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        messages.forEach(msg => {
            const targetTeam = this.currentFolder === 'sent' ? msg.receiver_team : msg.sender_team;
            const primaryColor = targetTeam.colors?.primary ?? '#10b981';
            const secondaryColor = targetTeam.colors?.secondary ?? '#0f1720';
            const formattedDate = new Date(msg.sentAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            // Status indicator: envelope for unread inbox, open book for read
            const indicator = msg.readAt ? '📖' : '✉️';

            html += `
                <tr class="community-row" data-action="click->community#readMessage" data-message-id="${msg.id}">
                    <td class="py-4 text-center text-lg">
                        <span data-community-unread-indicator-id="${msg.id}">
                            ${this.currentFolder === 'sent' ? '📤' : indicator}
                        </span>
                    </td>
                    <td class="py-4 font-bold text-white">
                        <span>${msg.subject}</span>
                    </td>
                    <td class="py-4 text-gray-400">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full border border-gray-700" style="background-color: ${primaryColor}; border-color: ${secondaryColor};"></span>
                            <span>${targetTeam.name}</span>
                        </div>
                    </td>
                    <td class="py-4 text-right text-gray-500">${formattedDate}</td>
                </tr>
            `;
        });

        this.mailTableBodyTarget.innerHTML = html;
    }

    openComposeModal() {
        this.composeRecipientTarget.value = '';
        this.composeSubjectTarget.value = '';
        this.composeBodyTarget.value = '';
        window.dispatchEvent(new CustomEvent('modal:open-compose'));
    }

    async submitCompose(e) {
        e.preventDefault();
        const receiver_team_id = parseInt(this.composeRecipientTarget.value);
        const subject = this.composeSubjectTarget.value.trim();
        const body = this.composeBodyTarget.value.trim();

        if (!receiver_team_id || !subject || !body) {
            this.showFeedback('warning', 'Prosím zvolte příjemce a vyplňte předmět i obsah zprávy.');
            return;
        }

        this.submitComposeBtnTarget.disabled = true;
        this.submitComposeBtnTarget.textContent = 'Odesílám...';

        try {
            const response = await fetch('/api/v1/messages', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ receiver_team_id, subject, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? 'Nepodařilo se odeslat zprávu.');
            }

            // Close modal
            this.composeModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', 'Zpráva byla úspěšně odeslána.');
            
            if (this.currentFolder === 'sent') {
                await this.refreshMessages();
            }
        } catch (err) {
            this.showFeedback('error', err.message);
        } finally {
            this.submitComposeBtnTarget.disabled = false;
            this.submitComposeBtnTarget.textContent = 'Odeslat zprávu';
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

            // Sender/Recipient styling
            if (this.currentFolder === 'sent') {
                this.readSenderLabelTarget.textContent = 'Komu:';
                this.readSenderNameTarget.textContent = msg.receiver_team.name;
                const primaryColor = msg.receiver_team.colors?.primary ?? '#10b981';
                const secondaryColor = msg.receiver_team.colors?.secondary ?? '#0f1720';
                this.readSenderColorTarget.style.backgroundColor = primaryColor;
                this.readSenderColorTarget.style.borderColor = secondaryColor;
            } else {
                this.readSenderLabelTarget.textContent = 'Od:';
                this.readSenderNameTarget.textContent = msg.sender_team.name;
                const primaryColor = msg.sender_team.colors?.primary ?? '#10b981';
                const secondaryColor = msg.sender_team.colors?.secondary ?? '#0f1720';
                this.readSenderColorTarget.style.backgroundColor = primaryColor;
                this.readSenderColorTarget.style.borderColor = secondaryColor;
            }
            this.readSenderColorTarget.classList.remove('hidden');

            window.dispatchEvent(new CustomEvent('modal:open-read-message'));

            // If it was unread, update row envelope in view immediately
            const unreadIndicator = document.querySelector(`[data-community-unread-indicator-id="${messageId}"]`);
            if (unreadIndicator && unreadIndicator.textContent.includes('✉️')) {
                unreadIndicator.textContent = '📖';
            }
        } catch (err) {
            this.showFeedback('error', 'Nepodařilo se přečíst zprávu.');
        }
    }

    async deleteCurrentMessage(e) {
        e.preventDefault();
        if (!this.activeMessageId) return;

        this.deleteMsgBtnTarget.disabled = true;
        this.deleteMsgBtnTarget.textContent = 'Mazu...';

        try {
            const response = await fetch(`/api/v1/messages/${this.activeMessageId}`, {
                method: 'DELETE'
            });

            if (!response.ok) throw new Error();

            // Close modal
            this.readMessageModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', 'Zpráva byla smazána.');
            await this.refreshMessages();
        } catch (err) {
            this.showFeedback('error', 'Nepodařilo se smazat zprávu.');
        } finally {
            this.deleteMsgBtnTarget.disabled = false;
            this.deleteMsgBtnTarget.textContent = '🗑️ Smazat zprávu';
            this.activeMessageId = null;
        }
    }
}
