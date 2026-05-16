# Team System

Reference: [game-summary.md](../game-summary.md#26-team-system)

Purpose: Document team entities, aggregation endpoints (dashboard), and team-related mechanics.

Sections to fill:
- Team data model and relations
- Dashboard aggregation endpoints
- Team-level stats (morale, reputation)
- Formation integration and validation
- Permissions & settings
- Implementation notes



Summary:
- Teams aggregate heroes, formations, HQ, and economic resources. Team-level stats include morale, chemistry, and reputation.
- Dashboard endpoints should aggregate per-team stats and recent activity for the main screen.

APIs:
- GET /api/teams/{id}/dashboard — aggregated data for Team Dashboard
- POST /api/teams/{id}/settings — update team settings

