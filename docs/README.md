# Fantager documentation

Pick a view. This file is an index, not a status table.

## Canonical sources

Conflicts resolve as:

1. **Live rules:** matching `systems/*.md` + **Open** rows in [product/known-issues.md](product/known-issues.md)
2. **Live UI:** [architecture/screen-code-map.md](architecture/screen-code-map.md) + Twig/Stimulus
3. **Routes / schema:** [architecture/routes.md](architecture/routes.md), [architecture/entities.md](architecture/entities.md)
4. **[product/overview.md](product/overview.md):** overview only; **systems win** on conflict
5. **[systems/README.md](systems/README.md) status snapshot:** one word + link. Leftovers in that system’s Remaining work or Parked. Wave chronicle: [roadmap/alignment-waves.md](roadmap/alignment-waves.md)

Do not invent mechanics for **Open** rows. Do not invent a next alignment wave (0–8 are complete — [roadmap/current.md](roadmap/current.md)). Do not start Milestone 8/9 ([future/](future/)).

Locks live in `## Locked:` in the matching system or screen doc (index: [product/known-issues.md](product/known-issues.md) § Resolved locks).

Old `docs/*.md` root names: [roadmap/alignment-waves.md](roadmap/alignment-waves.md#former-filenames).

## Reading paths

| Goal | Start |
|------|-------|
| Understand the game | [product/overview.md](product/overview.md) → [product/glossary.md](product/glossary.md) |
| New feature | [guides/backend.md](guides/backend.md) § Vertical slice |
| Change a screen | [architecture/screen-code-map.md](architecture/screen-code-map.md) → `screens/` → [guides/ui.md](guides/ui.md) |
| Change a game rule | matching `systems/*.md` |
| Agent task routing | [AGENTS.md](../AGENTS.md) |

## Views

| View | Start here | Contains |
|------|------------|----------|
| **Development** | [guides/](guides/README.md) | Backend and UI cheatsheets, UI guidelines |
| **Architecture** | [architecture/](architecture/README.md) | Stack, API, entities, routes, screen → code |
| **Product / domain** | [product/](product/README.md) | Game overview, glossary, known issues, live `systems/` and `screens/` |
| **Roadmap** | [roadmap/](roadmap/README.md) | Now / next / parked; historical milestones; alignment-wave archive |

## How to use

- Deferred design: [future/](future/)
- Parked repo meta and the original screen sketch: [`_deferred/`](../_deferred/)
- Remaining work and Parked items for a feature live in that feature’s `systems/*.md`
- Glossary: [product/glossary.md](product/glossary.md)
