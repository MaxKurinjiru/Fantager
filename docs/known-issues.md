# Known Issues & Open Questions

Single source of truth for documentation gaps, design questions, and known inconsistencies.

**AI agents:** Do not invent mechanics for open items below (especially turn engine details and enchanting #2). Read [roadmap.md](roadmap.md) and [screen-code-map.md](screen-code-map.md) before implementing related screens.

**Legend:** `Blocks` = what implementation phase is waiting on this item.

| ID | Area | Issue | Severity | Blocks | Status |
|----|------|-------|----------|--------|--------|
| 1 | Combat | Follow-up steps 2–7 are in: L0 spell pick, defensive targeting, stat formulas, post-match hero XP/form/fatigue, replay from snapshot HP, and full AP movement with ranged units holding weapon range. A KO is not always permanent death. Preparation length and fumble-without-retarget stay locked. Logos, match type, morale, and the initiative queue stay on the design list. Branch hygiene closed: `kill_score` aligned (`score_a`/`score_b`), event type `spell`, resolve-pending command description, run-state docs, resume/`stalled` wave tests. | High | — | Resolved |
| 2 | Item System | Durability & enchanting mechanics referenced in economy docs but undefined in item system | High | Phase 6 enchanting | Open |
| 3 | Friendly Matches | Rules documented in `calendar-system.md`; combat engine exists — scheduling UI/API (`POST /api/v1/arena/schedule-match`) still pending | Low | Friendly match scheduling | Partially resolved |
| 4 | Arena Matches | Home-match revenue and ticket price API implemented; friendly match scheduling and extended analytics UI still pending | Low | Friendly scheduling / analytics UI | Partially resolved |
| 5 | Graveyard | Memorial snapshots on dismiss and permanent combat death implemented; read UI/API at `/app/graveyard` and `GET /api/v1/graveyard/*` | Low | — | Resolved |

Deferred features beyond Milestone 7 (Milestone 8 dungeons/quests/crafting, Milestone 9 alliances/guilds, world events, public wiki/news) are documented under [`future/`](future/) and explicitly marked as **Deferred / Out of Scope** in [roadmap.md](roadmap.md) — they are parked and not tracked as active issues.

Remove rows from **Open** when fixed;
