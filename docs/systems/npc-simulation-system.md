# NPC Simulation System

Reference: [NpcSimulationService.php](../../src/Service/Team/NpcSimulationService.php) (facade), [NpcTacticsSimulator](../../src/Service/Team/NpcTacticsSimulator.php), [NpcTrainingSimulator](../../src/Service/Team/NpcTrainingSimulator.php), [NpcEconomySimulator](../../src/Service/Team/NpcEconomySimulator.php), [calendar-system.md](calendar-system.md), [team-system.md](team-system.md)

Status: Live (tick-driven)  
Screens: N/A · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none

Purpose: Document autonomous behaviors of NPC teams including tactics, training, economy, and scheduler integration.

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
- **Lineup Selection:** Rewrites the team's **default** formation only. NPC fixtures are not given a separate assignment, so kickoff uses that default. Scores live in `FormationLineupScorer` (same formula as player fill-from-roster): front STR + KON, back INT + SPD + DEX, plus form/fatigue/trait modifiers below. A full rewrite picks the six combatants with the highest `max(front, back)`, then puts the three with the largest `front − back` delta in front (rest in back). Kickoff hole-fill for a short default still fills empty fronts first, then backs; ties by `id` ascending.
- **Form & Fatigue Adaptation:** Readiness is penalized if fatigue > 50 (50% penalty) or form < 40 (40% penalty).
- **Trait-Aware Scoring:** Adjusts candidates' scores based on traits:
  - `Berserker`, `BattleHardened`, and `Overconfident` boost front-row score by +20% (multiplier `1.20`).
  - `Glasscannon` and `Reckless` boost back-row score by +20% (multiplier `1.20`) and penalize front-row placement by -15% (multiplier `0.85`).
  - `Clutch` boosts front-row score by +10% (multiplier `1.10`).
  - `Fragile` and `GlassJaw` penalize front-row placement by -20% (multiplier `0.80`).
  - Purely negative traits (`Slacker`, `Fragile`, `GlassJaw`) heavily penalize both front and back row placement by -90% (multiplier `0.10`) to prefer benching them. Volatile is a mixed trait and is not in this list.
- **Live L1 tactics:** Writes the same schema as the player editor (`movement_goal`, `target_order`, `fallback`, `inherit_*`) plus formation `tactics_defaults`. Combat does not invent movement for rows without `movement_goal`, so NPC slots store a concrete goal: ranged/caster weapons `seek_cover_ranged`; veteran front `protect_backline`; mercenary back melee `flank_rear`; otherwise `advance_to_melee`. Aggressive/balanced teams store the approach slot order and a role fallback (`lowest_hp` / `highest_threat` / `approach`). Defensive (veteran) omits `target_order` so engine L0 defensive targeting stays. Approach is still `aggressive` / `defensive` / `balanced` by archetype. Dummy keys (`aggression`, `heal_trigger_pct`) are not written.
- **Spells:** Learns and equips spells for the lineup, then writes L2 `spell_priorities` (`always` / `ally_hp_below`). Restore threshold follows the approach (25 / 40 / 50) except royal collector, which keeps 50.
- **Auto-Equip:** Scans unequipped inventory for the lineup's preferred weapon and armor masteries. A matching equipped item is replaced when the bag has a strictly higher rarity of the same subtype. Otherwise the best matching bag item is equipped, then a basic purchase fills empty/common mismatches (150 gold reserve).

---

## 3. Training Simulation

