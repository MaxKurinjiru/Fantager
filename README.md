# Fantager — Project Overview

## Short description

Fantager is a server-backed arena-management game built with Symfony and a Webpack Encore frontend.

## Game progress

Player-facing snapshot: **domain → block → feature**. **Not a contract** — live rules live in [`docs/systems/`](docs/systems/README.md). Spec links: [docs/product/progress.md](docs/product/progress.md).

| Icon | Meaning |
|------|---------|
| ✅ | **Live** — in the game |
| 🟡 | **Incomplete** — leftover player-visible work (none right now) |
| 📋 | **Planned** — parked until a product ask |
| ⏳ | **Future** — Milestone 8/9 |
| ✂️ | **Cut** — decided against |

### Public and account

| Block | Feature | Status |
|-------|---------|--------|
| Homepage | Landing copy + Play / Login / Enter game | ✅ |
| Homepage | Latest 3 news teasers | ✅ |
| Homepage | Guest language switcher (cs / en) | ✅ |
| News | Paginated news archive | ✅ |
| Auth | Login modal | ✅ |
| Auth | Register + email verification | ✅ |
| Auth | Password reset | ✅ |
| Kingdom | Pick kingdom at registration | ✅ |
| Account settings | Language | ✅ |
| Account settings | Close modals on backdrop click | ✅ |
| Account settings | Email change | ✅ |
| Account settings | Account deletion | ✅ |
| Account settings | Session password change | 📋 |
| Account settings | Extra settings (notification prefs, privacy, connected accounts) | 📋 |
| Wiki | Public wiki (`GET /wiki`) | 📋 |

### Team

| Block | Feature | Status |
|-------|---------|--------|
| Dashboard | Banner (name, emblem, colors, manager, morale, fans, chemistry, reputation) | ✅ |
| Dashboard | Finance snapshot + crisis banner | ✅ |
| Dashboard | Last 5 chronicle entries | ✅ |
| Dashboard | League place, record, and this week's fixtures | ✅ |
| Dashboard | Quick stats and quick actions | ✅ |
| Dashboard | 30-day history chart (morale, reputation, chemistry, fans) | ✅ |
| Dashboard | Edit team profile (name, emblem, colors) | ✅ |
| Dashboard | Live SSE updates | 📋 |
| Team chronicle | Full read-only log, filterable by manager period | ✅ |
| Chemistry | Flavor for matches, fan club / attendance, league tie-break | ✅ |
| Chemistry | Combat reads chemistry | ✂️ |

### Heroes

| Block | Feature | Status |
|-------|---------|--------|
| Roster | Hero cards (race, level, trait, age phase, form/fatigue, stats, OVR, status) | ✅ |
| Roster | Filter / sort (name, race, status, level, OVR, age, form, STR–INT) | ✅ |
| Roster | Summon CTA | ✅ |
| Roster | Per-card quick actions / multi-select / batch | ✂️ |
| Hero detail | Header (rename, ratings, gold value, salary, stored level + XP bar, secondaries, trait) | ✅ |
| Hero detail | Overview (attributes + gear, derived combat stats, trainer assign) | ✅ |
| Hero detail | Manage: sell, dismiss, promote to trainer | ✅ |
| History tab | Match / training tiles, weapon mastery, chronicle entries | ✅ |
| Rating | Base OVR (0–100) and complex rating (0–9999) | ✅ |

Equipment and spells on hero detail are under **Items and spells**.

### Training

| Block | Feature | Status |
|-------|---------|--------|
| Trainers | Promote combatant → trainer (stats freeze) | ✅ |
| Trainers | Dismiss trainer | ✅ |
| Trainers | Focus: Idle / Attribute / Magic / Form | ✅ |
| Trainers | Assign / unassign trainees (slot cap) | ✅ |
| Trainers | Thursday 08:00–10:00 lock | ✅ |
| Active trainees | Table + fatigue / form forecast | ✅ |
| Weekly tick | Thursday 10:00 evaluation (no gold cost, no XP write) | ✅ |

### Formations and combat

| Block | Feature | Status |
|-------|---------|--------|
| Saved lineups | Up to 4 formations; create / delete / set default | ✅ |
| Saved lineups | Pitch 3+3 drag-and-drop + quick-assign | ✅ |
| Tactics | Pre-match approach (Aggressive / Balanced / Defensive) | ✅ |
| Tactics | Formation-wide movement goal | ✅ |
| Tactics | Per-slot L1 target order + thin L2 spell priorities | ✅ |
| Tactics | L3 sequences / synergy tables / simulate sandbox | ✂️ |
| Match prep | Assign lineup to a league fixture | ✅ |
| Match prep | Match-ready panel + fill empty slots from roster | ✅ |
| Engine | Fully automated match (kill score 0–6, round 200 cap) | ✅ |
| Engine | Combat home-advantage | 📋 |
| Engine | In-combat race synergy | ✂️ |
| Replay | Hex playback (play / pause, step, speed, round scrubber) | ✅ |
| Replay | Round combat log | ✅ |
| Replay | Forfeit state (no canvas) | ✅ |
| Friendly matches | Challenge + time slots | ✅ |
| Friendly matches | Accept / decline / cancel | ✅ |
| Friendly matches | Upcoming / pending / history + battle report | ✅ |

