# UI & CSS Design Guidelines

This document outlines frontend guidelines and standards to ensure design cohesion, premium aesthetics, and accessibility across all views.

## Modals & Close Buttons

All custom dialog boxes and modal windows must adhere to the design tokens and class definitions in [_modal.scss](file:///d:/wamp64/www/Fantager/assets/styles/components/_modal.scss).

### 1. Structure
Every modal should use the standard overlay and box elements:
```html
<div class="modal-overlay" data-controller="modal">
    <div class="modal-box" data-modal-target="dialog" role="dialog" aria-modal="true">
        <!-- Close Button goes here -->
        <!-- Content goes here -->
    </div>
</div>
```

### 2. Standard Close Button
Close buttons must use the `.modal-close` class. Do **not** use raw characters like `&times;` or unstyled close tags. 

Use the exact HTML snippet below:
```html
<button type="button"
        data-action="click->modal#close"
        class="modal-close"
        aria-label="{{ 'modal.close'|trans }}">
    <span aria-hidden="true" class="text-xl leading-none">✕</span>
    <span class="sr-only">{{ 'modal.close'|trans }}</span>
</button>
```

#### Rationale:
- **Hover & Active States**: `.modal-close` provides transitions for color shifts (`var(--color-success-text)`), background opacity, and scaling effects.
- **Accessibility**: Includes `aria-label` translations and `sr-only` screen-reader fallbacks.
- **Visual Consistency**: Fits dark mode layout palettes.
