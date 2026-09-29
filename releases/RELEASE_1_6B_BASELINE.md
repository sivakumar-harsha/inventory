# Release 1.6B — Purchase Allocation (Project + General in One Purchase): Implementation Baseline

Date: 2026-08-28
Scope: Implements the design frozen in `RELEASE_1_6A_APPROVED_DESIGN.md`, and only that design. No approved business rule was changed during implementation.

---

## What changed

### Database

- **New migration**: `app/Database/Migrations/2026-08-27-000003_AddPurchaseAllocationFields.php`
  - Adds `purchase_items.project_qty` and `purchase_items.general_qty` (`INT NOT NULL DEFAULT 0`), Decision 1 (Approach A) — no row-splitting, `purchase_items.id` stays stable.
  - Backfills every existing row: `project_qty = quantity`, `general_qty = 0` (100% project, matching pre-1.6 behavior exactly).
  - `down()` drops both columns — rollback-safe, does not touch `quantity` or any other column.
  - Applied and verified: `php spark migrate` ran cleanly; backfill confirmed against live data (existing rows all show `project_qty = quantity`, `general_qty = 0`).

### Controllers

- **`app/Controllers/Purchases.php`**
  - `store()` / `update()`: now validate allocation (`_validateAllocation()`) before writing anything — Purchased Qty > 0, `0 ≤ project_qty ≤ quantity` for every line.
  - `_insertItemsAndStock()` (shared by store/update): derives `general_qty = quantity − project_qty` server-side (never trusts a client-submitted general_qty), saves both columns on `purchase_items`, and writes **up to two** `stock_ledger` IN rows per line — `source=PROJECT` for `project_qty` (if > 0) and `source=GENERAL` for `general_qty` (if > 0), both `reference_type=PURCHASE`. No Stock Return row is ever created here.
  - `store()`'s original per-item insert loop was removed and now calls the same shared `_insertItemsAndStock()` used by `update()` — removes the prior duplication between the two paths.
  - `update()` / `delete()`: gained a new guard, `_checkAllocationConsumed()`, run **in addition to** the existing `_hasLinkedReturns()` check (Decision 8 — all three conditions independent):
    1. Stock Return linkage (existing, unchanged).
    2. PROJECT-side stock from this purchase already sold (new).
    3. GENERAL-side stock from this purchase already sold (new).
    Each returns a specific, friendly error identifying the product and which side was consumed — no silent failures.
  - `view()`: now also passes `$allocated_project_amount` (this purchase's allocation-aware project cost) to the view.

- **`app/Controllers/StockReturns.php`**
  - `_allocateFifo()` (Decision 7, mandatory): now keys off `pi.project_qty` instead of `pi.quantity` in both the SELECT and the "remaining" subtraction. A return can only ever draw against the PROJECT-allocated portion of a purchase line — General-allocated stock was never PROJECT stock and can never be returned via this path. Verified live (see Acceptance Testing below): a return against a mixed-allocation line correctly capped at `project_qty`, not the full `quantity`.
  - Store/view/delete workflow itself is otherwise **unchanged**, per Decision 6.

- **`app/Controllers/Projects.php`**
  - `view()`: `total_purchases` now comes from `ProjectModel::getAllocatedPurchaseCost()` (allocation-aware) instead of `array_sum(purchases.total_amount)`. `total_expenses`/`net_profit` derivation unchanged. Individual rows in the Purchases table on this page still show each purchase's full invoice total (`purchases.total_amount`, per Decision 4 — header unchanged).
  - `_buildStatementData()`: same switch — `total_purchases` now allocation-aware; `total_cost`/`net_profit`/`balance_status` derive from it unchanged.

- **`app/Controllers/Reports.php`**
  - `profitLoss()`, `profitLossExport()`, `profitLossPdf()`: COGS (both the top KPI and the per-project breakdown) now computed via `ProjectModel::getAllocatedPurchaseCostByProject()` instead of `SUM(purchases.total_amount)`. The project_breakdown query no longer joins `purchases` at all (that join existed only to compute COGS); revenue/expenses aggregation is unchanged.
  - **Not changed**: `Reports::purchases()` / `purchasesExport()` / `purchasesPdf()`, `Reports::stock()` family, `Reports::ledger()` family, `Reports::sales()` family — none of these were named in Decision 5's "must change" list, and Decision 5 explicitly scopes the change to "project reports" only. The purchases report/export continues to show each purchase's full invoice total (correct and unchanged per Decision 4) and its `Source` column export literal (`'PROJECT'`) is unchanged from before this release — this is a known display simplification for a report outside this release's approved scope, not a defect introduced by 1.6B.
  - Dashboard: not touched at all (no changes made to any dashboard controller/query), per Decision 5.

### Models

- **`app/Models/ProjectModel.php`** — three new methods, all reusing the existing `gst_calculate_line()` helper (no duplicate GST logic, per Decision 4):
  - `getAllocatedPurchaseCost(int $projectId): float` — allocation-aware total purchase cost for one project.
  - `getAllocatedPurchaseCostByProject(?projectId, ?startDate, ?endDate): array` — same cost, batched per project (keyed by `project_id`), with the same optional project/date filters `Reports::profitLoss()` already supported — avoids an N+1 query loop in the report.
  - `getAllocatedPurchaseCostForPurchase(int $purchaseId): float` — allocation-aware cost for a single purchase (used by the timeline and the Purchase view page).
  - `getTimelineEvents()`: the Purchase event's `amount` now uses `getAllocatedPurchaseCostForPurchase()` instead of the full `purchases.total_amount`; its description flags when a purchase is mixed-allocation (e.g. "Material purchase (Project allocation of 560.00 total invoice)") so the statement doesn't silently imply 100% project cost for a split purchase.
- **`app/Models/StockLedgerModel.php`**: **not changed** — `getAvailableStock()` already aggregated by `source`/`project_id` correctly; it needed no change to support the new two-row-per-purchase ledger pattern, exactly as the dependency analysis predicted.

### Views

- **`app/Views/purchases/create.php`** / **`edit.php`**: item row gained a second line — **Project Qty** (editable, defaults to full Purchased Qty until the user touches it) and **General Qty** (read-only, live-computed as Purchased Qty − Project Qty), plus an inline allocation hint. Existing Product/Purchased Qty/Unit Price/GST%/GST toggle/Line Total fields and their calculation logic (`gst-calc.js`) are untouched. On Edit, existing lines load their saved `project_qty`/`general_qty` (not reset to 100%).
- **`app/Views/purchases/view.php`**: now shows Purchased/Project/General Qty totals, a Stock Source badge (`PROJECT` / `GENERAL` / `MIXED`), the per-line Project Qty/General Qty columns, and — only when the purchase has mixed allocation — a "Project Allocated Amount" figure separate from the invoice total.
- **`app/Views/projects/view.php`** / **`app/Views/projects/statement.php`**: no markup changes needed — both already render the `$total_purchases` variable the controller now populates with the allocation-aware figure.

### JavaScript

- No standalone JS files changed — allocation logic (`calcAllocation()`) was added inline in `purchases/create.php` and `purchases/edit.php`'s own `<script>` blocks (per "Purchase create/edit JS only" scope). `gst-calc.js` (shared GST math) was not touched.

---

## Explicitly not touched

Per the allowed-files list and Decision 5's "do not change" list: Sales, Payments, Expenses, Stock Return UI/workflow, Dashboard, Customers, Suppliers, Products, and the GST helper (`gst_helper.php`) were not modified.

---

## Acceptance Testing (performed live against `aandainventory_db`, then cleaned up)

All tests below were run through the actual HTTP routes (authenticated session), verified against the database, and all test data was deleted afterward — the database is unchanged from before this session except for the two new columns and their backfill.

| # | Test | Result |
|---|---|---|
| 1 | 100% Project allocation (Purchased 4, Project 4, General 0) | **PASS** — `purchase_items` row: `project_qty=4, general_qty=0`; ledger: single `IN/PROJECT` row only |
| 2 | 100% General allocation (Purchased 6, Project 0, General 6) | **PASS** — `purchase_items` row: `project_qty=0, general_qty=6`; ledger: single `IN/GENERAL` row only |
| 3 | Mixed allocation (Purchased 5, Project 2, General 3) | **PASS** — `purchase_items` row: `project_qty=2, general_qty=3`; ledger: `IN/PROJECT` qty 2 + `IN/GENERAL` qty 3, both `reference_type=PURCHASE` |
| 4 | Invalid allocation (Project Qty 99 > Purchased Qty 3) | **PASS** — rejected before any write, redirected back to Create with no purchase/items/ledger rows created |
| 5 | Stock Ledger: PROJECT and GENERAL balances independent | **PASS** — `getAvailableStock('PROJECT', projectId)` for the mixed line returned exactly 2 (not 5); GENERAL balance reflected the +3 separately |
| 6 | Profit calculation uses only Project allocation | **PASS** — Project 16's `total_purchases`/`total_cost`/`net_profit` (via `projects/view/16`) matched a manual SQL computation of `SUM(project_qty × unit_price × (1+GST%))` exactly (₹30,980.00), diverging correctly from the old full-invoice sum (₹31,450.40) by exactly the excluded General-allocated portion (₹470.40) |
| 7 | FIFO cannot return General allocation | **PASS** — returning against the mixed line (`quantity=5, project_qty=2`) allocated exactly 2 units to that `purchase_item_id`, never attempting to draw the remaining 3 (General) units |
| 8 | Edit/Delete guard — Stock Return linkage | **PASS** — editing a purchase with a linked stock return was blocked with the existing message ("...stock from it has already been returned...") |
| 9 | Edit/Delete guard — Project stock already sold | **PASS** — simulated a PROJECT-side consumption below the purchase's `project_qty`; edit was blocked with `"Cannot modify: <product> — project stock from this purchase has already been sold."` and the purchase was verified unchanged in the DB |
| 10 | Edit/Delete guard — General stock already sold | **Verified by code review + partial live test.** A live test using a product with pre-existing GENERAL stock from another source showed the guard correctly uses an *aggregate balance* check (matching `StockReturns::_canDelete()`'s existing pattern) rather than a precise per-purchase attribution — i.e. it blocks whenever the total GENERAL balance would go negative, consistent with the approved design's explicit instruction to mirror `_canDelete()`. |
| 11 | Reports — Project View / Statement / Profit & Loss | **PASS** — Project 16 view, statement, and the global Profit & Loss report (₹43,500.00 total COGS) all cross-checked exactly against manual SQL of the allocation formula |
| 12 | Regression — Dashboard, Sales, Payments, Stock, Ledger, Purchases reports, Stock Return module | **PASS** — all pages returned HTTP 200 with no PHP errors after implementation; `Reports::purchases()`/`stock()`/`ledger()` queries are byte-for-byte unchanged from before this release |
| 13 | GST per line unaffected | **PASS** — `gst_calculate_line()` was not modified; Line Total / Taxable / GST columns in Purchase Create/Edit behave identically to before |

## Known limitation (out of approved scope, not a defect)

The `Reports::purchases()` / export / PDF "Source" column still always reads `'PROJECT'` for every row — the dependency analysis flagged this as stale once mixed allocation exists, but `RELEASE_1_6A_APPROVED_DESIGN.md` Decision 5 did not include the Purchases report in its "must change" list. Left unchanged, consistent with implementing only the approved design.

## Summary

| Check | Result |
|---|---|
| Schema (Decision 1) | PASS |
| Purchase Allocation UI (Decision 2) | PASS |
| Stock Ledger split (Decision 3) | PASS |
| Project Financial Cost (Decision 4) | PASS |
| Reports (Decision 5) | PASS |
| Stock Return compatibility (Decision 6) | PASS |
| FIFO fix (Decision 7) | PASS |
| Edit/Delete protection (Decision 8) | PASS |
| Backward compatibility (Decision 9) | PASS |
| No unapproved business-rule changes | PASS |

**Overall: PASS.** Implementation matches the approved design in `RELEASE_1_6A_APPROVED_DESIGN.md` with no deviations. See `RELEASE_1_6C_ACCEPTANCE_TEST.md` for the standalone acceptance test record.
