# Systems (live domain rules)

Canonical **gameplay contracts**. On conflict with [product/overview.md](../product/overview.md), this folder wins until alignment Wave 2.

Open design questions: [product/known-issues.md](../product/known-issues.md) — do not invent mechanics for **Open** rows.

The status table below is a snapshot. During alignment waves 0–8, [../roadmap/current.md](../roadmap/current.md) is more current.

## Remaining work convention

Each system doc should open with:

```markdown
Status: Live | Partial | Planned
Screens: …    Code: ../architecture/screen-code-map.md
Open design: ../product/known-issues.md #n (if any)

## Remaining work
- [x] shipped …
- [ ] next step (Design first → known-issues)
```

Do not duplicate Remaining work in the roadmap. [../roadmap/README.md](../roadmap/README.md) only points here and to known-issues.

## System files

| Doc | Area |
|-----|------|
| [auth-system.md](auth-system.md) | Authentication, registration, sessions |
| [kingdom-system.md](kingdom-system.md) | Kingdoms / servers |
| [summoning-system.md](summoning-system.md) | Summoning chamber |
| [calendar-system.md](calendar-system.md) | Weekly ticks, schedule, season timeline |
| [friendly-match-system.md](friendly-match-system.md) | PvP practice matches |
| [economy-system.md](economy-system.md) | Gold, ledger, treasury |
| [financial-crisis-system.md](financial-crisis-system.md) | Financial crisis |
| [hero-system.md](hero-system.md) | Heroes |
| [hero-rating-system.md](hero-rating-system.md) | Base OVR, complex rating |
| [hero-chronicle-system.md](hero-chronicle-system.md) | Per-hero event log |
| [training-system.md](training-system.md) | Training |
| [team-system.md](team-system.md) | Teams |
| [team-chronicle-system.md](team-chronicle-system.md) | Team event log |
| [formation-system.md](formation-system.md) | Formations |
| [headquarters-system.md](headquarters-system.md) | HQ facilities |
| [item-system.md](item-system.md) | Items and equipment |
| [spell-system.md](spell-system.md) | Spells |
| [combat-system.md](combat-system.md) | Combat engine and replay |
| [marketplace-system.md](marketplace-system.md) | Marketplace |
| [league-system.md](league-system.md) | Leagues |
| [graveyard-system.md](graveyard-system.md) | Graveyard |
| [community-system.md](community-system.md) | Mail, forum |
| [notification-system.md](notification-system.md) | In-app notifications |
| [player-inactivity-system.md](player-inactivity-system.md) | Inactive player release |
| [npc-simulation-system.md](npc-simulation-system.md) | NPC tactics, training, economy |

## Current Implementation Status

Snapshot of implemented features versus placeholders. Alignment Wave 1 will make “Fully Implemented” match the live contract.

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
| **Combat** | Core implemented | Replay | Implemented | Wave Messenger lockstep (max 200, `stalled` isolation), L0 spell pick, defensive targeting, fatigue/morale/Orc/Ent formulas, post-match hero XP/form/fatigue, replay from snapshot HP, full AP movement (`seek_cover_ranged` holds weapon range), and `combat_log` contract aligned (`kill_score` + `spell`). See [combat-system.md](combat-system.md#follow-up-contract-after-61). |
| **World Events** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** — see [world-events-system.md](../future/world-events-system.md). |
| **Dungeons** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [dungeon-system.md](../future/dungeon-system.md). Backend removed from codebase. |
| **Marketplace** | Fully Implemented | Implemented | Implemented | Listings with hero ratings and **trait** on hero cards; browse filter/sort by value and OVR (cached DB columns). |
| **Community** | Fully Implemented | Implemented | Implemented | Messaging, forum threads/posts, and content filtering fully functional. |
| **Graveyard** | Fully Implemented | Implemented | Implemented | `GraveyardService` + dismissal & combat death flows; memorial wall at `/app/graveyard`; `GET /api/v1/graveyard/*`. |
| **Quests** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [quest-system.md](../future/quest-system.md). |
| **Crafting** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [crafting-system.md](../future/crafting-system.md). Backend and UI removed from codebase. |
| **Alliances & Guilds** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 9) — see [history.md](../roadmap/history.md). |
| **Arena Management** | Partially Implemented | Implemented (HQ panel) | Implemented (status + ticket price + friendlies) | **Active Milestone 7** — Home-match revenue; friendly PvP scheduling (`/app/friendly-matches`, Wed/Sun 18:00). Extended analytics UI still pending. |
| **Economy / Finance** | Fully Implemented | Implemented | Implemented | Royal Treasury weekly distribution, financial crisis, marketplace at `/app/marketplace`, ledger at `/app/finance`; `GET /api/v1/finance/*` implemented. |
| **Notifications** | Fully Implemented | Implemented | Implemented | Write + read API, navbar modal with unread badge. See [notification-system.md](notification-system.md). |
