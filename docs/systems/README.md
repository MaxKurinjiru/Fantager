# Systems (live domain rules)

Canonical **gameplay contracts**. On conflict with [product/overview.md](../product/overview.md), this folder wins until alignment Wave 2.

Open design questions: [product/known-issues.md](../product/known-issues.md) — do not invent mechanics for **Open** rows.

The status table below is the live-contract snapshot after A1.1. Alignment waves 0–8 are complete ([../roadmap/current.md](../roadmap/current.md)). Remaining leftovers live in each system doc’s Remaining work section.

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

Honest snapshot of the **live contract**. “Fully Implemented” means no open Wave 2–7 gap still contradicts that row. Remaining gaps are in Notes with known-issue / alignment pointers. Alignment waves 0–8 are complete ([../roadmap/current.md](../roadmap/current.md)).

| Feature / Area | Service Layer | Web UI / Controllers | API (V1) Endpoints | Status / Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Authentication** | Implemented | Implemented | N/A (Session-based) | Register, verification, login, password reset. Account settings modal (language, UI prefs, email change, delete). Password change, notification prefs, and privacy extras stay **Planned** (A8.6 keep Planned). |
| **Kingdom & Locale** | Implemented | Implemented | Implemented | JSON load, capacity, locale switcher (`GET /change-locale/{locale}` on the **guest/public navbar** and in **Account Settings**; not on the authenticated game navbar). Multi-tenant `kingdom_id`, not a separate process. `Kingdom.level_cap` **20**, `xp_modifier` at XP award (A7.1 / [#6](../product/known-issues.md)). |
| **Team / Dashboard** | Implemented | Implemented | Implemented | Dashboard, settings, **team history chart**, `/app/finance`, **team chronicle**. Chemistry is **flavor for matches** (A7.2 / [#7](../product/known-issues.md) Resolved): live for dashboard, fan club / attendance, league tie-break; combat does not read it. SSE stays planned (A4.2). |
| **Hero Roster** | Implemented (core) | Implemented (core) | Implemented | CRUD, rename, summoning, `HeroGenerator`, base OVR + complex rating, trait badges. Match XP writes lifetime `Hero.xp` and raises stored `Hero.level` (A7.1 / [#6](../product/known-issues.md)). Roster extras Cut (A7.7 / [#13](../product/known-issues.md) Resolved): no item icons, morale on card, tired/training status, quick actions, batch training, multi-select, or level filter. |
| **Summoning** | Implemented | Implemented | Implemented | Compatible-race summon, cooldowns, `TeamSummonHistory`, trait reveal. Shared pool: relationship ≥ 50, Genie adaptation ≥ 80 → Genie / Human / Elf (A7.9 / [#14](../product/known-issues.md)). `summon_stat_total_cap` unread and not shown on HQ (A6.5). |
| **Training** | Fully Implemented | Implemented | Implemented | Training calculations + weekly tick processing. Library `xp_gain_pct` multiplies match XP that now levels heroes (A7.1 / [#6](../product/known-issues.md)). |
| **Calendar & Ticks** | Fully Implemented | Implemented | Implemented | `/app/calendar`, kingdom feed API, scheduled ticks. Dungeon/crafting ticks are deferred, not in the runner. |
| **Formations** | Implemented | Implemented | Implemented | CRUD, slot assignment, lineup drag-and-drop, per-slot L1/L2 editor (A7.6 / [#12](../product/known-issues.md)). **No synergy** (A7.5 Cut / [#9](../product/known-issues.md) Resolved). Save allows 0–6 slots; duplicate `hero_id` rejected (A6.1 / #10). Kickoff: assigned → default → roster fill; forfeit if < 6 combat-ready. L3 sequences Cut. |
| **Headquarters** | Implemented | Implemented | Implemented | 7 facilities (no Forge); hub panels; upgrades/downgrades, arena adaptation, live passive bonuses. Arena & Summoning via `?facility=`. Chamber `summon_stat_total_cap` computed but unread and omitted from cards/API (A6.5). Visual themes / visitor access / morale-over-time not live. |
| **Items** | Implemented | Implemented | Implemented | Inventory, equip/unequip, dismantle. Nine slots (`ring` + `ring_2`); two-handed occupancy and dual-wield locked (A7.3 / [#8](../product/known-issues.md)). Enchanting not in the live game (A7.4 / [#2](../product/known-issues.md) Resolved). Equipment is hero-detail tab, not inventory DnD. |
| **Spells** | Implemented | Implemented | Implemented | Library, learning, slot equip. Seeded catalog T1–T5 (two per school per tier) (A7.11 / [#16](../product/known-issues.md)). No essence. |
| **Leagues** | Implemented | Implemented | Implemented | `LeagueFixtureScheduler`, `SeasonTransitionService`, standings, lockstep match tick. Win-streak / derby / relegation-pressure morale, veteran leadership, junior bonus XP not live. |
| **Combat** | **MVP / partial** | Replay | Implemented | Engine + replay: wave lockstep (max 200, `stalled` isolation), L0 spell pick, L1/L2 JSON + player editor (A7.6 / [#12](../product/known-issues.md)), defensive targeting, fatigue/morale/Orc/Ent formulas, post-match XP/form/fatigue, snapshot HP playback, full AP movement. HUD: emblems + match type (morale/queue/sidebar/skip cut). Haste/Shock on initiative sort (`ENGINE_VERSION` 7), not AP. [#9](../product/known-issues.md) Resolved (A7.5): morale / race synergy / simulate Cut. `MatchType::Arena` reserved, unused (A6.2). See [combat-system.md](combat-system.md#follow-up-contract-after-61). |
| **World Events** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** — see [world-events-system.md](../future/world-events-system.md). |
| **Dungeons** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [dungeon-system.md](../future/dungeon-system.md). Backend removed from codebase. |
| **Marketplace** | Implemented | Implemented | Implemented | Listings with ratings and **trait**; filter/sort by value and OVR. Player buys have no race filter. NPC hero buys use the shared summoning pool (A7.9 / [#14](../product/known-issues.md)). Listing prices must sit in the suggested band (−50% / +100%) (A7.10 / [#15](../product/known-issues.md)). |
| **Community** | Implemented (core) | Implemented (core) | Implemented (core) | Mail, forum threads/posts, content filter, public profiles. `is_pinned` is stored and displayed but **unsettable** (A6.3 Cut; no pin API, no forum admin). Public wiki CMS stays **Planned** (A8.5; no `/wiki` controller). Trade/league emails not live. |
| **Graveyard** | Fully Implemented | Implemented | Implemented | Dismissals + permanent combat death; memorial wall at `/app/graveyard`; `GET /api/v1/graveyard/*`. Reserved causes `age` / `retired` / `death` have no writers (A6.2). |
| **Quests** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [quest-system.md](../future/quest-system.md). |
| **Crafting** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 8) — see [crafting-system.md](../future/crafting-system.md). Backend and UI removed from codebase. |
| **Alliances & Guilds** | Not Implemented | Not Implemented | Not Implemented | ⏸️ **Deferred / Out of Scope** (Milestone 9) — see [history.md](../roadmap/history.md). |
| **Arena Management** | Implemented | Implemented (HQ panel) | Implemented (status + ticket price + friendlies + analytics) | Home-match revenue; friendly PvP (`/app/friendly-matches`, Wed/Sun 18:00). Attendance analytics in the HQ panel (last 18 payouts: table + chart + summary) ([#4](../product/known-issues.md) Resolved / A7.8). Combat home-advantage stays planned. |
| **Economy / Finance** | Implemented | Implemented | Implemented | Royal Treasury, financial crisis, marketplace, ledger at `/app/finance`; `GET /api/v1/finance/*`. Marketplace listing band is live (A7.10 / [#15](../product/known-issues.md)). Ledger types `spell_slot_cost` / `morale_restoration` reserved, no writers (A6.2). |
| **Notifications** | Implemented | Implemented | Implemented | Write + read API, navbar modal with unread badge. List `limit` default **10**, max **100** (`NotificationController`). Title/body rendered at **read-time locale** from keys (A6.4). Unused types: `league_update`, `event_started`, `spell_learned` (A6.2). See [notification-system.md](notification-system.md). |
| **NPC simulation** | Implemented | N/A (tick-driven) | N/A | Autonomous NPC tactics, training, and economy on ticks. Daily summons and marketplace hero buys share the player race pool (A7.9 / [#14](../product/known-issues.md)). See [npc-simulation-system.md](npc-simulation-system.md). |
| **Player inactivity** | Implemented | N/A (email + in-app) | N/A | Warning at 21 days, team release at 28 days; no dashboard banner (by design). See [player-inactivity-system.md](player-inactivity-system.md). |
