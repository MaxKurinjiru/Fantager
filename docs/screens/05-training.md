# Training Screen

Reference: [screens-overview.md](../screens-overview.md#5-training-screen), [training-system.md](../systems/training-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

## Displayed Information

- **Trainers Overview Panel:**
  - List of team's Trainers
  - For each Trainer:
    - Name, race, age
    - Configuration: Focus type (Attribute, Magic, Form, or Idle) and target attribute
    - Slots occupied vs. slots limit
    - List of assigned heroes currently training under this Trainer
- **Active Team Status:**
  - Whether training assignments and focus changes are currently **locked** (from Tuesday 12:00 to Thursday 10:00)
  - Next training tick execution time (weekly Thursday at 10:00)

## Possible Actions/Buttons

- **Configure Trainer Focus:**
  - Open a modal to change a Trainer's training focus type (Attribute, Magic, Form, or Idle) and select target attribute (if Attribute training type is chosen)
- **Assign Hero to Trainer:**
  - Select an available team hero and assign them to an empty slot on a Trainer
- **Unassign Hero from Trainer:**
  - Remove an assigned hero from a Trainer's slot, returning them to "Available" status

## Backend Requirements

- GET `/api/v1/training/trainers` — List team's trainers, current configurations, hero slot occupancy, limits, and team lock status.
- POST `/api/v1/training/trainers/{id}/configure` — Configure trainer focus (request parameters: `type`, `attribute`).
- POST `/api/v1/training/trainers/{id}/assign` — Assign a hero to a trainer's slot (request parameter: `hero_id`).
- POST `/api/v1/training/trainers/{id}/unassign` — Unassign a hero from a trainer (request parameter: `hero_id`).
