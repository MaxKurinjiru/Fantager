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
- [Summoning System](systems/summoning-system.md)
- [Calendar System](systems/calendar-system.md) — Weekly ticks, schedule, and season timeline
- [Economy System](systems/economy-system.md)
- [Financial Crisis System](systems/financial-crisis-system.md)
- [Hero System](systems/hero-system.md)
- [Hero Rating System](systems/hero-rating-system.md) — Base OVR, complex rating, cached columns, economy integration
- [Hero Chronicle System](systems/hero-chronicle-system.md) — Per-hero append-only event log (`hero_chronicle`), hero detail History tab
- [Training System](systems/training-system.md)
- [Team System](systems/team-system.md)
- [Team Chronicle System](systems/team-chronicle-system.md) — Append-only team event log (`team_chronicle`), dashboard widget, full history page
- [Formation System](systems/formation-system.md)
- [Headquarters System](systems/headquarters-system.md)
- [Item System](systems/item-system.md)
- [Spell System](systems/spell-system.md)
- [Combat System](systems/combat-system.md)
- [Marketplace System](systems/marketplace-system.md)
- [League System](systems/league-system.md)
- [Graveyard System](systems/graveyard-system.md)
- [Community System](systems/community-system.md)
- [Notification System](systems/notification-system.md) — In-app alerts (write + read API, navbar modal with unread badge)
- [Player Inactivity System](systems/player-inactivity-system.md) — Warning / release of inactive player accounts
- [NPC Simulation System](systems/npc-simulation-system.md) — Autonomous NPC tactics, training, and economy

**Screens**
- [00 Public Pages (Homepage, Wiki, News)](screens/00-public-pages.md)
- [00 Auth (Login/Register)](screens/00-auth-screens.md)
- [01 Kingdom Selection](screens/01-kingdom-selection.md)
- [02 Team Dashboard](screens/02-team-dashboard.md)
- [02a Team Chronicle](screens/02a-team-chronicle.md)
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
- [14 Calendar](screens/14-calendar.md)
- [15 Marketplace](screens/15-marketplace.md)
- [16 Graveyard](screens/16-graveyard.md)
- [17 Community](screens/17-community.md)
- [18 Arena Management](screens/18-arena-management.md)
- [19 Player Profile & Settings](screens/19-player-profile-settings.md)

**Meta**
- [UI & CSS Design Guidelines](ui-guidelines.md) — Design tokens, atomic component model (atoms/molecules), BEM naming, Tailwind usage rules, Stimulus controller conventions, and accessibility requirements
- [UI Agent Cheatsheet](ui-agent-cheatsheet.md) — One-page UI reference for agents (layout decision tree, forbidden utilities)
- [Backend Agent Cheatsheet](backend-agent-cheatsheet.md) — PHP/Symfony reference; `bash scripts/check-backend-docs.sh` (routes, entities, enums, i18n)
- [Screen → Code Map](screen-code-map.md) — Which controllers, templates, and Stimulus implement each screen
- [AGENTS.md](../AGENTS.md) — Entry point for AI assistants (task routing, Cursor rules/skills, no automatic commands)
- [README.md](../README.md) — Project overview and dev setup
- [`future/`](future/) — Deferred features not currently planned (world events, dungeons, quests, crafting)
- [`_deferred/`](../_deferred/) — Parked files (CHANGELOG.md, CONTRIBUTING.md, legacy-migration.md); not actively maintained until the project reaches a stable version

---

## Current Implementation Status

This table provides a snapshot of implemented features versus placeholders:

