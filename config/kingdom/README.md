# Kingdom bootstrap defaults

JSON placeholders loaded by `KingdomInitConfig` when running:

```bash
php bin/console app:kingdom:initialize "Kingdom Name" [--test]
```

With `--test`, the season starts on the previous Monday and three default test users are created (`player1@example.com` … `player3@example.com`, password `password`).

| File | Purpose |
|------|---------|
| `kingdom.defaults.json` | Kingdom entity settings (language, timezone, game_speed, level_cap, xp_modifier, marketplace_tax_rate, season_length) |
| `league_tiers.defaults.json` | `league_tiers_config` — teams_per_group, 3 tiers (T1/T2/T3) with groups, promotion/relegation slots, reward placeholders |
| `season.defaults.json` | First `LeagueSeason` (season_number, start_offset_days, status_when_started, status_when_future) |
| `team.defaults.json` | NPC team starting state: resources (gold, essence tiers), morale, reputation, chemistry, heroes_per_team, roster_races, default formation |
| `npc_teams.defaults.json` | NPC team identity: team_races pool, name prefix/suffix pools, emblem placeholder, colors pool |
| `headquarters.defaults.json` | HQ total_level and 7 facilities (barracks, training, medical, library, treasury, summoning_chamber, arena) each with level only — passive_bonuses are computed at runtime by `FacilityType::getPassiveBonuses(level)`, not stored here; race_optimization is overridden by the assigned team race |

Keys prefixed with `_` (e.g. `_comment`) are stripped before use and are not stored in the database.
