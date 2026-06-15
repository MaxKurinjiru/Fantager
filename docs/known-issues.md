# Known Issues & Open Questions

Single source of truth for documentation gaps, design questions, and known inconsistencies.

**Legend:** `Blocks` = what implementation phase is waiting on this item.

| ID | Area | Issue | Severity | Blocks | Status |
|----|------|-------|----------|--------|--------|
| 1 | Combat | No combat formulas documented (HP, damage, defense, accuracy, dodge, crits, status effects) | High | Phase 5 league match resolution, graveyard death triggers | Open |
| 2 | Item System | Durability & enchanting mechanics referenced in economy docs but undefined in item system | High | Phase 6 enchanting | Open |
| 3 | Dungeon | Encounter rules, rewards and tick processing undefined | Low | Phase 7 | Deferred (planned) |
| 6 | Terminology | "Death Expectation" column name misleading — alias "Mortality Threshold" added in game-summary | Low | — | Open (cosmetic) |
| 7 | Friendly Matches | Rules now documented in `calendar-system.md`; scheduling UI/API still pending combat engine | Low | Phase 5 friendly match scheduling | Partially resolved |
| 8 | Arena Matches | Home-match revenue model implemented; friendly match scheduling pending combat | Low | Phase 5 | Partially resolved |
| 9 | Public Section | News/wiki SEO and analytics not specified | Low | Public pages phase | Deferred (planned) |
| 10 | Public Section | Wiki content structure sketched but detail pending | Low | Public pages phase | Deferred (planned) |

## Recently resolved

| ID | Resolution |
|----|------------|
| — | Calendar Web UI implemented at `/app/calendar` |
| — | Arena home-match revenue model (fixed ticket price, dual-team attendance, league tick payout) |
| — | Crafting queue processor: `CraftingService` + `app:process-crafting-queue` + API endpoints |
| — | CI workflow added (`.github/workflows/ci.yml`) |
| — | PHPUnit coverage extended for Marketplace, Community, Spell, Item, Crafting services |

Remove rows from **Open** when fixed; move a one-line summary to **Recently resolved**.
