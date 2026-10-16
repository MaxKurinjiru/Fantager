# Game systems overview

> **Overview only — not the live contract.** On conflict, matching `docs/systems/*.md` wins. Open design: [known-issues.md](known-issues.md). Deferred design: [`docs/future/`](../future/).

## Game Premise

Players take on the role of **arena managers** in a persistent fantasy world. They recruit heroes from 8 distinct races, train and equip them, assemble tactical formations, and lead their teams into asynchronous turn-based battles against other managers. Success depends on strategic team building, resource management, and competitive league performance — not the manager's own combat ability.

## Table of Contents

- [2.1 Kingdom System & Server Split](#21-kingdom-system--server-split)
- [2.2 Calendar & Server Ticks](#22-calendar--server-ticks)
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

**Deferred (not core):** [Dungeons](../future/dungeon-system.md) · [Crafting](../future/crafting-system.md) · [Quests](../future/quest-system.md) · [World events](../future/world-events-system.md) — Milestone 8/9, parked. See [Deferred](#deferred-not-in-core).

## 2.1 Kingdom System & Server Split

Live rules: [kingdom-system.md](../systems/kingdom-system.md).

A **Kingdom** is a shared game world in one application: rows are scoped by **`kingdom_id`**, not a separate server process. Players pick a Kingdom once at registration. Gameplay, economy, and marketplace listings do not cross kingdoms.

Live settings include locale, timezone, `game_speed`, marketplace tax, season length (**77 days**), capacity, `level_cap` **20**, and `xp_modifier` at XP award. Starting heroes (**10**) are an init constant, not a Kingdom setting. Crafting Boost is deferred with crafting (`future/`).

## 2.2 Calendar & Server Ticks

Live rules: [calendar-system.md](../systems/calendar-system.md).

The game advances on **scheduled ticks** in the kingdom timezone. A league season is **11 weeks (77 days)**: week 1 preparation, weeks 2–10 two league matches per week (Tue/Fri), week 11 transition.

**Live ticks (summary):** daily reset / aging / NPC maintenance; fatigue and form recovery; inactivity cleanup; Tue/Fri league matches; Wed/Sun friendly matches; Thursday weekly training; Sunday arena adaptation; weekly reset. Full timetable: [calendar-system.md](../systems/calendar-system.md#weekly-server-tick-schedule).

**Not live:** dungeon-match ticks, crafting-queue ticks, dynamic world events ([future/world-events-system.md](../future/world-events-system.md)).

## 2.3 Economy System

Live rules: [economy-system.md](../systems/economy-system.md), [financial-crisis-system.md](../systems/financial-crisis-system.md).

Gold is the **sole** live currency. Training has **no gold cost**. Essence is **not** in the live economy. Every gold change is a `FinancialRecord`. Gold never goes negative; unpaid upkeep becomes **team debt** (warning → restricted → bankruptcy). Listing prices sit in a suggested band (−50% / +100%) — [marketplace-system.md](../systems/marketplace-system.md#locked-listing-price-band).

## 2.4 Hero System

Live rules: [hero-system.md](../systems/hero-system.md). Rating: [hero-rating-system.md](../systems/hero-rating-system.md).

Heroes are roster units: **race**, primary attributes **1–20**, form / fatigue / morale, equipment, and spells. There are **no classes** and **no rarity**. Match XP writes lifetime `Hero.xp` and raises stored `Hero.level` on the locked curve ([#6](known-issues.md)).

**Playable races:** Human, Elf, Dwarf, Orc, Undead, Giant, Ent, Genie. Relationship matrix, age milestones, race bonuses, and traits: [hero-system.md](../systems/hero-system.md). Team chemistry from the matrix is **flavor for matches** ([#7](known-issues.md)). In-combat race-synergy tables are **Cut** ([#9](known-issues.md)). Genie summon pool: [summoning-system.md](../systems/summoning-system.md#locked-genie-summon-pool).

### Age System

Milestones and aging live in [hero-system.md](../systems/hero-system.md) and `config/game/races.yaml`. Phase is display, marketplace filter, and **rating** — not a training-efficiency curve. KO is not always permanent death; elders at or beyond Mortality Threshold take a mortality roll. Undead skip daily aging.

## 2.5 Training System

Live rules: [training-system.md](../systems/training-system.md).

Trainer-centric: configure focus, assign heroes, wait for the **Thursday 10:00** tick. **No Gold cost.** Training does **not** write `Hero.xp`. Foci: Attribute (1–20), Magic capacity (cap 5), Form recovery. Promotion is permanent. Caps scale with the Training facility.

## 2.6 Team System

Live rules: [team-system.md](../systems/team-system.md).

One player, one team. Starting roster **10**; **6** combat-ready heroes to play. Fan club, reputation, morale, and chemistry (flavor for matches) are live on the dashboard. Chemistry does not affect combat ([#7](known-issues.md)).

### 2.6.1 Formation System

Live rules: [formation-system.md](../systems/formation-system.md). UI: [screens/07-formation-setup.md](../screens/07-formation-setup.md).

Six slots (3 front, 3 back), up to **4** saved formations. Save allows 0–6 slots and rejects duplicate `hero_id` ([#10](known-issues.md)). Kickoff fields 6 unique combat-ready heroes (assigned → default → roster fill). Player editor is L1 targeting + thin L2 equipped-spell priorities ([#12](known-issues.md)). No L3 sequences, no in-combat synergy, no simulate sandbox ([#9](known-issues.md)).

## 2.7 Headquarters System

Live rules: [headquarters-system.md](../systems/headquarters-system.md). Summoning: [summoning-system.md](../systems/summoning-system.md).

Seven facilities (no Forge), gold upgrades, weekly maintenance. Arena is **home-match ticket gold** plus HQ-panel attendance analytics ([#4](known-issues.md)). Combat home-advantage is **not** live. Visual themes / visitor access / HQ morale-over-time are not live.

## 2.8 Item System

Live rules: [item-system.md](../systems/item-system.md).

Nine equipment slots including two rings. Two-handed occupancy and dual-wield are locked ([#8](known-issues.md)). Equip is hero-detail paperdoll (click); `/app/inventory` redirects. Enchanting is **not** in the live game ([#2](known-issues.md)). Crafting and loot drops are deferred.

## 2.9 Spell System

Live rules: [spell-system.md](../systems/spell-system.md).

Learn for **gold only**. Seeded catalog is **Tier 1–5** (two spells per school per tier) ([#16](known-issues.md)). No resurrection, summon-creature, or essence. Magic capacity 1–5; school mastery from combat XP.

## 2.10 Combat System

Live rules: [combat-system.md](../systems/combat-system.md). Replay UI: [12-combat-battle.md](../screens/12-combat-battle.md).

Fully automated. Players set formation **before** kickoff; the UI is a **replay** of `combat_log`. League Tue/Fri and friendly Wed/Sun use the same engine. Kill score 0–6; round **200** ends on current scores. Haste/Shock apply YAML speed percents to the initiative **sort**, not AP ([#9](known-issues.md)). Replay HUD: emblems, League/Friendly label, contextual back link. Mid-combat morale, race synergy, and `POST /api/v1/combat/simulate` are Cut.

## 2.11 Marketplace System

Live rules: [marketplace-system.md](../systems/marketplace-system.md).

Same-kingdom gold listings: hero, trainer, item. Seller pays tax. Listing prices must sit in the suggested band ([#15](known-issues.md)). Auction bids are not capped. Player buys have no race filter; NPC hero buys use the shared summoning pool ([#14](known-issues.md)).

## 2.12 League System

Live rules: [league-system.md](../systems/league-system.md).

Kingdom-scoped tiers and groups. Season **77 days**. Table points Win 3 / Draw 1 / Loss 0. Promotion / relegation from `league_tiers_config`. Chemistry is a **tie-break** only. Win-streak / derby / relegation-pressure morale, veteran leadership, and junior bonus XP are **not** live.

## 2.13 Graveyard System

Live rules: [graveyard-system.md](../systems/graveyard-system.md).

Memorial wall for **dismissals** and permanent combat death. Reserved causes `age` / `retired` / `death` have no writers. Heroes cannot be revived.

## 2.14 Community System

Live rules: [community-system.md](../systems/community-system.md). Notifications: [notification-system.md](../systems/notification-system.md).

Kingdom-scoped in-app mail, forum, public profiles, and navbar notifications. Forum `is_pinned` is stored but unsettable. Public wiki stays **Planned** ([00-public-pages.md](../screens/00-public-pages.md#locked-public-wiki-stays-planned)). Session password change / extra settings stay **Planned**. Live outbound email is auth and inactivity only.

## Deferred (not in core)

Parked Milestone 8/9 design. **Not live.** Do not implement unless the user explicitly asks. Full specs: [`docs/future/`](../future/).

| Parked | Spec |
|:---|:---|
| **Dungeons** | [future/dungeon-system.md](../future/dungeon-system.md) |
| **Crafting** | [future/crafting-system.md](../future/crafting-system.md) |
| **Quests** | [future/quest-system.md](../future/quest-system.md) |
| **World events** | [future/world-events-system.md](../future/world-events-system.md) |
| **Alliances / guilds** | Milestone 9 — [roadmap/history.md](../roadmap/history.md) |

---

**End of overview**

> Live rules: `docs/systems/*.md`. Open design: [known-issues.md](known-issues.md). Now / next: [roadmap/current.md](../roadmap/current.md).