Training setups for NPC teams are simulated on **Tuesday 00:00:00 (during Daily Reset)**, well before the training lock begins (Thursday 08:00:00).
- **Trainer Promotion:** Promotes at most **one** combatant, and **only when the team has zero live trainers** (including heroes listed for sale). Does not fill the HQ trainer cap. Remaining target slots are left for marketplace buys so the trainer market does not die after week 1. With more than 6 combatants, skips the top 6 by cached `complex_rating` (then level, then id) so a starter is not converted. Among the rest, picks the highest **raw training-focus stat** (role template + lineup weapon attr: mercenary STR/SPD, veteran KON/WIL, collector INT/WIL, scavenger SPD/LCK; plus DEX/INT when the default lineup is ranged/caster), then age, then level. `complex_rating` is not used for trainer quality. Excludes purely negative-trait heroes unless desperate. Uses shared `TrainingService::applyTrainerPromotion()` to unequip items, remove from active formations, and record `trainer_promoted` in the team chronicle.
- **Trainer Focus Config:** Starts from the role template, prepends the default lineup's dominant weapon attribute (STR melee, DEX bow/crossbow/dagger, INT wand/staff), and appends Form. Duplicates are dropped. Weapon mastery XP still comes from combat — training has no mastery type.
- **Trainee Allocation:** Assigns combatants to trainers up to their slot limits, prioritizing those with the `QuickLearner` trait first.
- **Fairness Guarantee:** Because this runs before the lock starts, a player taking over an NPC team mid-week inherits a fully configured and active training setup instead of an empty or outdated queue.

---

## 4. Roster & Economy Simulation

To mimic realistic player progression and preserve a stable NPC budget, non-tactical decisions are split between daily resets, twice-weekly marketplace ticks, and weekly resets.

