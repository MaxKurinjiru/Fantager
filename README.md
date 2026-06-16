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

After running the migrations, bootstrap your local development database with a default kingdom and test players:

```bash
# Initialize default kingdom (NPCs, heroes, league standings) and 3 test users
docker exec -u apache fantager-web php bin/console app:kingdom:initialize "Main Kingdom" --test
```

The `--test` flag also creates three verified users, each assigned to an NPC team:

| Email | Nickname | Password |
|-------|----------|----------|
| player1@example.com | Test Player 1 | password |
| player2@example.com | Test Player 2 | password |
| player3@example.com | Test Player 3 | password |

To create an additional test user manually:

```bash
docker exec -u apache fantager-web php bin/console app:user:create-test "Main Kingdom" user@example.com "Player Nickname" "password"
```

## Where to look

- Documentation Index: [docs/README.md](docs/README.md)
- Architecture Specification: [docs/arch-spec.md](docs/arch-spec.md)
- API Design Guide: [docs/api-design.md](docs/api-design.md)

## Notes

- Prefer running developer tools inside the `fantager-web` container to avoid local version mismatches.
- See `docker/` and `docker-compose.yml` for container configuration.
- To execute any console tasks, make sure to prefix commands with `php` (e.g. `php bin/console <command>`).
