# Event System

Reference: [game-summary.md](../game-summary.md#22-event-system)

Purpose: Document scheduled events, server ticks, event lifecycle, and implementation details.

Sections to fill:
- Event types & triggers
- Server tick scheduling and processing
- Data model and event state
- Real-time notifications (WebSocket) and fallback
- Edge cases and failure handling
- Tests and monitoring
- Implementation notes

Summary:
- Dynamic world events include weekly server ticks, seasonal events, limited missions, and dungeon cycles. Events can grant XP, items, and currency.
- Each event has lifecycle states: scheduled -> active -> resolving -> complete.

Server tick responsibilities:
- Process fatigue/form recovery, league match resolution, dungeon/job processing, training/crafting queue completion, marketplace auctions.
- Runs on kingdom-local timezone and respects Game Speed multiplier.

APIs & data:
- GET /api/events — list active and upcoming events
- POST /api/events/{id}/participate — join event

Edge cases:
- Partial failures during multi-step rewards distribution; use transactional processing and retries.

