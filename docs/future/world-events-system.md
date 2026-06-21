# World Events System (Deferred)

> **Status:** Not implemented and not currently planned. This document preserves the original design for a possible future phase. The calendar and server-tick systems remain in scope; only dynamic world events (`event`, `event_participation`) were removed from the codebase.

Reference: [game-summary.md](../game-summary.md#22-calendar--server-ticks)

---

## Concept

- **Dynamic world events**, seasonal activities, and limited missions
- Heroes participating gain **XP**, level up, and improve stats
- **Fatigue/form** tracked to prevent overuse
- **Special economic events** (market fluctuations, gold rush, crafting festivals, tax holidays, resource shortages)

These are separate from **server ticks** (daily reset, training, league matches), which are documented in [calendar-system.md](../systems/calendar-system.md).

---

## Proposed Data Model

### `Event`

| Field | Type | Notes |
|-------|------|-------|
| `id` | INT | Primary key |
| `kingdom_id` | FK → Kingdom | Event scoped to one server |
| `type` | enum | `world_event`, `seasonal`, `limited_mission`, `special_economic` |
| `name` | VARCHAR(150) | Display name |
| `description` | TEXT | Player-facing description |
| `status` | enum | `scheduled` → `active` → `completed` / `cancelled` |
| `start_at`, `end_at` | DATETIME | Active window |
| `rewards` | JSON | Reward configuration |

### `EventParticipation`

| Field | Type | Notes |
|-------|------|-------|
| `id` | INT | Primary key |
| `event_id` | FK → Event | |
| `team_id` | FK → Team | Unique with `event_id` |
| `progress` | INT | Participation progress |
| `rewards_claimed` | BOOL | Whether rewards were collected |

### Proposed enums

| Enum | Values |
|------|--------|
| `EventType` | `world_event`, `seasonal`, `limited_mission`, `special_economic` |
| `EventStatus` | `scheduled`, `active`, `completed`, `cancelled` |

---

## Proposed API (not implemented)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/events` | List active and upcoming world events |
| POST | `/api/v1/events/{id}/participate` | Register team participation |

The kingdom calendar feed (`GET /api/v1/kingdom/{id}/calendar`) would optionally include `type: world_event` entries when this system is built.

---

## Proposed UI (not implemented)

Screen concepts from the original design:

- **Upcoming Events Panel** — list, rewards preview, participation requirements
- **Active Events Panel** — progress bars, time remaining
- **Event History** — completed events and earned rewards
- Actions: view details, participate, set reminder, filter by type

---

## Integration Notes

- World event lifecycle transitions could be driven by server ticks or a dedicated scheduler.
- Notifications on event start/end/completion would use the existing in-app notification system.
- Economy hooks (gold/essence bonuses) would integrate with `TeamFinancialRecord` and existing reward services.

---

## Removed Implementation (2026-06)

The following were removed from the codebase pending a future decision to implement this system:

- Entities: `App\Entity\Event\Event`, `App\Entity\Event\EventParticipation`
- Repositories: `EventRepository`, `EventParticipationRepository`
- Enums: `EventType`, `EventStatus`
- DB tables: `event`, `event_participation`
- Calendar feed aggregation of `world_event` entries
