# Release 1.7C — Acceptance Test

**Method:** a throwaway Spark CLI command (`app/Commands/TestRelease17C.php`) exercised the real, unmodified model/controller/view code against the live database (`aandainventory_db`), then was deleted after the run — the same pattern used for the Release 1.7A acceptance pass. Direct HTTP smoke testing was not used because of a pre-existing, out-of-scope local environment mismatch (Apache serving PHP 8.2.12 against Composer's ≥8.3 requirement) that predates this release and affects all routes, not just the ones touched here.

Run: `php spark test:release-17c` — **32 passed, 0 failed**.

---

## Removal completeness (files/routes/DB)

| # | Check | Result |
|---|---|---|
| 1 | `StockReturns` controller file deleted | PASS |
| 2 | `StockReturnModel` file deleted | PASS |
| 3 | `stock_returns/` view directory deleted | PASS |
| 4 | `Routes.php` contains no `stock-returns` paths | PASS |
| 5 | `Routes.php` contains no `StockReturns::` references | PASS |
| 6 | Sidebar (`layouts/main.php`) has no Stock Returns link | PASS |
| 7 | `projects/view.php` has no Stock Returns button | PASS |
| 8 | `projects/view.php` has no leftover Returns card | PASS |
| 9 | `Purchases.php` has no `_hasLinkedReturns` guard | PASS |
| 10 | `Purchases.php` has no `stock_return` table references | PASS |
| 11 | `Purchases.php` still has `_checkAllocationConsumed` (stock-sold guard preserved) | PASS |
| 12 | `Projects.php` has no `stock_return` table references | PASS |
| 13 | `ProjectModel.php` has no `stock_return` table references | PASS |
| 14 | `ProjectModel.php` has no `Stock Return` event type | PASS |
| 15 | `statement.php` has no Stock Return icon/label | PASS |
| 16 | `statement.php` has no dead `.category-inventory` CSS | PASS |
| 17 | `stock_returns` / `stock_return_items` tables dropped (`SHOW TABLES`) | PASS |
| 18 | `stock_ledger.reference_type` enum no longer contains `RETURN` | PASS |
| 19 | No `stock_ledger` rows with `reference_type='RETURN'` remain | PASS |

## Purchases

| # | Check | Result |
|---|---|---|
| 20 | `purchases/create.php` still renders the allocation helper text (Release 1.7A UI untouched) | PASS |
| 21 | `purchases/edit.php` still renders the allocation helper text (Release 1.7A UI untouched) | PASS |
| 22 | `_checkAllocationConsumed()` (project/general stock-sold guard) still runs without error | PASS |

Not independently re-verified by the CLI harness (unmodified in this release, previously verified in Release 1.6B/1.6C/1.7A acceptance passes): purchase Create/Store, Update validation (`_validateAllocation`), and Delete stock-ledger reversal — none of these code paths were touched by the Stock Return removal.

## Sales / Payments

Unmodified in this release. `SaleModel`, `Sales` controller, and `Payments` controller contain no Stock Return references (confirmed in the dependency analysis grep) and were not edited.

## Projects

| # | Check | Result |
|---|---|---|
| 23 | `getTimelineEvents()` returns an array without error | PASS |
| 24 | Timeline contains zero `Stock Return` events | PASS |
| 25 | `getFinancialSummary()` still returns all expected keys | PASS |
| 26 | `getAllocatedPurchaseCost()` runs without error | PASS |
| 27 | `_buildStatementData()` returns data for a live project | PASS |
| 28 | Rendered `projects/statement.php` contains no "Stock Return" text | PASS |
| 29 | `projects/statement.php` renders non-trivially (>500 chars output) | PASS |
| 30 | Rendered `projects/view.php` has no "Stock Returns" button | PASS |
| 31 | Rendered `projects/view.php` has no `stock-return` links | PASS |

Project Profit calculation (`net_profit = total_sales - total_purchases - total_expenses`) is unchanged — verified by reading `Projects::view()` and `_buildStatementData()`, neither of which was edited beyond the `$data['returns']` query removal.

## Reports

| # | Check | Result |
|---|---|---|
| 32 | `reports/ledger.php` has no RETURN-specific code | PASS |

Stock, Profit & Loss, and Dashboard reports were not touched by this release (confirmed via dependency analysis — no Stock Return references existed in `Reports::stock()`, `Reports::profitLoss()`, or `Dashboard.php` before this release).

## Stock

Project Qty / General Qty allocation, and stock consumption via Sales, run through `StockLedgerModel`/`stock_helper.php`, both confirmed generic (no `RETURN`-specific branching existed before this release, so none was removed). Not independently re-exercised here since neither file was edited.

---

## Summary

No regressions found. All checks that could be exercised without a live HTTP request (blocked by the pre-existing PHP-version/Apache mismatch, unrelated to this release) passed against real model/controller/view code and the live database schema.
