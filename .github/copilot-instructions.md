# Stack and Docker

Agent rules, task routing, locks, and UI/backend checklists live in **[AGENTS.md](../AGENTS.md)**. Local setup: [docs/guides/local-setup.md](../docs/guides/local-setup.md). Docs index: [docs/README.md](../docs/README.md).

## Stack

- **PHP** 8.5 (Symfony 8.1)
- **MariaDB** 11.4
- **PHP runtime** Alpine 3.24
- **Node** >= 24 (Webpack Encore, Tailwind CSS)

## Execution

When the user asks you to run console commands, tests, or builds, run them exclusively inside the `fantager-web` Docker container as user `apache` (prefix Symfony console commands with `php`, e.g. `php bin/console`):

```bash
docker exec -u apache fantager-web <command>
```

Do **not** run tests, linters, builds, or verification commands unless the user explicitly asks. See [AGENTS.md](../AGENTS.md) § No automatic commands.

## Command cheat sheet

| Task | Command |
|------|---------|
| Bootstrap local world | `docker exec -u apache fantager-web php bin/console app:dev:bootstrap` |
| Initialize kingdom | `docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test` |
| List kingdoms | `docker exec -u apache fantager-web php bin/console app:kingdom:list` |
| Tick status / retry | `docker exec -u apache fantager-web php bin/console app:ticks:status` / `app:ticks:retry --kingdom-id=1` |
| Create extra test user | `docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" user@example.com "Nickname" "password"` |
| Build season reports | `docker exec -u apache fantager-web php bin/console app:season:report:build --kingdom-id=1` |
| Export season reports | `docker exec -u apache fantager-web php bin/console app:season:report:export --kingdom-id=1 -o /tmp/seasons.jsonl` |
| Multi-season NPC/balance sim | `bash scripts/simulate-kingdom.sh --init --seasons=8 -o /tmp/seasons.jsonl` |
| Clear cache | `docker exec -u apache fantager-web php bin/console cache:clear` |
| PHPStan | `docker exec -u apache fantager-web vendor/bin/phpstan` |
| Style check / fix | `docker exec -u apache fantager-web composer php-cs` / `composer php-cs-fix` |
| Empty DB schema | `docker exec -u apache fantager-web composer init-db` |
| Migrations | `docker exec -u apache fantager-web composer migrate` |
| Build assets | `docker exec -u apache fantager-web npm run build` |
