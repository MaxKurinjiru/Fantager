# Notification System

Reference: [architecture/entities.md](../architecture/entities.md#15-notification-domain), [architecture/routes.md](../architecture/routes.md#notifications), [community-system.md](community-system.md)

Status: Live (navbar modal + API; title/body rendered at **read time** from keys, like chronicle)  
Screens: navbar modal (shell feature — [screens/README.md](../screens/README.md#shell-features-no-dedicated-screen-file)) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none

Purpose: In-game alerts for the **logged-in player account** (distinct from team-to-team mail and from the append-only team chronicle).

## Implementation Status

| Layer | Status |
|-------|--------|
| **Entity** | Implemented — `App\Entity\Notification\Notification` (`title_key` / `body_key` + params) |
| **Write path** | Implemented — `NotificationHelper::sendTranslatedNotification()` stores keys + params |
| **Read API** | Implemented — `NotificationService`, `NotificationPresenter`, `Api\V1\NotificationController` |
| **In-game UI** | Implemented — navbar modal + unread badge (`notifications_controller.js`) |

### Existing write hooks (today)

| Trigger | Service | `NotificationType` |
|---------|---------|-------------------|
| Inactivity warning / release | `PlayerInactivityService` | `system` |
| Financial crisis escalation | `FinancialCrisisService` | `financial_crisis` (bankruptcy release may use `system`) |
| Marketplace bid / sale / auction / expiry | `MarketplaceService` | `marketplace_bid`, `marketplace_sold`, `system` |
| HQ upgrade / downgrade / cancel / arena adaptation | `HeadquartersService` | `hq_upgrade`, `system` |
| League battle result | `LeagueMatchResolutionService` | `battle_result` |
| Friendly-match battle result | `FriendlyMatchResolutionService` | `battle_result` |
| Permanent combat death | `LeagueMatchResolutionService`, `FriendlyMatchResolutionService` | `hero_died` |
| Season ended | `SeasonTransitionService` | `season_ended` |
| Weekly training complete | `TrainingService` | `training_complete` |

All production writers call `sendTranslatedNotification()` (keys under `notification.*`).

### Unused / reserved types (A6.2)

These `NotificationType` cases exist in the enum but have **no writers**. Do not hook them “for completeness”.

| Enum case | Value | Notes |
|-----------|-------|--------|
| `LeagueUpdate` | `league_update` | Reserved / unused |
| `EventStarted` | `event_started` | Reserved / unused (world events are deferred) |
| `SpellLearned` | `spell_learned` | Reserved / unused as a notification; team/hero chronicle already records spell learning |

---

## Design Decisions

| Decision | Rationale |
|----------|-----------|
| **Scoped to `User`, not `Team`** | Alerts follow the account (inactivity, account-level system messages). Marketplace notifications go to the seller's user via their team ownership. |
| **Separate from mail** | Mail (`community_message`) is player-to-player communication with optional team snapshot at send time. Notifications are one-way system → player alerts. |
| **Separate from team chronicle** | Chronicle is append-only **team** history visible across managers. Notifications are ephemeral account alerts with read state. |
| **Read-time translation (A6.4)** | Same pattern as chronicle: store `title_key` / `body_key` + params; `NotificationPresenter` renders in the reader's locale. Locale switch re-renders keyed rows. Pre-A6.4 rows (keys null) keep frozen `title` / `body`. |
| **Modal UI (not standalone page)** | Matches account settings and mail — opened from navbar, no `/app/notifications` route required initially. |
| **No email duplication** | Inactivity already sends email; in-app notification is supplementary when the player returns. |

---

## Data Model

Entity: `App\Entity\Notification\Notification`

| Field | Type | Notes |
|-------|------|-------|
| `user_id` | FK → User | Owner |
| `type` | `NotificationType` enum | Filtering + icon mapping |
| `title_key` | string (255), nullable | Symfony key under `notification.*`; null on pre-A6.4 rows |
| `body_key` | string (255), nullable | Same as title_key |
| `title_params` | JSON | Placeholders for the title key (default `[]`) |
| `body_params` | JSON | Placeholders for the body key (default `[]`) |
| `title` | string (200), nullable | Legacy write-time headline; unused when `title_key` is set |
| `body` | text, nullable | Legacy write-time body; unused when `body_key` is set |
| `is_read` | bool | Default `false` |
| `created_at` | datetime | UTC |

Index: `(user_id, is_read)`.

**Future optional fields** (not required for v1): `action_url` (deep link), `context` (JSON with `listing_id`, etc.).

---

## Query contract (`NotificationController` is source of truth)

`GET /api/v1/notifications` clamps in `NotificationController::list()`, not in the service default:

| Query | Live rule |
|-------|-----------|
| `limit` | Default **10**. Clamped to **1–100** (`min(100, max(1, (int) $limit))`). |
| `page` | Default **1**. Minimum **1**. |
| `unread_only` | Optional boolean (`FILTER_VALIDATE_BOOL`). |

List JSON envelope: `{ items, page, total_pages, total_items }`. Newest first.

`NotificationService::listForUser()` / `NotificationRepository::findForUser()` default `$limit` to 50 when called without the API; the repository also caps `setMaxResults` at 100. **Public API and navbar UI use the controller contract (10 / 100).** The navbar requests `?limit=10&page=`.

`PUT /api/v1/notifications/read-all` has **no** 100-item cap (bulk update of all unread rows for the user).

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/notifications` | List for current user (see query contract above) |
| GET | `/api/v1/notifications/unread-count` | Badge count for navbar (`{ "count": n }`) |
| GET | `/api/v1/notifications/{id}` | Single notification detail; **marks the notification as read** (navbar modal relies on this) |
| PUT | `/api/v1/notifications/{id}/read` | Mark one as read |
| PUT | `/api/v1/notifications/read-all` | Mark all as read (`{ "marked": n }`) |

All routes require `ROLE_PLAYER`. Authorization: notification must belong to `getUser()`. Missing id → 404.

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

`title` / `body` in the JSON are **rendered** strings in the current user's locale (not the stored keys).

---

## Translation (read-time, A6.4)

Same pattern as team/hero chronicle (`subject_key` + params → presenter).

1. Domain services call `NotificationHelper::sendTranslatedNotification($user, $type, $titleKey, $bodyKey, $titleParams, $bodyParams)`.
2. The helper persists keys + params. It does **not** translate at send time. `title` / `body` stay null on new rows.
3. `NotificationPresenter::present($notification, $user->getLocale())` renders `notification.*` keys for the **reader's** locale. Nested params:
   - `facility` → `hq.facilities_list.{value}.name`
   - `race` → `heroes.race_{value}` (skip empty / `—`)
   - `status` / `status_text` → `activity.season_status.{value}` (`retained` maps to `maintained`)
4. The read API and Stimulus modal display the presenter output.
5. Rows with null keys (written before A6.4) fall back to the frozen `title` / `body` columns.

Changing language in Account Settings therefore re-renders keyed notifications, matching chronicle.

---

## Service Layer

| Class | Responsibility |
|-------|----------------|
| `NotificationHelper` | Write facade. Prefer `sendTranslatedNotification()` (keys + params). `sendNotification()` persists already-rendered strings (legacy / tests). Each send currently `flush()`es. |
| `NotificationPresenter` | Read-time i18n. Renders title/body in the given locale; nested facility / race / season-status params. |
| `NotificationService` | `listForUser()`, `countForUser()`, `countUnread()`, `markRead()`, `markAllRead()` (bulk update, no 100-cap), `getForUser()` |

Repository methods on `NotificationRepository`:

- `findForUser(User $user, int $limit, bool $unreadOnly, ?int $page): array`
- `countForUser(User $user, bool $unreadOnly): int`
- `countUnreadForUser(User $user): int`
- `markAllUnreadAsRead(User $user): int`

### Write path improvement (optional)

Replace per-call `flush()` inside `NotificationHelper` with persist-only; let the caller flush in the same transaction as the triggering action (marketplace purchase, crisis tick). Reduces double-flush overhead.

---

## Frontend

Follow the established pattern in `mail_controller.js` + `templates/components/mail/`.

| Asset | Purpose |
|-------|---------|
| `assets/controllers/notifications_controller.js` | Load list (`limit=10` + `page`), unread count, mark read, open detail |
| `templates/components/notifications/notifications_modal.html.twig` | List + read panel |
| `templates/components/notifications/js_templates.html.twig` | Row template for Stimulus cloning |
| `assets/styles/components/_notifications.scss` | Reuse mail modal layout tokens |

Navbar bell + badge are wired in `templates/components/layout/navbar.html.twig`. Twig helper: `GameExtension::getUnreadNotificationCount()`.

---

## Parked

Index: [known-issues.md](../product/known-issues.md) § Later product decisions (notification prefs are A8.6 extra settings). Unique to this system:

- Retention policy: delete read notifications older than 90 days (daily tick or cron)
- `action_url` deep links (e.g. open economy tab on marketplace sale)

---

## Tests

Unit coverage: `tests/Service/Notification/NotificationServiceTest.php`, `NotificationPresenterTest.php`, `NotificationHelperTest.php`. Cross-user access is a 404 from `getForUser()` / `markRead()`.

---

## Related Docs

- Mail (team messages): [community-system.md](community-system.md)
- Team history: [team-chronicle-system.md](team-chronicle-system.md)
- UI patterns: [guides/ui-guidelines.md](../guides/ui-guidelines.md#51-modals)
