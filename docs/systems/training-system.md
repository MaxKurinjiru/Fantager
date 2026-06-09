# Training System

Reference: [game-summary.md](../game-summary.md#25-training-system)

Purpose: Document training queues, cost calculations, trainer assignment, and server tick processing.

## Primary Attribute Range

All eight primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) use an integer range of **1–20** (inclusive) for heroes and trainers.

## Attribute Training Setup

Primary attribute training is configured **per job**, not per Trainer conversion:

| Step | Action |
|------|--------|
| 1 | Select **one primary attribute** to train |
| 2 | Optionally assign a **Trainer** |
| 3 | Assign **one or more heroes** to the job |

Each assigned hero creates a `TrainingQueue` row sharing the same `target_attribute` and `trainer_id`. There is no permanent specialty on the Trainer entity.

## Trainer Rules

| Rule | Detail |
|------|--------|
| **Per-job attribute** | Each training setup trains exactly one primary attribute |
| **Cap** | With a Trainer assigned, the hero cannot exceed the Trainer's frozen value for **that job's target attribute** |
| **Flexible reuse** | The same Trainer can lead different attribute jobs in different setups *(e.g., STR this week, KON next week)* |
| **No trainer** | Training without a Trainer uses only the global 1–20 cap |
| **Frozen stats** | Trainer attribute values are frozen at conversion and do not change |

> *Example: Trainer with STR 18 and KON 16 leads a STR job — assigned heroes cap at STR 18. The same Trainer can later lead a KON job capped at 16.*

## Hero Creation Age

Newly created heroes *(starting roster, summoning, NPC generation)* start at **level 1** with age between their race's **Min Age** and **Max Junior Age** (inclusive). See [game-summary.md § Age System](../game-summary.md#age-system).

## Internal Stat Scaling & Diversity
Primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) are scaled internally:
- **Stored scale**: All stats are stored in the database scaled by 10 (range `10` to `200`).
- **External scale**: Displayed stats and requirement checks are floored: `floor(raw / 10)` (range `1` to `20`).
- **Diversity at roll**: Hero generator rolls 1-20 stats, multiplies them by 10, and adds a random offset `0-9` (capped at 200).

## Training Effectiveness Formula
Attribute training processes weekly (Friday at 10:00). Raw stat gains per tick are calculated:

```
Raw Gain (external scale) = ((Base Gain + Trainer Bonus + Difference Bonus) / Difficulty Factor) * (1 + Facility Efficiency) * Race Modifier
```

Where:
- **Base Gain**: `1.0`
- **Trainer Bonus**: `max(0, (TrainerStatExternal - 10) * 0.05)` (0 if no trainer)
- **Difference Bonus**: `max(0, (TrainerStatExternal - HeroStatExternal) * 0.05)` (0 if no trainer)
- **Difficulty Factor**: `1.0 + (HeroStatExternal / 5)^1.5`
- **Facility Efficiency**: `FacilityLevel * 0.05` (from training facility)
- **Race Modifier**: `training_speed_modifier` from `races.yaml`

The final raw gain is:
```
Raw Gain (internal scale) = min(9, round(Raw Gain (external scale) * 10))
```
At least `1` raw point is always gained if under the cap.

The gain is capped at the trainer's raw stat (or `200` if no trainer is assigned).

## Server Tick Processing
Every Friday at 10:00, the tick processes all pending `training_queue` rows where `execute_at <= now`.
- Marks job as `in_progress`.
- Calculates and applies raw gains for:
  - **Attribute**: applies formula, caps at trainer's raw stat, increases hero raw stat, logs `stat_gain`.
  - **Magic**: increases magic capacity by 1 (max 5).
  - **Form**: increases form (e.g. +20, max 100).
- Sets job status to `completed` and `completed_at = now`.
- Restores hero status to `available` if no other pending traning jobs.

## APIs
- `GET /api/training-queue` — list queued jobs
- `POST /api/training-queue` — queue a training job (validates `heroRawStat < trainerRawStat`)
- `DELETE /api/training-queue/{id}` — cancel a pending job

