# Fantager UI — Reference

## SCSS file map

| File | Responsibility |
|------|----------------|
| `_tokens.scss` | CSS variables only |
| `_layout.scss` | Layout primitives (stack, cluster, screen grids) |
| `_shared.scss` | page-shell, game-panel, data-table, copy-*, stat-tile, filter-bar |
| `_buttons.scss` | `.btn`, variants, sizes |
| `_forms.scss` | `.form-input`, `.form-label`, `.form-check` |
| `_modal.scss` | `.modal-overlay`, `.modal-box`, `.modal-close` |
| `_hero.scss` | Hero detail, gear, attributes, spell rows, **trait badges** (`.hero-trait-badge`) |
| `_marketplace.scss` | Cards, filters, tables, sell-card |
| `_formation.scss` | Pitch, slots, tactics |
| `_item.scss` | Inventory, paperdoll, item-card |
| `_spell.scss` | Spellbook cards, slots, mastery |
| `_training.scss` | Trainer cards, schedule widgets |
| `_arena.scss` | Arena stats, revenue tables |
| `_summoning.scss` | Portal, race cards, history tables |
| `_finance.scss` | Ledger tables, summary cards |
| `_dashboard.scss` | Dashboard widgets |
| `_navbar.scss` | Nav, dropdowns |
| `_sidebar.scss` | Game sidebar, layout shell |

## Layout primitives (`_layout.scss`)

| Class | Purpose |
|-------|---------|
| `.layout-stack` | Vertical flex column (default gap 1.5rem) |
| `.layout-stack--sm/md/lg/form` | Smaller/larger gaps |
| `.layout-cluster` | Horizontal flex row, centered |
| `.layout-cluster--between/sm/lg/wrap/start/end/center` | Cluster modifiers |
| `.layout-toolbar` | Title row + actions, wraps |
| `.screen-3col` | 1→3 column grid |
| `.screen-3col__main` | Spans 2 cols on lg |
| `.screen-3col__aside` | Single column sidebar |
| `.scroll-grid` | Scrollable 2-col picker grid |
| `.scroll-grid__span-full` | Full width in scroll grid |
| `.form-field-group` | Label + input vertical stack |
| `.form-field-group--tight` | Tighter field stack |
| `.stat-grid` | 2→5 col stat row |
| `.grid-span-full` | `grid-column: 1 / -1` |
| `.emoji-icon` (+ `--sm/lg/xl`) | Emoji sizing |
| `.modal-form`, `__title`, `__body` | Modal form layout |
| `.entity-row__main` | Icon + title row |

## Shared shell (`_shared.scss`)

| Class | Purpose |
|-------|---------|
| `.page-shell` | Max-width page container |
| `.screen-grid`, `__main`, `__aside` | 2-column main/aside |
| `.dashboard-grid-2` | 2 equal columns |
| `.game-panel`, `__title` | Standard inset panel |
| `.game-panel--scroll` | Horizontal scroll panel |
| `.data-table-wrap` | Table overflow wrapper |
| `.data-table__col--center/end` | Table cell alignment |
| `.filter-bar`, `__controls`, `--spaced` | Filter toolbars |
| `.alert-box--spaced/compact` | Alert spacing |
| `.tab-content`, `.subtab-content` | Tab panel vertical stack |
| `.public-main` | Public layout main container |
| `.copy-muted/secondary/primary` | Text color semantics |
| `.copy-status-*` | Status text colors |
| `.stat-tile`, `.stat-tile-grid` | Stat display tiles |

## Domain-specific layout (examples)

| Class | File | Purpose |
|-------|------|---------|
| `.marketplace-filter-grid` | `_marketplace.scss` | Browse filter form grid |
| `.marketplace-browse-grid` | `_marketplace.scss` | Listing cards grid |
| `.paperdoll-grid` | `_item.scss` | Equipment slot grid |
| `.item-cards-grid` | `_item.scss` | Rucksack item grid |
| `.training-trainer-grid` | `_training.scss` | Trainer card grid |
| `.spell-mastery-grid` | `_spell.scss` | School mastery cells |
| `.summoning-race-grid` | `_summoning.scss` | Compatible races grid |
| `.hero-school-mastery__grid` | `_shared.scss` | Hero mastery panel |

## Twig exceptions (only these utilities)

- `hidden`
- `sr-only`
- `group`

## Email templates

`templates/email/**` is excluded from automated checks — separate inline-style scope.
