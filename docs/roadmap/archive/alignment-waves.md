# Alignment waves 0–8 (archive)

Finished chronicle of the 15 Oct 2026 docs-vs-code alignment. **Not a work queue.** Do not open this file unless you need the wave chronicle. Now / next: [current.md](../current.md). Feature chronicle: [history.md](history.md). Live conflict order: [docs/README.md](../../README.md) § Canonical sources.

Do not execute this file as a backlog.

---

## Former filenames

Old `docs/*.md` root names were stubs after the 15 Oct 2026 layout. Those stubs are gone. Use the canonical path:

| Old path | Canonical |
|----------|-----------|
| `docs/game-summary.md` | [product/overview.md](../../product/overview.md) |
| `docs/known-issues.md` | [product/known-issues.md](../../product/known-issues.md) |
| `docs/api-design.md` | [architecture/api.md](../../architecture/api.md) |
| `docs/arch-spec.md` | [architecture/overview.md](../../architecture/overview.md) |
| `docs/entity-reference.md` | [architecture/entities.md](../../architecture/entities.md) |
| `docs/route-map.md` | [architecture/routes.md](../../architecture/routes.md) |
| `docs/screen-code-map.md` | [architecture/screen-code-map.md](../../architecture/screen-code-map.md) |
| `docs/ui-guidelines.md` | [guides/ui-guidelines.md](../../guides/ui-guidelines.md) |
| `docs/ui-agent-cheatsheet.md` | [guides/ui.md](../../guides/ui.md) |
| `docs/backend-agent-cheatsheet.md` | [guides/backend.md](../../guides/backend.md) |
| `docs/alignment-roadmap.md` | [roadmap/current.md](../current.md) |
| `docs/screens-overview.md` | [screens/README.md](../../screens/README.md) |
| `docs/screens/overview-legacy.md` | [`_deferred/screens-overview-legacy.md`](../../../_deferred/screens-overview-legacy.md) |
| `docs/systems/combat-formulas-draft.md` | [systems/combat-formulas.md](../../systems/combat-formulas.md) |
| `_deferred/notes.md` | [future/notes.md](../../future/notes.md) |

---

## How to finish a finding

Each step has an **Action**:

| Action | Meaning |
|--------|---------|
| **Cut** | Remove or park the claim. Live code stays. Move leftover design to `docs/future/` or a Planned section — do not leave it looking live. |
| **Align docs** | Rewrite the spec to match code. No behaviour change. |
| **Implement** | Change code (and tests) to match an already-locked spec. |
| **Design first** | Product decision required. Do not code a new formula, slot, or UI. Record the decision in [product/known-issues.md](../../product/known-issues.md), then add/execute a follow-up Implement or Cut step. |

**Done when:** the listed files no longer contradict each other, and a second reader can tell live vs planned vs deferred.

**Out of scope here:** Milestone 8/9 (`docs/future/`), alliances, dungeons, quests, crafting, world events — unless a step explicitly **cuts** leftover mentions of them from live docs.

---

## Canonical sources (locked)

This wave locked the conflict order now documented in [docs/README.md](../../README.md) § Canonical sources. Do not treat [`_deferred/screens-overview-legacy.md`](../../../_deferred/screens-overview-legacy.md) or unchecked screen “Displayed Information” lists as implementation contracts.

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
| 6 | Cheap code alignment (validation, dead enums, i18n) | ✅ Done |
| 7 | Design-gated gameplay (XP, chemistry, combat polish, items) | ✅ Done (A7.1–A7.11) |
| 8 | Infra honesty (CI tests, health, legacy DB, wiki, settings extras) | ✅ Done (A8.1–A8.6) |

Milestone 7 arena **analytics UI** is Wave 7 (A7.8), not a separate M7 track.

---

## Wave 0 — Routing

### A0.1 Index this tracker

- **Action:** Align docs
- **Files:** [../README.md](../../README.md), [AGENTS.md](../../../AGENTS.md), [guides/backend.md](../../guides/backend.md), [.github/copilot-instructions.md](../../../.github/copilot-instructions.md), [history.md](history.md)
- **Done when:** “read first” paths point here for alignment work; historical M1–9 stay in `history.md` with an active-work banner.

- [x] A0.1 Index this tracker (created with this file)

### A0.2 Known-issues is for design only

- **Action:** Align docs
- **Files:** [product/known-issues.md](../../product/known-issues.md)
- **Done when:** Open design rows exist for chemistry, item occupancy, combat leftovers, formation save policy; enchanting no longer “blocks Phase 6”; this roadmap is linked as the work queue.

