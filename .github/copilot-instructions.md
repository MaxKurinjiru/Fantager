# GitHub Copilot / AI Agent Instructions

Purpose
- Give concise, actionable rules so AI agents work productively and safely in this repository.

Quick start (what to read first)
- Read [AGENTS.md](../AGENTS.md) for agent-level guidance.
- Read the docs index: [docs/README.md](../docs/README.md) for a high-level map of available docs.
- Read architecture and design docs: [arch-spec.md](../docs/arch-spec.md), [api-design.md](../docs/api-design.md), [entity-reference.md](../docs/entity-reference.md)
- Read domain docs: [game-summary.md](../docs/game-summary.md), [screens-overview.md](../docs/screens-overview.md), [calendar-system.md](../docs/systems/calendar-system.md).
- Read implementation order: [roadmap.md](../docs/roadmap.md).
- Read styling and layout guidelines: [ui-guidelines.md](../docs/ui-guidelines.md).
- See `docs/known-issues.md` for a short list of current documentation gaps.

Repository facts
- Language & framework: PHP 8.5, Symfony 7.4
- Database: MariaDB 11.4
- Frontend: Node >=24, Webpack Encore (webpack.config.js, assets/ -> public/build)
- Project root contains Symfony-like layout (expect `bin/console`, `config/`, `src/` if present).

Local setup
- See [README.md](../README.md) for the canonical development setup (container-first and local alternatives).
- Frontend config: [tailwind.config.cjs](../tailwind.config.cjs) and [postcss.config.cjs](../postcss.config.cjs)

Agent behavior rules
- **English only**: All documentation, markdown files, and code comments must be in English only.
- **No LaTeX math syntax**: Do not use LaTeX formatting (e.g., `\text`, `\times`, `\frac`, or block `$$` signs) in markdown files. Write all math formulas as plain text inside standard markdown code blocks or inline backticks to match the repository style.
- **Update instruction indices ALWAYS**: When creating new `.md` documentation files, you MUST immediately update: `docs/README.md`, `.github/copilot-instructions.md`, `AGENTS.md`, and also add or reference `CONTRIBUTING.md` and `CHANGELOG.md` when appropriate. Failure to do this violates the agent contract.
- **Update indices on any file/dir change**: When creating, deleting, moving, renaming, or otherwise changing files or directories that affect repository structure or documentation, you MUST update `docs/README.md`, `.github/copilot-instructions.md`, and `AGENTS.md` as appropriate so indexes and instructions remain accurate.
- Link, don't copy: reference existing docs rather than embedding large sections.
- Ask before changing infra: open PR or ask maintainers for DB/backup/CI changes.
- Small, reversible changes: prefer incremental commits and feature branches.
- **Run commands inside container**: All tests, console commands, database migrations, and developer script executions must be run exclusively inside the `fantager-web` Docker container.
- **CRITICAL**: Use the correct user for commands (usually `apache`) to avoid permission issues in `var/cache`.
  - Example: `docker exec -u apache fantager-web bin/console <command>`
- Run linters/tests inside the container when modifying PHP code. If tests fail for unrelated reasons, report and ask.
- **TEST OPTIMIZATION**: Do NOT run the full backend PHPUnit test suite if changes are purely to frontend templates (Twig), styling (CSS/assets), or translations (YAML). Only run PHPUnit tests when PHP code (classes, controllers, entities, services, commands, etc.) is modified. For frontend-only changes, verify template compilation or asset build success instead.
- Add or update documentation/README entries for behavioral or setup changes.

PR guidance
- Include short description, motivation, and testing notes.
- Run `composer cs-check` or equivalent if present; include results.

When uncertain
- Ask maintainers for missing details (DB credentials, CI tokens, deployment procedures) before touching deployment scripts.

Notes
- Keep instructions minimal; add environment-specific setup to CI or README rather than here.

When files or directories are created, moved, renamed, or deleted
- **REQUIRED**: Immediately update these files with all changes:
  1. `docs/README.md` — Add link to new doc in appropriate section (create section if needed)
  2. `.github/copilot-instructions.md` — Update "Quick start" references to include new doc or reflect structural changes
  3. `AGENTS.md` — Update "Quick facts" and "Key docs" to reference new doc or reflect structural changes
  4. Search repo for references to moved/old files and update all links

- Minimal checklist:
  1. Run a repo-wide search for old filenames and update links everywhere
  2. Ensure `docs/README.md` has the new file organized in appropriate section
  3. Ensure both instruction files (`copilot-instructions.md` and `AGENTS.md`) link to new documentation or reflect deletions
  4. Add a brief note in PR describing what was moved/added/deleted and why

- Do NOT commit without updating instructions and indices — future agents depend on this

---
Generated to help Copilot/AI agents follow repository conventions and avoid risky changes.
