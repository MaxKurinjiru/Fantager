# Crafting System

Reference: [game-summary.md](../game-summary.md#217-crafting-system) and [20-crafting.md](../screens/20-crafting.md)

Purpose: Detail crafting mechanics, recipes, and queue processing.

## Crafting Entities

The crafting system is powered by two main database entities: `CraftingRecipe` and `CraftingQueue`.

### 1. CraftingRecipe Entity
Defines the input requirements, cost, and outputs of crafting jobs.
* **resultItemCategory** (`ItemCategory` enum): Weapon, Shield, Spell Accelerator, Armor, Accessory, or Material.
* **resultItemRarity** (`ItemRarity` enum): Common, Uncommon, Rare, Epic, Legendary, or Mythic.
* **requiredMaterials** (`json` array): List of materials and quantities required for the recipe.
* **essenceCostType** (`ItemRarity` enum, nullable): The tier of essence consumed.
* **essenceCostAmount** (`int`): Amount of essence required.
* **goldCost** (`int`): Gold required to start crafting.
* **successRateBase** (`decimal`): Chance of success (default `1.00`).
* **craftingTime** (`int`): Time required for crafting in seconds.
* **requiredForgeLevel** (`int`): Minimum Forge facility level required (default `1`).

### 2. CraftingQueue Entity
Manages the active crafting jobs scheduled by teams.
* **team** (`Team` relation): The team executing the job.
* **recipe** (`CraftingRecipe` relation): The recipe being crafted.
* **status** (`CraftingStatus` enum): `pending`, `in_progress`, `completed`, `failed`, or `cancelled`.
* **startedAt** (`DateTimeImmutable`): When the crafting job was initiated.
* **completesAt** (`DateTimeImmutable`): When the crafting job will finish.

---

## Crafting Mechanics

1. **Initiation**: Starting a job validates that the team owns the required materials, gold, and essence, and checks that their headquarters has the required Forge facility level.
2. **Resource Consumption**: Gold, essence, and materials are deducted immediately upon starting.
3. **Queue Processing**: Jobs run asynchronously based on `startedAt` and `completesAt`. A tick worker updates the job status on completion, executing a success check against `successRateBase`.
4. **Resolution**: On success, the crafted item is added to the team's inventory. On failure, resources are lost.

---

## Implementation Status

- **Service**: `App\Service\Crafting\CraftingService` — start/cancel jobs, process due queue entries.
- **CLI**: `bin/console app:process-crafting-queue` — run from cron or manually after `completesAt`.
- **API**: `App\Controller\Api\V1\CraftingController` — recipes, queue, start, cancel.
- **Web UI**: Planned (`/crafting` screen); use API until Phase 7 UI is built.

---

## APIs (v1)

* `GET /api/v1/crafting/recipes` — List available crafting recipes.
* `POST /api/v1/crafting` — Start a crafting job.
* `GET /api/v1/crafting/queue` — List active crafting jobs for the team.
* `DELETE /api/v1/crafting/queue/{id}` — Cancel a queued crafting job (refunds materials based on refund policy).

