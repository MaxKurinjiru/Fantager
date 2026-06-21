# Notification System

Reference: [entity-reference.md](../entity-reference.md#19-notification-domain), [route-map.md](../route-map.md#notifications), [community-system.md](community-system.md)

Purpose: In-game alerts for the **logged-in player account** (distinct from team-to-team mail and from the append-only team chronicle).

---

## Implementation Status

| Layer | Status |
|-------|--------|
| **Entity** | Implemented — `App\Entity\Notification\Notification` |
| **Write path** | Implemented — `NotificationHelper::sendNotification()` |
| **Read API** | Implemented — `NotificationService`, `Api\V1\NotificationController` |
| **In-game UI** | Implemented — navbar modal + unread badge (`notifications_controller.js`) |

### Existing write hooks (today)

| Trigger | Service | `NotificationType` |
|---------|---------|-------------------|
| Inactivity warning / release | `PlayerInactivityService` | `system` |
| Financial crisis escalation | `FinancialCrisisService` | `system` |
| Marketplace bid / sale / auction | `MarketplaceService` | `marketplace_bid`, `marketplace_sold`, `system` |

Types reserved for future hooks: `battle_result`, `training_complete`, `league_update`, `event_started`, `hero_died`, `season_ended`.

---

## Design Decisions

| Decision | Rationale |
|----------|-----------|
| **Scoped to `User`, not `Team`** | Alerts follow the account (inactivity, account-level system messages). Marketplace notifications go to the seller's user via their team ownership. |
| **Separate from mail** | Mail (`community_message`) is player-to-player communication with optional team snapshot at send time. Notifications are one-way system → player alerts. |
| **Separate from team chronicle** | Chronicle is append-only **team** history visible across managers. Notifications are ephemeral account alerts with read state. |
| **Modal UI (not standalone page)** | Matches account settings and mail — opened from navbar, no `/app/notifications` route required initially. |
| **No email duplication** | Inactivity already sends email; in-app notification is supplementary when the player returns. |

---

## Data Model

Entity: `App\Entity\Notification\Notification`

| Field | Type | Notes |
|-------|------|-------|
| `user_id` | FK → User | Owner |
| `type` | `NotificationType` enum | Filtering + icon mapping |
| `title` | string (200) | Short headline |
| `body` | text | Full message |
| `is_read` | bool | Default `false` |
| `created_at` | datetime | UTC |

Index: `(user_id, is_read)` — already present.

**Future optional fields** (not required for v1): `action_url` (deep link), `context` (JSON with `listing_id`, etc.).

---

## API Endpoints (to implement)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/notifications` | List notifications for current user (newest first; query: `?unread_only=1`, `?limit=50`) |
| GET | `/api/v1/notifications/unread-count` | Badge count for navbar |
| GET | `/api/v1/notifications/{id}` | Single notification detail |
| PUT | `/api/v1/notifications/{id}/read` | Mark one as read |
| PUT | `/api/v1/notifications/read-all` | Mark all as read (optional v1.1) |

All routes require `ROLE_PLAYER`. Authorization: notification must belong to `getUser()`.

### Response shape (list item)

```json
{
  "id": 42,
  "type": "marketplace_sold",
  "title": "Hero sold",
  "body": "Your listing #123 was purchased for 500 gold.",
  "is_read": false,
  "created_at": "2026-06-17T14:30:00+00:00"
}
```

---

## Service Layer

### Refactor (recommended)

Extract read/query logic from the helper:

| Class | Responsibility |
|-------|----------------|
| `NotificationHelper` | Keep as thin write facade (or rename method to `NotificationService::create()`) |
| **`NotificationService`** *(new)* | `listForUser()`, `countUnread()`, `markRead()`, `markAllRead()`, `getForUser()` |

Repository additions on `NotificationRepository`:

- `findForUser(User $user, int $limit, bool $unreadOnly): array`
- `countUnreadForUser(User $user): int`

### Write path improvement (optional)

Replace per-call `flush()` inside `NotificationHelper` with persist-only; let the caller flush in the same transaction as the triggering action (marketplace purchase, crisis tick). Reduces double-flush overhead.

---

## Frontend (mirror mail modal)

Follow the established pattern in `mail_controller.js` + `templates/components/mail/`.

### Components to add

| Asset | Purpose |
|-------|---------|
| `assets/controllers/notifications_controller.js` | Load list, unread count, mark read, open detail |
| `templates/components/notifications/notifications_modal.html.twig` | List + read panel |
| `templates/components/notifications/js_templates.html.twig` | Row template for Stimulus cloning |
| `assets/styles/components/_notifications.scss` | Reuse mail modal layout tokens |

### Layout integration (`templates/layouts/game.html.twig`)

- Add `notifications` to root `data-controller` alongside `mail`
- Bootstrap `data-notifications-unread-count-value` from Twig helper (like `unread_mail_count()`)
- Include notification modal partials

### Navbar (`templates/components/layout/navbar.html.twig`)

- Add **bell icon** button next to mail (✉️ mail = player messages, 🔔 notifications = system alerts)
- Badge target: `data-notifications-target="badge"` (hidden when count = 0)
- `data-action="click->notifications#openModal"`

### Twig helper

Add to `GameExtension`:

```php
public function getUnreadNotificationCount(): int
```

Uses `NotificationRepository::countUnreadForUser()` for the authenticated user.

---

## Implementation Milestones

### Milestone A — Read API (backend only)

1. `NotificationRepository` query methods
2. `NotificationService` with authorization checks
3. `Api\V1\NotificationController` — list, unread-count, show, mark-read
4. PHPUnit: list filtering, ownership, mark-read idempotency
5. Update [route-map.md](../route-map.md) — remove **planned** markers

**Estimate:** small vertical slice; no migration needed.

### Milestone B — In-game UI

1. Stimulus controller + modal templates
2. Navbar bell + badge wired to unread-count endpoint
3. Poll or refresh unread count after marketplace/crisis actions (same pattern as mail badge)
4. Translation keys under `notifications.*` (cs/en)
5. Manual QA: trigger marketplace sale → badge increments → modal shows → mark read clears badge

**Estimate:** medium; mostly frontend following mail conventions.

### Milestone C — Hardening (optional)

- Retention policy: delete read notifications older than 90 days (daily tick or cron)
- `action_url` deep links (e.g. open economy tab on marketplace sale)
- Mark-all-read button
- Notification preferences in `UserSettings` (per-type toggles) — deferred until email prefs are designed

---

## Testing Checklist

- [ ] User A cannot read User B's notification
- [ ] Unread count matches list filter
- [ ] Mark read is idempotent
- [ ] Marketplace sale creates notification visible in UI
- [ ] Financial crisis warning appears after tick
- [ ] Account deletion cascades/removes notifications (already handled in inactive registration cleanup)

---

## Related Docs

- Mail (team messages): [community-system.md](community-system.md)
- Team history: [team-chronicle-system.md](team-chronicle-system.md)
- UI patterns: [ui-guidelines.md](../ui-guidelines.md#51-modals)
