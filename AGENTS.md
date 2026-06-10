# AGENTS.md — AI Agent Quick Guide & Commands

See [.github/copilot-instructions.md](.github/copilot-instructions.md) for full behavior rules and checklists.

## Quick Facts
- **Stack**: PHP 8.5 (Symfony 7.4), MariaDB 11.4, Node >=24 (Webpack Encore, Tailwind CSS).
- **Key Docs**: [docs/README.md](docs/README.md) (Index), [arch-spec.md](docs/arch-spec.md), [api-design.md](docs/api-design.md), [entity-reference.md](docs/entity-reference.md), [legacy-migration.md](docs/legacy-migration.md).

## Local Executions (CRITICAL)
Always run commands inside the `fantager-web` Docker container as user `apache`:
`docker exec -u apache fantager-web <command>`

### Cheat Sheet
- **Initialize Kingdom**: `docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom"`
- **Create Test User**: `docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" user@example.com "Nickname" "password"`
- **Clear Cache**: `docker exec -u apache fantager-web php bin/console cache:clear`
- **Run PHPUnit**: `docker exec -u apache fantager-web vendor/bin/phpunit`
- **Run PHPStan**: `docker exec -u apache fantager-web vendor/bin/phpstan`
- **Run Migrations**: `docker exec -u apache fantager-web php bin/console doctrine:migrations:migrate`
- **Build Assets**: `npm run dev` (run on host or in Node container)

## File Changes
If creating, moving, or deleting files, you MUST update indices in `docs/README.md`, `.github/copilot-instructions.md`, and `AGENTS.md`.


