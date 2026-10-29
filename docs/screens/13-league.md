# League Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [league-system.md](../systems/league-system.md)

**When:** Group table, own fixtures, kingdom-wide ranking.

**Status:** Live at `GET /app/league`. Tabs (translation keys): Group standings, My fixtures, Kingdom leaderboard, and on finished seasons **Season report**. A season selector opens finished seasons (final table, own results, place, tier move, gold, yearbook). Win-streak / derby / relegation-pressure morale are **Cut**. Page is server-rendered; JSON also exists under `/api/v1/league/*`.

**Live UI:** `templates/league/index.html.twig`, `templates/league/_yearbook.html.twig`, `templates/components/league/`.

**Lock:** [league-system.md — no league morale fiction](../systems/league-system.md#locked-no-league-morale-fiction), [season-report freeze](../systems/season-report-system.md#locked-frozen-snapshot-two-consumers)

---

## Implemented

### Header

- Season `<select>` (`?seasonId=`) — active and completed seasons in the kingdom, newest first. A scheduled shell is omitted. Unknown id falls back to the active season, then the newest finished season
- Selected season number and start/end dates
- Group `<select>` (`?groupId=`) — all groups in the selected season, by tier
- Empty state when the kingdom has no active or finished season

Query: `tab` = `standings` | `my-fixtures` | `leaderboard` | `yearbook` (yearbook only when the selected season is completed). `seasonId` and `groupId` stay on the tab links. Changing season drops a group id that belongs to another season.

### Season archive

Choosing a **completed** season keeps standings, fixtures, leaderboard, and adds **Season report** (`tab=yearbook`): own freeze vs group medians, income mix, roster/activity counts, flavor chemistry/reputation/fan base. Empty copy when no `season_report` row exists yet. Formation prep and fill-from-roster are hidden. A banner states the season is finished and, for the viewer's team, the place, group, tier, promotion/relegation/retained status, and gold from the `season_ended` chronicle.

### Group standings (`tab=standings`)

- Promotion / relegation legend from tier slot counts
- Table: rank (promo ▲ / releg ▼), emblem, name (profile button), NPC badge, played / W / D / L / GD / points, last-5 form dots
- Own team row highlighted
- **Group fixtures** on the same tab: round switcher, home vs away, score link to battle when the viewer’s team played, VS if not finished

### My fixtures (`tab=my-fixtures`)

Own season matches as cards: date, names (profiles), score or VS, completed/pending, match-ready panel, formation status + **Prepare formation** → `/app/formation?fixture_id=` for unfinished. Fill-from-roster uses `POST /api/v1/fixtures/{id}/formation/fill-roster`.

### Kingdom leaderboard (`tab=leaderboard`)

All teams in the active season. Tie-breakers: points DESC, GD DESC, wins DESC, real players before NPC, reputation DESC, chemistry DESC, team id ASC. Columns include group name. No form column on this table.

### Backend

| Piece | Location |
|-------|----------|
| Web | `GET /app/league` → `Web\LeagueController::index` |
| JSON | `GET /api/v1/league/standings`, `/fixtures`, `/seasons`, `/yearbook` |
| Domain | `LeagueService` (`getSortedStandings`, `getGlobalLeaderboard`, `getTeamForm`), `SeasonReportService` |

Team names open the community profile modal (`player-profile`).

---

## Sketch leftover

Not a contract:

- Separate “player’s current tier/rank/points” hero block on this page (the table already shows it)
- Arena-revenue / hero-level leaderboard categories (those were community-sketch lists)

League morale fiction (win-streak / derby / relegation pressure) is **Cut**: [league-system.md](../systems/league-system.md).

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/league/index.html.twig`, `templates/league/_yearbook.html.twig` |
| Formation chip | `templates/components/league/fixture_formation_status.html.twig` |
| Stimulus | `form-auto-submit`, `league-fixtures`, `player-profile`, `match_ready` |
