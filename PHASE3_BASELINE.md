# Phase 3 Baseline — Project Stock Return

Status: Implemented (3C), acceptance-tested (3D), documented (3E — this file).
Scope: Adds a "Project → General Stock Return" feature. No other workflow was altered in behavior.

---

## 1. Files Modified

| File | Type | Change |
|---|---|---|
| `app/Database/Migrations/2026-08-27-000002_CreateStockReturns.php` | New | Creates `stock_returns`, `stock_return_items`; extends `stock_ledger.reference_type` enum with `RETURN` |
| `app/Models/StockReturnModel.php` | New | Header model for `stock_returns` |
| `app/Controllers/StockReturns.php` | New | Full controller: index, create, getProducts (AJAX), store, view, delete |
| `app/Views/stock_returns/index.php` | New | List view (DataTable) |
| `app/Views/stock_returns/create.php` | New | Create form (product-based, AJAX-driven) |
| `app/Views/stock_returns/view.php` | New | Read-only detail view, conditional delete |
| `app/Config/Routes.php` | Modified | Added 6 routes for Stock Returns (see §3) |
| `app/Views/layouts/main.php` | Modified | Added sidebar nav link "Stock Returns" under Transactions |
| `app/Controllers/Purchases.php` | Modified | Added `_hasLinkedReturns()` guard, called from `update()` and `delete()` |
| `app/Controllers/Projects.php` | Modified | Added `$data['returns']` query in `view()` |
| `app/Views/projects/view.php` | Modified | Added "Returns" card in Project View |

No other controller, model, view, or route file was touched.

---

## 2. New Tables

### `stock_returns` (header)
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| project_id | INT, FK → `projects.id` | No `ON DELETE` clause (RESTRICT), matches `sales.project_id` convention |
| return_date | DATE | |
| notes | TEXT NULL | |
| created_at / updated_at | TIMESTAMP | `updated_at` has `ON UPDATE CURRENT_TIMESTAMP` |

### `stock_return_items` (lines)
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| stock_return_id | INT, FK → `stock_returns.id` | `ON DELETE CASCADE` |
| product_id | INT, FK → `products.id` | |
| purchase_item_id | INT, FK → `purchase_items.id` | RESTRICT (deliberate — see §7); preserves provenance to the original purchase line |
| quantity | INT | Matches live `purchase_items.quantity` / `stock_ledger.quantity` type (INT, not DECIMAL) |
| created_at / updated_at | TIMESTAMP | |

### Altered: `stock_ledger`
`reference_type` enum extended: `ENUM('PURCHASE','SALE','MANUAL','RETURN')` (was missing `RETURN`; `MANUAL` already existed live but was undocumented in `erp_database.sql`).

Item tables (`stock_return_items`, like `purchase_items`/`sale_items`) intentionally have **no dedicated Model class** — accessed via raw `$db->table()`, consistent with existing codebase convention that only header tables get Models.

---

## 3. New Routes

```
GET  stock-returns                        → StockReturns::index
GET  stock-returns/create                 → StockReturns::create
POST stock-returns/store                  → StockReturns::store
GET  stock-returns/view/(:num)            → StockReturns::view/$1
GET  stock-returns/delete/(:num)          → StockReturns::delete/$1
GET  stock-returns/get-products/(:num)    → StockReturns::getProducts/$1   (AJAX)
```

All gated by `['filter' => 'auth']`, same as every other protected route. **No `edit`/`update` route exists** — intentional (see §8, immutability).

---

## 4. New Controller / Model / View List

- **Controller:** `App\Controllers\StockReturns` — methods `index()`, `create()`, `getProducts($projectId)`, `store()`, `view($id)`, `delete($id)`, private `_allocateFifo()`, private `_canDelete()`.
- **Model:** `App\Models\StockReturnModel` (header only, standard CI4 Model — `allowedFields`: `project_id`, `return_date`, `notes`).
- **Views:** `stock_returns/index.php`, `stock_returns/create.php`, `stock_returns/view.php`.

