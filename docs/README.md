# Fantager documentation

Pick a view. This file is an index, not a status table.

**Live rules:** matching `systems/*.md`.  
**Live UI:** [architecture/screen-code-map.md](architecture/screen-code-map.md) + Twig.  
**Overview vs live:** [product/overview.md](product/overview.md) loses to `systems/` on conflict.  
**Now / next:** [roadmap/current.md](roadmap/current.md) — alignment waves 0–8 complete; leftovers in `systems/` Remaining work and `future/`.

## Views

| View | Start here | Contains |
|------|------------|----------|
| **Development** | [guides/](guides/README.md) | Backend and UI cheatsheets, UI guidelines |
| **Architecture** | [architecture/](architecture/README.md) | Stack, API, entities, routes, screen → code |
| **Product / domain** | [product/](product/README.md) | Game overview, glossary, known issues, live `systems/` and `screens/` |
| **Roadmap** | [roadmap/](roadmap/README.md) | Now / next / parked; historical milestones; alignment-wave archive |

## Canonical sources

Conflicts resolve as:

1. **Live rules:** matching `systems/*.md` + **Open** rows in [product/known-issues.md](product/known-issues.md)
2. **Live UI:** [architecture/screen-code-map.md](architecture/screen-code-map.md) + Twig/Stimulus
3. **Routes / schema:** [architecture/routes.md](architecture/routes.md), [architecture/entities.md](architecture/entities.md)
4. **[product/overview.md](product/overview.md):** overview only; **systems win** on conflict
5. **[systems/README.md](systems/README.md) status snapshot:** one word + link. Leftovers in that system’s Remaining work. Wave chronicle: [roadmap/alignment-waves.md](roadmap/alignment-waves.md)

## How to use

- Agents: [AGENTS.md](../AGENTS.md)
- Do not invent mechanics for **Open** rows in known-issues
- Deferred design: [future/](future/)
- Parked repo meta and the original screen sketch: [`_deferred/`](../_deferred/)
- Remaining work for a feature lives in that feature’s `systems/*.md` (see the Remaining work convention there)
- Glossary: [product/glossary.md](product/glossary.md)

## Former filenames

Old `docs/*.md` root names were stubs after the 15 Oct 2026 layout. Those stubs are gone. Use the canonical path:

| Old path | Canonical |
|----------|-----------|
| `docs/game-summary.md` | [product/overview.md](product/overview.md) |
| `docs/known-issues.md` | [product/known-issues.md](product/known-issues.md) |
| `docs/api-design.md` | [architecture/api.md](architecture/api.md) |
| `docs/arch-spec.md` | [architecture/overview.md](architecture/overview.md) |
| `docs/entity-reference.md` | [architecture/entities.md](architecture/entities.md) |
| `docs/route-map.md` | [architecture/routes.md](architecture/routes.md) |
| `docs/screen-code-map.md` | [architecture/screen-code-map.md](architecture/screen-code-map.md) |
| `docs/ui-guidelines.md` | [guides/ui-guidelines.md](guides/ui-guidelines.md) |
| `docs/ui-agent-cheatsheet.md` | [guides/ui.md](guides/ui.md) |
| `docs/backend-agent-cheatsheet.md` | [guides/backend.md](guides/backend.md) |
| `docs/alignment-roadmap.md` | [roadmap/current.md](roadmap/current.md) |
| `docs/screens-overview.md` | [screens/README.md](screens/README.md) |
| `docs/screens/overview-legacy.md` | [`_deferred/screens-overview-legacy.md`](../_deferred/screens-overview-legacy.md) |
| `docs/systems/combat-formulas-draft.md` | [systems/combat-formulas.md](systems/combat-formulas.md) |
| `_deferred/notes.md` | [future/notes.md](future/notes.md) |
