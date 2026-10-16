# Finance Ledger Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [economy-system.md](../systems/economy-system.md), [financial-crisis-system.md](../systems/financial-crisis-system.md)

**When:** Inspect gold on hand, weekly costs, period/all-time net, and the full `FinancialRecord` audit log.

**Status:** Live at `GET /app/finance`. Sidebar **Finance**. Dashboard, HQ arena, and the navbar ledger modal link here. Type filters use `FinancialRecordType::liveCases()` (reserved `spell_slot_cost` / `morale_restoration` are not offered).

**Live UI:** `templates/finance/index.html.twig`, `templates/components/finance/`.

---

## Implemented

### Header

- Title / subtitle
- Treasury badge (current `team.gold`)

### Crisis banner

Same `financial_crisis_banner.html.twig` as the dashboard when crisis level is not `none`.

### Summary (`summary_stats.html.twig`)

`FinanceSummaryService::buildOverview()`:

- Gold on hand and unpaid debt
- Weekly expenses split into payroll and HQ maintenance
- Period net (income / expense)
- All-time net (income / expense)

### Ledger (`ledger_content.html.twig`)

GET filters, auto-submit:

| Control | Query | Values |
|---------|-------|--------|
| Type | `type` | Live `FinancialRecordType` cases, or all |
| Actor | `actor` | `system` / `active` / `passive`, or all |
| Sort | `sort` | `date-desc` (default), `date-asc`, `amount-desc`, `amount-asc` |

Table: type, actor badge, signed gold change, timestamp in the kingdom timezone (`processedAt` as tooltip when set). **50** rows per page. Empty state when the filter matches nothing.

### Backend

| Method | Path | Role |
|--------|------|------|
| GET | `/app/finance` | Full page (filters + summary) |
| GET | `/api/v1/finance/status` | Crisis JSON (dashboard / banner) |
| GET | `/api/v1/finance/recent` | Last 10 rows (navbar ledger modal) |

The page is server-rendered. The API is for the modal and crisis widgets, not this table.

Writers are other systems (`EconomyService` ledger entries). This screen does not create records.

---

## Related UI (not this page)

| Surface | Notes |
|---------|-------|
| Dashboard | Summary tiles + recent rows + link here — [02-team-dashboard.md](02-team-dashboard.md) |
| Navbar ledger modal | `ledger` Stimulus + `GET /api/v1/finance/recent`; footer links here |
| HQ arena panel | “View full ledger” after attendance history — [18-arena-management.md](18-arena-management.md) |
| Marketplace | Buy/sell/tax write the ledger; history tab is marketplace transactions, not this page — [15-marketplace.md](15-marketplace.md) |

---

Later product decisions (diminishing returns): [known-issues.md](../product/known-issues.md) § Later product decisions. Royal Treasury balance stays hidden from players.

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/finance/index.html.twig` |
| Summary / table | `templates/components/finance/` |
| Crisis banner | `templates/components/dashboard/financial_crisis_banner.html.twig` |
| Filters | `form-auto-submit` |
| Navbar modal | `templates/components/layout/ledger_modal.html.twig`, `ledger` |