---

## 5. FIFO Allocation Rules

The UI is product-based — a user picks a product and a quantity, never a specific purchase line. Internally, `StockReturns::store()` explodes each (product, quantity) line into one or more `stock_return_items` rows via a private `_allocateFifo()` walk:

1. Fetch all `purchase_items` for that product within that project, ordered by `purchases.purchase_date ASC` (oldest first).
2. For each purchase item, compute remaining returnable quantity:
   `remaining = purchase_items.quantity − SUM(existing stock_return_items.quantity WHERE purchase_item_id = that item)`
3. Consume from the oldest item first; if it can't fully cover the requested quantity, spill the remainder into the next-oldest item, and so on.
4. Each consumed slice becomes its own `stock_return_items` row, carrying its own `purchase_item_id`.
5. If total available across all purchase items is insufficient, the whole return is rejected before any writes occur (`\RuntimeException`, caught pre-transaction).

This allocation is computed **before** the database transaction opens (two-phase validate-then-write), so a bad line never leaves partial state.

---

## 6. Stock Ledger RETURN Reference Flow

Follows the same header/line-item convention already used by Purchases and Sales: **all ledger rows generated by one return transaction share a single `reference_id` = the `stock_returns.id` header row**, regardless of how many `stock_return_items` (FIFO-split or multi-product) it produced.

For every `stock_return_items` allocation, two paired ledger rows are written:

| transaction_type | source | project_id | reference_type | reference_id |
|---|---|---|---|---|
| OUT | PROJECT | the return's project_id | RETURN | stock_returns.id |
| IN | GENERAL | NULL | RETURN | stock_returns.id |

This is what makes returns automatically visible in the Stock Ledger Report and Stock Report with no reporting code changes — reports already aggregate `stock_ledger` by `reference_type`/`source`, and simply gained a new enum value to display.

The existing `StockLedgerModel::getAvailableStock($productId, 'PROJECT', $projectId)` was reused unmodified as the "Available Return Qty" formula: since `PURCHASE` is the only reference type inserting PROJECT `IN` rows, and `SALE`/`RETURN` are the only ones inserting PROJECT `OUT` rows, this already equals `Purchased − Sold From Project − Already Returned` — no new aggregation SQL was needed.

---

## 7. Purchase Protection Rules

Once any `purchase_items` row belonging to a purchase has a `stock_return_items` row allocated against it (via `purchase_item_id`), that purchase becomes locked:

- `Purchases::update($id)` — blocked, redirects back with: *"Cannot modify this purchase — stock from it has already been returned. Delete the related stock return(s) first."*
- `Purchases::delete($id)` — blocked with the equivalent message.

Enforced by a new private helper:
```php
private function _hasLinkedReturns(BaseConnection $db, int $purchaseId): bool
```
which joins `stock_return_items` → `purchase_items` on `purchase_item_id` and checks for any row belonging to the purchase.

This app-level check is backed by a DB-level safety net: `stock_return_items.purchase_item_id` is a `RESTRICT` (not cascade) FK to `purchase_items.id`, so even if the app-level check were ever bypassed, MySQL itself would refuse a delete that orphans the link — surfacing as a friendly redirect instead of only a raw constraint error.

Acceptance-verified in Phase 3D (Test C2/C3): an edit attempt left `invoice_no` unchanged; a delete attempt left the purchase and its item intact.

---

## 8. Delete Rules

Stock returns are **create-and-delete only** — there is no edit/update path at all (no route, no controller method, no view).

A return is deletable only when, for **every** item in it, the current GENERAL balance for that product is still ≥ the quantity that item returned — i.e., none of what came back into GENERAL stock from this return has since been sold out of GENERAL. This is **all-or-nothing across the whole return** (checked by private `_canDelete()`), not per line.

