# Backend Agent Cheatsheet (Fantager)

One-page reference for AI agents and quick human review.  
**Full specs:** [architecture/overview.md](../architecture/overview.md) · [architecture/api.md](../architecture/api.md) · [architecture/entities.md](../architecture/entities.md)

---

## Before you code

| Check | Why |
|-------|-----|
| [roadmap/current.md](../roadmap/current.md) | Active docs/code work queue — one unchecked step at a time |
| [product/known-issues.md](../product/known-issues.md) | Open design gaps (XP curve, enchanting, chemistry, item occupancy, combat leftovers) — do not invent missing mechanics |
| [systems/README.md](../systems/README.md) § Current Implementation Status | Snapshot; alignment roadmap is more current during waves 0–8 |
| [roadmap/history.md](../roadmap/history.md) | Historical Milestones 1–9 (M8/M9 parked) |
| Matching `systems/*.md` + `screens/*.md` | Domain rules and UI; systems win over `product/overview.md` |

---

## Where code lives

| Layer | Path | Responsibility |
|-------|------|----------------|
| Business logic | `src/Service/{Domain}/` | Rules, calculations, orchestration — **shared by Web and API** |
| Presenters | `src/Service/{Domain}/*Presenter.php` | Shape data for Twig/JSON without duplicating logic |
| Entities | `src/Entity/{Domain}/` | Doctrine entities |
| Enums | `src/Enum/` | PHP backed enums |
| Repositories | `src/Repository/{Domain}/` | Queries only — no business rules |
| Web pages | `src/Controller/Web/` | Twig responses; routes under `/app/…` (authenticated) |
| Internal API | `src/Controller/Api/V1/` | JSON; routes under `/api/v1/…` |
| API helpers | `src/Controller/Api/ApiControllerTrait.php` | `jsonError`, `jsonException`, `transMessage` |
| Config data | `src/Service/Config/` + `config/game/` | Static YAML/JSON (races, status effects, …) |
| Commands | `src/Command/` | Console entry points |
| Messages | `src/Message/` | Symfony Messenger handlers |
| Tests | `tests/Service/{Domain}/`, `tests/Controller/Web/` | Mirror production namespaces |

**Pattern:** thin controllers → inject services → return Twig or JSON. Never duplicate business logic between Web and API.

---

## Vertical slice (new feature)

1. Read `systems/{feature}.md`, screen doc, `architecture/entities.md`
2. Entity / enum / migration (if schema changes) — follow table naming in architecture/entities.md
3. `Service/{Domain}/` + unit test in `tests/Service/{Domain}/`
4. `Controller/Api/V1/` with `ApiControllerTrait` (if AJAX/Stimulus needs it)
5. `Controller/Web/` + Twig (follow [guides/ui.md](ui.md))
6. Stimulus controller if interactive (follow [architecture/api.md](../architecture/api.md) response shape)
7. Update docs (see checklist below)

Copy an existing feature in the same domain before inventing new patterns (e.g. training → `TrainingService`, `TrainingController` Web + API).

---

## API controllers

- Class-level `#[Route('/api/v1')]`; method routes are resource paths
- `use ApiControllerTrait;`
- Auth: `ROLE_PLAYER`; resolve team via private `getPlayerTeam()` pattern used in sibling controllers
- Errors: `jsonError('translation.key', $status)` — keys in `translations/messages.*.yaml`
- Service failures: `jsonException($e, 422)` (handles `UserFacingException` via `UserMessageTranslator`)
- Success: `$this->json([...])` with snake_case keys consistent with sibling endpoints
- HTTP status codes: see [architecture/api.md](../architecture/api.md)

---

## Web controllers

- `#[IsGranted('ROLE_PLAYER')]` on authenticated controllers
- Inject `UserMessageTranslator` for flashes: `$this->userMessages->trans('key')`
- Load data via services/repositories; pass arrays/entities to Twig
- Redirect + flash on recoverable errors; do not expose raw exception messages

---

## Services

