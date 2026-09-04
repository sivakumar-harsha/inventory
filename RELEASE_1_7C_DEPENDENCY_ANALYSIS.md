# Release 1.7C — Dependency Analysis (Read-Only)

**Purpose:** Enumerate every reference to the Stock Return module before any removal work begins, so Phase B touches exactly these points and nothing else.

**Method:** Repo-wide grep for `stock_return`, `StockReturn`, `stock-return`, and `RETURN` (scoped to `app/`), followed by manual read of every match.

---

## 1. Routes — `app/Config/Routes.php`

Lines 85–91, six routes, all under `stock-returns`:

```
$routes->get('stock-returns', 'StockReturns::index', ...);
$routes->get('stock-returns/create', 'StockReturns::create', ...);
$routes->post('stock-returns/store', 'StockReturns::store', ...);
$routes->get('stock-returns/view/(:num)', 'StockReturns::view/$1', ...);
$routes->get('stock-returns/delete/(:num)', 'StockReturns::delete/$1', ...);
$routes->get('stock-returns/get-products/(:num)', 'StockReturns::getProducts/$1', ...);
```

**Action:** remove all six.

---

## 2. Sidebar / Menu — `app/Views/layouts/main.php`

Already removed in Release 1.7A (the sidebar `Stock Returns` link was pulled from the TRANSACTIONS section). Grep confirms no remaining reference in this file.

**Action:** none required.

---

## 3. Projects → View button — `app/Views/projects/view.php`

- Line 8: `<a href="stock-returns/create?project_id=...">Stock Returns</a>` button (added 1.7A).
- Lines 186–205: a "Returns" card listing `$returns` (return date, qty, notes, link to `stock-returns/view/...`), guarded by `if (!empty($returns))`.

**Action:** remove the button and the Returns card.

---

## 4. Projects controller — `app/Controllers/Projects.php`

`view()` method, lines 181–186: builds `$data['returns']` via a query against `stock_returns` / `stock_return_items`, feeding the card in #3.

**Action:** remove this query block (the `$data['returns']` assignment). `total_sales`, `total_purchases` (via `getAllocatedPurchaseCost()`), `total_expenses`, `net_profit` are untouched — none of them read `$data['returns']`.

---

## 5. Project Statement timeline — `app/Models/ProjectModel.php`

`getTimelineEvents()`, lines 280–300: queries `stock_returns` / `stock_return_items` / `products`, pushes a `'Stock Return'` event (`category => 'Inventory'`) into the merged timeline.

The running-balance loop (lines 304–316) already treats `Inventory` as a non-balance category (`running_balance = null`), the same as `Cost` — removing the Stock Return block does not touch the Billing/Payment running-balance arithmetic.

**Action:** remove the `$returns` query and its `foreach` push block. No change to the Advance / Sales Invoice / Invoice Payment / Purchase / Expense blocks or the running-balance loop.

---

## 6. Project Statement view — `app/Views/projects/statement.php`

- `$typeIcons` array: `'Stock Return' => 'bi-arrow-return-left'` entry (Release 1.7A).
- Running-balance cell: `if ($ev['category'] === 'Cost' || $ev['category'] === 'Inventory')` → renders the "Project Cost Only" badge for both Purchase/Expense (`Cost`) and Stock Return (`Inventory`).
- `.category-inventory` CSS pill class (used only by the now-removed `Inventory` category).

**Action:** drop the `'Stock Return'` icon entry, narrow the badge condition to `'Cost'` only, remove the now-dead `.category-inventory` rule. Purchase/Expense "Project Cost Only" badge behavior is unchanged.

---

## 7. Purchase guards — `app/Controllers/Purchases.php`

Two separate guard mechanisms exist; only one is in scope:

- **`_hasLinkedReturns()`** (lines 407–416) — queries `stock_return_items` joined to `purchase_items`, blocks purchase **edit** (line 158) and **delete** (line 386) when a return references the purchase. **This is the Stock Return guard — in scope for removal**, along with its two call sites and the related comments (lines 316, 403).
- **`_checkAllocationConsumed()`** (lines 318–353) — checks `stock_ledger` via `StockLedgerModel::getAvailableStock()` for both `PROJECT` and `GENERAL` sources, independent of Stock Return. **Out of scope — this is the "Project stock sold" / "General stock sold" guard the release notes say to keep.**

**Action:** remove `_hasLinkedReturns()` and its two call sites only. `_checkAllocationConsumed()`, `_validateAllocation()`, and all allocation math are untouched.

