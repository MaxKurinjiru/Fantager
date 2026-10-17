# Fantager documentation

Pick a view. This file is an index, not a status table. Reader icon snapshot: [product/progress.md](product/progress.md) (derived; systems and the Later index win).

## Canonical sources

Conflicts resolve as:

1. **Live rules:** matching `systems/*.md` + **Open** rows in [product/known-issues.md](product/known-issues.md)
2. **Live UI:** [architecture/screen-code-map.md](architecture/screen-code-map.md) + Twig/Stimulus
3. **Routes / schema:** [architecture/routes.md](architecture/routes.md), [architecture/entities.md](architecture/entities.md)
4. **[product/overview.md](product/overview.md):** overview only; **systems win** on conflict
5. **[systems/README.md](systems/README.md) status snapshot:** one word + link. Leftover **code** in that system’s Remaining work. Later product decisions: [product/known-issues.md](product/known-issues.md) § Later product decisions. Wave chronicle: [roadmap/archive/](roadmap/archive/README.md)

Do not invent mechanics for **Open** rows. Do not invent a next alignment wave (0–8 are complete — [roadmap/current.md](roadmap/current.md)). Do not start Milestone 8/9 ([future/](future/)).

Locks live in `## Locked:` in the matching system or screen doc (index: [product/known-issues.md](product/known-issues.md) § Resolved locks). **Cut** is a lock, not a Parked later item.

Old `docs/*.md` root names: [roadmap/archive/alignment-waves.md](roadmap/archive/alignment-waves.md#former-filenames).

## Start here

About 20 minutes for a new reader:

1. [product/overview.md](product/overview.md) then [product/glossary.md](product/glossary.md) — what the game is
2. [product/progress.md](product/progress.md) — live / planned / future at a glance (also in the [root README](../README.md#game-progress))
3. [architecture/overview.md](architecture/overview.md) — stack and dual-layer flow
4. [architecture/screen-code-map.md](architecture/screen-code-map.md) — which files implement each screen
5. One matching `systems/*.md` for the area you care about
6. [guides/backend.md](guides/backend.md) or [guides/ui.md](guides/ui.md) before you edit

Status words: [product/glossary.md](product/glossary.md#how-to-read-status-words). Task routing: [AGENTS.md](../AGENTS.md).

## Reading paths

| Goal | Start |
|------|-------|
| Understand the game | [product/overview.md](product/overview.md) → [product/glossary.md](product/glossary.md) |
| See what is live / planned / future | [product/progress.md](product/progress.md) (compact: [root README](../README.md#game-progress)) |
| New feature | [guides/backend.md](guides/backend.md) § Vertical slice |
| Change a screen | [architecture/screen-code-map.md](architecture/screen-code-map.md) → `screens/` → [guides/ui.md](guides/ui.md) |
| Change a game rule | matching `systems/*.md` |
| Agent task routing | [AGENTS.md](../AGENTS.md) |
| Chronicle (M1–9 / waves 0–8) | [roadmap/archive/](roadmap/archive/README.md) — not a work queue |

## Views

| View | Start here | Contains |
|------|------------|----------|
| **Development** | [guides/](guides/README.md) | Local setup, backend and UI cheatsheets, UI guidelines |
| **Architecture** | [architecture/](architecture/README.md) | Stack, API, entities, routes, screen → code |
| **Product / domain** | [product/](product/README.md) | Game overview, [progress snapshot](product/progress.md), glossary, known issues, live `systems/` and `screens/` |
| **Roadmap** | [roadmap/](roadmap/README.md) | Now / next; deferred M8/M9; [archive/](roadmap/archive/README.md) |

## Out of scope

Not a work queue. Do not implement unless the user explicitly asks.

| Kind | Where |
|------|-------|
| Later product decisions (wiki, SSE, …) | [product/known-issues.md](product/known-issues.md) § Later product decisions |
| Milestone 8 + world events | [future/](future/) |
| Milestone 9 alliances / guilds | [roadmap/archive/history.md](roadmap/archive/history.md) |
| Repo meta, original screen sketch, and original systems summary | [`_deferred/`](../_deferred/) |

**Cut** decisions live in `## Locked:` (index: [product/known-issues.md](product/known-issues.md) § Resolved locks), not in this table. Unfinished **code** for a live feature lives in that system’s Remaining work.
