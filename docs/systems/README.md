# Systems (live domain rules)

Canonical **gameplay contracts**. On conflict with [product/overview.md](../product/overview.md), this folder wins until alignment Wave 2.

Open design questions: [product/known-issues.md](../product/known-issues.md) — do not invent mechanics for **Open** rows.

The status table below is the live-contract snapshot after A1.1. Remaining work is queued in [../roadmap/current.md](../roadmap/current.md).

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

Honest snapshot of the **live contract**. “Fully Implemented” means no open Wave 2–7 gap still contradicts that row. Remaining gaps are in Notes with known-issue / alignment pointers. During waves 0–8, [../roadmap/current.md](../roadmap/current.md) is the work queue.

| Feature / Area | Service Layer | Web UI / Controllers | API (V1) Endpoints | Status / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Authentication** | Implemented | Implemented | N/A (Session-based) | Register, verification, login, password reset. Account settings modal (language, UI prefs, email change, delete). Password change, notification prefs, and privacy extras stay planned (A8.6 / A4.1). |
| **Kingdom & Locale** | Implemented | Implemented | Implemented | JSON load, capacity, locale switcher (`GET /change-locale/{locale}` on the **guest/public navbar** and in **Account Settings**; not on the authenticated game navbar). Multi-tenant `kingdom_id`, not a separate process. `Kingdom.level_cap` / `xp_modifier` unused until [#6](../product/known-issues.md). |
| **Team / Dashboard** | Implemented | Implemented | Implemented | Dashboard, settings, **team history chart**, `/app/finance`, **team chronicle**. Chemistry is **display-only** until [#7](../product/known-issues.md). SSE stays planned (A4.2). |
| **Hero Roster** | Implemented (core) | Implemented (core) | Implemented | CRUD, rename, summoning, `HeroGenerator`, base OVR + complex rating, trait badges. Match XP writes `Hero.xp`; no production `levelUp()` ([#6](../product/known-issues.md)). Roster extras (item icons, morale on card, batch training) not live (A4.2 / A7.7). |
| **Summoning** | Implemented | Implemented | Implemented | Compatible-race summon, cooldowns, `TeamSummonHistory`, trait reveal. **Genie dual rule:** players use relationship ≥ 50 (Genie can summon all races); NPC marketplace may apply a stricter Genie-only filter (A7.9). `summon_stat_total_cap` unread (A6.5). |
| **Training** | Fully Implemented | Implemented | Implemented | Training calculations + weekly tick processing. Library `xp_gain_pct` multiplies match XP that does not yet level up ([#6](../product/known-issues.md)). |
| **Calendar & Ticks** | Fully Implemented | Implemented | Implemented | `/app/calendar`, kingdom feed API, scheduled ticks. Dungeon/crafting ticks are deferred, not in the runner. |
| **Formations** | Partial | Implemented (lineup) | Implemented | CRUD, slot assignment, lineup drag-and-drop. **No synergy.** Save allows 0–6 slots and duplicate `hero_id` ([#10](../product/known-issues.md)); kickoff forfeits understaffed teams. Per-slot L1/L2 editor not in UI (A7.6). |
| **Headquarters** | Implemented | Implemented | Implemented | 7 facilities (no Forge); hub panels; upgrades/downgrades, arena adaptation, passive bonuses. Arena & Summoning via `?facility=`. Chamber total-cap unused (A6.5). Visual themes / visitor access / morale-over-time not live. |
| **Items** | Partial | Implemented | Implemented | Inventory, equip/unequip, dismantle. One `ring` slot; two-handed / dual-wield occupancy open ([#8](../product/known-issues.md)). Enchanting undefined ([#2](../product/known-issues.md)). Equipment is hero-detail tab, not inventory DnD. |
| **Spells** | Implemented | Implemented | Implemented | Library, learning, slot equip. Seeded catalog T1–T2; mastery can reach T5 (A7.11). No essence. |
| **Leagues** | Implemented | Implemented | Implemented | `LeagueFixtureScheduler`, `SeasonTransitionService`, standings, lockstep match tick. Win-streak / derby / relegation-pressure morale, veteran leadership, junior bonus XP not live. |
| **Combat** | **MVP / partial** | Replay | Implemented | Engine + replay: wave lockstep (max 200, `stalled` isolation), L0 spell pick, defensive targeting, fatigue/morale/Orc/Ent formulas, post-match XP/form/fatigue, snapshot HP playback, full AP movement. HUD extras, Haste/Shock initiative, in-combat synergy, `POST /api/v1/combat/simulate` open ([#9](../product/known-issues.md)). See [combat-system.md](combat-system.md#follow-up-contract-after-61). |
| **World Events** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** — see [world-events-system.md](../future/world-events-system.md). |
| **Dungeons** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [dungeon-system.md](../future/dungeon-system.md). Backend removed from codebase. |
| **Marketplace** | Implemented | Implemented | Implemented | Listings with ratings and **trait**; filter/sort by value and OVR. Player buys have no race filter. NPC hero buys: compatible pool, except Genie-adapted NPCs may buy Genie-only (A7.9). Price floor/ceiling not live (A7.10). |
| **Community** | Implemented (core) | Implemented (core) | Implemented (core) | Mail, forum threads/posts, content filter, public profiles. `is_pinned` is stored but has **no write API** (A6.3). Public wiki CMS planned (A8.5). Trade/league emails not live. |
| **Graveyard** | Fully Implemented | Implemented | Implemented | Dismissals + permanent combat death; memorial wall at `/app/graveyard`; `GET /api/v1/graveyard/*`. |
| **Quests** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [quest-system.md](../future/quest-system.md). |
| **Crafting** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [crafting-system.md](../future/crafting-system.md). Backend and UI removed from codebase. |
| **Alliances & Guilds** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 9) — see [history.md](../roadmap/history.md). |
| **Arena Management** | Partial | Implemented (HQ panel) | Implemented (status + ticket price + friendlies) | Home-match revenue; friendly PvP (`/app/friendly-matches`, Wed/Sun 18:00). Extended attendance analytics UI still pending ([#4](../product/known-issues.md), A7.8). |
| **Economy / Finance** | Implemented | Implemented | Implemented | Royal Treasury, financial crisis, marketplace, ledger at `/app/finance`; `GET /api/v1/finance/*`. Price floor/ceiling not live (A7.10). |
| **Notifications** | Implemented | Implemented | Implemented | Write + read API, navbar modal with unread badge. List `limit` default **10**, max **100** (`NotificationController`). Title/body frozen at **write-time locale** (A6.4). Unused types: `league_update`, `event_started`, `spell_learned` (A6.2). See [notification-system.md](notification-system.md). |
| **NPC simulation** | Implemented | N/A (tick-driven) | N/A | Autonomous NPC tactics, training, and economy on ticks. Daily summons use `SummoningService` (player race pool); marketplace hero buys may Genie-only-filter (A7.9). See [npc-simulation-system.md](npc-simulation-system.md). |
| **Player inactivity** | Implemented | N/A (email + in-app) | N/A | Warning at 21 days, team release at 28 days; no dashboard banner (by design). See [player-inactivity-system.md](player-inactivity-system.md). |
