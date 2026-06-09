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

## Where to look

- Documentation: [docs/README.md](docs/README.md)
- Architecture: [docs/arch-spec.md](docs/arch-spec.md)
- API design: [docs/api-design.md](docs/api-design.md)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)

## Notes

- Prefer running developer tools inside the `fantager-web` container to avoid local version mismatches.
- See `docker/` and `docker-compose.yml` for container configuration.



<!-- data migration connection use -->
service/etc use:

<?php
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

// Přes DBAL (raw SQL - vhodné pro migraci)
public function __construct(
    #[\Symfony\Component\DependencyInjection\Attribute\Target('legacy')]
    private Connection $legacyConnection,
) {}

// Nebo přes entity manager
public function __construct(
    #[\Symfony\Component\DependencyInjection\Attribute\Target('legacy')]
    private EntityManagerInterface $legacyEntityManager,
) {}

or services.yaml:

App\Service\MigrationService:
    arguments:
        $legacyConnection: '@doctrine.dbal.legacy_connection'
