# Fantager — Project Overview

## Short description

Fantager is a server-backed arena-management game built with Symfony and a Webpack Encore frontend.

## Development Quickstart

Use the container-first development flow to ensure consistent local environments.

```bash
# build and start local stack
docker compose up --build -d

# install PHP deps (inside web container)
docker exec -it fantager-web composer install

# install frontend deps and build
docker exec -it fantager-web npm ci
docker exec -it fantager-web npm run build

# run DB migrations
docker exec -it fantager-web composer migrate
# or
docker exec -it fantager-web php bin/console doctrine:migrations:migrate
```

Or run the equivalent commands locally if you prefer not to use containers.

## Database Seeding & Initialization

After running the migrations, bootstrap your local development database with a default kingdom and then create test players:

```bash
# Initialize default kingdom (NPCs, heroes, league standings)
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test

# Mid-season dev setup (~1 month of simulated game time)
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test --start-offset-days=-21

# Create the 3 default test users, each assigned to an NPC team
docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" --default
```

The `--default` option creates three verified users, each assigned to an NPC team:

| Email | Nickname | Password |
|-------|----------|----------|
| player1@example.com | Test Player 1 | password |
| player2@example.com | Test Player 2 | password |
| player3@example.com | Test Player 3 | password |

To create an additional test user manually:

```bash
docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" user@example.com "Player Nickname" "password"
```

## Running Server Ticks & Background Workers

To simulate game progression, the application uses a tick-based calendar system and asynchronous message queues.

### 1. Processing Server Ticks

To check and execute scheduled game events (such as daily resets, weekly training, and league match simulation), run the tick runner command:

```bash
# Check and schedule pending ticks for all active kingdoms
docker exec -u apache fantager-web php bin/console app:ticks:run
```

You can also simulate running ticks at a specific date and time or limit execution to a single kingdom:

```bash
# Simulate running ticks at a specific time in the future or past
docker exec -u apache fantager-web php bin/console app:ticks:run --time="2026-06-27 18:00:00"

# Run ticks only for a specific kingdom ID
docker exec -u apache fantager-web php bin/console app:ticks:run --kingdom-id=1
```

### 2. Running Messenger Consumers (Background Workers)

When `app:ticks:run` schedules ticks, it dispatches them to Symfony Messenger to be processed asynchronously. To consume and process these tasks, you must run the consumer workers:

```bash
# Run workers to consume all queues manually (ideal for local development)
docker exec -u apache fantager-web php bin/console messenger:consume async_high async_medium async_low
```

This command will block your terminal and process tasks as they arrive. To limit how long they run or how many messages they process (similar to the production cron worker):

```bash
# Consume messages with message, time, and memory limits
docker exec -u apache fantager-web php bin/console messenger:consume async_high async_medium async_low --limit=200 --time-limit=270 --memory-limit=128M
```

## Where to look

- **AI assistants:** [AGENTS.md](AGENTS.md) — task routing, UI/backend cheatsheets, Cursor rules
- Documentation Index: [docs/README.md](docs/README.md)
- UI Guidelines: [docs/ui-guidelines.md](docs/ui-guidelines.md) · Cheatsheet: [docs/ui-agent-cheatsheet.md](docs/ui-agent-cheatsheet.md)
- Architecture Specification: [docs/arch-spec.md](docs/arch-spec.md)
- API Design Guide: [docs/api-design.md](docs/api-design.md)

```bash
# Verify Twig templates follow UI guidelines (also runs in CI)
bash scripts/check-ui-compliance.sh

# Verify routes and entities are documented (also runs in CI)
bash scripts/check-backend-docs.sh
```

## Notes

- Prefer running developer tools inside the `fantager-web` container to avoid local version mismatches.
- See `docker/` and `docker-compose.yml` for container configuration.
- To execute any console tasks, make sure to prefix commands with `php` (e.g. `php bin/console <command>`).
