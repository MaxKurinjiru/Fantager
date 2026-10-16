# Trainer Management Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [training-system.md](../systems/training-system.md), [05-training.md](05-training.md)

**When:** Promote combatants to trainers, inspect frozen stats, dismiss a trainer.

**Status:** There is **no standalone trainer page**. Trainers live on the **Training** screen (`GET /app/training`), **Trainers** tab. Focus, slots, and hero assignment are documented in [05-training.md](05-training.md) (same page).

**Live UI:** `templates/training/index.html.twig`, `templates/components/training/trainer_card.html.twig`. Promote also exists on hero detail (manage dropdown).

---

## Implemented

### Routing

Same as training: `GET /app/training` → `Web\TrainingController::index()`.

Trainer limit: `2 + floor((trainingFacilityLevel - 1) / 2)`. Shown as a count/limit bar on the Trainers tab. Promote UI is hidden when at the cap or when training is locked.

### Displayed information (trainer card)

- Name, race (icon + translated name)
- Age, age phase, mortality-threshold warning when at or above the race threshold
- Frozen primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) via `compact_attributes`
- Slot occupancy vs limit (`3 + floor((trainingFacilityLevel - 1) / 2)`)
- Focus type / target attribute and assigned trainees (see [05-training.md](05-training.md))
- Dismiss copy; if `status = selling`, dismiss is disabled with a selling warning

There is **no** selected-trainer detail pane, age-progression timeline, or marketplace price on this page.

### Actions

| Action | How |
|--------|-----|
| Promote combatant → trainer | Select an `available` combatant and confirm. `POST /api/v1/training/trainers/promote` (`hero_id`). No Veteran+ gate; no specialty pick — stats freeze as-is. Unequips items and clears formation slots. |
| Dismiss trainer | Confirm on the card. `POST /api/v1/training/trainers/{id}/dismiss` → memorial via `GraveyardService`. Blocked while `selling`. |
| Buy / sell trainer | **Marketplace** (`/app/marketplace` browse `trainer` / sell tab) — not a button on the trainer card |

Hero-detail manage menu also offers **Promote** when the hero is an available combatant, has no trainer assignment, and the team is under the trainer cap (`hero-promote`).

### Backend

| Piece | Location |
|-------|----------|
| Web | `GET /app/training` → `Web\TrainingController` |
| List JSON | `GET /api/v1/training/trainers` — name, race, focus, slots, assigned heroes, lock; **not** the full attribute block (attributes are server-rendered on the card) |
| Promote | `POST /api/v1/training/trainers/promote` → `TrainingService::promoteToTrainer()` |
| Dismiss | `POST /api/v1/training/trainers/{id}/dismiss` → `TrainerDismissalService` |

---

## Planned

Original sketch extras — do not treat as a contract:

- Dedicated trainer-management route
- Trainer detail (caps timeline, marketplace cost on this screen)
- Status chips “Active” / “Aging risk” beyond the mortality warning
- Sell / buy CTAs on the trainer card (use Marketplace)

---

## Frontend

| Piece | Location |
|-------|----------|
| Page / tabs | `templates/training/index.html.twig` |
| Card | `templates/components/training/trainer_card.html.twig` |
| Stimulus | `training`, plus `hero-promote` on hero detail |
