# Alignment Roadmap — Docs vs Implementation

Active work tracker after the 15 Oct 2026 audit. Historical feature chronicle: [history.md](history.md) (Milestones 1–9). Design blockers: [product/known-issues.md](../product/known-issues.md).

Docs layout (15 Oct 2026): views live under `docs/guides/`, `docs/architecture/`, `docs/product/`, `docs/roadmap/`. Old root filenames are stubs. Index: [../README.md](../README.md).

**AI agents:** Do one **unchecked step** at a time, in wave order. Do not skip ahead. Do not invent mechanics for steps marked **Design first** — stop and ask. After a step: tick the checkbox, update this file’s wave table, and update [systems/README.md](../systems/README.md) status / [architecture/routes.md](../architecture/routes.md) / [architecture/entities.md](../architecture/entities.md) / [product/known-issues.md](../product/known-issues.md) when that step says so.

---

## How to finish a finding

Each step has an **Action**:

| Action | Meaning |
|--------|---------|
| **Cut** | Remove or park the claim. Live code stays. Move leftover design to `docs/future/` or a Planned section — do not leave it looking live. |
| **Align docs** | Rewrite the spec to match code. No behaviour change. |
| **Implement** | Change code (and tests) to match an already-locked spec. |
| **Design first** | Product decision required. Do not code a new formula, slot, or UI. Record the decision in [product/known-issues.md](../product/known-issues.md), then add/execute a follow-up Implement or Cut step. |

**Done when:** the listed files no longer contradict each other, and a second reader can tell live vs planned vs deferred.

**Out of scope here:** Milestone 8/9 (`docs/future/`), alliances, dungeons, quests, crafting, world events — unless a step explicitly **cuts** leftover mentions of them from live docs.

---

## Canonical sources (locked)

Until a later step changes this, conflicts resolve as:

1. **Live rules:** matching `docs/systems/*.md` + open rows in [product/known-issues.md](../product/known-issues.md)
2. **Live UI:** [architecture/screen-code-map.md](../architecture/screen-code-map.md) + Twig/Stimulus (not the wish-list in [screens/overview-legacy.md](../screens/overview-legacy.md))
3. **Routes / schema:** [architecture/routes.md](../architecture/routes.md), [architecture/entities.md](../architecture/entities.md) — keep them true to code
4. **[product/overview.md](../product/overview.md):** overview only; **systems win** until Wave 2 rewrites it
5. **[systems/README.md](../systems/README.md) status table:** snapshot; this file is more current while alignment is in progress

Do not treat [screens/overview-legacy.md](../screens/overview-legacy.md) or unchecked screen “Displayed Information” lists as implementation contracts.

---

## Progress

| Wave | Theme | Status |
|------|--------|--------|
| 0 | Agent routing + this tracker | ✅ Done |
| 1 | Honest status (README, known-issues, historical roadmap) | ✅ Done |
| 2 | Strip fiction from `product/overview.md` | ✅ Done |
| 3 | Maps: routes, entities, API/arch examples | ✅ Done |
| 4 | Screen contracts vs actual UI | ✅ Done |
| 5 | System docs leftovers (“Sections to fill”, stale sentences) | ✅ Done |
| 6 | Cheap code alignment (validation, dead enums, i18n) | ⏳ Not started |
| 7 | Design-gated gameplay (XP, chemistry, combat polish, items) | ⏳ Not started |
| 8 | Infra honesty (CI tests, health, legacy DB, wiki) | ⏳ Not started |

Milestone 7 arena **analytics UI** is Wave 7 (A7.8), not a separate M7 track.

---

## Wave 0 — Routing

### A0.1 Index this tracker

- **Action:** Align docs
- **Files:** [../README.md](../README.md), [AGENTS.md](../../AGENTS.md), [guides/backend.md](../guides/backend.md), [.github/copilot-instructions.md](../../.github/copilot-instructions.md), [history.md](history.md)
- **Done when:** “read first” paths point here for alignment work; historical M1–9 stay in `history.md` with an active-work banner.

- [x] A0.1 Index this tracker (created with this file)

### A0.2 Known-issues is for design only

- **Action:** Align docs
- **Files:** [product/known-issues.md](../product/known-issues.md)
- **Done when:** Open design rows exist for chemistry, item occupancy, combat leftovers, formation save policy; enchanting no longer “blocks Phase 6”; this roadmap is linked as the work queue.

