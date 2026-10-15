# Combat/Battle Screen (Replay Viewer)

Reference: [screens/overview-legacy.md](overview-legacy.md#12-combatbattle-screen) (historical), [combat-system.md](../systems/combat-system.md), [architecture/screen-code-map.md](../architecture/screen-code-map.md)

**When:** Watch a **completed** match. Combat is fully automated — players do **not** issue mid-battle actions. Pre-match behaviour is set on [Formation Setup](07-formation-setup.md) (`approach` now; targeting / spell priorities later — A7.6).

**Status:** Implemented at `/app/battles/{id}` (`Web\BattleController`, `templates/battle/index.html.twig`). Post-match replay only (no live / in-progress match UI). HUD extras (logos, match-type label, morale, initiative queue, skip-to-end) stay on the **Still open** list — not live. Leftovers: [#9](../product/known-issues.md) / A7.5.

---

## Design principles

| Principle | Detail |
|-----------|--------|
| **Fully automated** | Server simulates the entire match from both formations; no turn submission UI |
| **Replay, not interactive control** | This screen reads a **completed** `Battle.combat_log` and plays it back |
| **Strategy before kickoff** | Targeting, spells, and tactics are set on Formation Setup (or fixture lineup) before the match runs |
| **No live UI (for now)** | League waves may process rounds asynchronously, but players do not watch mid-simulation |

---

## Implemented

### Displayed information

**Match HUD**

- Home and away **team names**
- Kill score (`scoreA` : `scoreB`)
- Result badge: winner / loser / draw (from `Battle.result`)

Not in the HUD: team logos, match-type label (League / Friendly / …), morale, remaining-heroes sidebar, formation-integrity meter.

**Hex replay** (when `Battle.runState` is present)

- Canvas over `run_state` + `combat_log`
- Round label; playback starts from snapshot `currentHp` and folds `damage`, `heal_applied`, and `status_tick`
- Play / Pause, step previous / next, round scrubber, speed ×1 / ×2 / ×4 (default ×2)

**Round log**

- Accordion of `combat_log` events (`battle_report`)
- Semantic row styles per event type
- Spell events use type `spell` (i18n key remains `battle.spell_cast`)

**Forfeit**

- When `run_state` is null: forfeit copy instead of the canvas (`league.forfeit_desc`)

Participant teams only (access denied otherwise). `LeagueFixture` is loaded in the controller but **not** shown as match-type / round chrome.

### Actions

Replay controls only — **no** Perform Action, target selection, Auto-Battle toggle, or mid-match surrender:

- Play / Pause
- Step forward / back
- Scrubber (seek by round)
- Speed ×1 / ×2 / ×4
- Return to League (`/app/league`)

There is **no** skip-to-end button (the scrubber already seeks). No post-match detailed-stats panel on this page.

### Backend

- Wave-based combat simulation (Messenger cohort; see [combat-system.md](../systems/combat-system.md#wave-based-messenger-orchestration))
- Persist `Battle` with kill scores, result, and `combat_log` JSON after completion
- `GET /api/v1/battles/{id}` — battle result metadata
- `GET /api/v1/battles/{id}/log` — full combat log (web replay is server-rendered JSON on the page, not fetched by Stimulus)
- Optional: notify when a fixture result is ready — not turn-by-turn client streaming
- Post-match updates applied on `CompleteBattle`, not by this screen

Canonical `combat_log` format: [combat-system.md — Simulation Contract](../systems/combat-system.md#simulation-contract).

---

## Still open

Design list, not in steps 2–7 (A7.5 / #9):

- Team logos
- Match-type label
- Morale indicators
- Initiative / speed-order queue
- Team-stats sidebar
- Skip-to-end button (scrubber already seeks)
- Log accordion uses event type `spell` (i18n key remains `battle.spell_cast`)
- Haste/Shock may show as status in the log without changing turn order (see [combat-system.md](../systems/combat-system.md#speed--initiative-init))
- Hex coordinate debug labels are not drawn on the replay canvas
- `POST /api/v1/combat/simulate` sandbox

Do not change locked round-200 / fumble / KO≠death rules.

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/battle/index.html.twig` |
| Stimulus | `hex_battle_replay` (canvas), `battle_report` (accordion) |
