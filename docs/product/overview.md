# Game systems overview

> **Overview only — not the live contract.** Conflict order: [docs/README.md](../README.md) § Canonical sources. Open design: [known-issues.md](known-issues.md). Deferred design: [`docs/future/`](../future/).

## Game Premise

Players take on the role of **arena managers** in a persistent fantasy world. They recruit heroes from 8 distinct races, train and equip them, assemble tactical formations, and lead their teams into asynchronous turn-based battles against other managers. Success depends on strategic team building, resource management, and competitive league performance — not the manager's own combat ability.

## Live systems

One-line index. Rules live in the linked spec.

### Kingdom

Live rules: [kingdom-system.md](../systems/kingdom-system.md).

A **Kingdom** is a shared game world: rows are scoped by `kingdom_id`, not a separate process. Players pick one Kingdom at registration. Season length is **77 days**; `level_cap` is **20**.

### Calendar and ticks

Live rules: [calendar-system.md](../systems/calendar-system.md).

Scheduled ticks in the kingdom timezone. Season: week 1 preparation, weeks 2–10 two league matches (Tue/Fri), week 11 transition. Full timetable in the calendar spec. Dungeon, crafting, and world-event ticks are not live.

### Economy

Live rules: [economy-system.md](../systems/economy-system.md), [financial-crisis-system.md](../systems/financial-crisis-system.md). UI: [20-finance.md](../screens/20-finance.md).

**Gold** is the sole live currency. Every change is a `FinancialRecord`. Gold never goes negative; unpaid upkeep becomes team debt. Essence is not in the live economy.

### Heroes

Live rules: [hero-system.md](../systems/hero-system.md). Rating: [hero-rating-system.md](../systems/hero-rating-system.md).

Roster units: race, primary attributes **1–20**, form / fatigue / morale, equipment, spells. **No classes, no rarity.** Stored `Hero.level` is live truth ([#6](known-issues.md)). Playable races: Human, Elf, Dwarf, Orc, Undead, Giant, Ent, Genie. Chemistry from the relationship matrix is **flavor for matches** ([#7](known-issues.md)). In-combat race synergy is **Cut** ([#9](known-issues.md)).

### Training

Live rules: [training-system.md](../systems/training-system.md).

Trainer-centric, **Thursday 10:00** tick, **no gold cost**. Training does not write `Hero.xp`.

### Team

Live rules: [team-system.md](../systems/team-system.md).

One player, one team. Starting roster **10**; **6** combat-ready heroes to play. Chemistry does not affect combat.

### Formations

Live rules: [formation-system.md](../systems/formation-system.md). UI: [07-formation-setup.md](../screens/07-formation-setup.md).

Six slots, up to **4** saved formations. Player editor is L1 targeting + thin L2 spell priorities. No L3, no in-combat synergy, no simulate sandbox.

### Headquarters

Live rules: [headquarters-system.md](../systems/headquarters-system.md). Summoning: [summoning-system.md](../systems/summoning-system.md).

Seven facilities (no Forge). Arena is home-match **ticket gold** plus attendance analytics. Combat home-advantage is not live.

### Items

Live rules: [item-system.md](../systems/item-system.md).

Nine slots including two rings. Enchanting is **not** in the live game ([#2](known-issues.md)).

### Spells

Live rules: [spell-system.md](../systems/spell-system.md).

Learn for **gold only**. Seeded catalog is **Tier 1–5** ([#16](known-issues.md)).

### Combat

Live rules: [combat-system.md](../systems/combat-system.md). Formulas: [combat-formulas.md](../systems/combat-formulas.md). Replay: [12-combat-battle.md](../screens/12-combat-battle.md).

Fully automated. The UI is a **replay** of `combat_log`. Kill score 0–6; round **200** ends on current scores. Mid-combat morale, race synergy, and `POST /api/v1/combat/simulate` are Cut.

### Marketplace

Live rules: [marketplace-system.md](../systems/marketplace-system.md).

Same-kingdom gold listings (hero, trainer, item). Listing prices sit in the suggested band ([#15](known-issues.md)). Auction bids are not capped.

### League

Live rules: [league-system.md](../systems/league-system.md).

Kingdom-scoped tiers. Points Win 3 / Draw 1 / Loss 0. Chemistry is a **tie-break** only.

### Graveyard

Live rules: [graveyard-system.md](../systems/graveyard-system.md).

Memorials for dismissals and permanent combat death. Heroes cannot be revived.

### Community

Live rules: [community-system.md](../systems/community-system.md). Notifications: [notification-system.md](../systems/notification-system.md).

Kingdom-scoped mail, forum, public profiles, navbar notifications. Public wiki and session password change stay **Planned**.

## Deferred

Parked Milestone 8/9 design. **Not live.** Do not implement unless the user explicitly asks.

| Parked | Spec |
|--------|------|
| **Dungeons** | [future/dungeon-system.md](../future/dungeon-system.md) |
| **Crafting** | [future/crafting-system.md](../future/crafting-system.md) |
| **Quests** | [future/quest-system.md](../future/quest-system.md) |
| **World events** | [future/world-events-system.md](../future/world-events-system.md) |
| **Alliances / guilds** | Milestone 9 — [archive/history.md](../roadmap/archive/history.md) |

---

Live rules: `docs/systems/*.md`. Reader snapshot: [progress.md](progress.md). Glossary: [glossary.md](glossary.md). Open design: [known-issues.md](known-issues.md). Now / next: [roadmap/current.md](../roadmap/current.md).
