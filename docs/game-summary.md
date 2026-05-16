# Core Systems Summary (Complete Version)

## Game Premise

Players take on the role of **arena managers** in a persistent fantasy world. They recruit heroes from 8 distinct races, train and equip them, assemble tactical formations, and lead their teams into asynchronous turn-based battles against other managers. Success depends on strategic team building, resource management, and competitive league performance — not the manager's own combat ability.

## Table of Contents

- [2.1 Kingdom System & Server Split](#21-kingdom-system--server-split)
- [2.2 Event System](#22-event-system)
- [2.3 Economy System](#23-economy-system)
- [2.4 Hero System](#24-hero-system)
- [2.5 Training System](#25-training-system)
- [2.6 Team System](#26-team-system)
- [2.6.1 Formation System](#261-formation-system)
- [2.7 Headquarters System](#27-headquarters-system)
- [2.8 Item System](#28-item-system)
- [2.9 Spell System](#29-spell-system)
- [2.10 Combat System](#210-combat-system)
- [2.11 Marketplace System](#211-marketplace-system)
- [2.12 League System](#212-league-system)
- [2.13 Graveyard System](#213-graveyard-system)
- [2.14 Community System](#214-community-system)

## 2.1 Kingdom System & Server Split

### Concept

- Each **Kingdom** is a separate server instance with its own player base, economy, and events
- Players select or are assigned a Kingdom (server) at **account creation**; all gameplay, economy, and events are **isolated per Kingdom**

> *Example: A player joining "Kingdom of Ashenvale" will never interact with players, items, or marketplace listings from "Kingdom of Ironforge."*

### Kingdom-Specific Global Settings

| Setting | Purpose |
|:---|:---|
| **Main Language** | Primary interface language for the kingdom/server |
| **Time Zone** | Server time zone affecting event schedules and daily resets |
| **Game Speed** | Adjusts tick rates for training, fatigue recovery, aging, and event duration |
| **Starting Resources** | Initial gold, items, and heroes given to new players |
| **Marketplace Tax Rate** | Kingdom-wide transaction fee percentage |
| **Season Length** | Duration of league seasons and event cycles |
| **Max Player Capacity** | Server population cap for performance and community balance |
| **Kingdom Theme/Lore** | Unique narrative, visuals, and special events tied to the kingdom's identity |
| **Level Cap** | Max level restrictions for heroes in the kingdom |
| **XP Modifier** | Global multiplier for experience gain |
| **Crafting Boost** | Global modifier for crafting success rates and material yields |

> *Example: A testing kingdom might use Game Speed 2x and XP Modifier 1.5x to accelerate progression for beta testing.*

---

## 2.2 Event System

### Concept

- **Dynamic world events**, seasonal activities, and limited missions
- Heroes participating gain **XP**, level up, and improve stats
- **Fatigue/form** tracked to prevent overuse

### Weekly Calendar (Server Ticks)

The game is driven by calendar events on **weekly cycles**. Each Kingdom/server runs scheduled tasks (*server ticks*) at fixed times (server-local timezone).

| Tick Event | Category |
|:---|:---|
| **Fatigue and form recovery** | Hero Maintenance |
| **League match processing** | Competition |
| **Dungeon matches** | PvE |
| **Friendly matches** | Social |
| **Update team morale** based on performance | Team Maintenance |
| **Distribute arena ticket revenue** | Economy |
| **Process training queues** | Progression |
| **Process crafting queues** | Progression |
| **Update hero aging** | Hero Lifecycle |
| **Marketplace listing expiration** | Economy |
| **Marketplace auction processing** | Economy |

> *All tick events run on the Kingdom's configured time zone and are affected by the Game Speed setting.*

---

## 2.3 Economy System

### Purpose

The economy provides resource management depth through multiple currencies, each with specific purposes and acquisition methods. Players must balance income sources with strategic spending to optimize team progression.

### Currencies

#### Gold (Primary Currency)

- **Earned from:**
  - Arena match ticket revenue (passive income based on arena capacity and team reputation)
  - League match rewards (scales with tier and position)
  - Selling items/heroes on marketplace
  - Daily login bonuses and quest completions
  - Event participation rewards
  - Combat victories
- **Spent on:**
  - Hero training costs (increases with hero level and stat tier)
  - Headquarters facility upgrades (exponentially expensive at higher levels)
  - Marketplace purchases (items and heroes)
  - Crafting materials
  - Formation slot unlocks and customization
  - Hero summoning chamber fees
  - Morale restoration items (emergency recovery)
- **Mechanics:** Primary economic driver; scales with team reputation and arena capacity; unlimited earning potential but balanced by scaling costs

#### Crystals (Premium/Event Currency)

- **Earned from:**
  - Event completions and dungeon clears
  - League season rewards (based on final position)
  - Achievement unlocks
  - Rare quest chains
  - **Supporter Contributions:** Optional one-time or recurring donations to support game development
- **Spent on:**
  - **Quality of Life Features:**
    - Additional formation slots (beyond base 2, up to 4–5 total)
    - Extra hero roster slots (increased storage capacity)
    - Cosmetic customizations (team emblems, headquarters themes, hero skins)
    - Chat badges and profile flair (supporter status, achievement badges)
    - Extended marketplace listing duration
    - Additional saved item/equipment loadouts per hero
  - **Convenience (Non-Competitive):**
    - Batch training queue expansion (more simultaneous training actions)
    - Auto-sell filters for marketplace management
    - Advanced statistics and analytics dashboards
    - Priority customer support
  - **NOT Available for Crystals:**
    - ❌ Direct stat boosts or power increases
    - ❌ Instant training completion or progression shortcuts
    - ❌ Exclusive powerful items or heroes
    - ❌ Any gameplay advantages in combat or competition
- **Mechanics:**
  - Limited availability through gameplay creates scarcity
  - Account-bound (cannot be traded on marketplace)
  - Designed to reward active participation, achievement, and voluntary support
  - Supporter contributions grant badge tiers (Bronze/Silver/Gold/Platinum) based on contribution level
  - Badge tiers unlock cosmetic rewards and minor convenience features (no competitive advantages)

#### Essence (Crafting/Upgrade Currency)

- **Earned from:**
  - Dismantling items (returns Essence based on item rarity)
  - Combat victories and arena performance
  - Event dungeons and special challenges
  - Daily activity rewards
  - Kingdom events
- **Spent on:**
  - Item crafting (cost scales with rarity tier)
  - Item enchanting and upgrades
  - Magic school mastery training
  - Spell learning and spell slot expansion
  - Item durability restoration
- **Mechanics:** Rarity-specific variants (Common Essence, Uncommon Essence, Rare Essence, Epic Essence, Legendary Essence, Mythic Essence); prevents easy progression to high-tier items; different essence types cannot be converted

### Economic Sinks & Sources

| Gold Sinks | Gold Sources |
|:---|:---|
| **Training costs** — scales exponentially with hero level and attribute tier | **Arena ticket revenue** — passive income, scales with arena upgrades and team reputation |
| **Headquarters facility upgrades** — major long-term investment | **League match rewards** — tier-based: higher tiers = better rewards |
| **Marketplace transaction fees** — percentage-based, typically 5–15% | **Event completion bonuses** |
| **Formation unlocks** and strategic customization | **Marketplace sales** — minus transaction fees |
| **Hero maintenance** — form restoration, fatigue recovery items | **Daily and weekly quest chains** |
| **Summoning chamber usage fees** | **Combat victory bonuses** |

### Inflation Control Mechanisms

- **Marketplace Tax Rate:** Kingdom-specific percentage (typically 5–15%) applied to all sales
- **Diminishing Returns:** Repeated training actions within time windows yield reduced efficiency
- **Scaling Costs:** Higher-tier upgrades and items have exponentially increasing costs
- **Hero Maintenance Costs:** Fatigue recovery and form restoration create ongoing expenses
- **Time-Gated Income:** Arena revenue generated per cycle; limited daily quests prevent infinite farming
- **Level-Based Scaling:** Training and upgrade costs scale with hero level and facility tier

### Trading & Marketplace

- **Tradeable:** Heroes and items can be listed for Gold only
- **Non-Tradeable:** Crystals and Essence cannot be traded between players
- **Marketplace Fees:** Applied to sellers (buyers pay listed price + no additional fee)
- **Price Controls:** Suggested price ranges based on item rarity and hero stats (prevents extreme manipulation)
- **Kingdom-Isolated:** Each server maintains separate economy; no cross-kingdom trading
- **Listing Duration:** Items expire after set period (e.g., 7 days) if unsold
- **Anti-Sniping:** Auction-style or buy-now options based on item type

### Progression Scaling

| Phase | Level Range | Key Characteristics |
|:---|:---:|:---|
| **Early Game** | 1–20 | Gold abundant for basics; limited Crystal access; Common/Uncommon Essence available; focus on initial roster and headquarters |
| **Mid Game** | 21–50 | HQ upgrades become major gold sinks; Rare/Epic Essence needed; league becomes primary income; resource management critical |
| **Late Game** | 51+ | Massive gold investment for high-tier facilities; Legendary/Mythic crafting; arena revenue and league rewards sustain play |

> *Example: A mid-game player might spend 80% of weekly gold income on a single Headquarters facility upgrade, making league participation essential for sustained progression.*

### Anti-Exploitation Measures

- **Fatigue & Form Limits:** Prevent unlimited grinding and resource farming
- **Diminishing Returns:** Repeated activities within time windows yield reduced rewards
- **Account-Bound Crystals:** Event/achievement Crystals cannot be traded or transferred
- **Hero Aging System:** Prevents indefinite use of same roster without replacement costs
- **Activity Throttling:** Daily/weekly caps on certain high-reward activities
- **Price Floor/Ceiling:** Marketplace algorithms suggest fair prices; prevent extreme manipulation
- **Transaction Logging:** All trades tracked for anti-fraud monitoring

### Special Economic Events

- **Market Fluctuations:** Kingdom-wide events that temporarily adjust prices or rewards
- **Gold Rush Events:** Limited-time increased arena revenue or quest rewards
- **Crafting Festivals:** Reduced Essence costs or improved success rates for crafting
- **Tax Holidays:** Temporary marketplace fee reductions to stimulate trading
- **Resource Shortages:** Narrative events that increase certain costs while offering alternative rewards

---

## 2.4 Hero System

### Purpose

Heroes are the core units players manage, train, equip, and deploy. They are differentiated by **race**, **primary attributes**, and **secondary attributes**, with progression through XP and leveling.

### Core Mechanics

- **No Classes** — Heroes are defined by attributes and skills, not class archetypes
- **No Rarity** — All heroes are equally valuable; power comes from training and strategy

### Playable Races

| # | Race |
|---:|:---|
| 1 | **Human** |
| 2 | **Elf** |
| 3 | **Dwarf** |
| 4 | **Orc** |
| 5 | **Undead** |
| 6 | **Giant** |
| 7 | **Ent** |
| 8 | **Genie** |

*8 playable races with unique characteristics, restrictions, and bonuses.*

### Race Relationships

Each race has natural affinities and conflicts with other races, affecting **team dynamics** and **morale**. Relationship values range from **0–100**:

| Range | Tier | Description |
|:---:|:---|:---|
| **90–100** | Highly Positive | Strong chemistry, significant morale boost, enhanced combat synergy, mutual inspiration |
| **70–89** | Positive | Bonus chemistry, improved morale when paired, combat synergy bonuses |
| **50–69** | Neutral | No bonuses or penalties; heroes coexist without conflict |
| **21–49** | Negative | Reduced chemistry, morale penalties, potential performance conflicts |
| **0–20** | Hostile | Severe chemistry penalties, significant morale loss, refusal of cooperative actions |

### Relationship Matrix

| Race vs Race | Human | Elf | Dwarf | Orc | Undead | Giant | Ent | Genie |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Human** | 100 | 90 | 70 | 20 | 10 | 30 | 70 | 80 |
| **Elf** | 90 | 100 | 50 | 0 | 10 | 20 | 100 | 80 |
| **Dwarf** | 70 | 50 | 100 | 20 | 20 | 30 | 30 | 60 |
| **Orc** | 20 | 0 | 20 | 100 | 90 | 70 | 0 | 60 |
| **Undead** | 10 | 10 | 20 | 90 | 100 | 80 | 20 | 70 |
| **Giant** | 30 | 20 | 30 | 70 | 80 | 100 | 40 | 60 |
| **Ent** | 70 | 100 | 30 | 0 | 20 | 40 | 100 | 60 |
| **Genie** | 80 | 80 | 60 | 60 | 70 | 70 | 60 | 100 |

### Race Relationship Breakdown

- **Human** — *Highly Positive:* Elf (90) | *Positive:* Dwarf (70), Ent (70), Genie (80) | *Negative:* Giant (30) | *Hostile:* Orc (20), Undead (10)
- **Elf** — *Highly Positive:* Human (90), Ent (100) | *Positive:* Genie (80) | *Neutral:* Dwarf (50) | *Hostile:* Giant (20), Orc (0), Undead (10)
- **Dwarf** — *Positive:* Human (70) | *Neutral:* Elf (50), Genie (60) | *Negative:* Giant (30), Ent (30) | *Hostile:* Orc (20), Undead (20)
- **Orc** — *Highly Positive:* Undead (90) | *Positive:* Giant (70) | *Neutral:* Genie (60) | *Hostile:* Human (20), Dwarf (20), Elf (0), Ent (0)
- **Undead** — *Highly Positive:* Orc (90) | *Positive:* Giant (80), Genie (70) | *Hostile:* Dwarf (20), Ent (20), Human (10), Elf (10)
- **Giant** — *Positive:* Orc (70), Undead (80) | *Neutral:* Genie (60) | *Negative:* Human (30), Dwarf (30), Ent (40) | *Hostile:* Elf (20)
- **Ent** — *Highly Positive:* Elf (100) | *Positive:* Human (70) | *Neutral:* Genie (60) | *Negative:* Dwarf (30), Giant (40) | *Hostile:* Orc (0), Undead (20)
- **Genie** — *Positive:* Human (80), Elf (80), Undead (70), Giant (70) | *Neutral:* Dwarf (60), Orc (60), Ent (60)

> *Example: Pairing an Elf (90) with a Human on a team provides Highly Positive chemistry bonuses. Adding an Orc (0 with Elf) would create a Hostile relationship, severely impacting team morale.*

### Race Relationship Effects

| Tier | Effects |
|:---|:---|
| **Highly Positive (90–100)** | Strong team chemistry bonuses, significant morale boost, enhanced synergy in combat, mutual inspiration |
| **Positive (70–89)** | Bonus team chemistry, improved morale when paired, synergy bonuses in combat |
| **Neutral (50–69)** | No bonuses or penalties; heroes coexist without conflict |
| **Negative (21–49)** | Reduced team chemistry, morale penalties, potential performance conflicts |
| **Hostile (0–20)** | Severe chemistry penalties, significant morale loss, refusal of cooperative actions, reduced combat effectiveness |

**Additional Relationship Mechanics:**
- **Headquarters Optimization** — Applied bonuses benefit the chosen race and all races with *Positive* relationships (70+)
- **Relationship Impact** — Values affect hero interactions, group training efficiency, and formation effectiveness

### Primary Attributes

| Attribute | Abbreviation | Description |
|:---|:---:|:---|
| Strength | STR | Physical power and melee damage |
| Dexterity | DEX | Accuracy, dodging, reflexes |
| Constitution | KON | Health, resistance, endurance |
| Speed | SPD | Turn order and movement |
| Intelligence | INT | Spell power, learning speed, tactics |
| Willpower | WIL | Resistance to status effects and morale |
| Charisma | CHA | Leadership, trade efficiency, event influence |
| Luck | LCK | Critical hits, loot drops, event outcomes |

### Secondary Attributes

| Attribute | Description |
|:---|:---|
| Age | Heroes age over time; race-specific milestones determine performance and mortality |
| Form | Physical condition maintained by activity and training; declines with inactivity |
| Fatigue | Accumulated through actions; limits the number of available actions per period |
| Morale | Mental state affecting performance; influenced by victories, defeats, and team dynamics |

### Equipment & Magic Attributes

| Attribute | Description |
|:---|:---|
| Magic Capacity | Determines equipped spell slots (minimum 1, maximum 5); increases through training |
| School Mastery | Individual mastery per magic school (Fire, Water, Air, Earth, Light, Dark); determines maximum usable spell tier |

### Equipment Slots

| Slot | Type | Details |
|:---|:---|:---|
| **Main Hand** | Weapon | Single-handed weapon or dual-handed weapon (occupies both hands) |
| **Off-Hand** | Shield / Spell Accelerator / Weapon | Shield, Spell Accelerator, or second single-handed weapon (dual-wielding); *unavailable with dual-handed weapons* |
| **Armor** | 4 Slots | Head, Body (main armor), Hands, Feet |
| **Accessories** | 3 Slots | 1 Amulet + 2 Rings |

> *Example: A warrior wielding a dual-handed greataxe cannot equip a shield or off-hand item. A mage might use a single-handed wand + Spell Accelerator for casting speed.*

### Age System

Each race has specific age milestones that determine hero performance and lifespan.

#### Age Milestones

| Milestone | Description |
|:---|:---|
| **Minimum Age** | Lowest recruitment age for junior heroes |
| **Max Junior Age** | Maximum age for junior-tier heroes |
| **Prime Age Limit** | Maximum age for optimal training efficiency and peak performance |
| **Death Expectation** | Age threshold where mortality risk begins to increase significantly |

### Race Age Milestones

| Race | Min Age | Max Junior Age | Prime Age Limit | Death Expectation |
|:---|---:|---:|---:|---:|
| Human | 16 | 20 | 50 | 80 |
| Elf | 50 | 80 | 300 | 800 |
| Dwarf | 20 | 30 | 100 | 250 |
| Orc | 12 | 16 | 35 | 60 |
| Undead | 50 | 80 | 300 | 800 |
| Giant | 15 | 25 | 60 | 150 |
| Ent | 30 | 50 | 200 | 1000 |
| Genie | 100 | 150 | 500 | 2000 |

### Age Mechanics

| Age Phase | Condition | Effects |
|:---|:---|:---|
| **Junior** | Between Min Age and Max Junior Age | Recruited at random age in range; possess growth potential; bonus training efficiency |
| **Prime** | ≤ Prime Age Limit | Full training efficiency, peak performance, no age penalties |
| **Veteran** | > Prime Age, < Death Expectation | Gradually declining training efficiency, slight stat penalties, accumulated experience bonuses |
| **Elder** | ≥ Death Expectation | Significantly reduced training efficiency, increased permanent death risk per combat death |

*Undead do not age naturally; they only age through combat deaths. They use the same milestones as Elves.*

### Combat Death & Aging

| Mechanic | Description |
|:---|:---|
| **Auto-Resurrection** | Heroes who die in combat are **automatically resurrected** after the match ends, but suffer an age penalty |
| **Age Accumulation** | Each combat death adds years to the hero's age, *including Undead*. **Multiple deaths in the same match stack** — each subsequent death applies an escalating age penalty *(e.g., 1st death: +1 year, 2nd death: +2 years, 3rd death: +3 years)* |
| **Mid-Combat Revival** | A hero KO'd during combat can be revived mid-battle by a Light **Resurrection** spell, restoring them to partial HP so they can continue fighting. *This does not prevent the post-match age penalty — each KO still counts as a death for aging purposes* |
| **Revival Constraints** | **Once per match** — only one Resurrection can be cast per combat. Requires **Light School Mastery Tier 8+** and high INT. The **caster suffers –50% stats** for the remainder of the match (exhaustion). The **revived hero returns at 30% HP and 50% reduced form**, severely limiting their effectiveness for the rest of the battle and subsequent matches |
| **Mortality Threshold** | Heroes at or beyond Death Expectation face escalating permanent death chance per combat death. Multiple deaths in one match each trigger a separate mortality check |
| **Permanent Death** | Final removal — hero is placed in the Graveyard and cannot return |

> *Example: A Human hero (age 78, Death Expectation 80) dies in combat. Your Light mage (Mastery Tier 8) casts Resurrection — the hero revives at 30% HP with halved form, and the caster loses 50% of their stats for the rest of the match. The revived hero dies again later. No second Resurrection is possible (once per match). Post-match: the hero ages +1 year (1st death) and +2 years (2nd death) = total +3 years, pushing them to age 81 — past Death Expectation and at permanent death risk. The Light mage also carries fatigue into the next match from the casting exhaustion.*

### Morale System

**Individual Hero Morale** — Each hero has a morale value affecting combat performance, training efficiency, and resistance to negative effects.

**Team Morale** — A separate persistent value that changes gradually based on team performance and events *(not an average of individual morale)*.

#### Morale Influences

| Factor | Effect |
|:---|:---|
| **Match victories** | Increase both individual and team morale |
| **Match defeats** | Decrease both individual and team morale |
| **Death of team members** | Significantly reduces morale for surviving heroes and team morale |
| **High Charisma heroes** | Provide morale buffs to teammates and slow team morale decay |
| **Prolonged inactivity / repeated losses** | Cause morale decay over time |
| **Special events, items, kingdom bonuses** | Can boost morale |
| **Consistent individual morale** | Heroes with persistently high/low morale gradually pull team morale in that direction |

#### Morale Effects

| Morale Level | Effects |
|:---|:---|
| **High** | Bonus to damage, accuracy, and resistance |
| **Low** | Penalties to performance; increased chance of fleeing or refusing orders |
| **Team-wide** | Affects formation synergy and cooperative abilities |

> *Example: A team with 3 consecutive victories may see +15% morale boost. If their star hero dies in the 4th match, surviving heroes could drop 20–30% morale immediately.*

### Race Attributes & Bonuses

Each race has unique restrictions, bonuses, and training modifiers that define their playstyle and progression characteristics.

#### Race-Specific Restrictions & Equipment

| Race | Equipment Restrictions | Special Notes |
|:---|:---|:---|
| Human | None | Most versatile race; no restrictions |
| Elf | None | No restrictions; versatile equipment options |
| Dwarf | None | No restrictions; natural affinity for heavy armor |
| Orc | None | No restrictions; natural affinity for melee weapons |
| Undead | None | No restrictions; no benefit from healing spells (self-healing through drain/curse) |
| Giant | Off-hand restrictions | Cannot equip off-hand items (hands too large); main-hand only |
| Ent | **No armor or weapons** | Cannot equip any weapons, shields, or armor (body is natural armor); accessories only (Amulets, Rings) |
| Genie | None | No restrictions; magical versatility |

#### Race-Specific Stat Bonuses

Races receive inherent bonus multipliers to primary attributes, reflecting their natural strengths:

| Race | STR | DEX | KON | SPD | INT | WIL | CHA | LCK |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Human | 1.0x | 1.0x | 1.0x | 1.0x | 1.0x | 1.0x | 1.0x | 1.0x |
| Elf | 0.9x | 1.15x | 0.9x | 1.1x | 1.1x | 1.0x | 1.05x | 1.0x |
| Dwarf | 1.1x | 0.85x | 1.15x | 0.85x | 0.95x | 1.1x | 0.9x | 1.05x |
| Orc | 1.2x | 0.95x | 1.05x | 1.0x | 0.85x | 0.9x | 0.8x | 1.0x |
| Undead | 1.0x | 1.0x | 1.05x | 0.95x | 1.1x | 1.0x | 0.85x | 0.95x |
| Giant | 1.25x | 0.8x | 1.2x | 0.9x | 0.85x | 1.0x | 0.95x | 0.9x |
| Ent | 1.05x | 0.75x | **1.3x** | 0.7x | 1.15x | 1.15x | 1.05x | 0.9x |
| Genie | 0.95x | 1.1x | 0.95x | 1.15x | 1.25x | 1.1x | 1.2x | 1.15x |

**Ent Special Defensive Bonus:** Ents receive an additional **+15% Constitution bonus** due to their natural armor/bark, making their effective KON multiplier **1.3x** instead of the base calculation.

> *The Ent’s 1.3x KON is an additive bonus (+0.15) applied on top of their base Constitution multiplier, reflecting their bark-like natural armor.*

#### Race-Specific Training Modifiers

Races have inherent training speed multipliers based on their lifespan. Long-lived races train slower per real-time unit, while short-lived races progress faster:

| Race | Lifespan Category | Training Speed Modifier | Justification |
|:---|:---|:---:|:---|
| Human | Standard | 1.0x | Baseline training speed |
| Elf | Very Long | 0.6x | Extended lifespan means training is spread over centuries; slower per-cycle gains |
| Dwarf | Long | 0.85x | Long lifespan; moderate training slowdown |
| Orc | Short | 1.2x | Brief lifespan; accelerated training to achieve peak quickly |
| Undead | Very Long | 0.6x | Effectively infinite lifespan; training gains spread thin |
| Giant | Long | 0.8x | Long lifespan; slow but steady progression |
| Ent | Extremely Long | 0.5x | Millennia-long lifespan; training gains highly attenuated |
| Genie | Extremely Long | 0.5x | Timeless beings; training gains highly attenuated |

**Training Modifier Impact:**

- Modifier applies to **all attribute training**, magic mastery, and spell slot expansion
- Affects gold and essence costs *(lower cost × lower gains = time tradeoff)*
- Does **NOT** affect XP from combat or events

> *Example: An Elf training STR costs 60% of a Human's cost but gains 60% of the stat improvement per session — the cost-per-point is identical, but the Elf gains less per weekly cycle, extending their total progression timeline.*

#### Race-Specific Combat Bonuses

Beyond equipment and training, races have tactical advantages in combat:

| Race | Combat Bonus |
|:---|:---|
| Human | +5% XP gain from victories (adaptability) |
| Elf | +10% accuracy and dodge chance (natural grace) |
| Dwarf | +15% armor effectiveness and critical resistance (natural durability) |
| Orc | +20% melee damage against non-Orc enemies; –10% against Orc allies (bloodlust) |
| Undead | Immune to poison and disease; 50% reduced healing effectiveness (unliving) |
| Giant | +10% damage with main-hand weapons; cannot use off-hand (size limitation) |
| Ent | +20% Constitution defensive calculations; –20% speed-based actions (rooted nature) |
| Genie | +15% spell effectiveness and spell critical hit chance (magical essence) |

#### Race-Specific Restrictions Summary

| Race | Restriction | Details |
|:---|:---|:---|
| **Ent** | No armor or weapons | Cannot equip any weapons, shields, or armor (body is natural armor). Can equip **Amulets and Rings only** |
| **Giant** | No off-hand items | Cannot equip off-hand items due to hand size. No dual-wielding option |
| **Undead** | No external healing | Do not benefit from healing spells cast by others. Can only heal through life-drain spells, curse-based effects, or items |

### Leveling System

| Mechanic | Description |
|:---|:---|
| **XP Sources** | Heroes earn XP from all actions: training, battles, crafting, and events |
| **Level-Up Gains** | Leveling increases primary stats incrementally |
| **Progress Tracking** | XP progress tracked with `xp_to_next_level` field |
| **Starting Stats** | Influenced by race multipliers; all heroes start at the same base level but with race-adjusted attributes |

---

## 2.5 Training System

### Purpose

Training is the primary method for improving hero attributes, learning new abilities, and maintaining peak performance. The system balances strategic progression with resource management and time investment.

### Training Types

#### Attribute Training

| Aspect | Details |
|:---|:---|
| **Primary Stat Training** | Directly increases one of eight primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) |
| **Gold Cost** | Scales with hero level and current attribute value |
| **XP Gain** | Grants XP toward hero leveling |
| **Fatigue** | Accumulates based on training intensity |
| **Form** | Improves or maintains form when below peak condition |
| **Success Rate** | Affected by hero age, form, and facility level |
| **Efficiency** | Higher attribute values require more sessions for equivalent gains |

**Age Impact on Training:**

| Age Phase | Training Effect |
|:---|:---|
| **Junior** | Bonus efficiency and faster gains |
| **Prime** | Standard efficiency and consistent gains |
| **Veteran / Elder** | Reduced efficiency, slower gains, higher costs |

#### Magic Training

**Spell Slot Expansion** — Increases Magic Capacity from 1 to maximum of **5 slots**:

- Requires progressive training sessions at **Library/Academy**
- Cost increases exponentially with each slot *(1→2 is cheapest, 4→5 is most expensive)*
- Unlocked through hero level milestones and Intelligence threshold

**School Mastery Training** — Improves mastery in specific magic schools:

- Each school has independent mastery levels *(Tier 1–10)*
- Higher mastery unlocks access to more powerful spell tiers
- Requires **Essence** and **Gold**; cost scales with mastery tier
- Learning speed affected by Intelligence attribute and Library/Academy level

**Spell Learning** — Teaches specific spells to heroes:

- Requires minimum School Mastery level for the spell's tier
- Consumes Essence and Gold
- Available spell slots determine how many spells can be equipped simultaneously
- Heroes can know **more spells than they have slots**, allowing strategic swapping

> *Example: A hero with 3 spell slots might learn 6 spells total, swapping between Fire offense and Water healing loadouts depending on the upcoming match.*

#### Form & Condition Training

| Type | Description |
|:---|:---|
| **Form Restoration** | Returns hero to peak physical condition. Cost scales with form deficit and hero level. Medical Wing upgrades improve recovery speed |
| **Conditioning** | Maintains peak form through regular activity. Active heroes sustain form naturally; inactive heroes lose form gradually |
| **Recovery Training** | Light training that reduces fatigue while maintaining form. Lower intensity and lower cost — ideal for post-combat recovery |

### Trainer System

#### Trainer Transformation

**Hero-to-Trainer Conversion** — Veteran age heroes can be permanently transformed into Trainers:

| Rule | Detail |
|:---|:---|
| **Permanence** | Transformation is permanent and **cannot be reversed** |
| **No Combat** | Transformed heroes no longer participate in combat |
| **Frozen Stats** | Converted heroes retain their attribute values from the moment of transformation. Stats are permanently frozen — they **cannot be improved or degraded** after conversion |
| **Role** | Trainers become non-combatant entities used exclusively for training purposes |

> *Example: A Veteran Dwarf with STR 85 and KON 90 transforms into a Trainer. They can now train other heroes’ STR up to 85 and KON up to 90, but can never fight again.*

#### Trainer Mechanics

- **Trainer Acquisition** — Transform Veteran heroes into Trainers, or purchase Trainers from the marketplace using Gold
- **Trainer Attributes** — Each Trainer has attribute values in all eight primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK)
- **Trainer Aging** — Trainers age during each **training tick** (weekly cycle) by the same amount a hero would age from a combat death. This applies universally to **all races, including Undead** *(overrides the Undead race exception of aging only through combat deaths)*. Trainers who reach Death Expectation face escalating permanent death risk per training tick, identical to the combat mortality mechanic for active heroes
- **Training Limitation** — Attribute training is **capped by the Trainer's value** for that attribute. Multiple Trainers can be used, each training different attributes up to their respective values
- **Training as Calendar Event** — Training occurs during weekly server tick cycles. Multiple training actions can be queued and process sequentially

> *Example: If your Trainer has STR 70, your hero cannot train STR beyond 70 with that Trainer. You’d need a different Trainer with higher STR to push further.*

#### Trainer Marketplace

- Trainers can be listed and sold on the marketplace for **Gold**
- Price reflects **attribute values** and **age** *(higher-valued or younger trainers cost more)*
- Trainer ownership transfers with marketplace transaction
- *Trainers age over time; purchasing an older Trainer provides less long-term utility*

### Training Efficiency Modifiers

#### Positive Modifiers

| Modifier | Effect |
|:---|:---|
| **Training Facilities** (HQ) | +5–25% efficiency (scales with upgrade level) |
| **Library/Academy** (HQ) | +10–30% magic training speed |
| **Medical Wing** (HQ) | +15–40% form restoration rate |
| **Race Optimization** | Heroes of optimized race and positive relationship races gain +10–20% efficiency |
| **High Morale** | +5–15% training effectiveness |
| **Team Chemistry** | Training with compatible heroes (positive race relationships) grants efficiency bonus |
| **Kingdom Modifiers** | Server-specific training speed multipliers |

#### Negative Modifiers

| Modifier | Effect |
|:---|:---|
| **High Fatigue** | –20% to –50% training efficiency |
| **Low Form** | –10% to –30% gains |
| **Low Morale** | –10% to –25% effectiveness |
| **Age Penalties** | Veteran and Elder heroes face declining efficiency |
| **Incompatible Races** | Training alongside hostile races may reduce efficiency |
| **Overtraining** | Multiple consecutive sessions on same hero incur diminishing returns |

### Training Costs & Time

#### Cost Structure

| Factor | Description |
|:---|:---|
| **Base Cost** | Determined by training type and hero level |
| **Attribute Value** | Higher stats = higher cost |
| **Hero Level** | Higher level = higher cost |
| **Age** | Elder heroes cost more to train |
| **Stat Rarity** | Some stats are naturally harder to improve |
| **Currency** | Primary training uses Gold; magic training also requires Essence |

#### Time Investment

- **Real-time Component** — Training sessions may have cooldowns or completion times
- **Fatigue Cost** — Each training session adds fatigue, limiting consecutive training
- **Batch Training** — Players can queue multiple training sessions *(with increasing fatigue)*
- **No Pay-to-Skip** — Training time **cannot be bypassed** with currency; all players progress at the same rate

### Training Strategy

#### Specialization vs. Generalization

| Approach | Focus | Advantage | Disadvantage |
|:---|:---|:---|:---|
| **Specialist** | 2–3 core attributes for specific roles | Excel in combat roles, cost-efficient | Limited versatility, vulnerable to counters |
| **Generalist** | Balanced distribution across multiple stats | Adaptable, well-rounded performance | Higher total investment, may not excel |

> *Example: A specialist tank might focus on STR/KON only, becoming nearly unkillable but useless if the opponent targets backline. A generalist can fill multiple roles but won’t dominate any.*

#### Age-Based Training Decisions

| Hero Phase | Recommended Strategy |
|:---|:---|
| **Junior** | Invest heavily in core stats to build a strong foundation |
| **Prime Age** | Balance training with active combat deployment |
| **Veteran** | Selective training to maintain competitive stats; focus on experience |
| **Elder** | Minimal training investment; leverage accumulated experience and strategic value |

#### Formation-Oriented Training

- Train heroes to complement **specific formation roles**
- Coordinate training to create **synergistic stat combinations**
- Consider **race relationships** when planning group training sessions

### Training Limitations

| Limitation | Details |
|:---|:---|
| **Daily/Weekly Caps** | Maximum training sessions per hero per day *(prevents excessive grinding)*. Caps increase with HQ upgrades |
| **Fatigue Constraints** | Accumulated fatigue limits consecutive training. Heroes must rest or use recovery items. Over-fatigued heroes **cannot train or enter combat** |
| **Resource Limitations** | Gold and Essence reserves limit volume. Higher-tier training requires rare Essence types |
| **Form Requirements** | Heroes with very low form cannot train effectively. Must restore form before productive training resumes |

### Training Rewards

| Category | Rewards |
|:---|:---|
| **Direct Benefits** | Increased attribute values, XP gain toward hero leveling, improved combat performance, expanded magic capacity and mastery |
| **Secondary Benefits** | Maintained/improved form, morale boost from productive activity, team chemistry improvements with compatible heroes, achievement unlocks |
| **Long-term Progression** | Specialized heroes become more valuable on marketplace, well-trained rosters dominate league competition, strategic training creates competitive advantages |

---

## 2.6 Team System

### Purpose

Each player manages a **single team** (1:1 player-to-team relationship) that serves as their primary game entity. The team is a persistent organization containing heroes, formations, and its own statistics.

### Team Components

| Component | Description |
|:---|:---|
| **Hero Roster** | Collection of all heroes recruited by the player |
| **Formations** | Up to 2 saved formations with customizable positioning and strategy *(can set one as default)* |
| **Team Identity** | Name, emblem, colors, and visual customization |
| **Headquarters** | Team's base providing passive bonuses and upgrade options *(see [2.7 Headquarters System](#27-headquarters-system))* |

### Team Statistics

| Statistic | Description |
|:---|:---|
| **Team Morale** | Persistent value affecting overall team performance *(see Morale System in [2.4 Hero System](#24-hero-system))* |
| **Team Reputation** | Earned through victories, events, and league performance |
| **Win/Loss Record** | Historical performance tracking across all combat modes |
| **Team Chemistry** | Calculated from race relationship values between heroes on the team |

### Hero Relations (Race Relationships Applied)

- Hero interactions are determined by **race relationship values** *(90–100: Highly Positive, 70–89: Positive, 50–69: Neutral, 21–49: Negative, 0–20: Hostile)*
- Compatible heroes (relationships **70+**) gain **synergy bonuses** when deployed together
- Hostile race combinations (**0–20**) reduce team chemistry and morale significantly
- **Charisma** attribute can partially offset negative race relationships and improve team cohesion

### Mechanics

- Team stats **persist and evolve** continuously based on activity and results
- Formation changes can be made **between matches** but not during combat
- Team-wide buffs from events, kingdom bonuses, and achievements
- Headquarters upgrades provide **passive bonuses** to all heroes

---

## 2.6.1 Formation System

### Purpose

Formations define how heroes are **positioned** and **controlled** during combat. Each formation contains **6 heroes** arranged across two lines with customizable combat strategy, targeting priorities, spell priorities, and action sequencing. Players can save up to **2 formations** and select which to use before each match.

### Formation Structure

#### Combat Lineup (6 Heroes)

| Line | Heroes | Role |
|:---|:---:|:---|
| **Front Line** | 3 | Primary defense — high KON and WIL heroes; absorb melee damage; influence enemy targeting |
| **Back Line** | 3 | Support, healing, ranged, and magic specialists; protected by front line heroes |

#### Hero Specialization Categories

*Before setting formations, consider hero specializations:*

| Specialization | Key Attributes | Role |
|:---|:---|:---|
| **Melee** (Physical) | STR, KON, WIL | Close-range combat |
| **Ranged** (Physical) | DEX, SPD | Distance-based attacks |
| **Magic Focus** | INT, WIL | Spell-casting specialists |
| **Support** | CHA, WIL | Buffs, heals, and team enhancement |
| **Damage** | STR or INT | Primary offense output |
| **Healing** | INT, WIL | Restoration spells and recovery |

### Pre-match Approach

Players can select a strategic approach before each match that defines the team's overall playstyle:

| Approach | Playstyle | Best Against |
|:---|:---|:---|
| **Aggressive** | Prioritize high damage, target enemy damage dealers first, use offensive spells constantly, accept higher damage intake | Slower or defensive-focused teams |
| **Balanced** | Mix offensive and defensive tactics dynamically, adapt to match situation | Varied opponents, unpredictable matchups |
| **Defensive** | Prioritize survival and healing, use control abilities, eliminate threats with sustained offense | Aggressive or high-damage teams |

### Combat Strategy Settings

Each formation can be customized with the following strategy parameters:

#### Targeting Priority (Hero Level)

Determines which enemy hero each of your heroes attempts to target:

| Priority | Description |
|:---|:---|
| **Priority 1** (Primary) | Preferred target *(e.g., enemy main damage dealer)* |
| **Priority 2** (Secondary) | Secondary target if primary is unavailable |
| **Priority 3** (Tertiary) | Fallback target |
| **Priority 4–6** | Alternate targets |
| **Flexible** | Auto-adjust based on threat assessment |

#### Action Sequences

Determine the order and type of actions each hero takes during their turn:

| Action | Description |
|:---|:---|
| **Attack** | Standard physical or melee attack |
| **Cast Spell** | Use equipped spell from designated school |
| **Use Ability** | Activate hero-specific special ability |
| **Defend** | Raise defensive stance (+armor, –damage) |
| **Heal** | Use healing spell on lowest-health ally |
| **Buff** | Apply morale/stat boost to team |
| **Debuff** | Apply negative status to enemy |
| **Flee** | Attempt to retreat *(low priority action)* |
| **Auto-Suggest** | AI recommends action based on situation |

#### Spell Priority (Per Hero)

Magic spells are configured at **two levels**:

| Level | Scope | Description |
|:---|:---|:---|
| **Formation Level** (Primary) | Per formation | Spells selected specifically for this hero in this formation. Takes priority if configured. Allows match-specific customization |
| **Hero Level** (Fallback) | Per hero | Spells equipped on heroes in inventory. Used only if no formation-level spell is configured |

**Casting Conditions** *(for configured formation spells):*

| Condition | Trigger |
|:---|:---|
| **On Low Health** | Cast healing spell if ally health < threshold |
| **On Low Morale** | Cast morale spell if team morale < threshold |
| **On Enemy Weakness** | Cast debuff if enemy condition met |
| **On Cooldown Ready** | Use high-cooldown ability when available |

**Spell Targets:**

| Target | Description |
|:---|:---|
| **Self** | Hero casts on themselves |
| **Lowest Health** | Target ally with lowest HP |
| **Highest Priority** | Target enemy set in Targeting Priority |
| **Area** | Affects multiple targets *(if applicable)* |

> *Example: A healer configured at Formation Level with "On Low Health" condition will automatically cast Heal when any ally falls below the HP threshold, overriding their default hero-level spells.*

#### Conditional Tactics

Advanced strategy rules that trigger under specific conditions:

| Tactic | Trigger |
|:---|:---|
| **Formation Switch** | Change formation if condition met *(e.g., main tank health < 20%)* |
| **Hero Substitution** | Bring in substitute hero if active hero incapacitated |
| **Morale Threshold** | Execute special strategy if team morale drops below threshold |
| **Numerical Disadvantage** | Activate defensive/survival tactics if outnumbered |
| **Ally Death Response** | Increase damage/aggression if teammate dies |

### Formation Mechanics

#### Positioning Effects

| Effect | Description |
|:---|:---|
| **Front Line Protection** | Back line heroes gain reduced damage (–15–25%) if front line is intact |
| **Line Break** | If all front line heroes are incapacitated, back line takes full damage |
| **Positioning Advantage** | Some abilities have positional bonuses *(front line area attacks, back line ranged)* |
| **Formation Disruption** | Crowd control effects may disrupt formation positioning |

#### Formation Synergy

| Synergy Type | Effect |
|:---|:---|
| **Racial Synergy** | Heroes with positive race relationships gain tactical bonuses |
| **Complementary Roles** | Teams with varied roles (tank, damage, support, control) gain +5–10% overall effectiveness |
| **Line Coordination** | Front and back lines coordinate to create combined effects |
| **Shared Morale** | Team morale adjustments affect all heroes in formation |

#### Action Resolution

| Step | Description |
|:---|:---|
| **Turn Order** | Determined by Speed (SPD) attribute |
| **Formation Integrity** | Positioning maintained unless disrupted by crowd control |
| **Action Queue** | Each hero's action executes according to priority queue |
| **Spell Casting** | Spells execute immediately after queued action |
| **Outcome Resolution** | Damage, healing, and effects apply immediately |

### Formation Management

#### Saving Formations

Players can save up to **2 named formations**. Each formation saves:

| Saved Data |
|:---|
| Hero lineup (6 heroes) |
| Positions (front/back) |
| Targeting priorities |
| Action sequences |
| Spell priorities |
| Conditional tactics |

*Formations persist across matches and can be reused.*

#### Formation Selection

| Option | Description |
|:---|:---|
| **Default Formation** | One saved formation designated as default for automatic selection |
| **Match Selection** | Players choose formation before each match *(not during combat)* |
| **Rapid Swap** | Formation can be changed between matches but **not during active combat** |
| **Suggested Formations** | System recommends optimal formations based on opponent team composition |

#### Formation Editing

- Edit saved formations **between matches**
- Test formations in **practice/friendly matches**
- **Clone** existing formations to create new variants
- **Delete** unused formations

### Formation Limitations

| Category | Rules |
|:---|:---|
| **Slot Requirements** | All 6 lineup slots must be filled. Cannot use same hero twice. Cannot use heroes on multiple teams |
| **Cooldowns & Restrictions** | No cooldown between matches. Cannot change formation during combat. Substitutions only on incapacitation |
| **Resource Constraints** | HQ level determines available formations (base 2, expandable to 4–5 with upgrades). Advanced features unlock with player progression |

### Formation Tips & Strategy

| Area | Tips |
|:---|:---|
| **Front Line Selection** | Prioritize high KON and WIL heroes. Select heroes with defensive abilities. Consider race synergies within front line |
| **Back Line Selection** | Include at least 1 dedicated healer. Mix damage dealers with support/control. Ensure spell diversity for varied tactics |
| **Targeting Strategy** | Focus fire on high-threat enemies first (damage dealers, healers). Adapt targeting based on opponent formation. Use CC to disable dangerous enemies |
| **Spell Prioritization** | Healing spells should activate on threshold conditions *(not manual)*. Offensive spells target high-priority enemies. Utility spells coordinate with team tactics |
| **Conditional Tactics** | Set formation backup if main tank fails. Trigger aggression when outnumbering. Execute survival tactics when low on morale/resources |

> *Example: Against a team heavy on magic damage, consider placing a high-WIL hero on the front line to absorb spells, while your back line mages target their fragile casters first.*

---

## 2.7 Headquarters System

### Purpose

Each team has its own **Headquarters** serving as their base of operations, providing facilities, passive bonuses, and strategic settings. The Headquarters can be upgraded and customized to enhance team performance.

### Headquarters Facilities

| Facility | Description |
|:---|:---|
| **Training Facilities** | Improve hero training efficiency and reduce fatigue accumulation |
| **Medical Wing** | Faster recovery from injuries and form restoration |
| **Library/Academy** | Enhances spell learning speed and magic school mastery gains |
| **Forge/Workshop** | Improves crafting success rates and item durability |
| **Treasury** | Increases passive gold generation and resource storage capacity |
| **Barracks** | Expands hero roster capacity and improves team morale recovery |
| **Summoning Chamber** | Recruit junior heroes; time-limited *(e.g., 1 summon per week cycle)*; juniors aged between Min Age and Max Junior Age for their race; upgrading may reduce cooldown or improve quality |
| **Arena** | Match venue with public seating; upgrading increases audience capacity, ticket revenue, and home advantage bonuses |

### Race Optimization Settings

**Racial Affinity** — Choose **1 preferred race** that receives bonuses within team.

| Effect Type | Details |
|:---|:---|
| **Positive Effects** *(optimized race + positive relationship races)* | Higher training efficiency, faster form and morale recovery, reduced fatigue accumulation |
| **Negative Effects** *(non-positive relationship races)* | Lower training efficiency, slower recovery rates, increased morale decay over time |

> *Strategic Trade-off: Specializing in certain races provides strong bonuses but limits flexibility. A team optimized for Elves benefits Humans and Ents (positive relations) but penalizes Orcs and Undead.*

### Facility Upgrades

- Each facility can be upgraded independently using **Gold** and resources
- Higher-level facilities provide **stronger passive bonuses**
- Upgrades may unlock new features *(e.g., advanced training methods, special crafting recipes)*
- **Headquarters level** represents the sum of all facility levels

### Headquarters Bonuses

| Bonus | Description |
|:---|:---|
| **Passive Buffs** | Continuous benefits to all heroes *(e.g., +5% XP gain, –10% fatigue rate)* |
| **Home Advantage** | Enhanced performance when defending in certain combat modes *(scales with Arena seating capacity)* |
| **Efficiency Boosts** | Reduced costs and time for training, crafting, and recovery |
| **Morale Boost** | Well-maintained headquarters improves team morale over time |
| **Ticket Revenue** | Arena matches generate passive gold income based on audience capacity and team reputation |

### Customization

- **Visual themes** and decorations
- **Facility layout** and placement
- **Special trophies** and achievements displayed
- **Visitor access** settings *(for social features)*

---

## 2.8 Item System

### Purpose

Items boost hero attributes and provide unique effects. They can be **crafted**, **found**, or **traded**.

### Categories

| Type | Slots | Details |
|:---|:---:|:---|
| **Weapons** | Main Hand | Single-handed or dual-handed (occupies both hands) |
| **Off-hand** | Off-Hand | Shield, Spell Accelerator, or single-handed weapon (dual-wielding) |
| **Armor** | 4 Slots | Head, Body (main), Hands, Feet |
| **Accessories** | 3 Slots | 1 Amulet + 2 Rings |

### Item Rarity Tiers

| Tier | Rarity | Description |
|---:|:---|:---|
| 1 | **Common** | Basic items with standard attribute bonuses |
| 2 | **Uncommon** | Improved items with enhanced bonuses |
| 3 | **Rare** | High-quality items with significant bonuses |
| 4 | **Epic** | Exceptional items with powerful bonuses and special effects |
| 5 | **Legendary** | Extremely rare items with unique effects and superior stats |
| 6 | **Mythic** | The rarest and most powerful items with game-changing abilities |

### Mechanics

| Mechanic | Description |
|:---|:---|
| **Rarity & Durability** | Each item has rarity, durability, and attribute modifiers |
| **Weapon Types** | Single-handed *(can be dual-wielded)* or dual-handed *(occupies both hands)* |
| **Off-hand Options** | Shield *(defense/resistance)* or Spell Accelerator *(casting speed/power)*; **unavailable** with dual-handed weapons |
| **Armor Slots** | Head, Body, Hands, Feet — one piece per slot. Body is the main armor and provides the highest defense |
| **Accessory Slots** | 1 Amulet + 2 Rings per hero |

> *Example: A dual-wield setup uses two single-handed weapons (Main Hand + Off-Hand), sacrificing shield defense for additional attack power. This option is unavailable to Giants (no off-hand) and irrelevant for Ents (no weapons).*

---

## 2.9 Spell System

### Purpose

Spells provide tactical options based on hero **magic proficiency**.

### Magic Proficiency

| Attribute | Description |
|:---|:---|
| **Magic Capacity** | Determines spell slot capacity — how many spells a hero can have equipped at once *(min 1, max 5; increases with training)* |
| **School Mastery** | Per-school permanent stat (Fire, Water, Air, Earth, Light, Dark); determines the max difficulty/tier of usable spells |

### Spell Types by Purpose

| Type | Description | Examples |
|:---|:---|:---|
| **Offensive** | Direct damage, damage over time, debuffs | *Fireball, Poison, Weakness* |
| **Defensive** | Healing, shields, buffs, cleansing | *Heal, Shield, Purify* |
| **Utility** | Crowd control, turn manipulation, summoning | *Stun, Slow, Summon* |

### Magic Schools

| School | Type | Effects & Abilities |
|:---|:---|:---|
| **Fire** | Offensive | Damage over time, area attacks, burn effects |
| **Water** | Defensive/Control | Healing, cleansing, ice-based control and damage |
| **Air** | Offensive/Speed | Speed buffs, lightning damage, evasion enhancement |
| **Earth** | Defensive/Control | Defense buffs, physical damage, stuns and slows |
| **Light** | Healing/Holy | Healing, mid-combat resurrection *(once per match; requires Mastery Tier 8+; revives KO'd hero at 30% HP with reduced form; caster suffers –50% stats for remainder of match; does not prevent post-match age penalty)*, holy damage *(bonus against Undead)* |
| **Dark** | Cursing/Drain | Curses, life drain *(Undead are immune to enemy life drain but can use it themselves — life drain is the primary healing method for Undead, who cannot benefit from external healing spells)*, debuffs, summoning |

> *Note: Some races (Genie, Elf) gain bonuses from Intelligence for spell effectiveness. Heroes can learn spells from multiple schools but may specialize for efficiency.*

---

## 2.10 Combat System

### Concept

- **Turn-based**, asynchronous battles
- Hero performance depends on **primary stats, form, fatigue, level, age, and morale**

### Combat Flow

| Step | Description |
|---:|:---|
| **1** | Formation selection and setup *(each player can save up to 2 formations, set one as default, and manually select which to use)* |
| **2** | Queue match in Redis |
| **3** | PHP worker simulates turn-based combat |
| **4** | XP, form, fatigue, and morale updates applied |
| **5** | Result stored in `battles` table and broadcast via WebSocket |

### Morale in Combat

**Individual Hero Morale** — Affects damage output, accuracy, defense, and resistance to debuffs during battle.

**Team Morale** — Influences formation synergy, cooperative abilities, and overall team cohesion.

| Morale State | Effects |
|:---|:---|
| **High Morale** | +10–20% damage and accuracy bonuses; increased resistance to fear, confusion, and morale-breaking effects; enhanced critical hit chance; better performance under pressure |
| **Low Morale** | –10–20% damage and accuracy penalties; increased chance to flee, hesitate, or refuse risky actions; reduced resistance to debuffs; may break formation or ignore orders |

**Morale Changes During Combat:**

| Trigger | Effect |
|:---|:---|
| **Ally death witnessed** | Decreases morale |
| **Critical hits and victories** | Boost morale |
| **Outnumbered or severely wounded** | Reduces morale |
| **High Charisma hero rally** | Restores morale mid-battle for nearby allies |

> *Example: A team at high morale scoring a critical hit on turn 1 gets an additional morale surge, creating a snowball advantage. Conversely, losing their front-line tank on turn 2 could reverse the momentum entirely.*

---

## 2.11 Marketplace System

### Purpose

Players **trade heroes and items** with other players within the same Kingdom.

### Mechanics

| Rule | Description |
|:---|:---|
| **Hero Stats Retained** | Heroes retain **level, age, form, fatigue** when traded |
| **Morale Reset** | Morale is **reset to base value** when a hero changes teams |
| **XP Continuation** | XP continues to accumulate post-trade |
| **Transaction Fee** | A percentage-based fee applies on all sales |

> *Example: Selling a Level 35 Elf hero — the buyer receives the hero at Level 35 with current age, form, and fatigue intact, but morale resets to the base value since the hero joins a new team.*

*For detailed marketplace economics, see [2.3 Economy System — Trading & Marketplace](#23-economy-system).*

---

## 2.12 League System

### Concept

- **Structured PvP competition** organized like football/soccer leagues
- Seasonal format with multiple **tiers and groups** within each tier
- Teams compete for **promotion**, avoid **relegation**, and earn **seasonal rewards**

### Structure

| Component | Description |
|:---|:---|
| **Tiers** | Multiple competitive levels *(e.g., Premier, Division 1, Division 2, Division 3)* |
| **Groups** | Within each tier, players are divided into groups for round-robin competition |
| **Seasons** | Fixed-duration periods *(e.g., 2–4 weeks)* with scheduled matches |
| **Promotion/Relegation** | Top performers move up a tier; bottom performers move down |

### Mechanics

| Mechanic | Description |
|:---|:---|
| **Matchmaking** | Within groups; ensures fair competition based on tier and group assignment |
| **Points System** | Win = **3 pts**, Draw = **1 pt**, Loss = **0 pts** |
| **Match Outcomes** | Determined by heroes' primary stats, form, fatigue, level, age, and morale |
| **Schedule** | Fixed schedule with rest days between matches for strategic management |
| **Rewards** | Seasonal rewards based on final tier and group position |

### Morale Dynamics in League Play

| Scenario | Morale Effect |
|:---|:---|
| **Win Streaks** | Consecutive victories provide escalating morale bonuses |
| **Relegation Pressure** | Teams near bottom positions suffer morale penalties due to stress |
| **Derby Matches** | Matches against top-ranked opponents/rivals have amplified morale swings |
| **Promotion Reward** | Promoting to a higher tier grants significant morale boost to entire team |
| **Season Performance** | Sustained success builds confidence; prolonged struggles erode morale |

### Age Considerations in League Play

| Factor | Description |
|:---|:---|
| **Veteran Experience** | Elder heroes (beyond Prime Age) provide tactical bonuses and leadership in high-pressure matches |
| **Junior Development** | Junior heroes (below Max Junior Age) earn bonus XP from league matches |
| **Age Diversity Bonus** | Teams with balanced age distribution gain tactical flexibility and formation options |
| **Strategic Rotation** | Rotate elder heroes to minimize death risk while maintaining competitive performance |
| **Season Fatigue** | Long seasons may accelerate aging for heavily-used elder heroes, especially through combat deaths |

> *Example: A savvy manager might field junior heroes in early-season "safe" matches to develop them, then deploy veteran prime-age heroes for crucial promotion/relegation matches.*

---

## 2.13 Graveyard System

### Purpose

The Graveyard is a **permanent repository** for all heroes who have died permanently in combat. It serves as a **memorial** and **historical record** for fallen heroes.

### Mechanics

| Mechanic | Description |
|:---|:---|
| **Permanent Death Storage** | When a hero dies permanently *(typically elder heroes at or beyond Death Expectation age)*, they are automatically moved to the Graveyard |
| **Historical Record** | Preserves complete hero information: final stats, level, age at death, team/player association, total battles fought, victories achieved, and cause of death |
| **No Resurrection** | Heroes in the Graveyard **cannot** be revived or returned to active play |
| **Memorial Function** | Players can visit the Graveyard to view their fallen heroes and team history |
| **Statistics Tracking** | Contributes to overall team legacy statistics and achievements |

> *Note: The Graveyard is purely a record-keeping system. It does not consume roster space or affect active team management. It may unlock special achievements or titles based on legendary fallen heroes.*

---

## 2.14 Community System

### Purpose

Supports **player interaction**, strategy discussion, and **community building** across the Kingdom.

### Features

| Feature | Description |
|:---|:---|
| **Mail System** | Private messaging between players for trading and communication |
| **News Feed** | Kingdom-wide announcements, server events, and updates |
| **Forum Discussions** | Dedicated spaces for strategy discussion and player engagement |
| **Email Notifications** | System alerts for trades, league results, and events |
| **Player Profiles** | Public profiles showing hero stats *(level, form, fatigue, age)*, team record, and achievements |
| **Leaderboards** | Rankings by league tier, arena performance, total victories |

---

## Known Issues & Open Questions

| # | Category | Issue | Severity | Notes |
|---:|:---|:---|:---|:---|
| 1 | **Combat** | No combat formulas documented — HP, damage, defense, accuracy, dodge, critical hit, and status effect calculations are undefined | Important | Section 2.10 is high-level only |
| 2 | **Morale** | "Base morale value" referenced in Marketplace (2.11) but never defined — unclear what value morale resets to when a hero changes teams | Important | Is it 50? 100? Race-specific? |
| 3 | **Roster** | Base hero roster size never stated — Barracks "expands capacity" but starting capacity is unknown | Important | Section 2.7 |
| 4 | **Onboarding** | Hero acquisition flow undocumented — how many starting heroes? What level/age? How does initial team setup work? | Important | Kingdom Settings mention "starting resources" but no details |
| 5 | **Missing Systems** | Dungeon System referenced in tick schedule (2.2) and Crystal earning (2.3) but has no dedicated section | Important | Needs own section or removal from references |
| 6 | **Missing Systems** | Crafting System referenced in tick schedule (2.2) and Economy (2.3) but undocumented | Important | Forge/Workshop facility exists but no crafting rules |
| 7 | **Missing Systems** | Quest System referenced in Gold earning (2.3) but no quest mechanics documented | Important | "Daily login bonuses and quest completions" |
| 8 | **Missing Systems** | Item Durability & Enchanting referenced in Essence spending (2.3) but not covered in Item System (2.8) | Important | Essence costs listed but no mechanics |
| 9 | **Missing Systems** | Friendly Matches listed in tick schedule (2.2) but no rules or purpose documented | Minor | |
| 10 | **Missing Systems** | Arena Match mechanics not documented — only arena as HQ facility with ticket revenue | Minor | No standalone combat mode rules |
| 11 | **Balance** | Genie stat budget (8.85 total multiplier) is 10.6% higher than Human baseline (8.00) with no equipment restrictions | Design | Only tradeoff is 0.5x training speed and 2000yr lifespan — but lifespan is an advantage |
| 12 | **Data** | Giant→Genie relationship is 60 (Neutral) but Genie→Giant is 70 (Positive) — only asymmetric pair in the entire matrix | Design | Intentional or typo? All other 27 pairs are symmetric |
| 13 | **Clarity** | Ent KON base multiplier unclear — table shows 1.3x as final value but text says "additional +15% bonus on top of base" without stating the base (presumably 1.15x) | Minor | Table and explanation should agree |
| 14 | **Clarity** | Training speed modifier actual penalty unclear — example says cost-per-point is identical across races, meaning the only penalty is calendar time, not gold efficiency | Minor | Should state explicitly that slow races pay time, not gold |
| 15 | **Clarity** | "Death Expectation" naming is slightly misleading — sounds like expected age of death but actually means when mortality risk begins increasing; heroes commonly survive past it | Minor | Consider "Mortality Threshold" or add clarifying note |
| 16 | **Race Data** | Race Relationship Breakdown has ~12 values assigned to wrong tier labels per defined ranges — e.g., values of 20 labeled "Negative" instead of "Hostile", values of 30 labeled "Neutral" instead of "Negative" | Minor | Tier ranges: 90–100 Highly Positive, 70–89 Positive, 50–69 Neutral, 21–49 Negative, 0–20 Hostile |

---

**End of System Documentation**
