import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { csrfHeaders } from '../utils/csrf.js';
import { applyTeamColors } from '../utils/team_color.js';

export default class extends Controller {
    static targets = [
        'threadsContainer', 'threadsTableBody', 'threadDetailContainer',
        'threadTitle', 'threadCategory', 'threadLockBadge', 'lockBtn',
        'postsTimeline', 'replyInput', 'submitReplyBtn', 'replyFormContainer',
        'newThreadModal', 'newThreadCategory', 'newThreadTitle', 'newThreadBody', 'submitNewThreadBtn',
        'categoryBtn',
        'alert', 'alertMessage'
    ];

    static values = {
        activeTeamId: Number,
        kingdomId: Number,
        emptyThreadsMsg: String,
        translations: Object
    };

    connect() {
        this.currentCategory = 'all';
        this.activeThreadId = null;
        this.isLockedThread = false;
    }

    disconnect() {
        this.activeThreadId = null;
    }

    hideAlert() {
        hideAlert(this.alertTarget);
    }

    showFeedback(type, message) {
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    async filterCategory(e) {
        e.preventDefault();
        const category = e.currentTarget.dataset.category;
        this.currentCategory = category;

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

            rowNode.querySelector('.js-thread-title').textContent = thread.title;
            if (thread.isLocked) {
                rowNode.querySelector('.js-lock-icon').classList.remove('hidden');
            }

            const categoryText = this.translationsValue.categories?.[thread.category] || thread.category;
            rowNode.querySelector('.js-thread-category').textContent = categoryText;

            applyTeamColors(rowNode.querySelector('.js-author-color'), thread.author_team.colors);
            rowNode.querySelector('.js-author-name').textContent = thread.author_team.name;

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
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ category, title, body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_create_thread || 'Nepodařilo se založit téma.'));
            }

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

            this.threadTitleTarget.textContent = thread.title;
            this.threadCategoryTarget.textContent = this.translationsValue.categories?.[thread.category] || thread.category;

            this.threadLockBadgeTarget.classList.toggle('hidden', !thread.isLocked);

            const isAuthor = thread.author_team.id === this.activeTeamIdValue;
            this.lockBtnTarget.classList.toggle('hidden', !isAuthor);
            this.lockBtnTarget.textContent = thread.isLocked
                ? `🔓 ${this.translationsValue.btn_unlock || 'Odemknout'}`
                : `🔒 ${this.translationsValue.btn_lock || 'Zamknout'}`;

            this.replyFormContainerTarget.classList.toggle('hidden', thread.isLocked);

            this.renderPosts(thread.posts);

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
            applyTeamColors(postNode.querySelector('.js-author-color'), post.author_team.colors);

            postNode.querySelector('.js-author-name').textContent = post.author_team.name;
            const formattedDate = new Date(post.createdAt).toLocaleString('cs-CZ', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            postNode.querySelector('.js-created-at').textContent = formattedDate;
            postNode.querySelector('.js-post-body').textContent = post.body;

            this.postsTimelineTarget.appendChild(postNode);
        });

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
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ body })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error ?? (this.translationsValue.error_send_reply || 'Nepodařilo se odeslat odpověď.'));
            }

            this.replyInputTarget.value = '';
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
        const btn = this.lockBtnTarget;
        const originalText = btn.textContent;

        btn.disabled = true;

        try {
            const response = await fetch(`/api/v1/forum/threads/${this.activeThreadId}/lock`, {
                method: 'POST',
                headers: csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ lock: newLockState })
            });

            if (!response.ok) throw new Error();

            this.isLockedThread = newLockState;
            this.threadLockBadgeTarget.classList.toggle('hidden', !newLockState);
            btn.textContent = newLockState
                ? `🔓 ${this.translationsValue.btn_unlock || 'Odemknout'}`
                : `🔒 ${this.translationsValue.btn_lock || 'Zamknout'}`;
            this.replyFormContainerTarget.classList.toggle('hidden', newLockState);
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_toggle_lock || 'Nepodařilo se změnit zámek tématu.');
            btn.textContent = originalText;
        } finally {
            btn.disabled = false;
        }
    }
}
