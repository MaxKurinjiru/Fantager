# Legacy Database Migration Guide

This document describes how to access and query the legacy database to perform data migrations to the main Fantager database.

## Database Connections

Two connections are configured in Symfony:
1. `default` — The main Fantager database (MariaDB 11.4).
2. `legacy` — The read-only source database containing legacy game data.

Both connections are defined in `config/packages/doctrine.yaml` and reference environment variables in `.env`.

---

## Accessing the Legacy Connection in PHP

You can inject the legacy connection or entity manager into your services using Symfony's `Target` attribute.

### 1. Using Connection (DBAL / Raw SQL)
Use this approach for high-performance raw SQL select queries to fetch legacy rows.

```php
<?php

declare(strict_types=1);

namespace App\Service\Migration;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Target;

class MigrationService
{
    public function __construct(
        #[Target('legacy')]
        private readonly Connection $legacyConnection,
        
        // Main connection to write migrated data
        private readonly Connection $defaultConnection,
    ) {}

    public function migrateUsers(): int
    {
        // Fetch raw data from legacy DB
        $legacyUsers = $this->legacyConnection->fetchAllAssociative('SELECT * FROM old_users');
        
        // ... process and insert into defaultConnection
        return count($legacyUsers);
    }
}
```

### 2. Using EntityManager (ORM)
If you map legacy tables to ORM entities, you can target the legacy entity manager.

```php
<?php

declare(strict_types=1);

namespace App\Service\Migration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

class MigrationService
{
    public function __construct(
        #[Target('legacy')]
        private readonly EntityManagerInterface $legacyEntityManager,
        
        // Main entity manager to save migrated entities
        private readonly EntityManagerInterface $defaultEntityManager,
    ) {}
}
```

### 3. Alternative YAML Configuration
If you prefer not to use attributes, you can target the argument in `config/services.yaml`:

```yaml
services:
    App\Service\Migration\MigrationService:
        arguments:
            $legacyConnection: '@doctrine.dbal.legacy_connection'
```
