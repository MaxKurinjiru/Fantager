# Graveyard System

Reference: [game-summary.md](../game-summary.md#213-graveyard-system)

Purpose: Permanent memorial records for heroes and staff who leave the team.

## Record Types

| Type | Entity | Triggers |
|------|--------|----------|
| **Hero memorial** | `GraveyardRecord` | Dismissal, combat death (future), age (future) |
| **Staff memorial** | `StaffRecord` | Trainer dismissal (future: retirement, death) |

Dismissed heroes are no longer hard-deleted — a snapshot is written to `graveyard_record` before the live `Hero` row is removed.

## Hero Dismissal Flow

1. Validate roster minimum (6 combat-ready heroes)
2. `GraveyardService::prepareHeroRemoval()` — unequip items, clear formation, detach trainer, remove spells/masteries/queue
3. `GraveyardService::recordHero()` — immutable snapshot with cause `dismissed`
4. Pay 40% compensation via `HeroDismissalService`
5. Remove hero entity

## Trainer Dismissal Flow

1. Validate trainer is `active` (not listed on marketplace)
2. `GraveyardService::prepareTrainerRemoval()` — unassign all trainees
3. `GraveyardService::recordTrainer()` — snapshot with cause `dismissed`
4. Pay 30% compensation via `TrainerDismissalService`
5. Remove trainer entity

No minimum trainer count — trainers do not affect match eligibility.

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/graveyard` | List hero + staff memorial records |
| GET | `/api/v1/graveyard/heroes/{id}` | Hero memorial detail |
| GET | `/api/v1/graveyard/staff/{id}` | Staff memorial detail |
| POST | `/api/v1/training/trainers/{id}/dismiss` | Dismiss trainer |

## Web UI

| Screen | Route | Notes |
|--------|-------|-------|
| Graveyard | `/app/graveyard` | Tabs: Heroes / Trainers |
| Hero dismiss | Hero detail overview | Redirects to graveyard after success |
| Trainer dismiss | Training page trainer card | Redirects to graveyard staff tab |

## Services

- `App\Service\Graveyard\GraveyardService` — snapshots and cleanup
- `App\Service\Hero\HeroDismissalService` — hero dismissal + compensation
- `App\Service\Training\TrainerDismissalService` — trainer dismissal + compensation

## Financial Ledger

| Type | When |
|------|------|
| `hero_dismissal_compensation` | Hero dismissed |
| `trainer_dismissal_compensation` | Trainer dismissed |
