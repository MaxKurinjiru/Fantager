# Fantager — Agent Instructions

Entry point for Cursor agents, Copilot, and other AI assistants working in this repository.

## Task routing

| Task | Read first | Code / docs to touch |
|------|------------|----------------------|
| **Docs vs code alignment** | [docs/roadmap/current.md](docs/roadmap/current.md) → [docs/product/known-issues.md](docs/product/known-issues.md) | Waves 0–8 complete — do not invent a next alignment step |
| **UI / Twig / SCSS / Stimulus** | [docs/guides/ui.md](docs/guides/ui.md) | `templates/`, `assets/styles/`, `assets/controllers/` |
| **New or changed API endpoint** | [docs/guides/backend.md](docs/guides/backend.md) → [docs/architecture/api.md](docs/architecture/api.md) | `src/Controller/Api/V1/`, `src/Service/`, [docs/architecture/routes.md](docs/architecture/routes.md) |
| **Web page / screen** | [docs/architecture/screen-code-map.md](docs/architecture/screen-code-map.md) + screen in [docs/screens/](docs/screens/) + [docs/guides/ui.md](docs/guides/ui.md) | `src/Controller/Web/`, `templates/`, often API + Stimulus |
| **Domain logic / service** | [docs/systems/](docs/systems/) + [docs/architecture/entities.md](docs/architecture/entities.md) | `src/Service/`, `src/Entity/`, `tests/Service/` |
| **Schema / migration** | [docs/architecture/entities.md](docs/architecture/entities.md) | `migrations/`, entities, repositories |
| **Bugfix / extend existing feature** | [docs/roadmap/current.md](docs/roadmap/current.md) + [docs/systems/README.md](docs/systems/README.md) § Current Implementation Status + [docs/product/known-issues.md](docs/product/known-issues.md) | Mirror patterns in the same domain |
| **Stack, Docker, doc index** | [.github/copilot-instructions.md](.github/copilot-instructions.md) | — |

Full vertical slice: backend cheatsheet § Vertical slice + UI cheatsheet when the feature has a UI.

**Live rules vs overview:** `docs/systems/*.md` + [docs/product/known-issues.md](docs/product/known-issues.md) win over [docs/product/overview.md](docs/product/overview.md) on conflict. Live UI is [docs/architecture/screen-code-map.md](docs/architecture/screen-code-map.md) + Twig, not unchecked wish-lists in [docs/screens/overview-legacy.md](docs/screens/overview-legacy.md).

