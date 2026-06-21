# UI Agent Cheatsheet (Fantager)

One-page reference for AI agents and quick human review.  
**Full spec:** [ui-guidelines.md](ui-guidelines.md)

---

## Decision tree

### Need layout?

| Situation | Use |
|-----------|-----|
| Page content wrapper | `.page-shell` |
| Vertical sections | `.layout-stack` (+ `--sm` / `--md` / `--lg` / `--form`) |
| Horizontal icon + text | `.layout-cluster` (+ `--between`, `--sm`, `--wrap`) |
| Toolbar (title + actions) | `.layout-toolbar` |
| 3 columns (1 + 2 span) | `.screen-3col`, `.screen-3col__aside`, `.screen-3col__main` |
| 2 columns (main + aside) | `.screen-grid`, `.screen-grid__main`, `.screen-grid__aside` |
| 2 equal columns | `.dashboard-grid-2` |
| Form label + input stack | `.form-field-group` (+ `--tight`) |
| Scrollable 2-col picker | `.scroll-grid`, `.scroll-grid__span-full` |
| Full-width grid cell | `.grid-span-full` |
| Stat row (arena) | `.stat-grid` |
| Stat tiles (2×2) | `.stat-tile-grid` |

### Need color on text?

| Situation | Use |
|-----------|-----|
| Muted body | `.copy-muted`, `.copy-secondary` |
| Primary emphasis | `.copy-primary` |
| Status color | `.copy-status-success`, `-error`, `-warning`, `-info` |
| Badge/chip | `.status-chip`, `.status-chip--purple`, `.rarity-tag--*` |

### Need a panel or card?

| Situation | Use |
|-----------|-----|
| Generic inset panel | `.game-panel`, `.game-panel__title` |
| Hero-specific panel | `.hero-panel`, `.hero-panel__title`, `.hero-panel__desc` |
| Feature/settings block | `.feature-panel`, `.feature-panel__title` |
| HQ facility body | `.hq-facility-panel` |

### Need a button?

Always **base + variant**: `.btn` + `.btn-primary` | `.btn-outline` | `.btn-ghost` | `.btn-danger`  
Sizes: `.btn--sm`, `.btn--lg`, `.btn--block`, `.btn--compact`, `.btn--icon-lead`

---

## Twig: forbidden vs allowed

### Do NOT put in Twig `class`

`flex`, `grid`, `gap-*`, `space-y-*`, `justify-*`, `items-*`, `m*-*`, `p*-*`, `text-*`, `font-*`, Tailwind colors, `overflow-*`, `shadow-*`, responsive prefixes (`sm:`, `md:`, `lg:`)

### OK in Twig

| Class / pattern | Why |
|-------|-----|
| `hidden` | Stimulus visibility |
| `sr-only` | Accessibility |
| `group` | Hover groups |
| `style="--ui-progress-pct: …"` | Dynamic progress fill only (`progress_bar.html.twig`) |
| Semantic project classes | `.layout-stack`, `.btn`, `.game-panel`, … |

---

## SCSS: where to add

1. Reusable layout → `_layout.scss`
2. Shared copy/tables/shell → `_shared.scss`
3. Domain UI → `_hero.scss`, `_marketplace.scss`, etc.
4. New token → `_tokens.scss` only

---

## Required checklist (every UI change)

- [ ] No Tailwind utilities in Twig (except `hidden`, `sr-only`, `group`)
- [ ] Colors via tokens / semantic copy classes
- [ ] All user text via `{{ 'key'|trans }}`
- [ ] Form fields: `label` + `for`/`id` or `aria-label`
- [ ] Icon-only controls: `aria-label`
- [ ] New class added in SCSS before Twig
- [ ] Code follows UI compliance rules (run the script only when the user asks)

---

## Verification commands (user-requested only)

Agents must not run these unless asked. Humans may use them before a PR:

```bash
bash scripts/check-ui-compliance.sh
npm run build
```

---

## Agent resources in repo

| Resource | Purpose |
|----------|---------|
| `AGENTS.md` | Entry point for all agents |
| `docs/screen-code-map.md` | Screen → code file map |
| `docs/backend-agent-cheatsheet.md` | PHP/Symfony quick reference |
| `.cursor/rules/php-backend.mdc` | Auto-injected on PHP edits |
| `.cursor/skills/fantager-backend/` | Full backend workflow skill + reference |
| `.cursor/rules/twig-ui.mdc` | Auto-injected on Twig edits |
| `.cursor/rules/scss-ui.mdc` | Auto-injected on SCSS edits |
| `.cursor/skills/fantager-ui/` | Full UI workflow skill |
