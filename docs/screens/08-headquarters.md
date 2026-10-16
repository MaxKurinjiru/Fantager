# Headquarters Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [headquarters-system.md](../systems/headquarters-system.md)

**When:** Upgrade the seven facilities, set arena race adaptation, open Arena / Summoning panels.

**Status:** Live at `GET /app/hq`. `/app/arena` and `/app/summon` **redirect** here (`?facility=arena` / `?facility=summoning_chamber`). `summon_stat_total_cap` is stored/computed and unread — HQ cards and API do **not** show it as a live bonus.

**Live UI:** `templates/hq/index.html.twig`, `templates/components/hq/`.

---

## Implemented

### Header stats (`compound_stats.html.twig`)

- Weekly HQ maintenance, weekly payroll
- Active race optimization (or none)
- Unpaid debt when crisis debt > 0
- Financial-crisis banner when applicable

No dedicated “HQ total level” or theme-preview illustration. `computedTotalLevel` is used for upgrade **costs**, not as a headline stat.

### Facility grid (7)

Cards: training, medical, library, treasury, barracks, summoning chamber, arena. Plus a separate **arena adaptation** card.

Each facility card: name, description, level, current **live** passive bonuses and next-level values, upgrade gold cost, upgrade / downgrade. Summoning Chamber cards show `summon_base_stat_bonus` and `summon_stat_random_bonus` only — not `summon_stat_total_cap`. **Manage** only for training (link to `/app/training`), arena, and summoning chamber (panels).

Upgrade in progress: page banner with completion date; **cancel** in the first hour of an upgrade (`POST /api/v1/hq/cancel-upgrade`). Downgrade-lock-cycle warning when set. One facility operation at a time.

### Arena adaptation

Race `<select>` (or none) → `POST /api/v1/hq/optimize`. Pending / locked states shown on the card. This is the summoning “home theme”, not a visual HQ skin.

### Panels

- Arena: [18-arena-management.md](18-arena-management.md)
- Summoning: [09-summoning-chamber.md](09-summoning-chamber.md)

### Backend

| Piece | Location |
|-------|----------|
| Web | `GET /app/hq` → `Web\HeadquartersController` |
| Data | `GET /api/v1/hq` |
| Upgrade / downgrade / cancel | `POST /api/v1/hq/upgrade`, `/downgrade`, `/cancel-upgrade` |
| Adaptation | `POST /api/v1/hq/optimize` |
| Domain | `HeadquartersService`, `FacilityType::getPassiveBonuses()` |

---

## Parked

Do not implement unless the user explicitly asks.

- Visual HQ themes / visitor access / morale-over-time
- Combat home-advantage as a live match buff

## Sketch leftover

Not a contract:

- Facility layout editor
- Separate passive-bonuses summary board (bonuses are on each card)

Arena attendance graphs/history are live in the arena facility panel: [18-arena-management.md](18-arena-management.md#locked-attendance-analytics).

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/hq/index.html.twig` |
| Cards / panels | `templates/components/hq/` |
| Stimulus | `hq`, `arena`, `arena_analytics`, `summoning` |
