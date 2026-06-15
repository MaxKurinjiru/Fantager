# Architectural Specification — Foundation

## Purpose
This document defines the basic backend architecture of the project. It serves as a reference for implementation, deployment, and operations.

## Scope
- Backend application
- Data layer
- Operational and backup requirements
- Security and operational requirements

## Goals
- Ensure code scalability and maintainability
- Define a consistent deployment environment
- Establish availability and data recovery requirements

## Required Technologies
- Docker 20.10+ and docker-compose 2.0+ (containerization)
- PHP 8.5
- Symfony 7.4
- MariaDB 11.4
- Node.js 24+ (for Webpack Encore and frontend tooling)
- npm 11+ (frontend package manager)
- Composer 2.5+ (PHP dependency manager)
- **Sentry** (error tracking — `sentry/sentry-symfony` package)

> Note: This specification lists only the technologies approved above. All development must target these versions.

## Main Components (logical overview)
- **Web Layer**: Server-rendered templates (Symfony Twig) with progressive enhancement (Stimulus + Turbo) for client interactions
- **Frontend Asset Pipeline**: Webpack Encore bundling CSS, JavaScript, and assets for optimized delivery
- **Application Services**: Reusable business logic layer, independent of delivery mechanism (Web controllers or API endpoints)
- **Internal API Routes**: Resource-oriented endpoints returning JSON; serve as reference implementations and prepare for future REST API expansion
- **Data Access Layer**: Repository and ORM (Doctrine) abstracting database operations
- **Persistence Layer**: MariaDB 11.4 for relational data
- **Migrations and Schema Management**: Doctrine migrations for DB versioning and rollback capability

## Architectural Pattern

**Dual-Layer Design**: Web-First with Internal API Readiness
- Controllers route to reusable Services (not duplicating logic for Web vs. API)
- **Web routes** (`/heroes`, `/training`) render Twig templates; frontend uses Stimulus + Turbo for interactivity
- **Internal API routes** (`/api/v1/heroes`, `/api/v1/training`) return JSON from the same Service methods
- Stimulus components trigger API endpoints via AJAX; Turbo handles navigation between web pages
- Internal API design follows REST principles; ready for external consumption, mobile apps, or REST API layer migration

**Benefits**:
- Clear separation of concerns (business logic in Services)
- Testable services independent of HTTP presentation
- Future migration path to REST API or mobile backends
- Consistent patterns for new feature development

### Request Flow Comparison

**Web Route (Traditional Navigation)**
```
User clicks link → Turbo intercepts → GET /heroes → WebController → Service → Twig render → HTML response → DOM update
```

**API Route (Stimulus AJAX)**
```
Stimulus event (click, submit) → AJAX POST /api/v1/heroes/123/train → ApiController → Service → JSON response → DOM update
```

## Frontend Architecture

The frontend combines three complementary technologies for a modern web experience:

- **Twig Templates**: Server-rendered HTML with access to backend data; no build step needed for templates
- **Stimulus**: Lightweight JavaScript framework for component interactivity (form handling, DOM updates, AJAX calls)
- **Turbo**: Automatic SPA-like navigation and form submission without full page reloads
- **Webpack Encore**: Bundles CSS, JavaScript, and assets; outputs versioned files to `public/build/` for long-term caching

**Asset Pipeline Flow**:
```
assets/ (source)
  ├── app.js (Stimulus controllers)
  ├── controllers/ (component JS)
  └── styles/ (SCSS → compiled CSS)
         ↓ npm run build (Webpack Encore)
    public/build/ (output)
      ├── app.[hash].js
      ├── app.[hash].css
      ├── runtime.[hash].js
      ├── entrypoints.json
      └── manifest.json
         ↓ Twig references via {{ asset() }}
    Served in HTML responses
```

## Data Architecture
- Primary storage: MariaDB 11.4
- Schema modeling: relational model designed according to domain entities
- Migrations: Doctrine migrations for version control and rollback
- Backup and recovery: backup plan and tested recovery procedures (details to be added)

## Code Quality & Standards

### PHP Code Quality
- **Linting & Formatting**: PHP-CS-Fixer for PSR-12 compliance; configured in `.php-cs-fixer.dist.php`
- **Static Analysis**: PHPStan level 8+ for type checking and bug detection
- **Commands**: `composer php-cs` (check) and `composer php-cs-fix` (apply fixes)

### Testing Strategy
- **Unit Tests**: PHPUnit for Service and Repository layer testing
- **Integration Tests**: PHPUnit with test database for API endpoint and workflow testing
- **End-to-End Tests**: Playwright for critical user journeys and UI interactions
- **Target Coverage**: Minimum 70% code coverage on Services and Controllers
- **Commands**:
  - `composer test` — Run PHPUnit
  - `npm run test:e2e` — Run Playwright tests

### Git Workflow
- **Branching**: Feature branches from `main` or `develop`
- **PR Process**: Mandatory code review before merge
- **Commit Messages**: Clear, descriptive messages; reference issue IDs when applicable

