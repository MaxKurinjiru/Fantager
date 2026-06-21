# Player Inactivity System

Reference: [team-system.md](team-system.md), [team-chronicle-system.md](team-chronicle-system.md), [financial-crisis-system.md](financial-crisis-system.md), [calendar-system.md](calendar-system.md)

Purpose: Release teams from verified players who stop playing, so NPC slots remain available for active managers.

---

## Overview

Verified players must stay active to keep their team. Activity is tracked on `auth_user.last_activity_at` and updated on login and authenticated game requests (at most once per hour).

| Event | When | Channel |
|-------|------|---------|
| **Warning** | 21 days without activity | Email + in-app notification |
| **Release** | 28 days without activity | Email + in-app notification; team returned to NPC pool |

Unlike bankruptcy, released teams **keep** their gold, debt, heroes, and HQ progress.

There is **no dashboard banner** — inactive players do not log in, so outreach happens via **email** (primary) and in-app notifications (when they return).

---

## User Fields

| Field | Type | Description |
|-------|------|-------------|
| `last_activity_at` | datetime | Last recorded login or in-game activity |
| `inactive_warning_sent_at` | datetime? | When the current inactivity warning was sent; cleared on activity |
| `team_reassignment_available_at` | datetime? | Shared with bankruptcy — cooldown before claiming a new NPC team (7 days) |

---

## Daily Tick Flow (`inactive_player_cleanup`)

Runs daily at **03:45** (Kingdom local time), after inactive registration cleanup.

1. Find verified users in the Kingdom with a non-NPC team
2. If inactive ≥ 28 days → release team to NPC pool, email + notify player, set reassignment cooldown, chronicle entry `player_released` / `inactivity`
3. If inactive ≥ 21 days and no warning sent for this inactivity period → email + notify player

---

## Activity Tracking

| Event | Service |
|-------|---------|
| Email verification | `VerificationService` sets `last_activity_at` |
| Login | `UserActivityListener` on `InteractiveLoginEvent` |
| Game routes (`app_*`, `api_v1_*`) | `UserActivityListener` on kernel request (debounced to 1 h) |

Service: `App\Service\Auth\PlayerActivityService`

---

## Team Release

When inactivity release triggers:

1. Team `user_id` → NULL, `is_npc` → true
2. Chronicle: `TeamChronicleService::recordPlayerReleased()` with reason `inactivity`
3. Team state preserved (gold, debt, roster, HQ)
4. User `team` → NULL; `team_reassignment_available_at` set (+7 days)
5. Email + system notification sent to player
6. User account remains — only the team assignment is removed

Service: `App\Service\Auth\PlayerInactivityService::executeInactivityRelease()`

---

## Email Templates

| Template | When |
|----------|------|
| `email/inactivity_warning.html.twig` | 21 days inactive |
| `email/inactivity_release.html.twig` | Team released at 28 days |

---

## Constants (`PlayerInactivityService`)

| Constant | Value |
|----------|-------|
| `WARNING_DAYS` | 21 |
| `RELEASE_DAYS` | 28 |
| Reassignment cooldown | 7 days (`FinancialCrisisService::REASSIGNMENT_COOLDOWN_DAYS`) |

---

## Distinction from Inactive Registration Cleanup

| | Registration cleanup (03:30) | Player inactivity (03:45) |
|--|------------------------------|---------------------------|
| Target | Unverified accounts | Verified players with teams |
| Threshold | 1 day since registration | 28 days since last activity |
| User account | Deleted | Kept |
| Team | Released to NPC pool | Released to NPC pool |
| Outreach | N/A (account deleted) | Email |
