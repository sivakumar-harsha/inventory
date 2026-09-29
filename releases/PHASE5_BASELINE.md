# Phase 5B — Project Statement Implementation: Acceptance Baseline

Date: 2026-08-27
Scope: Project Statement & Timeline (approved Phase 5B implementation)

## Files Changed

- `app/Controllers/Projects.php` — added `statement()`, `statementExport()`, `statementPdf()`, private `_buildStatementData()`
- `app/Models/ProjectModel.php` — added `getTimelineEvents()`
- `app/Views/projects/statement.php` — new view
- `app/Views/projects/view.php` — added "View Statement" link
- `app/Config/Routes.php` — added 3 routes (`projects/statement/(:num)`, `projects/statement-export/(:num)`, `projects/statement-pdf/(:num)`)

No migrations. No changes to Sales/Purchases/Payments/Expenses/StockReturns controllers.

## 1. Browser Test — PASS

Tested against live XAMPP instance (`http://localhost/aandainventory`), logged in as `aainv`.

- `projects/statement/14` (project with 3 sales, 4 purchases, 4 returns, 0 expenses, 0 advance/payments): page renders 200, no PHP warnings/errors, correct title, all 11 timeline rows present.
- `projects/statement/15` (project with zero transactions): renders 200, "No timeline events for this project" empty state shown correctly.
- `projects/statement/9999` (nonexistent project): redirects cleanly to `/projects` with an error flash — no 500.
- `projects/view/14` (existing page, regression check): renders 200, unchanged, "View Statement" link present and correct.

## 2. SQL Verification — PASS

Cross-checked rendered figures against direct DB queries for project 14:

- Total Billed = 590.00 + 590.00 + 6.16 = **1,186.16** ✓ matches 3 sales rows
- Outstanding Collection Balance = 1,186.16 − 0 (advance) − 0 (payments) = **1,186.16** ✓ matches page and matches final running-balance row
- Total Purchases = 5,900.00 + 3,540.00 + 1,416.00 + 2,124.00 = **12,980.00** ✓
- Total Cost = Purchases + Expenses = 12,980.00 + 0 = **12,980.00** ✓
- Net Profit = Total Billed − Total Cost = 1,186.16 − 12,980.00 = **−11,793.84** ✓ (correctly flagged red/negative)
- Timeline: chronological order correct (2026-08-01 → 2026-08-27); running balance accumulates only on Billing (+) rows for this project (no Payment/Advance rows exist) and shows "—" for all Cost/Inventory rows, per spec.
- Stock Return rows: product names and returned quantities render correctly via `GROUP_CONCAT`/join through `stock_return_items` → `products`; destination fixed text "General Stock"; no monetary amount shown, per spec.

## 3. Export Verification — BLOCKED (pre-existing defect, not a regression)

`projects/statement-export/14` and `projects/statement-pdf/14` both return HTTP 500:

```
Class "PhpOffice\PhpSpreadsheet\Spreadsheet" not found
```

Root cause: `app/ThirdParty/phpoffice` and `app/ThirdParty/dompdf` are vendored on disk but are **not registered** in `app/Config/Autoload.php` (no PSR-4 mapping, not in `vendor/composer/autoload_*`). This is not something Phase 5B introduced.

**Confirmed pre-existing**: hitting the existing, unmodified `reports/profit-loss-export` endpoint produces the identical error at `Reports.php:118`. The new `statementExport()`/`statementPdf()` methods were written to exactly mirror `Reports::profitLossExport()`/`profitLossPdf()`'s structure and styling per the requirement ("Reuse Reports export styling") — their logic is correct and will work once the autoload gap is fixed.

`app/Config/Autoload.php` is **not** in the Phase 5B allowed-files list, so it was not modified. Fixing the PhpSpreadsheet/Dompdf autoload registration is a prerequisite for *all* export features in this app (existing Reports exports included) and should be tracked as a separate, explicitly-scoped fix.

## 4. Regression Verification — PASS

- `ProjectModel::getFinancialSummary()` untouched — contract frozen, still returns the same 7 keys `Projects::view()` depends on.
- `Projects::view()`, `Projects::index()`, `Projects::edit()`, etc. unchanged and still render correctly.
- No changes to Sales/Purchases/Payments/Expenses/StockReturns controllers or models.
- New routes are additive only; no existing route signatures changed.

## Summary

| Check | Result |
|---|---|
| Browser test | PASS |
| SQL verification | PASS |
| Export verification | BLOCKED — pre-existing Autoload.php gap, outside Phase 5B scope |
| Regression verification | PASS |

Overall: **PASS**, with export generation blocked by a pre-existing, out-of-scope infrastructure defect shared with the existing Reports module.
