# Release 1.6A — Purchase Allocation (Project + General in One Purchase)

## Dependency Analysis (read-only — NOT approved for implementation)

Date: 2026-08-27
Status: **ANALYSIS ONLY. No code changed.**

---

## 0. What exists today (the assumption this feature breaks)

`purchases` has a single `project_id` (nullable, `ON DELETE SET NULL`). Every `purchase_items` row inherits that one project by virtue of `purchase_id → purchases.id` — **there is no per-line project/general split anywhere in the schema or code.**

`Purchases::store()` and the shared `_insertItemsAndStock()` (`app/Controllers/Purchases.php`) write **exactly one** `stock_ledger` row per purchase line, always:
```php
'transaction_type' => 'IN', 'source' => 'PROJECT', 'project_id' => $projectId
```
100% of every purchased unit is posted to PROJECT stock, unconditionally. General stock today only originates from (a) manual entries via `Stock::store()` (`reference_type='MANUAL'`), or (b) a later Stock Return moving PROJECT → GENERAL. This is exactly the "inflates project purchase cost" problem the task describes: `Reports::profitLoss()` and `Projects::_buildStatementData()` both compute project cost as `SUM(purchases.total_amount)` — the full header total — with no notion that some of that purchase was never really "for" the project.

---

## 1. Database Impact

**Two viable schema approaches — a design decision, not yet made:**

**A. Split columns on the existing row** — add `project_qty` and `general_qty` (or just `project_qty`, deriving general as `quantity - project_qty`) to `purchase_items`. One row per product line, as today.
- Pro: `purchase_items.id` stability is preserved — no impact on the `stock_return_items.purchase_item_id` FK (RESTRICT) or on `Purchases::_hasLinkedReturns()`.
- Con: `unit_price`/`total`/`gst_amount` on the row still describe the *whole* purchased quantity; project-cost and general-cost derivations must be computed (`unit_price × project_qty` etc.) rather than read directly off a column, everywhere those figures are consumed.

**B. Split into two rows per line** (one project-flagged, one general-flagged), each carrying its own qty and its own share of cost.
- Pro: cost-per-allocation is a stored column, not a derived expression — simpler downstream reads (reports, FIFO).
- Con: **breaks `purchase_item_id` stability** for any existing `stock_return_items` rows if retrofitted onto historical data (old row split into two new rows means the FK target changes identity) — requires an explicit data migration/remap step, not just a schema ALTER. New purchases created after the feature ships aren't affected, but backward compatibility with rows written before the feature is materially harder under this approach.

