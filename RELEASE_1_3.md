# Release 1.3 — ERP Enhancement Baseline

Status: Complete (Phases 1–3 implemented; Phase 3 acceptance-tested and documented — see [PHASE3_BASELINE.md](PHASE3_BASELINE.md)).
This document is the consolidated release baseline across all three phases.

---

## Completed Features

### Phase 1 — Project Financial Fields

* Added project-level value tracking: `total_project_value`, `advance_amount`, `advance_date`, `advance_notes`.
* New derived financial summary calculation (`ProjectModel::getFinancialSummary()`): Remaining Billable Value (`total_project_value − total_billed`), Outstanding Collection Balance (`total_billed − advance_amount − total_paid`), and Billing Progress % (`total_billed / total_project_value × 100`, guarded against division by zero). Both remaining/outstanding figures are intentionally left unclamped — a negative value is meaningful (over-billed / over-collected).
* UI: Project Create/Edit forms gained the four new input fields; Project View gained a "Project Financial Summary" card (KPI-style breakdown + billing-progress bar) above the existing Sales/Purchases tables.

### Phase 2 — GST Per Line Item

* Added `gst_applicable` (boolean, default `1`) to `sale_items` and `purchase_items`, allowing individual lines within one invoice to be GST-exempt while others are taxed — i.e. mixed-GST invoices.
* New shared helper `gst_helper.php` (autoloaded globally via `Config/Autoload.php` → `$helpers = ['stock', 'gst']`):
  * `gst_calculate_line($quantity, $unitPrice, $gstPercent, $gstApplicable)` — single source of truth for the taxable/GST/total formula, used by both Sales and Purchases on every insert/update path.
  * `gst_summarize_items($items)` — sums taxable amount, GST amount, and grand total across a line-item set; correct for mixed invoices since exempt lines simply carry `gst_amount = 0`.
* Sales and Purchases create/edit forms gained a per-line "GST Applicable" toggle; both controllers now call `gst_calculate_line()` per item and `gst_summarize_items()` for the invoice total instead of computing GST inline.

### Phase 3 — Project Stock Return

* New "Project → General Stock" return workflow: product-based UI, FIFO allocation against the original purchase lines, full immutability (create-and-delete only, no edit), and gated delete (blocked once returned stock has been re-sold from General).
* RETURN entries recorded in `stock_ledger` using the same header/line reference convention as Purchases/Sales, making returns automatically visible in existing Stock and Stock Ledger reports with no report code changes.
* Purchase protection: a purchase becomes locked (edit/delete blocked, app-level + DB `RESTRICT` FK) once any of its lines have a return allocated against them.
* Completed-project exception: unlike Sales/Purchases, returns are permitted against `COMPLETED` projects.
* Full detail: [PHASE3_BASELINE.md](PHASE3_BASELINE.md).

---

## Database Migrations

| # | Migration | Phase | Effect |
|---|---|---|---|
| 1 | `2026-08-27-000000_AddProjectValueFields.php` | 1 | Adds `total_project_value`, `advance_amount`, `advance_date`, `advance_notes` to `projects` |
| 2 | `2026-08-27-000001_AddGstApplicableFields.php` | 2 | Adds `gst_applicable` (TINYINT(1), default 1) to `sale_items` and `purchase_items` |
| 3 | `2026-08-27-000002_CreateStockReturns.php` | 3 | Creates `stock_returns`, `stock_return_items`; extends `stock_ledger.reference_type` enum to add `RETURN` |

All three are additive (new columns/tables/enum values only) — none alters or drops pre-existing data-bearing columns.

---

## Routes Added

Phases 1 and 2 introduced **no new routes** — both reused the existing Projects/Sales/Purchases create, store, edit, update, view routes; only the underlying fields, calculations, and views changed.

Phase 3 added 6 routes, all under `['filter' => 'auth']`:

```
GET  stock-returns
GET  stock-returns/create
POST stock-returns/store
GET  stock-returns/view/(:num)
GET  stock-returns/delete/(:num)
GET  stock-returns/get-products/(:num)   (AJAX)
```

