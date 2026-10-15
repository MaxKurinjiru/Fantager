# Known Issues & Open Questions

Single source of truth for documentation gaps, design questions, and known inconsistencies.

**AI agents:** Do not invent mechanics for open items below (especially turn engine details and enchanting #2). Read [roadmap.md](roadmap.md) and [screen-code-map.md](screen-code-map.md) before implementing related screens.

**Legend:** `Blocks` = what implementation phase is waiting on this item.

| ID | Area | Issue | Severity | Blocks | Status |
|----|------|-------|----------|--------|--------|
| 1 | Combat | Follow-up steps 2–7 + wave `MAX_ROUNDS` force-complete are in. A KO is not always permanent death. Preparation length and fumble-without-retarget stay locked. Logos, match type, morale, and the initiative queue stay on the design list. Status durations/DOT percents read from `status_effects.yaml` via `StatusEffectConfig` (with hardcoded fallback). | High | — | Resolved |
| 2 | Item System | Enchanting mechanics undefined (essence currency removed from live economy; redesign without essence) | High | Phase 6 enchanting | Open |
| 3 | Friendly Matches | Spec + implementation in [friendly-match-system.md](systems/friendly-match-system.md) (Wed/Sun 18:00 windows, PvP invites, full combat consequences) | Low | — | Resolved |
| 4 | Arena Matches | Home-match revenue, ticket price API, and friendly scheduling implemented; extended attendance analytics UI still pending | Low | Analytics UI | Partially resolved |
| 5 | Graveyard | Memorial snapshots on dismiss and permanent combat death implemented; read UI/API at `/app/graveyard` and `GET /api/v1/graveyard/*` | Low | — | Resolved |
| 6 | Hero progression | Match XP is awarded to `Hero.xp` (Library `xp_gain_pct` applies). `HeroService::levelUp()` is never called; displayed level stays at generation until an XP→level curve is designed. | Medium | Level-up loop | Open |

Deferred features beyond Milestone 7 (Milestone 8 dungeons/quests/crafting, Milestone 9 alliances/guilds, world events, public wiki/news) are documented under [`future/`](future/) and explicitly marked as **Deferred / Out of Scope** in [roadmap.md](roadmap.md) — they are parked and not tracked as active issues.

Remove rows from **Open** when fixed;
