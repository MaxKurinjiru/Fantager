import { Controller } from '@hotwired/stimulus';
import { showAlert, hideAlert } from '../utils/alert.js';
import { formatBadgeCount, formatDateTime } from '../utils/locale.js';

export default class extends Controller {
    static targets = [
        'alert', 'alertMessage', 'badge', 'tableBody',
        'readModal', 'readTitle', 'readType', 'readDate', 'readBody', 'markAllBtn',
    ];

    static values = {
        unreadCount: Number,
        emptyMsg: String,
        translations: Object,
    };

    connect() {
        this.activeNotificationId = null;
        this._onNotificationsOpen = () => this.refreshNotifications();
        window.addEventListener('modal:open-notifications', this._onNotificationsOpen);
    }

    disconnect() {
        window.removeEventListener('modal:open-notifications', this._onNotificationsOpen);
    }

    openNotificationsModal(event) {
        event?.preventDefault();
        window.dispatchEvent(new CustomEvent('modal:open-notifications'));
    }

    hideAlert() {
        hideAlert(this.alertTarget);
    }

    showFeedback(type, message) {
        showAlert(this.alertTarget, this.alertMessageTarget, type, message);
    }

    async refreshNotifications() {
        try {
            const response = await fetch('/api/v1/notifications?limit=50');
            if (!response.ok) {
                throw new Error();
            }

            const notifications = await response.json();
            this.renderNotifications(notifications);
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_load || '');
        }
    }

    renderNotifications(notifications) {
        this.tableBodyTarget.innerHTML = '';

        if (notifications.length === 0) {
            const emptyTemplate = document.getElementById('template-notification-empty-row');
            if (emptyTemplate) {
                const emptyNode = emptyTemplate.content.cloneNode(true);
                emptyNode.querySelector('.js-empty-text').textContent = this.emptyMsgValue;
                this.tableBodyTarget.appendChild(emptyNode);
            }
            return;
        }

        const template = document.getElementById('template-notification-row');
        if (!template) {
            return;
        }

        notifications.forEach((notification) => {
            const rowNode = template.content.cloneNode(true);
            const row = rowNode.querySelector('.notification-row');
            row.dataset.action = 'click->notifications#readNotification';
            row.dataset.notificationId = notification.id;

            const indicator = notification.is_read ? '📖' : '🔔';
            rowNode.querySelector('.js-unread-indicator').textContent = indicator;
            rowNode.querySelector('.js-title').textContent = notification.title;
            rowNode.querySelector('.js-type').textContent = this.typeLabel(notification.type);

            const formattedDate = formatDateTime(notification.created_at, {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
            rowNode.querySelector('.js-created-at').textContent = formattedDate;

            if (!notification.is_read) {
                row.classList.add('notification-row--unread');
            }

            this.tableBodyTarget.appendChild(rowNode);
        });
    }

    typeLabel(type) {
        const key = `type_${type}`;
        return this.translationsValue[key] || type;
    }

    async readNotification(event) {
        const notificationId = event.currentTarget.dataset.notificationId;
        this.activeNotificationId = notificationId;

        try {
            const response = await fetch(`/api/v1/notifications/${notificationId}`);
            if (!response.ok) {
                throw new Error();
            }

            const notification = await response.json();
            this.readTitleTarget.textContent = notification.title;
            this.readTypeTarget.textContent = this.typeLabel(notification.type);
            this.readBodyTarget.textContent = notification.body;

            const formattedDate = formatDateTime(notification.created_at, {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
            this.readDateTarget.textContent = formattedDate;

            window.dispatchEvent(new CustomEvent('modal:open-read-notification'));
            await this.refreshUnreadCount();
            await this.refreshNotifications();
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_read || '');
        }
    }

    async markAllRead(event) {
        event.preventDefault();

        this.markAllBtnTarget.disabled = true;

        try {
            const response = await fetch('/api/v1/notifications/read-all', { method: 'PUT' });
            if (!response.ok) {
                throw new Error();
            }

            this.showFeedback('success', this.translationsValue.success_mark_all || '');
            await this.refreshUnreadCount();
            await this.refreshNotifications();
        } catch (err) {
            this.showFeedback('error', this.translationsValue.error_mark_all || '');
        } finally {
            this.markAllBtnTarget.disabled = false;
        }
    }

    async refreshUnreadCount() {
        try {
            const response = await fetch('/api/v1/notifications/unread-count');
            if (!response.ok) {
                throw new Error();
            }

            const data = await response.json();
            this.unreadCountValue = data.count;
            this.updateBadges();
        } catch (err) {
            // Silently ignore badge refresh failures
        }
    }

    updateBadges() {
        const count = this.unreadCountValue;
        this.badgeTargets.forEach((badge) => {
            if (count > 0) {
                const overflow = this.translationsValue.badge_overflow || '99+';
                badge.textContent = formatBadgeCount(count, overflow);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
    }
}
