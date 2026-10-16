# Fantager — Agent Instructions

Entry point for Cursor agents, Copilot, and other AI assistants working in this repository.

## Task routing

| Task | Read first | Code / docs to touch |
|------|------------|----------------------|
| **Docs vs code alignment** | [docs/roadmap/current.md](docs/roadmap/current.md) → [docs/product/known-issues.md](docs/product/known-issues.md) | Waves 0–8 complete — do not invent a next alignment step |
| **UI / Twig / SCSS / Stimulus** | [docs/guides/ui.md](docs/guides/ui.md) | `templates/`, `assets/styles/`, `assets/controllers/` |
| **New or changed API endpoint** | [docs/guides/backend.md](docs/guides/backend.md) → [docs/architecture/api.md](docs/architecture/api.md) | `src/Controller/Api/V1/`, `src/Service/`, [docs/architecture/routes.md](docs/architecture/routes.md) |
| **Web page / screen** | [docs/architecture/screen-code-map.md](docs/architecture/screen-code-map.md) + screen in [docs/screens/](docs/screens/) + [docs/guides/ui.md](docs/guides/ui.md) | `src/Controller/Web/`, `templates/`, often API + Stimulus |
| **Domain logic / service** | [docs/systems/](docs/systems/) + [docs/architecture/entities.md](docs/architecture/entities.md) | `src/Service/`, `src/Entity/`, `tests/Service/` |
| **Schema / migration** | [docs/architecture/entities.md](docs/architecture/entities.md) | `migrations/`, entities, repositories |
| **Bugfix / extend existing feature** | Matching `systems/*.md` Remaining work + [docs/screens/](docs/screens/) | Mirror patterns in the same domain |
| **Domain terms** | [docs/product/glossary.md](docs/product/glossary.md) | — |
| **Stack & Docker** | [.github/copilot-instructions.md](.github/copilot-instructions.md) | — |

Full vertical slice: backend cheatsheet § Vertical slice + UI cheatsheet when the feature has a UI.

**Live rules vs overview:** matching `docs/systems/*.md` wins over [docs/product/overview.md](docs/product/overview.md). Do not invent mechanics for **Open** rows in [docs/product/known-issues.md](docs/product/known-issues.md). Live UI is [docs/architecture/screen-code-map.md](docs/architecture/screen-code-map.md) + Twig.

**Locks** live in the matching `systems/*.md` or `screens/*.md` `## Locked:` section (index: [docs/product/known-issues.md](docs/product/known-issues.md) § Resolved locks). Highest-risk — do not reopen without an explicit product ask:

- **Hero level:** stored `Hero.level` is live truth — do not derive it from `Hero.xp` on read. [hero-system.md](docs/systems/hero-system.md#locked-hero-level-invariant)
- **Chemistry:** `Team.chemistry` is flavor for matches — combat must not read it. [team-system.md](docs/systems/team-system.md#locked-chemistry-flavor)
- **Enchanting:** not in the live game — do not reintroduce essence or an enchant UI. [item-system.md](docs/systems/item-system.md#locked-no-enchanting)
- **Simulate:** do not ship `POST /api/v1/combat/simulate`. [combat-system.md](docs/systems/combat-system.md#locked-no-race-synergy--no-simulate)
- **Wiki / extra settings:** `GET /wiki` and session password change stay **Planned**. [00-public-pages.md](docs/screens/00-public-pages.md#locked-public-wiki-stays-planned), [19-player-profile-settings.md](docs/screens/19-player-profile-settings.md#locked-password-change--extra-settings-stay-planned)

## Backend (PHP / Symfony)

| Resource | When to use |
|----------|-------------|
| [docs/architecture/screen-code-map.md](docs/architecture/screen-code-map.md) | **Read first** for screen → controller/template/API map |
| [docs/guides/backend.md](docs/guides/backend.md) | PHP/Symfony layering, checklist, scope guardrails |
| [docs/architecture/api.md](docs/architecture/api.md) | REST shape, status codes, response format |
| [docs/architecture/entities.md](docs/architecture/entities.md) | Entities, enums, naming conventions |
| [docs/architecture/routes.md](docs/architecture/routes.md) | All Web and API routes |
| [.cursor/rules/php-backend.mdc](.cursor/rules/php-backend.mdc) | Auto-applied when editing `src/**/*.php`, `tests/**/*.php` |
| [.cursor/skills/fantager-backend/SKILL.md](.cursor/skills/fantager-backend/SKILL.md) | Full backend implementation workflow |

## Frontend UI (mandatory for templates & styles)

| Resource | When to use |
|----------|-------------|
| [docs/guides/ui-guidelines.md](docs/guides/ui-guidelines.md) | Authoritative UI/CSS spec |
| [docs/guides/ui.md](docs/guides/ui.md) | **Read first** for Twig/SCSS work |
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

All documentation, markdown files, and code comments must be in English.

## File changes

When creating, deleting, moving, or renaming files/directories:

1. Update [docs/README.md](docs/README.md) (link the new file in the appropriate section).
2. If agent routing or locks changed, update this file.
3. If stack or Docker commands changed, update [.github/copilot-instructions.md](.github/copilot-instructions.md).
4. Search repo-wide for old references and update stale links.

When adding routes, entities, enums, or features also update [docs/architecture/routes.md](docs/architecture/routes.md), [docs/architecture/entities.md](docs/architecture/entities.md), [docs/architecture/screen-code-map.md](docs/architecture/screen-code-map.md), the matching `systems/*.md` Remaining work, and the status snapshot in [docs/systems/README.md](docs/systems/README.md).

Make small, reversible changes. Do not change DB/backup/CI infrastructure without consulting maintainers.

## Strict constraints

1. **No LaTeX syntax**: Never use LaTeX formatting, syntax, or mathematical notations (such as `$$...$$` or `$...$`) under any circumstances. All mathematical expressions and formulas must be written in plain, readable text (e.g., `a + b = c`).
