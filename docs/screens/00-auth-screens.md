# Authentication Screens (Login / Register / Password Reset)

Reference: [systems/auth-system.md](../systems/auth-system.md), [00-public-pages.md](00-public-pages.md), [architecture/screen-code-map.md](../architecture/screen-code-map.md)

**When:** Before gameplay — unauthenticated users (or logged-in users hitting `/login` / `/register`, who are redirected to the dashboard).

---

## Presentation — Implemented

Login and Register are **one auth modal** (`templates/auth/_modal.html.twig`) included from `templates/base.html.twig` for guests. Panels switch via Stimulus `auth_modal` (`openLogin` / `openRegister` / `switchToLogin` / `switchToRegister`).

- Homepage and guest navbar CTAs `preventDefault` and open the modal; `href` still points at `/login` or `/register`.
- `GET /login` and `GET /register` render the public layout with an empty body and `data-auth-modal-open-value` so the modal opens on load (`Web\AuthController`, `Web\RegisterController`).
- Already logged-in users hitting those routes go to `/app/dashboard`.
- **Password reset** is **standalone pages** (`/password-reset`, `/password-reset/confirm`) — not the auth modal.
- Post-register landing: `/register/success` (check email + resend form).

Kingdom pick lives on the register panel — [01-kingdom-selection.md](01-kingdom-selection.md).

---

## Register — Implemented

**Routes:** `GET, POST /register`, `GET /register/success`, `POST /register/resend-verification`, `GET /verify-email`.

### Displayed information

- Email, password, confirm password, game nickname (`display_name`)
- Kingdom list (desktop radio cards + mobile `<select>`)
- Link/button to switch to the login panel
- Password visibility toggle
- Server field errors inline; empty-kingdom warning when none are available

### Actions

- **Register** — `POST /register` (does **not** log in)
- **Switch to login** — same modal
- On success: redirect to `/register/success` (check-email copy + resend)

### Backend

- `RegistrationService`: create unverified user, set `kingdom_id`, assign a random available NPC team, default `UserSettings`, send verification email
- Email uniqueness reported openly (`register.email.taken`)
- Nickname: required; unique on `display_name_slug`; stored as entered in `display_name`
- Kingdom must exist and have capacity (422-style field error if full)
- Password: min 8, max 4096
- CSRF intent `register` (`_token`); 403 / flash on invalid
- Rate limit `register`: 10/hour/IP; 429 + `Retry-After` on the POST JSON path
- `GET /verify-email?token=...` — activate, log in, redirect to Team Dashboard (NPC team was assigned at **registration**, not at verify)
- Resend: `POST /register/resend-verification`, CSRF `register_resend`, same register limiter

Detail: [auth-system.md](../systems/auth-system.md).

---

## Register — Planned

- Terms of service / privacy policy links (`auth.terms_agree` exists in translations; **not** on the form)
- OAuth / social login
- Client-side (JS) field validation beyond HTML5 `required` / `minlength` / `maxlength`

---

## Login — Implemented

**Route:** `GET, POST /login` (POST is Symfony `form_login` `check_path`).

### Displayed information

- Email (`_username`) + password (`_password`)
- Remember me (`_remember_me`)
- Switch to register; forgot-password link → `/password-reset`
- Authentication error banner (server)

### Actions

- **Login** — session; redirect to Team Dashboard (kingdom and team are already set)
- **Switch to register** — same modal
- **Forgot password** — full page

### Backend

- CSRF `_csrf_token` intent `authenticate`
- `login_throttling`: 5 failed attempts / 15 minutes / IP+email; 429 + `Retry-After`
- Unverified user: “Please verify your email first.”
- No post-login kingdom-selection redirect

---

## Password reset — Implemented

**Routes:** `GET, POST /password-reset`, `GET, POST /password-reset/confirm`.

Standalone public pages (`templates/auth/password_reset_request.html.twig`, `password_reset_confirm.html.twig`).

### Displayed information / actions

- Request: email → send reset link
- Confirm: new password + confirm (token in query); invalid-token alert if missing
- Back to login

### Backend

- Token via `VerificationToken` (see [auth-system.md](../systems/auth-system.md))
- CSRF: `password_reset_request` / `password_reset_confirm`
- Rate limit `password_reset`: 5/hour/IP; 429 + `Retry-After`

---

## Form validation (live)

Authoritative layer is **server-side**. HTML5 attributes run in the browser. Stimulus `auth_modal` does **not** validate on blur or clear errors while typing.

### Register form

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| Email | `type="email"` `required` `maxlength="180"` | NotBlank, Email (RFC), max 180, unique in DB | `register.email.*` |
| Password | `required` `minlength="8"` `maxlength="4096"` | NotBlank, min 8, max 4096 | `register.password.*` |
| Confirm password | `required` | Must match password field | `register.password_confirm.*` |
| Display name (nickname) | `required` `maxlength="50"` | NotBlank, max 50, unique (by slug) | `register.display_name.*` |
| Kingdom | `required` on the control | Existing Kingdom with capacity | `register.kingdom.*` |

### Login form

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| Email | `type="email"` `required` | NotBlank, Email format | — |
| Password | `required` | NotBlank | — |

Login errors use a single generic message for invalid credentials (does not reveal which field is wrong).

### Password reset — request

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| Email | `type="email"` `required` `maxlength="180"` | NotBlank, Email format | `password_reset.email.*` |

### Password reset — confirm

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| New password | `required` `minlength="8"` `maxlength="4096"` | NotBlank, min 8, max 4096 | `password_reset.password.*` |
| Confirm password | `required` | Must match new password | `password_reset.password_confirm.*` |

### Decisions (locked)

- **Password complexity**: minimum 8 characters only (no uppercase/number/special rules). Max 4096 (bcrypt DoS).
- **Email uniqueness**: reported openly (“This email is already registered”).

---

## Error display — Implemented

- **Field errors (register):** inline under the input after `POST /register` re-renders the modal.
- **Login:** banner above the form from Symfony authentication error.
- **Flashes:** public layout flash bag (rate limit, CSRF, verify/resend, success).
- **No** `alert()` / `confirm()` dialogs.
- Messages go through translation keys (`validators` / `security` / `messages`).

## Error display — Planned

- JS validation on blur; clear errors while typing; focus first invalid field after submit
- Dismissible global banner with auto-dismiss timer

### Translation keys (validators domain)

```
register.email.required
register.email.invalid
register.email.max_length
register.email.taken
register.password.required
register.password.min_length
register.password.max_length
register.password_confirm.mismatch
login.invalid_credentials
password_reset.email.required
password_reset.email.invalid
password_reset.password.required
password_reset.password.min_length
password_reset.password.max_length
password_reset.password_confirm.mismatch
password_reset.token.expired
password_reset.token.invalid
global.rate_limit
global.csrf_expired
global.server_error
```

---

## Planned (auth extras)

- OAuth / social login
- Dedicated handling notes for expired sessions beyond current flashes / login redirect
- In-session **password change** for logged-in players — not this screen; stay **Planned** on [19-player-profile-settings.md](19-player-profile-settings.md) (A8.6 keep Planned)
