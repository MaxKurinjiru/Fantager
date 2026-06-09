# Kingdom bootstrap defaults

JSON placeholders loaded by `KingdomInitConfig` when running:

```bash
php bin/console app:kingdom:initialize "Kingdom Name"
```

| File | Purpose |
|------|---------|
| `kingdom.defaults.json` | Kingdom entity settings (language, timezone, economy modifiers, …) |
| `league_tiers.defaults.json` | `league_tiers_config` — tiers, groups, promotion/relegation, reward placeholders |
| `season.defaults.json` | First `LeagueSeason` (number, start offset, status rules) |
| `team.defaults.json` | Starting resources, hero count/races, default formation |
| `npc_teams.defaults.json` | NPC team names, colors, emblem placeholder |
| `headquarters.defaults.json` | HQ level and facility placeholders |

Keys prefixed with `_` (e.g. `_comment`) are stripped before use and are not stored in the database.
