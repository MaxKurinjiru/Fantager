# League Screen

Reference: [screens-overview.md](../screens-overview.md#13-league-screen)

Purpose: Document the implemented League and Standings screen, including its Web controller, service logic, templates, and navigation.

## Route Map & Controller

- **Web Route**: `/app/league` (Route Name: `app_league`)
- **Controller Class**: `App\Controller\Web\LeagueController::index`
- **Request Parameters**:
  - `groupId` (optional, integer): Filters the standing table and fixtures by the chosen group ID. Defaults to the logged-in user's group.

## Displayed Information & Layout

The League Screen is rendered in `templates/league/index.html.twig` and uses the standard `tab_controller.js` for switching between the following tabs:

### 1. Group Standings (Tabulka skupiny)
- **Header info**: Displays the active season number and start/end dates.
- **Group Select Dropdown**: Select dropdown populated with all groups in the active season, sorted by tier. Selecting a group reloads the page with `?groupId=X`.
- **Standings Table**:
  - Rank (column `col_rank`): Highlights promotion zone (top teams configured by `promotionSlots`) with a green arrow ▲ and relegation zone (bottom teams configured by `relegationSlots`) with a red arrow ▼.
  - Team Name (column `col_team`): Shows team emblem and name. Real players have no extra badge, whereas NPCs have a `NPC` badge.
  - Highlight: The user's team is styled with a bold font, green borders, and a subtle green background.
  - Core statistics: Played (`col_played`), Wins (`col_wins`), Draws (`col_draws`), Losses (`col_losses`), Goal Difference (`col_diff`), Points (`col_points`).
  - Recent Form (`col_form`): Shows 5 small colored circles representing the last 5 completed matches of each team (Green W = Win, Gray D = Draw, Red L = Loss).

### 2. Fixtures & Results (Zápasy a výsledky)
- **Left Column - My Team's Matches**: List of all matches (upcoming and completed) for the logged-in team in the active season. Completed matches display scores and a status badge.
- **Right Column - Group Matches**: All fixtures in the selected group grouped by round (using distinct scheduled dates/times). Completed matches show scores.

### 3. Kingdom Leaderboard (Globální žebříček)
- Shows a list of all teams in the active season ranked kingdom-wide.
- Ordered by:
  1. Tier ID / level ASC
  2. Total Points DESC
  3. Goal Difference DESC
  4. Wins DESC
  5. Player Type (Real players first)
  6. Team Reputation DESC
  7. Team Chemistry DESC
  8. Team ID ASC

---

## Technical Details

### LeagueService (`App\Service\League\LeagueService`)
Implements the core business rules for querying and sorting:
- `getCurrentSeason(Kingdom $kingdom)`: Returns the active season.
- `getSortedStandings(LeagueGroup $group)`: Sorts a group's standing using the standard tie-breaker formula.
- `getGlobalLeaderboard(LeagueSeason $season)`: Aggregates and sorts all standings for the season.
- `getTeamForm(Team $team, LeagueSeason $season)`: Scans completed league fixtures to return form results (e.g. `['W', 'L', 'D']`).