| Feature / Area | Service Layer | Web UI / Controllers | API (V1) Endpoints | Status / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Authentication** | Fully Implemented | Implemented | N/A (Session-based) | Register, verification, login, password reset. Account settings modal (language, UI prefs, email change, delete account). |
| **Kingdom & Locale**| Fully Implemented | Implemented | Implemented | Data loading from JSON, capacity calculations. Locale switcher (`/change-locale/{locale}`) implemented. |
| **Team / Dashboard**| Fully Implemented | Implemented | Implemented | Dashboard, settings, **team history chart** modal, financial records at `/app/finance`, **team chronicle** (recent events + `/app/chronicle`). |
| **Hero Roster** | Fully Implemented | Implemented | Implemented | Hero CRUD, rename, summoning chamber, `HeroGenerator`, **base OVR + complex rating** (`HeroRatingCalculator`), **personality trait badges** (roster + detail). |
| **Summoning** | Fully Implemented | Implemented | Implemented | Summoning random compatible race based on arena adaptation, cooldowns, `TeamSummonHistory` logging, **trait reveal on summon card**. |
| **Training** | Fully Implemented | Implemented | Implemented | Training calculations + automated tick processing. |
| **Calendar & Ticks**| Fully Implemented | Implemented | Implemented | Calendar page at `/app/calendar`; kingdom feed API; scheduled tick generation. |
| **Formations** | Fully Implemented | Implemented | Implemented | Formation CRUD, slot assignment, strategy JSON. |
| **Headquarters** | Fully Implemented | Implemented | Implemented | 7 facilities (no Forge); HQ hub with facility panels; upgrades/downgrades, arena adaptation, passive bonuses. Arena & Summoning panels via `?facility=`. |
| **Items** | Fully Implemented | Implemented | Implemented | Inventory, equip/unequip, dismantle. |
| **Spells** | Fully Implemented | Implemented | Implemented | Spell library, learning, slot equipping. |
| **Leagues** | Fully Implemented | Implemented | Implemented | `LeagueFixtureScheduler`, `SeasonTransitionService`, `LeagueService`, and `Api\V1\LeagueController` implemented; league match tick initiates lockstep wave cohort simulation. |
| **Combat** | Fully Implemented | Implemented | Implemented | Persisted cohort **wave Messenger** lockstep rounds (max 200, `stalled` isolation, resume catch-up) via deterministic turn engine loop; battle viewer at `/app/battles/{id}` and API at `/api/v1/battles/{id}` — see [combat-system.md](systems/combat-system.md). |
| **World Events** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** — see [future/world-events-system.md](future/world-events-system.md). |
| **Dungeons** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [future/dungeon-system.md](future/dungeon-system.md). Backend removed from codebase. |
| **Marketplace** | Fully Implemented | Implemented | Implemented | Listings with hero ratings and **trait** on hero cards; browse filter/sort by value and OVR (cached DB columns). |
| **Community** | Fully Implemented | Implemented | Implemented | Messaging, forum threads/posts, and content filtering fully functional. |
| **Graveyard** | Fully Implemented | Implemented | Implemented | `GraveyardService` + dismissal & combat death flows; memorial wall at `/app/graveyard`; `GET /api/v1/graveyard/*`. |
| **Quests** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [future/quest-system.md](future/quest-system.md). |
| **Crafting** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [future/crafting-system.md](future/crafting-system.md). Backend and UI removed from codebase. |
| **Alliances & Guilds** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 9) — see [roadmap.md](roadmap.md). |
| **Arena Management** | Partially Implemented | Implemented (HQ panel) | Implemented (status + ticket price) | **Active Milestone 7** — Home-match revenue model; arena panel in HQ (`/app/hq?facility=arena`); `/app/arena` is a redirect; `POST /api/v1/hq/arena/tickets/price` implemented. Extended analytics UI & friendly scheduling pending. |
| **Economy / Finance** | Fully Implemented | Implemented | Implemented | Royal Treasury weekly distribution, financial crisis, marketplace at `/app/marketplace`, ledger at `/app/finance`; `GET /api/v1/finance/*` implemented. |
| **Notifications** | Fully Implemented | Implemented | Implemented | Write + read API, navbar modal with unread badge. See [notification-system.md](systems/notification-system.md). |

---

## How to use
- Each system/screen file is a template to fill with implementation details: data models, API routes, DTOs, and services.
- Link to existing docs rather than copying large sections.
- Consult [known-issues.md](known-issues.md) for gaps that need resolution before implementation.