### Daily Actions (Daily Reset - 00:00:00)
- **Combatant keep floor:** NPC teams aim to keep **8** live combatants (6 lineup + short bench). Summoning, recycling, and most hero buys/sales are gated on this floor so the roster does not churn every tick.
- **Proactive Dismissal & Trainer Promotion:** Evaluates non-trainer available heroes carrying purely negative traits (`Slacker`, `Fragile`, `GlassJaw`). **Keeps** the hero when `complex_rating` is at least **1.25×** the median of the other combatants (median > 0). If a negative-trait hero has a raw attribute stat >= 75% of the team's highest combatant stat (min 5.0) **and the team has zero live trainers**, they are promoted to a **Trainer** first (using `TrainingService::applyTrainerPromotion()`). Otherwise, they are dismissed to prevent degrading team performance. Volatile is not in this list. Does not fill the HQ trainer cap.
- **Roster Recycling:** If combatants are **above** the keep floor, the roster is full, and at least one hero is listed for sale, dismisses the worst available combatant by `complex_rating` **only when that rating is below 0.75× team combatant median** (skipped when median is 0).
- **Summoning:** Summons a new hero via `SummoningService` (`getStatus` then `summon`) only if combatant count is **below 8**, the weekly cycle limit is not reached (`summons_used < summons_max`), and gold permits (summoning cost + 150 gold safety buffer). Race pick uses the same compatible pool as players ([summoning-system.md](summoning-system.md#locked-genie-summon-pool)).

### Twice-Weekly Marketplace Actions (Tuesday & Friday - 00:00:00)
(`simulateMarketplaceActions` — called from `ExecuteSingleTickHandler` on Tuesday and Friday at 00:00)

**Selling:** Lists a random number of unequipped items (`0` to `3`), surplus **available** combatants who are **not** among the team's best 8 by `complex_rating` (capped by keep-floor surplus and the role sell limit), and trainers who are **not** the specialist for a training-focus stat (capped by surplus and the role sell limit). Remaining surplus combatants are listed worst `complex_rating` first; surplus trainers worst focus-stat first.

**Trainer targets** (capped by HQ `getTrainerLimit`): `veteran_guild` / `royal_collector` keep **2**; `mercenary_academy` / `scavenger_clan` keep **1**. Internal promotion never fills the extra veteran/collector slot — that slot is for buying.
- Hero/trainer candidates are sorted by descending sell priority: heroes with negative traits and/or low race compatibility with the team's arena theme are listed first.
- Unequipped items to sell are selected from those exceeding a random safety reserve of `1` to `2` items *per slot type*.
- Sellable excess items from all slots are sorted by rarity ascending (common first) and then by ID ascending (oldest first) to list lower-value/older inventory first. Item gold uses `ItemService::estimateMarketPrice()` (catalog commons: 70% of merchant cost; otherwise rarity table). Heroes/trainers use `HeroRatingCalculator::estimateMarketPrice()`. NPC lists at suggested, so they stay inside the player listing band ([marketplace-system.md](marketplace-system.md#locked-listing-price-band)).

**Role-specific marketplace limits:**
- `mercenary_academy`:
  - Buy limit: 1-3 heroes, 1 trainer
  - Sell limit: 1-3 heroes, 1 trainer
- `veteran_guild`:
  - Buy limit: 1 hero, 1-3 trainers
  - Sell limit: 1 hero, 1-3 trainers
- `royal_collector`:
  - Buy limit: 1-2 heroes, 1-2 trainers
  - Sell limit: 1-2 heroes, 1-2 trainers
- `scavenger_clan`:
  - Buy limit: 1-2 heroes, 1 trainer
  - Sell limit: 1-2 heroes, 1 trainer

**Buying:** Evaluates all active listings in the same Kingdom and buys them separately:
1. **Trainers:** First (so the per-tick spend cap does not starve trainer demand). Buys only while live trainers are **below the role target**, scored > 0, up to the role's buy limit. Quality is the listing's **raw stats for the team's training focuses**, not `complex_rating`. With no trainer, any listing fills the slot. With trainers already, the listing must either **cover an uncovered focus** (that raw stat > 0 while current coverage is 0) or beat current coverage of a focus by **1.10×**. `buyListing` still rejects at the HQ trainer cap.
2. **Items:** Capped by Tier (T1: max 4/tick, T2: max 2/tick, T3: max 1/tick). NPC teams buy listings scored > 0 representing a useful upgrade for a hero in their active lineup, matching masteries. For T3 teams, single item price is capped at 1 500 gold.
3. **Heroes:** Buys combatant heroes scored > 0 up to the role's buy limit, and only while live combatants are below 8 (mercenary_academy: below 10). Below the keep floor, listing `complex_rating` must be at least **0.75×** team median (median 0 = fill). At or above keep, the listing must be at least **1.10×** the worst current combatant (a true upgrade). Compatible-race scoring uses `SummoningService::getCompatibleRacesForTeam()` (Genie adaptation ≥ 80 → Genie / Human / Elf). Trainers are not race-filtered.

**Tier-aware Budget Controls:**
- **Gold Safety Reserve:** T1 requires `2.0x weekly maintenance`, T2 requires `3.5x weekly maintenance`, T3 requires `5.0x weekly maintenance + 2 000 gold base floor`.
- **Per-Tick Spend Cap:** Maximum gold spent on marketplace per tick is capped by Tier percentage of current gold (T1: 50%, T2: 35%, T3: 20%).

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

*`mercenary_academy` preferred races: Orc, Dwarf, Human. Heroes of incompatible race (outside the team's race pool) always score 0. NPC hero buys and daily summons share the player summoning pool ([summoning-system.md](summoning-system.md#locked-genie-summon-pool)).

### Weekly Actions (Weekly Reset - Sunday 23:59:00)
(`simulateWeeklyManagementAndEconomy` — called from `ExecuteSingleTickHandler` at Sunday 23:59)

- **Arena Theme Optimization:** Automatically changes the team's arena race optimization (in HQ) to match the current roster distribution if not locked:
  - `mercenary_academy`: Chooses the most common race in the team among Orc, Dwarf, and Human (falls back to Orc if none are present).
  - Other archetypes: Chooses the race that has the largest number of alive heroes in the team.
- **Headquarters Upgrades:** Upgrades a single facility if budget allows (respecting a safety reserve of 2x weekly maintenance). Candidates are sorted first by **archetype priority index** (role-specific facility order from `getFacilityPrioritiesForRole`), then by **current level ascending** (to balance equal-priority facilities). Keeps upgrades balanced: the level difference between the highest and lowest facilities after the upgrade must be ≤ 3.

Operator multi-season runs (NPC archetypes in `season_report.payload.npc`): `scripts/simulate-kingdom.sh` (`simulate:init` / `advance` / `process`). [local-setup.md](../guides/local-setup.md#multi-season-balance--npc-simulation).
