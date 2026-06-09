# Authentication Screens (Login / Register / Password Reset)

Reference: [systems/auth-system.md](../systems/auth-system.md), [00-public-pages.md](00-public-pages.md)

**When:** Before gameplay — unauthenticated users

**Presentation:** Login and Register are presented as **modal windows** on public pages (Homepage, wiki, news archive) — NOT as standalone pages. Modals can switch between each other via inline links ("Already have an account?" / "Create new account").

---

## Register Modal

### Displayed Information:
- Registration form fields (email, password, confirm password, **game nickname**, **Kingdom selection**)
- Link to switch to Login modal ("Already have an account? Login")
- Terms of service / privacy policy links

### Possible Actions/Buttons:
- **Register** — submit registration form (email, password, confirm password, nickname, kingdom)
- **Switch to Login** — close Register modal, open Login modal

### Backend Requirements:
- `POST /register` — validate input (including nickname and kingdom selection), create user (unverified, `kingdom_id` set), send verification email
- Email uniqueness validation (reported openly: "This email is already registered")
- **Nickname**: required; uniqueness checked against `display_name_slug` (webalized form); reported openly: "This nickname is already taken"; stored as entered in `display_name`, webalized form in `display_name_slug`; displayed everywhere as entered
- Kingdom exists + has available capacity (returned as 422 with field error if full)
- Password strength requirements (min 8, max 4096)
- CSRF token (intent: `register`) — hidden `_token` field validated server-side; 403 on invalid/missing
- Rate limiting: `register` limiter — 10 requests per hour per IP; returns 429 with `Retry-After` header on exceed
- On success: show confirmation message ("Check your email to verify your account")
- Does NOT log in immediately — account requires email verification first

### Email Verification:
- Verification email contains a signed, time-limited link (24h expiry)
- `GET /verify-email?token=...` — validates token, activates account, assigns player a random NPC team in their Kingdom, logs in and redirects to **Team Dashboard**
- Resend verification link available (subject to same rate limiter)

---

## Login Modal

### Displayed Information:
- Login form (email + password)
- "Remember me" checkbox
- Link to switch to Register modal ("Create new account")
- Link to Password Reset

### Possible Actions/Buttons:
- **Login** — authenticate and redirect to Team Dashboard (inside section)
- **Switch to Register** — close Login modal, open Register modal
- **Forgot Password** — navigate to password reset flow

### Backend Requirements:
- `POST /login` — validate credentials, create session
- CSRF token (intent: `authenticate`) — hidden `_csrf_token` field validated by Symfony Security; 403 on invalid/missing
- Rate limiting: built-in `login_throttling` — 5 failed attempts per 15 minutes per IP+email; returns 429 with `Retry-After` header
- Unverified user attempting login sees: "Please verify your email first."
- Redirect to Team Dashboard on success (new players without a Kingdom are redirected to Kingdom Selection first)

---

## Password Reset Screen

### Displayed Information:
- Email input (request step)
- New password + confirm (reset step, via token link)

### Possible Actions/Buttons:
- **Send Reset Link** — trigger password reset email
- **Set New Password** — submit new password with valid token

### Backend Requirements:
- `POST /password-reset` — generate token, send email
- `POST /password-reset/confirm` — validate token, update password
- Token expiration (e.g., 1 hour)
- CSRF tokens — intent `password_reset_request` (request step), `password_reset_confirm` (confirm step); 403 on invalid/missing
- Rate limiting: `password_reset` limiter — 5 requests per hour per IP; returns 429 with `Retry-After` header

---

## Form Validation Rules

Validation runs on three layers: HTML5 attributes (immediate), JavaScript (pre-submit UX), and server-side (authoritative). Server-side is always the final authority.

### Register Form

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| Email | `type="email"` `required` `maxlength="180"` | NotBlank, Email (RFC), max 180, unique in DB | `register.email.*` |
| Password | `required` `minlength="8"` `maxlength="4096"` | NotBlank, min 8, max 4096 | `register.password.*` |
| Confirm password | `required` | Must match password field | `register.password_confirm.*` |
| Display name (nickname) | `required` `maxlength="50"` | NotBlank, max 50, unique (by slug) | `register.display_name.*` |
| Kingdom | `required` | Must reference an existing Kingdom with available capacity | `register.kingdom.*` |

### Login Form

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| Email | `type="email"` `required` | NotBlank, Email format | — |
| Password | `required` | NotBlank | — |

Login errors use a single generic message for invalid credentials (does not reveal which field is wrong).

### Password Reset — Request Step

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| Email | `type="email"` `required` `maxlength="180"` | NotBlank, Email format | `password_reset.email.*` |

### Password Reset — Confirm Step

| Field | HTML5 attrs | Server rules | Error key |
|-------|-------------|-------------|-----------|
| New password | `required` `minlength="8"` `maxlength="4096"` | NotBlank, min 8, max 4096 | `password_reset.password.*` |
| Confirm password | `required` | Must match new password | `password_reset.password_confirm.*` |

### Decisions

- **Password complexity**: Minimum 8 characters only (NIST SP 800-63B recommendation — length over complexity rules). No uppercase/number/special requirements.
- **Email uniqueness**: Reported openly ("This email is already registered") — acceptable for a game application where UX outweighs enumeration risk.
- **Max 4096 on passwords**: Prevents DoS via extremely long bcrypt inputs.

---

## Error Display Patterns

### Layout

- **Field errors**: Displayed inline, directly below the relevant input field. Red text, small font size.
- **Global errors**: Displayed as a dismissible banner at the top of the modal (above the form).
- **Success messages**: Green banner at the top (e.g., "Reset link sent").

### Error Classification

| Error type | Display location | Behavior |
|---|---|---|
| Missing/invalid field value | Inline below field | Appears on blur (JS) or on submit (server) |
| Email already registered | Inline below email field | Server-side only |
| Invalid credentials (login) | Global banner | Generic: "Invalid email or password" |
| Rate limit exceeded | Global banner | "Too many attempts. Try again in X minutes." |
| CSRF invalid/expired | Global banner | "Session expired. Please refresh and try again." |
| Server error (500) | Global banner | "Something went wrong. Please try again later." |
| Password mismatch | Inline below confirm field | JS: on blur/change; Server: on submit |

### UX Rules

1. **No alert()/confirm() dialogs** — all errors are inline or banner.
2. **Errors clear** when the user starts typing in the affected field (JS layer).
3. **Focus shifts** to the first field with an error after submit.
4. **Global banner** is dismissible via X button; auto-dismisses after 10 seconds for non-critical messages.
5. **All messages are translated** — use Symfony translation keys (locale cs/en), never hardcode strings.

### Translation Key Structure

Error messages use the `validators` translation domain (files: `translations/validators.en.yaml`, `translations/validators.cs.yaml`).

Usage in Twig:
```twig
{{ 'register.email.required'|trans({}, 'validators') }}
{{ 'global.rate_limit'|trans({'%minutes%': retryMinutes}, 'validators') }}
```

Key reference (see translation files for complete list):
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

Sections to fill:
- OAuth/social login buttons (future)
- UX notes and edge cases (expired sessions, already logged in)
- Tests and mocks
- Implementation notes
