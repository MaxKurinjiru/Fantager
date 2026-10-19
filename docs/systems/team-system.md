# Team System

Reference: [product/overview.md](../product/overview.md#team)

Status: Live  
Screens: [02-team-dashboard.md](../screens/02-team-dashboard.md), [02a-team-chronicle.md](../screens/02a-team-chronicle.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none  
Lock: [Locked: chemistry flavor](#locked-chemistry-flavor)

Purpose: Document team entities, roster rules, dashboard aggregation, and team-related mechanics. Later product decisions (dashboard SSE): [known-issues.md](../product/known-issues.md) § Later product decisions.

## Starting Roster

| Rule | Value |
|------|-------|
| **Heroes at team creation** | **10** (6 for match lineup + 4 reserves) |
| **Starting Barracks capacity** | **10** heroes (expandable via HQ upgrades) |
| **Minimum to play a match** | **6** combat-ready heroes |
| **Starting level** | **1** (all newly created heroes) |
| **Starting age** | Random within **[Min Age, Max Junior Age]** per race |

New teams (including NPC teams at Kingdom initialization) receive 10 heroes at level 1 so they can always field a full 6-hero lineup with substitution depth. Starting-hero races use the same compatible pool as summoning for the team's assigned race ([summoning-system.md](summoning-system.md#locked-genie-summon-pool)).

## Match Eligibility

When a fixture is processed, a team that cannot field its kickoff formation loses without simulation:

- **Player:** the formation assigned to the match, or the default when none is assigned, must already contain 6 unique combat-ready heroes. A short assigned formation is a forfeit. Empty slots are not filled from the roster.
- **NPC:** the default formation is used. `NpcTacticsSimulator` rewrites that formation and does not assign a fixture lineup. If it is short, kickoff fills the gaps from the combat-ready roster. Forfeit only when that fill still cannot place 6 heroes.
- The match is **not simulated**
- The team that cannot field **automatically loses** with kill score **0–3** (opponent receives 3–0)
- If **both** teams cannot field: **0–0 draw**

See [combat-system.md](combat-system.md#match-eligibility) for full rules.

## NPC Teams

Every team in the system, including AI-controlled opponents, is represented by the same `Team` entity. NPC teams are distinguished by `is_npc = true` and `user_id = NULL`.

- **Auto-created on Kingdom initialization**: All slots up to kingdom capacity are filled with NPC teams before any real player joins. Each new team receives one chronicle entry: `team_established`.
- **Fully staffed**: Each NPC team is created with **10 heroes** and a default formation.
- **Assigned to a real player on registration**: When a player registers, a random unclaimed NPC team (`user_id IS NULL`) in their Kingdom is assigned to them immediately (`user_id` set, `is_npc` set to false). A `team_chronicle` entry with type `player_joined` is recorded on the team chronicle. If the registration is not verified within 24 hours, a daily maintenance tick (running at 03:30 AM) removes the team assignment, writes `player_released` / `unverified_registration`, and deletes the user.
- **Inactive player release**: Verified players who do not log in or play for **28 days** have their team released back to the NPC pool (daily tick at 03:45 AM). Chronicle entry: `player_released` / `inactivity`. A warning is sent after **21 days** of inactivity. See [player-inactivity-system.md](player-inactivity-system.md).
- **AI-controlled gameplay**: NPC matches are resolved by the combat engine automatically. NPC tactics (lineup, gear, and slot strategies), training setups, summoning, and marketplace activities are autonomously simulated. See [npc-simulation-system.md](npc-simulation-system.md) for full implementation details.

## Data model

Canonical columns: [entities.md](../architecture/entities.md) § Team Domain.

| Entity | Table | Role |
|--------|-------|------|
| **Team** | `team` | Identity, gold, morale/reputation/chemistry/fan_base, NPC flag, crisis fields |
| **FinancialRecord** | `financial_record` | Gold ledger — [economy-system.md](economy-system.md) |
| **TeamChronicle** | `team_chronicle` | Append-only team log — [team-chronicle-system.md](team-chronicle-system.md) |
| **TeamSummonHistory** | `team_summon_history` | Summon audit — [summoning-system.md](summoning-system.md) |
| **TeamDailySnapshot** | `team_daily_snapshot` | Last-30-days chart (morale, reputation, chemistry, fan_base) |

Related: `Headquarters` 1:1, `Formation` 1:N, `Hero` 1:N, `LeagueStanding` via group. `User` is nullable (`NULL` while NPC).

---

## Dashboard aggregation

Web: `GET /app/dashboard` (server-rendered banner includes **fan_base** and `last_fan_base_delta`). JSON: `GET /api/v1/teams/{teamId}/dashboard` from `TeamService::getDashboardData()`:

| Block | Fields |
|-------|--------|
| `team` | `id`, `name`, `emblem`, `colors`, `morale`, `reputation`, `chemistry`, `fan_base`, `last_fan_base_delta` |
| `resources` | `gold` |
| `heroes` | `total` combatants, `available`, `in_training` (has a trainer) |
| `headquarters` | `total_level`, `initialized`, arena adaptation + lock flags |
| `formations` | saved `count` |
| `summoning` | `summons_this_cycle`, `last_summon_at` |
| `next_match` / `week_matches` | upcoming league fixtures. Unfinished fixtures include `lineup` from `MatchLineupService::preview()` |
| `league` | current season place and record (`LeagueService::summarizeStanding()`), or `null` |
| `financial_crisis` | status from `FinancialCrisisService` |

History chart: `GET /api/v1/teams/{teamId}/history` (30 daily snapshots). SSE dashboard updates stay **planned** (A4.2).

`lineup` on an unfinished fixture:

| Field | Meaning |
|-------|---------|
| `source` | `assigned`, `default`, or `roster` — the formation kickoff will actually use |
| `formation_name` | Name of that formation, or of the short saved formation when kickoff must fill or forfeit |
| `ready_slots` | Unique combat-ready heroes already in that formation (6 when it is match-ready) |
| `will_forfeit` | Player: the assigned formation, or the default when none is assigned, is not match-ready. NPC: the default cannot be completed from the roster |
| `uses_default_fallback` | Always false. An assigned player formation does not fall through to the default |
| `will_autofill` | NPC only: the default is short and the combat-ready roster can still place 6 |
| `can_fill` | Player only: the kickoff formation is short and the combat-ready roster can still place 6. Kickoff does not fill; the player uses **Fill empty slots from roster** |
| `slots` | Six positions with `hero_id`, `hero_name`, `status`, and `issue` (`ok` / `empty` / `duplicate` / `trainer` / `unready`) |

The dashboard, league My fixtures, and formation match-prep show this as a **match-ready panel**. A short formation assigned to the match is a forfeit for a player team unless they fill it before kickoff.

---

## Formation integration

- Each NPC/player team is created with a **default formation**. Saved cap is **4** — [formation-system.md](formation-system.md).
- Kickoff fields the assigned formation, or the default when none is assigned. Players forfeit if that formation is not match-ready. Before kickoff they can **explicitly** fill empty / unready slots from the combat-ready roster (`POST /api/v1/fixtures/{id}/formation/fill-roster`), which writes a match-specific formation and does not change the saved default. NPCs play the default and may fill a short one from the roster at kickoff ([formation-system.md](formation-system.md#validation-rules)). Save allows 0–6 slots but rejects duplicate `hero_id`.
- Dashboard shows saved formation count; lineup editor is `/app/formation`.

---

## Permissions and settings

API and web require `ROLE_PLAYER`. Resource checks compare the authenticated user’s team id to `{teamId}` (`403` otherwise). There is no kingdom-admin team editor.

`POST /api/v1/teams/{teamId}/settings` (CSRF `X-CSRF-Token` / `api`) updates display fields only:

- `name` — required non-empty, max 100; rename writes chronicle `team_renamed`
- `emblem` — optional string, max 255
- `colors` — optional JSON/array

Morale, chemistry, reputation, gold, and ticket price are **not** player-editable here (ticket price is HQ arena API).

---

## Summary

Teams aggregate heroes, formations, HQ, and economic resources. Team-level stats include morale, chemistry (flavor for matches; live for fan club and league tie-break — [Locked: chemistry flavor](#locked-chemistry-flavor)), and reputation. Roster depth (10 heroes at start) gives teams a 6-hero lineup. A player forfeits when the formation that will start is incomplete. An NPC plays the default formation and forfeits only when that formation cannot be completed from the roster.

## Team-Level Stats

Morale, chemistry, reputation, and **fan club size** (`fan_base`) are team-level attributes. Fan club is a **background mechanic** — no dedicated screen or player management; it drives arena attendance. Current size and the most recent change (`last_fan_base_delta`) appear on the **dashboard banner**. See [economy-system.md](economy-system.md#fan-club--arena-attendance). Chemistry is computed and shown; combat does not read it (see [Locked: chemistry flavor](#locked-chemistry-flavor)).

## Financial Crisis

Player teams track financial health via `unpaid_debt`, `crisis_weeks`, and `last_recovery_action_at`. Prolonged insolvency escalates from warning to restricted play, and ultimately **bankruptcy** (team released to NPC pool).

Recovery options: sell assets on marketplace, dismiss heroes (40% compensation), downgrade HQ facilities.

See [financial-crisis-system.md](financial-crisis-system.md).

## Locked: chemistry flavor

**#7 Resolved (A7.2 Cut / flavor).** `Team.chemistry` is a roster-derived integer **0–100**. Combat and in-match morale do **not** read it. Do not add match damage, initiative, combo, or training multipliers from this number.

**Live consumers:** dashboard banner and 30-day history, `FanClubService` target fan base and show-up rate (arena attendance), league standings / promotion-relegation **tie-break**.

**Not live:** in-combat race-synergy tables, Loner pairing skip. Those are **Cut** ([#9](../product/known-issues.md) Resolved / A7.5). Loner is still counted in the chemistry formula like any other combatant.

## Team Chemistry

`TeamChemistryService::recalculate()` writes `Team.chemistry` as an integer **0–100**. The dashboard and `GET /api/v1/teams/{teamId}/history` show it. **Combat does not read it.** In-combat race-synergy tables are **Cut** ([#9](../product/known-issues.md) Resolved / A7.5).

**Who is counted:** heroes with `role = combatant` (`HeroRepository::findCombatantsByTeam`). Trainers are excluded. Fewer than 2 combatants → chemistry **50**.

**Pair score** for each unordered pair of combatants:

1. `base` = race affinity from `config/game/race_relations.yaml` (0–100).
2. If `base < 50`: CHA offset = `max(0, CHA_A − 10) + max(0, CHA_B − 10)`; `effective = min(50, base + chaOffset)`. CHA cannot raise a pair above Neutral (50).
3. Otherwise `effective = base`.
4. If `effective <= 20`, the pair is **hostile**.

**Team value:**

- `avg` = mean of all pair `effective` values
- `penalty` = (hostile pair count) × **15**
- `chemistry` = `round(avg − penalty)`, clamped to 0–100

Recalculated when roster composition changes (kingdom init, summon, dismiss, marketplace hero transfer). The race matrix also feeds summoning, arena adaptation, and weekly training group modifiers; those uses are separate from this stored chemistry number. Summoning / starting roster / NPC hero buys use relationship ≥ 50, except Genie adaptation ≥ 80 ([summoning-system.md](summoning-system.md#locked-genie-summon-pool)).

## Team Chronicle

Each team maintains an append-only **chronicle** in `team_chronicle` — a timeline of ownership changes, league match results, season milestones, summons, and other roster/economy events. The log is tied to the **team**, not the current player, so history survives manager changes and NPC periods.

| Event | Chronicle type |
|-------|----------------|
| NPC team created at kingdom init | `team_established` |
| Player claims team (registration) | `player_joined` |
| Team released (inactivity, bankruptcy, unverified reg, account delete) | `player_released` |
| Season ends | `season_ended` |
| League match won / lost / drawn | `battle_win` / `battle_loss` / `battle_draw` |
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

| Method | Path | Notes |
|--------|------|-------|
| GET | `/app/dashboard` | Team Dashboard |
| GET | `/app/chronicle` | Full team chronicle |
| GET | `/api/v1/teams/{teamId}/dashboard` | Aggregation above |
| POST | `/api/v1/teams/{teamId}/settings` | Name / emblem / colors |
| GET | `/api/v1/teams/{teamId}/history` | Last 30 days of `TeamDailySnapshot` |
| POST | `/api/v1/fixtures/{id}/formation/fill-roster` | Player fill of a short kickoff lineup (match-specific formation) |

Canonical list: [architecture/routes.md](../architecture/routes.md).

---

## Implementation notes

| Piece | Location |
|-------|----------|
| Entity | `src/Entity/Team/Team.php` |
| Dashboard / settings | `App\Service\Team\TeamService` |
| Chemistry | `TeamChemistryService` |
| Roster helpers | `TeamRosterService` |
| Fan club | `FanClubService` — [economy-system.md](economy-system.md) |
| NPC | `NpcSimulationService` — [npc-simulation-system.md](npc-simulation-system.md) |

**Tests:** `tests/Service/Team/` (`TeamChemistryServiceTest`, `TeamMoraleReputationServiceTest`, `TeamRosterServiceTest`, `FanClubServiceTest`, `NpcSimulationServiceTest`). Chronicle: `tests/Service/TeamChronicle/`.
