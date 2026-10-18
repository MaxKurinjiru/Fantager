# Training System

Reference: [product/overview.md](../product/overview.md#training)

Status: Live  
Screens: [05-training.md](../screens/05-training.md), [06-trainer-management.md](../screens/06-trainer-management.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none

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
- **Lock Start**: Thursday at 08:00:00 in the **kingdom timezone**
- **Lock End**: Thursday at 10:00:00 in the kingdom timezone (when the weekly training tick processes)

During the lock period, players cannot configure trainers, assign heroes, or unassign heroes.

## Trainer Rules

| Rule | Detail |
|------|--------|
| **Single Active Training** | A hero can be assigned to at most one Trainer. While assigned, the hero's status remains `Available` (training is a parallel activity, not a blocking state, similar to being assigned to a formation slot). A hero can still be selected for match lineups while training. |
| **Attribute Cap** | A hero cannot train a primary attribute beyond the Trainer's frozen value for that attribute. |
| **No monetary cost** | Training has no Gold cost. It is instead balanced by a high fatigue load. |
| **Trainer aging** | A trainer with at least one living trainee ages on the weekly tick by a share of the race span from minimum age to the mortality threshold (22 weeks at pace 1.00 from minimum age, before daily aging). Applies to every race, including Undead. A configured trainer with no living trainees does not take the jump. |
| **Auto-unequip on promotion** | When a combatant is promoted to trainer role (`TrainingService::promoteToTrainer`), all items equipped on that hero are automatically unequipped (set to `Available`). The same unequip step also applies when a trainer is listed for sale on the marketplace. |

## Hero Creation Age

Newly created heroes (starting roster, summoning, NPC generation) start at **level 1** with age between their race's **Min Age** and **Max Junior Age** (inclusive). See [hero-system.md](hero-system.md).

## Internal Stat Scaling & Diversity

Primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) are scaled internally:
- **Stored scale**: All stats are stored in the database scaled by 10 (range `10` to `200`).
- **External scale**: Displayed stats and requirement checks are floored: `floor(raw / 10)` (range `1` to `20`).
- **Diversity at roll**: Hero generator rolls 1-20 stats, multiplies them by 10, and adds a random offset `0-9` (capped at 200).

## Training Types & Weekly Tick Processing

Every Thursday at 10:00, the server processes the training tick for all trainers that have a configured training type.

### 1. Attribute Training
Trains a primary attribute (STR, DEX, KON, SPD, INT, WIL, CHA, LCK).
- **Gain Calculation**:
  `Raw Gain (external scale) = ((Base Gain + Trainer Bonus + Difference Bonus) / Difficulty Factor) * (1 + Facility Efficiency) * Race Modifier * Arena Bonus * Group Modifier * Training Speed * Trait Modifier`
  Where:
  - **Base Gain**: `1.0`
  - **Trainer Bonus**: `max(0.0, (TrainerStatExternal - 10) * 0.05)`
  - **Difference Bonus**: `max(0.0, (TrainerStatExternal - HeroStatExternal) * 0.05)`
  - **Difficulty Factor**: `1.0 + (HeroStatExternal / 5)^1.5`
  - **Facility Efficiency**: `training_efficiency_pct / 100` (passive bonus from Training facility, defaults to 0.03)
  - **Race Modifier**: `training_speed_modifier` from `races.yaml`
  - **Arena Bonus**: `1.10` (+10%) if hero race matches HQ arena optimization race; `1.05` (+5%) if race relationship >= 70; else `1.0`
  - **Group Modifier**: `1.0` when the trainee has no living partners. Otherwise `1.0 + (averageRelationship - 50) * 0.001`, where the average is **this hero's** race relationship with each other living trainee. Relationship 100 is +5%, 50 is neutral, 0 is -5%. A hostile partner lowers only the heroes who have that low relationship.
  - **Training Speed**: stored kingdom `training_speed` (copied from the pace preset)
  - **Trait Modifier**: `1.20` for QuickLearner (+20%), `0.85` for Slacker (-15%), `0.90` for Perfectionist (-10%)

  The final raw gain (internal scale x10) is:
  `Raw Gain (internal scale) = max(1, min(Attribute Cap, round(Raw Gain (external scale) * 10)))`
  Where **Attribute Cap** = `max(1, round((9 + max(0, TrainerStatExternal - 10)) * Training Speed))`. A trainer at 10 or below keeps the old ceiling of 9 raw (0.9 displayed) at pace 1.0. Each point above 10 raises that ceiling by 1 raw before pace, so a stat-20 trainer caps at 19 raw.
  Capped so raw stat does not exceed trainer's raw stat cap.
- **Fatigue Impact**: Adds **+20 fatigue** (capped at 100) only when `gainRaw > 0` (no fatigue when already at the trainer’s raw-stat cap).

### 2. Magic Training
The trainer’s `targetAttribute` stores a magic school (`fire`, `water`, `air`, `earth`, `light`, `dark`). Each completed week grants school XP of `max(1, round(5 × (max(INT, WIL) / 10) × training_speed))` (`TrainingService::MAGIC_TRAINING_SCHOOL_XP` is the base 5, for a trainer whose higher of INT and WIL is 10). That XP counts as exercise for school decay. Weapon attunement is unchanged.
Increases a hero's Magic Capacity by `max(1, round(1 × training_speed))` (capped at a maximum of 5 slots). At training speed `1.0` this is +1 per week. Capacity gain does not use the trainer's INT or WIL.
- **Fatigue Impact**: Adds **+20 fatigue** when capacity increased. Once capacity is already 5, school XP still applies and adds **+10 fatigue** (`MAGIC_AT_CAP_FATIGUE`). No school and no capacity gain adds no fatigue. Fatigue is **not** scaled by game speed or by the trainer's magic stat.

Every hero the weekly tick actually processes (attribute, magic, or form, including a zero stat gain) also receives **8** base hero XP (`HeroProgressionService::TRAINING_PARTICIPATION_XP`). Library `xp_gain_pct` and `Kingdom.xp_modifier` multiply it the same way as match XP, and stored level follows the same curve and cap of 20.

### 3. Form Training (Resting)
Helps a hero recover physical conditioning and rest.
- **Form Impact**: Increases form by `round(20 × training_speed × (KON / 10))` (capped at 100). KON 10 restores 20 at pace 1.0. KON 20 restores 40.
- **Fatigue Impact**: Decreases fatigue by `round(20 × (KON / 10))` (floor at 0). Fatigue reduction uses the trainer's KON and is **not** scaled by game speed.

A trainer with at least one living trainee ages on this tick by `round(((mortality_threshold - min_age) / 22) × aging_speed × 10)` raw tenths. The 22 weeks are two seasons at pace 1.00 for a trainer who starts at the race minimum age, ignoring daily aging. A configured trainer with no living trainees does not take this jump. The jump uses the stored aging pace, not training speed. The trainer card and the promote confirm show calendar weeks until the mortality threshold, and that count also includes seven daily age increments for every race except Undead.

At the end of the tick, a `HeroTrainingHistory` row is persisted for each trained hero (append-only log for hero activity and calendar history).

## APIs

All mutating routes validate the lock state.

- `GET /api/v1/training/trainers` — List team's trainers, current configurations, hero slot occupancy, limits, and team lock status.
- `POST /api/v1/training/trainers/promote` — Promote a hero to trainer (request parameter: `hero_id`).
- `POST /api/v1/training/trainers/{id}/configure` — Configure trainer focus (request parameters: `type`, `attribute`).
- `POST /api/v1/training/trainers/{id}/assign` — Assign a hero to a trainer's slot (request parameter: `hero_id`).
- `POST /api/v1/training/trainers/{id}/unassign` — Remove assignment of hero from trainer (request parameter: `hero_id`).
- `POST /api/v1/training/trainers/{id}/dismiss` — Dismiss trainer for partial compensation.


