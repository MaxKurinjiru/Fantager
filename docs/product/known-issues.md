# Known Issues & Open Questions

Single source of truth for **design questions** that must not be invented in code.

**Locks** live in the matching `systems/*.md` or `screens/*.md` `## Locked:` section — not in this file. Now / next: [roadmap/current.md](../roadmap/current.md).

**AI agents:** Do not invent mechanics for **Open** rows. Do not implement **Later product decisions** unless the user explicitly asks.

## Open

| ID | Area | Issue | Severity | Status |
|----|------|-------|----------|--------|
| — | — | None. Next new question is **#17**. | — | — |

When an Open row is decided, record Cut vs Implement. Mark **Decided — Implement** until that code lands, then **Resolved** and move the lock into the matching system or screen doc. Cut decisions may go straight to Resolved.

## Later product decisions (not Open)

Parked until an explicit product ask. Do not treat these as a backlog.

| Topic | Status | Spec |
|-------|--------|------|
| Public wiki (`GET /wiki`) | Planned (A8.5) | [00-public-pages.md](../screens/00-public-pages.md#locked-public-wiki-stays-planned) |
| Session password change / extra settings | Planned (A8.6) | [19-player-profile-settings.md](../screens/19-player-profile-settings.md#locked-password-change--extra-settings-stay-planned) |
| Dashboard SSE | Planned | [02-team-dashboard.md](../screens/02-team-dashboard.md) |
| Combat home-advantage | Planned | [combat-system.md](../systems/combat-system.md), [18-arena-management.md](../screens/18-arena-management.md) |
| Combat simulate sandbox | Route stays Planned | [combat-system.md](../systems/combat-system.md#locked-no-race-synergy--no-simulate) |
| Enchanting / essence | Cut from live game | [item-system.md](../systems/item-system.md#locked-no-enchanting) |
| In-combat morale / race synergy | Cut | [combat-system.md](../systems/combat-system.md#locked-no-race-synergy--no-simulate) |
| Forum pin write path | Cut | [community-system.md](../systems/community-system.md) |
| League morale fiction (streak / derby / relegation pressure) | Cut | [league-system.md](../systems/league-system.md) |

Deferred features beyond this list (Milestone 8 dungeons/quests/crafting, Milestone 9 alliances/guilds, world events) stay under [`future/`](../future/) and are **not** issues.

## Resolved locks (index)

| ID | Area | Status | Spec |
|----|------|--------|------|
| 1 | Combat core | Resolved | [combat-system.md](../systems/combat-system.md) |
| 2 | Enchanting | Resolved (Cut) | [item-system.md](../systems/item-system.md#locked-no-enchanting) |
| 3 | Friendly matches | Resolved | [friendly-match-system.md](../systems/friendly-match-system.md) |
| 4 | Arena analytics | Resolved | [18-arena-management.md](../screens/18-arena-management.md#locked-attendance-analytics) |
| 5 | Graveyard | Resolved | [graveyard-system.md](../systems/graveyard-system.md) |
| 6 | Hero level | Resolved | [hero-system.md](../systems/hero-system.md#locked-hero-level-invariant) |
| 7 | Team chemistry | Resolved (flavor) | [team-system.md](../systems/team-system.md#locked-chemistry-flavor) |
| 8 | Item occupancy | Resolved | [item-system.md](../systems/item-system.md#locked-item-occupancy) |
| 9 | Combat leftovers | Resolved (HUD + Haste/Shock locked; morale / synergy / simulate Cut) | [combat-system.md](../systems/combat-system.md#locked-no-race-synergy--no-simulate), [12-combat-battle.md](../screens/12-combat-battle.md#locked-replay-hud) |
| 10 | Formation save | Resolved | [formation-system.md](../systems/formation-system.md) |
| 11 | Forum pin | Resolved (Cut) | [community-system.md](../systems/community-system.md) |
| 12 | Formation L1/L2 | Resolved | [formation-system.md](../systems/formation-system.md#locked-l1l2-editor) |
| 13 | Roster extras | Resolved (Cut) | [03-hero-roster.md](../screens/03-hero-roster.md#locked-roster-extras) |
| 14 | Genie summon pool | Resolved | [summoning-system.md](../systems/summoning-system.md#locked-genie-summon-pool) |
| 15 | Marketplace price band | Resolved | [marketplace-system.md](../systems/marketplace-system.md#locked-listing-price-band) |
| 16 | Spell catalog T1–T5 | Resolved | [spell-system.md](../systems/spell-system.md#locked-catalog-t1t5) |

Decision notes for #1–#16 live in the linked specs and in [roadmap/alignment-waves.md](../roadmap/alignment-waves.md) (waves 6–8).
