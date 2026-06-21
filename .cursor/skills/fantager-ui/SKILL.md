---
name: fantager-ui
description: >-
  Implements and reviews Fantager frontend UI following docs/ui-guidelines.md.
  Use when editing Twig templates, SCSS in assets/styles/components, Stimulus
  controllers, modals, forms, layout, marketplace cards, or when the user asks
  for UI compliance, semantic classes, or Tailwind removal from templates.
---

# Fantager UI

## Read first

1. [docs/ui-agent-cheatsheet.md](../../docs/ui-agent-cheatsheet.md) — decision trees
2. [reference.md](reference.md) — layout & file map (when needed)
3. [examples.md](examples.md) — before/after patterns

Full spec: [docs/ui-guidelines.md](../../docs/ui-guidelines.md)

## Workflow

Copy and track:

```
UI task progress:
- [ ] Searched existing SCSS for reusable class
- [ ] Added/extended semantic class in correct _*.scss (if needed)
- [ ] Updated Twig (no Tailwind utilities except hidden/sr-only/group)
- [ ] Translations + a11y (label/aria-label, modal ARIA)
```

Do not run verification commands unless the user asks (see [AGENTS.md](../../../AGENTS.md)).

## Twig rules (strict)

**Forbidden in `class`:** flex, grid, gap-*, space-*, justify-*, items-*, m*/p* utilities, text-*, font-*, Tailwind colors, overflow-*, shadow-*, sm:/md:/lg: prefixes.

**Allowed:** `hidden`, `sr-only`, `group`, and project semantic classes.

**Order:** SCSS class first → then Twig.

## SCSS rules

- Tokens only: `var(--color-…)`, `color-mix(…)`
- New layout → `_layout.scss`; shared → `_shared.scss`; domain → `_hero.scss`, etc.
- Register new files in `assets/styles/app.scss`

## Stimulus

- No user-facing strings in JS — use `data-*-value` from Twig
- Dynamic UI → `<template>` in Twig, clone in controller

## When adding a layout primitive

1. Add to `_layout.scss` or `_shared.scss`
2. Add one row to `docs/ui-guidelines.md` §3.1
3. Add to `reference.md` in this skill

## Optional verification (user-requested only)

```bash
bash scripts/check-ui-compliance.sh
npm run build
```

## Additional resources

- [reference.md](reference.md) — class catalog & SCSS file map
- [examples.md](examples.md) — real before/after examples
