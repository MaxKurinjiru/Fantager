# Combat/Battle Screen

Reference: [screens-overview.md](../screens-overview.md#12-combatbattle-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Match HUD:
	- Opponent team name & logo
	- Match type
	- Kill score (0–6 per team; forfeit: 3–0 or 0–0 draw)
- Combat Area:
	- Front/Back lines, hero avatars
	- HP bars, mana/energy, status effects, morale indicators
	- Position-based modifiers and terrain overlays
- Turn Indicator:
	- Current turn, active hero, speed order queue
- Action Panel:
	- Available actions (attack, skill, item, defend)
	- Target selection UI and preview (damage/impact predictions)
- Combat Log:
	- Action-by-action feed with timestamps and condensed replay markers
- Team Stats Panel:
	- Morale, remaining heroes, formation integrity, passive effects

Possible Actions/Buttons:
- Perform Action (confirm action)
- Toggle Auto-Battle
- Pause/Resume
- Speed Control (x1, x2, x4)
- Skip to End
- View Detailed Stats / Replay
- Retreat / Surrender

Backend Requirements:
- Combat simulation engine (PHP worker)
- Combat state streaming (Server-Sent Events or polling)
- Combat action endpoint (turn submission)
- Battle result endpoint and post-battle updates
- Replay/simulation generation endpoint

Sections to fill:
- Combat stream format (SSE/polling)
- Replay/log format
- Controls and speed options
- Implementation notes
