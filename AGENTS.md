# Fantager — Agent Instructions

Entry point for Cursor agents, Copilot, and other AI assistants working in this repository.

## Task routing

| Task | Read first | Code / docs to touch |
|------|------------|----------------------|
| **UI / Twig / SCSS / Stimulus** | [docs/ui-agent-cheatsheet.md](docs/ui-agent-cheatsheet.md) | `templates/`, `assets/styles/`, `assets/controllers/` |
| **New or changed API endpoint** | [docs/backend-agent-cheatsheet.md](docs/backend-agent-cheatsheet.md) → [docs/api-design.md](docs/api-design.md) | `src/Controller/Api/V1/`, `src/Service/`, [docs/route-map.md](docs/route-map.md) |
| **Web page / screen** | [docs/screen-code-map.md](docs/screen-code-map.md) + screen in [docs/screens/](docs/screens/) + [docs/ui-agent-cheatsheet.md](docs/ui-agent-cheatsheet.md) | `src/Controller/Web/`, `templates/`, often API + Stimulus |
| **Domain logic / service** | [docs/systems/](docs/systems/) + [docs/entity-reference.md](docs/entity-reference.md) | `src/Service/`, `src/Entity/`, `tests/Service/` |
| **Schema / migration** | [docs/entity-reference.md](docs/entity-reference.md) | `migrations/`, entities, repositories |
| **Bugfix / extend existing feature** | [docs/README.md](docs/README.md) § Current Implementation Status + [docs/known-issues.md](docs/known-issues.md) | Mirror patterns in the same domain |
| **Stack, Docker, doc index** | [.github/copilot-instructions.md](.github/copilot-instructions.md) | — |

Full vertical slice: backend cheatsheet § Vertical slice + UI cheatsheet when the feature has a UI.

## Backend (PHP / Symfony)

| Resource | When to use |
|----------|-------------|
| [docs/screen-code-map.md](docs/screen-code-map.md) | **Read first** for screen → controller/template/API map |
| [docs/backend-agent-cheatsheet.md](docs/backend-agent-cheatsheet.md) | PHP/Symfony layering, checklist, scope guardrails |
| [docs/api-design.md](docs/api-design.md) | REST shape, status codes, response format |
| [docs/entity-reference.md](docs/entity-reference.md) | Entities, enums, naming conventions |
| [docs/route-map.md](docs/route-map.md) | All Web and API routes |
| [.cursor/rules/php-backend.mdc](.cursor/rules/php-backend.mdc) | Auto-applied when editing `src/**/*.php`, `tests/**/*.php` |
| [.cursor/skills/fantager-backend/SKILL.md](.cursor/skills/fantager-backend/SKILL.md) | Full backend implementation workflow |

## Frontend UI (mandatory for templates & styles)

| Resource | When to use |
|----------|-------------|
| [docs/ui-guidelines.md](docs/ui-guidelines.md) | Authoritative UI/CSS spec |
| [docs/ui-agent-cheatsheet.md](docs/ui-agent-cheatsheet.md) | **Read first** for Twig/SCSS work |
| [.cursor/rules/twig-ui.mdc](.cursor/rules/twig-ui.mdc) | Auto-applied when editing `templates/**/*.twig` |
| [.cursor/rules/scss-ui.mdc](.cursor/rules/scss-ui.mdc) | Auto-applied when editing `assets/styles/**/*.scss` |
| [.cursor/rules/stimulus-ui.mdc](.cursor/rules/stimulus-ui.mdc) | Auto-applied when editing Stimulus JS |
| [.cursor/skills/fantager-ui/SKILL.md](.cursor/skills/fantager-ui/SKILL.md) | Full UI implementation workflow |

## No automatic commands

Do **not** run tests, linters, static analyzers, build scripts, Docker console commands for verification, or `bash scripts/check-ui-compliance.sh` unless the user explicitly asks. Follow the docs and review your edits — do not execute verification commands to “finish” a task.

### Optional verification (user-requested only)

```bash
bash scripts/check-ui-compliance.sh
npm run build
bash scripts/check-backend-docs.sh
docker exec -u apache fantager-web composer test
docker exec -u apache fantager-web composer phpstan
```

## Core UI rules (summary)

1. **Twig:** semantic classes only — no Tailwind layout/color/spacing utilities (`hidden`, `sr-only`, `group` are exceptions).
2. **SCSS:** design tokens from `_tokens.scss`; new styles in `assets/styles/components/_*.scss`.
3. **i18n:** `{{ 'key'|trans }}` everywhere; no hardcoded user-facing strings.
4. **a11y:** labels, `aria-label` on icon buttons, modal ARIA, `role="alert"` for dynamic messages.
5. **Stimulus:** translations via `data-*-value`; dynamic HTML from `<template>` in Twig.

## English

Documentation and code comments are in English (see copilot-instructions).
