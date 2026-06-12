# Training System

Reference: [game-summary.md](../game-summary.md#25-training-system)

Purpose: Document training queues, cost calculations, trainer assignment, and server tick processing.

## Primary Attribute Range

All eight primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) use an integer range of **1–20** (inclusive) for heroes and trainers.

## Training Setup

Training is configured directly on the **Trainer** instead of individual training jobs. Each trainer acts as a training leader with a dynamic number of hero slots.

| Step | Action |
|------|--------|
| 1 | Configure the Trainer's training focus (Attribute, Magic, Form, or idle) |
| 2 | Assign one or more heroes to the Trainer's slots |

### Trainer & Slot Limits

- **Trainer Limit**: A team can have at most `2 + floor((trainingFacilityLevel - 1) / 2)` trainers.
- **Hero Slot Limit**: Each trainer can have at most `3 + floor((trainingFacilityLevel - 1) / 2)` assigned heroes.

### Lock Period

To prevent last-minute manipulation before weekly ticks, training configurations and assignments are **locked** during the following period:
- **Lock Start**: Wednesday at 12:00:00 (server local time)
- **Lock End**: Friday at 10:00:00 (when the weekly tick processes)

During the lock period, players cannot configure trainers, assign heroes, or unassign heroes.

## Trainer Rules

| Rule | Detail |
|------|--------|
| **Single Active Training** | A hero can be assigned to at most one Trainer. While assigned, the hero's status is set to `Training`. |
| **Attribute Cap** | A hero cannot train a primary attribute beyond the Trainer's frozen value for that attribute. |
| **No monetary cost** | Training has no Gold or Essence cost. It is instead balanced by a high fatigue load. |
| **Trainer aging** | Trainers age during each weekly tick by the same amount a hero would age from a combat death (applies to all races, including Undead). |

## Hero Creation Age

Newly created heroes (starting roster, summoning, NPC generation) start at **level 1** with age between their race's **Min Age** and **Max Junior Age** (inclusive). See [game-summary.md § Age System](../game-summary.md#age-system).

## Internal Stat Scaling & Diversity

Primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) are scaled internally:
- **Stored scale**: All stats are stored in the database scaled by 10 (range `10` to `200`).
- **External scale**: Displayed stats and requirement checks are floored: `floor(raw / 10)` (range `1` to `20`).
- **Diversity at roll**: Hero generator rolls 1-20 stats, multiplies them by 10, and adds a random offset `0-9` (capped at 200).

## Training Types & Weekly Tick Processing

Every Friday at 10:00, the server processes the training tick for all trainers that have a configured training type.

### 1. Attribute Training
Trains a primary attribute (STR, DEX, KON, SPD, INT, WIL, CHA, LCK).
- **Gain Calculation**:
  `Raw Gain (external scale) = ((Base Gain + Trainer Bonus + Difference Bonus) / Difficulty Factor) * (1 + Facility Efficiency) * Race Modifier`
  Where:
  - **Base Gain**: `1.0`
  - **Trainer Bonus**: `max(0, (TrainerStatExternal - 10) * 0.05)`
  - **Difference Bonus**: `max(0, (TrainerStatExternal - HeroStatExternal) * 0.05)`
  - **Difficulty Factor**: `1.0 + (HeroStatExternal / 5)^1.5`
  - **Facility Efficiency**: `FacilityLevel * 0.05` (from training facility)
  - **Race Modifier**: `training_speed_modifier` from `races.yaml`
  
  The final raw gain is:
  `Raw Gain (internal scale) = min(9, round(Raw Gain (external scale) * 10))`
  At least `1` raw point is gained if under the trainer's cap.
- **Fatigue Impact**: Adds **+20 fatigue** (capped at 100).

### 2. Magic Training
Increases a hero's Magic Capacity by 1 (capped at a maximum of 5 slots).
- **Fatigue Impact**: Adds **+20 fatigue** (capped at 100).

### 3. Form Training (Resting)
Helps a hero recover physical conditioning and rest.
- **Form Impact**: Increases form by **+20** (capped at 100).
- **Fatigue Impact**: Decreases fatigue by **-20** (floor at 0).

At the end of the tick, a historical `TrainingQueue` record with status `Completed` is persisted for logging purposes.

## APIs

All mutating routes validate the lock state.

- `GET /api/v1/training/trainers` — List team's trainers, current configurations, hero slot occupancy, limits, and team lock status.
- `POST /api/v1/training/trainers/{id}/configure` — Configure trainer focus (request parameters: `type`, `attribute`).
- `POST /api/v1/training/trainers/{id}/assign` — Assign a hero to a trainer's slot (request parameter: `hero_id`).
- `POST /api/v1/training/trainers/{id}/unassign` — Unassign a hero from a trainer (request parameter: `hero_id`).


