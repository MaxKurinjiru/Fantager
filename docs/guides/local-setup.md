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

The calendar's persisted planning horizon adds `kingdom.tick_planning_horizon`. Calendar events now live in `kingdom_tick_event`, and execution phases in `kingdom_tick_log` reference them through `event_id` and use `kingdom_scope`, `marketplace_batch`, `marketplace_max_listing_id`, `combat_recovery_count`, and `combat_recovery_round`; kingdom, type, and scheduled time are no longer stored on the phase. Optimistic locking also adds `kingdom.version`, `team.version`, `marketplace_listing.version`, and `marketplace_listing.bid_revision`. An existing schema must be updated before running the application; when old data is disposable, recreate the empty database and initialize the schema as above. Recreating the database deletes its contents; migrate existing data separately if it must be retained.

Stop all tick schedulers and Messenger workers before deploying recovery-enabled code and applying its schema changes. Start only workers running the new code afterward: older workers do not hold per-tick advisory locks, so running both versions together can make recovery mistake active work for an interruption. Plan any migration separately for databases whose data must be retained.

### Deploying tick recovery with existing data

1. Prepare and verify a restorable database backup and a staging copy. Identify the exact source schema and queued-message formats; do not generate or apply a schema diff against the live database as a substitute for a reviewed data migration.
2. Stop every tick scheduler and Messenger worker, and prevent old workers from restarting. Drain or preserve the queue as appropriate; do not purge it. Keep the application stopped during any incompatible schema change. Confirm no old worker connections remain.
3. On staging, migrate existing calendar events and phase data as needed, then apply the remaining reviewed schema changes and validate both schema and representative data. A database that already has the current event/phase model and all other current mappings, but lacks only the combat recovery columns, needs `combat_recovery_count INT NOT NULL DEFAULT 0` and nullable `combat_recovery_round INT` on `kingdom_tick_log`. An older schema requires a separate data migration; adding only those columns is insufficient. MariaDB DDL is not transactionally rolled back with application work.
4. Test recovery and queued-message compatibility on staging with only new workers. Agree on a rollback plan that accounts for both the database and application version. Do not mix versions: older workers do not acquire the new per-tick locks.
5. Only after approving the tested migration and backup, apply the same procedure to the target database. Start new workers and the scheduler, inspect `app:ticks:status --json` and the error logs, and confirm the queue and blocked work are progressing. Recovery requires periodic asynchronous `app:ticks:run` execution.

Use the isolated crash integration tests only with a dedicated test database and an empty Messenger queue. Set `FANTAGER_CRASH_TEST_DATABASE` to that database's exact name alongside its test `DATABASE_URL`; never point these tests at a shared or live database. The tests kill their own Messenger workers and commit temporary fixtures before cleanup.

For a reproducible synthetic performance sample, use the same isolated test setup with `FANTAGER_PERFORMANCE_REPORT=1` and `FANTAGER_BENCHMARK_DATABASE` set to the database name, then run `vendor/bin/phpunit tests/Repository/KingdomTickLogRepositoryTest.php --filter testSyntheticTickAndCombatMetrics` inside the container. It reports two settlement batches (26 test listings total) and a bounded combat batch (two synthetic battles), then rolls back the workload. This is a small local sample, not a production benchmark or percentile distribution; compare production logs over a representative workload before tuning limits. The surrounding test fixture creates and removes a temporary kingdom, so never use a shared or live database.

App: http://localhost:10001

## Development logs

Development logs are written to `var/log/dev-YYYY-MM-DD.log`. Monolog starts a new file each day and keeps up to seven dated files, deleting older matching files when rotation occurs. Ordinary Doctrine SQL debug records are excluded from the file; Doctrine records at info level and above are retained. The other development channels keep their existing levels. Restart long-running workers so they load the updated configuration.

This is time-based rotation, not a file-size limit or compression; one day of heavy logging can still fill the disk. Use externally managed size-based rotation and disk monitoring where a strict capacity limit is needed. The existing undated `var/log/dev.log` is not rotated or deleted by this configuration and continues to occupy disk space until an operator handles it. Production logs continue to use stderr and require separate retention in the hosting environment.

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
