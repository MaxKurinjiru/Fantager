# Architecture

Dual-layer design: Web (Twig + Stimulus/Turbo) and Internal API (`/api/v1`) share the same services. Controllers stay thin.

| Doc | Contents |
|-----|----------|
| [overview.md](overview.md) | Stack, dual-layer request flow, ops, security |
| [api.md](api.md) | REST shape, status codes, JSON conventions |
| [entities.md](entities.md) | Entities, enums, naming, design decisions |
| [routes.md](routes.md) | All Web and API routes (must match code) |
| [screen-code-map.md](screen-code-map.md) | Screen → controller, Twig, Stimulus, services |

`bash scripts/check-backend-docs.sh` checks [routes.md](routes.md) and [entities.md](entities.md) against code.
