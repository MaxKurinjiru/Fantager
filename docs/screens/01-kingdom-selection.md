# Kingdom Selection (Registration Step)

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [kingdom-system.md](../systems/kingdom-system.md), [00-auth-screens.md](00-auth-screens.md)

**When:** Field on the **register form**, not a separate post-login screen. Choice is permanent (`User.kingdom_id`); no kingdom switcher after registration.

**Status:** Live on the register panel. The form shows one summary card, or a placeholder when nothing is chosen. The list opens in a stacked modal on mobile and desktop.

**Live UI:** `templates/components/auth/register_panel.html.twig` and the picker in `templates/auth/_modal.html.twig`. Stimulus `auth_modal` opens the picker, filters it, and writes `kingdom_id`.

---

## Implemented

### Displayed information

For each kingdom from `KingdomService::listWithCapacity()` (Twig `auth_kingdoms()` on pages that do not pass `kingdoms`):

- Name
- Main language
- Timezone (IANA id, underscores shown as spaces)
- Current player count / total capacity (capacity from `league_tiers_config`; e.g. `23/60`)
- Full kingdoms: disabled control + “full” copy
- Rules on the card: pace preset name (Classic, Fast season, Short careers, Rich world, Test), season length (days), and the season number, labeled **Season:** (`KingdomService::listWithCapacity()` → `activeSeason`). Numeric paces, XP, tax, and the hero level cap are not shown. Tax is **10** everywhere. XP is stored from the preset (`1.00` on every current preset)

Permanent-choice warning next to the field. If the list is empty, a warning and a hidden `kingdom_id=0`.

The form itself shows either the placeholder (“select a kingdom”) or the chosen kingdom’s card (identity line, rules line, and a change hint). Both are the same control on mobile and desktop. Choosing or changing opens the picker modal: a language filter (`All languages` plus each language present in the list) and one card per kingdom. Full kingdoms stay visible and disabled. Theme/lore, icon/flag, expandable details, and sort are **not** in the form.

### Actions

- Open the picker from the placeholder or the chosen card
- Filter the list by language
- Choose one kingdom (writes the hidden `kingdom_id`) and submit registration (same POST as the rest of the register form)
- Full kingdoms cannot be selected

There is no separate “confirm kingdom” step and no “show details” expander. Language is the only filter.

### Backend

- Register POST writes `kingdom_id` on the new user (`RegistrationService`)
- Server checks the kingdom exists and has capacity
- `GET /api/v1/kingdoms` (`Api\V1\KingdomController`) returns the same list as JSON: `id`, `name`, `language`, `timezone`, `pace_preset`, `training_speed`, `recovery_speed`, `aging_speed`, `construction_speed`, `economy_scale`, `fan_pace`, `summon_cap`, `xp_modifier`, `season_length`, `capacity`, `player_count`, `active_season_number` — used as a public API, **not** fetched by the register Twig (server-rendered). Pace values are required and come from the preset chosen at kingdom creation. `active_season_number` is the season currently in progress

---

## Sketch leftover

Not a contract:

- Theme / lore copy per kingdom
- Kingdom icon / flag
- Expandable “show details”
- Sort, or filter by pace preset or occupancy
- Standalone kingdom-selection screen after login
