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
docker exec -it fantager-web php bin/console doctrine:migrations:migrate

# run tests
docker exec -it fantager-web composer test
```

Or run the equivalent commands locally if you prefer not to use containers.

## Database Seeding & Initialization

After running the migrations, bootstrap your local development database with a default kingdom and a test player:

```bash
# 1. Initialize default kingdom (NPCs, heroes, league standings)
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom"

# 2. Create and activate a test user, and assign them an NPC team in the kingdom
docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" user@example.com "Player Nickname" "password"
```

## Where to look

- Documentation Index: [docs/README.md](docs/README.md)
- Architecture Specification: [docs/arch-spec.md](docs/arch-spec.md)
- API Design Guide: [docs/api-design.md](docs/api-design.md)
- Legacy Database Migration: [docs/legacy-migration.md](docs/legacy-migration.md)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)

## Notes

- Prefer running developer tools inside the `fantager-web` container to avoid local version mismatches.
- See `docker/` and `docker-compose.yml` for container configuration.
- To execute any console tasks, make sure to prefix commands with `php` (e.g. `php bin/console <command>`).