- [x] A0.2 Expand known-issues (#2, #6–#10 + pointer to this file)

---

## Wave 1 — Honest status

Do not change game behaviour. Make “Fully Implemented” mean the live contract.

### A1.1 Systems status table

- **Action:** Align docs
- **Files:** [systems/README.md](../../systems/README.md) § Status snapshot
- **Edits:**
  - Combat → **MVP / partial** (engine + replay; HUD and synergy open)
  - Heroes → note XP without level-up ([product/known-issues.md](../../product/known-issues.md) #6)
  - Items → note occupancy / ring count open (#8)
  - Formations → no synergy; save ≠ kickoff rules
  - Add **NPC simulation** and **player inactivity** rows (implemented, currently missing)
  - Community → pin has no write API; wiki still planned
  - Notifications → write-time locale; unused types
- **Done when:** no row says Fully Implemented if a Wave 2–7 item still contradicts it.

- [x] A1.1 Systems status table (live Combat row later relabeled **Implemented** after A7.5; HUD locked, synergy/simulate Cut — no longer MVP/partial)

### A1.2 Historical roadmap banner and M1–6 caveat

- **Action:** Align docs
- **Files:** [roadmap/history.md](history.md)
- **Edits:** Active-work banner → this file. Scope guardrail: M8/M9 still parked; **alignment waves are the active work**, not “M1–6 fully complete”. Cross-cutting: PHPUnit/Playwright are Wave 8, not done. M4.1 drag-and-drop inventory and M4.3 synergies must not stay ✅ without a footnote.
- **Done when:** a new agent cannot conclude inventory DnD and formation synergies shipped.

- [x] A1.2 Historical roadmap honesty

### A1.3 Cheatsheet scope guardrails

- **Action:** Align docs
- **Files:** [guides/backend.md](../../guides/backend.md)
- **Edits:** Point “before you code” at this file. Keep combat/enchanting guardrails; add chemistry (#7), item occupancy (#8), XP curve (#6).

- [x] A1.3 Cheatsheet guardrails

---

## Wave 2 — `product/overview.md` vs live systems

Rewrite summary so it does not sell unbuilt mechanics. Prefer **Cut** to live text + one-line pointer to systems/ or `future/`. Do not add new formulas.

### A2.1 Front matter and TOC

- **Action:** Cut
- **Files:** [product/overview.md](../../product/overview.md)
- **Edits:** Label as overview; “systems win on conflict”. Move dungeon/crafting **out of core TOC** into Deferred (already have `future/`). Drop Crafting Boost from kingdom settings or mark deferred.

- [x] A2.1 Summary role + TOC

### A2.2 Kingdom, calendar, economy claims

- **Action:** Cut / Align docs
- **Files:** [product/overview.md](../../product/overview.md) §2.1–2.3
- **Cut or reword:** Kingdom as a separate server instance → multi-tenant `kingdom_id`. Dungeon/crafting ticks. Price floor/ceiling. Diminishing returns as if live. Email/trade anti-fraud as if live. Season “2–4 weeks” → 77 days (or point at [calendar-system.md](../../systems/calendar-system.md)).

- [x] A2.2 Kingdom / calendar / economy

### A2.3 Heroes, training, team, formation

- **Action:** Cut / Align docs
- **Files:** [product/overview.md](../../product/overview.md) §2.4–2.6.1
- **Cut or reword:** XP/leveling as a live loop → #6. In-combat race synergy as live → deferred. Suggested formations, clone, “all 6 slots required at save”, dual-wield targeting essays that the UI cannot set. Point formation save rules at [formation-system.md](../../systems/formation-system.md).

- [x] A2.3 Heroes / formation (later restored live Elf/Dwarf/Giant/Genie/Ent engine hooks that the first pass over-cut as synergy)

### A2.4 HQ, items, spells, combat, league

- **Action:** Cut / Align docs
- **Files:** [product/overview.md](../../product/overview.md) §2.7–2.12
- **Cut:** HQ visual themes, facility layout, visitor access, combat home advantage as live, HQ morale-over-time as live. Item **two rings**, dual-wield, two-handed occupying both hands **unless #8 is decided Implement** (until then: match [item-system.md](../../systems/item-system.md) — 8 slots, one ring). League win-streak/derby/relegation-pressure morale, veteran leadership, junior bonus XP, age-diversity bonus.

- [x] A2.4 HQ / items / league fiction

### A2.5 Graveyard, community, leftover ticks

- **Action:** Align docs
- **Files:** [product/overview.md](../../product/overview.md) §2.13–end
- **Edits:** Graveyard includes **dismissals**, not only combat death. Community: in-app mail/forum live; **email for trades/league** not live (auth + inactivity only). Remove live-looking dungeon/crafting sections or keep a single Deferred pointer.

- [x] A2.5 Graveyard / community / deferred (A2.1 stub anchors `#215` / `#217` dropped with the single Deferred pointer; `future/` retargeted)

---

## Wave 3 — Maps and examples

### A3.1 Route map: one live list

- **Action:** Align docs
- **Files:** [architecture/routes.md](../../architecture/routes.md)
- **Edits:** One Combat table (today there are two). Deferred dungeon/crafting/quest routes **only** as a pointer to `future/` — not fake controller names in the live list. Mark `/app/inventory` as **redirect**. Drop or relabel planned `POST /api/v1/heroes/{id}/train` and `convert-trainer` (live: training trainer assign/promote). Health/wiki/simulate stay **planned**. Recalculate implemented counts. Unify notification `limit` with [notification-system.md](../../systems/notification-system.md) (code is source).

- [x] A3.1 Route map

### A3.2 Entity reference hygiene

- **Action:** Align docs
- **Files:** [architecture/entities.md](../../architecture/entities.md)
- **Edits:** Fix domain numbering (skip 13–15). Enum count vs `src/Enum` (41 files; table includes deferred `DungeonResult` / `CraftingStatus`). Design decisions: remove live `DungeonEnemy` / crafting-material / `event` table as if present. `MatchType::Arena`, `MoraleRestoration`, `hero_retired`, reserved `MemorialCause`, unused notification types: **reserved / unused** until Wave 6.

- [x] A3.2 Entity reference

### A3.3 API design + arch-spec examples

- **Action:** Align docs
- **Files:** [architecture/api.md](../../architecture/api.md), [architecture/overview.md](../../architecture/overview.md)
- **Edits:** Replace `/api/v1/heroes/{id}/train` and web `/heroes` examples with real paths (`/app/heroes`, `/api/v1/training/trainers/{id}/assign`).

- [x] A3.3 Stale REST examples

### A3.4 Hero/team system stale sentences

- **Action:** Align docs
- **Files:** [hero-system.md](../../systems/hero-system.md), [team-system.md](../../systems/team-system.md)
- **Edits:** `/api/heroes` → `/api/v1/heroes`. Chronicle match results are **implemented**, not future. Document chemistry formula (CHA offset, −15 hostile) as **display-only** until #7.

- [x] A3.4 Hero/team stale API and chronicle

---

## Wave 4 — Screen contracts

For each screen: **Implemented** = what Twig/API does. **Planned** = the rest. Empty “Sections to fill” go away (delete or fill with a pointer).

### A4.1 Overview + public/auth

- **Action:** Align docs
- **Files:** [`_deferred/screens-overview-legacy.md`](../../../_deferred/screens-overview-legacy.md), [00-public-pages.md](../../screens/00-public-pages.md), [00-auth-screens.md](../../screens/00-auth-screens.md), [01-kingdom-selection.md](../../screens/01-kingdom-selection.md), [19-player-profile-settings.md](../../screens/19-player-profile-settings.md)
- **Edits:** Overview not Draft-as-source-of-truth; crafting not a core screen. Locale switcher is **guest/public navbar + Account Settings** (not on the authenticated game navbar; public-pages had claimed the inside section had none). Screen 19: English only (was “Backdrop zavírání modalů”). Wiki/news admin stay Planned. Password change / notification prefs / privacy stay Planned.

- [x] A4.1 Overview + public + settings copy

### A4.2 Dashboard, roster, formation, inventory, combat

- **Action:** Align docs (UI Implement is Wave 7 if product wants the old lists)
- **Files:** [02-team-dashboard.md](../../screens/02-team-dashboard.md), [03-hero-roster.md](../../screens/03-hero-roster.md), [07-formation-setup.md](../../screens/07-formation-setup.md), [10-item-equipment.md](../../screens/10-item-equipment.md), [12-combat-battle.md](../../screens/12-combat-battle.md), [architecture/screen-code-map.md](../../architecture/screen-code-map.md)
- **Roster live:** name, race, level, trait, age, form, fatigue, attributes, inspect, client filter/sort. **Not live (later Cut A7.7 / #13):** item icons, morale on card, tired/training status, quick-action dropdown, batch training, multi-select, level filter control.
- **Formation:** keep the existing Planned table; summary/overview must not re-add synergies/clone/simulate as live. Drag-and-drop **is** live for lineup, **not** for equipment.
- **Inventory:** route redirects; equipment is hero-detail tab, click-to-equip paperdoll.
- **Combat:** keep “Still open” list; overview must not describe live logos/morale HUD/initiative queue.
- **Dashboard:** SSE stays Planned.

- [x] A4.2 Core game screens

### A4.3 Remaining screens leftover sections

- **Action:** Align docs
- **Files:** [06-trainer-management.md](../../screens/06-trainer-management.md), [17-community.md](../../screens/17-community.md), others still containing “Sections to fill”
- **Done when:** no screen file is an empty template.

- [x] A4.3 Remaining screen stubs

---

## Wave 5 — System docs leftovers

### A5.1 Fill or delete “Sections to fill”

- **Action:** Align docs
- **Files:** [kingdom-system.md](../../systems/kingdom-system.md), [hero-system.md](../../systems/hero-system.md), [team-system.md](../../systems/team-system.md), [auth-system.md](../../systems/auth-system.md), [formation-system.md](../../systems/formation-system.md) (purpose still says “synergy calculations”)
- **Edits:** Replace template headings with live behaviour or delete them. Formation purpose: drop synergy until #7/#9.

- [x] A5.1 System stub headings

### A5.2 Summoning Genie rule (docs only)

- **Action:** Align docs
- **Files:** [summoning-system.md](../../systems/summoning-system.md) (already notes the split), [npc-simulation-system.md](../../systems/npc-simulation-system.md), [product/overview.md](../../product/overview.md) if Wave 2 missed it
- **Done when:** one paragraph states: **players** use relationship ≥ 50 (Genie can summon all races); **NPC marketplace** may apply a stricter Genie-only filter. Product change is A7.9.

- [x] A5.2 Genie dual rule documented everywhere (later superseded by A7.9 / #14: one shared pool, Genie adaptation ≥ 80 — do not restore the player-vs-NPC split)

### A5.3 Notifications contract

- **Action:** Align docs
- **Files:** [notification-system.md](../../systems/notification-system.md), [architecture/routes.md](../../architecture/routes.md)
- **Edits:** Default `limit` / max from `NotificationController`. Unused types listed. Write-time translation documented until A6.4.

- [x] A5.3 Notification limits and unused types

---

## Wave 6 — Cheap code alignment

No new combat formulas. Behaviour changes only where the live system doc already implies them **or** known-issues is **Closed as Implement**.

### A6.1 Formation unique `hero_id` on save

- **Action:** Implement (decision: unique at save; kickoff waterfall)
- **Blocks:** [product/known-issues.md](../../product/known-issues.md) #10 — Resolved
- **Done:** `FormationService::rebuildSlots()` rejects duplicate `hero_id` (422). Empty slots still persist, including a fully empty draft (API + Formation Setup UI). Kickoff (`MatchLineupService`): assigned if 6 unique combat-ready, else default if match-ready, else roster fill (temporary copy). Forfeit only when the team cannot field 6 combat-ready heroes. Docs + tests updated.

- [x] A6.1 Formation duplicates (decision + execution)

### A6.2 Dead writers / reserved enums

- **Action:** Align docs + optional code
- **Files:** enums + writers + [architecture/entities.md](../../architecture/entities.md)
- **Default (Cut / reserve):** keep enum values, mark **unused**: `MatchType::Arena`, `FinancialRecordType::MoraleRestoration`, `ChronicleEventType::HeroRetired`, `MemorialCause::{Age,Retired,Death}`, `NotificationType::{LeagueUpdate,EventStarted,SpellLearned}`, `FinancialRecordType::SpellSlotCost`.
- **Do not delete columns** without an explicit migrate step in this item.
- **Done:** Cases kept (`isReserved()` / `liveCases()`). No writers existed to remove; columns not dropped. Entity-reference + system docs list each as reserved. Finance / graveyard / chronicle type filters omit reserved cases. Related unused retirement cases (`HeroStatus::Retired`, `HeroChronicleEventType::Retired`) have `isReserved()` the same way.

- [x] A6.2 Reserve unused enums

### A6.3 Forum pin

- **Action:** Design first → **Cut** (16 Oct 2026)
- **If Cut:** docs say pin is stored but unsettable (no admin API). Remove “admin use” as if it exists.
- **If Implement:** author-or-admin pin/unpin API + UI, CSRF, tests. Do not invent roles.
- **Done:** Pin stays on `ForumThread` and in listing/UI when true. No pin/unpin API, create/lock never set it, no forum admin role. Docs drop “admin use”. [product/known-issues.md](../../product/known-issues.md) #11 Resolved.

- [x] A6.3 Forum pin write path or docs-only

### A6.4 Notification i18n at read time

- **Action:** Implement (same pattern as chronicle)
- **Files:** `Notification` entity (store `title_key` / `body_key` / params **or** keep strings and document freeze), `NotificationHelper`, presenter, migrations if schema changes, tests
- **Done:** Keys + JSON params stored at write; `NotificationPresenter` renders in the reader's locale (facility / race / season-status nested like chronicle). Pre-A6.4 rows without keys keep frozen `title` / `body`. Migration `Version20261016120000`. Locale switch re-renders keyed notifications.

- [x] A6.4 Notification translation model

### A6.5 `summon_stat_total_cap`

- **Action:** Align docs (code already ignores it) **or** Implement wiring
- **Default:** keep unread; [hero-system.md](../../systems/hero-system.md) already says reserved. Ensure HQ/summary do not show it as an active bonus.
- **Done:** Field stays on `FacilityType::getPassiveBonuses()` and is still passed into `HeroGenerator`, which ignores it (live total cap is 60% of per-stat ceilings). HQ cards, Stimulus bonus map, and API `passive_bonuses` omit it via `getDisplayedPassiveBonuses()`. Unused `hq.bonus_summon_stat_total_cap` translation dropped. Docs mark it reserved/unread.

- [x] A6.5 Chamber total-cap unused everywhere

---

## Wave 7 — Design-gated gameplay

**Stop here without a human decision** on each **Open** known-issues ID. After a decision, execute in the same slice (code + systems + screens + tests). **#6 is Resolved** (A7.1). **#7 is Resolved** (A7.2 flavor). **#8 is Resolved** (A7.3 occupancy). **#2 is Resolved** (A7.4 Cut). **#9 is Resolved** (A7.5: HUD + Haste/Shock locked; morale / synergy / simulate Cut). **#12 is Resolved** (A7.6: L1 + thin L2 editor; L3 / unmet `when`/`target` Cut). **#13 is Resolved** (A7.7: roster extras Cut). **#4 is Resolved** (A7.8: HQ attendance graphs/history). **#14 is Resolved** (A7.9: Genie adaptation ≥ 80). **#15 is Resolved** (A7.10: listing floor −50% / ceiling +100% of suggested). **#16 is Resolved** (A7.11: catalog T1–T5).

### A7.1 XP → level curve — #6

- **Action:** Implement (decision locked 16 Oct 2026)
- **Locked (do not reopen in this step):** [hero-system.md](../../systems/hero-system.md#locked-hero-level-invariant) + [product/known-issues.md](../../product/known-issues.md) #6.
  - Package **B**: career level; training keeps the 1–20 attribute cap; no stat points on level-up.
  - Lifetime `Hero.xp` (never spent). Stored `Hero.level` is the live truth — no on-read derivation.
  - Writers: match XP path (current curve); formula/cap change → migration or job only.
  - Library `xp_gain_pct` stays. `Kingdom.xp_modifier` multiplies XP at award. `level_cap` **20**.
  - Cumulative cost to leave level `L`: `400 × (L + 1)` (800 XP for 1→2).
- **Done:** `HeroProgressionService` + `config/game/hero-progression.yaml`. League/friendly `applyMatchXp()` (Library × `xp_modifier`). `levelUp()` does not consume XP or flush. Chronicle on live raise only. Silent `app:hero-levels:refresh`. `Kingdom.level_cap` default/rows **20**. XP bar via `hero_xp_bar()`. Tests: `HeroProgressionServiceTest`.

- [x] A7.1 Level-up loop

### A7.2 Team chemistry purpose — #7

- **Action:** Design first → **Cut / flavor** (16 Oct 2026)
- **Must decide:** flavor-only number **or** combat/morale modifiers (tables).
- **If flavor:** dashboard copy + systems say it does not affect matches; Loner trait text matches.
- **If live:** formulas in [combat-formulas.md](../../systems/combat-formulas.md) first, then `CombatEngine`. Do not invent tables in code.
- **Done:** Chemistry is flavor for matches. Combat / in-match morale do not read `Team.chemistry`. Live consumers stay dashboard, history, fan club / attendance, league tie-break. Dashboard `chemistry_desc` and Loner trait copy match. In-combat race-synergy later Cut in A7.5. Lock: [team-system.md](../../systems/team-system.md#locked-chemistry-flavor). [product/known-issues.md](../../product/known-issues.md) #7 Resolved.

- [x] A7.2 Chemistry

### A7.3 Item occupancy — #8

- **Action:** Implement (decision locked 16 Oct 2026)
- **Locked:** [item-system.md](../../systems/item-system.md#locked-item-occupancy) + [product/known-issues.md](../../product/known-issues.md) #8.
  - Nine slots including `ring_2`. Ring items equip in `ring` or `ring_2`.
  - Two-handed (`two_handed_*`, bow, crossbow, staff) clears `off_hand`; equipping off-hand clears that two-hander.
  - Dual-wield: one-handed sword/axe/mace and dagger in `off_hand`.
  - Giant/Ent restrictions stay `RaceConfig`. Combat sums equipped bonuses; reach from main_hand.
- **Done:** `ItemService::equip()` occupancy + `compatibleSlots()`; paperdoll / rucksack / overview gear grid; `app:item-occupancy:refresh`; tests.

- [x] A7.3 Hands and rings

### A7.4 Enchanting — #2

- **Action:** Design first → **Cut** (16 Oct 2026)
- **Must decide:** redesign without essence **or** explicit never.
- **Done:** Enchanting is not in the live game. No essence. Remaining Enchant Item planned rows cut. `Item.special_effects` stays unused/reserved (combat reads `bonuses` only; item API omits the key). Lock: [item-system.md](../../systems/item-system.md#locked-no-enchanting). [product/known-issues.md](../../product/known-issues.md) #2 Resolved.

- [x] A7.4 Enchanting redesign or explicit never

### A7.5 Combat leftovers — #9

- **Action:** Design first → remaining **Cut** (16 Oct 2026)
- **HUD locked (16 Oct 2026):** Implement emblems + League/Friendly label + contextual back link. Cut morale widget, initiative queue, team-stats sidebar, skip-to-end, hex debug, unused formation grid. Lock: [12-combat-battle.md](../../screens/12-combat-battle.md#locked-replay-hud).
- **Haste/Shock locked (16 Oct 2026):** YAML `speed_bonus_percent` / `speed_reduction_percent` apply to the per-round initiative sort after ticks. Additive; not AP. `ENGINE_VERSION` 7. Spec: [combat-formulas.md](../../systems/combat-formulas.md#speed--initiative-init).
- **In-combat morale Cut (16 Oct 2026):** No `morale_change`. Volatile / Battle Hardened copy is flavor. `moraleDecayMultiplier` stays unused. Kickoff damage band and post-match morale stay.
- **Race synergy Cut (16 Oct 2026):** No in-combat race/role synergy tables. Combat does not read `ignoresRaceSynergy` (serialized unused). Loner copy stays flavor. Chemistry stays [#7](../../product/known-issues.md). A later product decision is required before adding in-combat synergy.
- **Simulate Cut (16 Oct 2026):** `POST /api/v1/combat/simulate` is **Cut** (no controller). Do not ship a sandbox. Friendly matches remain the live adjacent path. `CombatEngine::simulate()` stays for tests. Reopening requires an explicit product ask (consequences, opponent, one-shot vs cohort).
- **Do not** change locked round-200 / fumble / KO≠death rules. Do not reopen the HUD cut list, Haste/Shock AP coupling, in-combat morale, synergy tables, or simulate.
- **Done:** Remaining #9 closed. Lock: [combat-system.md](../../systems/combat-system.md#locked-no-race-synergy--no-simulate). [product/known-issues.md](../../product/known-issues.md) #9 Resolved.

- [x] A7.5 Combat leftover traits / simulate (HUD + Haste/Shock locked; morale / synergy / simulate Cut)

### A7.6 Formation L1/L2 editor

- **Action:** Design first (UI) then **Implement** (16 Oct 2026)
- **Locked:** [formation-system.md](../../systems/formation-system.md#locked-l1l2-editor) + [product/known-issues.md](../../product/known-issues.md) #12.
  - Per-slot **L1** `target_order` + `fallback` (`lowest_hp` / `highest_threat` / approach). Empty order = L0 approach.
  - Thin **L2**: ordered **equipped** spells; `when` `always` / `ally_hp_below` / `self_hp_below`; `target` `priority` / `lowest_hp_ally` / `self`. Empty list = L0 spell pick.
  - **Cut:** L3 action sequences / conditional tactics UI; `enemy_status`; `lowest_hp_enemy`. Save sanitizes JSON (`FormationTacticsNormalizer`).
- **Done:** Formation Setup slot panel (click pitch slot). Save merges formation-wide `movement_goal` with per-slot L1/L2. Tests: `FormationTacticsNormalizerTest`, `FormationServiceTest::testSaveSanitizesStrategyAndSpellPriorities`.

- [x] A7.6 Per-slot tactics UI

### A7.7 Roster UI extras — #13

- **Action:** Design first → **Cut** (16 Oct 2026)
- **Candidates from old screen 03:** morale on card, equipped icons, tired/training status, quick-action dropdown, batch training, multi-select, level filter, marketplace header CTA, server-side list filtering.
- **Done:** Live roster stays name, race, level, trait, age, form, fatigue, compact attributes/combat stats, OVR, status badge, Inspect, client filter/sort, Summon CTA. Morale remains a hidden sort key only. **Cut:** morale value/icon on the card, equipped-item icons, tired/training status, level filter, per-card quick-action dropdown, multi-select, batch training / formation assignment from the grid, marketplace CTA in the roster header, server-side list filtering, batch-operation APIs. Lock: [03-hero-roster.md](../../screens/03-hero-roster.md#locked-roster-extras). [product/known-issues.md](../../product/known-issues.md) #13 Resolved.

- [x] A7.7 Roster extras or confirmed cut

### A7.8 Arena analytics UI — #4

- **Action:** Implement after a short UI spec in [18-arena-management.md](../../screens/18-arena-management.md)
- **Locked:** [18-arena-management.md](../../screens/18-arena-management.md#locked-attendance-analytics) + [product/known-issues.md](../../product/known-issues.md) #4.
  - HQ panel only. Last **18** `arena_revenue` payouts for table, Chart.js (attendance + gold), and summary (total gold, average attendance).
  - Same window — no season query, no new route, no snapshot entity.
  - **Cut:** combat home-advantage, match results on the table, seating-upgrade charts, standalone `/app/arena` page.
- **Done:** `ArenaService::buildRevenueHistory()` (occupancy from ledger context). HQ panel table + dual-axis chart (`arena_analytics`). `GET /api/v1/arena` includes `recent_revenue` + `analytics`. Tests: `ArenaServiceTest`.

- [x] A7.8 Arena attendance analytics

### A7.9 Genie summon rule — #14

- **Action:** Design first → **Implement** (16 Oct 2026)
- **Locked:** [summoning-system.md](../../systems/summoning-system.md#locked-genie-summon-pool) + [product/known-issues.md](../../product/known-issues.md) #14.
  - Shared pool: relationship **≥ 50**, except Genie adaptation **≥ 80** (Genie / Human / Elf).
  - Same path for player summons, NPC daily summons, NPC marketplace hero buys, and kingdom starting roster.
  - **Cut:** Genie-only NPC buy filter; Genie-only starting roster; ≥ 50 for Genie (all 8 races).
- **Done:** `RaceConfig::getCompatibleRacesForAdaptation()`. `SummoningService` + `NpcEconomySimulator` + `KingdomInitializationService` share it. Tests: `RaceConfigTest`, `SummoningServiceTest`.

- [x] A7.9 Single Genie rule

### A7.10 Marketplace price guidance

- **Action:** Design first → **Implement** (16 Oct 2026)
- **Locked:** [marketplace-system.md](../../systems/marketplace-system.md#locked-listing-price-band) + [product/known-issues.md](../../product/known-issues.md) #15.
  - Suggested: heroes/trainers from `estimateMarketPrice()`; items from `ItemService::estimateMarketPrice()` (same as NPC).
  - Floor **−50%**, ceiling **+100%** (`round`, then floor ≥ 1).
  - `createListing` rejects buy-now / start / buyout outside the band. Auction **bids** are not capped. UI shows suggested + range.
- **Done:** `MarketplacePriceGuidance` + sell-tab / hero-detail hint. Tests: `MarketplacePriceGuidanceTest`, `MarketplaceServiceTest` band cases.

- [x] A7.10 Price hints or confirmed cut

### A7.11 Spell catalog T3–T5

- **Action:** Design first (content) then **seed** (16 Oct 2026)
- **Locked:** [spell-system.md](../../systems/spell-system.md#locked-catalog-t1t5) + [product/known-issues.md](../../product/known-issues.md) #16.
  - Two spells per school per tier for **T1–T5** (60). `required_mastery_tier` = `tier`.
  - Same type mix and effect keys as T1–T2. No resurrection, summon-creature, or new combat formulas.
  - Gold ~2.5× per tier. Academy filter 1–5. Combat still uses school + type + `spell.tier` (cast `1 + tier`).
- **Done:** `SpellService::seedDefaultSpells()` / `app:spells:seed` (kingdom init). Tests: `SpellServiceTest::testSeedDefaultSpellsCoversTiersOneThroughFive`.

- [x] A7.11 Spell catalog vs mastery cap

---

## Wave 8 — Infra honesty

Do not change game rules. Optional product asks stay Design first.

### A8.1 PHPUnit in CI

- **Action:** Implement
- **Files:** `.github/workflows/` (new or extend `phpstan.yml` caller)
- **Done:** `.github/workflows/phpunit.yml` runs `composer test` on PRs/pushes that touch `src/**/*.php`, `tests/**`, `config/**`, `migrations/**`, or Composer/PHPUnit workflow files (MariaDB 11.4 service + test migrations). Also `workflow_call` from `deploy.yml` before the image build. [history.md](history.md) Cross-Cutting updated.

- [x] A8.1 PHPUnit workflow

### A8.2 Playwright E2E

- **Action:** Design first → **Cut** (16 Oct 2026)
- **If Cut:** remove Cross-Cutting Playwright claim; do not leave “ongoing”.
- **If Implement:** real suite (register → dashboard → training or summon), not only a Vitest transitive dependency.
- **Done:** Playwright is not in the live toolchain. No suite, no `playwright.config`, no `npm run test:e2e`, no CI job. Cross-Cutting no longer lists browser E2E as ongoing. Live automated tests stay PHPUnit (`composer test` / A8.1). A later product decision is required before adding a browser suite. [architecture/overview.md](../../architecture/overview.md) Testing Strategy parks the pointer.

- [x] A8.2 Playwright or cut the claim

### A8.3 `GET /health`

- **Action:** Implement (16 Oct 2026)
- **If Implement:** unauthenticated liveness, no secrets.
- **Done:** `GET /health` returns `{ "status": "ok" }` with `Cache-Control: no-store`. No database ping, no env/version/DSN. Dedicated firewall `security: false` (no session). Compose `web` probes this path. `HealthCheckController` + `HealthCheckControllerTest` (direct + HTTP kernel, no session cookie). Route map / API tree / Cross-Cutting updated.

- [x] A8.3 Health route

### A8.4 Dual Doctrine `legacy` connection

- **Action:** Cut (recommended) or keep parked → **Park** (16 Oct 2026)
- **Files:** `config/packages/doctrine.yaml`, [roadmap/history.md](history.md) M1.1, `_deferred/legacy-migration.md`
- **Done when:** live config does not look like a second game DB unless maintainers still use it.
- **Done:** Connection + unused EM stay in `doctrine.yaml`. Not a second live game DB. Unused at runtime (`DATABASE_LEGACY_URL` empty in local/dev). Planned as a **one-shot import immediately before the first production deploy**. Guide stays in `_deferred/legacy-migration.md`. History M1.1 + Cross-Cutting say parked. Do not inject `legacy` into live services until that window.

- [x] A8.4 Legacy connection

### A8.5 Public wiki

- **Action:** Design first (content model) or keep Planned → **keep Planned** (16 Oct 2026)
- **Default:** stay Planned; Wave 2/4 already stop calling it live.
- **Done:** `GET /wiki` stays **Planned** (no controller, Twig, entity, search, or navbar Help link). Live public content is homepage + `/news` (seed / DB; no admin UI). News admin CMS stays Planned. Unused `nav.wiki` translation may stay. A later product decision is required before adding a wiki. Lock: [00-public-pages.md](../../screens/00-public-pages.md#locked-public-wiki-stays-planned).

- [x] A8.5 Wiki stays planned or scoped

### A8.6 Password change / extra settings

- **Action:** Design first → **keep Planned** (16 Oct 2026)
- **Today:** out of scope on purpose. Either implement session password change or keep Planned in screen 19 only.
- **Done:** Session password change, notification prefs, privacy, account-info panel, and connected accounts stay **Planned**. No password-change form, per-type notification toggles, privacy flags, or `GET/PUT /api/v1/settings`. Live settings stay language, backdrop-close, email change, and account deletion. Forgotten passwords use `/password-reset`. A later product decision is required before adding extras. Lock: [19-player-profile-settings.md](../../screens/19-player-profile-settings.md#locked-password-change--extra-settings-stay-planned).

- [x] A8.6 Password change

---

## Finding → step index

| Audit theme | Step |
|-------------|------|
| Dual spec (summary vs systems) | A0 canonical + Wave 2 |
| Systems status table / M1–6 “complete” | A1.1, A1.2 |
| XP without `levelUp()` | A7.1 (#6) |
| Chemistry unused in combat | A3.4, A7.2 (#7) |
| Roster screen vs card | A4.2, A7.7 (#13 Resolved: extras Cut) |
| Two-handed / rings / dual-wield | A2.4, A7.3 (#8) |
| Enchanting “Phase 6” | A1.2, A7.4 (#2) |
| League morale fiction | A2.4 |
| Combat HUD / Haste / unused traits | A4.2, A7.5 (#9 Resolved: HUD + Haste/Shock locked; morale / synergy / simulate Cut) |
| Formation save / synergies / simulate | A2.3, A4.2, A6.1, A7.6 (#12 Resolved: L1 + thin L2; L3 Cut) |
| Inventory redirect / equipment DnD | A3.1, A4.2 |
| Notification i18n + limit | A5.3, A6.4 |
| Unused enums | A3.2, A6.2 |
| PHPUnit / Playwright | A8.1, A8.2 |
| Locale switcher docs | A4.1 |
| Kingdom “separate server” | A2.2 |
| Genie summon split | A5.2, A7.9 (#14 Resolved: Genie ≥ 80) |
| Wiki / news / health | A4.1, A8.3, A8.5 (wiki stay Planned) |
| Forum pin | A6.3 |
| Graveyard dismissals in summary | A2.5 |
| Screen stubs / Czech in EN | A4.1, A4.3 |
| `summon_stat_total_cap` | A6.5 |
| Price floor | A2.2, A7.10 (#15 Resolved: −50% / +100% of suggested) |
| Planned `/train` routes | A3.1, A3.3 |
| NPC + inactivity missing from status | A1.1 |
| Arena analytics | A7.8 (#4) |
| Spell T1–T2 seed | A7.11 (#16 Resolved: catalog T1–T5) |
| Legacy DB connection | A8.4 |
| Password / settings extras | A4.1, A8.6 (stay Planned) |
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

*Last updated: 16 October 2026 — Alignment waves 0–8 complete (archived). Now / next: [current.md](../current.md). Honesty leftovers: A5.2 dual Genie rule superseded by A7.9; item API omits unused `special_effects`.*
