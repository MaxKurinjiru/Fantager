# Fantager documentation

Pick a view. This file is an index, not a status table.

**Live rules:** `systems/*.md` + [product/known-issues.md](product/known-issues.md).  
**Live UI:** [architecture/screen-code-map.md](architecture/screen-code-map.md) + Twig.  
**Overview vs live:** [product/overview.md](product/overview.md) loses to `systems/` until alignment Wave 2.  
**Active work:** [roadmap/current.md](roadmap/current.md) — one unchecked step at a time.

## Views

| View | Start here | Contains |
|------|------------|----------|
| **Development** | [guides/](guides/README.md) | Backend and UI cheatsheets, UI guidelines |
| **Architecture** | [architecture/](architecture/README.md) | Stack, API, entities, routes, screen → code |
| **Product / domain** | [product/](product/README.md) | Game overview, known issues, live `systems/` and `screens/` |
| **Roadmap** | [roadmap/](roadmap/README.md) | Now / next / parked; historical milestones |

## Canonical sources

Until alignment Wave 2–4 change this, conflicts resolve as:

1. **Live rules:** matching `systems/*.md` + open rows in [product/known-issues.md](product/known-issues.md)
2. **Live UI:** [architecture/screen-code-map.md](architecture/screen-code-map.md) + Twig/Stimulus (not [screens/overview-legacy.md](screens/overview-legacy.md))
3. **Routes / schema:** [architecture/routes.md](architecture/routes.md), [architecture/entities.md](architecture/entities.md)
4. **[product/overview.md](product/overview.md):** overview only; **systems win** until Wave 2
5. **[systems/README.md](systems/README.md) status table:** snapshot; [roadmap/current.md](roadmap/current.md) is more current during waves 0–8

## How to use

- Agents: [AGENTS.md](../AGENTS.md)
- Do not invent mechanics for **Open** rows in known-issues
- Deferred design: [future/](future/)
- Parked repo meta: [`_deferred/`](../_deferred/)
- Remaining work for a feature lives in that feature’s `systems/*.md` (see the Remaining work convention there)
