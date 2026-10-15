# Known Issues & Open Questions

Single source of truth for **design questions** that must not be invented in code.

**Work queue (docs/code mismatches, cut vs implement):** [roadmap/current.md](../roadmap/current.md).  
**AI agents:** Do not invent mechanics for **Open** rows (especially #2 enchanting, #6 XP curve, #7 chemistry, #8 item occupancy, #9 combat leftovers). Read [roadmap/current.md](../roadmap/current.md) and [architecture/screen-code-map.md](../architecture/screen-code-map.md) before related work.

**Legend:** `Blocks` = alignment step or feature waiting on a decision. `Resolved` rows stay as history until the alignment roadmap says to drop them.

| ID | Area | Issue | Severity | Blocks | Status |
|----|------|-------|----------|--------|--------|
| 1 | Combat | Follow-up steps 2–7 + wave `MAX_ROUNDS` force-complete are in. A KO is not always permanent death. Preparation length and fumble-without-retarget stay locked. Replay HUD (logos, match type, morale, initiative queue), Haste/Shock initiative, and unused trait hooks are tracked as **#9**. Status durations/DOT percents read from `status_effects.yaml` via `StatusEffectConfig` (with hardcoded fallback). | High | — | Resolved (core); leftovers → #9 |
| 2 | Item System | Enchanting mechanics undefined (essence currency removed from live economy; redesign without essence). Not part of Milestone 6. | High | Alignment A7.4 | Open |
| 3 | Friendly Matches | Spec + implementation in [friendly-match-system.md](../systems/friendly-match-system.md) (Wed/Sun 18:00 windows, PvP invites, full combat consequences) | Low | — | Resolved |
| 4 | Arena Matches | Home-match revenue, ticket price API, and friendly scheduling implemented; extended attendance analytics UI still pending | Low | Alignment A7.8 | Partially resolved |
| 5 | Graveyard | Memorial snapshots on dismiss and permanent combat death implemented; read UI/API at `/app/graveyard` and `GET /api/v1/graveyard/*` | Low | — | Resolved |
| 6 | Hero progression | Match XP is awarded to `Hero.xp` (Library `xp_gain_pct` applies). `HeroService::levelUp()` is never called; displayed level stays at generation until an XP→level curve is designed. Also decide what `Kingdom.level_cap` / `xp_modifier` do. | High | Alignment A7.1 | Open |
| 7 | Team chemistry | `TeamChemistryService` writes 0–100 (CHA offset, −15 per hostile pair). Dashboard shows it. Combat does not read it. In-combat race-synergy tables are deferred; Loner `ignoresRaceSynergy` is unused. Decide flavor-only vs live modifiers. | High | Alignment A7.2 | Open |
| 8 | Item occupancy | `product/overview.md` describes two-handed occupying both hands, dual-wield, and two rings. Code: one `ring` slot; `ItemService::equip()` only clears the target slot. Decide occupancy rules before changing `ItemService`. | High | Alignment A7.3 | Open |
| 9 | Combat leftovers | Replay HUD extras; Haste/Shock on initiative (design text only); Volatile / Battle Hardened in-combat morale; race synergy tables; optional `POST /api/v1/combat/simulate`. Do not change locked round-200 / fumble / KO≠death. | Medium | Alignment A7.5 | Open |
| 10 | Formation save | Save accepts 0–6 slots and does not reject duplicate `hero_id`. Kickoff forfeits understaffed teams. Summary historically required a unique 6-hero lineup at save. Decide save-time validation vs kickoff-only. | Medium | Alignment A6.1 | Open |

Deferred features beyond the alignment waves (Milestone 8 dungeons/quests/crafting, Milestone 9 alliances/guilds, world events, public wiki as a full CMS) stay under [`future/`](../future) and are **not** active issues unless [roadmap/current.md](../roadmap/current.md) Wave 8 picks wiki/health/CI up as infra.

When an Open row is decided, record Cut vs Implement on the alignment step, then mark this row Resolved.
