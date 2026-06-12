# Contributing

Thank you for contributing to Fantager. This guide explains the common workflows and rules to follow when making changes.

Quick start
- Fork and create a feature branch from `main` (or `develop` if used): `feature/your-name/brief-description`.
- Keep commits small and focused. Use descriptive commit messages and reference issue IDs where applicable.

**Docker & Permissions**
- If using the Docker setup, execute all commands inside the `fantager-web` container.
- **IMPORTANT**: Use `-u apache` for all commands that interact with the application (console, tests, etc.) to avoid permission issues in `var/cache` and `var/log`.
- Examples:
  - `docker exec -u apache fantager-web composer cache-clear`
  - `docker exec -u apache fantager-web composer install`

Dependencies, build and data (inside Docker container if used)
- Install PHP deps: `composer install`
- Install frontend deps: `npm ci`
- Build assets: `npm run build`
- Run migrations: `composer migrate`

Tests & quality
- Run PHP unit tests: `composer test`
- Run PHP static analysis: `composer phpstan`
- Run PHP-CS-Fixer / style checks: `composer php-cs` and `composer php-cs-fix`
- Run E2E tests: `npm run test:e2e`

Pull Requests
- Open a PR against `main` (or `develop` per project workflow).
- Include a short description, motivation, and testing notes.
- Ensure CI passes and at least one code review approval is present before merge.

Changelog
- Every PR that changes code or documentation should add one or a few lines under `## [Unreleased]` section (which is a template) as new section labeled `## [<YYYY-MM-DD>] - vX.Y.Z` in CHANGELOG.md`.
- Format: `- <Category>: <Short description> (PR #123)`
- Categories: `Added`, `Changed`, `Fixed`, `Docs`, `Removed`, `Security`
- Keep each entry under ~100 characters; link to the PR/issue for details rather than writing long descriptions.
- Do **not** add entries directly to unrelease sections — `[Unreleased]` is template only.

Documentation rules
- When adding or moving documentation files (.md), update the following indices immediately:
  - `docs/README.md`
  - `.github/copilot-instructions.md`
- Consider adding or updating `CONTRIBUTING.md` and `CHANGELOG.md` when making changes that affect contributors or releases.

Other notes
- Ask maintainers before changing DB backups, CI, or deployment configurations.
- Prefer small, reversible changes and feature branches.

Thanks for contributing — your help keeps the project healthy and discoverable.
