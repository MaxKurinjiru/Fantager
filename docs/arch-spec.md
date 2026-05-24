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
- PHP 8.3
- Symfony 7.4
- MariaDB 11.4
- Node.js 18+ (for Webpack Encore and frontend tooling)
- npm 9+ or yarn 3+ (frontend package manager)
- Composer 2.5+ (PHP dependency manager)
- Docker 20.10+ and docker-compose 2.0+ (containerization)

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
- **Linting & Formatting**: PHP-CS-Fixer for PSR-12 compliance
- **Static Analysis**: PHPStan level 8+ for type checking and bug detection
- **Command**: `composer cs-check` and `composer cs-fix`

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
- Development: Symfony DebugBar and error pages
- Production: Integration with error tracking service (e.g., Sentry) — to be configured
- Error logging: All exceptions logged to file and error tracking service

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

Use this checklist to verify all technologies are properly installed and configured:

### Backend
- [ ] PHP 8.3 installed and set as default CLI version
- [ ] Composer 2.5+ installed globally
- [ ] Symfony CLI installed (optional but recommended)
- [ ] MariaDB 11.4 running locally or via Docker
- [ ] `.env.local` configured with `DATABASE_URL`
- [ ] `composer install` completed; vendor/ present
- [ ] `php bin/console doctrine:migrations:migrate` successful

### Frontend
- [ ] Node.js 18+ installed (`node --version`)
- [ ] npm 9+ or yarn 3+ installed
- [ ] `npm install` completed; node_modules/ present
- [ ] Webpack Encore configured in `webpack.config.js`
- [ ] `npm run build` produces output in `public/build/`
- [ ] `npm run watch` works for development

### Development Tools
- [ ] PHP-CS-Fixer installed (`composer cs-check`)
- [ ] PHPStan installed (`composer phpstan`)
- [ ] PHPUnit installed (`composer test`)
- [ ] Playwright installed (`npm run test:e2e`)

### Local Environment (Docker)
- [ ] Docker Desktop installed
- [ ] `docker-compose up` starts PHP, Apache, MariaDB
- [ ] Port 8000 (or configured port) accessible
- [ ] Database accessible via MariaDB client

### CI/CD & Deployment
- [ ] CI configuration (GitHub Actions, GitLab CI) defined
- [ ] Deployment script or process documented
- [ ] Environment variables for staging and production defined
- [ ] Database backup strategy configured

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

### Phase 1: Foundation (Current)
- [x] Define core architecture and technology stack
- [x] Establish dual-layer design (Web + API)
- [x] Document security and deployment strategy
- [x] Create technology stack checklist
- [ ] Finalize CI/CD pipeline tools
- [ ] Set up local development environment documentation

### Phase 2: Implementation (In Progress)
- [ ] Create Dockerfile and docker-compose.yml
- [ ] Set up Symfony project structure and routing
- [ ] Implement database migrations and schema
- [ ] Create Service layer foundation
- [ ] Set up testing framework (PHPUnit + Playwright)

### Phase 3: Production Readiness (Future)
- [ ] Deploy to staging environment
- [ ] Load testing and performance tuning
- [ ] Finalize backup and recovery procedures
- [ ] Set up monitoring and alerting
- [ ] Create deployment and operations runbooks

---

**Document Status**: Last updated May 23, 2026. Reflects current approved architecture and technology choices.
