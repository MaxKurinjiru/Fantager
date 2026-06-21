# Player Profile & Settings Screen

Reference: [screens-overview.md](../screens-overview.md#19-player-profile--settings-screen)

Purpose: Per-screen API, UI data requirements, and implementation notes for account-level player settings (distinct from team profile settings on the dashboard).

## Presentation

- **Inside section** (`/app/*`): Account settings open as a **modal** from the navbar (event `modal:open-account-settings`), not as a standalone page.
- **Route** `GET /app/settings` redirects to the dashboard; the modal is included in `templates/layouts/game.html.twig`.

## Displayed Information (implemented)

- **Language:** Czech / English selector — persists to `User.locale` via `/change-locale/{locale}`.
- **Interface preferences:**
  - **Backdrop zavírání modalů** (`closeModalOnBackdrop`) — checkbox; default `false`. When enabled, clicking the modal overlay closes the dialog. Stored in `auth_user_settings`.
- **Account email:** Current address shown as placeholder; form to request a new address (two-step email verification flow).
- **Danger zone:** Account cancellation with inline confirmation panel.

## Displayed Information (planned)

- Account info panel (display name, kingdom, member since, supporter tier)
- Notification settings (email / in-game toggles)
- Privacy settings (profile visibility, HQ visitor access, trade requests)
- Password change
- Connected accounts / support & donations

## Possible Actions (implemented)

- **Change language** — link to locale switcher
- **Toggle backdrop modal closing** — auto-saves via AJAX (`account-settings` Stimulus controller)
- **Request email change** — `POST /app/settings/change-email` → token flows `confirm-email-change/old`, `confirm-email-change/new`
- **Cancel account** — `POST /app/settings/cancel-account` → `confirm-cancel-account`

## Backend Requirements

### Data model

Player UI preferences live in a dedicated **`UserSettings`** entity (`auth_user_settings`), not as ad-hoc columns on `User`:

| Entity | Table | Relationship | Key fields |
|--------|-------|--------------|------------|
| **UserSettings** | `auth_user_settings` | 1:1 → User (CASCADE DELETE) | `close_modal_on_backdrop`, `updated_at` |

- Created automatically on registration via `UserSettingsService::getOrCreate()`.
- Legacy users without a row get settings lazily on first read/update.

Account identity fields remain on **`User`** (`email`, `locale`, `display_name`, etc.).

### Web routes (implemented)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/settings` | Redirect to dashboard (modal is the UI) |
| POST | `/app/settings/preferences` | Update interface preferences (JSON body, CSRF header) |
| POST | `/app/settings/change-email` | Initiate email change |
| GET | `/confirm-email-change/old` | Confirm current email |
| GET | `/confirm-email-change/new` | Confirm new email |
| POST | `/app/settings/cancel-account` | Initiate account deletion |
| GET | `/confirm-cancel-account` | Confirm and execute deletion |

### Preferences API

- **Route:** `POST /app/settings/preferences`
- **Auth:** `ROLE_PLAYER`, session cookie
- **CSRF:** `X-CSRF-Token` header (`csrf_token('api')`)
- **Request body:**
  ```json
  { "closeModalOnBackdrop": true }
  ```
- **Success response:**
  ```json
  {
    "message": "Preferences saved.",
    "closeModalOnBackdrop": true
  }
  ```
- **Errors:** `403` (invalid CSRF), `400` (missing/invalid payload)

Future preferences should extend this endpoint (or add PATCH with partial updates) and corresponding columns on `UserSettings`.

### Planned API

- `GET /api/v1/settings` — read all settings
- `PUT /api/v1/settings` — bulk update
- Password change, notification, and privacy endpoints

## Frontend Implementation

| Piece | Location |
|-------|----------|
| Modal template | `templates/components/layout/_account_settings_modal.html.twig` |
| Stimulus controller | `assets/controllers/account_settings_controller.js` |
| Preference bootstrap | `data-user-pref-close-modal-on-backdrop` on game layout root (`templates/layouts/game.html.twig`) |
| Runtime preference read | `assets/utils/user_preferences.js` |
| Modal close behaviour | `assets/controllers/modal_controller.js`, `assets/controllers/auth_modal_controller.js` |
| Mobile back / gesture | `assets/utils/modal_history.js` — `history.pushState` on open; browser back closes topmost modal |

### Modal behaviour rules

- **Escape** — always closes the active modal.
- **Backdrop click** — closes only when `UserSettings.closeModalOnBackdrop === true`.
- **Mobile back** — always closes the active modal (stacked modals close one level per back press).
- **Focus** — first focusable element on open; focus restored to trigger on close (see [ui-guidelines.md](../ui-guidelines.md#51-modals)).

## Service layer

- **`UserSettingsService`** (`App\Service\Auth\UserSettingsService`) — `getOrCreate(User $user): UserSettings`
- Used by `RegistrationService`, `TestUserService`, and `SettingsController::updatePreferences()`

## Tests

- `tests/Controller/Web/SettingsControllerTest.php` — CSRF and validation for preferences endpoint
- `tests/Service/Auth/UserSettingsServiceTest.php` — get-or-create behaviour
