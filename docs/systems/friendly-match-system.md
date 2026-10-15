# Friendly Match System

Reference: [calendar-system.md](calendar-system.md#friendly-matches-practice), [combat-system.md](combat-system.md), [headquarters-system.md](headquarters-system.md) (HQ arena panel)

Purpose: Player-vs-player practice matches outside the league, with full combat consequences and fixed weekly kickoff windows.

## Overview

Friendly matches use the same `CombatEngine` / wave Messenger pipeline as league fixtures (`MatchType::Friendly`). They do **not** affect league standings, arena ticket revenue, or fan-base deltas. Hero XP, form, fatigue, durability, KO aging, and **permanent death** (mortality roll) apply the same as league play.

| Aspect | Rule |
|--------|------|
| Opponents | Player teams only (both must have a `User`; no NPC) |
| Home team | Challenger (invite sender) |
| Home cap | Max **1 home** friendly per team per ISO calendar week (kingdom TZ) |
| Away | Unlimited within the week (second match only if another team challenges you) |
| Kickoff windows | **Wednesday 18:00** and **Sunday 18:00** (kingdom local time) |
| Horizon | Slots offered up to **14 days** ahead |
| Invite cutoff | Create / accept only while `now < scheduledAt − 6 hours` |
| Cancel cutoff | Either participant may cancel while `now < scheduledAt − 1 hour` |
| Public list | All kingdom friendlies on `/app/friendly-matches` |
| Personal calendar | `friendly_match` feed entries only for participating teams |

## Weekly windows

Same clock as league matches (18:00), different days:

```
Tue 18:00 League → Wed 18:00 Friendly → Thu 10:00 Training → Fri 18:00 League → Sun 18:00 Friendly
```

Tick type: `TickType::FriendlyMatch` (fixture-scoped `KingdomTickLog` via `friendly_match_id`).

## Slot selection

`GET /api/v1/arena/friendly/slots?opponent_team_id=` returns candidate `scheduled_at` values where:

1. Instant is Wed or Sun 18:00 kingdom TZ, converted to UTC
2. Instant is in `(now, now + 14 days]`
3. Invite cutoff still open (`scheduledAt − 6h > now`)
4. Challenger has no other **home** friendly (pending/scheduled/in_progress) in that ISO week
5. Neither team already has an active friendly at that exact `scheduled_at`

`POST /api/v1/arena/schedule-match` must use a `scheduled_at` from that list.

## Lifecycle

| Status | Meaning |
|--------|---------|
| `pending` | Invite sent; awaiting accept/decline |
| `scheduled` | Accepted; formations editable; waiting for kickoff |
| `in_progress` | Battle simulating |
| `completed` | Battle finished |
| `declined` | Away team declined |
| `cancelled` | Cancelled before kickoff (within cancel window) |
| `expired` | Accept window closed without accept |

## Combat resolution

- Kickoff: `FriendlyMatchResolutionService::resolveMatch` (no `ArenaRevenueService`)
- Forfeit if a side lacks minimum combat-ready heroes (same roster rule as league)
- Completion: shared combat side-effects from league resolution **except** standings, fan club, ticket gold; morale/reputation match deltas are skipped
- Formations: per-match assignment via `/api/v1/friendly-matches/{id}/formation` (defaults if unset)

## API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/arena/friendly/slots` | Available slots vs opponent |
| POST | `/api/v1/arena/schedule-match` | Create pending invite |
| POST | `/api/v1/arena/friendly/{id}/accept` | Accept invite |
| POST | `/api/v1/arena/friendly/{id}/decline` | Decline invite |
| POST | `/api/v1/arena/friendly/{id}/cancel` | Cancel (T−1h) |
| GET | `/api/v1/arena/friendly` | List kingdom friendlies (filters) |
| GET/PUT | `/api/v1/friendly-matches/{id}/formation` | Match formation |

## Web

| Path | Purpose |
|------|---------|
| `/app/friendly-matches` | Public kingdom list + challenge UI |
| HQ Arena panel | Short link / pending invites widget |