No `edit`/`update` route exists for Stock Returns — intentional (immutability by design).

---

## Controllers / Models / Views Added

**New (Phase 3 only):**
- Controller: `app/Controllers/StockReturns.php`
- Model: `app/Models/StockReturnModel.php`
- Views: `app/Views/stock_returns/index.php`, `create.php`, `view.php`

**Modified (no new files) — Phase 1:**
- `app/Models/ProjectModel.php` (allowedFields + `getFinancialSummary()`)
- `app/Controllers/Projects.php` (passes `financial_summary` to view)
- `app/Views/projects/create.php`, `edit.php`, `view.php`

**Modified (no new files) — Phase 2:**
- `app/Helpers/gst_helper.php` (new helper file, autoloaded — not a controller/model/view but a new file)
- `app/Config/Autoload.php` (registers `gst` helper)
- `app/Controllers/Sales.php`, `app/Controllers/Purchases.php` (use `gst_calculate_line`/`gst_summarize_items`)
- `app/Views/sales/create.php`, `edit.php`, `view.php`
- `app/Views/purchases/create.php`, `edit.php`, `view.php`

**Modified (no new files) — Phase 3:**
- `app/Config/Routes.php`, `app/Views/layouts/main.php`
- `app/Controllers/Purchases.php` (added `_hasLinkedReturns()` guard — separate from its Phase 2 change)
- `app/Controllers/Projects.php` (added `$data['returns']` — separate from its Phase 1 change)
- `app/Views/projects/view.php` (added Returns card — separate from its Phase 1 change)

---

## Backward Compatibility

Modules verified unchanged in behavior for data/flows outside the new fields:

| Module | Status | Basis |
|---|---|---|
| Sales | Additive only | Core create/store/edit/update/view/delete flow unchanged; only change is per-line GST toggle (Phase 2) — a non-GST-applicable line simply behaves as `gst_amount = 0`, matching prior implicit behavior for zero-GST items |
| Purchases | Additive only | Same as Sales, plus the new Phase 3 `_hasLinkedReturns()` guard, which only activates once a linked return exists — a purchase with no returns is unaffected |
| Payments | Unchanged | `Payments.php` not modified in any phase |
| Dashboard | Unchanged | `Dashboard.php` not modified in any phase |
| Reports | Unchanged except automatic new-data visibility | `Reports.php` not modified; GST totals now reflect per-line `gst_applicable` (correct behavior, not a regression); Stock/Ledger reports show `RETURN` rows automatically since they're ordinary `stock_ledger` rows |
| Stock Entry (manual adjustments) | Unchanged | `Stock.php` not modified in any phase |

Verified in Phase 3D via live HTTP + SQL acceptance testing (2 projects, 3 GST products, purchases, sales, returns) — see [PHASE3_BASELINE.md](PHASE3_BASELINE.md) §11 for the Phase 3 regression evidence. Phase 1/2 field additions are backward-compatible by construction: all new columns are non-null with defaults (`0.00` / `1`), so pre-existing rows and code paths that don't reference the new fields are unaffected.

---

## Known Limitations (deferred to Phase 4+)

* Stock Returns have no edit path — a mis-entered return must be deleted (if eligible) and re-created; there is no in-place correction.
* Stock Return delete is all-or-nothing per return (blocked if *any* line's returned stock has been re-sold), not partial/per-line.
* GST is not computed or stored on Stock Return transactions — returns are stock-quantity movements only, no financial/GST re-statement.
* No Sales-side "restock from return" shortcut — a returned-to-General item is sold again through the normal Sales flow like any other General-stock item.
* Project financial summary (Phase 1) does not account for Purchases, Expenses, or Stock Returns — it is Sales/Advance/Payment based only, as scoped.
* No automated test suite covers Phases 1–3; all verification to date is manual/HTTP-level (Phase 3D) or code-review level (Phases 1–2).
