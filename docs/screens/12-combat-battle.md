# Combat/Battle Screen (Replay Viewer)

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [combat-system.md](../systems/combat-system.md)

**When:** Watch a **completed** match. Combat is fully automated — players do **not** issue mid-battle actions. Pre-match behaviour is set on [Formation Setup](07-formation-setup.md) (`approach`, movement goal, per-slot L1/L2 — A7.6 / [#12](../product/known-issues.md)).

**Status:** Implemented at `/app/battles/{id}` (`Web\BattleController`, `templates/battle/index.html.twig`). Post-match replay only (no live / in-progress match UI). Replay HUD lock: [Locked: replay HUD](#locked-replay-hud). Combat leftovers [#9](../product/known-issues.md) Resolved (A7.5).

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

- Home and away **team names** and **emblems** (`Team.emblem`, fallback shield glyph)
- Match-type label: League or Friendly (`Battle.matchType`; not Arena / Dungeon)
- Kill score (`scoreA` : `scoreB`)
- Result badge: winner / loser / draw (from `Battle.result`)

Not in the HUD: morale widget, initiative queue, remaining-heroes sidebar, formation-integrity meter, skip-to-end, hex debug labels, second formation grid.

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

Participant teams only (access denied otherwise). Match type comes from `Battle.matchType`. Do not invent a fixture round number in the HUD.

### Actions

Replay controls only — **no** Perform Action, target selection, Auto-Battle toggle, or mid-match surrender:

- Play / Pause
- Step forward / back
- Scrubber (seek by round)
- Speed ×1 / ×2 / ×4
- Return: League → `/app/league`; Friendly → `/app/friendly-matches` (history tab)

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

## Locked: replay HUD

**#9 HUD slice (A7.5, 16 Oct 2026).** Live chrome is emblems, League/Friendly label, and a contextual back link. Cut from this screen (do not re-add without a new product decision): morale widget, initiative queue, team-stats sidebar, skip-to-end, hex coordinate debug labels, unused formation-grid sketch.

Kickoff `morale` on the combat snapshot is not a replay HUD. In-combat `morale_change` is **Cut** (#9). Turn order stays in the accordion (`turn_start`); do not add a second initiative queue. Engine, log format, round-200 / fumble / KO≠death stay locked.

## Still open

None for this screen. Race synergy and `POST /api/v1/combat/simulate` are **Cut** ([#9](../product/known-issues.md) Resolved / A7.5). Haste/Shock **do** change turn order (initiative sort only). `turn_start.initiative` includes the YAML speed modifier. Do not add an initiative queue HUD.

Log accordion uses event type `spell` (i18n key remains `battle.spell_cast`). Do not change locked round-200 / fumble / KO≠death rules.

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/battle/index.html.twig` |
| Styles | `assets/styles/components/_battle.scss` |
| Stimulus | `hex_battle_replay` (canvas), `battle_report` (accordion) |
