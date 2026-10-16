# Screens

Per-screen UI contracts. **Live UI** is [../architecture/screen-code-map.md](../architecture/screen-code-map.md) plus Twig/Stimulus.

Each screen file separates **Implemented** (what Twig/API does) from **Planned**. The original screen sketch is parked at [`_deferred/screens-overview-legacy.md`](../../_deferred/screens-overview-legacy.md) — not an implementation contract.

## Site structure

- **Public** (`/`) — unauthenticated: homepage, news. Login/register open as a **modal** (`/login` and `/register` auto-open it). Password reset is standalone. Wiki stays **Planned**.
- **Game** (`/app/*`) — authenticated; Team Dashboard is the default landing page.

## Screen files

| Doc | Screen |
|-----|--------|
| [00-public-pages.md](00-public-pages.md) | Homepage, news (wiki stays Planned) |
| [00-auth-screens.md](00-auth-screens.md) | Login, register, password reset |
| [01-kingdom-selection.md](01-kingdom-selection.md) | Kingdom selection (registration) |
| [02-team-dashboard.md](02-team-dashboard.md) | Team dashboard |
| [02a-team-chronicle.md](02a-team-chronicle.md) | Team chronicle |
| [03-hero-roster.md](03-hero-roster.md) | Hero roster |
| [04-hero-detail.md](04-hero-detail.md) | Hero detail |
| [05-training.md](05-training.md) | Training |
| [06-trainer-management.md](06-trainer-management.md) | Trainer management |
| [07-formation-setup.md](07-formation-setup.md) | Formation setup |
| [08-headquarters.md](08-headquarters.md) | Headquarters |
| [09-summoning-chamber.md](09-summoning-chamber.md) | Summoning chamber |
| [10-item-equipment.md](10-item-equipment.md) | Item / equipment |
| [11-spell-management.md](11-spell-management.md) | Spell management |
| [12-combat-battle.md](12-combat-battle.md) | Combat replay |
| [13-league.md](13-league.md) | League |
| [14-calendar.md](14-calendar.md) | Calendar |
| [15-marketplace.md](15-marketplace.md) | Marketplace |
| [16-graveyard.md](16-graveyard.md) | Graveyard |
| [17-community.md](17-community.md) | Community |
| [18-arena-management.md](18-arena-management.md) | Arena (HQ panel) |
| [18a-friendly-matches.md](18a-friendly-matches.md) | Friendly matches |
| [19-player-profile-settings.md](19-player-profile-settings.md) | Player profile and settings (password change / extras stay Planned) |
| [20-finance.md](20-finance.md) | Finance ledger |
