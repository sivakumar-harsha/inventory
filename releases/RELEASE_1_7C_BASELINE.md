# Release 1.7C — Baseline: Stock Return Module Removed

**Release:** 1.7C
**Type:** Safe removal (navigation, controller, view, model, database)
**Depends on:** Release 1.6B (Purchase Allocation), which makes Stock Return unnecessary for the normal workflow.

---

## 1. What changed

The Stock Return module (added in Release 1.6B, UI-repositioned in Release 1.7A) has been fully removed. Purchase Allocation — splitting a purchase line into Project Qty / General Qty at the time of purchase — is now the only supported allocation path. There is no in-app way to move stock from Project to General after the fact.

See [RELEASE_1_7C_DEPENDENCY_ANALYSIS.md](RELEASE_1_7C_DEPENDENCY_ANALYSIS.md) for the full pre-removal reference inventory this release was scoped against.

### Removed outright

| Item | Detail |
|---|---|
| Controller | `app/Controllers/StockReturns.php` |
| Model | `app/Models/StockReturnModel.php` |
| Views | `app/Views/stock_returns/index.php`, `create.php`, `view.php` |
| Routes | `stock-returns`, `stock-returns/create`, `stock-returns/store`, `stock-returns/view/(:num)`, `stock-returns/delete/(:num)`, `stock-returns/get-products/(:num)` |
| Database tables | `stock_returns`, `stock_return_items` |
| Database enum value | `stock_ledger.reference_type` narrowed from `('PURCHASE','SALE','MANUAL','RETURN')` to `('PURCHASE','SALE','MANUAL')` |

### Edited (references removed, surrounding logic untouched)

| File | What was removed |
|---|---|
| `app/Controllers/Projects.php` | `view()`'s `$data['returns']` query against `stock_returns`/`stock_return_items` |
| `app/Views/projects/view.php` | "Stock Returns" button (header) and the "Returns" card (date/qty/notes/view-link table) |
| `app/Models/ProjectModel.php` | `getTimelineEvents()`'s Stock Return query + event-push block; docblock updated to drop the Stock Returns mention |
| `app/Views/projects/statement.php` | `'Stock Return'` icon entry, `Inventory` branch of the "Project Cost Only" badge condition (now `Cost` only), `Inventory` option in the Category filter, dead `.category-inventory` CSS rule |
| `app/Controllers/Purchases.php` | `_hasLinkedReturns()` guard method and its two call sites (in `update()` and `delete()`), plus the two comments referencing `StockReturns::_canDelete()` |

### Unchanged (confirmed, not touched)

- Purchase Allocation math: `_validateAllocation()`, `_insertItemsAndStock()`, the `project_qty`/`general_qty` split, GST calculation (`gst_calculate_line()`).
- Purchase's other guard, `_checkAllocationConsumed()` — blocks edit/delete when PROJECT-side or GENERAL-side stock has already been sold. This is a *different* guard from the removed Stock Return one and stays exactly as it was.
- `StockLedgerModel::getAvailableStock()` and `app/Helpers/stock_helper.php` — both were already generic (sum by `transaction_type`/`source`), with no `reference_type`-specific branching to remove.
- Sales, Payments, GST, Project Profit (`net_profit` calc), and Project Statement financial figures (`ProjectModel::getFinancialSummary()`, `getAllocatedPurchaseCost()`, `getPaymentStatus()`).
- Reports/exports (`Reports.php`, `reports/ledger.php`) — these render `reference_type` generically; there was no RETURN-specific branch to remove.

---

## 2. Navigation after this release

- Sidebar: no Stock Returns entry (already removed in Release 1.7A).
- Projects → View: no Stock Returns button (removed this release).
- No route in the application resolves to a Stock Return screen; visiting `/stock-returns/*` now 404s.

## 3. Business workflow after this release

```
Purchase Create/Edit
        │
        ▼
Purchased Qty ──▶ Project Qty  (goes to PROJECT stock for the selected project)
        │
        └────────▶ General Qty (goes to GENERAL/warehouse stock)
```

This is the **only** allocation mechanism. There is no post-purchase "move Project stock back to General" workflow in the application anymore. If a correction is needed, it must be handled by editing/deleting the purchase (subject to the existing `_checkAllocationConsumed()` guard) or via a manual stock entry (`Stock::store()`, `reference_type = 'MANUAL'`), neither of which is new — both existed before this release.

## 4. Database migration

`app/Database/Migrations/2026-08-28-000001_RemoveStockReturns.php`:

- `up()`: deletes any `stock_ledger` rows with `reference_type = 'RETURN'`, drops `stock_return_items` then `stock_returns`, narrows the `reference_type` enum.
- `down()`: re-widens the enum and recreates both tables with their original columns/keys/foreign keys. **Schema-reversible only** — dropped table data and purged ledger rows cannot be restored by `down()`, since the removal is inherently destructive to that data.
- Applied and verified against the live database (`aandainventory_db`): `SHOW TABLES LIKE 'stock_return%'` returns zero rows; `SHOW COLUMNS FROM stock_ledger LIKE 'reference_type'` shows `enum('PURCHASE','SALE','MANUAL')`.

## 5. Regression baseline

No changes were made to:
- Purchase allocation calculations or validation.
- Sales, Payments, or GST calculations.
- Project financial summary or profit calculations.
- Stock ledger IN/OUT arithmetic (`StockLedgerModel`, `stock_helper.php`).
- Report/export generation logic (Profit & Loss, Stock, Sales, Purchases, Ledger).
- Dashboard.

All 32 acceptance checks in [RELEASE_1_7C_ACCEPTANCE_TEST.md](RELEASE_1_7C_ACCEPTANCE_TEST.md) passed against the live database and codebase.
