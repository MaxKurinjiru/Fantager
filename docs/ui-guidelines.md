# UI & CSS Design Guidelines

This document is the **single authoritative reference** for frontend design decisions, component usage, and coding standards across all views.  
All contributors — human and AI alike — must follow these rules to keep the UI consistent, accessible, and maintainable.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Design Tokens](#2-design-tokens)
3. [Atomic Component Model](#3-atomic-component-model)
4. [Atoms](#4-atoms)
   - [Buttons](#41-buttons)
   - [Form Elements](#42-form-elements)
   - [Links](#43-links)
   - [Alerts / Flash Messages](#44-alerts--flash-messages)
5. [Molecules](#5-molecules)
   - [Modals](#51-modals)
6. [Accessibility](#6-accessibility)
7. [Naming Conventions](#7-naming-conventions)
8. [Do & Don't](#8-do--dont)
9. [Stimulus Controller Guidelines](#9-stimulus-controller-guidelines)
   - [Structure & Lifecycle](#91-structure--lifecycle)
   - [Naming Conventions](#92-naming-conventions)
   - [State Management & UI Toggles](#93-state-management--ui-toggles)
   - [API Communications](#94-api-communications)
   - [Accessibility & Focus](#95-accessibility--focus)
   - [Strict Rules & Constraints](#96-strict-rules--constraints)

---

## 1. Architecture Overview

Styles are organised in three layers, loaded via [`app.scss`](../assets/styles/app.scss):

```
assets/styles/
├── abstracts/
│   └── _tokens.scss      ← design tokens (single source of truth)
├── base/
│   └── _base.scss        ← element-level resets & typographic defaults
└── components/
    ├── _buttons.scss
    ├── _flash.scss
    ├── _forms.scss
    ├── _links.scss
    └── _modal.scss
```

| Layer | Purpose | Do |
|---|---|---|
| `abstracts/` | Variables only — no output CSS | Define tokens here, nowhere else |
| `base/` | Bare HTML element defaults | Use `@layer base` |
| `components/` | Reusable class-based UI atoms | Use `@layer components` |

> **Rule:** Never hard-code a colour, radius, or transition speed outside `_tokens.scss`. Always reference a CSS custom property (`var(--…)`).

---

## 2. Design Tokens

All visual constants live in [`_tokens.scss`](../assets/styles/abstracts/_tokens.scss).  
The file has two blocks:

- **`@theme {}`** — extends Tailwind v4 utilities (`bg-brand-500`, `text-text-primary`, …)
- **`:root {}`** — semantic properties for use in hand-written component CSS

### Colour Palette Reference

| Token | Value | Usage |
|---|---|---|
| `--color-brand-500` | `#10b981` | Primary CTA, active states |
| `--color-brand-400` | `#34d399` | Hover state of primary |
| `--color-surface-page` | `#0f1720` | Root background |
| `--color-surface-base` | `#0b1220` | Deepest inset elements |
| `--color-surface-raised` | `#162030` | Cards, inputs |
| `--color-surface-overlay` | `#1a2535` | Dropdowns, popovers |
| `--color-text-primary` | `#e6edf3` | Body copy |
| `--color-text-secondary` | `#9ca3af` | Labels, metadata |
| `--color-text-muted` | `#6b7280` | Placeholders, hints |
| `--color-border` | `#374151` | Default borders |
| `--color-border-strong` | `#4b5563` | Focused / elevated borders |

### Status Tokens

Each status has three tokens — always use all three together:

| Status | `-bg` | `-border` | `-text` |
|---|---|---|---|
| success | `#052e16` | `#15803d` | `#86efac` |
| error | `#450a0a` | `#b91c1c` | `#fca5a5` |
| warning | `#422006` | `#92400e` | `#fde68a` |
| info | `#082f49` | `#1e40af` | `#93c5fd` |

### Spacing & Motion Tokens

| Token | Value | Usage |
|---|---|---|
| `--radius-sm` | `0.375rem` | Tight UI (badges, chips) |
| `--radius-md` | `0.5rem` | Inputs, buttons |
| `--radius-lg` | `0.75rem` | Cards |
| `--radius-xl` | `1rem` | Modals, large panels |
| `--transition-fast` | `120ms ease` | Hover colour swaps |
| `--transition-base` | `160ms ease` | Appearing elements |
| `--focus-ring` | `0 0 0 3px rgba(16,185,129,.5)` | Keyboard focus ring |

### Adding New Tokens

1. Add the value to `_tokens.scss` inside the appropriate block.
2. Document it in the table above.
3. Use only via `var(--…)` in component files.

---

## 3. Atomic Component Model

This project follows a **lightweight Atomic Design** approach:

```
Atoms      → smallest reusable pieces (btn, form-input, alert, …)
Molecules  → combinations of atoms with logic (modal, form group, …)
Organisms  → page sections built from molecules (nav, sidebar, …)
```

### Rules

- **Atoms** are defined in `components/` SCSS files and documented below.
- **Molecules** use atoms as building blocks — never duplicate atom styles.
- **Organisms** are composed in Twig templates using the classes documented here.
- **Never create a one-off style** for something that looks like an existing atom — extend it instead.

---

## 4. Atoms

### 4.1 Buttons

**Source:** [`_buttons.scss`](../assets/styles/components/_buttons.scss)

All buttons use the two-class pattern: **`.btn`** (base) + **variant modifier**.

#### Variants

| Class | Use case |
|---|---|
| `.btn-primary` | Main call-to-action (create, save, submit) |
| `.btn-outline` | Secondary action (cancel, back) |
| `.btn-ghost` | Tertiary / low-emphasis (links masquerading as buttons) |
| `.btn-danger` | Destructive actions (delete, remove) |

#### Size Modifiers

| Class | When to use |
|---|---|
| *(none)* | Default — most contexts |
| `.btn--sm` | Compact tables, inline actions, badges |
| `.btn--lg` | Hero CTAs, prominent single-action forms |

#### Usage

```html
<!-- Primary -->
<button type="submit" class="btn btn-primary">Save changes</button>

<!-- Secondary -->
<button type="button" class="btn btn-outline">Cancel</button>

<!-- Destructive -->
<button type="button" class="btn btn-danger btn--sm">Delete</button>

<!-- Disabled (use attribute, not just class) -->
<button type="button" class="btn btn-primary" disabled>Processing…</button>

<!-- Link styled as button -->
<a href="/play" class="btn btn-primary">Play now</a>
```

#### Rules

- Always set `type="submit"` or `type="button"` — never omit it.
- Use the `disabled` attribute for disabled state; do **not** only rely on opacity.
- For destructive actions, always show a confirmation step before submission.
- Do **not** nest a `<button>` inside another `<button>`.

---

### 4.2 Form Elements

**Source:** [`_forms.scss`](../assets/styles/components/_forms.scss)

#### Available Classes

| Class | Element | Purpose |
|---|---|---|
| `.form-label` | `<label>` | Field label |
| `.form-input` | `<input>`, `<select>`, `<textarea>` | Text input |
| `.form-input--error` | modifier on `.form-input` | Red border on validation failure |
| `.form-error` | `<p>` or `<span>` | Inline validation error message |
| `.form-hint` | `<p>` or `<span>` | Helper text below the field |
| `.form-check` | `<div>` wrapper | Checkbox / radio row with aligned label |

#### Canonical Field Group

Always wrap a label + input + error in a single container:

```html
<div class="mb-4">
    <label for="email" class="form-label">Email address</label>
    <input
        id="email"
        type="email"
        name="email"
        class="form-input"
        autocomplete="email"
        required
    >
    <p class="form-error" role="alert">{{ error }}</p>
    {{-- or hint: --}}
    <p class="form-hint">We'll never share your email.</p>
</div>
```

#### Error State

Add `.form-input--error` programmatically when a field fails validation:

```html
<input class="form-input form-input--error" aria-describedby="email-error">
<p id="email-error" class="form-error" role="alert">This field is required.</p>
```

#### Rules

- Every `<input>` **must** have a matching `<label>` (use `for`/`id` pairing).
- Use `aria-describedby` to associate error/hint messages with the field.
- Use `autocomplete` on login/registration inputs.
- Never remove focus styles — they are required for keyboard navigation.
- `<select>` and `<textarea>` use `.form-input` too — no separate class needed.

---

### 4.3 Links

**Source:** [`_links.scss`](../assets/styles/components/_links.scss)

| Selector | Colour | Use |
|---|---|---|
| `a:not([class])` | `--color-success-text` (emerald-300) | Inline body text links |
| `a.link` | same as above | Explicit brand link in a component |
| Hover / focus | `--color-brand-500` | Darker on interaction |

#### Rules

- Never add `text-decoration` manually — it is handled globally.
- Use `.link` when you need a brand-coloured link inside a component that already has other `<a>` tags.
- Links that navigate to external URLs must have `target="_blank" rel="noopener noreferrer"`.
- Links that trigger actions (e.g., log out) must use `<button>` instead of `<a>`.

---

### 4.4 Alerts / Flash Messages

**Source:** [`_flash.scss`](../assets/styles/components/_flash.scss)

Use the **`.alert`** base class + a status modifier:

```html
<div class="alert alert-success" role="alert">Profile saved successfully.</div>
<div class="alert alert-error"   role="alert">Authentication failed.</div>
<div class="alert alert-warning" role="alert">Your session expires in 5 minutes.</div>
<div class="alert alert-info"    role="alert">Maintenance window tonight at 00:00.</div>
```

#### Rules

- Always add `role="alert"` so screen readers announce the message immediately.
- Flash messages injected via Symfony should render above the page content, not inline.
- Do not use status colours (success-bg, error-border, …) outside of `.alert` variants — create a new component if needed.

---

## 5. Molecules

### 5.1 Modals

**Source:** [`_modal.scss`](../assets/styles/components/_modal.scss)

Modals are built from the overlay + box atoms and controlled by the Stimulus `modal` controller.

#### Structure (standard pattern)

The overlay element carries `data-modal-target="dialog"` and the backdrop click handler:

```html
<div data-controller="modal">
    <div data-modal-target="dialog"
         class="hidden modal-overlay"
         data-action="click->modal#closeBackdrop">

        <div class="modal-box"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-title-{id}">

            <!-- Close button — see 5.1.1 -->

            <h2 id="modal-title-{id}" class="text-lg font-semibold mb-4">
                Dialog Title
            </h2>

            <!-- Content -->

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" data-action="click->modal#close" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>
</div>
```

Some modals (e.g. community compose) use the overlay as the controller root; `modal_controller.js` detects this and toggles visibility on the correct element. Backdrop clicks on that variant are handled in `connect()` when the preference is enabled.

#### 5.1.1 Standard Close Button

Close buttons **must** use the `.modal-close` class. Do **not** use raw `&times;` or unstyled close tags.

```html
<button type="button"
        data-action="click->modal#close"
        class="modal-close"
        aria-label="{{ 'modal.close'|trans }}">
    <span aria-hidden="true" class="text-xl leading-none">✕</span>
    <span class="sr-only">{{ 'modal.close'|trans }}</span>
</button>
```

#### Rules

- `aria-labelledby` must point to the modal's heading `id`.
- The first focusable element inside the modal should receive focus when it opens (handled by the Stimulus controller).
- **Escape** always closes the active modal.
- **Backdrop click** closes the modal only when the player has enabled **Backdrop zavírání modalů** in Account Settings (`UserSettings.closeModalOnBackdrop`, default `false`). Read at runtime via `assets/utils/user_preferences.js` (bootstrapped from `data-user-pref-close-modal-on-backdrop` on the game layout).
- **Mobile back / swipe-back** always closes the topmost open modal. Implemented in `assets/utils/modal_history.js` using `history.pushState` on open and `popstate` on back; programmatic close calls `history.back()` without double-closing.
- Never place a modal inside another modal (stacked overlays such as mail read-on-compose are separate controller instances with their own history entry).
- `aria-modal="true"` is required; it prevents screen readers from reading background content.

---

## 6. Accessibility

These are non-negotiable requirements, not optional enhancements.

### Keyboard Navigation

- All interactive elements must be reachable via `Tab` in a logical order.
- Focus state must be clearly visible — defined by `--focus-ring` (emerald glow).
- Avoid `tabindex > 0`; use DOM order to control focus sequence.

### Colour Contrast

- Body text on page background: **≥ 4.5:1** (WCAG AA).
- Large text (≥ 18px or ≥ 14px bold): **≥ 3:1**.
- Never communicate status by colour alone — always pair with text or an icon.

### ARIA Usage

| Pattern | Correct attribute |
|---|---|
| Modal dialog | `role="dialog"` + `aria-modal="true"` + `aria-labelledby` |
| Alert messages | `role="alert"` |
| Icon-only buttons | `aria-label="…"` on the button |
| Hidden decorative text | `aria-hidden="true"` |
| Screen-reader-only text | `.sr-only` Tailwind class |
| Error messages | `role="alert"` + `aria-describedby` on the field |

### Screen Reader Text

Use `<span class="sr-only">…</span>` for text that should be read but not displayed:

```html
<button class="modal-close" aria-label="{{ 'modal.close'|trans }}">
    <span aria-hidden="true">✕</span>
    <span class="sr-only">{{ 'modal.close'|trans }}</span>
</button>
```

---

## 7. Naming Conventions

### CSS Classes — BEM-inspired

```
block            .btn
block--modifier  .btn--sm  .btn--lg
block-element    .form-label  .form-error
block-variant    .btn-primary  .alert-success
```

| Pattern | Example | Purpose |
|---|---|---|
| Atom base | `.btn`, `.alert`, `.form-input` | Base styles |
| Variant | `.btn-primary`, `.alert-error` | Visual variant |
| Size modifier | `.btn--sm`, `.btn--lg` | Compact/expanded |
| State modifier | `.form-input--error` | Validation / interactive state |

### Rules

- Use **lowercase-with-dashes** for all class names.
- Never use `id` attributes for styling — only for accessibility (`aria-*`, `for`/`id` pairing) and JS hooks.
- Prefix JS-only hooks with `js-` (e.g., `js-toggle-nav`) so they are never accidentally styled.
- Do not use Tailwind utility classes directly in Twig templates for component-level styling — always wrap in a semantic component class.

---

## 8. Do & Don't

### ✅ Do

- Use design tokens for every colour, radius, and transition.
- Use the two-class button pattern: `.btn .btn-primary`.
- Always pair `<label>` with its `<input>` via `for`/`id`.
- Add `role="alert"` to dynamically injected messages.
- Use `.sr-only` for icon-only buttons and decorative labels.
- Keep component SCSS files thin — one component per file.
- Use `var(--transition-fast)` for hover state changes.
- Add `autocomplete` attributes to login and registration inputs.
- Use translation filters or keys (e.g., `{{ 'key'|trans }}` in Twig, translation helpers in JS) for all user-facing text to support localization.

### ❌ Don't

- Hard-code hex colours in Twig templates or component SCSS.
- Use inline `style="…"` attributes (except for truly dynamic values like chart colours).
- Create a new class that duplicates an existing atom's styles.
- Remove `:focus-visible` styles for "aesthetic" reasons.
- Use `&times;` or plain `×` as close buttons.
- Use `<a href="#">` for actions that don't navigate — use `<button>` instead.
- Skip `aria-label` on icon-only interactive elements.
- Nest `<button>` inside `<button>` or `<a>` inside `<a>`.
- Rely on colour alone to convey status (error, warning, success).
- Hardcode user-facing text in templates, controllers, or JavaScript.

---

## 9. Stimulus Controller Guidelines

Stimulus controllers add interactive behaviour to server-rendered HTML. To keep Javascript lightweight, predictable, and compliant with UI guidelines, follow these standards:

### 9.1 Structure & Lifecycle

- **State Definitions**: Define all targets and values at the top of the class using `static targets` and `static values`.
- **Lifecycle Methods**:
  - `connect()`: Initialize DOM listeners, bind events, or setup timers.
  - `disconnect()`: Clean up timers, global event listeners, and prevent memory leaks.
- **Methods**: Keep action methods focused on single user interactions (e.g. `save(e)`, `toggle(e)`).

```javascript
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'output'];
    static values = { threshold: Number };

    connect() {
        // Setup listeners if needed
    }

    disconnect() {
        // Clean up
    }
}
```

### 9.2 Naming Conventions

- **File Names**: Use `snake_case` (e.g. `auth_modal_controller.js`, `formation_controller.js`).
- **HTML Identifiers**:
  - Controller name: `kebab-case` (e.g. `data-controller="auth-modal"`).
  - Actions: `event->controller-name#method` (e.g. `data-action="click->auth-modal#close"`).
  - Targets: `data-controller-name-target="targetName"` (e.g. `data-auth-modal-target="dialog"`).
  - Values: `data-controller-name-value-name-value="value"` (e.g. `data-auth-modal-team-id-value="42"`).

### 9.3 State Management & UI Toggles

- **CSS Classes over Inline Styles**: Always toggle semantic CSS classes (e.g. `.hidden`, `.active`, `.form-input--error`) rather than setting inline `style="..."` properties or raw Tailwind utility classes dynamically.
- **Shared Utilities**:
  - For alerts/notifications, use the shared alert helper:
    ```javascript
    import { showAlert, hideAlert } from '../utils/alert.js';
    ```
  - Element targets must already carry the `.alert` base class in the Twig template. Only the type modifier (`alert-success`, `alert-error`) and `hidden` classes are toggled by the utility.

### 9.4 API Communications

- **Async/Await**: Use standard modern `async/await` pattern for `fetch()` operations.
- **CSRF Token**: Always include the CSRF token in write requests (POST, PUT, DELETE). Extract the token from the document head:
  ```javascript
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
  ```
- **UX States**:
  - Disable buttons during active requests and show feedback (e.g. `Saving...`, `Processing...`).
  - Re-enable and restore text in `finally` blocks or error handlers.

### 9.5 Accessibility & Focus

- **Focus Recovery**: When hiding overlays (modals, dropdowns), restore focus back to the triggering element to maintain correct keyboard flow:
  ```javascript
  this._returnFocusTo = document.activeElement; // on open
  this._returnFocusTo?.focus(); // on close
  ```
- **Initial Focus**: Automatically focus the first interactive element inside a modal upon opening.
- **Key handlers**: Bind the Escape key to close overlays. Modal history for mobile back is managed centrally in `assets/utils/modal_history.js` — do not reimplement per controller.
- **User preferences**: Interface toggles that affect modal behaviour must be stored in `auth_user_settings` and exposed to JS via layout data attributes or the preferences utility — not hardcoded defaults in controllers.

### 9.6 Strict Rules & Constraints

- **No Hardcoded Tailwind**: Never add or remove lists of raw Tailwind color or padding classes (e.g. `bg-green-950/40 text-green-300`) in Javascript. Use semantic custom CSS properties and components.
- **No Dynamic HTML Strings with Utilities**: Never construct HTML elements in JS using raw style/class names. If dynamic markup is needed, use `<template>` elements or read configuration options via `data-` values.
- **No Logic Duplication**: If a method is identical across multiple controllers, extract it to a shared utility under `assets/utils/`.
- **No Hardcoded User-Facing Text**: Never embed raw user-facing strings directly in Javascript code. Use translation mechanisms or pass localized strings via `data-` attributes/values from Twig templates.