**Hero level (#6 Resolved):** `Hero.level` is the stored live value. Do not derive it from `Hero.xp` on read. Recalc only after XP award (`HeroProgressionService`) or via `app:hero-levels:refresh`. Spec: [docs/systems/hero-system.md](docs/systems/hero-system.md) § Locked: hero level invariant.

**Team chemistry (#7 Resolved):** `Team.chemistry` is flavor for matches. Combat must not read it. Live consumers: dashboard, fan club / attendance, league tie-break. Spec: [docs/systems/team-system.md](docs/systems/team-system.md) § Locked: chemistry flavor.

**Item occupancy (#8 Resolved):** Nine slots (`ring` + `ring_2`). Two-handed weapons occupy both hands. Dual-wield for one-handed melee. Giant/Ent stay `RaceConfig`. Spec: [docs/systems/item-system.md](docs/systems/item-system.md) § Locked: item occupancy.

**Item enchanting (#2 Resolved):** Enchanting is not in the live game. Do not reintroduce essence or ship an enchant UI, cost, or combat effect. `Item.special_effects` stays unused (column kept; item API omits the key). Spec: [docs/systems/item-system.md](docs/systems/item-system.md) § Locked: no enchanting.

**Replay HUD (#9 HUD locked):** Emblems, League/Friendly label, contextual back link. Do not add morale HUD, initiative queue, team-stats sidebar, skip-to-end, hex debug, or a formation grid. Spec: [docs/screens/12-combat-battle.md](docs/screens/12-combat-battle.md) § Locked: replay HUD.

**Haste/Shock (#9 locked):** YAML `speed_bonus_percent` / `speed_reduction_percent` apply to the per-round initiative sort after ticks, not to AP. Spec: [docs/systems/combat-system.md](docs/systems/combat-system.md) § Speed / Initiative.

**In-combat morale (#9 Cut):** Do not emit `morale_change` or read `moraleDecayMultiplier`. Volatile / Battle Hardened copy is flavor.

**Race synergy (#9 Cut):** Do not add in-combat race/role synergy tables. Combat must not read `ignoresRaceSynergy`. Loner copy is flavor. Spec: [docs/systems/combat-system.md](docs/systems/combat-system.md) § Locked: no race synergy / no simulate.

**Combat simulate (#9 Cut):** Do not ship `POST /api/v1/combat/simulate`. Route stays Planned. Friendly matches are the live adjacent path.

**Formation L1/L2 (#12 Resolved):** Per-slot `target_order` + `fallback` and thin equipped `spell_priorities`. Do not add L3 sequences or `enemy_status` / `lowest_hp_enemy` to the UI. Spec: [docs/systems/formation-system.md](docs/systems/formation-system.md) § Locked: L1/L2 editor.

**Roster extras (#13 Resolved):** Live roster card stays name / race / level / trait / age / form / fatigue / attributes / inspect + client filter/sort. Do not add morale on the card, equipped icons, tired/training status, quick-action dropdown, batch training, multi-select, or a level filter. Spec: [docs/screens/03-hero-roster.md](docs/screens/03-hero-roster.md) § Locked: roster extras.

**Arena analytics (#4 Resolved):** HQ panel graphs/history from the last 18 `arena_revenue` payouts (table + attendance/gold chart + window summary). Do not add home-advantage, match results, seating-upgrade charts, or a standalone arena page. Spec: [docs/screens/18-arena-management.md](docs/screens/18-arena-management.md) § Locked: attendance analytics.

**Genie summon pool (#14 Resolved):** Compatible races use relationship ≥ 50, except Genie adaptation ≥ 80 (Genie / Human / Elf). Same pool for summons, NPC hero buys, and kingdom starting roster. Do not restore a Genie-only NPC filter. Spec: [docs/systems/summoning-system.md](docs/systems/summoning-system.md) § Locked: Genie summon pool.

**Marketplace price band (#15 Resolved):** Listing prices must sit in `[round(suggested × 0.5), round(suggested × 2)]` (min 1). Suggested is rating-based for heroes/trainers and merchant/rarity for items. Auction bids are not capped. Spec: [docs/systems/marketplace-system.md](docs/systems/marketplace-system.md) § Locked: listing price band.

**Spell catalog (#16 Resolved):** Seeded library is two spells per school per tier for T1–T5. Do not add T6+, resurrection, or summon-creature. Combat stays school + type + `spell.tier`. Spec: [docs/systems/spell-system.md](docs/systems/spell-system.md) § Locked: catalog T1–T5.

**Public wiki (A8.5 Planned):** `GET /wiki` stays Planned. Do not ship a wiki CMS, help articles, search, or a navbar Help link. Live public content is homepage + `/news`. Spec: [docs/screens/00-public-pages.md](docs/screens/00-public-pages.md) § Locked: public wiki stays Planned.

**Password change / extra settings (A8.6 Planned):** Do not ship session password change, notification prefs, privacy flags, an account-info panel, or `GET/PUT /api/v1/settings`. Live settings are language, backdrop-close, email change, and account deletion. Forgotten passwords use `/password-reset`. Spec: [docs/screens/19-player-profile-settings.md](docs/screens/19-player-profile-settings.md) § Locked: password change / extra settings stay Planned.

## Backend (PHP / Symfony)

| Resource | When to use |
|----------|-------------|
| [docs/architecture/screen-code-map.md](docs/architecture/screen-code-map.md) | **Read first** for screen → controller/template/API map |
| [docs/guides/backend.md](docs/guides/backend.md) | PHP/Symfony layering, checklist, scope guardrails |
| [docs/architecture/api.md](docs/architecture/api.md) | REST shape, status codes, response format |
| [docs/architecture/entities.md](docs/architecture/entities.md) | Entities, enums, naming conventions |
| [docs/architecture/routes.md](docs/architecture/routes.md) | All Web and API routes |
| [.cursor/rules/php-backend.mdc](.cursor/rules/php-backend.mdc) | Auto-applied when editing `src/**/*.php`, `tests/**/*.php` |
| [.cursor/skills/fantager-backend/SKILL.md](.cursor/skills/fantager-backend/SKILL.md) | Full backend implementation workflow |

## Frontend UI (mandatory for templates & styles)

| Resource | When to use |
|----------|-------------|
| [docs/guides/ui-guidelines.md](docs/guides/ui-guidelines.md) | Authoritative UI/CSS spec |
| [docs/guides/ui.md](docs/guides/ui.md) | **Read first** for Twig/SCSS work |
| [.cursor/rules/twig-ui.mdc](.cursor/rules/twig-ui.mdc) | Auto-applied when editing `templates/**/*.twig` |
| [.cursor/rules/scss-ui.mdc](.cursor/rules/scss-ui.mdc) | Auto-applied when editing `assets/styles/**/*.scss` |
| [.cursor/rules/stimulus-ui.mdc](.cursor/rules/stimulus-ui.mdc) | Auto-applied when editing Stimulus JS |
| [.cursor/skills/fantager-ui/SKILL.md](.cursor/skills/fantager-ui/SKILL.md) | Full UI implementation workflow |

## No automatic commands

Do **not** run tests, linters, static analyzers, build scripts, Docker console commands for verification, or `bash scripts/check-ui-compliance.sh` unless the user explicitly asks. Follow the docs and review your edits — do not execute verification commands to “finish” a task.

### Optional verification (user-requested only)

```bash
bash scripts/check-ui-compliance.sh
npm run build
bash scripts/check-backend-docs.sh
docker exec -u apache fantager-web composer test
docker exec -u apache fantager-web composer phpstan
```

## Core UI rules (summary)

1. **Twig:** semantic classes only — no Tailwind layout/color/spacing utilities (`hidden`, `sr-only`, `group` are exceptions).
2. **SCSS:** design tokens from `_tokens.scss`; new styles in `assets/styles/components/_*.scss`.
3. **i18n:** `{{ 'key'|trans }}` everywhere; no hardcoded user-facing strings.
4. **a11y:** labels, `aria-label` on icon buttons, modal ARIA, `role="alert"` for dynamic messages.
5. **Stimulus:** translations via `data-*-value`; dynamic HTML from `<template>` in Twig.

## English

Documentation and code comments are in English (see copilot-instructions).

## Strict constraints

1. **No LaTeX syntax**: Never use LaTeX formatting, syntax, or mathematical notations (such as `$$...$$` or `$...$`) under any circumstances. All mathematical expressions and formulas must be written in plain, readable text (e.g., `a + b = c`).
