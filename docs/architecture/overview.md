# Architecture

Live stack, dual-layer request flow, security, and local ops. API shape: [api.md](api.md). Schema: [entities.md](entities.md). Routes: [routes.md](routes.md). Local setup: [guides/local-setup.md](../guides/local-setup.md), [docker/README.md](../../docker/README.md).

## Stack

| Layer | Version |
|-------|---------|
| PHP | 8.5 (Symfony 8.1) |
| Database | MariaDB 11.4 |
| PHP/Apache image | Alpine 3.24 |
| Frontend | Twig + Stimulus + Turbo, Webpack Encore |
| Node / npm | Node 24+, npm 11+ |
| Packaging | Docker Compose, Composer 2.5+ |
| Errors | Sentry (`sentry/sentry-symfony`) |

Development targets these versions. Run PHP, Composer, npm, and console commands inside `fantager-web` as `apache` — [.github/copilot-instructions.md](../../.github/copilot-instructions.md).

## Dual-layer design

Web and the internal API share the same services. Controllers stay thin.

- **Web** (`/app/…`) — Twig pages; Turbo for navigation
- **Internal API** (`/api/v1/…`) — JSON for Stimulus AJAX; session cookies; no external consumers
- **Services** (`src/Service/{Domain}/`) — rules and orchestration
- **Repositories** — queries only
- **Config data** — `config/game/*.yaml` plus loader services

**Web:** `User clicks link → Turbo → GET /app/heroes → WebController → Service → Twig → HTML`

**API:** `Stimulus event → POST /api/v1/… → ApiController → same Service → JSON → DOM update`

Frontend source is `assets/` (Stimulus controllers, SCSS). `npm run build` writes versioned files to `public/build/`. Twig loads them via `asset()`.

## Data

- One MariaDB schema. Kingdoms are **not** separate databases: isolation is `kingdom_id` on rows — [kingdom-system.md](../systems/kingdom-system.md).
- Schema bootstrap: empty database via `composer init-db` (`doctrine:schema:create`, Messenger transports, migrations metadata). Historical `Version*` files were removed; new mapping diffs go in `migrations/` (`composer migrate-diff` / `composer migrate`). Production start runs `doctrine:schema:update --force` plus Messenger transports.
- Player session is tied to one Kingdom; no cross-kingdom marketplace or league play.

## Quality

| Tool | How |
|------|-----|
| PHP-CS-Fixer | `composer php-cs` / `composer php-cs-fix` (PSR-12) |
| PHPStan | level 8+, `composer phpstan` |
| PHPUnit | `composer test` — services and controllers under `tests/` |
| Docs vs code | `bash scripts/check-backend-docs.sh` (routes, entities, enums, i18n) |
| UI compliance | `bash scripts/check-ui-compliance.sh` |
| CI | GitHub Actions: PHPStan, PHP-CS, PHPUnit, UI compliance, backend-docs, deploy |

Browser E2E / Playwright is **Cut** (A8.2). Do not add a Playwright suite unless the user explicitly asks.

Coverage is not a gate. Focus tests on economy, calendar, league, combat, and new service logic.

## Observability

- **Logs:** Monolog → `var/log/` (`app`, `api`, `database`)
- **Sentry:** unhandled exceptions; scrub PII in `SentryBeforeSendCallback`. Empty `SENTRY_DSN` disables locally.
- **Liveness:** `GET /health` — unauthenticated JSON `{ "status": "ok" }` only. No DB ping. `Cache-Control: no-store`. Compose healthchecks probe this path. Not a readiness probe.

## Security

- HTTPS in production; secrets via `.env` / environment, not images
- CSRF on state-changing forms and Stimulus AJAX (`framework.csrf_protection`)
- Session cookies: `httpOnly`, `secure=auto`, `sameSite=lax`
- Input validation; parameterized Doctrine queries
- Auth is session email/password — [auth-system.md](../systems/auth-system.md). Public API (JWT/OAuth) is not live.

API rate limiting, CSP / HSTS / frame options, and session password change stay **Parked** — [auth-system.md](../systems/auth-system.md).

## Local backup

```bash
docker exec fantager-db mysqldump -u root -p"$MARIADB_ROOT_PASSWORD" fantager > backup.sql
docker exec -i fantager-db mysql -u root -p"$MARIADB_ROOT_PASSWORD" fantager < backup.sql
```

## Not live

Do not treat these as a work queue. Staging/prod runbooks, OpenAPI, architecture diagrams, Redis/CDN, load testing, and quantified RPO/RTO are unspecified. Production backup retention is not documented here.
