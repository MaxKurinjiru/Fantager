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
        emptySentMsg: String,
        translations: Object
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
            this.showFeedback('error', this.translationsValue.error_load_threads || 'Nepodařilo se načíst témata.');
        }
       renderThreads(threads) {
        this.threadsTableBodyTarget.innerHTML = '';

        if (threads.length === 0) {
            const emptyTemplate = document.getElementById('template-community-empty-row');
            const emptyNode = emptyTemplate.content.cloneNode(true);
            emptyNode.querySelector('.js-empty-text').textContent = this.emptyThreadsMsgValue;
            this.threadsTableBodyTarget.appendChild(emptyNode);
            return;
        }

        const template = document.getElementById('template-forum-thread-row');

        threads.forEach(thread => {
            const rowNode = template.content.cloneNode(true);
            const row = rowNode.querySelector('.community-row');
            row.dataset.action = 'click->community#viewThread';
            row.dataset.threadId = thread.id;

            // Title and lock
            rowNode.querySelector('.js-thread-title').textContent = thread.title;
            if (thread.isLocked) {
                rowNode.querySelector('.js-lock-icon').classList.remove('hidden');
            }

            // Category translation
            const categoryText = this.translationsValue.categories?.[thread.category] || thread.category;
            rowNode.querySelector('.js-thread-category').textContent = categoryText;

            // Author details
            const primaryColor = thread.author_team.colors?.primary ?? '#10b981';
            const secondaryColor = thread.author_team.colors?.secondary ?? '#0f1720';
            const colorSpan = rowNode.querySelector('.js-author-color');
            colorSpan.style.backgroundColor = primaryColor;
            colorSpan.style.borderColor = secondaryColor;
            rowNode.querySelector('.js-author-name').textContent = thread.author_team.name;

            // Replies and Date
            rowNode.querySelector('.js-replies-count').textContent = thread.posts_count;
            const formattedDate = new Date(thread.createdAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            rowNode.querySelector('.js-created-at').textContent = formattedDate;

            this.threadsTableBodyTarget.appendChild(rowNode);
        });
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
            this.showFeedback('warning', this.translationsValue.warning_fill_fields || 'Vyplňte prosím předmět i obsah zprávy.');
            return;
        }

        this.submitNewThreadBtnTarget.disabled = true;
        this.submitNewThreadBtnTarget.textContent = this.translationsValue.text_sending || 'Odesílám...';

        try {
            const response = await fetch('/api/v1/forum/threads', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ category, title, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_create_thread || 'Nepodařilo se založit téma.'));
            }

            // Close modal
            this.newThreadModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', this.translationsValue.success_create_thread || 'Téma bylo úspěšně založeno.');
            await this.refreshThreads();
        } catch (err) {
            this.showFeedback('error', err.message);
        } finally {
            this.submitNewThreadBtnTarget.disabled = false;
            this.submitNewThreadBtnTarget.textContent = this.translationsValue.create_thread || 'Vytvořit téma';
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
            this.threadCategoryTarget.textContent = this.translationsValue.categories?.[thread.category] || thread.category;

            // Handle lock controls
            this.threadLockBadgeTarget.classList.toggle('hidden', !thread.isLocked);
            
            const isAuthor = thread.author_team.id === this.activeTeamIdValue;
            this.lockBtnTarget.classList.toggle('hidden', !isAuthor);
            this.lockBtnTarget.textContent = thread.isLocked 
                ? `🔓 ${this.translationsValue.btn_unlock || 'Odemknout'}` 
                : `🔒 ${this.translationsValue.btn_lock || 'Zamknout'}`;

            // Reply Form Container Visibility
            this.replyFormContainerTarget.classList.toggle('hidden', thread.isLocked);

            // Render Posts Timeline
            this.renderPosts(thread.posts);

            // Switch views
            this.threadsContainerTarget.classList.add('hidden');
            this.threadDetailContainerTarget.classList.remove('hidden');
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_load_thread_detail || 'Nepodařilo se načíst detail tématu.');
        }
    }

    renderPosts(posts) {
        this.postsTimelineTarget.innerHTML = '';
        const template = document.getElementById('template-forum-post');

        posts.forEach(post => {
            const postNode = template.content.cloneNode(true);
            const primaryColor = post.author_team.colors?.primary ?? '#10b981';
            const secondaryColor = post.author_team.colors?.secondary ?? '#0f1720';
            const colorSpan = postNode.querySelector('.js-author-color');
            colorSpan.style.backgroundColor = primaryColor;
            colorSpan.style.borderColor = secondaryColor;

            postNode.querySelector('.js-author-name').textContent = post.author_team.name;
            const formattedDate = new Date(post.createdAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            postNode.querySelector('.js-created-at').textContent = formattedDate;
            postNode.querySelector('.js-post-body').textContent = post.body;

            this.postsTimelineTarget.appendChild(postNode);
        });

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
        this.submitReplyBtnTarget.textContent = this.translationsValue.text_sending || 'Odesílám...';

        try {
            const response = await fetch(`/api/v1/forum/threads/${this.activeThreadId}/posts`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_send_reply || 'Nepodařilo se odeslat odpověď.'));
            }

            this.replyInputTarget.value = '';
            // Reload thread replies
            await this.reloadThreadReplies();
        } catch (err) {
            this.showFeedback('error', err.message);
        } finally {
            this.submitReplyBtnTarget.disabled = false;
            this.submitReplyBtnTarget.textContent = this.translationsValue.post_reply || 'Odeslat odpověď';
        }
    }

    async reloadThreadReplies() {
        try {
            const response = await fetch(`/api/v1/forum/threads/${this.activeThreadId}`);
            if (!response.ok) throw new Error();

            const thread = await response.json();
            this.renderPosts(thread.posts);
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_refresh_posts || 'Nepodařilo se obnovit příspěvky.');
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
            this.lockBtnTarget.textContent = newLockState 
                ? `🔓 ${this.translationsValue.btn_unlock || 'Odemknout'}` 
                : `🔒 ${this.translationsValue.btn_lock || 'Zamknout'}`;
            this.replyFormContainerTarget.classList.toggle('hidden', newLockState);
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_toggle_lock || 'Nepodařilo se změnit zámek tématu.');
        }
    }

    // =============================================================================
    //  MAIL LOGIC
    // ======================    async switchFolder(e) {
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
            const emptyTemplate = document.getElementById('template-community-empty-row');
            const emptyNode = emptyTemplate.content.cloneNode(true);
            emptyNode.querySelector('.js-empty-text').textContent = emptyMsg;
            this.mailTableBodyTarget.appendChild(emptyNode);
            return;
        }

        const template = document.getElementById('template-mail-message-row');

        messages.forEach(msg => {
            const rowNode = template.content.cloneNode(true);
            const row = rowNode.querySelector('.community-row');
            row.dataset.action = 'click->community#readMessage';
            row.dataset.messageId = msg.id;

            // Status indicator
            const indicator = msg.readAt ? '📖' : '✉️';
            const unreadSpan = rowNode.querySelector('.js-unread-indicator');
            unreadSpan.dataset.communityUnreadIndicatorId = msg.id;
            unreadSpan.textContent = this.currentFolder === 'sent' ? '📤' : indicator;

            // Subject
            rowNode.querySelector('.js-subject').textContent = msg.subject;

            // Target Team
            const targetTeam = this.currentFolder === 'sent' ? msg.receiver_team : msg.sender_team;
            const primaryColor = targetTeam.colors?.primary ?? '#10b981';
            const secondaryColor = targetTeam.colors?.secondary ?? '#0f1720';
            const colorSpan = rowNode.querySelector('.js-team-color');
            colorSpan.style.backgroundColor = primaryColor;
            colorSpan.style.borderColor = secondaryColor;
            rowNode.querySelector('.js-team-name').textContent = targetTeam.name;

            // Date
            const formattedDate = new Date(msg.sentAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            rowNode.querySelector('.js-sent-at').textContent = formattedDate;

            this.mailTableBodyTarget.appendChild(rowNode);
        });
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
            this.showFeedback('warning', this.translationsValue.warning_compose_fields || 'Prosím zvolte příjemce a vyplňte předmět i obsah zprávy.');
            return;
        }

        this.submitComposeBtnTarget.disabled = true;
        this.submitComposeBtnTarget.textContent = this.translationsValue.text_sending || 'Odesílám...';

        try {
            const response = await fetch('/api/v1/messages', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ receiver_team_id, subject, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_send_msg || 'Nepodařilo se odeslat zprávu.'));
            }

            // Close modal
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

            // Sender/Recipient styling
            if (this.currentFolder === 'sent') {
                this.readSenderLabelTarget.textContent = `${this.translationsValue.col_recipient || 'Recipient'}:`;
                this.readSenderNameTarget.textContent = msg.receiver_team.name;
                const primaryColor = msg.receiver_team.colors?.primary ?? '#10b981';
                const secondaryColor = msg.receiver_team.colors?.secondary ?? '#0f1720';
                this.readSenderColorTarget.style.backgroundColor = primaryColor;
                this.readSenderColorTarget.style.borderColor = secondaryColor;
            } else {
                this.readSenderLabelTarget.textContent = `${this.translationsValue.col_sender || 'Sender'}:`;
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
                method: 'DELETE'
            });

            if (!response.ok) throw new Error();

            // Close modal
            this.readMessageModalTarget.querySelector('.modal-close').click();
            this.showFeedback('success', this.translationsValue.success_delete_msg || 'Zpráva byla smazána.');
            await this.refreshMessages();
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_delete_msg || 'Nepodařilo se smazat zprávu.');
        } finally {
            this.deleteMsgBtnTarget.disabled = false;
            this.deleteMsgBtnTarget.textContent = `🗑️ ${this.translationsValue.delete_msg || 'Smazat zprávu'}`;
            this.activeMessageId = null;
        }
    }
}
