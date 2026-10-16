# Player Profile & Settings Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [auth-system.md](../systems/auth-system.md)

**When:** Account-level player settings (distinct from team profile settings on the dashboard).

**Status:** Modal UI is live for language, backdrop-close preference, email change, and account deletion.

**Live UI:** `templates/components/layout/_account_settings_modal.html.twig`. Stimulus `account_settings`, `profile_settings`, `modal`.

**Lock:** [Locked: password change / extra settings stay Planned](#locked-password-change--extra-settings-stay-planned)

---

## Implemented

### Presentation

- **Inside section** (`/app/*`): Account settings open as a **modal** from the navbar (event `modal:open-account-settings`), not as a standalone page.
- **Route** `GET /app/settings` redirects to the dashboard; the modal is included in `templates/layouts/game.html.twig`.

### Displayed information

- **Language:** Czech / English selector — persists to `User.locale` via `/change-locale/{locale}`.
- **Interface preferences:**
  - **Close modals on backdrop click** (`closeModalOnBackdrop`) — checkbox; default `false`. When enabled, clicking the modal overlay closes the dialog. Stored in `auth_user_settings`. Label key: `settings.modal_backdrop_close`.
- **Account email:** Current address shown as placeholder; form to request a new address (two-step email verification flow).
- **Danger zone:** Account cancellation with inline confirmation panel.

### Actions

- **Change language** — link to locale switcher
- **Toggle backdrop modal closing** — auto-saves via AJAX (`account-settings` Stimulus controller)
- **Request email change** — `POST /app/settings/change-email` → token flows `confirm-email-change/old`, `confirm-email-change/new`
- **Cancel account** — `POST /app/settings/cancel-account` → `confirm-cancel-account`

### Backend

#### Data model

Player UI preferences live in a dedicated **`UserSettings`** entity (`auth_user_settings`), not as ad-hoc columns on `User`:

| Entity | Table | Relationship | Key fields |
|--------|-------|--------------|------------|
| **UserSettings** | `auth_user_settings` | 1:1 → User (CASCADE DELETE) | `close_modal_on_backdrop`, `updated_at` |

- Created automatically on registration via `UserSettingsService::getOrCreate()`.
- Legacy users without a row get settings lazily on first read/update.

Account identity fields remain on **`User`** (`email`, `locale`, `display_name`, etc.).

#### Web routes (implemented)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/settings` | Redirect to dashboard (modal is the UI) |
| POST | `/app/settings/preferences` | Update interface preferences (JSON body, CSRF header) |
| POST | `/app/settings/change-email` | Initiate email change |
| GET | `/confirm-email-change/old` | Confirm current email |
| GET | `/confirm-email-change/new` | Confirm new email |
| POST | `/app/settings/cancel-account` | Initiate account deletion |
| GET | `/confirm-cancel-account` | Confirm and execute deletion |

#### Preferences API

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

Do not add password-change, notification, or privacy fields here until a later product decision. New live UI prefs (like backdrop-close) may still extend this endpoint or add PATCH with partial updates and corresponding columns on `UserSettings`.

#### Related API

- `GET /api/v1/me` — current player identity (not settings); see [auth-system.md](../systems/auth-system.md)
- Password change / dedicated `/api/v1/settings` bulk endpoints stay **Planned**; preferences use `POST /app/settings/preferences` above

### Frontend

| Piece | Location |
|-------|----------|
| Modal template | `templates/components/layout/_account_settings_modal.html.twig` |
| Stimulus controller | `assets/controllers/account_settings_controller.js` |
| Preference bootstrap | `data-user-pref-close-modal-on-backdrop` on game layout root (`templates/layouts/game.html.twig`) |
| Runtime preference read | `assets/utils/user_preferences.js` |
| Modal close behaviour | `assets/controllers/modal_controller.js`, `assets/controllers/auth_modal_controller.js` |
| Mobile back / gesture | `assets/utils/modal_history.js` — `history.pushState` on open; browser back closes topmost modal |

#### Modal behaviour rules

- **Escape** — always closes the active modal.
- **Backdrop click** — closes only when `UserSettings.closeModalOnBackdrop === true`.
- **Mobile back** — always closes the active modal (stacked modals close one level per back press).
- **Focus** — first focusable element on open; focus restored to trigger on close (see [guides/ui-guidelines.md](../guides/ui-guidelines.md#51-modals)).

### Service layer

- **`UserSettingsService`** (`App\Service\Auth\UserSettingsService`) — `getOrCreate(User $user): UserSettings`
- Used by `RegistrationService`, `TestUserService`, and `SettingsController::updatePreferences()`

### Tests

- `tests/Controller/Web/SettingsControllerTest.php` — CSRF and validation for preferences endpoint
- `tests/Service/Auth/UserSettingsServiceTest.php` — get-or-create behaviour

## Parked

Index: [known-issues.md](../product/known-issues.md) § Later product decisions. Password change, notification prefs, privacy, account-info panel, and connected accounts are A8.6 — [Locked: password change / extra settings stay Planned](#locked-password-change--extra-settings-stay-planned).

## Locked: password change / extra settings stay Planned

Session password change, notification prefs, privacy, account-info panel, and connected accounts stay **Planned**. Do not ship a password-change form, per-type notification toggles, privacy flags, or `GET/PUT /api/v1/settings`. Live settings are language, backdrop-close, email change, and account deletion. Forgotten passwords use `/password-reset`. A later product decision is required before adding any of these.
