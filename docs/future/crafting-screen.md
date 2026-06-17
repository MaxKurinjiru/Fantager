# Crafting Screen (Deferred)

> **Status:** Not implemented and not currently planned. The crafting backend and UI were removed from the codebase. This document describes the intended design for a possible future phase.

Reference: [screens-overview.md](../screens-overview.md#20-crafting-screen-deferred) and [crafting-system.md](crafting-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Crafting Recipe List:
	- Result item, required materials, essence cost, gold cost
	- Success rate and crafting time
- Material Inventory:
	- Available materials and quantities
- Crafting Queue:
	- Active jobs with ETA and progress bars

Possible Actions/Buttons:
- Start Crafting
- Queue Craft
- Cancel Craft
- View Recipe Details
- Acquire Materials (market or resource nodes)

Backend Requirements:
- Crafting recipes endpoint
- Crafting queue endpoint and job processing
- Crafting success calculation (RNG) and failure handling
- Server tick processing and notifications

Sections to fill:
- Recipes and ingredient models
- Crafting queue APIs
- Success calculations and RNG
- Implementation notes
