# Team System

Reference: [game-summary.md](../game-summary.md#26-team-system)

Purpose: Document team entities, roster rules, aggregation endpoints (dashboard), and team-related mechanics.

## Starting Roster

| Rule | Value |
|------|-------|
| **Heroes at team creation** | **10** (6 for match lineup + 4 reserves) |
| **Starting Barracks capacity** | **10** heroes (expandable via HQ upgrades) |
| **Minimum to play a match** | **6** combat-ready heroes |
| **Starting level** | **1** (all newly created heroes) |
| **Starting age** | Random within **[Min Age, Max Junior Age]** per race |

New teams (including NPC teams at Kingdom initialization) receive 10 heroes at level 1 so they can always field a full 6-hero lineup with substitution depth.

## Match Eligibility

If a team has fewer than 6 combat-ready heroes when a fixture is processed:

- The match is **not simulated**
- The understaffed team **automatically loses** with kill score **0–3** (opponent receives 3–0)
- If **both** teams are understaffed: **0–0 draw**

See [combat-system.md](combat-system.md#match-eligibility) for full rules.

## NPC Teams

Every team in the system, including AI-controlled opponents, is represented by the same `Team` entity. NPC teams are distinguished by `is_npc = true` and `user_id = NULL`.

- **Auto-created on Kingdom initialization**: All slots up to kingdom capacity are filled with NPC teams before any real player joins. Each new team receives one chronicle entry: `team_established`.
- **Fully staffed**: Each NPC team is created with **10 heroes** and a default formation.
- **Assigned to a real player on registration**: When a player registers, a random unclaimed NPC team (`user_id IS NULL`) in their Kingdom is assigned to them immediately (`user_id` set, `is_npc` set to false). A `team_chronicle` entry with type `player_joined` is recorded on the team chronicle. If the registration is not verified within 24 hours, a daily maintenance tick (running at 03:30 AM) removes the team assignment, writes `player_released` / `unverified_registration`, and deletes the user.
- **Inactive player release**: Verified players who do not log in or play for **28 days** have their team released back to the NPC pool (daily tick at 03:45 AM). Chronicle entry: `player_released` / `inactivity`. A warning is sent after **21 days** of inactivity. See [player-inactivity-system.md](player-inactivity-system.md).
- **AI-controlled gameplay**: NPC matches are resolved by the combat engine automatically. NPC tactics (lineup, gear, and slot strategies), training setups, summoning, and marketplace activities are autonomously simulated. See [npc-simulation-system.md](npc-simulation-system.md) for full implementation details.

## Sections to Fill

- Team data model and relations
- Dashboard aggregation endpoints
- Team-level stats (morale, reputation)
- Formation integration and validation
- Permissions & settings
- Implementation notes

## Summary

Teams aggregate heroes, formations, HQ, and economic resources. Team-level stats include morale, chemistry, and reputation. Roster depth (10 heroes at start) ensures teams can field 6-hero lineups; understaffed teams forfeit matches automatically.

## Team-Level Stats

Morale, chemistry, reputation, and **fan club size** (`fan_base`) are team-level attributes. Fan club is a **background mechanic** — no dedicated screen or player management; it drives arena attendance. Current size and the most recent change (`last_fan_base_delta`) appear on the **dashboard banner**. See [economy-system.md](economy-system.md#fan-club--arena-attendance).

## Financial Crisis

Player teams track financial health via `unpaid_debt`, `crisis_weeks`, and `last_recovery_action_at`. Prolonged insolvency escalates from warning to restricted play, and ultimately **bankruptcy** (team released to NPC pool).

Recovery options: sell assets on marketplace, dismiss heroes (40% compensation), downgrade HQ facilities.

See [financial-crisis-system.md](financial-crisis-system.md).

## Team Chronicle

Each team maintains an append-only **chronicle** in `team_chronicle` — a timeline of ownership changes, league milestones, summons, and (future) match results. The log is tied to the **team**, not the current player, so history survives manager changes and NPC periods.

| Event | Chronicle type |
|-------|----------------|
| NPC team created at kingdom init | `team_established` |
| Player claims team (registration) | `player_joined` |
| Team released (inactivity, bankruptcy, unverified reg, account delete) | `player_released` |
| Season ends | `season_ended` |
| Hero summoned | `summon_completed` |

**UI:** Dashboard shows the 5 most recent entries; full filtered history at `/app/chronicle` (`app_team_chronicle`).

See [team-chronicle-system.md](team-chronicle-system.md) for write hooks, categories, and presentation details.

## Hero Dismissal

Players can dismiss available heroes via `POST /api/v1/heroes/{id}/dismiss`:

- Compensation = **40%** of estimated hero value (`complex_rating` × `gold_per_complex_point` from [hero-rating-system.md](hero-rating-system.md))
- Team must retain at least **6 combat-ready** heroes (status `available`)
- Hero must be unassigned from trainer before dismissal
- Hero is permanently removed (unlike marketplace sale)

## APIs

- `GET /api/teams/{id}/dashboard` — aggregated data for Team Dashboard
- `POST /api/teams/{id}/settings` — update team settings
