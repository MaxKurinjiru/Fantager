# Changelog

<!--
  HOW TO USE THIS FILE
  ====================
  Format:  - <Category>: <Short description> (PR #123)
  Categories: Added, Changed, Fixed, Docs, Removed, Security
  Rules:
    - Add entries under [Unreleased] only — never directly to versioned sections.
    - One bullet per change, max ~100 chars. Link to PR/issue for details.
    - At release time: create ## [YYYY-MM-DD] - vX.Y.Z and move Unreleased entries there.
  See CONTRIBUTING.md#changelog for full workflow.
-->

## [Unreleased]

### Docs
- Updated `CONTRIBUTING.md`, `AGENTS.md` and `.github/copilot-instructions.md` with critical info about using `-u apache` in Docker to avoid `var/cache` permission issues.

### Added
- Code: All 41 Doctrine entity classes scaffolded across every domain (Hero, Training, HQ, Item, Spell, Formation, Combat, League, Event, Marketplace, Community, Graveyard, Dungeon, Quest, Crafting, Summoning, Notification, ActivityLog)
- Code: Repository class created for every entity across all domains
- Code: 30 PHP backed enums created covering all domain value types (Race, HeroStatus, School, TrainingType, FacilityType, MatchType, LeagueSeasonStatus, etc.)
- Code: Game config YAML files added (`config/game/races.yaml`, `race_relations.yaml`, `status_effects.yaml`)
- Code: Training weekly tick processing (`processTrainingTick`) with trainer stats, team facility levels, and non-linear complexity scaling
- Code: Symfony console command `app:training:tick` to run weekly training ticks
- Code: PHPUnit test suite `TrainingServiceTest` verifying scheduling, caps, validation, and fractional calculations
- Docs: roadmap.md with phased implementation order
- Docs: game-summary sections 2.15–2.17 (Dungeon, Quest, Crafting)
- Docs: site structure section in screens-overview.md (public vs inside split)

### Changed
- Code: Primary attributes of heroes and trainers are stored scaled by 10 (range 10-200) internally with floored external representation (1-20)
- Code: HeroGenerator rolls base stats and adds a random raw offset (0-9) for starting diversity
- Docs: Updated calendar-system.md and training-system.md to reflect weekly training ticks and formulas


### Fixed
- Docs: copilot-instructions.md redundant local setup (now links to README)
- Docs: docs/README.md missing entries (known-issues, CONTRIBUTING, CHANGELOG, roadmap)

### Changed
- Docs: DRY file-change checklist (canonical in copilot-instructions, reference in AGENTS)
- Docs: AGENTS.md local setup trimmed to reference README
