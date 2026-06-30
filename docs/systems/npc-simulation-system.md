# NPC Simulation System

Reference: [NpcSimulationService.php](../../src/Service/Team/NpcSimulationService.php), [calendar-system.md](calendar-system.md), [team-system.md](team-system.md)

Purpose: Document autonomous behaviors of NPC teams including tactics, training, economy, and scheduler integration.

---

## 1. Economic Archetypes (Roles)

Every NPC team is assigned an archetype based on a deterministic modulo calculation of its database ID: `team_id % 4`. This role dictates priorities for facility upgrades and marketplace activity:

| Archetype | Upgrade Priority | Marketplace Buying Focus | Marketplace Selling Focus |
|:---|:---|:---|:---|
| **`mercenary_academy`** | Training -> Summoning -> Barracks | Combat heroes (prefer Orc/Dwarf/Human) | Combat heroes |
| **`veteran_guild`** | Training -> Treasury -> Medical | Trainers | Trainers |
| **`royal_collector`** | Arena -> Library -> Summoning | Epic/Legendary/Mythic gear, high-level heroes/trainers | Generic |
| **`scavenger_clan`** | Treasury -> Medical -> Training | Cheap items (< 150 gold) | Generic |

---

## 2. Match Tactics Simulation

Tactics simulation runs automatically for both teams during the `league_match` tick (prior to match resolution).
- **Lineup Selection:** Evaluates available heroes for a 3-3 formation. Scores front-row performance (based on STR + KON) and back-row performance (based on INT + SPD + DEX).
- **Form & Fatigue Adaptation:** Readiness is penalized if fatigue > 50 (50% penalty) or form < 40 (40% penalty).
- **Trait-Aware Scoring:** Adjusts candidates' scores based on traits (e.g. `Berserker` or `BattleHardened` boost front-row score by +20%, `Glasscannon` boosts back-row score by +20% and penalizes front-row placement, and purely negative traits like `Volatile` penalize both).
- **Auto-Equip:** NPC teams automatically scan their unequipped inventory and equip the best gear matching the active lineup's preferred weapon and armor masteries.

---

## 3. Training Simulation

Training setups for NPC teams are simulated on **Tuesday 00:00:00 (during Daily Reset)**, exactly 12 hours before the training lock begins (Tuesday 12:00:00).
- **Trainer Promotion:** Promotes the oldest and highest-level eligible combatants to fill empty trainer slots (excluding purely negative-trait heroes unless desperate).
- **Trainer Focus Config:** Configures trainer specializations (training type and target attributes) matching the team's economic role.
- **Trainee Allocation:** Assigns combatants to trainers up to their slot limits, prioritizing those with the `QuickLearner` trait first.
- **Fairness Guarantee:** Because this runs before the lock starts, a player taking over an NPC team mid-week inherits a fully configured and active training setup instead of an empty or outdated queue.

---

## 4. Roster & Economy Simulation

To mimic realistic player progression and preserve a stable NPC budget, non-tactical decisions are split between daily resets, twice-weekly marketplace ticks, and weekly resets.

### Daily Actions (Daily Reset - 00:00:00)
- **Proactive Dismissal:** Finds and dismisses non-trainer, low-level heroes carrying purely negative traits (`Slacker`, `Volatile`, `Fragile`, `GlassJaw`) to prevent them from degrading team performance.
- **Roster Recycling:** If the roster is full and at least one hero is listed for sale, dismisses the worst available combatant to make room for a new summon.
- **Summoning:** Summons a new hero if the weekly cycle limit is not reached and gold permits (cooldown cost + 150 safety buffer).

### Twice-Weekly Marketplace Actions (Tuesday & Friday - 00:00:00)
(`simulateMarketplaceActions` — called from `ProcessKingdomTicksHandler` on Tuesday and Friday at 00:00)

**Selling:** Lists at most 1 unequipped item and 1 surplus hero/trainer per tick.
- Hero/trainer candidates are sorted by descending sell priority: heroes with negative traits and/or low race compatibility with the team's arena theme are listed first.
- `veteran_guild` prefers selling trainers; all other roles prefer combatants.
- Items are selected by the cheapest available unequipped item (to free up inventory).

**Buying:** Evaluates all active listings in the same Kingdom and selects the single highest-scoring listing that fits within the team's budget (safety reserve = 2x weekly maintenance).

Score table (score `0` = not interested; higher score wins):

| Listing type | Archetype condition | Score |
|:---|:---|---:|
| Item — Epic/Legendary/Mythic | `royal_collector` | 100 |
| Item — any rarity | `royal_collector` | 10 |
| Item — price < 150 gold | `scavenger_clan` | 100 |
| Item — any price | `scavenger_clan` | 20 |
| Item — Epic/Rare & price < 300 | other roles | 30 |
| Hero (compatible race, preferred races*) | `mercenary_academy` | 100 |
| Hero (compatible race, other races) | `mercenary_academy` | 60 |
| Hero (compatible race) | `royal_collector` | 80 |
| Hero (compatible race) | other roles | 20 |
| Trainer | `veteran_guild` | 100 |
| Trainer | `royal_collector` | 80 |
| Trainer | other roles | 20 |

*`mercenary_academy` preferred races: Orc, Dwarf, Human. Heroes of incompatible race (outside team's race pool) always score 0.

### Weekly Actions (Weekly Reset - Sunday 23:59:00)
(`simulateWeeklyManagementAndEconomy` — called from `ProcessKingdomTicksHandler` at Sunday 23:59)

- **Headquarters Upgrades:** Upgrades a single facility if budget allows (respecting a safety reserve of 2x weekly maintenance). Candidates are sorted first by **archetype priority index** (role-specific facility order from `getFacilityPrioritiesForRole`), then by **current level ascending** (to balance equal-priority facilities). Keeps upgrades balanced: the level difference between the highest and lowest facilities after the upgrade must be ≤ 3.