- [x] A0.2 Expand known-issues (#2, #6–#10 + pointer to this file)

---

## Wave 1 — Honest status

Do not change game behaviour. Make “Fully Implemented” mean the live contract.

### A1.1 Systems status table

- **Action:** Align docs
- **Files:** [systems/README.md](../systems/README.md) § Current Implementation Status
- **Edits:**
  - Combat → **MVP / partial** (engine + replay; HUD and synergy open)
  - Heroes → note XP without level-up ([product/known-issues.md](../product/known-issues.md) #6)
  - Items → note occupancy / ring count open (#8)
  - Formations → no synergy; save ≠ kickoff rules
  - Add **NPC simulation** and **player inactivity** rows (implemented, currently missing)
  - Community → pin has no write API; wiki still planned
  - Notifications → write-time locale; unused types
- **Done when:** no row says Fully Implemented if a Wave 2–7 item still contradicts it.

- [x] A1.1 Systems status table

### A1.2 Historical roadmap banner and M1–6 caveat

- **Action:** Align docs
- **Files:** [roadmap/history.md](history.md)
- **Edits:** Active-work banner → this file. Scope guardrail: M8/M9 still parked; **alignment waves are the active work**, not “M1–6 fully complete”. Cross-cutting: PHPUnit/Playwright are Wave 8, not done. M4.1 drag-and-drop inventory and M4.3 synergies must not stay ✅ without a footnote.
- **Done when:** a new agent cannot conclude inventory DnD and formation synergies shipped.

- [x] A1.2 Historical roadmap honesty

### A1.3 Cheatsheet scope guardrails

- **Action:** Align docs
- **Files:** [guides/backend.md](../guides/backend.md)
- **Edits:** Point “before you code” at this file. Keep combat/enchanting guardrails; add chemistry (#7), item occupancy (#8), XP curve (#6).

- [x] A1.3 Cheatsheet guardrails

---

## Wave 2 — `product/overview.md` vs live systems

Rewrite summary so it does not sell unbuilt mechanics. Prefer **Cut** to live text + one-line pointer to systems/ or `future/`. Do not add new formulas.

### A2.1 Front matter and TOC

- **Action:** Cut
- **Files:** [product/overview.md](../product/overview.md)
- **Edits:** Label as overview; “systems win on conflict”. Move dungeon/crafting **out of core TOC** into Deferred (already have `future/`). Drop Crafting Boost from kingdom settings or mark deferred.

- [x] A2.1 Summary role + TOC

### A2.2 Kingdom, calendar, economy claims

- **Action:** Cut / Align docs
- **Files:** [product/overview.md](../product/overview.md) §2.1–2.3
- **Cut or reword:** Kingdom as a separate server instance → multi-tenant `kingdom_id`. Dungeon/crafting ticks. Price floor/ceiling. Diminishing returns as if live. Email/trade anti-fraud as if live. Season “2–4 weeks” → 77 days (or point at [calendar-system.md](../systems/calendar-system.md)).

- [x] A2.2 Kingdom / calendar / economy

### A2.3 Heroes, training, team, formation

- **Action:** Cut / Align docs
- **Files:** [product/overview.md](../product/overview.md) §2.4–2.6.1
- **Cut or reword:** XP/leveling as a live loop → #6. In-combat race synergy as live → deferred. Suggested formations, clone, “all 6 slots required at save”, dual-wield targeting essays that the UI cannot set. Point formation save rules at [formation-system.md](../systems/formation-system.md).

- [x] A2.3 Heroes / formation

### A2.4 HQ, items, spells, combat, league

- **Action:** Cut / Align docs
- **Files:** [product/overview.md](../product/overview.md) §2.7–2.12
- **Cut:** HQ visual themes, facility layout, visitor access, combat home advantage as live, HQ morale-over-time as live. Item **two rings**, dual-wield, two-handed occupying both hands **unless #8 is decided Implement** (until then: match [item-system.md](../systems/item-system.md) — 8 slots, one ring). League win-streak/derby/relegation-pressure morale, veteran leadership, junior bonus XP, age-diversity bonus.

- [x] A2.4 HQ / items / league fiction

### A2.5 Graveyard, community, leftover ticks

- **Action:** Align docs
- **Files:** [product/overview.md](../product/overview.md) §2.13–end
- **Edits:** Graveyard includes **dismissals**, not only combat death. Community: in-app mail/forum live; **email for trades/league** not live (auth + inactivity only). Remove live-looking dungeon/crafting sections or keep a single Deferred pointer.

- [x] A2.5 Graveyard / community / deferred

---

## Wave 3 — Maps and examples

### A3.1 Route map: one live list

- **Action:** Align docs
- **Files:** [architecture/routes.md](../architecture/routes.md)
- **Edits:** One Combat table (today there are two). Deferred dungeon/crafting/quest routes **only** as a pointer to `future/` — not fake controller names in the live list. Mark `/app/inventory` as **redirect**. Drop or relabel planned `POST /api/v1/heroes/{id}/train` and `convert-trainer` (live: training trainer assign/promote). Health/wiki/simulate stay **planned**. Recalculate implemented counts. Unify notification `limit` with [notification-system.md](../systems/notification-system.md) (code is source).

- [x] A3.1 Route map

### A3.2 Entity reference hygiene

- **Action:** Align docs
- **Files:** [architecture/entities.md](../architecture/entities.md)
- **Edits:** Fix domain numbering (skip 13–15). Enum count vs `src/Enum` (41 files; table includes deferred `DungeonResult` / `CraftingStatus`). Design decisions: remove live `DungeonEnemy` / crafting-material / `event` table as if present. `MatchType::Arena`, `MoraleRestoration`, `hero_retired`, reserved `MemorialCause`, unused notification types: **reserved / unused** until Wave 6.

- [x] A3.2 Entity reference

### A3.3 API design + arch-spec examples

- **Action:** Align docs
- **Files:** [architecture/api.md](../architecture/api.md), [architecture/overview.md](../architecture/overview.md)
- **Edits:** Replace `/api/v1/heroes/{id}/train` and web `/heroes` examples with real paths (`/app/heroes`, `/api/v1/training/trainers/{id}/assign`).

- [x] A3.3 Stale REST examples

### A3.4 Hero/team system stale sentences

- **Action:** Align docs
- **Files:** [hero-system.md](../systems/hero-system.md), [team-system.md](../systems/team-system.md)
- **Edits:** `/api/heroes` → `/api/v1/heroes`. Chronicle match results are **implemented**, not future. Document chemistry formula (CHA offset, −15 hostile) as **display-only** until #7.

- [x] A3.4 Hero/team stale API and chronicle

---

## Wave 4 — Screen contracts

For each screen: **Implemented** = what Twig/API does. **Planned** = the rest. Empty “Sections to fill” go away (delete or fill with a pointer).

### A4.1 Overview + public/auth

- **Action:** Align docs
- **Files:** [screens/overview-legacy.md](../screens/overview-legacy.md), [00-public-pages.md](../screens/00-public-pages.md), [00-auth-screens.md](../screens/00-auth-screens.md), [01-kingdom-selection.md](../screens/01-kingdom-selection.md), [19-player-profile-settings.md](../screens/19-player-profile-settings.md)
- **Edits:** Overview not Draft-as-source-of-truth; crafting not a core screen. Locale switcher is **guest/public navbar + Account Settings** (not on the authenticated game navbar; public-pages had claimed the inside section had none). Screen 19: English only (was “Backdrop zavírání modalů”). Wiki/news admin stay Planned. Password change / notification prefs / privacy stay Planned.

- [x] A4.1 Overview + public + settings copy

### A4.2 Dashboard, roster, formation, inventory, combat

- **Action:** Align docs (UI Implement is Wave 7 if product wants the old lists)
- **Files:** [02-team-dashboard.md](../screens/02-team-dashboard.md), [03-hero-roster.md](../screens/03-hero-roster.md), [07-formation-setup.md](../screens/07-formation-setup.md), [10-item-equipment.md](../screens/10-item-equipment.md), [12-combat-battle.md](../screens/12-combat-battle.md), [architecture/screen-code-map.md](../architecture/screen-code-map.md)
- **Roster live:** name, race, level, trait, age, form, fatigue, attributes, inspect, client filter/sort. **Not live:** item icons, morale on card, tired/training status, quick-action dropdown, batch training, multi-select, level filter control.
- **Formation:** keep the existing Planned table; summary/overview must not re-add synergies/clone/simulate as live. Drag-and-drop **is** live for lineup, **not** for equipment.
- **Inventory:** route redirects; equipment is hero-detail tab, click-to-equip paperdoll.
- **Combat:** keep “Still open” list; overview must not describe live logos/morale HUD/initiative queue.
- **Dashboard:** SSE stays Planned.

- [x] A4.2 Core game screens

### A4.3 Remaining screens leftover sections

- **Action:** Align docs
- **Files:** [06-trainer-management.md](../screens/06-trainer-management.md), [17-community.md](../screens/17-community.md), others still containing “Sections to fill”
- **Done when:** no screen file is an empty template.

- [x] A4.3 Remaining screen stubs

---

## Wave 5 — System docs leftovers

### A5.1 Fill or delete “Sections to fill”

- **Action:** Align docs
- **Files:** [kingdom-system.md](../systems/kingdom-system.md), [hero-system.md](../systems/hero-system.md), [team-system.md](../systems/team-system.md), [auth-system.md](../systems/auth-system.md), [formation-system.md](../systems/formation-system.md) (purpose still says “synergy calculations”)
- **Edits:** Replace template headings with live behaviour or delete them. Formation purpose: drop synergy until #7/#9.

- [x] A5.1 System stub headings

### A5.2 Summoning Genie rule (docs only)

- **Action:** Align docs
- **Files:** [summoning-system.md](../systems/summoning-system.md) (already notes the split), [npc-simulation-system.md](../systems/npc-simulation-system.md), [product/overview.md](../product/overview.md) if Wave 2 missed it
- **Done when:** one paragraph states: **players** use relationship ≥ 50 (Genie can summon all races); **NPC marketplace** may apply a stricter Genie-only filter. Product change is A7.9.

- [x] A5.2 Genie dual rule documented everywhere

### A5.3 Notifications contract

- **Action:** Align docs
- **Files:** [notification-system.md](../systems/notification-system.md), [architecture/routes.md](../architecture/routes.md)
- **Edits:** Default `limit` / max from `NotificationController`. Unused types listed. Write-time translation documented until A6.4.

- [x] A5.3 Notification limits and unused types

---

## Wave 6 — Cheap code alignment

No new combat formulas. Behaviour changes only where the live system doc already implies them **or** known-issues is **Closed as Implement**.

### A6.1 Formation unique `hero_id` on save

- **Action:** Design first → then Implement or Cut
- **Blocks:** [product/known-issues.md](../product/known-issues.md) #10
- **If Implement:** `FormationService::rebuildSlots()` reject duplicate `hero_id`; API 422; tests. Kickoff forfeit for empty slots can stay.
- **If Cut:** summary already stripped in Wave 2; add one sentence “duplicates allowed until kickoff” in [formation-system.md](../systems/formation-system.md).

- [ ] A6.1 Formation duplicates (decision + execution)

### A6.2 Dead writers / reserved enums

- **Action:** Align docs + optional code
- **Files:** enums + writers + [architecture/entities.md](../architecture/entities.md)
- **Default (Cut / reserve):** keep enum values, mark **unused**: `MatchType::Arena`, `FinancialRecordType::MoraleRestoration`, `ChronicleEventType::HeroRetired`, `MemorialCause::{Age,Retired,Death}`, `NotificationType::{LeagueUpdate,EventStarted,SpellLearned}`, `FinancialRecordType::SpellSlotCost`.
- **Do not delete columns** without an explicit migrate step in this item.
- **Done when:** entity-reference lists each as unused/reserved so agents do not hook them “for completeness”.

- [ ] A6.2 Reserve unused enums

### A6.3 Forum pin

- **Action:** Design first
- **If Cut:** docs say pin is stored but unsettable (no admin API). Remove “admin use” as if it exists.
- **If Implement:** author-or-admin pin/unpin API + UI, CSRF, tests. Do not invent roles.

- [ ] A6.3 Forum pin write path or docs-only

### A6.4 Notification i18n at read time

- **Action:** Implement (same pattern as chronicle)
- **Files:** `Notification` entity (store `title_key` / `body_key` / params **or** keep strings and document freeze), `NotificationHelper`, presenter, migrations if schema changes, tests
- **Done when:** locale switch re-renders notifications like chronicle. If schema change is deferred, document write-time freeze as the locked rule and close this as Align docs — do not leave both stories.

- [ ] A6.4 Notification translation model

### A6.5 `summon_stat_total_cap`

- **Action:** Align docs (code already ignores it) **or** Implement wiring
- **Default:** keep unread; [hero-system.md](../systems/hero-system.md) already says reserved. Ensure HQ/summary do not show it as an active bonus.

- [ ] A6.5 Chamber total-cap unused everywhere

---

## Wave 7 — Design-gated gameplay

**Stop here without a human decision** on each known-issues ID. After a decision, execute in the same slice (code + systems + screens + tests).

### A7.1 XP → level curve — #6

- **Action:** Design first
- **Must decide:** XP thresholds, whether level changes the 1–20 cap, what `Kingdom.level_cap` / `xp_modifier` do, whether Library `xp_gain_pct` should exist before level-up exists.
- **Then:** call `HeroService::levelUp()` from a single production path; chronicle `hero_levelup`; tests.

- [ ] A7.1 Level-up loop

### A7.2 Team chemistry purpose — #7

- **Action:** Design first
- **Must decide:** flavor-only number **or** combat/morale modifiers (tables).
- **If flavor:** dashboard copy + systems say it does not affect matches; Loner trait text matches.
- **If live:** formulas in [combat-system.md](../systems/combat-system.md) first, then `CombatEngine`. Do not invent tables in code.

- [ ] A7.2 Chemistry

### A7.3 Item occupancy — #8

- **Action:** Design first
- **Must decide:** one ring vs two; two-handed clears `off_hand`; dual-wield allowed or not; Giant/Ent restrictions stay from `RaceConfig`.
- **Then:** `ItemService::equip()` + UI paperdoll + [item-system.md](../systems/item-system.md) + tests.

- [ ] A7.3 Hands and rings

### A7.4 Enchanting — #2

- **Action:** Design first (still no essence)
- **Default until designed:** Cut remaining live-looking mentions; keep one Open known-issue. Do not reintroduce essence.

- [ ] A7.4 Enchanting redesign or explicit never

### A7.5 Combat leftovers — #9

- **Action:** Design first, then small Implements
- **Bundle (each can be its own PR after design lock):**
  - Replay HUD: logos, match type, morale, initiative queue, skip-to-end
  - Haste/Shock initiative (or Cut from YAML/docs)
  - In-combat `morale_change` for Volatile / Battle Hardened (or Cut trait copy)
  - Race synergy tables (or Cut Loner combat text)
  - `POST /api/v1/combat/simulate` sandbox
- **Do not** change locked round-200 / fumble / KO≠death rules.

- [ ] A7.5 Combat HUD / leftover traits / simulate

### A7.6 Formation L1/L2 editor

- **Action:** Design first (UI) then Implement
- **Today:** UI writes `spell_priorities: []`; NPC may fill lists. Decide whether players get the same editor. Depends on [formation-system.md](../systems/formation-system.md) schema (already specified).

- [ ] A7.6 Per-slot tactics UI

### A7.7 Roster UI extras

- **Action:** Design first
- **Candidates from old screen 03:** morale on card, equipped icons, quick actions, batch training. Implement only what is re-specified. Default: **Cut** (already Wave 4).

- [ ] A7.7 Roster extras or confirmed cut

### A7.8 Arena analytics UI — #4

- **Action:** Implement after a short UI spec in [18-arena-management.md](../screens/18-arena-management.md)
- **Live today:** HQ arena panel, ticket price API, match-day revenue. This step is graphs/history only.

- [ ] A7.8 Arena attendance analytics

### A7.9 Genie summon rule

- **Action:** Design first
- **Pick one:** relationship matrix for everyone (current player code) **or** Genie-only summons (NPC marketplace). Implement one path in `SummoningService` + NPC.

- [ ] A7.9 Single Genie rule

### A7.10 Marketplace price guidance

- **Action:** Design first
- **Default:** Cut floor/ceiling (Wave 2). Optional later: suggested price from `complex_rating` as UI hint only (NPC already has rating-based prices).

- [ ] A7.10 Price hints or confirmed cut

### A7.11 Spell catalog T3–T5

- **Action:** Design first (content) then seed
- **Today:** seed T1–T2; mastery can reach T5. Either seed higher tiers or docs say T3–T5 are unused.

- [ ] A7.11 Spell catalog vs mastery cap

---

## Wave 8 — Infra honesty

Do not change game rules. Optional product asks stay Design first.

### A8.1 PHPUnit in CI

- **Action:** Implement
- **Files:** `.github/workflows/` (new or extend `phpstan.yml` caller)
- **Done when:** PRs that touch `src/**/*.php` or `tests/**` run `composer test` (or documented equivalent). Match [roadmap/history.md](history.md) Cross-Cutting once true.

- [ ] A8.1 PHPUnit workflow

### A8.2 Playwright E2E

- **Action:** Design first
- **If Cut:** remove Cross-Cutting Playwright claim; do not leave “ongoing”.
- **If Implement:** real suite (register → dashboard → training or summon), not only a Vitest transitive dependency.

- [ ] A8.2 Playwright or cut the claim

### A8.3 `GET /health`

- **Action:** Implement or Cut from route-map
- **If Implement:** unauthenticated liveness, no secrets.

- [ ] A8.3 Health route

### A8.4 Dual Doctrine `legacy` connection

- **Action:** Cut (recommended) or keep parked
- **Files:** `config/packages/doctrine.yaml`, [roadmap/history.md](history.md) M1.1, `_deferred/legacy-migration.md`
- **Done when:** live config does not look like a second game DB unless maintainers still use it.

- [ ] A8.4 Legacy connection

### A8.5 Public wiki

- **Action:** Design first (content model) or keep Planned
- **Default:** stay Planned; Wave 2/4 already stop calling it live.

- [ ] A8.5 Wiki stays planned or scoped

### A8.6 Password change / extra settings

- **Action:** Design first
- **Today:** out of scope on purpose. Either implement session password change or keep Planned in screen 19 only.

- [ ] A8.6 Password change

---

## Finding → step index

| Audit theme | Step |
|-------------|------|
| Dual spec (summary vs systems) | A0 canonical + Wave 2 |
| Systems status table / M1–6 “complete” | A1.1, A1.2 |
| XP without `levelUp()` | A7.1 (#6) |
| Chemistry unused in combat | A3.4, A7.2 (#7) |
| Roster screen vs card | A4.2, A7.7 |
| Two-handed / rings / dual-wield | A2.4, A7.3 (#8) |
| Enchanting “Phase 6” | A1.2, A7.4 (#2) |
| League morale fiction | A2.4 |
| Combat HUD / Haste / unused traits | A4.2, A7.5 (#9) |
| Formation save / synergies / simulate | A2.3, A4.2, A6.1, A7.6 |
| Inventory redirect / equipment DnD | A3.1, A4.2 |
| Notification i18n + limit | A5.3, A6.4 |
| Unused enums | A3.2, A6.2 |
| PHPUnit / Playwright | A8.1, A8.2 |
| Locale switcher docs | A4.1 |
| Kingdom “separate server” | A2.2 |
| Genie summon split | A5.2, A7.9 |
| Wiki / news / health | A4.1, A8.3, A8.5 |
| Forum pin | A6.3 |
| Graveyard dismissals in summary | A2.5 |
| Screen stubs / Czech in EN | A4.1, A4.3 |
| `summon_stat_total_cap` | A6.5 |
| Price floor | A2.2, A7.10 |
| Planned `/train` routes | A3.1, A3.3 |
| NPC + inactivity missing from status | A1.1 |
| Arena analytics | A7.8 (#4) |
| Spell T1–T2 seed | A7.11 |
| Legacy DB connection | A8.4 |
| Password / settings extras | A4.1, A8.6 |
| SSE dashboard | A4.2 (stay Planned) |

---

## Suggested session size

| Session | Do |
|---------|----|
| 1 | Finish A0.2 if needed, then A1.1–A1.3 |
| 2 | Wave 2 (summary) |
| 3 | Wave 3 (maps) |
| 4 | Wave 4–5 (screens + system stubs) |
| 5 | Wave 6 decisions + execution |
| 6+ | One Wave 7 ID per session, then Wave 8 |

*Last updated: 15 October 2026 — A5.3 documented the live notification contract: list `limit` default 10 / max 100 from `NotificationController`, unused types `league_update` / `event_started` / `spell_learned`, and write-time title/body translation until A6.4. No gameplay code changed. Next: A6.1 Formation duplicates (decision + execution).*
