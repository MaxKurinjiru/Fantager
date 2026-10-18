# Training Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [training-system.md](../systems/training-system.md), [06-trainer-management.md](06-trainer-management.md)

**When:** Configure trainer focus and assign combatants to weekly training.

**Status:** Live at `GET /app/training` (two tabs: **Trainers** and **Active trainees**). Trainer promote/dismiss is the same page — [06-trainer-management.md](06-trainer-management.md). Hero overview also assigns/unassigns a trainer; it does **not** use a separate Training tab.

**Live UI:** `templates/training/index.html.twig`, `templates/components/training/`.

---

## Implemented

### Header

- Next lock countdown (or locked badge)
- Next weekly evaluation countdown (Thursday 10:00 kingdom time)
- Lock alert Thursday 08:00–10:00: configure / assign / unassign / promote disabled

### Trainers tab

Trainer identity, stats, promote, dismiss: [06-trainer-management.md](06-trainer-management.md).

Per card, for training itself:

- Focus type: Idle, Attribute, Magic, Form (`<select>`; Attribute reveals target STR–LCK)
- Save focus → `POST /api/v1/training/trainers/{id}/configure` (`type`, `attribute`)
- Assigned trainees: name (link to hero detail), level, fatigue, form, cap progress (attribute vs trainer frozen stat, magic school plus capacity / 5, or form/idle copy)
- Unassign → `POST /api/v1/training/trainers/{id}/unassign` (`hero_id`)
- Assign `<select>` of available combatants with no trainer when a slot is free → `POST /api/v1/training/trainers/{id}/assign`

A hero stays `available` while training (not a roster “training” status).

### Active trainees tab

Table of every assigned hero: name/level/compact stats, trainer, focus, cap, fatigue forecast, form forecast, unassign. Forecasts come from `TrainingService` (form gain and rest scale with the trainer's KON; magic fatigue is +20 while capacity can still rise and +10 once it is at 5). Idle trainers with no trainees yield an empty state.

### Hero detail (overview)

If assigned: trainer identity, focus, cap bar, fatigue/form forecast (same projections as the trainees table), unassign, link to this screen. If not: compact trainer cards with assign. Same lock and APIs. Promote confirm includes weeks until the mortality threshold.

### Backend

| Piece | Location |
|-------|----------|
| Web | `GET /app/training` → `Web\TrainingController` |
| List | `GET /api/v1/training/trainers` |
| Configure / assign / unassign | `POST /api/v1/training/trainers/{id}/configure`, `/assign`, `/unassign` |
| Domain | `TrainingService` (weekly tick Thursday 10:00) |

Gold cost: none. Fatigue load and caps: [training-system.md](../systems/training-system.md).

---

## Sketch leftover

Not a contract: original sketch “View Trainer List” as a **separate** screen — trainers are this page’s first tab.

Batch training from the roster is **Cut**. Assign stays per trainer on this page and on hero-detail overview. Lock: [03-hero-roster.md — roster extras](03-hero-roster.md#locked-roster-extras).

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/training/index.html.twig` |
| Card / table | `templates/components/training/trainer_card.html.twig`, `active_trainees_table.html.twig` |
| Stimulus | `training`, `tab` |
