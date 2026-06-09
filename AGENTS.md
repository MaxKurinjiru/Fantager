# AGENTS.md — AI agent customization

Purpose
- Give AI coding agents concise, actionable guidance to work productively in this repository.

Quick facts
- Main tech (see [arch-spec](docs/arch-spec.md) and [api-design](docs/api-design.md)): PHP 8.5, Symfony 7.4 / 8.0, MariaDB 11.4. Frontend: Node >=24, Webpack Encore, Tailwind CSS (tailwind.config.cjs).
- Key docs: [arch-spec.md](docs/arch-spec.md), [api-design.md](docs/api-design.md), [entity-reference.md](docs/entity-reference.md), [game-summary.md](docs/game-summary.md), [screens-overview.md](docs/screens-overview.md), [roadmap.md](docs/roadmap.md), [calendar-system.md](docs/systems/calendar-system.md), [ui-guidelines.md](docs/ui-guidelines.md).

What agents should do first
- Read the above key docs for domain context and architecture.
- Look for `composer.json` and typical Symfony files (`bin/console`, `config/`, `src/`) before making changes.
- Avoid embedding large docs; link to them instead.

Local setup
- See [README.md](README.md) for the canonical development setup (container-first and local alternatives).
- **CRITICAL**: All tests, console commands, database migrations, and developer script executions must be run exclusively inside the `fantager-web` Docker container.
- **IMPORTANT**: To avoid permission issues in `var/cache`, always run commands as the web user:
  - Format: `docker exec -u apache fantager-web <command>`
  - Never run as host user; avoid running as `root` (default `docker exec`) for application-level commands.
- **TEST OPTIMIZATION**: Do NOT run the full backend PHPUnit test suite if changes are purely to frontend templates (Twig), styling (CSS/assets), or translations (YAML). Only run PHPUnit tests when PHP code (classes, controllers, entities, services, commands, etc.) is modified. For frontend-only changes, verify template compilation or asset build success instead.

What to include in future customization files
- Exact build/test commands from `composer.json` or CI config.
- Environment setup (secrets, DB credentials) and local dev conveniences (Docker compose, symfony CLI usage).
- Runbooks or scripts for migrations and backups.

Where to add more
- See `.github/copilot-instructions.md` for full agent behavior rules and the file-change checklist.
- See `docs/known-issues.md` for current documentation gaps and a short issues list.
- When adding broader contributor docs, ensure `CONTRIBUTING.md` and `CHANGELOG.md` are updated and referenced in `docs/README.md`.

Contact
- If something is unclear, ask the maintainers before making infra changes.

---
Generated to help AI agents quickly find context and follow repository conventions.

When files or directories are created, moved, renamed, or deleted
- **REQUIRED**: Follow the full checklist in [`.github/copilot-instructions.md`](.github/copilot-instructions.md#when-files-or-directories-are-created-moved-renamed-or-deleted).
- Summary: update `docs/README.md`, `.github/copilot-instructions.md`, and `AGENTS.md` references, then search repo-wide for stale links.
