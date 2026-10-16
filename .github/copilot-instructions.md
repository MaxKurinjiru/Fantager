# GitHub Copilot / AI Agent Instructions

## Quick Start (What to read first)
- **Agent entry point**: [AGENTS.md](../AGENTS.md)
- **UI (Twig/SCSS)**: [docs/guides/ui.md](../docs/guides/ui.md) → [docs/guides/ui-guidelines.md](../docs/guides/ui-guidelines.md); see [AGENTS.md](../AGENTS.md) for UI rules
- **Backend (PHP/API)**: [docs/guides/backend.md](../docs/guides/backend.md) → [docs/architecture/api.md](../docs/architecture/api.md); see [AGENTS.md](../AGENTS.md) for task routing
- **All docs index**: [docs/README.md](../docs/README.md)
- **Key design**: [architecture/overview.md](../docs/architecture/overview.md), [architecture/api.md](../docs/architecture/api.md), [architecture/entities.md](../docs/architecture/entities.md)
- **Domain**: [product/overview.md](../docs/product/overview.md), [calendar-system.md](../docs/systems/calendar-system.md)
- **Known gaps**: [product/known-issues.md](../docs/product/known-issues.md) — Open rows (currently none); resolved-lock index
- **Now / next**: [roadmap/current.md](../docs/roadmap/current.md) — alignment waves 0–8 complete; leftovers in `systems/` Remaining work and `future/`
- **Roadmap**: [roadmap/history.md](../docs/roadmap/history.md) — historical Milestones 1–9 (M8/M9 parked). Wave archive: [roadmap/alignment-waves.md](../docs/roadmap/alignment-waves.md)
- **Deferred (do not use actively)**: [`_deferred/`](../_deferred/) — CHANGELOG.md and CONTRIBUTING.md (parked until a stable version); `legacy-migration.md` parked until the one-shot old-game import immediately before the first production deploy (A8.4); original screen sketch `screens-overview-legacy.md`. Design brainstorm: [docs/future/notes.md](../docs/future/notes.md).

## Repo Facts & Environment
- **Stack**: PHP 8.5 (Symfony 7.4), MariaDB 11.4, Node >=24 (Webpack Encore, Tailwind CSS).
- **Execution**: When the user asks you to run console commands, tests, or builds, run them exclusively inside the `fantager-web` Docker container as user `apache` (prefix Symfony console commands with `php`, e.g. `php bin/console`):
  `docker exec -u apache fantager-web <command>`
- **Command Cheat Sheet**:
  - Initialize Kingdom: `docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test`
  - Create Test User: `docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" user@example.com "Nickname" "password"`
  - Clear Cache: `docker exec -u apache fantager-web php bin/console cache:clear`
  - Run PHPStan: `docker exec -u apache fantager-web vendor/bin/phpstan`
  - Run style checks: `docker exec -u apache fantager-web composer php-cs` and `docker exec -u apache fantager-web composer php-cs-fix`
  - Run Migrations: `docker exec -u apache fantager-web php bin/console doctrine:migrations:migrate`
  - Build Assets: `docker exec -u apache fantager-web npm run build`

## Core Rules
- **English only**: All documentation, markdown files, and code comments must be in English.
- **No LaTeX**: Use plain text/inline backticks for formulas in markdown, NOT LaTeX (e.g., no `$$` or `\text`).
- **File Changes Checklist**:
  When creating, deleting, moving, or renaming files/directories:
  1. Update [docs/README.md](../docs/README.md) (link new file in appropriate section).
  2. Update references/indexes in [.github/copilot-instructions.md](copilot-instructions.md).
  3. Search repo-wide for old references and update stale links.
  When adding routes, entities, enums, or features also update [docs/architecture/routes.md](../docs/architecture/routes.md), [docs/architecture/entities.md](../docs/architecture/entities.md), [docs/architecture/screen-code-map.md](../docs/architecture/screen-code-map.md), and the implementation status table in [docs/systems/README.md](../docs/systems/README.md) where applicable.
- **Incremental Changes**: Make small, reversible changes. Do not change DB/backup/CI infrastructure without consulting maintainers.
- **No automatic commands**: Do NOT run PHPUnit, PHPStan, PHP-CS-Fixer, php-cs, `bash scripts/check-ui-compliance.sh`, `bash scripts/check-backend-docs.sh`, `npm run build`, or any other test, linter, static analyzer, build, or validation command unless the user explicitly requests it. Same rule for Docker commands used only to verify work (see [AGENTS.md](../AGENTS.md)).
- **Strict Frontend Adherence**: When modifying or building any frontend code, templates, or styles, the AI Agent **MUST** strictly adhere to [docs/guides/ui-guidelines.md](../docs/guides/ui-guidelines.md) and [docs/guides/ui.md](../docs/guides/ui.md). See [fantager-ui skill](../.cursor/skills/fantager-ui/SKILL.md).
- **Strict Backend Adherence**: When modifying PHP, services, or API endpoints, follow [docs/guides/backend.md](../docs/guides/backend.md) and [fantager-backend skill](../.cursor/skills/fantager-backend/SKILL.md). See [AGENTS.md](../AGENTS.md) for task routing.
  - **No inline Tailwind/utility classes in Twig for component-level styling**: Always define semantic BEM-inspired CSS classes (e.g. `.block-name`, `.block-name__element`) and implement their styles inside dedicated component SCSS files under `assets/styles/components/` using `@apply` or custom properties. Exceptions: `hidden`, `sr-only`, `group`.
  - **Granular Twig Componentization**: Avoid bloated page templates. Decompose layouts into modular components under `templates/components/` (e.g. separate widgets, cards, banners) and include them using `{% include ... %}`.
  - **Zero Hardcoded User-Facing Text**: Always use Twig translation filters `|trans` and register corresponding translation keys in YAML files. Do not put raw strings in Twig, controllers, or JavaScript.
  - **Strict Accessibility (A11y)**: Do not skip `aria-label` and `.sr-only` screen-reader helper labels on icon-only interactive elements. Maintain correct focus rings.