- If deletable: `StockReturns::delete($id)` runs one transaction that removes the paired `stock_ledger` rows, then the `stock_return_items`, then the `stock_returns` header — fully reversing the return.
- If not deletable: the delete route redirects with an error, and the view page (`stock_returns/view.php`) proactively hides the Delete button in favor of a lock-icon warning showing the block reason — but the guard is enforced server-side regardless of UI state (verified in 3D Test C5 by calling the delete route directly).

A blocked correction requires deleting the return (once eligible) and creating a new one — there is no in-place edit, by design (rule 5 of Phase 3C).

---

## 9. Completed Project Exception

Per approved rule 6, returns are allowed against `COMPLETED` projects (unlike Purchases and Sales, which both reject `project.status === 'COMPLETED'` server-side).

- `StockReturns::create()` loads **all** projects (no `WHERE status = 'ACTIVE'` filter), unlike `Purchases::create()`/`Sales::create()` which filter to `ACTIVE` only.
- The create-form project dropdown labels completed projects `"(Completed)"` so the distinction is visible but not blocking.
- `StockReturns::store()` performs no project-status check at all — a return against a `COMPLETED` project is accepted unconditionally (subject to the normal available-quantity validation).

---

## 10. Rollback / Migration Steps

Migration file: `app/Database/Migrations/2026-08-27-000002_CreateStockReturns.php`

**Forward** (`up()`): creates `stock_returns`, creates `stock_return_items` with its three FKs, alters `stock_ledger.reference_type` to add `'RETURN'`.

**Rollback** (`down()`) — run via `php spark migrate:rollback`:
1. Reverts `stock_ledger.reference_type` back to `ENUM('PURCHASE','SALE','MANUAL')`.
   ⚠️ This step will fail if any `stock_ledger` row still has `reference_type = 'RETURN'` — delete all Stock Return records through the app (or truncate `stock_return_items`/`stock_returns`/the `RETURN` ledger rows manually) before rolling back.
2. Drops `stock_return_items` (drops its FKs first).
3. Drops `stock_returns`.

No data outside these two new tables and the one enum column is touched by rollback — `purchases`, `purchase_items`, `sales`, `sale_items`, and their existing ledger rows are untouched in both directions.

---

## 11. Regression Baseline (confirmed unaffected)

Verified in Phase 3D via direct HTTP + SQL testing against a live dataset (2 projects, 3 GST products, 4 purchases, 2 sales, 4 returns):

| Area | Status | Basis |
|---|---|---|
| Sales workflow | Unaffected | `Sales.php` not modified; sale create/store/view exercised in 3D (project sale + general sale), both worked exactly as before |
| Purchases workflow | Unaffected except new guard | Only additive change is the `_hasLinkedReturns()` block on `update()`/`delete()` — normal create/view/edit/delete of a purchase with **no** linked return is untouched (Purchase #27–29 had returns against them and were correctly locked; any purchase without returns is unaffected by this code path) |
| Payments | Unaffected | `Payments.php` not modified, not referenced by any Phase 3 code |
| Reports | Unaffected except automatic RETURN visibility | `Reports.php` not modified; Stock, Ledger, Sales, Purchases reports all returned HTTP 200 with correct figures in 3D; RETURN rows appear in the Ledger report purely because they're `stock_ledger` rows with a new enum value, not because report code changed |
| Dashboard | Unaffected | `Dashboard.php` not modified; loaded with HTTP 200, no error markers in 3D |
| Stock Entry (manual stock adjustments) | Unaffected | `Stock.php` not modified, not referenced by any Phase 3 code |
| GST logic | Unaffected | `gst_calculate_line()`/`gst_summarize_items()` not touched; Stock Returns don't compute or store GST at all |

**Files confirmed NOT modified in Phase 3:** `Sales.php`, `Payments.php`, `Reports.php`, `Dashboard.php`, `Stock.php`, `SaleModel.php`, all Sales/Payments/Reports/Dashboard/Stock views, GST helper functions.
