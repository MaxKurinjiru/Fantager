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
# One-shot local world (~1 month in, default test users, catch-up ticks)
docker exec -u apache fantager-web php bin/console app:dev:bootstrap

# Initialize default kingdom (NPCs, heroes, league standings). --test also ensures player1–3@example.com
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test

# Mid-season dev setup (~1 month of simulated game time)
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test --start-offset-days=-21 --catch-up-ticks
```

`--test` / `app:dev:bootstrap` ensure three verified users:

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
# List kingdoms
docker exec -u apache fantager-web php bin/console app:kingdom:list

# Check and schedule pending ticks for all active kingdoms
docker exec -u apache fantager-web php bin/console app:ticks:run

# Preview what would be scheduled (no persist / no dispatch)
docker exec -u apache fantager-web php bin/console app:ticks:run --dry-run --kingdom-id=1

# Local catch-up without Messenger
docker exec -u apache fantager-web php bin/console app:ticks:run --sync --kingdom-id=1

# Simulate running ticks at a specific time
docker exec -u apache fantager-web php bin/console app:ticks:run --time="2026-06-27 18:00:00"

# Pipeline snapshot (exit 1 if blocked or failed)
docker exec -u apache fantager-web php bin/console app:ticks:status --kingdom-id=1

# After fixing a failed tick
docker exec -u apache fantager-web php bin/console app:ticks:retry --kingdom-id=1
```

Season reports are written on `season_transition`. Backfill and export (JSONL + health alerts):

```bash
docker exec -u apache fantager-web php bin/console app:season:report:build --kingdom-id=1
docker exec -u apache fantager-web php bin/console app:season:report:export --kingdom-id=1 -o /tmp/seasons.jsonl
```

## Multi-season balance / NPC simulation

Use the live tick pipeline and Messenger combat waves (not `POST /api/v1/combat/simulate`). **Do not run 8 seasons in one PHP process** — combat batches will hit the process time/memory limit. The operator loop is a shell script: init once, then **advance one step** and **process bounded Messenger batches until idle**.

From the host (recommended):

```bash
# New NPC-only world, 8 seasons, JSONL + health alerts
bash scripts/simulate-kingdom.sh --init --name="Balance Sim" --pace=classic --seasons=8 -o /tmp/seasons.jsonl

# Preview
bash scripts/simulate-kingdom.sh --init --seasons=8 --dry-run

# Continue an existing kingdom
bash scripts/simulate-kingdom.sh --kingdom-id=1 --seasons=8 -o /tmp/seasons.jsonl
```

Each PHP invocation is short-lived:

| Command | Role | Exit |
|---------|------|------|
| `app:kingdom:simulate:init` | Create NPC kingdom (prints `kingdom_id=`) | 0 |
| `app:kingdom:simulate:advance --kingdom-id= --step=week` | Schedule **one** week (or `--step=season` = 11 weeks). Does not consume the queue. | 0 ok, 2 pipeline still busy, 1 failed |
| `app:kingdom:simulate:process --kingdom-id= --limit=200 --time-limit=45` | One Messenger batch (or orchestrator resume) | 0 idle, 2 still busy (call again), 1 failed |
| `app:kingdom:simulate:status --kingdom-id= --json` | Snapshot including `completed_seasons` | 0 idle, 2 busy, 1 failed |

Manual loop inside `fantager-web`:

```bash
php bin/console app:kingdom:simulate:init --name="Balance Sim" --json
# kingdom_id from JSON

php bin/console app:kingdom:simulate:advance --kingdom-id=1 --step=week
php bin/console app:kingdom:simulate:process --kingdom-id=1 --limit=200 --time-limit=45
# repeat process until exit 0, then advance again, until status completed_seasons >= N

php bin/console app:season:report:export --kingdom-id=1 -o /tmp/seasons.jsonl
```

`--with-test-users` on init claims three NPC teams for `player1–3@example.com`. If a batch fails: `app:ticks:status`, `app:kingdom:diagnose`, `app:ticks:retry --kingdom-id=`, then `simulate:process` again.

Bands: `config/game/season-health.yaml`. Spec: [season-report-system.md](../systems/season-report-system.md).

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
