# Hero Detail Screen

Reference: [screens-overview.md](../screens-overview.md#4-hero-detail-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Hero Header: name (editable), race icon, avatar, level + XP, age indicator, **personality trait badge** (when present)
- **Personality Trait panel** (overview tab): trait name, category badge, full effect description
- Primary Attributes: STR, DEX, KON, SPD, INT, WIL, CHA, LCK (values + tooltips)
- Secondary Attributes: Form %, Fatigue %, Morale, Magic Capacity
- **Combat statistics** (overview): `matches_played`, `matches_won`, win rate — updated after league fixtures
- Equipment Slots: visual slots (Main Hand, Off-Hand, Head, Body, Hands, Feet, Amulet, Ring1, Ring2)
- Equipped Spells: spell icons + names
- **History tab:** last 15 entries from `hero_chronicle` (summons, transfers, matches, training, mastery, death)
- Training tab: recent `hero_training_history` rows + trainer assignment UI

Possible Actions/Buttons:
- Train Attributes — navigate to Training Screen
- Manage Equipment — Equipment tab / inventory
- Manage Spells — Spells tab
- Assign to Formation — quick add
- Sell on Marketplace
- Dismiss Hero (financial crisis recovery)
- Rename Hero

Backend Requirements:
- Hero detail endpoint (full data including `trait`, `ratings.base_ovr`, `ratings.complex_rating`)
- Hero update endpoint (rename)
- Hero dismiss endpoint — `POST /api/v1/heroes/{id}/dismiss`
- Hero chronicle read — server-rendered via `HeroChroniclePresenter` (no dedicated API yet)
- Trainer conversion endpoint — **planned**

Implementation:
- **Route:** `GET /app/heroes/{id}` — `HeroController::detail()`; tabs: `overview`, `equipment`, `spells`, `training`, `history`
- **Header:** `templates/components/hero/header_card.html.twig` — trait badge in meta row
- **Overview:** `templates/components/hero/combat_stats.html.twig`, `trait_panel.html.twig`
- **History:** `templates/components/hero/recent_activity.html.twig` with `heroHistory` from presenter
- **API:** `GET /api/v1/heroes/{id}` includes `trait`, `ratings` via `HeroService::serialize()`
- **Domain docs:** [hero-system.md](../systems/hero-system.md), [hero-chronicle-system.md](../systems/hero-chronicle-system.md)
