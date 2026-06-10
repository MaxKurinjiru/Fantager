# Combat System

Reference: [game-summary.md](../game-summary.md#210-combat-system)

Purpose: Document combat simulation, match eligibility, scoring, turn order, status effects, and result processing.

## Match Eligibility

Before any match is simulated, each team is checked for roster readiness:

| Condition | Result |
|-----------|--------|
| Both teams have ≥ 6 **combat-ready** heroes | Proceed to simulation |
| Exactly one team has < 6 combat-ready heroes | **Automatic forfeit** — eligible team wins **3–0** (kill score); no simulation |
| Both teams have < 6 combat-ready heroes | **Automatic draw** — **0–0** kill score; no simulation |

**Combat-ready hero:** active status, not a Trainer, not permanently dead (Graveyard).

A team needs 6 combat-ready heroes to **enter** a match, independent of formation lineup configuration. Formation still requires all 6 slots filled when simulating.

## Match Scoring

- Each **kill** (enemy hero removed from the opposing lineup during the match) = **1 point** for the scoring team
- Maximum score per team = **6** (one per enemy lineup slot)
- Forfeit win = **3–0** (half of maximum 6)
- Double forfeit = **0–0**

**League table points** (Win 3 / Draw 1 / Loss 0) are derived from the match winner/loser/draw, not from kill totals directly.

## Combat Flow

1. Formation selection (6 heroes per team, 3 front / 3 back)
2. Roster eligibility check (see above)
3. Queue match for simulation (Redis) — skipped on forfeit/draw
4. PHP worker runs deterministic turn-based simulation
5. Apply post-match updates (XP, form, fatigue, morale, aging)
6. Store result in `Battle` entity; broadcast via Server-Sent Events (SSE)

## Simulation (to be defined)

Sections still to fill:

- Combat engine architecture (worker/service)
- Turn resolution and speed order
- Damage, healing, and status effect formulas
- Logging and replay format (`combat_log` JSON structure)
- Performance and scaling considerations

Status effect reference config: [config/game/status_effects.yaml](../../config/game/status_effects.yaml)

## Summary

Combat runs in a deterministic simulation engine (server-side worker) producing event logs and final results. Turn order is determined by speed (SPD); actions resolve per-turn with spell and status interactions. Kill-based scoring determines the displayed match result; understaffed teams forfeit without simulation.

## API Endpoints

- `POST /api/combat/simulate` — run simulation for practice (both teams must be eligible)
- `GET /api/combat/{matchId}/log` — retrieve combat log/replay
