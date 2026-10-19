# Local development setup

Container-first local workflow. Docker image and ports: [docker/README.md](../../docker/README.md). Stack versions: [architecture/overview.md](../architecture/overview.md). Agent command cheat sheet: [.github/copilot-instructions.md](../../.github/copilot-instructions.md).

Run PHP, Composer, npm, and console commands inside `fantager-web`. Prefix Symfony console with `php`.

## Quick start

```bash
docker compose up --build -d

docker exec -it fantager-web composer install

docker exec -it fantager-web npm ci
docker exec -it fantager-web npm run build

# Empty database: create schema from entities + Messenger table
docker exec -it fantager-web composer init-db
```

After entity mapping changes, either drop and recreate the database then `composer init-db`, or add a Version file (`composer migrate-diff`) and `composer migrate`. Historical `Version*` files were removed; the migrations bundle and commands stay.

App: http://localhost:10001

## Database seeding

After schema init, bootstrap a kingdom and test players. Kingdom defaults: [config/kingdom/README.md](../../config/kingdom/README.md).

```bash
# Initialize default kingdom (NPCs, heroes, league standings)
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test

# Mid-season dev setup (~1 month of simulated game time)
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test --start-offset-days=-21

# Create the 3 default test users, each assigned to an NPC team
docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" --default
```

`--default` creates three verified users:

| Email | Nickname | Password |
|-------|----------|----------|
| player1@example.com | Test Player 1 | password |
| player2@example.com | Test Player 2 | password |
| player3@example.com | Test Player 3 | password |

Additional user:

```bash
docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" user@example.com "Player Nickname" "password"
```

## Server ticks and workers

Tick semantics: [calendar-system.md](../systems/calendar-system.md). NPC opponents: [npc-simulation-system.md](../systems/npc-simulation-system.md). Inactive teams: [player-inactivity-system.md](../systems/player-inactivity-system.md).

```bash
# Check and schedule pending ticks for all active kingdoms
docker exec -u apache fantager-web php bin/console app:ticks:run

# Simulate running ticks at a specific time
docker exec -u apache fantager-web php bin/console app:ticks:run --time="2026-06-27 18:00:00"

# One kingdom
docker exec -u apache fantager-web php bin/console app:ticks:run --kingdom-id=1
```

`app:ticks:run` dispatches work to Symfony Messenger. Consume the queue:

```bash
docker exec -u apache fantager-web php bin/console messenger:consume async

# Limits similar to the production cron worker
docker exec -u apache fantager-web php bin/console messenger:consume async --limit=200 --time-limit=270 --memory-limit=128M
```

## Optional verification

Do not run these unless you explicitly want a check (also in CI). Agent rule: [AGENTS.md](../../AGENTS.md) § No automatic commands.

```bash
bash scripts/check-ui-compliance.sh
bash scripts/check-backend-docs.sh
docker exec -u apache fantager-web composer test
docker exec -u apache fantager-web composer phpstan
docker exec -u apache fantager-web composer php-cs
```
