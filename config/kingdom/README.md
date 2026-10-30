# Kingdom bootstrap defaults

JSON placeholders loaded by `KingdomInitConfig` when running:

```bash
php bin/console app:dev:bootstrap
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
| `--test` | Season starts on **last Monday** (not next Monday) and ensures the 3 default test users (`player1–3@example.com` / `password`). Existing emails are skipped. |
| `--start-offset-days` | Shifts season start relative to that Monday anchor. **Negative** = further in the past. Overrides `season.defaults.json` → `start_offset_days`. |
| `--catch-up-ticks` | Synchronously runs all server ticks from season start through now (training, aging, league matches, economy). Without it, run `php bin/console app:ticks:run --sync --kingdom-id=<id>` (or async `app:ticks:run` + Messenger). |

Rough guide: `--test` alone ≈ 0–6 days in; `--test --start-offset-days=-21` ≈ 4 calendar weeks; `-28` ≈ 5 weeks. Season length is 77 days (11 weeks) — do not offset beyond that.

If league fixtures stayed **scheduled** after catch-up (timezone mismatch on older bootstraps), resolve them manually:

```bash
php bin/console app:league:resolve-pending-fixtures --kingdom-id=<id> --dry-run
php bin/console app:league:resolve-pending-fixtures --kingdom-id=<id>
```

Prefer `app:ticks:run`. The league-resolve and arena-revenue commands are break-glass recovery; they skip already-completed fixtures / already-paid arena rows. Do not run them while the tick pipeline is processing matches.

**Multi-season NPC/balance simulation** (new world, live pipeline, JSONL reports). Shell loop — not one long PHP process:

```bash
bash scripts/simulate-kingdom.sh --init --name="Balance Sim" --pace=classic --seasons=8 -o /tmp/seasons.jsonl
```

Details: [docs/guides/local-setup.md](../../docs/guides/local-setup.md#multi-season-balance--npc-simulation).

| File | Purpose |
|------|---------|
| `kingdom.defaults.json` | Kingdom entity settings other than the preset (language, timezone, season_length). Hero level cap is `HeroProgressionConfig::LEVEL_CAP`. Marketplace tax is `MarketplaceService::TAX_RATE_PERCENT` |
| `pace-presets.json` | Required world presets (`classic`, `fast_season`, `short_careers`, `rich_world`, `test`), including `xp_modifier`. `app:kingdom:initialize --pace` copies one set onto the new kingdom |
| `league_tiers.defaults.json` | `league_tiers_config` — teams_per_group, 3 tiers (T1/T2/T3) with groups, promotion/relegation slots, reward placeholders |
| `season.defaults.json` | First `LeagueSeason` (season_number, start_offset_days, status_when_started, status_when_future) |
| `team.defaults.json` | NPC team starting state: resources (gold), morale, reputation, chemistry, heroes_per_team, roster_races, default formation |
| `npc_teams.defaults.json` | NPC team identity: team_races pool, name prefix/suffix pools, emblem placeholder, colors pool |
| `headquarters.defaults.json` | HQ total_level and 7 facilities (barracks, training, medical, library, treasury, summoning_chamber, arena) each with level only — passive_bonuses are computed at runtime by `FacilityType::getPassiveBonuses(level)`, not stored here; `summon_stat_total_cap` is unread (A6.5); `race_optimization` (arena adaptation) is overridden by the assigned team race |

Keys prefixed with `_` (e.g. `_comment`) are stripped before use and are not stored in the database.
