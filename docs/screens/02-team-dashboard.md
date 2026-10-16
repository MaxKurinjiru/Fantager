# Team Dashboard (Main Screen)

Reference: [screens/overview-legacy.md](overview-legacy.md#2-team-dashboard-main-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [team-system.md](../systems/team-system.md)

**When:** Default authenticated landing page after login.

**Status:** Live at `GET /app/dashboard`. Chemistry on the banner is **flavor for matches** ([product/known-issues.md](../product/known-issues.md) #7 Resolved) — it does not change combat. Server-Sent Events (SSE) stay **Planned**. UI extras from the original sketch (win/loss record, league tier/position on this page) are Wave 7 if product wants them.

**Live UI:** `templates/dashboard/index.html.twig` plus `templates/components/dashboard/`.

---

## Implemented

### Displayed information

**Team banner** (`team_banner.html.twig`)

- Team name, emblem, primary/secondary colors
- Manager display name and kingdom name
- Morale (value / 100 + bar)
- Fan club size and last delta (when non-zero)
- Chemistry (value / 100 + bar, `chemistry_desc` tooltip) — **flavor for matches**; combat does not read it (#7 Resolved). Live for fan club / attendance and league tie-break.
- Reputation (value + bar)

**Finance**

- Treasury / gold on hand, unpaid debt, weekly expenses (payroll + HQ maintenance), period and all-time net (`components/finance/summary_stats.html.twig`)
- Recent ledger rows with link to `/app/finance`
- **Financial crisis banner** when crisis level is not `none` (level copy, debt, weekly costs, restricted-action hint)

**Chronicle**

- Last **5** `team_chronicle` entries (localized message + timestamp)
- Link **Full chronicle** → `/app/chronicle`

**League this week**

- Up to two fixtures this week (home/away, schedule, score if completed, formation status)
- Prepare-formation link for unfinished fixtures (`/app/formation?fixture_id=`)
- Link to league fixtures tab

**Quick stats** (links)

- Total heroes, available heroes → `/app/heroes`
- Heroes in training (assigned to a trainer) → `/app/training`
- HQ total level → `/app/hq`
- Summons this cycle → HQ summoning chamber
- Unpaid debt (only when > 0) → `/app/finance`

**Quick actions**

- Prepare formation for the next match (only when `dashboard.next_match` is set)
- Summon (HQ summoning chamber)
- Headquarters

**Team history chart (modal)**

- Opened from banner stat cards
- 30-day trend: morale, reputation, chemistry, fan club size (`TeamDailySnapshot`)
- `GET /api/v1/teams/{teamId}/history`

**Team profile (modal)**

- Name, emblem picker, primary/secondary colors
- `POST /api/v1/teams/{teamId}/settings`

Not on this page: win/loss record, current league tier & position, a dedicated “next match” card (`templates/components/dashboard/next_match.html.twig` exists but is **not** included — week-match cards cover upcoming fixtures).

### Actions

- Edit team profile (banner)
- Open 30-day history chart (banner stats)
- Navigate via stats, quick actions, chronicle, finance, and week-match links
- Prepare formation for a listed unfinished fixture

### Backend

| Piece | Location |
|-------|----------|
| Web | `GET /app/dashboard` → `Web\DashboardController` |
| Aggregation | `TeamService::getDashboardData()` |
| Chronicle | `TeamChroniclePresenter::presentRecentForTeam()` (5 entries) |
| Finance | `FinanceSummaryService::buildOverview()` |
| JSON dashboard | `GET /api/v1/teams/{teamId}/dashboard` (same aggregation; page is server-rendered) |
| Settings | `POST /api/v1/teams/{teamId}/settings` |
| History | `GET /api/v1/teams/{teamId}/history` |

Gold also appears on the game resource bar (layout), not only on this screen.

---

## Planned

- **Server-Sent Events / SSE** for live dashboard or notification push (A4.2). In-app notifications today are the navbar modal + polling, separate from chronicle.
- Win/loss record on the dashboard
- Current league tier & position on the dashboard
- Dedicated shortcuts to Training, Marketplace, and League as first-class quick-action tiles (league/finance/heroes are reached via other blocks)

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/dashboard/index.html.twig` |
| Banner / crisis / week matches / stats / actions | `templates/components/dashboard/` |
| Profile modal | `templates/dashboard/_team_profile_modal.html.twig` |
| History modal | `templates/dashboard/_team_history_modal.html.twig` |
| Stimulus | `dashboard_banner`, `modal` (team profile), `profile_settings`, `team_history_chart` |
