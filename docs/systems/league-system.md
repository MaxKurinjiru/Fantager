# League System

Reference: [game-summary.md](../game-summary.md#212-league-system)

Purpose: Document season scheduling, promotion/relegation, fixtures, and rewards.

Sections to fill:
- Season lifecycle and scheduling
- Match scheduling and processing
- Promotion/relegation rules
- Reward distribution
- Integration with standings and leaderboards
- Implementation notes



Summary:
- League seasons run for configured durations per-kingdom; players are grouped by tier and group, with promotion/relegation at season end. Matches scheduled and processed by server ticks.

APIs:
- GET /api/league/standings — current standings
- GET /api/league/fixtures — upcoming fixtures
- POST /api/league/process-season — admin/scheduler endpoint

