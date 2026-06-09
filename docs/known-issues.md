# Known Issues & Open Questions

This file is the single source of truth for documentation gaps, design questions, and known inconsistencies.

| # | Area | Issue | Severity | Notes |
|---:|---|---|---|---|
| 1 | Combat | No combat formulas documented (HP, damage, defense, accuracy, dodge, crits, status effects) | High | Section 2.10 is high-level only |
| 5 | Item System | Durability & enchanting mechanics referenced in Essence spending (2.3) but undefined in Item System (2.8) | High | Essence costs listed but no mechanics |
| 6 | Dungeon | Dungeon System has placeholder section (2.15) but encounter rules, rewards and tick processing still undefined | Medium | Moved to Phase 7 (Future Feature) |
| 7 | Quest | Quest System has placeholder section (2.16) but quest generation, limits and rewards still undefined | Medium | |
| 8 | Crafting | Crafting System has placeholder section (2.17) but recipes, success rates and dismantle rules still undefined | Medium | |
| 13 | Terminology | "Death Expectation" naming misleading — means when mortality risk begins, not expected death age | Low | Consider "Mortality Threshold" or add clarifying note |
| 14 | Missing Mechanics | Friendly Matches listed in tick schedule (2.2) but no rules or purpose documented | Low | |
| 15 | Missing Mechanics | Arena Match mechanics not documented — only arena as HQ facility with ticket revenue | Low | No standalone combat mode rules |
| 16 | Public Section | Homepage content and layout not fully specified — news feed count, CMS vs static, SEO requirements | Medium | See [00-public-pages.md](screens/00-public-pages.md) |
| 17 | Public Section | Wiki/Help system undocumented — content structure, categorization, search, static vs DB-driven | Medium | |
| 19 | Auth UX | Modal login/register behavior not fully specified — animations, mobile responsiveness, deep-link support (e.g. `/login` opening modal) | Low | |

Keep this file updated as fixes are applied.
Remove point(s) from this list when fixed.
