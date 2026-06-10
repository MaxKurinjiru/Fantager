# GitHub Copilot / AI Agent Instructions

## Quick Start (What to read first)
- **All docs index**: [docs/README.md](../docs/README.md)
- **Key design**: [arch-spec.md](../docs/arch-spec.md), [api-design.md](../docs/api-design.md), [entity-reference.md](../docs/entity-reference.md), [legacy-migration.md](../docs/legacy-migration.md)
- **Domain**: [game-summary.md](../docs/game-summary.md), [calendar-system.md](../docs/systems/calendar-system.md)
- **Known gaps**: `docs/known-issues.md`

## Repo Facts & Environment
- **Stack**: PHP 8.5 (Symfony 7.4), MariaDB 11.4, Node >=24 (Webpack Encore, Tailwind CSS).
- **Execution**: Run all tests, console commands, database migrations exclusively inside the `fantager-web` Docker container as user `apache` (prefix Symfony console commands with `php`, e.g. `php bin/console`):
  `docker exec -u apache fantager-web <command>`
- **Test Optimization**: Do NOT run the backend PHPUnit tests if changes are purely to frontend/templates (Twig), styling (CSS/assets), or translations (YAML). Verify asset builds or template compilation instead.

## Core Rules
- **English only**: All documentation, markdown files, and code comments must be in English.
- **No LaTeX**: Use plain text/inline backticks for formulas in markdown, NOT LaTeX (e.g., no `$$` or `\text`).
- **File Changes Checklist**:
  When creating, deleting, moving, or renaming files/directories:
  1. Update [docs/README.md](../docs/README.md) (link new file in appropriate section).
  2. Update references/indexes in [.github/copilot-instructions.md](copilot-instructions.md) and [AGENTS.md](../AGENTS.md).
  3. Search repo-wide for old references and update stale links.
- **Incremental Changes**: Make small, reversible changes. Do not change DB/backup/CI infrastructure without consulting maintainers.

