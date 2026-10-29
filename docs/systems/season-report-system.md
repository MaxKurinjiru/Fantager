# Season Report System

Reference: [league-system.md](league-system.md), [calendar-system.md](calendar-system.md)

Status: Live  
Screens: [13-league.md](../screens/13-league.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none  
Lock: [Locked: frozen snapshot, two consumers](#locked-frozen-snapshot-two-consumers)

Purpose: Freeze one end-of-season document per kingdom. Players see a yearbook (own team vs group medians). Operators export the same JSON for multi-season simulation health checks.

## When it is written

`SeasonTransitionService::executeTransition()` calls `SeasonReportService::recordForSeason()` after rewards and after the season is marked `completed`, in the same flush. The write is **idempotent**: an existing row for that `season_id` is left alone unless `--force` rebuilds it.

Historical completed seasons (before this feature) have no row until `app:season:report:build --kingdom-id=` backfills them from standings + `season_ended` chronicle.

Opening gold for season N is the previous report’s `gold.end` when that report exists.

## Payload

Table `season_report`: `kingdom_id`, unique `season_id`, `season_number`, indexed scalars (`median_gold`, `median_combatant_age`, `combat_death_count`, `market_volume`, `crisis_count`, `mean_kill_score`), JSON `payload`.

JSON `schema_version` is `1`. Blocks:

- `kingdom` / `season` — pace preset, team count, NPC share, treasury (validation only)
- `teams` — standing, gold open/end/reward, ledger totals by `FinancialRecordType` in the season date window, HQ levels, roster histograms (stored `Hero.level`, `baseOvr`, displayed age), chronicle event counts, combat deaths from graveyard
- `aggregates` — kingdom / tier / group p25/p50/p75 for gold, OVR, age, level, points; gold Gini; crisis count
- `combat` — mean kill score, home/away/draw rates, round-200 share, forfeit share (`combat_log.simulator = forfeit`)
- `market` — transaction count, volume, fees
- `aging` — median displayed age by race; combat deaths
- `npc` — mean points/gold/wins by NPC archetype
- `league.yo_yo_count` — promoted last season and relegated this season
- `health` — band checks from `config/game/season-health.yaml` (`ok` / `low` / `high` / `missing`)

Health flags **do not** change ticks or rewards. Tune the YAML after simulation runs.

## Player yearbook

Finished-season tab `yearbook` on `GET /app/league`. `SeasonYearbookPresenter` keeps the viewer’s team, group/tier medians, vs-median deltas, and public group highlights (champion name, best kill difference). It omits other teams’ wallets, Royal Treasury, NPC internals, and health flags.

Chemistry / reputation / fan_base are flavor copy. Combat does not read chemistry.

JSON: `GET /api/v1/league/yearbook?seasonId=`.

Arena HQ last-18 attendance charts stay on the arena panel. Yearbook income uses `arena_revenue` ledger totals for the season window only.

## Operator export

```bash
docker exec -u apache fantager-web php bin/console app:season:report:build --kingdom-id=1
docker exec -u apache fantager-web php bin/console app:season:report:export --kingdom-id=1 -o /tmp/seasons.jsonl
```

`--force` overwrites. `--alerts-only` prints band alerts without JSONL. One JSONL line per completed season.

## Locked: frozen snapshot, two consumers

Do not recompute the yearbook from live `Hero` rows or 30-day `TeamDailySnapshot` on read. Do not feed health alerts into tick logic. Do not expose treasury or other teams’ gold on the player tab. Do not add `POST /api/v1/combat/simulate`. Do not widen HQ arena analytics beyond last 18 payouts.

---

## Implementation notes

| Piece | Location |
|-------|----------|
| Entity | `SeasonReport` |
| Build | `SeasonReportBuilder`, `SeasonStats`, `SeasonHealthEvaluator`, `SeasonHealthConfig` |
| Persist | `SeasonReportService` (hooked from `SeasonTransitionService`) |
| Player view | `SeasonYearbookPresenter`, `templates/league/_yearbook.html.twig` |
| CLI | `app:season:report:build`, `app:season:report:export` |
| Bands | `config/game/season-health.yaml` |

**Tests:** `tests/Service/League/SeasonStatsTest.php`, `SeasonHealthEvaluatorTest.php`, `SeasonYearbookPresenterTest.php`.