## Performance & Caching

### Query Caching
- Doctrine query result cache for frequently accessed, slowly-changing data
- Cache TTL varies by data type (heroes: long-lived, battle results: short-lived)

### HTTP Caching
- Browser caching headers for static assets (versioned with hashes)
- Long cache TTL for assets: `Cache-Control: public, max-age=31536000`
- Short/no cache for HTML and API responses (unless explicitly cacheable)

### Performance Targets
- Page load time: < 2 seconds (P95)
- API response time: < 500ms (P95)
- Database query time: < 100ms (average)

## Logging & Monitoring

### Logging
- **Framework**: Monolog with Symfony integration
- **Channels**: `app` (general), `api` (API requests), `database` (query logs)
- **Aggregation**: Logs written to `var/log/` locally; future: aggregate to ELK/Loki stack
- **Log Levels**: ERROR, WARNING, INFO, DEBUG; configure per environment

### Error Tracking
- **Tool**: Sentry (`sentry/sentry-symfony`)
- Development: exceptions captured and visible in Sentry with full stack traces; can be disabled locally via `SENTRY_DSN=` empty
- Production: all unhandled exceptions and errors reported to Sentry with environment, release, and user context
- Symfony integration: `Sentry\SentryBundle` registers automatically via Flex; configure DSN in `.env.local` / environment variable `SENTRY_DSN`
- **Do not log sensitive data** (passwords, tokens) — scrub PII in `before_send` hook using [SentryBeforeSendCallback](../src/Service/Sentry/SentryBeforeSendCallback.php)

### Metrics & APM
- Response times, error rates, and custom business metrics
- Future: APM tool (e.g., New Relic, Datadog) for performance profiling
- Database query monitoring: Log slow queries (> 1s)

## Multi-Kingdom Architecture

Each **kingdom** is a separate game server/economy:

### Data Isolation Strategy
- **Database Strategy**: Separate MariaDB database per kingdom (or shared DB with kingdom scoping)
- **Schema**: Same schema replicated per kingdom (no cross-kingdom queries)
- **Authentication**: User session tied to kingdom; kingdom ID part of auth context
- **Data References**: Players, heroes, teams, items exist only within their kingdom
- **Cross-Kingdom**: League rankings may aggregate across kingdoms (or per-kingdom only)

### Implementation
- Doctrine: Use repository queries scoped to current kingdom
- Services: Require kingdom context from controller
- Controllers: Extract kingdom from route or session, pass to services

