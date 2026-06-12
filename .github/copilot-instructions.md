# GitHub Copilot / AI Agent Instructions

## Quick Start (What to read first)
- **All docs index**: [docs/README.md](../docs/README.md)
- **Key design**: [arch-spec.md](../docs/arch-spec.md), [api-design.md](../docs/api-design.md), [entity-reference.md](../docs/entity-reference.md)
- **Domain**: [game-summary.md](../docs/game-summary.md), [calendar-system.md](../docs/systems/calendar-system.md)
- **Known gaps**: [known-issues.md](../docs/known-issues.md)
- **Roadmap**: [roadmap.md](../docs/roadmap.md) — implementation phases, priorities, and open blockers
- **Deferred (do not use actively)**: [`_deferred/`](../_deferred/) — contains CHANGELOG.md, CONTRIBUTING.md, and legacy-migration.md; parked until the project reaches a stable version

## Repo Facts & Environment
- **Stack**: PHP 8.5 (Symfony 7.4), MariaDB 11.4, Node >=24 (Webpack Encore, Tailwind CSS).
- **Execution**: Run all tests, console commands, database migrations exclusively inside the `fantager-web` Docker container as user `apache` (prefix Symfony console commands with `php`, e.g. `php bin/console`):
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
- **Incremental Changes**: Make small, reversible changes. Do not change DB/backup/CI infrastructure without consulting maintainers.
- **No automatic testing or verification**: Do NOT run PHPUnit, Playwright, PHPStan, PHP-CS-Fixer, php-cs, or any other tests, linters, static analyzers, or code validation/verification commands automatically. Run these commands ONLY when explicitly requested by the user.
- **Strict Frontend Adherence**: When modifying or building any frontend code, templates, or styles, the AI Agent **MUST** strictly adhere to [docs/ui-guidelines.md](../docs/ui-guidelines.md).
  - **No inline Tailwind/utility classes in Twig for component-level styling**: Always define semantic BEM-inspired CSS classes (e.g. `.block-name`, `.block-name__element`) and implement their styles inside dedicated component SCSS files under `assets/styles/components/` using `@apply` or custom properties.
  - **Granular Twig Componentization**: Avoid bloated page templates. Decompose layouts into modular components under `templates/components/` (e.g. separate widgets, cards, banners) and include them using `{% include ... %}`.
  - **Zero Hardcoded User-Facing Text**: Always use Twig translation filters `|trans` and register corresponding translation keys in YAML files. Do not put raw strings in Twig, controllers, or JavaScript.
  - **Strict Accessibility (A11y)**: Do not skip `aria-label` and `.sr-only` screen-reader helper labels on icon-only interactive elements. Maintain correct focus rings.

