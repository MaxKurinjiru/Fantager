# Fantager UI — Examples

Real patterns from this codebase. **Bad** = do not ship. **Good** = target state.

---

## 1. Filter form (marketplace browse)

**Bad**

```twig
<form class="grid grid-cols-1 md:grid-cols-4 gap-4">
  <div class="flex flex-col gap-1">…</div>
  <div class="md:col-span-4 flex justify-end gap-3 mt-2">…</div>
</form>
```

**Good**

```twig
<form class="marketplace-filter-grid">
  <div class="form-field-group form-field-group--tight">…</div>
  <div class="marketplace-filter-actions">…</div>
</form>
```

SCSS: `_marketplace.scss` (`.marketplace-filter-grid`, `.marketplace-filter-actions`)

---

## 2. Equipment slots (paperdoll)

**Bad**

```twig
<div class="grid grid-cols-2 gap-4">
  <button class="btn btn-danger absolute right-1.5 top-1.5 w-5 h-5 …">✕</button>
  <span class="text-2xl mb-1 select-none">🗡️</span>
</div>
```

**Good**

```twig
<div class="paperdoll-grid">
  <button class="btn btn-danger btn--sm paperdoll-slot__unequip" aria-label="…">✕</button>
  <span class="paperdoll-slot__icon">🗡️</span>
</div>
```

SCSS: `_item.scss`

---

## 3. Page header actions

**Bad**

```twig
<a href="…" class="btn btn-outline gap-1.5 shadow-lg">⬅️ Back</a>
```

**Good**

```twig
<a href="…" class="btn btn-outline btn--icon-lead btn--elevated">⬅️ {{ '…'|trans }}</a>
```

SCSS: `_buttons.scss`

---

## 4. Scrollable data table

**Bad**

```twig
<div class="game-panel overflow-x-auto">
  <td colspan="7" class="text-center">…</td>
</div>
```

**Good**

```twig
<div class="game-panel game-panel--scroll">
  <td colspan="7" class="data-table__col--center">…</td>
</div>
```

SCSS: `_marketplace.scss`, `_shared.scss`

---

## 5. Modal close button

**Bad**

```twig
<span aria-hidden="true" class="text-xl leading-none">✕</span>
```

**Good**

```twig
<button type="button" class="modal-close" aria-label="{{ 'modal.close'|trans }}">
  <span aria-hidden="true" class="modal-close__icon">✕</span>
</button>
```

SCSS: `_modal.scss`

---

## 6. Status / warning hint

**Bad**

```twig
<p class="hero-panel__hint text-warning mt-2">…</p>
<p class="copy-muted text-xs mt-2">…</p>
```

**Good**

```twig
<p class="hero-panel__hint hero-panel__hint--warning">…</p>
<p class="hero-panel__hint hero-panel__hint--muted">…</p>
```

SCSS: `_shared.scss`

---

## 7. Stimulus dynamic row

**Bad** (in JS)

```javascript
row.innerHTML = `<td class="py-3.5 px-4 font-semibold">${name}</td>`
```

**Good** (in Twig template + JS clone)

```twig
<template id="template-listing-row">
  <tr>
    <td class="marketplace-table__cell marketplace-table__cell--emphasis js-name"></td>
  </tr>
</template>
```

```javascript
const row = this.templateTarget.content.cloneNode(true)
row.querySelector('.js-name').textContent = name
```

---

## 8. New layout primitive (agent workflow)

When `.dashboard-grid-2` did not exist:

1. Added to `_shared.scss`:

```scss
.dashboard-grid-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
  @media (min-width: 768px) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
```

2. Replaced Twig `grid grid-cols-1 md:grid-cols-2 gap-6` with `dashboard-grid-2`
3. Documented in `ui-guidelines.md` §3.1
4. Ran `bash scripts/check-ui-compliance.sh`
