# Hero Chronicle System

Reference: [entity-reference.md](../entity-reference.md#4-hero-domain), [hero-system.md](hero-system.md), [screens/04-hero-detail.md](../screens/04-hero-detail.md)

Purpose: Document the **hero chronicle** — an append-only per-hero event log shown on the Hero Detail **History** tab.

---

## Overview

The hero chronicle answers: **“What happened to this hero?”** — summons, transfers, training, matches, mastery gains, and death.

| Concern | Storage | Scope |
|---------|---------|--------|
| **Hero chronicle** | `hero_chronicle` | Per **hero** (`hero_id`); survives hero dismissal via `original_hero_id` |
| **Team chronicle** | `team_chronicle` | Per **team** — team-wide events (ownership, season, marketplace at team level) |
| **Hero training history** | `hero_training_history` | Weekly tick stat-gain rows; still used on Training tab; mirrored into chronicle |

Unlike team chronicle, hero chronicle is **hero-scoped**. Marketplace transfers write to both hero chronicle (per hero) and team chronicle (buy/sell events on each team).

---

## Data Model

Table: `hero_chronicle` — entity `App\Entity\Hero\HeroChronicle`.

| Field | Role |
|-------|------|
| `hero_id` | Current hero FK (nullable after dismiss/death — see `recordDied`) |
| `original_hero_id` | Stable hero id for lookups after removal |
| `team_id` | Team context at event time (nullable) |
| `type` | `HeroChronicleEventType` |
| `subject_key` | Symfony translation key under `hero_activity.*` |
| `subject_params` | JSON parameters for the translation string |
| `data` | Machine-readable context (opponent team id, price, cause, …) |
| `created_at` | Event timestamp |

Entries are **append-only**. Rendering uses the viewer's locale via `HeroChroniclePresenter`.

---

## Event Types

### Implemented (written today)

| `HeroChronicleEventType` | Trigger | Service |
|--------------------------|---------|---------|
| `summoned` | Hero summoned or joined starting NPC roster | `SummoningService`, `KingdomInitializationService` |
| `transferred` | Hero/trainer sold on marketplace | `MarketplaceService` |
| `match_played` | Hero fielded in a resolved league fixture | `LeagueMatchResolutionService` |
| `mastery_gained` | Weapon or school mastery tier increased | `HeroMasteryService` |
| `training_completed` | Weekly training tick completed | `TrainingService` |
| `died` | Hero dismissed or memorialized (incl. permanent combat death) | `GraveyardService` |
| `spell_learned` | Hero learned a spell | `SpellService` |

### Reserved / dormant (enum + service methods exist)

| Type | Notes |
|------|-------|
| `levelup` | `HeroChronicleService::recordLevelUp` / `HeroService::levelUp` exist, but no production XP→level path currently calls them |
| `injured` / `recovered` | Injury system (future) |
| `retired` | Voluntary retirement (future) |

---

## UI

| Location | Route / tab | Limit |
|----------|-------------|-------|
| Hero Detail — **History** tab | `/app/heroes/{id}?tab=history` | Last **15** entries |
| Overview — combat stats | `/app/heroes/{id}?tab=overview` | `matches_played`, `matches_won`, win rate (`combat_stats.html.twig`) |

**Templates:** `templates/components/hero/recent_activity.html.twig`  
**Presenter:** `HeroChroniclePresenter::presentRecentForHero()`  
**Controller:** `Web\HeroController::detail()` passes `heroHistory` to Twig.

---

## Write / Read Paths

- **Write:** `HeroChronicleService` only — do not persist `HeroChronicle` directly from feature code.
- **Read:** `HeroChronicleRepository` + `HeroChroniclePresenter`.
- **Tests:** cover presenters and service record methods as features grow.

---

## Related Docs

- [team-chronicle-system.md](team-chronicle-system.md) — team-scoped timeline
- [combat-system.md](combat-system.md) — match eligibility, post-match hero stats
- [marketplace-system.md](marketplace-system.md) — transfer events
