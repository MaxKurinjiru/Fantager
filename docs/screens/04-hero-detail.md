# Hero Detail Screen

Reference: [screens-overview.md](../screens-overview.md#4-hero-detail-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Hero Header: name (editable), race icon, avatar, level + XP, age indicator, **personality trait badge** (when present)
- **Personality Trait panel** (overview tab): trait name, category badge, full effect description
- Primary Attributes: STR, DEX, KON, SPD, INT, WIL, CHA, LCK (values + tooltips)
- Secondary Attributes: Form %, Fatigue %, Morale, Magic Capacity
- Equipment Slots: visual slots (Main Hand, Off-Hand, Head, Body, Hands, Feet, Amulet, Ring1, Ring2)
- Equipped Spells: spell icons + names
- Statistics: total battles, wins/losses, combat deaths, training sessions
- History Log: recent actions

Possible Actions/Buttons:
- Train Attributes — navigate to Training Screen
- Manage Equipment — Equipment Screen
- Manage Spells — Spell Management Screen
- Assign to Formation — quick add
- Sell on Marketplace
- Convert to Trainer (if Veteran+)
- Rename Hero

Backend Requirements:
- Hero detail endpoint (full data)
- Hero update endpoint (rename, stats, equipment)
- Trainer conversion endpoint

Implementation:
- **Header:** `templates/components/hero/header_card.html.twig` — trait badge in meta row.
- **Overview sidebar:** `templates/components/hero/trait_panel.html.twig` (only when `hero.trait` is not null).
- **API:** `GET /api/v1/heroes/{id}` includes `trait` (nullable enum value string) via `HeroService::serialize()`.
- **Domain doc:** [hero-system.md](../systems/hero-system.md) § Hero Traits / Player UI.

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
