# Project Docs Index

This folder contains specifications derived from [game-summary.md](game-summary.md) and [screens-overview.md](screens-overview.md).

**Game Design & Domain**
- [Core Systems Summary](game-summary.md) — Complete game mechanics, races, economy, combat, and all systems
- [Screens Overview](screens-overview.md) — All 20 game screens with UI/backend requirements
- [Known Issues](known-issues.md) — Documentation gaps and design questions to resolve

**Architecture & Backend Design**
- [Architectural Specification](arch-spec.md) — Core backend architecture with dual-layer design (Web + Internal API)
- [API Design Guide](api-design.md) — Internal API structure, REST principles, service layer patterns, and future evolution roadmap
- [Entity Reference](entity-reference.md) — All DB entities, config data, enums, and structural decisions
- [Route Map](route-map.md) — Complete reference of all Web and API routes
- [Roadmap](roadmap.md) — Implementation order and priorities

**Systems**
- [Auth System](systems/auth-system.md) — Authentication, registration, sessions
- [Kingdom System](systems/kingdom-system.md)
- [Event System](systems/event-system.md)
- [Calendar System](systems/calendar-system.md) — Weekly ticks, schedule, and season timeline
- [Economy System](systems/economy-system.md)
- [Hero System](systems/hero-system.md)
- [Training System](systems/training-system.md)
- [Team System](systems/team-system.md)
- [Formation System](systems/formation-system.md)
- [Headquarters System](systems/headquarters-system.md)
- [Item System](systems/item-system.md)
- [Spell System](systems/spell-system.md)
- [Combat System](systems/combat-system.md)
- [Dungeon System](systems/dungeon-system.md)
- [Marketplace System](systems/marketplace-system.md)
- [League System](systems/league-system.md)
- [Graveyard System](systems/graveyard-system.md)
- [Community System](systems/community-system.md)
- [Quest System](systems/quest-system.md)
- [Crafting System](systems/crafting-system.md)

**Screens**
- [00 Public Pages (Homepage, Wiki, News)](screens/00-public-pages.md)
- [00 Auth (Login/Register)](screens/00-auth-screens.md)
- [01 Kingdom Selection](screens/01-kingdom-selection.md)
- [02 Team Dashboard](screens/02-team-dashboard.md)
- [03 Hero Roster](screens/03-hero-roster.md)
- [04 Hero Detail](screens/04-hero-detail.md)
- [05 Training](screens/05-training.md)
- [06 Trainer Management](screens/06-trainer-management.md)
- [07 Formation Setup](screens/07-formation-setup.md)
- [08 Headquarters](screens/08-headquarters.md)
- [09 Summoning Chamber](screens/09-summoning-chamber.md)
- [10 Item/Equipment](screens/10-item-equipment.md)
- [11 Spell Management](screens/11-spell-management.md)
- [12 Combat/Battle](screens/12-combat-battle.md)
- [13 League](screens/13-league.md)
- [14 Calendar/Events](screens/14-calendar-events.md)
- [15 Marketplace](screens/15-marketplace.md)
- [16 Graveyard](screens/16-graveyard.md)
- [17 Community](screens/17-community.md)
- [18 Arena Management](screens/18-arena-management.md)
- [19 Player Profile & Settings](screens/19-player-profile-settings.md)
- [20 Crafting](screens/20-crafting.md)

**Meta**
- [UI & CSS Design Guidelines](ui-guidelines.md) — Design tokens, atomic component model (atoms/molecules), BEM naming, Tailwind usage rules, Stimulus controller conventions, and accessibility requirements
- [README.md](../README.md) — Project overview and dev setup
- [`_deferred/`](../_deferred/) — Parked files (CHANGELOG.md, CONTRIBUTING.md, legacy-migration.md); not actively maintained until the project reaches a stable version

---

## Current Implementation Status

This table provides a snapshot of implemented features versus placeholders:

| Feature / Area | Service Layer | Web UI / Controllers | API (V1) Endpoints | Status / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Authentication** | Fully Implemented | Implemented | N/A (Session-based) | Register, verification, login, password reset. |
| **Kingdom & Locale**| Fully Implemented | Implemented | Implemented | Data loading from JSON, capacity calculations. Locale switcher (`/change-locale/{locale}`) implemented. |
| **Team / Dashboard**| Fully Implemented | Implemented | Implemented | Dashboard, settings, economy (gold/essence), financial records. |
| **Hero Roster** | Fully Implemented | Implemented | Implemented | Hero CRUD, rename, summoning chamber, `HeroGenerator`. |
| **Summoning** | Fully Implemented | Implemented | Implemented | Race selection, cooldowns, `SummonHistory` logging. |
| **Training** | Fully Implemented | Implemented | Implemented | Training calculations + automated tick processing. |
| **Calendar & Ticks**| Fully Implemented | Dashboard integrated | Implemented | Scheduled tick generation and seasonal calendar. |
| **Formations** | Fully Implemented | Implemented | Implemented | Formation CRUD, slot assignment, strategy JSON. |
| **Headquarters** | Fully Implemented | Implemented | Implemented | Facility upgrades, race optimization, passive bonuses. |
| **Items** | Fully Implemented | Implemented | Implemented | Inventory, equip/unequip, dismantle. |
| **Spells** | Fully Implemented | Implemented | Implemented | Spell library, learning, slot equipping. |
| **Leagues** | Partially Implemented | Not Implemented | Not Implemented | `LeagueFixtureScheduler` and `SeasonTransitionService` implemented; match result processing still pending. |
| **Combat** | Not Implemented | Not Implemented | Not Implemented | Combat simulator, formulas, and results are missing (Phase 5). |
| **Events / Calendar UI** | Not Implemented | Not Implemented | Not Implemented | Event triggers and participation pending (Phase 5). |
| **Marketplace** | Not Implemented | Not Implemented | Not Implemented | Listings, bids, transactions pending (Phase 6). |
| **Community** | Not Implemented | Not Implemented | Not Implemented | Messaging, forum, leaderboards pending (Phase 6). |
| **Graveyard** | Not Implemented | Not Implemented | Not Implemented | Permanent death records pending (Phase 6). |
| **Dungeons** | Not Implemented | Not Implemented | Not Implemented | PvE encounters and rewards pending (Phase 7). |
| **Quests** | Not Implemented | Not Implemented | Not Implemented | Quest generation and rewards pending (Phase 7). |
| **Crafting** | Not Implemented | Not Implemented | Not Implemented | Recipe crafting and queue pending (Phase 7). |
| **Arena Management** | Not Implemented | Not Implemented | Not Implemented | Standalone arena match rules and revenue upgrades pending (Phase 7). |

---

## How to use
- Each system/screen file is a template to fill with implementation details: data models, API routes, DTOs, and services.
- Link to existing docs rather than copying large sections.
- Consult [known-issues.md](known-issues.md) for gaps that need resolution before implementation.