**Recommendation to bring to design review: Approach A** (columns on the existing row) — it is backward-compatible by construction (`project_qty` can default to `quantity`, i.e. "100% project" for every historical row, exactly matching current behavior with zero data migration), and it keeps every existing FK and guard (`_hasLinkedReturns`, `_allocateFifo`'s join) valid without rewriting historical `purchase_item_id` references.

**Schema change (Approach A), for review:**
```sql
ALTER TABLE purchase_items
  ADD COLUMN project_qty INT NOT NULL DEFAULT 0 AFTER quantity,
  ADD COLUMN general_qty INT NOT NULL DEFAULT 0 AFTER project_qty;
-- Backfill: existing rows get project_qty = quantity, general_qty = 0
-- (preserves current 100%-project behavior for all historical purchases)
```
Note `purchase_items.quantity` is `int`, and `stock_ledger.quantity` is also `int` (unlike `sale_items.quantity`, which is `decimal(15,3)`) — no fractional-quantity handling exists in this part of the schema today, so `project_qty + general_qty = quantity` is an exact-integer invariant, not a rounding concern.

`purchases.project_id` stays as-is (still needed — a purchase without *some* project association has no context; General allocation is a *portion* of a project-linked purchase, not a projectless purchase type, per the task's example).

---

## 2. PurchaseItem / Purchase Controller Changes

`app/Controllers/Purchases.php`:
- `store()` and `_insertItemsAndStock()` (currently lines 89–112 / 241–277): each line's insert must accept `project_qty` from the request (validated `0 ≤ project_qty ≤ quantity`), compute `general_qty = quantity - project_qty`, and write **two** `stock_ledger` rows instead of one whenever `general_qty > 0`:
  ```php
  if ($project_qty > 0) {
      $db->table('stock_ledger')->insert([..., 'source' => 'PROJECT', 'project_id' => $projectId, 'quantity' => $project_qty, ...]);
  }
  if ($general_qty > 0) {
      $db->table('stock_ledger')->insert([..., 'source' => 'GENERAL', 'project_id' => null, 'quantity' => $general_qty, ...]);
  }
  ```
  Both rows keep `reference_type='PURCHASE'`, `reference_id=$purchaseId` — this reuses the existing typing mechanism (`source` enum), no new `reference_type` needed. This directly satisfies Business Rule 2 ("Stock Ledger immediately creates IN PROJECT + IN GENERAL") and Business Rule 3 ("No Stock Return transaction is created") — the split now happens at write-time, not via a subsequent StockReturns record.
- `update($id)`: same two-row logic reused in `_insertItemsAndStock()`, so no separate change needed there — the existing "delete all ledger rows for this purchase, then re-insert" pattern (lines 205–208 + 225) already handles re-splitting cleanly on edit.
- **Validation (Business Rule 6)**: `_hasLinkedReturns()` today only blocks edit/delete when Stock Return rows reference the purchase's items. With immediate GENERAL posting at purchase time, General-side stock can now be **sold** (via `Sales.php`, which draws from GENERAL) before anyone touches the purchase again. Edit/Delete must therefore gain a **new guard**, analogous to `StockReturns::_canDelete()`: before allowing a `project_qty` decrease (which would reduce/remove a `source='PROJECT'` ledger row) or a purchase delete, verify the PROJECT-side quantity currently posted hasn't already been consumed by a project sale, and — separately — that the GENERAL-side quantity hasn't already been sold out of GENERAL. This is a **new check**, not a reuse of `_hasLinkedReturns()`, because the consumption path is now Sales, not Stock Return.

---

## 3. Stock Ledger Changes

No schema change needed — `stock_ledger.source` (`GENERAL`/`PROJECT`) and nullable `project_id` already support exactly this two-row pattern; it's the same mechanism `StockReturns::store()` already uses for its OUT-PROJECT/IN-GENERAL pair (`app/Controllers/StockReturns.php` lines ~145–178), just applied at purchase time with two **IN** rows instead of a paired OUT/IN.

`StockLedgerModel::getAvailableStock()` (the canonical availability query) needs no change — it already aggregates by `source`/`project_id` and will simply see GENERAL balances appear sooner (at purchase time) rather than only after a return. **However**, per the earlier architectural map, this aggregation is independently re-implemented (not shared) in at least 4 other places: `Sales.php::_getAvailableStock()`, `app/Helpers/stock_helper.php::getStock()`, and the `Reports::stock()`/`stockExport()`/`stockPdf()` family. None of these require logic changes for this feature (they read `stock_ledger`, which now just has more/different rows), but each is a place the design review should spot-check post-implementation, since they don't share code with the canonical method and a future ledger-shape drift could desync them.

---

## 4. Project Profit / Financial Calculation Changes

This is the **core of the feature's value** and the largest set of touch points, since project cost today is purely `SUM(purchases.total_amount)` — the full header, ignoring allocation entirely:

- `Projects::view()` (`app/Controllers/Projects.php` ~154–174): `total_purchases = array_sum(purchases.total_amount)` → must become `SUM(purchase_items.unit_price × project_qty [+ its GST share])` filtered to the project, i.e. cost derived from allocated quantity, not the line/header total.
- `Projects::_buildStatementData()` (~404–451): the same `SELECT SUM(total_amount) FROM purchases WHERE project_id=?` subquery needs the equivalent line-level, allocation-weighted replacement.
- `Reports::profitLoss()` / `profitLossExport()` / `profitLossPdf()` (`app/Controllers/Reports.php` lines 24–478, including the `project_breakdown` sub-query at 56–67): "COGS" is currently `SUM(purchases.total_amount)`; must switch to an allocation-aware sum, or every project with a General-allocated purchase continues to overstate its cost exactly as before — i.e. **implementing the ledger/UI split without updating this query leaves the original business problem unsolved.**
- `ProjectModel::getTimelineEvents()` (46–218): the Purchase timeline row (149–162) currently shows the full `purchases.total_amount` per purchase event; should be reconsidered to show the project-allocated portion (or annotate both) so the project timeline doesn't visually imply 100% project cost for a mixed purchase.

**Design question for review, not yet resolved:** should the allocated cost be computed as a simple pro-rata split of the line's `total_with_gst` by `project_qty/quantity`, or should GST be recalculated per split (since GST is normally per-line, not per-unit, and splitting a taxed line into two allocations could introduce rounding drift versus the original invoice total)? This needs an explicit decision before implementation — it affects both the `purchase_items` cost-read formula everywhere above and whether `purchases.total_amount` (Business Rule 1: "still stores the supplier invoice total") stays exactly reconcilable to `SUM(purchase_items.total_with_gst)` after allocation.

---

## 5. Reports Affected

| Report | Current behavior | Impact |
|---|---|---|
| `profitLoss` / Export / Pdf | COGS = `SUM(purchases.total_amount)` by project | **Must change** — see §4 |
| `stock` / Export / Pdf | Groups `stock_ledger` by `source` | No code change — automatically reflects more/earlier GENERAL rows |
| `ledger` / Export / Pdf | Raw dump of `stock_ledger` rows | Cosmetic change only — a purchase now yields up to 2 rows instead of 1; worth flagging to report consumers |
| `purchases` / Export / Pdf | Lists purchase headers; **export hard-codes `'Source' => 'PROJECT'`** (line ~1344) | **Must change** — this literal becomes misleading the moment a purchase can be General-allocated; needs to reflect actual per-line split |
| `getProductsByProject` | Reads `stock_ledger` | No change — same reasoning as `stock()` |
| `sales` family | Reads `sales`/`sale_items` | Unaffected |

---

## 6. Edit/Delete Validation Changes

Current guard, `Purchases::_hasLinkedReturns()` (lines 326–335), only checks whether any `stock_return_items` row references one of this purchase's `purchase_items`. Per Business Rule 6 ("Purchase Edit/Delete must validate allocated quantities exactly like current stock logic"), this needs to be **extended, not replaced**, with a consumption check mirroring `StockReturns::_canDelete()`'s pattern (checking current `getAvailableStock()` balance against the quantity being removed):

- On **decreasing** `project_qty` for a line (or deleting the purchase outright): verify the PROJECT-side balance for that product/project (`getAvailableStock($productId, 'PROJECT', $projectId)`) is still ≥ the quantity being removed — i.e. no project sale has already drawn on it.
- On **decreasing** `general_qty` (or deleting): verify the GENERAL-side balance (`getAvailableStock($productId, 'GENERAL')`) is still ≥ the quantity being removed — i.e. no sale from GENERAL, and no *other* purchase's General allocation, has already been drawn down below it.
- The existing Stock-Return linkage check stays **in addition** to this, unchanged, since a historical return against this purchase's items is still a valid reason to block edit/delete.

This is new logic, not a modification of existing logic — `_hasLinkedReturns()` remains as-is; a new sibling check is added alongside it.

---

## 7. FIFO Compatibility

The **only** FIFO logic in the codebase is `StockReturns::_allocateFifo()` (`app/Controllers/StockReturns.php` lines 261–292), and it is **directly impacted**:

```sql
SELECT pi.id, pi.quantity,
    pi.quantity - COALESCE((SELECT SUM(sri.quantity) FROM stock_return_items sri WHERE sri.purchase_item_id = pi.id), 0) AS remaining
FROM purchase_items pi
INNER JOIN purchases pu ON pi.purchase_id = pu.id
WHERE pu.project_id = ? AND pi.product_id = ?
ORDER BY pu.purchase_date ASC, pi.id ASC
```

This query currently assumes **the entire `pi.quantity` of every line is PROJECT stock**, ordered oldest-first, for a return-driven consumption. Once a line can be split, this assumption is wrong on two counts:
1. `pi.quantity` must become `pi.project_qty` in both the SELECT and the "remaining" subtraction — a return should only ever be able to draw against the *project-allocated* portion of a line, never the General portion (which was never PROJECT stock to begin with).
2. The existing `WHERE pu.project_id = ?` (header-level filter) still works fine under schema Approach A, since `project_id` remains on the purchase header — no change needed there specifically.

**This query must be updated as part of this feature**, even though Stock Return itself is "unchanged" from a user/workflow perspective (Business Rule 7) — its internal FIFO math silently breaks (over-allocates returns beyond what was actually posted to PROJECT) if `purchase_items.quantity` keeps meaning "100% project" while the ledger now says otherwise. This is the single most important cross-cutting correctness risk in the whole feature and should be called out explicitly as an implementation task, not treated as "no change" just because Stock Return's UI/workflow doesn't change.

No FIFO cost-layer logic exists for Sales COGS anywhere (Sales only checks aggregate `stock_ledger` IN−OUT balance, never per-unit cost) — so this feature does **not** need to touch any sales-side FIFO logic, because none exists.

---

## 8. Backward Compatibility with Existing Purchases

Under schema Approach A (recommended): backfilling `project_qty = quantity`, `general_qty = 0` on every existing `purchase_items` row exactly reproduces current behavior (100% project) for all historical data — no re-derivation of past `stock_ledger` rows is needed, since those rows were already written as `source='PROJECT'` for the full quantity, which stays consistent with `project_qty = quantity`. `_allocateFifo()`'s updated query (`pi.project_qty` instead of `pi.quantity`) continues to return the correct "remaining" value for old rows since `project_qty` backfills to the same number `quantity` already held.

Under schema Approach B (row-split): backward compatibility requires an explicit remap of `stock_return_items.purchase_item_id` for every historical row that gets split, since the FK target's identity would change — this is a materially larger, riskier migration and is the main reason Approach A is recommended above.

Either way: `purchases.total_amount` (the supplier invoice total, Business Rule 1) is unaffected by this feature and requires no backfill — it already reflects the full purchase regardless of downstream allocation, consistent with "Purchase header still stores the supplier invoice total."

---

## Summary — Design Decisions Needed Before Implementation

1. **Schema approach**: Approach A (qty-split columns on existing row) recommended over Approach B (row split), primarily for backward-compatibility and FK-stability reasons (§1, §8).
2. **Cost-allocation formula**: pro-rata split of line total by qty, vs. per-split GST recalculation (§4) — affects rounding/reconciliation against `purchases.total_amount`.
3. **New edit/delete consumption guard** (§6) needs to be scoped precisely — mirrors `StockReturns::_canDelete()` but is new code, not a reuse.
4. **`_allocateFifo()` must be updated** to key off `project_qty` instead of `quantity` (§7) — required for correctness, not optional, despite Stock Return's user-facing workflow being unchanged.
5. **Reports**: `profitLoss` family and the `purchases` export's hard-coded `'PROJECT'` literal (§5) must change or the feature's stated goal (accurate project profit) isn't actually achieved even after the ledger/UI work is done.

**No implementation has been started.** This document is for design review; awaiting approval before any code is written.
