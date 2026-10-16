# Kingdom Selection (Registration Step)

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [kingdom-system.md](../systems/kingdom-system.md), [00-auth-screens.md](00-auth-screens.md)

**When:** Field on the **register form**, not a separate post-login screen. Choice is permanent (`User.kingdom_id`); no kingdom switcher after registration.

**Live UI:** `templates/components/auth/register_panel.html.twig` (desktop radio cards + mobile `<select>`). Stimulus `auth_modal` keeps select and radios in sync.

---

## Implemented

### Displayed information

For each kingdom from `KingdomService::listWithCapacity()` (Twig `auth_kingdoms()` on pages that do not pass `kingdoms`):

- Name
- Main language
- Game speed (`Nx`)
- Season length (days)
- Current player count / total capacity (capacity from `league_tiers_config`; e.g. `23/60`)
- Full kingdoms: disabled control + “full” copy

Permanent-choice warning next to the field. If the list is empty, a warning and a hidden `kingdom_id=0`.

Timezone is on the JSON API payload and is **not** shown on the register cards. Theme/lore, icon/flag, expandable details, and filter/sort are **not** in the form.

### Actions

- Choose one kingdom and submit registration (same POST as the rest of the register form)
- Full kingdoms cannot be selected

There is no separate “confirm kingdom” step, no “show details” expander, and no filter/sort.

### Backend

- Register POST writes `kingdom_id` on the new user (`RegistrationService`)
- Server checks the kingdom exists and has capacity
- `GET /api/v1/kingdoms` (`Api\V1\KingdomController`) returns the same list as JSON: `id`, `name`, `language`, `timezone`, `game_speed`, `season_length`, `capacity`, `player_count` — used as a public API, **not** fetched by the register Twig (server-rendered)

---

## Planned

Original sketch, not in the register UI:

- Theme / lore copy per kingdom
- Time zone on the cards
- Kingdom icon / flag
- Expandable “show details”
- Filter / sort by language, game speed, occupancy
- Standalone kingdom-selection screen after login
