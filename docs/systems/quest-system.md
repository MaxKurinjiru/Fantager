# Quest System

Reference: [game-summary.md](../game-summary.md#216-quest-system)

Purpose: Detail quest mechanics, requirements, rewards, and progression flows.

## Quest Entities

The quest system uses two main entities: `Quest` (static definitions or generated runs) and `PlayerQuestProgress` (tracking individual team progress).

### 1. Quest Entity
Defines the structure, requirements, and rewards of a quest.
* **kingdom** (`Kingdom` relation): The kingdom/server where the quest is active.
* **type** (`QuestType` enum): `daily`, `weekly`, `story`, or `repeatable`.
* **title** (`string`): The name of the quest.
* **description** (`string`): Description text.
* **rewards** (`json` array): Rewards granted on completion (Gold, Crystals, Essence, Items).
* **requirements** (`json` array): Conditions required to complete or accept the quest.
* **expiresAt** (`DateTimeImmutable`, nullable): Expiration date/time for timed quests.

### 2. PlayerQuestProgress Entity
Tracks a team's progress toward completing a quest.
* **team** (`Team` relation): The team attempting the quest.
* **quest** (`Quest` relation): The quest being tracked.
* **status** (`QuestProgressStatus` enum): `in_progress`, `completed`, `failed`, or `expired`.
* **progress** (`int`): Current progress counter.
* **completedAt** (`DateTimeImmutable`, nullable): When the quest was successfully completed.

---

## Quest Mechanics

1. **Acceptance**: A player views available quests and accepts them, creating a `PlayerQuestProgress` entry.
2. **Progress Updates**: Gameplay events (combat wins, item crafting, training completions) publish updates that increment progress on active quests matching criteria.
3. **Claiming**: Once progress reaches the requirement, the team can submit/claim the rewards, updating `status` to `completed` and crediting rewards.

---

## APIs (v1)

* `GET /api/v1/quests` — Retrieve a list of available and active quests for the team.
* `POST /api/v1/quests/{id}/accept` — Accept a quest.
* `POST /api/v1/quests/{id}/claim` — Claim rewards for a completed quest.

