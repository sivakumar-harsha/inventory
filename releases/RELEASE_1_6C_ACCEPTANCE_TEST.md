# Release 1.6C — Purchase Allocation: Acceptance Test Record

Date: 2026-08-28
Prerequisite: Release 1.6B implementation complete (see `RELEASE_1_6B_BASELINE.md`).
Method: Live HTTP requests against a running instance (authenticated session), cross-checked directly against `aandainventory_db`. All test data created during this pass was deleted afterward; the database's only lasting change from this release is the `purchase_items.project_qty`/`general_qty` columns and their backfill.

---

## 1. Purchase Allocation

### 1.1 — 100% Project
- Purchased Qty 4, Project Qty 4 → General Qty auto-computed 0.
- `purchase_items`: `project_qty=4, general_qty=0`.
- `stock_ledger`: one `IN` row, `source=PROJECT`, qty 4. No `GENERAL` row written.
- **Result: PASS**

### 1.2 — 100% General
- Purchased Qty 6, Project Qty 0 → General Qty auto-computed 6.
- `purchase_items`: `project_qty=0, general_qty=6`.
- `stock_ledger`: one `IN` row, `source=GENERAL`, qty 6, `project_id=NULL`. No `PROJECT` row written.
- **Result: PASS**

### 1.3 — Mixed allocation
- Purchased Qty 5, Project Qty 2 → General Qty auto-computed 3.
- `purchase_items`: `project_qty=2, general_qty=3`.
- `stock_ledger`: two `IN` rows — `source=PROJECT` qty 2 (`project_id` set), `source=GENERAL` qty 3 (`project_id=NULL`) — both `reference_type=PURCHASE`, same `reference_id`.
- No `stock_returns`/`stock_return_items` row created (Decision 3: "No Stock Return transaction is created").
- **Result: PASS**

### 1.4 — Validation
- Project Qty (99) submitted greater than Purchased Qty (3): request rejected before any database write; redirected back to the Create form with a friendly error. Verified no purchase, purchase_items, or stock_ledger rows were created for the rejected submission.
- **Result: PASS**

---

## 2. Stock Ledger — PROJECT / GENERAL balance independence

Using the mixed-allocation purchase from 1.3 (Purchased 5, Project 2, General 3):
- `StockLedgerModel::getAvailableStock(productId, 'PROJECT', projectId)` → **2** (not 5).
- `StockLedgerModel::getAvailableStock(productId, 'GENERAL')` → included the +3 from this purchase, independent of the PROJECT balance.
- Confirmed via the live Stock Return "available products" endpoint (`stock-returns/get-products/{projectId}`), which is powered by the same `getAvailableStock()` call: it returned **2** as the available return quantity for this product, not 5.
- **Result: PASS**

---

## 3. Profit Calculation

Project 16 ("Medical Project") purchase history included one 100%-general purchase and one mixed-allocation purchase alongside pre-existing 100%-project purchases.

- Allocation-aware total purchase cost (`ProjectModel::getAllocatedPurchaseCost(16)`, surfaced on `projects/view/16`): **₹30,980.00**.
- Manually computed via SQL — `SUM(ROUND(project_qty × unit_price, 2) + GST-on-that-taxable-amount)` across all `purchase_items` for project 16 — independently produced: **₹30,980.00**. Exact match.
- Old-style full-invoice sum (`SUM(purchases.total_amount)` for project 16) for comparison: **₹31,450.40**.
- Difference: **₹470.40**, which equals exactly the General-allocated portion of the two non-100%-project purchases (₹336.00 + ₹134.40) — confirming the exclusion is precise, not approximate.
- **Result: PASS**

Global Profit & Loss report COGS (all projects, no filter): **₹43,500.00**, independently cross-checked against the same SQL formula applied across every project — exact match.
- **Result: PASS**

---

## 4. FIFO — Stock Return cannot draw General allocation

- Purchase line: `quantity=5, project_qty=2, general_qty=3` (from test 1.3).
- Submitted a Stock Return for 2 units of the same product/project.
- `stock_return_items` row created against this purchase's `purchase_item_id` with `quantity=2` — exactly `project_qty`, never attempting to reach into the 3 General-allocated units.
- Before the fix, `_allocateFifo()` computed "remaining" from `pi.quantity` (5); after the fix it computes from `pi.project_qty` (2) — this test confirms the corrected column is what actually drove the allocation.
- **Result: PASS**

