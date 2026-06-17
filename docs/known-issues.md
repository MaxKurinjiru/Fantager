# Known Issues & Open Questions

Single source of truth for documentation gaps, design questions, and known inconsistencies.

**Legend:** `Blocks` = what implementation phase is waiting on this item.

| ID | Area | Issue | Severity | Blocks | Status |
|----|------|-------|----------|--------|--------|
| 1 | Combat | No combat formulas documented (HP, damage, defense, accuracy, dodge, crits, status effects) | High | Phase 5 league match resolution, graveyard death triggers | Open |
| 2 | Item System | Durability & enchanting mechanics referenced in economy docs but undefined in item system | High | Phase 6 enchanting | Open |
| 3 | Friendly Matches | Rules documented in `calendar-system.md`; scheduling UI/API pending combat engine | Low | Phase 5 friendly match scheduling | Partially resolved |
| 4 | Arena Matches | Home-match revenue implemented; friendly match scheduling pending combat | Low | Phase 5 | Partially resolved |
| 5 | Graveyard UI | Memorial snapshots on dismiss implemented; combat death memorials pending combat engine | Low | Phase 6 combat deaths | Resolved (read UI/API implemented) |

Deferred features (dungeons, world events, quests, crafting, public wiki/news) are documented under [`future/`](future/) — not tracked here.

Remove rows from **Open** when fixed;
