# Glossary

Short definitions for live game terms. **Rules** live in `docs/systems/*.md`. This file is not a contract.

| Term | Meaning |
|------|---------|
| **Kingdom** | One shared game world. Isolation is `kingdom_id` on rows, not a separate process. |
| **Tick** | Scheduled server job in the kingdom timezone (daily reset, league match, weekly training, …). |
| **Season** | One league cycle: **77 days** (11 weeks). |
| **Gold** | Sole live currency. Every change is a `FinancialRecord`. Gold never goes negative. |
| **Essence** | Not in the live game. Do not reintroduce (enchanting Cut). |
| **Hero** | Roster combatant. No classes, no rarity. |
| **Trainer** | A `Hero` with `role = trainer`. Permanent promotion. |
| **Race** | One of eight playable: Human, Elf, Dwarf, Orc, Undead, Giant, Ent, Genie. |
| **Base OVR** | Cross-race rating **0–100**, cached on `Hero`. |
| **Complex rating** | Intrinsic value **0–9999** for market, salary, dismiss pay. Cached on `Hero`. |
| **Chemistry** | `Team.chemistry` 0–100. Flavor for matches: dashboard, fan club / attendance, league tie-break. Combat does not read it. |
| **Form / fatigue / morale** | Hero secondaries. Form and fatigue affect combat stats; in-combat morale change is Cut. |
| **Combat-ready** | Active, not a trainer, not permanently dead. A team needs **6** unique combat-ready heroes to field a match. |
| **Kill score** | Match result **0–6** (kills). Round 200 ends on current scores. |
| **Formation L0 / L1 / L2** | Pre-match AI: L0 default spell pick, L1 target order, L2 equipped-spell priorities. **L3** sequences are Cut. |
| **Arena** | HQ facility: home-match **ticket gold** + attendance analytics. Not `MatchType::Arena` (reserved, unused). |
| **Friendly match** | Player-vs-player practice (Wed/Sun 18:00). Same combat engine; no standings or tickets. |
| **Fan club** | Background attendance mechanic. Shown on the dashboard and HQ arena panel; no management UI. |
| **Royal Treasury** | Kingdom gold pool. Collects fees; weekly capped share to teams. Not shown to players. |
| **Financial crisis** | Unpaid upkeep becomes `unpaid_debt` (warning → restricted → bankruptcy). |
| **Graveyard** | Memorial wall for dismissals and permanent combat death. Reserved causes `age` / `retired` / `death` have no writers. |
| **Chronicle** | Append-only event log. **Hero** chronicle is per hero (History tab); **team** chronicle is per team. |
| **Library `xp_gain_pct`** | HQ Library bonus. Multiplies **match XP** only. Training does not write `Hero.xp`. |
| **Summoning pool** | Compatible races from relationships + arena adaptation. Genie adaptation ≥ 80 → Genie / Human / Elf. |
| **Listing price band** | Create-listing floor/ceiling around suggested price (−50% / +100%). Auction **bids** may exceed the ceiling. |
| **`Hero.level`** | Stored column is live truth. Do not derive level from `Hero.xp` on read. |
| **SSE** | Server-Sent Events for the dashboard. Stays **Planned**. |

## How to read status words

| Word | Meaning |
|------|---------|
| **Live** | In code and docs. Follow the matching `systems/*.md`. |
| **Planned** | Parked until an explicit product ask. Do not implement. |
| **Cut** | Decided against for the live game. Do not reintroduce. |
| **Deferred** | Specified in `docs/future/` (Milestone 8/9). Out of scope. |
| **Reserved** | Enum/column exists; **no writer**. Do not start writing it. |
| **Lock** | `## Locked:` in a system or screen doc. Do not reopen without an explicit product ask. Index: [known-issues.md](known-issues.md) § Resolved locks. |
