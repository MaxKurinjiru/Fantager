# Backend Agent Cheatsheet (Fantager)

One-page reference for AI agents and quick human review.  
**Full specs:** [arch-spec.md](arch-spec.md) · [api-design.md](api-design.md) · [entity-reference.md](entity-reference.md)

---

## Before you code

| Check | Why |
|-------|-----|
| [known-issues.md](known-issues.md) | Open design gaps (combat formulas, enchanting, …) — do not invent missing mechanics |
| [README.md](README.md) § Current Implementation Status | Avoid reimplementing features that already exist |
| [roadmap.md](roadmap.md) | Milestone order and scope |
| Matching `systems/*.md` + `screens/*.md` | Domain rules and UI requirements for the feature |

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

1. Read `systems/{feature}.md`, screen doc, `entity-reference.md`
2. Entity / enum / migration (if schema changes) — follow table naming in entity-reference
3. `Service/{Domain}/` + unit test in `tests/Service/{Domain}/`
4. `Controller/Api/V1/` with `ApiControllerTrait` (if AJAX/Stimulus needs it)
5. `Controller/Web/` + Twig (follow [ui-agent-cheatsheet.md](ui-agent-cheatsheet.md))
6. Stimulus controller if interactive (follow [api-design.md](api-design.md) response shape)
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
- HTTP status codes: see [api-design.md](api-design.md)

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
- Write tests when adding logic; do **not** run PHPUnit unless the user asks (see [AGENTS.md](../AGENTS.md))

---

## Schema changes

- New migration in `migrations/` via `doctrine:migrations:diff` (user-requested) or hand-written `Version*.php`
- Document new entities/enums in [entity-reference.md](entity-reference.md)
- Respect naming conventions (domain prefixes on sub-entities — see entity-reference § Design Decisions)

---

## Required checklist (every backend change)

- [ ] Business logic in `Service/`, not in controllers
- [ ] Web and API share the same service methods where both exist
- [ ] User-facing text uses translation keys (+ EN/CS YAML)
- [ ] `UserFacingException` / `jsonError` for expected failures
- [ ] New route listed in [route-map.md](route-map.md)
- [ ] Schema/entity changes reflected in [entity-reference.md](entity-reference.md) (entities + enums tables)
- [ ] PHP translation keys in `messages.en.yaml` + `messages.cs.yaml` (or `validators.*.yaml` for form validation)
- [ ] Domain behavior matches `systems/*.md`; screen matches `screens/*.md`
- [ ] Implementation status updated in [README.md](README.md) when feature status changes

---

## Scope guardrails

| Area | Agent note |
|------|------------|
| Combat engine | Formulas undefined — see [known-issues.md](known-issues.md) #1 |
| Item enchanting | Mechanics undefined — #2 |
| `docs/future/`, `_deferred/` | Out of scope unless user explicitly requests |
| Public wiki/news | Mostly planned — check implementation status first |

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
| `docs/backend-agent-cheatsheet.md` | This file |
| `.cursor/rules/php-backend.mdc` | Auto-injected on PHP edits |
| `.cursor/skills/fantager-backend/` | Full backend workflow + examples + reference |
| `docs/api-design.md` | REST shape, status codes |
| `docs/route-map.md` | All Web + API routes |
| `docs/screen-code-map.md` | Screen → controller, Twig, Stimulus, services |
