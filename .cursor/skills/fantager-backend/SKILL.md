---
name: fantager-backend
description: >-
  Implements and reviews Fantager PHP/Symfony backend following docs/backend-agent-cheatsheet.md.
  Use when editing services, API/Web controllers, entities, repositories, migrations,
  Messenger handlers, console commands, or PHP tests; or when the user asks for API design,
  vertical slices, or Symfony patterns in this repo.
---

# Fantager Backend

## Read first

1. [docs/backend-agent-cheatsheet.md](../../docs/backend-agent-cheatsheet.md) — layering, checklist, scope guardrails
2. [docs/screen-code-map.md](../../docs/screen-code-map.md) — when implementing or changing a game screen
3. [reference.md](reference.md) — service/entity/controller map (when needed)
3. [examples.md](examples.md) — before/after patterns from this codebase
4. [docs/api-design.md](../../docs/api-design.md) — when adding or changing API endpoints

Full specs: [docs/arch-spec.md](../../docs/arch-spec.md) · [docs/entity-reference.md](../../docs/entity-reference.md)

## Workflow

Copy and track:

```
Backend task progress:
- [ ] Read known-issues.md + implementation status + systems/screens docs
- [ ] Found sibling feature to copy (service + controllers + tests)
- [ ] Business logic in Service/ (shared by Web + API)
- [ ] Translation keys in messages.en.yaml + messages.cs.yaml
- [ ] route-map.md (+ entity-reference.md if schema changed)
- [ ] Unit test in tests/Service/{Domain}/ for new logic
```

Do not run verification commands unless the user asks (see [AGENTS.md](../../../AGENTS.md)).

## Layering (strict)

- **Service/** — rules, orchestration, persistence calls
- **Controller/Web** — Twig + flashes; **Controller/Api/V1** — JSON + `ApiControllerTrait`
- **Repository/** — queries only
- Never copy business logic into controllers

## API

- `use ApiControllerTrait;`
- `jsonError('error.key', $status)` / `jsonException($e, 422)`
- Private `getPlayerTeam()` like sibling API controllers
- `201` for resource creation, snake_case JSON keys

## Services

- `UserFacingException` for expected business failures
- `EconomyService`, chronicle, config services — compose, do not duplicate

## Tests

- Mirror `src/Service/{Domain}/` under `tests/Service/{Domain}/`
- Mock repositories and `EntityManagerInterface` in unit tests
- Add tests when adding logic; do not run PHPUnit unless asked

## Optional verification (user-requested only)

```bash
docker exec -u apache fantager-web composer test
docker exec -u apache fantager-web composer phpstan
docker exec -u apache fantager-web composer php-cs
bash scripts/check-backend-docs.sh
```

## Additional resources

- [reference.md](reference.md) — service, entity, controller, and test maps
- [examples.md](examples.md) — API controller, service, Web controller, test patterns
