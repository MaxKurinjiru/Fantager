# Known Issues & Open Questions

Single source of truth for documentation gaps, design questions, and known inconsistencies.

**AI agents:** Do not invent mechanics for open items below (especially turn engine details and enchanting #2). Read [roadmap.md](roadmap.md) and [screen-code-map.md](screen-code-map.md) before implementing related screens.

**Legend:** `Blocks` = what implementation phase is waiting on this item.

| ID | Area | Issue | Severity | Blocks | Status |
|----|------|-------|----------|--------|--------|
| 1 | Combat | Wave Messenger (lockstep rounds, max 200, no wave timeout, `stalled` isolation), turn loop, post-match effects (XP, morale, form, fatigue), and **6.1d** combat death → aging → permanent death → graveyard (+ durability loss) are implemented. A KO in `combat_log` / `killed_hero_ids` is not always permanent death (non-elder or failed mortality roll) — that is by design. Still pending: L1–L2 AI, post-match replay UI. See [combat-system.md](systems/combat-system.md) | High | L1+ AI, replay UI | Partially resolved |
| 2 | Item System | Durability & enchanting mechanics referenced in economy docs but undefined in item system | High | Phase 6 enchanting | Open |
| 3 | Friendly Matches | Rules documented in `calendar-system.md`; combat engine exists — scheduling UI/API (`POST /api/v1/arena/schedule-match`) still pending | Low | Friendly match scheduling | Partially resolved |
| 4 | Arena Matches | Home-match revenue and ticket price API implemented; friendly match scheduling and extended analytics UI still pending | Low | Friendly scheduling / analytics UI | Partially resolved |
| 5 | Graveyard | Memorial snapshots on dismiss and permanent combat death implemented; read UI/API at `/app/graveyard` and `GET /api/v1/graveyard/*` | Low | — | Resolved |

Deferred features beyond Milestone 7 (Milestone 8 dungeons/quests/crafting, Milestone 9 alliances/guilds, world events, public wiki/news) are documented under [`future/`](future/) and explicitly marked as **Deferred / Out of Scope** in [roadmap.md](roadmap.md) — they are parked and not tracked as active issues.

Remove rows from **Open** when fixed;