---

## 8. Models

- `app/Models/StockReturnModel.php` — thin CI4 `Model` wrapping `stock_returns`. Not referenced by any other model or by `StockReturns` controller's raw-SQL paths (the controller uses `$db->table()`/`$db->query()` directly, not this model's methods) — safe to delete outright.
- `app/Models/ProjectModel.php` — see #5.
- `app/Models/StockLedgerModel.php` — **no** Stock-Return-specific logic. `getAvailableStock()` sums generically by `transaction_type`/`source`, with no `reference_type` filter. **No change.**
- `app/Helpers/stock_helper.php` — same: generic `getStock()` helper, no `reference_type` filter. **No change.**

---

## 9. AJAX endpoints

- `stock-returns/get-products/(:num)` → `StockReturns::getProducts()` — removed with the controller/routes (#1, #8).
- No other controller calls this endpoint.

---

## 10. Reports / Stock Ledger display — `app/Controllers/Reports.php`, `app/Views/reports/ledger.php`, `app/Controllers/Stock.php`

- `Reports.php` line 1740 and `reports/ledger.php` line 160 both render `reference_type . ' #' . reference_id` **generically** — there is no `RETURN`-specific branch, filter, or label to remove. Once no new `RETURN` rows can be created (module removed) and historical ones are cleaned up (#12), these rows simply stop appearing — no code change required here.
- `Stock.php` (manual stock entry) only ever writes `reference_type = 'MANUAL'` — unrelated.

**Action:** none required in Reports/Stock/ledger view code.

---

## 11. Dashboard — `app/Controllers/Dashboard.php`

No references found.

**Action:** none required.

---

## 12. Database

Tables/constraints created by `2026-08-27-000002_CreateStockReturns.php`:

- `stock_returns` (PK `id`, FK `project_id → projects.id`)
- `stock_return_items` (PK `id`, FK `stock_return_id → stock_returns.id` ON DELETE CASCADE, FK `product_id → products.id`, FK `purchase_item_id → purchase_items.id`)
- `stock_ledger.reference_type` ENUM widened from `('PURCHASE','SALE','MANUAL')` to `('PURCHASE','SALE','MANUAL','RETURN')`

Historical `stock_ledger` rows may carry `reference_type = 'RETURN'` (one IN/OUT pair per returned line, written by `StockReturns::store()`) — see Phase B §7 for cleanup ordering (data delete before enum narrowing).

**Action (Phase B, migration):** drop both tables, delete any `stock_ledger` rows with `reference_type = 'RETURN'`, narrow the enum back to `('PURCHASE','SALE','MANUAL')`. Migration `down()` reverses all three.

---

## 13. Views/Controllers deleted outright

- `app/Controllers/StockReturns.php`
- `app/Models/StockReturnModel.php`
- `app/Views/stock_returns/index.php`
- `app/Views/stock_returns/create.php`
- `app/Views/stock_returns/view.php`

---

## Summary — files touched in Phase B

| File | Action |
|---|---|
| `app/Config/Routes.php` | Remove 6 stock-returns routes |
| `app/Controllers/StockReturns.php` | Delete |
| `app/Models/StockReturnModel.php` | Delete |
| `app/Views/stock_returns/*` (3 files) | Delete directory |
| `app/Controllers/Projects.php` | Remove `$data['returns']` query in `view()` |
| `app/Views/projects/view.php` | Remove Stock Returns button + Returns card |
| `app/Models/ProjectModel.php` | Remove Stock Return block from `getTimelineEvents()` |
| `app/Views/projects/statement.php` | Remove Stock Return icon entry, narrow cost-only badge condition, drop dead `.category-inventory` CSS |
| `app/Controllers/Purchases.php` | Remove `_hasLinkedReturns()` + its 2 call sites + related comments |
| `app/Database/Migrations/2026-08-28-XXXXXX_RemoveStockReturns.php` | New reversible migration: drop tables, purge `RETURN` ledger rows, narrow enum |

**Confirmed out of scope / untouched:** purchase allocation math (`_validateAllocation`, `_insertItemsAndStock`, `project_qty`/`general_qty` split), `_checkAllocationConsumed()` (project/general stock-sold guards), `StockLedgerModel`, `stock_helper.php`, GST calculations, `SaleModel`/payments/FIFO billing logic, `ProjectModel::getFinancialSummary()`/`getAllocatedPurchaseCost*()`, Reports controllers/exports (generic, no RETURN-specific code), Dashboard.
