# Backend Agent Cheatsheet (Fantager)

One-page reference for AI agents and quick human review.  
**Full specs:** [architecture/overview.md](../architecture/overview.md) · [architecture/api.md](../architecture/api.md) · [architecture/entities.md](../architecture/entities.md)

---

## Before you code

**Work queue:** [roadmap/current.md](../roadmap/current.md) — alignment waves 0–8 complete; do not invent a next alignment step. Do not start Milestone 8/9. Do not invent mechanics for **Open** rows in [product/known-issues.md](../product/known-issues.md). **#16 is Resolved** — spell catalog T1–T5; follow [spell-system.md](../systems/spell-system.md#locked-catalog-t1t5). **#15 is Resolved** — listing price band −50% / +100%; follow [marketplace-system.md](../systems/marketplace-system.md#locked-listing-price-band). **#14 is Resolved** — Genie adaptation ≥ 80; follow [summoning-system.md](../systems/summoning-system.md#locked-genie-summon-pool). **#4 is Resolved** — HQ attendance analytics; follow [18-arena-management.md](../screens/18-arena-management.md#locked-attendance-analytics). **#13 is Resolved** — roster extras Cut; follow [03-hero-roster.md](../screens/03-hero-roster.md#locked-roster-extras). **#12 is Resolved** — L1 + thin L2 editor; L3 / unmet `when`/`target` Cut; follow [formation-system.md](../systems/formation-system.md#locked-l1l2-editor). **#9 is Resolved** — HUD + Haste/Shock locked; in-combat morale, race synergy, and simulate Cut; follow [combat-system.md](../systems/combat-system.md#locked-no-race-synergy--no-simulate) and [12-combat-battle.md](../screens/12-combat-battle.md#locked-replay-hud). **#2 is Resolved** — enchanting is not in the live game; follow [item-system.md](../systems/item-system.md#locked-no-enchanting); do not reintroduce essence. **#6 is Resolved** — follow [hero-system.md](../systems/hero-system.md#locked-hero-level-invariant); do not derive `Hero.level` from XP on read. **#7 is Resolved** — chemistry is flavor for matches; follow [team-system.md](../systems/team-system.md#locked-chemistry-flavor). **#8 is Resolved** — occupancy; follow [item-system.md](../systems/item-system.md#locked-item-occupancy).

| Check | Why |
|-------|-----|
| [roadmap/current.md](../roadmap/current.md) | Active docs/code work queue |
| [product/known-issues.md](../product/known-issues.md) | Design first — stop and ask on Open IDs |
| [systems/README.md](../systems/README.md) § Current Implementation Status | Honest live-contract snapshot (A1.1) |
| [roadmap/history.md](../roadmap/history.md) | Historical M1–9 only; M1–6 ✅ ≠ no gaps (A1.2) |
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

Do not invent formulas, slots, or UI for Open known-issues. Combat leftovers stay locked as below; XP, chemistry, occupancy, enchanting, and roster extras are Resolved locks.

| Area | Agent note |
|------|------------|
| Combat engine | Formulas live in [combat-system.md](../systems/combat-system.md) + `CombatStatCalculator`. Replay HUD is locked ([12-combat-battle.md](../screens/12-combat-battle.md#locked-replay-hud)). Haste/Shock YAML speed percents apply to the initiative **sort only**, not AP ([combat-system.md](../systems/combat-system.md#speed--initiative-init)). In-combat `morale_change` is Cut — do not read `moraleDecayMultiplier`. Race synergy and `POST /api/v1/combat/simulate` are Cut ([product/known-issues.md](../product/known-issues.md) #9 Resolved). Do not change locked round-200 / fumble / KO≠death. |
| Item enchanting | **#2 Resolved (Cut).** Enchanting is not in the live game. Do not reintroduce essence or ship an enchant UI, cost, or combat effect. `Item.special_effects` stays unused. Spec: [item-system.md](../systems/item-system.md#locked-no-enchanting). |
| Hero XP / level | **#6 Resolved (B).** Lifetime `Hero.xp` (never spent). Stored `Hero.level` is live truth — combat/UI/API must not compute level from XP. After match XP, `HeroProgressionService` raises stored level. Formula/`level_cap` change: `app:hero-levels:refresh` (or a migration), never lazy-recalc on read. Curve: `config/game/hero-progression.yaml`. Spec: [hero-system.md](../systems/hero-system.md#locked-hero-level-invariant). |
| Team chemistry | **#7 Resolved (flavor).** `TeamChemistryService` writes 0–100. Combat does not read it. Live: dashboard, fan club / attendance, league tie-break. Do not add in-combat synergy or Loner combat effects (#9 Cut). Spec: [team-system.md](../systems/team-system.md#locked-chemistry-flavor). |
| Item occupancy | **#8 Resolved.** Nine slots (`ring` + `ring_2`). Two-handed subtypes occupy both hands. Dual-wield one-handed melee. Spec: [item-system.md](../systems/item-system.md#locked-item-occupancy). Do not add extra dual-wield attacks or a third ring. |
| Formation L1/L2 | **#12 Resolved.** Per-slot `target_order` + `fallback` and thin equipped `spell_priorities`. Do not add L3 sequences or `enemy_status` / `lowest_hp_enemy` in the UI. Save via `FormationTacticsNormalizer`. Spec: [formation-system.md](../systems/formation-system.md#locked-l1l2-editor). |
| Roster extras | **#13 Resolved (Cut).** Do not add batch training / multi-select APIs, server-side roster filters, or sketch extras on the hero card. Spec: [03-hero-roster.md](../screens/03-hero-roster.md#locked-roster-extras). |
| Arena analytics | **#4 Resolved.** Last 18 `arena_revenue` rows in the HQ panel (`ArenaService::buildRevenueHistory()`). Do not add a new analytics route, season query, or snapshot entity. Spec: [18-arena-management.md](../screens/18-arena-management.md#locked-attendance-analytics). |
| Marketplace price band | **#15 Resolved.** Suggested list price; floor −50% / ceiling +100%. `createListing` rejects out-of-band buy-now / start / buyout. Auction bids are not capped. Spec: [marketplace-system.md](../systems/marketplace-system.md#locked-listing-price-band). |
| Spell catalog | **#16 Resolved.** Two spells per school per tier for T1–T5. No T6+, resurrection, or summon-creature. Spec: [spell-system.md](../systems/spell-system.md#locked-catalog-t1t5). |
| `docs/future/`, `_deferred/` | Out of scope unless the user explicitly requests |
| Public wiki/news | Wiki stays **Planned** (A8.5) — do not ship `GET /wiki`, a CMS, or a navbar Help link. Live public content is homepage + `/news` (no admin UI). Lock: [00-public-pages.md](../screens/00-public-pages.md#locked-public-wiki-stays-planned). |
| Password / settings extras | Session password change, notification prefs, and privacy stay **Planned** (A8.6). Do not ship a password-change form or `/api/v1/settings`. Forgotten passwords use `/password-reset`. Lock: [19-player-profile-settings.md](../screens/19-player-profile-settings.md#locked-password-change--extra-settings-stay-planned). |

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