### Headquarters

Seven facilities. Forge is not live (crafting is ⏳).

| Block | Feature | Status |
|-------|---------|--------|
| Facilities | Training / Medical / Library / Treasury / Barracks cards + passives | ✅ |
| Facilities | Summoning Chamber + Arena cards (open panels) | ✅ |
| Facilities | Upgrade / downgrade / cancel (first hour) | ✅ |
| Facilities | Header: maintenance, payroll, debt, crisis, race optimization | ✅ |
| Facilities | Visual themes / visitor access / morale-over-time | 📋 |
| Facilities | Forge / workshop | ⏳ |
| Arena adaptation | Set home-arena race (summoning pool, not a visual skin) | ✅ |
| Arena panel | Ticket price 1–50 + elasticity | ✅ |
| Arena panel | Attendance / gold projections | ✅ |
| Arena panel | Last-18 attendance analytics (chart + table) | ✅ |
| Arena panel | Home-match ticket gold payout | ✅ |
| Summoning | Cycle limit, gold cost, summon button | ✅ |
| Summoning | Compatible-race pool + reveal card | ✅ |
| Summoning | History (filter, sort, pagination) | ✅ |

### Items and spells

| Block | Feature | Status |
|-------|---------|--------|
| Equipment | Nine-slot paperdoll (two rings), click-to-equip / unequip | ✅ |
| Equipment | Rucksack: repair / dismantle | ✅ |
| Equipment | Enchanting / essence | ✂️ |
| Equipment | Crafting | ⏳ |
| Spellbook | Equip / unequip slots (magic capacity) | ✅ |
| Spellbook | School mastery (Fire / Water / Air / Earth / Light / Dark) | ✅ |
| Spellbook | Learned list + inline store | ✅ |
| Academy | Buy T1–T5 for gold (school / tier filters) | ✅ |

### League and calendar

| Block | Feature | Status |
|-------|---------|--------|
| Standings | Group table (W/D/L, GD, points, last-5, promo/releg) | ✅ |
| Standings | Group fixtures + score link to replay | ✅ |
| My fixtures | Own season matches + prepare formation | ✅ |
| Kingdom leaderboard | All teams in the selected season | ✅ |
| Season archive | Finished seasons: table, fixtures, place, tier move, gold | ✅ |
| League | Win-streak / derby / relegation-pressure morale | ✂️ |
| Calendar | Week navigation, team-only / system-tick toggles | ✅ |
| Calendar | League fixtures + own friendlies in the feed | ✅ |
| Calendar | World-event rows | ⏳ |

### Marketplace and economy

| Block | Feature | Status |
|-------|---------|--------|
| Browse | Search / filter heroes, trainers, items | ✅ |
| Browse | Buy now / bid / buyout | ✅ |
| Sell | Create listing (buy-now or auction, price band −50% / +100%) | ✅ |
| Sell | My listings + cancel (blocked if auction has bids) | ✅ |
| Basic merchant | Fixed-price starter equipment | ✅ |
| History | Settled purchase / sale log | ✅ |
| Finance | Gold, debt, weekly payroll + HQ maintenance, period / all-time net | ✅ |
| Finance | Full ledger with type / actor filters | ✅ |
| Finance | Navbar recent-ledger modal | ✅ |
| Crisis | Warning → restricted → bankruptcy | ✅ |
| Economy | Gold never goes negative | ✅ |
| Economy | Diminishing returns | 📋 |

### Community

| Block | Feature | Status |
|-------|---------|--------|
| Forum | Categories, search, new thread, paginated replies | ✅ |
| Forum | Author lock / unlock | ✅ |
| Forum | Player-set pin | ✂️ |
| Mail | Inbox / sent, compose, unread badge (navbar) | ✅ |
| Mail | Trade / league emails | 📋 |
| Profiles | Public player / team profile modal and team card | ✅ |
| Notifications | Navbar modal + unread badge | ✅ |
| Graveyard | Memorial list, filters, detail (dismiss + combat death) | ✅ |

### Future (Milestone 8 / 9)

| Block | Feature | Status |
|-------|---------|--------|
| PvE | Dungeons | ⏳ |
| PvE | Quests | ⏳ |
| Economy | Crafting | ⏳ |
| Calendar | World events | ⏳ |
| Social | Alliances / guilds | ⏳ |

## Development

Local stack, seed, ticks, and workers live in docs, not here:

- [docs/guides/local-setup.md](docs/guides/local-setup.md)
- [docker/README.md](docker/README.md)
- [docs/guides/](docs/guides/README.md) — how to edit backend and UI
- [docs/README.md](docs/README.md) — documentation index