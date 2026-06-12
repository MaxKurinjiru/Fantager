# Known Issues & Open Questions

This file is the single source of truth for documentation gaps, design questions, and known inconsistencies.

> Numbers are non-sequential — gaps indicate previously resolved issues that have been removed.

| # | Area | Issue | Severity | Notes |
|---:|---|---|---|---|
| 1 | Combat | No combat formulas documented (HP, damage, defense, accuracy, dodge, crits, status effects) | High | `combat-system.md` covers eligibility, scoring and flow; simulation formulas remain undefined — blocks Phase 5 |
| 2 | Item System | Durability & enchanting mechanics referenced in Essence spending (2.3) but undefined in Item System (2.8) | High | Essence costs listed but no mechanics; `item-system.md` is still a placeholder |
| 3 | Dungeon | Dungeon System entity + high-level mechanics documented in `dungeon-system.md` but encounter rules, rewards and tick processing still undefined | Low | Future Feature (Phase 7); design can be deferred |
| 6 | Terminology | "Death Expectation" naming misleading — means when mortality risk begins, not expected death age | Low | "Mortality Threshold" row added to game-summary.md (line ~388) as alias, but column header unchanged; consider renaming column or adding inline note |
| 7 | Missing Mechanics | Friendly Matches listed in tick schedule (2.2) but no rules or purpose documented | Low | Phase 5 blocker — see roadmap.md #8 |
| 8 | Missing Mechanics | Arena Match mechanics not documented — only arena as HQ facility with ticket revenue | Low | Phase 5 blocker — see roadmap.md #9; no standalone combat mode rules |
| 9 | Public Section | Homepage content partially specified in `00-public-pages.md` — news feed count, SEO requirements and analytics still missing | Low | Standalone auth pages documented; modal UX noted as future enhancement |
| 10 | Public Section | Wiki/Help system partially documented in `00-public-pages.md` — content structure and categorization listed in "Sections to fill" | Low | Basic structure (categories, search, static vs DB) sketched; detail pending |

Keep this file updated as fixes are applied.
Remove entry from this list when fixed.
