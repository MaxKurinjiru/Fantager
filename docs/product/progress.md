# Game progress

Reader snapshot of **what is in the live game**. Not a contract and not a work queue.

Conflict order: [../README.md](../README.md) § Canonical sources. Live rules: [`../systems/`](../systems/README.md). Leftover **code**: that system’s Remaining work. Planned product extras: [known-issues.md](known-issues.md) § Later product decisions. Milestone 8/9: [`../future/`](../future/).

Same tables (without spec links): [README.md](../../README.md#game-progress).

## How this is split

| Level | What it is | Example |
|-------|------------|---------|
| **Domain** | Player-facing game area | Heroes, Combat, Headquarters |
| **Block** | One screen, panel, or subsystem | Hero detail, Arena panel, Forum |
| **Feature** | One player-visible capability inside that block | Filter bar, click-to-equip, bid / buyout |

Not listed: backend loops (NPC simulation, inactivity, tick runner), parked polish unique to a spec (SEO, clone-formation, CSP headers, …), leftover **code** (tests, unread columns), and internal APIs. Those stay in `docs/systems/`, `## Parked` / Remaining work, and [guides/local-setup.md](../guides/local-setup.md).

## Icons

| Icon | Word | Meaning |
|------|------|---------|
| ✅ | **Live** | In the game. Follow the linked spec. |
| 🟡 | **Incomplete** | Live feature with leftover **player-visible** work. None right now. |
| 📋 | **Planned** | Parked until an explicit product ask. |
| ⏳ | **Future** | Milestone 8/9 / `docs/future/`. |
| ✂️ | **Cut** | Decided against. Do not reintroduce. |

## Public and account

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Homepage | Landing copy + Play / Login / Enter game | ✅ | [00-public-pages.md](../screens/00-public-pages.md) |
| Homepage | Latest 3 news teasers | ✅ | [00-public-pages.md](../screens/00-public-pages.md) |
| Homepage | Guest language switcher (cs / en) | ✅ | [00-public-pages.md](../screens/00-public-pages.md) |
| News | Paginated news archive | ✅ | [00-public-pages.md](../screens/00-public-pages.md) |
| Auth | Login modal | ✅ | [00-auth-screens.md](../screens/00-auth-screens.md) |
| Auth | Register + email verification | ✅ | [00-auth-screens.md](../screens/00-auth-screens.md), [auth-system.md](../systems/auth-system.md) |
| Auth | Password reset | ✅ | [00-auth-screens.md](../screens/00-auth-screens.md) |
| Kingdom | Pick kingdom at registration | ✅ | [01-kingdom-selection.md](../screens/01-kingdom-selection.md) |
| Account settings | Language | ✅ | [19-player-profile-settings.md](../screens/19-player-profile-settings.md) |
| Account settings | Close modals on backdrop click | ✅ | [19-player-profile-settings.md](../screens/19-player-profile-settings.md) |
| Account settings | Email change | ✅ | [19-player-profile-settings.md](../screens/19-player-profile-settings.md) |
| Account settings | Account deletion | ✅ | [19-player-profile-settings.md](../screens/19-player-profile-settings.md) |
| Account settings | Session password change | 📋 | [19-player-profile-settings.md](../screens/19-player-profile-settings.md#locked-password-change--extra-settings-stay-planned) |
| Account settings | Extra settings (notification prefs, privacy, connected accounts) | 📋 | [19-player-profile-settings.md](../screens/19-player-profile-settings.md#locked-password-change--extra-settings-stay-planned) |
| Wiki | Public wiki (`GET /wiki`) | 📋 | [00-public-pages.md](../screens/00-public-pages.md#locked-public-wiki-stays-planned) |

## Team

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Dashboard | Banner (name, emblem, colors, manager, morale, fans, chemistry, reputation) | ✅ | [02-team-dashboard.md](../screens/02-team-dashboard.md) |
| Dashboard | Finance snapshot + crisis banner | ✅ | [02-team-dashboard.md](../screens/02-team-dashboard.md), [20-finance.md](../screens/20-finance.md) |
| Dashboard | Last 5 chronicle entries | ✅ | [02-team-dashboard.md](../screens/02-team-dashboard.md) |
| Dashboard | League place, record, and this week's fixtures | ✅ | [02-team-dashboard.md](../screens/02-team-dashboard.md) |
| Dashboard | Quick stats and quick actions | ✅ | [02-team-dashboard.md](../screens/02-team-dashboard.md) |
| Dashboard | 30-day history chart (morale, reputation, chemistry, fans) | ✅ | [02-team-dashboard.md](../screens/02-team-dashboard.md) |
| Dashboard | Edit team profile (name, emblem, colors) | ✅ | [02-team-dashboard.md](../screens/02-team-dashboard.md) |
| Dashboard | Live SSE updates | 📋 | [known-issues.md](known-issues.md) § Later product decisions |
| Team chronicle | Full read-only log, filterable by manager period | ✅ | [02a-team-chronicle.md](../screens/02a-team-chronicle.md) |
| Chemistry | Flavor for matches, fan club / attendance, league tie-break | ✅ | [team-system.md](../systems/team-system.md#locked-chemistry-flavor) |
| Chemistry | Combat reads chemistry | ✂️ | [team-system.md](../systems/team-system.md#locked-chemistry-flavor) |

## Heroes

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Roster | Hero cards (race, level, trait, age phase, form/fatigue, stats, OVR, status) | ✅ | [03-hero-roster.md](../screens/03-hero-roster.md) |
| Roster | Filter / sort (name, race, status, level, OVR, age, form, STR–INT) | ✅ | [03-hero-roster.md](../screens/03-hero-roster.md) |
| Roster | Summon CTA | ✅ | [03-hero-roster.md](../screens/03-hero-roster.md) |
| Roster | Per-card quick actions / multi-select / batch | ✂️ | [03-hero-roster.md](../screens/03-hero-roster.md#locked-roster-extras) |
| Hero detail | Header (rename, ratings, gold value, salary, stored level + XP bar, secondaries, trait) | ✅ | [04-hero-detail.md](../screens/04-hero-detail.md) |
| Hero detail | Overview (attributes + gear, derived combat stats, trainer assign) | ✅ | [04-hero-detail.md](../screens/04-hero-detail.md) |
| Hero detail | Manage: sell, dismiss, promote to trainer | ✅ | [04-hero-detail.md](../screens/04-hero-detail.md) |
| History tab | Match / training tiles, weapon mastery, chronicle entries | ✅ | [04-hero-detail.md](../screens/04-hero-detail.md), [hero-chronicle-system.md](../systems/hero-chronicle-system.md) |
| Rating | Base OVR (0–100) and complex rating (0–9999) | ✅ | [hero-rating-system.md](../systems/hero-rating-system.md) |

Equipment and spells on hero detail are listed under **Items and spells**.

## Training

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Trainers | Promote combatant → trainer (stats freeze) | ✅ | [06-trainer-management.md](../screens/06-trainer-management.md) |
| Trainers | Dismiss trainer | ✅ | [06-trainer-management.md](../screens/06-trainer-management.md) |
| Trainers | Focus: Idle / Attribute / Magic / Form | ✅ | [05-training.md](../screens/05-training.md) |
| Trainers | Assign / unassign trainees (slot cap) | ✅ | [05-training.md](../screens/05-training.md) |
| Trainers | Thursday 08:00–10:00 lock | ✅ | [05-training.md](../screens/05-training.md) |
| Active trainees | Table + fatigue / form forecast | ✅ | [05-training.md](../screens/05-training.md) |
| Weekly tick | Thursday 10:00 evaluation (no gold cost, no XP write) | ✅ | [training-system.md](../systems/training-system.md) |

## Formations and combat

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Saved lineups | Up to 4 formations; create / delete / set default | ✅ | [07-formation-setup.md](../screens/07-formation-setup.md) |
| Saved lineups | Pitch 3+3 drag-and-drop + quick-assign | ✅ | [07-formation-setup.md](../screens/07-formation-setup.md) |
| Tactics | Pre-match approach (Aggressive / Balanced / Defensive) | ✅ | [07-formation-setup.md](../screens/07-formation-setup.md) |
| Tactics | Per-slot movement goal (default + apply-all) | ✅ | [07-formation-setup.md](../screens/07-formation-setup.md) |
| Tactics | Per-slot L1 target order + thin L2 spell priorities | ✅ | [formation-system.md](../systems/formation-system.md#locked-l1l2-editor) |
| Tactics | L3 sequences / synergy tables / simulate sandbox | ✂️ | [formation-system.md](../systems/formation-system.md), [combat-system.md](../systems/combat-system.md#locked-no-race-synergy--no-simulate) |
| Match prep | Assign lineup to a league fixture | ✅ | [07-formation-setup.md](../screens/07-formation-setup.md) |
| Match prep | Match-ready panel + fill empty slots from roster | ✅ | [07-formation-setup.md](../screens/07-formation-setup.md), [team-system.md](../systems/team-system.md) |
| Engine | Fully automated match (kill score 0–6, round 200 cap) | ✅ | [combat-system.md](../systems/combat-system.md) |
| Engine | Combat home-advantage | 📋 | [known-issues.md](known-issues.md) § Later product decisions |
| Engine | In-combat race synergy | ✂️ | [combat-system.md](../systems/combat-system.md#locked-no-race-synergy--no-simulate) |
| Replay | Hex playback (play / pause, step, speed, round scrubber) | ✅ | [12-combat-battle.md](../screens/12-combat-battle.md) |
| Replay | Round combat log | ✅ | [12-combat-battle.md](../screens/12-combat-battle.md) |
| Replay | Forfeit state (no canvas) | ✅ | [12-combat-battle.md](../screens/12-combat-battle.md) |
| Friendly matches | Challenge + time slots | ✅ | [18a-friendly-matches.md](../screens/18a-friendly-matches.md) |
| Friendly matches | Accept / decline / cancel | ✅ | [18a-friendly-matches.md](../screens/18a-friendly-matches.md) |
| Friendly matches | Upcoming / pending / history + battle report | ✅ | [18a-friendly-matches.md](../screens/18a-friendly-matches.md) |

## Headquarters

Seven facilities. Forge is not live (crafting is ⏳).

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Facilities | Training / Medical / Library / Treasury / Barracks cards + passives | ✅ | [08-headquarters.md](../screens/08-headquarters.md) |
| Facilities | Summoning Chamber + Arena cards (open panels) | ✅ | [08-headquarters.md](../screens/08-headquarters.md) |
| Facilities | Upgrade / downgrade / cancel (first hour) | ✅ | [08-headquarters.md](../screens/08-headquarters.md) |
| Facilities | Header: maintenance, payroll, debt, crisis, race optimization | ✅ | [08-headquarters.md](../screens/08-headquarters.md) |
| Facilities | Visual themes / visitor access / morale-over-time | 📋 | [known-issues.md](known-issues.md) § Later product decisions |
| Facilities | Forge / workshop | ⏳ | [future/crafting-system.md](../future/crafting-system.md) |
| Arena adaptation | Set home-arena race (summoning pool, not a visual skin) | ✅ | [headquarters-system.md](../systems/headquarters-system.md) |
| Arena panel | Ticket price 1–50 + elasticity | ✅ | [18-arena-management.md](../screens/18-arena-management.md) |
| Arena panel | Attendance / gold projections | ✅ | [18-arena-management.md](../screens/18-arena-management.md) |
| Arena panel | Last-18 attendance analytics (chart + table) | ✅ | [18-arena-management.md](../screens/18-arena-management.md#locked-attendance-analytics) |
| Arena panel | Home-match ticket gold payout | ✅ | [18-arena-management.md](../screens/18-arena-management.md) |
| Summoning | Cycle limit, gold cost, summon button | ✅ | [09-summoning-chamber.md](../screens/09-summoning-chamber.md) |
| Summoning | Compatible-race pool + reveal card | ✅ | [summoning-system.md](../systems/summoning-system.md#locked-genie-summon-pool) |
| Summoning | History (filter, sort, pagination) | ✅ | [09-summoning-chamber.md](../screens/09-summoning-chamber.md) |

## Items and spells

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Equipment | Nine-slot paperdoll (two rings), click-to-equip / unequip | ✅ | [10-item-equipment.md](../screens/10-item-equipment.md) |
| Equipment | Rucksack: repair / dismantle, slot/style filter | ✅ | [10-item-equipment.md](../screens/10-item-equipment.md) |
| Equipment | Gear vs unequipped + team wear on Equipment tab | ✅ | [10-item-equipment.md](../screens/10-item-equipment.md) |
| Equipment | Enchanting / essence | ✂️ | [item-system.md](../systems/item-system.md#locked-no-enchanting) |
| Equipment | Crafting | ⏳ | [future/crafting-system.md](../future/crafting-system.md) |
| Spellbook | Equip / unequip slots (magic capacity) | ✅ | [11-spell-management.md](../screens/11-spell-management.md) |
| Spellbook | School mastery (Fire / Water / Air / Earth / Light / Dark) | ✅ | [11-spell-management.md](../screens/11-spell-management.md) |
| Spellbook | Learned list + inline store | ✅ | [11-spell-management.md](../screens/11-spell-management.md) |
| Academy | Buy T1–T5 for gold (school / tier filters) | ✅ | [11-spell-management.md](../screens/11-spell-management.md#locked-catalog-t1t5) |
| Spellbook | Mana pool, payload cards, starting slot, L0 restore/harm/hinder, name keys | ✅ | [spell-system.md](../systems/spell-system.md) |

## League and calendar

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Standings | Group table (W/D/L, GD, points, last-5, promo/releg) | ✅ | [13-league.md](../screens/13-league.md) |
| Standings | Group fixtures + score link to replay | ✅ | [13-league.md](../screens/13-league.md) |
| My fixtures | Own season matches + prepare formation | ✅ | [13-league.md](../screens/13-league.md) |
| Kingdom leaderboard | All teams in the selected season | ✅ | [13-league.md](../screens/13-league.md) |
| Season archive | Finished seasons: table, fixtures, place, tier move, gold | ✅ | [13-league.md](../screens/13-league.md) |
| League | Win-streak / derby / relegation-pressure morale | ✂️ | [league-system.md](../systems/league-system.md#locked-no-league-morale-fiction) |
| Calendar | Week navigation, team-only / system-tick toggles | ✅ | [14-calendar.md](../screens/14-calendar.md) |
| Calendar | League fixtures + own friendlies in the feed | ✅ | [14-calendar.md](../screens/14-calendar.md) |
| Calendar | World-event rows | ⏳ | [future/world-events-system.md](../future/world-events-system.md) |

## Marketplace and economy

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Browse | Search / filter heroes, trainers, items | ✅ | [15-marketplace.md](../screens/15-marketplace.md) |
| Browse | Buy now / bid / buyout | ✅ | [15-marketplace.md](../screens/15-marketplace.md) |
| Sell | Create listing (buy-now or auction, price band −50% / +100%) | ✅ | [marketplace-system.md](../systems/marketplace-system.md#locked-listing-price-band) |
| Sell | My listings + cancel (blocked if auction has bids) | ✅ | [15-marketplace.md](../screens/15-marketplace.md) |
| Basic merchant | Fixed-price starter equipment | ✅ | [15-marketplace.md](../screens/15-marketplace.md) |
| History | Settled purchase / sale log | ✅ | [15-marketplace.md](../screens/15-marketplace.md) |
| Finance | Gold, debt, weekly payroll + HQ maintenance, period / all-time net | ✅ | [20-finance.md](../screens/20-finance.md) |
| Finance | Upcoming weekly cashflow (home tickets vs Sunday costs) | ✅ | [20-finance.md](../screens/20-finance.md), [economy-system.md](../systems/economy-system.md) |
| Finance | Full ledger with type / actor filters | ✅ | [20-finance.md](../screens/20-finance.md) |
| Finance | Navbar recent-ledger modal | ✅ | [20-finance.md](../screens/20-finance.md) |
| Crisis | Warning (recovery grace) → restricted → bankruptcy | ✅ | [financial-crisis-system.md](../systems/financial-crisis-system.md) |
| Economy | Gold never goes negative | ✅ | [economy-system.md](../systems/economy-system.md) |
| Economy | Diminishing returns | 📋 | [known-issues.md](known-issues.md) § Later product decisions |

## Community

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| Forum | Categories, search, new thread, paginated replies | ✅ | [17-community.md](../screens/17-community.md) |
| Forum | Author lock / unlock | ✅ | [17-community.md](../screens/17-community.md) |
| Forum | Player-set pin | ✂️ | [community-system.md](../systems/community-system.md#locked-forum-pin) |
| Mail | Inbox / sent, compose, unread badge (navbar) | ✅ | [17-community.md](../screens/17-community.md) |
| Mail | Trade / league emails | 📋 | [known-issues.md](known-issues.md) § Later product decisions |
| Profiles | Public player / team profile modal and team card | ✅ | [17-community.md](../screens/17-community.md) |
| Notifications | Navbar modal + unread badge | ✅ | [notification-system.md](../systems/notification-system.md) |
| Graveyard | Memorial list, filters, detail (dismiss + combat death) | ✅ | [16-graveyard.md](../screens/16-graveyard.md) |

## Future (Milestone 8 / 9)

Out of scope. Do not implement unless the user explicitly asks.

| Block | Feature | Status | Spec |
|-------|---------|--------|------|
| PvE | Dungeons | ⏳ | [future/dungeon-system.md](../future/dungeon-system.md) |
| PvE | Quests | ⏳ | [future/quest-system.md](../future/quest-system.md) |
| Economy | Crafting | ⏳ | [future/crafting-system.md](../future/crafting-system.md) |
| Calendar | World events | ⏳ | [future/world-events-system.md](../future/world-events-system.md) |
| Social | Alliances / guilds | ⏳ | [../roadmap/archive/history.md](../roadmap/archive/history.md) |
