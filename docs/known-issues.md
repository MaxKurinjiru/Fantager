# Known Issues & Open Questions

Single source of truth for documentation gaps, design questions, and known inconsistencies.

**AI agents:** Do not invent mechanics for open items below (especially turn engine details and enchanting #2). Read [roadmap.md](roadmap.md) and [screen-code-map.md](screen-code-map.md) before implementing related screens.

**Legend:** `Blocks` = what implementation phase is waiting on this item.

| ID | Area | Issue | Severity | Blocks | Status |
|----|------|-------|----------|--------|--------|
| 1 | Combat | Contract VO + thin engine done. Wave Messenger (lockstep rounds, max 200, no wave timeout, `stalled` isolation), turn loop, and post-match effects (XP, morale, form, fatigue) implemented. **Pending (6.1d):** combat death → graveyard — a hero may appear as killed in `combat_log` but their `status` remains `Available` and they do **not** appear in the graveyard until milestone 6.1d ships. Also pending: L1–L2 AI, post-match replay UI. See [combat-system.md](systems/combat-system.md) | High | Graveyard combat death triggers, L1+ AI, replay UI | Partially resolved |
| 2 | Item System | Durability & enchanting mechanics referenced in economy docs but undefined in item system | High | Phase 6 enchanting | Open |
| 3 | Friendly Matches | Rules documented in `calendar-system.md`; scheduling UI/API pending combat engine | Low | Phase 5 friendly match scheduling | Partially resolved |
| 4 | Arena Matches | Home-match revenue implemented; friendly match scheduling pending combat | Low | Phase 5 | Partially resolved |
| 5 | Graveyard UI | Memorial snapshots on dismiss implemented; combat death memorials pending combat engine | Low | Phase 6 combat deaths | Resolved (read UI/API implemented) |

Deferred features (dungeons, world events, quests, crafting, public wiki/news) are documented under [`future/`](future/) — not tracked here.

Remove rows from **Open** when fixed;