**Reference**: See [game-summary.md](game-summary.md#21-kingdom-system--server-split) for kingdom system details.

## Internal API Strategy

The backend exposes **internal API routes** for Stimulus components and future expansion:

### Current Use
- Stimulus components trigger AJAX calls to `/api/v1/*` endpoints
- Services return JSON responses for dynamic UI updates
- No external consumption (internal only)

### Future Use
- Public REST API with authentication (JWT, OAuth)
- Mobile app backend
- Third-party integrations

**Full Design**: See [api-design.md](api-design.md) for endpoints, response formats, error handling, and design patterns.

## Technology Stack Checklist

This repository follows a container-first development model. Developer tooling (PHP, Composer, Node, npm/yarn, build tools) is expected to run inside the local containers defined by `docker-compose.yml` to ensure consistent environments.

Use the `web` container (container name `fantager-web`) for PHP, Composer and frontend build tasks, and the `db` container (container name `fantager-db`) for database checks. Example commands:

```
docker compose up --build -d
docker exec -it fantager-web composer install
docker exec -it fantager-web npm install
docker exec -it fantager-web npm run build
docker exec -it fantager-web php bin/console doctrine:migrations:migrate
```

### Backend (container-first checks)
- [x] Run `docker exec -it fantager-web php -v` to confirm PHP 8.5 is available in the container
- [x] Run `docker exec -it fantager-web composer --version` to confirm Composer 2.5+ in-container
- [x] `.env.local` configured with `DATABASE_URL` (pointing to the `db` service when running in containers)
- [x] `docker exec -it fantager-web composer install` completed; `vendor/` present in the project volume
- [x] `docker exec -it fantager-web php bin/console doctrine:migrations:migrate` runs successfully against the `db` container

### Frontend (container-first checks)
- [x] Node and npm/yarn available in the `fantager-web` container: `docker exec -it fantager-web node --version` and `docker exec -it fantager-web npm --version`
- [x] `docker exec -it fantager-web npm install` completed; `node_modules/` present (mounted in project volume)
- [x] Webpack Encore configured in `webpack.config.js`
- [x] `docker exec -it fantager-web npm run build` produces files in `public/build/`
- [x] `docker exec -it fantager-web npm run watch` works for development iterations

### Development Tools (run inside container when possible)
- [x] PHP-CS-Fixer available and configured (`.php-cs-fixer.dist.php`): `docker exec -it fantager-web composer php-cs`
- [x] PHPStan available via Composer: `docker exec -it fantager-web composer phpstan`
- [x] PHPUnit available and passing: `docker exec -it fantager-web composer test`
- [ ] Playwright not yet installed — E2E tests pending

### Local Environment (Docker)
- [x] Docker Desktop / Docker Engine installed on host
- [x] `docker compose up -d` starts the `fantager-web` and `fantager-db` containers
- [x] Application reachable on the configured port (see `docker-compose.yml` and `apache` config)
- [x] Database accessible from the `fantager-web` container as `db`/`fantager-db` service

### CI/CD & Deployment
- [ ] CI configuration (GitHub Actions, GitLab CI) defined and uses container images or `docker compose` for reproducible builds
- [ ] Deployment process documented; images/tags and secret management described
- [ ] Database backup strategy and migration run policy documented for deployments

### Notes & Tips
- Prefer `docker exec -it` for iterative development tasks to avoid creating ephemeral containers unless isolation is needed (`docker compose run --rm`)
- Keep sensitive configuration out of images; provide via `.env`, Docker secrets, or CI secret store
- If developers prefer local installs, the container-first commands above are the canonical reference

## Security (requirements)
- Enforce secure communication between client and server (HTTPS)
- Secure storage and management of secrets (DB credentials, environment variables, API keys)
- Principle of least privilege for access to DB and services
- Input validation and protection against common application-level attacks (SQL injection, XSS, CSRF)
- CSRF tokens on all state-changing forms (automatic in Symfony)
- Rate limiting on API endpoints (future implementation)
- Session security: secure cookies, reasonable timeout, kingdom-scoped sessions

## Operations and Deployment
- Environments: `dev`, `staging`, `prod`
- Deployment automation: CI/CD pipeline (specific tools to be added later)
- Database migrations: Doctrine migrations run before deployment; rollback capability maintained

### Containerization
Use Docker for local development, CI and consistent deployment environment:
- Dockerfile for PHP with necessary extensions (PDO_MYSQL, mbstring, intl, etc.)
- docker-compose.yml for local stack: Apache/Nginx, PHP-FPM, MariaDB, optional Redis/Mailhog
- Production: Consider Docker for deployment to Kubernetes or Docker Swarm
- Minimum recommendations:
  - Create Dockerfile for PHP and docker-compose.yml for local development stack (Apache, MariaDB).
  - Use containers in CI for isolated builds and tests; version images or tags according to CI/CD pipeline.
  - Ensure sensitive data configuration is outside the image (environment variables, Docker secrets or CI secret store).

### Scaling Considerations (Future)
- Database replication and read replicas
- Load balancing for multiple application instances
- Caching layer (Redis) for sessions and query results
- CDN for static assets
- Microservices for separate systems (battle engine, economy calculations)

## Backups and Recovery

### Backup Strategy
- **Frequency**: Daily database backups, with hourly snapshots for critical data
- **Retention**: 30 days of daily backups; 12 months of monthly backups
- **Storage**: Local backup storage + remote (S3 or similar) for disaster recovery
- **Verification**: Monthly backup restoration tests to staging environment

### Recovery Procedures
- **RPO (Recovery Point Objective)**: 1 hour (lose up to 1 hour of data)
- **RTO (Recovery Time Objective)**: 4 hours (return to service within 4 hours)
- **Runbook**: Documented steps for restore from backup (to be created)
- **Testing**: Quarterly backup restoration drill

## Documentation and Runbooks

### Required Documentation
- [ ] Architecture diagram
- [ ] Deployment runbook — steps for deploying to staging/production
- [ ] Backup recovery runbook — steps for restoring from backup
- [ ] Database schema diagram — entity relationships
- [ ] API documentation — OpenAPI/Swagger spec (generated from code)
- [ ] Local development guide — setup instructions for new developers
- [ ] Operations handbook — troubleshooting, common tasks, monitoring alerts


## Next Steps & Status

### Phase 1: Foundation ✅ Complete
- [x] Define core architecture and technology stack
- [x] Establish dual-layer design (Web + API)
- [x] Document security and deployment strategy
- [x] Create technology stack checklist
- [x] Set up local development environment (Docker + README)
- [ ] Finalize CI/CD pipeline tools (GitHub Actions — not yet configured)

### Phase 2: Implementation ✅ Backend largely done
- [x] Create Dockerfile and docker-compose.yml
- [x] Set up Symfony project structure and routing
- [x] Implement database migrations and schema (all phases covered in `Version20260608160305`)
- [x] Create Service layer foundation (Auth, Kingdom, Team, Hero, Summoning, Training, HQ, Economy, Item, Spell, Formation)
- [x] Set up PHPUnit testing framework (1 test passing; coverage expansion pending)
- [ ] Set up Playwright for E2E tests (not yet installed)

### Phase 3: Production Readiness (Future)
- [ ] Deploy to staging environment
- [ ] Load testing and performance tuning
- [ ] Finalize backup and recovery procedures
- [ ] Set up monitoring and alerting
- [ ] Create deployment and operations runbooks

---

**Document Status**: Last updated June 4, 2026. Reflects current approved architecture and technology choices.
