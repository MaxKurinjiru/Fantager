# Training System

Reference: [game-summary.md](../game-summary.md#25-training-system)

Purpose: Document training queues, cost calculations, success rates and server tick processing.

Sections to fill:
- Training queue model
- Cost & success calculation formulas
- Trainer assignment rules
- Queue processing and cancellation
- UI integration points
- Tests and performance considerations
- Implementation notes


Server tick processing notes:
- Tick worker fetches due `training_queue` rows (WHERE execute_at <= now AND status='queued'), marks as running, applies stat increases using deterministic formulas, deducts costs, and writes history records atomically.

Summary:
- Training consumes Gold (and sometimes Essence) and takes server tick cycles; success rates can be influenced by trainer assignment and diminishing returns.
- Support batch queueing and cancellation with partial refunds rules.

APIs:
- GET/POST/DELETE /api/training-queue — manage queued training jobs
- Server tick worker consumes queued items and applies stat increases, costs, and logging

