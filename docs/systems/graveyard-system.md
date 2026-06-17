# Graveyard System

Reference: [game-summary.md](../game-summary.md#213-graveyard-system)

Purpose: Permanent memorial records for heroes and trainers who leave the team.

---

## Implementation Status

| Layer | Status |
|-------|--------|
| **Entity & service** | Implemented — `GraveyardMemorial`, `GraveyardService` |
| **Dismissal flows** | Implemented — hero and trainer dismiss write memorial snapshots before entity removal |
| **Web UI** | Implemented — `Web\GraveyardController`, `/app/graveyard` |
| **Read API** | Implemented — `Api\V1\GraveyardController`, `GET /api/v1/graveyard`, `GET /api/v1/graveyard/{id}` |

Combat death memorials (`MemorialCause::CombatDeath`) remain reserved until the combat engine is implemented.

---

## Data Model

All departures (combatant heroes and trainers) use a single entity:

| Entity | Table | Key fields |
|--------|-------|------------|
| **GraveyardMemorial** | `graveyard` | `team_id`, `name`, `race`, `role_at_departure` (`HeroRole`), `cause` (`MemorialCause`), `age`, `final_level`, `final_stats` (JSON), `departed_at`, `original_hero_id` |

There is **no separate `StaffRecord` entity**. Trainers are `Hero` rows with `role = trainer`; their memorial uses the same table with `role_at_departure = trainer`.

### `MemorialCause` values (implemented)

| Value | Used today |
|-------|------------|
| `dismissed` | Hero or trainer dismissal |
| `combat_death` | Reserved — combat engine (Phase 5) |
| `age` | Reserved |
| `retired` | Reserved |
| `death` | Reserved |

---

## Hero Dismissal Flow (implemented)

1. Validate roster minimum (6 combat-ready heroes)
2. `GraveyardService::prepareHeroRemoval()` — unequip items, clear formation slots, detach trainer, remove spells/masteries/training history
3. `GraveyardService::recordMemorial($hero, $team, MemorialCause::Dismissed)` — immutable snapshot
4. Pay 40% compensation via `HeroDismissalService` (`FinancialRecordType::HeroDismissalCompensation`)
5. `GraveyardService::removeHero()` — delete live `Hero` row

**Endpoint:** `POST /api/v1/heroes/{id}/dismiss`

---

## Trainer Dismissal Flow (implemented)

1. Validate trainer is not listed on marketplace (`HeroStatus::Selling`)
2. `GraveyardService::prepareTrainerRemoval()` — unassign all trainees
3. `GraveyardService::recordMemorial($hero, $team, MemorialCause::Dismissed)` — snapshot with `role_at_departure = trainer`
4. Pay 30% compensation via `TrainerDismissalService` (`FinancialRecordType::TrainerDismissalCompensation`)
5. `GraveyardService::removeHero()` — delete trainer entity

No minimum trainer count — trainers do not affect match eligibility.

**Endpoint:** `POST /api/v1/training/trainers/{id}/dismiss`

---

## Read API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/graveyard` | Memorial wall (Twig) |
| GET | `/api/v1/graveyard` | List memorial records (`?role=`, `?cause=`, `?race=`, `?search=`) + summary stats |
| GET | `/api/v1/graveyard/{id}` | Memorial detail |

See [route-map.md](../route-map.md#graveyard).

---

## Web UI

| Screen | Route | Notes |
|--------|-------|-------|
| Graveyard | `/app/graveyard` | Filter by role (combatant / trainer), cause, race; summary stats; memorial detail via `?id=` |
| Hero dismiss | Hero detail | Dismiss succeeds in-place; memorial visible on graveyard page |
| Trainer dismiss | Training page | Dismiss succeeds in-place; memorial visible on graveyard page |

---

## Services

| Service | Responsibility |
|---------|----------------|
| `App\Service\Graveyard\GraveyardService` | `recordMemorial()`, `prepareHeroRemoval()`, `prepareTrainerRemoval()`, `removeHero()`, `serializeMemorial()` |
| `App\Service\Graveyard\GraveyardPresenter` | Summary stats and list presentation for Web/API |
| `App\Service\Hero\HeroDismissalService` | Hero dismissal validation + compensation |
| `App\Service\Training\TrainerDismissalService` | Trainer dismissal validation + compensation |

---

## Financial Ledger

| Type | When |
|------|------|
| `hero_dismissal_compensation` | Hero dismissed |
| `trainer_dismissal_compensation` | Trainer dismissed |
