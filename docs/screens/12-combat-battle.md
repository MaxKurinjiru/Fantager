# Combat/Battle Screen (Replay Viewer)

Reference: [screens-overview.md](../screens-overview.md#12-combatbattle-screen), [combat-system.md](../systems/combat-system.md)

Purpose: Watch a **completed** (or freshly simulated) match. Combat itself is fully automated — players do **not** issue mid-battle actions. Pre-match behaviour is configured via the [Formation System](../systems/formation-system.md) (`approach` now; targeting / spell priorities in later phases).

**Status:** Not implemented. Blocked on wave orchestration + turn-resolution engine and full `combat_log` events ([known-issues.md](../known-issues.md) #1). Product surface is **post-match replay only** (no live / in-progress match UI).

---

## Design principles

| Principle | Detail |
|-----------|--------|
| **Fully automated** | Server simulates the entire match from both formations; no turn submission UI |
| **Replay, not interactive control** | This screen reads a **completed** `Battle.combat_log` and plays it back |
| **Strategy before kickoff** | Targeting, spells, and tactics are set on Formation Setup (or fixture lineup) before the match runs |
| **No live UI (for now)** | League waves may process rounds asynchronously, but players do not watch mid-simulation |

---

## Displayed Information

- **Match HUD**
  - Opponent team name & logo
  - Match type (League, Friendly, …)
  - Kill score (0–6 per team; forfeit: 3–0 or 0–0 draw)
  - Final score from completed log
- **Combat Area (replay visualization)**
  - Front/Back lines, hero avatars
  - HP bars, status effects, morale indicators driven by log events
  - Position / formation integrity from engine events (when present in the log)
- **Turn Indicator**
  - Current turn / active hero highlight during playback
  - Speed-order queue preview (from log or derived state)
- **Combat Log**
  - Action-by-action feed synced with playback position
- **Team Stats Panel**
  - Morale, remaining heroes, formation integrity, passive effects (from log snapshots)

---

## Possible Actions/Buttons

Replay controls only — **no** Perform Action, target selection, Auto-Battle toggle, or mid-match surrender:

- Play / Pause
- Speed control (×1, ×2, ×4)
- Skip to end (jump to result)
- Step forward / back (optional)
- View detailed match stats (post-result summary)
- Return to League / Dashboard

---

## Backend Requirements

- Wave-based combat simulation (Messenger cohort; see [combat-system.md](../systems/combat-system.md#wave-based-messenger-orchestration))
- Persist `Battle` with kill scores, result, and `combat_log` JSON after completion
- `GET /api/v1/battles/{id}` — battle result metadata
- `GET /api/v1/battles/{id}/log` — full combat log for replay
- Optional: notify when a fixture result is ready — not turn-by-turn client streaming
- Post-match updates applied on `CompleteBattle`, not by this screen

---

## Log contract

Canonical `combat_log` format (event stream + seed, event payloads, wave orchestration): [combat-system.md — Simulation Contract](../systems/combat-system.md#simulation-contract).

## Sections still to fill (implementation)

- Replay client state machine (speed, seek via event fold, sync with log)
- Web route / template mapping (`/app/battles/{id}` planned — see [route-map.md](../route-map.md#combat))