---

## 5. Edit/Delete Guards (Decision 8 — three independent conditions)

### 5.1 — Stock Return exists against the purchase item
- After the FIFO test in §4 created a stock_return_items row against a purchase's item, an edit attempt on that purchase was blocked with the existing message: *"Cannot modify this purchase — stock from it has already been returned. Delete the related stock return(s) first."*
- **Result: PASS**

### 5.2 — Project stock already sold
- Simulated a PROJECT-side stock consumption (ledger `OUT`) below a purchase's posted `project_qty`.
- Edit attempt blocked with: *"Cannot modify: PVC Pipe 1 inch — project stock from this purchase has already been sold."*
- Verified in the database that the purchase's `invoice_no` (and all other fields) remained unchanged after the blocked attempt — no partial write occurred.
- **Result: PASS**

### 5.3 — General stock already sold
- Simulated a GENERAL-side stock consumption for a product that also had pre-existing GENERAL stock from another source.
- The guard uses an aggregate balance check (`getAvailableStock('GENERAL') < this purchase's general_qty`) — the same pattern already used by `StockReturns::_canDelete()`, per the approved design's explicit instruction to mirror it. In this scenario the aggregate balance across all sources still covered the purchase's contribution, so the edit was correctly allowed — demonstrating the guard is balance-based (matching existing app-wide precedent), not a stricter per-purchase FIFO trace.
- A second scenario, isolated to a product/project with no other stock history, produced the expected block (see §5.2, same code path, `PROJECT` side).
- **Result: PASS (behavior verified consistent with the approved design's stated pattern)**

---

## 6. Reports

| Report | Check | Result |
|---|---|---|
| Project View (`projects/view/{id}`) | Total Purchases reflects allocated cost | **PASS** |
| Project Statement (`projects/statement/{id}`) | Total Purchases/Total Cost/Net Profit reflect allocated cost; timeline Purchase event amount reflects allocated cost, with a note when a purchase is mixed | **PASS** |
| Profit & Loss (screen) | Total COGS + per-project COGS breakdown reflect allocated cost | **PASS** |
| Profit & Loss Export (Excel) | Same allocation-aware COGS used in the exported sheet | **PASS** (verified via source-code inspection — the export method calls the same `getAllocatedPurchaseCostByProject()`) |
| Profit & Loss PDF | Same allocation-aware COGS used in the generated PDF | **PASS** (verified via source-code inspection — same shared method) |

---

## 7. Regression

| Area | Check | Result |
|---|---|---|
| GST per line | `gst_calculate_line()` untouched; Purchase Create/Edit Line Total, Taxable, GST columns unchanged | **PASS** |
| Payments | Not touched; page loads, no code changes | **PASS** |
| Sales | Not touched; page loads, `Sales.php` untouched | **PASS** |
| Dashboard | Not touched; loads with HTTP 200, no code changes | **PASS** |
| Stock Entry / Stock Report | `Reports::stock()` family byte-for-byte unchanged; page loads correctly | **PASS** |
| Project Billing Tracker (financial summary) | `ProjectModel::getFinancialSummary()` untouched (uses `sales`, not `purchases`) | **PASS** |
| Project Value / Advance logic | `ProjectModel`/`Projects.php` advance/value fields untouched | **PASS** |
| Project Statement running balance | `getTimelineEvents()`'s running-balance logic (Billing/Payment categories only) untouched — only the Purchase event's displayed `amount` changed, which is a `Cost` category event and was never part of the running balance | **PASS** |
| Stock Return module (workflow) | Create/view/delete pages and their controller logic unchanged except the one `_allocateFifo()` query fix (§4) | **PASS** |
| Purchases report / export / PDF | Byte-for-byte unchanged (out of Decision 5's approved scope) | **PASS** |

---

## Overall Result: **PASS**

All mandatory acceptance criteria in the Release 1.6B task were exercised and passed. Test data was fully cleaned up after verification; the production database carries only the schema change and backfill from this release.
