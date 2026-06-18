# Kingdom bootstrap defaults

JSON placeholders loaded by `KingdomInitConfig` when running:

```bash
php bin/console app:kingdom:initialize "Kingdom Name" [--test] [--start-offset-days=-21] [--catch-up-ticks]
```

## Quick start (local dev)

**Fresh kingdom at season start** (no simulated history):

```bash
php bin/console app:kingdom:initialize "Main Kingdom" --test
```

**Kingdom ~1 month into season 1** (recommended for testing mid-season UI and ticks):

```bash
php bin/console app:kingdom:initialize "Main Kingdom" \
  --test \
  --start-offset-days=-21 \
  --catch-up-ticks
```

| Flag | Effect |
|------|--------|
| `--test` | Season starts on **last Monday** (not next Monday). Also creates 3 test users (`player1@example.com` … `player3@example.com`, password `password`). |
| `--start-offset-days` | Shifts season start relative to that Monday anchor. **Negative** = further in the past. Overrides `season.defaults.json` → `start_offset_days`. |
| `--catch-up-ticks` | Synchronously runs all server ticks from season start through now (training, aging, league matches, economy). Without it, run `php bin/console app:ticks:run --kingdom-id=<id>` later (async via Messenger). |

Rough guide: `--test` alone ≈ 0–6 days in; `--test --start-offset-days=-21` ≈ 4 calendar weeks; `-28` ≈ 5 weeks. Season length is 77 days (11 weeks) — do not offset beyond that.

| File | Purpose |
|------|---------|
| `kingdom.defaults.json` | Kingdom entity settings (language, timezone, game_speed, level_cap, xp_modifier, marketplace_tax_rate, season_length) |
| `league_tiers.defaults.json` | `league_tiers_config` — teams_per_group, 3 tiers (T1/T2/T3) with groups, promotion/relegation slots, reward placeholders |
| `season.defaults.json` | First `LeagueSeason` (season_number, start_offset_days, status_when_started, status_when_future) |
| `team.defaults.json` | NPC team starting state: resources (gold, essence tiers), morale, reputation, chemistry, heroes_per_team, roster_races, default formation |
| `npc_teams.defaults.json` | NPC team identity: team_races pool, name prefix/suffix pools, emblem placeholder, colors pool |
| `headquarters.defaults.json` | HQ total_level and 7 facilities (barracks, training, medical, library, treasury, summoning_chamber, arena) each with level only — passive_bonuses are computed at runtime by `FacilityType::getPassiveBonuses(level)`, not stored here; `race_optimization` (arena adaptation) is overridden by the assigned team race |

Keys prefixed with `_` (e.g. `_comment`) are stripped before use and are not stored in the database.
