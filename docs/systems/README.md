# Systems (live domain rules)

Canonical **gameplay contracts**. Conflict order: [docs/README.md](../README.md) § Canonical sources.

The status snapshot below is one word plus a link. Leftover **code** lives in that system’s **Remaining work**. Later product decisions live in [product/known-issues.md](../product/known-issues.md) § Later product decisions — not in this table.

## Remaining work convention

Each system doc should open with Status / Screens / Open design.

- **Remaining work** — only genuine unfinished work (tests, wiring). Omit the heading when there is none. Do not list shipped `[x]` items.
- **Parked** — later product decisions **unique to this file** and not already in the known-issues Later index. Bullets **without checkboxes**. Omit the heading when there is nothing unique. Do not list **Cut** here — Cut belongs in `## Locked:`. Index: [product/known-issues.md](../product/known-issues.md) § Later product decisions.

```markdown
Status: Live | Partial | Planned
Screens: … · Code: ../architecture/screen-code-map.md
Open design: none
Lock: [Locked: …](#…)   ← only when a ## Locked: section exists

## Remaining work
- genuine unfinished item

## Parked
Index: [known-issues.md](../product/known-issues.md) § Later product decisions.

- Unique local topic — Planned
```

**Open design** is only for **Open** rows in [product/known-issues.md](../product/known-issues.md). Resolved decisions belong in `## Locked:` — not in Parked and not in the header chronicle (wave IDs, “#n decided”). Index: [product/known-issues.md](../product/known-issues.md) § Resolved locks.

Locks use **`## Locked:`** (exactly that heading level) so [AGENTS.md](../../AGENTS.md) and grep find them. Do not duplicate Remaining work or the Later table in the roadmap. [../roadmap/README.md](../roadmap/README.md) only points here and to known-issues.

## System files

| Doc | Area |
|-----|------|
| [auth-system.md](auth-system.md) | Authentication, registration, sessions |
| [kingdom-system.md](kingdom-system.md) | Kingdoms / servers |
| [summoning-system.md](summoning-system.md) | Summoning chamber |
| [calendar-system.md](calendar-system.md) | Weekly ticks, schedule, season timeline |
| [friendly-match-system.md](friendly-match-system.md) | PvP practice matches |
| [economy-system.md](economy-system.md) | Gold, ledger, treasury |
| [financial-crisis-system.md](financial-crisis-system.md) | Financial crisis |
| [hero-system.md](hero-system.md) | Heroes |
| [hero-rating-system.md](hero-rating-system.md) | Base OVR, complex rating |
| [hero-chronicle-system.md](hero-chronicle-system.md) | Per-hero event log |
| [training-system.md](training-system.md) | Training |
| [team-system.md](team-system.md) | Teams |
| [team-chronicle-system.md](team-chronicle-system.md) | Team event log |
| [formation-system.md](formation-system.md) | Formations |
| [headquarters-system.md](headquarters-system.md) | HQ facilities |
| [item-system.md](item-system.md) | Items and equipment |
| [spell-system.md](spell-system.md) | Spells |
| [combat-system.md](combat-system.md) | Combat engine, replay, simulation contract |
| [combat-formulas.md](combat-formulas.md) | Derived stats, durability, mastery, traits |
| [marketplace-system.md](marketplace-system.md) | Marketplace |
| [league-system.md](league-system.md) | Leagues |
| [graveyard-system.md](graveyard-system.md) | Graveyard |
| [community-system.md](community-system.md) | Mail, forum |
| [notification-system.md](notification-system.md) | In-app notifications |
| [player-inactivity-system.md](player-inactivity-system.md) | Inactive player release |
| [npc-simulation-system.md](npc-simulation-system.md) | NPC tactics, training, economy |

## Status snapshot

One-line index of the **live contract**. Leftovers live in that doc’s Remaining work. Later product decisions: [product/known-issues.md](../product/known-issues.md) § Later product decisions — do not restate them here.

| Area | Status | Spec |
|------|--------|------|
| Authentication | Live | [auth-system.md](auth-system.md) |
| Kingdom | Live | [kingdom-system.md](kingdom-system.md) |
| Team / dashboard | Live | [team-system.md](team-system.md) |
| Hero roster | Live | [hero-system.md](hero-system.md) |
| Hero rating | Live | [hero-rating-system.md](hero-rating-system.md) |
| Hero chronicle | Live | [hero-chronicle-system.md](hero-chronicle-system.md) |
| Summoning | Live | [summoning-system.md](summoning-system.md) |
| Training | Live | [training-system.md](training-system.md) |
| Calendar & ticks | Live | [calendar-system.md](calendar-system.md) |
| Formations | Live | [formation-system.md](formation-system.md) |
| Headquarters | Live | [headquarters-system.md](headquarters-system.md) |
| Arena (HQ panel) | Live | [headquarters-system.md](headquarters-system.md), [18-arena-management.md](../screens/18-arena-management.md) |
| Friendly matches | Live | [friendly-match-system.md](friendly-match-system.md) |
| Items | Live | [item-system.md](item-system.md) |
| Spells | Live | [spell-system.md](spell-system.md) |
| Leagues | Live | [league-system.md](league-system.md) |
| Combat | Live (replay) | [combat-system.md](combat-system.md), [combat-formulas.md](combat-formulas.md) |
| Marketplace | Live | [marketplace-system.md](marketplace-system.md) |
| Community | Live | [community-system.md](community-system.md) |
| Graveyard | Live | [graveyard-system.md](graveyard-system.md) |
| Economy / finance | Live | [economy-system.md](economy-system.md), [financial-crisis-system.md](financial-crisis-system.md) |
| Team chronicle | Live | [team-chronicle-system.md](team-chronicle-system.md) |
| Notifications | Live | [notification-system.md](notification-system.md) |
| NPC simulation | Live | [npc-simulation-system.md](npc-simulation-system.md) |
| Player inactivity | Live | [player-inactivity-system.md](player-inactivity-system.md) |

Deferred (not live): [future/](../future/) — dungeons, quests, crafting, world events. Milestone 9 alliances: [archive/history.md](../roadmap/archive/history.md).