- `declare(strict_types=1);` on every PHP file
- Throw `UserFacingException` with translation keys for business-rule violations
- Keep `DomainException` / `InvalidArgumentException` messages as translation keys when caught by `jsonException`
- Persistence via injected `EntityManagerInterface` or repositories — not in controllers
- Config loaders (`RaceConfig`, etc.) read from `config/game/` via `$projectDir`

---

## i18n (PHP)

- User-facing strings: translation keys only (`error.insufficient_gold`, not English prose)
- Register keys in `translations/messages.en.yaml` and `translations/messages.cs.yaml`
- API: `transMessage()` / `jsonError()`; Web: `UserMessageTranslator`; Twig: `|trans`

---

## Tests

- Service tests: `tests/Service/{Domain}/{Service}Test.php` — mock repositories/EM, assert business rules
- Controller tests: `tests/Controller/Web/` — HTTP/kernel tests where useful
- Use `declare(strict_types=1);` and `#[AllowMockObjectsWithoutExpectations]` when mocking (see existing tests)
- Write tests when adding logic; do **not** run PHPUnit unless the user asks (see [AGENTS.md](../../AGENTS.md))

---

## Schema changes

- New migration in `migrations/` via `doctrine:migrations:diff` (user-requested) or hand-written `Version*.php`
- Document new entities/enums in [architecture/entities.md](../architecture/entities.md)
- Respect naming conventions (domain prefixes on sub-entities — see architecture/entities.md § Design Decisions)

---

## Required checklist (every backend change)

- [ ] Business logic in `Service/`, not in controllers
- [ ] Web and API share the same service methods where both exist
- [ ] User-facing text uses translation keys (+ EN/CS YAML)
- [ ] `UserFacingException` / `jsonError` for expected failures
- [ ] New route listed in [architecture/routes.md](../architecture/routes.md)
- [ ] Schema/entity changes reflected in [architecture/entities.md](../architecture/entities.md) (entities + enums tables)
- [ ] PHP translation keys in `messages.en.yaml` + `messages.cs.yaml` (or `validators.*.yaml` for form validation)
- [ ] Domain behavior matches `systems/*.md`; screen matches `screens/*.md`
- [ ] Implementation status updated in [systems/README.md](../systems/README.md) when feature status changes

---

## Scope guardrails

| Area | Agent note |
|------|------------|
| Combat engine | Formulas live in [combat-system.md](../systems/combat-system.md) + `CombatStatCalculator`. Do not invent Haste/Shock initiative, in-combat morale events, or `POST /api/v1/combat/simulate` ([product/known-issues.md](../product/known-issues.md) #9). |
| Item enchanting | Mechanics undefined — [product/known-issues.md](../product/known-issues.md) #2 |
| Hero XP / level | No production `levelUp()` — [product/known-issues.md](../product/known-issues.md) #6 |
| Team chemistry | Display-only until #7 is decided |
| Item occupancy | Two-handed / dual-wield / ring count — #8 |
| `docs/future/`, `_deferred/` | Out of scope unless user explicitly requests |
| Public wiki/news | Mostly planned — check [roadmap/current.md](../roadmap/current.md) A8.5 |

---

## Verification commands (user-requested only)

Agents must not run these unless asked. Humans may use them before a PR:

```bash
docker exec -u apache fantager-web composer test
docker exec -u apache fantager-web composer phpstan
docker exec -u apache fantager-web composer php-cs
docker exec -u apache fantager-web php bin/console doctrine:migrations:migrate
bash scripts/check-backend-docs.sh
```

---

## Agent resources in repo

| Resource | Purpose |
|----------|---------|
| `AGENTS.md` | Entry point, task routing |
| `docs/guides/backend.md` | This file |
| `.cursor/rules/php-backend.mdc` | Auto-injected on PHP edits |
| `.cursor/skills/fantager-backend/` | Full backend workflow + examples + reference |
| `docs/architecture/api.md` | REST shape, status codes |
| `docs/architecture/routes.md` | All Web + API routes |
| `docs/architecture/screen-code-map.md` | Screen → controller, Twig, Stimulus, services |
