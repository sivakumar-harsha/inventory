# Release 4.8.1 Architecture Analysis
## General Purchase & Warehouse Stock — Analysis & Design Only (No Code Changes)

**Project:** A&A Inventory ERP (CodeIgniter 4 / PHP 8.x / Bootstrap 5 + AdminLTE 3.1)
**Status:** Architecture & planning document. No source files, migrations, controllers, models, routes, or views were created or modified to produce this document.

---

## 0. Executive Summary — What Already Exists

Before designing anything new, it's important to flag a discovery from the codebase audit: **this ERP already has a partial "General vs Project" split baked into the existing Purchase → Stock pipeline**, introduced in an earlier release (comments in code reference "Release 1.6B Approved Design"). Specifically:

- `purchase_items` already has `project_qty` and `general_qty` columns per line. A single Project Purchase can allocate part of a line's quantity to the project and the rest to general/warehouse stock.
- `stock_ledger` already has a `source` ENUM('GENERAL','PROJECT') column, plus `project_id`, `reference_type` ENUM('PURCHASE','SALE','MANUAL'), and `reference_id`.
- `StockLedgerModel::getAvailableStock($productId, $source, $projectId)` already computes IN−OUT balance filtered by source.
- A `Stock` controller/module (`stock-entry` routes) already does **manual GENERAL stock IN** entries (source hardcoded to `GENERAL`, `reference_type = 'MANUAL'`).
- `Sales` controller already supports selling from `GENERAL` or `PROJECT` stock per line item (and a `MIXED` sale header when a single invoice draws from both), decrementing `stock_ledger` accordingly.
- `ProjectModel::getAllocatedPurchaseCost()` already excludes `general_qty` from project cost — i.e., **project cost already only reflects the project-allocated share of a purchase**, not the full invoice.

**Implication for 4.8.1:** the warehouse-stock *mechanism* (ledger with GENERAL source, availability queries, cost exclusion) is not new — it already exists and is exercised today by Project Purchase's leftover "general_qty" and by manual Stock entries. What is genuinely new in this release is:

1. A **purchase document that is not required to have a project_id** (today `purchases.project_id`, while nullable in the schema, is enforced as mandatory in `Purchases::store()`/`update()` application logic, and every purchase currently belongs to exactly one project with an optional general remainder).
2. A **dedicated General Purchase entity** (separate table, separate numbering, separate list/create/edit/view UI) so Project Purchase's screens, validations, and edit/delete "already consumed" guards are never touched.
3. A **Warehouse Issue** transaction — moving stock from GENERAL to a specific PROJECT *without* it being a Sale — which does not exist today. Today, GENERAL stock only ever leaves via `Sales` (a revenue transaction) or via a Project Purchase's `general_qty` (only ever an IN). There is no existing "internal transfer to project, recognized as project cost, not billed to customer" flow.
4. **Supplier outstanding / advance payment tracking**, which does not exist anywhere in the current schema — `purchases` has no payment fields at all today (Release 4.8.2 will build the payment screen, but 4.8.1 must reserve the header fields).

This changes the safest architecture: rather than retrofitting `purchases`/`purchase_items` (which are already load-bearing for Project Purchase, its edit/delete "consumed" guard, and project cost timelines), **General Purchase should be a fully separate table set**, deliberately parallel in shape to `purchases`/`purchase_items`/`stock_ledger`, reusing the same GST helper, Supplier Master, and Product Master — never sharing rows.

---

## 1. Existing Module Analysis

### 1.1 Project Purchase

| Concern | File | Notes |
|---|---|---|
| Controller | [app/Controllers/Purchases.php](app/Controllers/Purchases.php) | `index`, `create`, `store`, `edit`, `update`, `view`, `delete`. All CRUD is raw query-builder (`$db->table(...)`), not `PurchaseModel::insert()`. |
| Model (header) | [app/Models/PurchaseModel.php](app/Models/PurchaseModel.php) | Thin CodeIgniter Model; `allowedFields`: supplier_id, project_id, purchase_date, invoice_no, total_amount, notes. Not actually used for inserts (controller writes via `$db->table()` directly) — used elsewhere for `find()`/reads. |
| Model (lines) | *(no dedicated model)* | `purchase_items` is only ever touched via raw `$db->table('purchase_items')` in `Purchases.php`. No `PurchaseItemModel` exists. |
| Stock posting | `Purchases::_insertItemsAndStock()` | Private method; splits each line's `quantity` into `project_qty` + `general_qty`, writes one `purchase_items` row and up to two `stock_ledger` rows (`source='PROJECT'` and/or `source='GENERAL'`) per line. |
| GST | [app/Helpers/gst_helper.php](app/Helpers/gst_helper.php) | `gst_calculate_line()` and `gst_summarize_items()` — single source of truth for taxable/GST/total math, shared by Purchases and Sales. |
| Allocation validation | `Purchases::_validateAllocation()` | Server-side: `0 ≤ project_qty ≤ quantity`; `general_qty` is always the derived remainder, never trusted from client input. |
| Edit/Delete guard | `Purchases::_checkAllocationConsumed()` | Blocks edit/delete once *either* the PROJECT-side or GENERAL-side stock this purchase posted has already been drawn down below what it posted (i.e., already sold/consumed). Prevents corrupting a ledger that downstream Sales rows depend on. |
| Numbering | — | `invoice_no` is a free-text field for the **supplier's own invoice number** — there is no app-generated running purchase number today. |
| Project cost link | `ProjectModel::getAllocatedPurchaseCost*()` | Reads `purchase_items.project_qty` directly; `general_qty` is never counted as project cost. |

### 1.2 Stock (manual General stock entry)

[app/Controllers/Stock.php](app/Controllers/Stock.php) — `stock-entry` routes. This is a **manual adjustment screen**, not a purchase workflow: it inserts a single `stock_ledger` row with `source='GENERAL'`, `reference_type='MANUAL'`, `reference_id=0`. `index()` aggregates current GENERAL balance per product (`SUM(IN) − SUM(OUT)`, `HAVING current_stock > 0`). This is effectively a primitive version of "Warehouse Stock Summary" (Phase D/G below), but it has no ledger view beyond a single last row, no per-transaction drill-down, and edits mutate the ledger row in place rather than reversing/re-posting.

### 1.3 Sales (stock consumption reference)

[app/Controllers/Sales.php](app/Controllers/Sales.php) — each sale line carries its own `stock_source` (`GENERAL` or `PROJECT`); the sale header derives `stock_source = 'MIXED'` if lines mix sources. `_getAvailableStock()` mirrors `StockLedgerModel::getAvailableStock()` inline (duplicated logic, not reused from the model — a pre-existing inconsistency, not introduced by this release). Selling from GENERAL stock posts a `stock_ledger` OUT row with `source='GENERAL'`; this is a **billing** event, not a warehouse transfer. There is currently no "move GENERAL stock into a project without billing the customer" transaction anywhere in the app — that is the actual net-new piece for Warehouse Issue.

### 1.4 Reusable vs Not-Reusable

**Reusable as-is (no duplication needed):**
- `gst_helper.php` (`gst_calculate_line`, `gst_summarize_items`) — General Purchase items need identical GST math.
- `SupplierModel`, `ProductModel` — General Purchase selects from the same masters, no new fields required for the purchase itself.
- `StockLedgerModel::getAvailableStock()` — already computes GENERAL balance; General Purchase's warehouse stock can post into the **same** `stock_ledger` table (see Phase C for the recommendation) and reuse this method unchanged.
- The GENERAL/PROJECT/reference_type pattern in `stock_ledger` — General Purchase is simply a new `reference_type` value (`GENERAL_PURCHASE`) alongside the existing `PURCHASE`, `SALE`, `MANUAL`.
- AdminLTE Purchase list/create/edit/view Blade-style layout ([app/Views/purchases/*.php](app/Views/purchases)) as a visual/structural template for the new views.
- The auto-numbering *pattern* used by `ProjectCashReceiptModel::nextReceiptNo()` (`id`-based, zero-padded, prefixed) — directly reusable as a template for `GP-000001` numbering (see Phase H).

**Must NOT be reused / must stay isolated:**
- `purchases` / `purchase_items` tables and `PurchaseModel` — must not gain new business rules for the no-project case; General Purchase gets its own tables so `Purchases::_checkAllocationConsumed()`, project-cost timeline queries, and Project Purchase's edit/delete guards never need to branch on "is this a general purchase."
- `Purchases::_validateAllocation()` / `_checkAllocationConsumed()` — these encode Project Purchase's specific project/general split-on-one-line semantics; General Purchase has no project_qty split at creation time (100% of a General Purchase line is warehouse stock until issued), so this logic doesn't apply and shouldn't be generalized to fit both.
- `ProjectModel::getAllocatedPurchaseCost*()` — must remain reading only from `purchase_items`; project cost from warehouse stock must be added via a **separate** query against the new Warehouse Issue table (additive, not a rewrite).

---

## 2. Reusable Components

| Component | Reused For |
|---|---|
| `gst_helper.php` | General Purchase item GST calculation (identical rules: per-product `gst_percent`, optional `gst_applicable` per line). |
| `SupplierModel` + existing Supplier dropdown/Quick-Add | Supplier selection on General Purchase Create. |
| `ProductModel` + existing Product dropdown | Product selection on General Purchase Create. |
| `stock_ledger` table + `StockLedgerModel::getAvailableStock()` | Warehouse stock IN (on General Purchase) and OUT (on future Warehouse Issue); Warehouse Stock Summary/Ledger pages read from here. |
| AdminLTE Purchase Create/Edit/View page structure | General Purchase Create/Edit/View layout (line-item grid, GST summary panel, supplier/date header). |
| `id`-based zero-padded running-number pattern (`ProjectCashReceiptModel::nextReceiptNo`) | `GP-000001` numbering for `general_purchases`. |
| `Auth` filter (`['filter' => 'auth']`) on all existing routes | Same filter applied to all new General Purchase / Warehouse routes. |

---

## 3. Files That Will Need Modification (Analysis Only — Not Modified)

Per client rule "no existing workflow should break," the list of touched *existing* files is intentionally small:

| File | Nature of Future Change | Why It's Safe |
|---|---|---|
| [app/Config/Routes.php](app/Config/Routes.php) | Append new route group for `general-purchases/*` and `warehouse-stock/*`. Purely additive — no existing route lines change. |
| [app/Views/layout or sidebar partial] *(menu file, not yet located — confirm exact path during implementation)* | Add two new sidebar menu items ("General Purchase", "Warehouse Stock"). Additive only. |
| `ProjectModel.php` | **Additive method only**, e.g. `getWarehouseIssueCost(int $projectId)`, mirroring `getAllocatedPurchaseCost()` but reading from the new Warehouse Issue table. `getTimelineEvents()` gets one more `foreach` block appended (Warehouse Issue rows, category `Cost`), exactly like the existing Purchase/Expense blocks. No existing method body changes. |
| `Reports.php` (future release) | Additive queries only, once General Purchase go-live; not touched in 4.8.1 itself (analysis in Phase I). |

No changes are proposed to `Purchases.php`, `PurchaseModel.php`, `Sales.php`, `Stock.php`, `SupplierModel.php`, `ProductModel.php`, `StockLedgerModel.php`, or any existing view.

---

## 4. New Files Required (For the Future Implementation Phase)

```
app/Controllers/GeneralPurchases.php
app/Controllers/WarehouseStock.php          (Summary + Ledger views)
app/Models/GeneralPurchaseModel.php
app/Models/GeneralPurchaseItemModel.php
app/Models/WarehouseStockLedgerModel.php     (see Phase C for the "reuse stock_ledger vs new table" decision — this may instead be a thin wrapper around StockLedgerModel)
app/Views/general-purchases/index.php
app/Views/general-purchases/create.php
app/Views/general-purchases/edit.php
app/Views/general-purchases/view.php
app/Views/warehouse-stock/index.php          (Summary)
app/Views/warehouse-stock/ledger.php         (per-product ledger drill-down)
app/Database/Migrations/xxxx_CreateGeneralPurchases.php
app/Database/Migrations/xxxx_CreateGeneralPurchaseItems.php
app/Database/Migrations/xxxx_ExtendStockLedgerReferenceType.php   (only if reusing stock_ledger — widen the reference_type ENUM)
```
*(Not created in this phase — listed for planning only.)*

---

## 5. Database Design

### Decision: reuse `stock_ledger` for warehouse movements, do not create a parallel `warehouse_stock_ledger` table

Covered in depth in Phase C/D. Short version: `stock_ledger` already models exactly what's needed (`source` GENERAL/PROJECT, `reference_type`, `reference_id`, `transaction_date`, IN/OUT). Splitting warehouse movements into a second physical ledger table would require every reporting/reconciliation query to `UNION` two tables to get a true stock picture, and risks the two ledgers drifting out of sync. The tables below therefore represent the *document* layer (`general_purchases`, `general_purchase_items`) only; stock movement continues to live in the existing `stock_ledger`, with two new `reference_type` values added to its ENUM: `GENERAL_PURCHASE` and `WAREHOUSE_ISSUE`.

If the client insists on a fully separate ledger for auditability/reporting isolation, Phase D includes that alternative as Option B.

### 5.1 `general_purchases`

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT(10) UNSIGNED | No | AUTO_INCREMENT | PK | |
| purchase_no | VARCHAR(20) | No | — | UNIQUE | `GP-000001` format (Phase H) |
| supplier_id | INT(10) UNSIGNED | No | — | FK → suppliers.id | Same Supplier Master as Project Purchase |
| purchase_date | DATE | No | — | INDEX | |
| invoice_no | VARCHAR(100) | Yes | NULL | | Supplier's own invoice number |
| total_amount | DECIMAL(15,2) | No | 0.00 | | Sum of `total_with_gst` across items |
| advance_paid | DECIMAL(15,2) | No | 0.00 | | Advance captured at creation time |
| balance_amount | DECIMAL(15,2) | No | 0.00 | | Generated/maintained: `total_amount − advance_paid − SUM(payments)`; Release 4.8.2 payment screen updates this |
| payment_status | ENUM('UNPAID','PARTIAL','PAID') | No | 'UNPAID' | INDEX | Derived, stored for fast list filtering (mirrors `sales.status` pattern) |
| notes | TEXT | Yes | NULL | | |
| created_by | INT(10) UNSIGNED | Yes | NULL | FK → users.id | Audit trail (not present on `purchases` today — recommended addition here since this is a new table, not retrofitted) |
| created_at | DATETIME | Yes | CURRENT_TIMESTAMP | | |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | | |

Indexes: `UNIQUE(purchase_no)`, `INDEX(supplier_id)`, `INDEX(purchase_date)`, `INDEX(payment_status)`.

Deliberately **no `project_id` column** — General Purchase is never linked to a project at creation. (Contrast with `purchases.project_id`, which is nullable in schema but mandatory in practice today.)

### 5.2 `general_purchase_items`

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT(10) UNSIGNED | No | AUTO_INCREMENT | PK | |
| general_purchase_id | INT(10) UNSIGNED | No | — | FK → general_purchases.id (ON DELETE CASCADE) | |
| product_id | INT(10) UNSIGNED | No | — | FK → products.id | |
| hsn_code | VARCHAR(50) | Yes | NULL | | Snapshot at purchase time, same pattern as `purchase_items.hsn_code` |
| quantity | INT(11) | No | — | | Whole quantity received into warehouse (no project/general split — 100% goes to warehouse) |
| unit_price | DECIMAL(15,2) | No | — | | |
| gst_percent | DECIMAL(5,2) | Yes | 0.00 | | Snapshot from product at purchase time |
| gst_applicable | TINYINT(1) | No | 1 | | Per-line optional GST, same as `purchase_items` |
| gst_amount | DECIMAL(10,2) | Yes | 0.00 | | |
| total | DECIMAL(15,2) | No | — | | Taxable amount (qty × price) |
| total_with_gst | DECIMAL(10,2) | Yes | 0.00 | | |
| created_at | DATETIME | Yes | CURRENT_TIMESTAMP | | |
| updated_at | DATETIME | Yes | CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | | |

Indexes: `INDEX(general_purchase_id)`, `INDEX(product_id)`.

Note: no `project_qty`/`general_qty` split columns — that split belongs conceptually to Project Purchase only. Every General Purchase line is 100% warehouse stock until a future Warehouse Issue moves some of it to a project.

### 5.3 `stock_ledger` — extension, not a new table

No new columns needed. Only the `reference_type` ENUM is widened:

```
reference_type ENUM('PURCHASE','SALE','MANUAL','GENERAL_PURCHASE','WAREHOUSE_ISSUE')
```

- General Purchase posting → one `stock_ledger` row per line: `transaction_type='IN'`, `source='GENERAL'`, `project_id=NULL`, `reference_type='GENERAL_PURCHASE'`, `reference_id=general_purchases.id`.
- Warehouse Issue posting → **two** `stock_ledger` rows per line (a true transfer, not a plain OUT): `transaction_type='OUT'`, `source='GENERAL'`, `project_id=NULL` **and** `transaction_type='IN'`, `source='PROJECT'`, `project_id=<target project>` — both sharing `reference_type='WAREHOUSE_ISSUE'` and the same `reference_id`. This exactly mirrors how a Project Purchase line already produces a PROJECT-side IN row; the only difference is the OUT leg on the GENERAL side.

### 5.4 `general_purchase_payments` (reserved for Release 4.8.2, header fields only touched in 4.8.1)

Not built in 4.8.1, but the schema is reserved here since `general_purchases.advance_paid`/`balance_amount`/`payment_status` depend on its eventual shape, and the client explicitly said "one supplier payment can pay multiple bills":

| Column | Type | Notes |
|---|---|---|
| id | INT(10) UNSIGNED PK | |
| supplier_id | INT(10) UNSIGNED FK | Payment is supplier-level, not bill-level, per client clarification |
| payment_date | DATE | |
| amount | DECIMAL(15,2) | |
| method | VARCHAR(50) | |
| reference | VARCHAR(100) | |
| notes | TEXT | |

A join table `general_purchase_payment_allocations (payment_id, general_purchase_id, allocated_amount)` will be needed to let one payment settle multiple `general_purchases` rows (many-to-many), analogous to how `sales`/`payments` work today but generalized to N:N since a single supplier payment can span multiple bills. **This is analysis for 4.8.2 planning only — flagged now so the `general_purchases` payment columns above are designed compatibly.**

---

## 6. Business Flow

### 6.1 Create General Purchase
1. User opens General Purchase → Create.
2. Selects Supplier (existing dropdown, Quick-Add reused), Purchase Date, Invoice No.
3. Adds line items: Product (existing dropdown), Quantity, Unit Price, GST Applicable (checkbox, defaults from product), GST% (from product, editable per line as today).
4. Optionally enters Advance Paid.
5. Submit → server recalculates every line's GST via `gst_calculate_line()` (never trusts client-computed totals, matching current Purchase pattern), computes `total_amount`, sets `balance_amount = total_amount − advance_paid`, derives `payment_status`.
6. Transaction: insert `general_purchases` header (with generated `purchase_no`) → insert `general_purchase_items` rows → for each line insert one `stock_ledger` IN/GENERAL/`GENERAL_PURCHASE` row.
7. Commit; redirect to list with success message.

### 6.2 Edit General Purchase
1. Same "already consumed" guard concept as Project Purchase: before allowing edit, check whether any warehouse stock this purchase posted has since been issued out (`getAvailableStock(productId, 'GENERAL') < quantity this purchase contributed`). If so, block edit with a clear message — this reuses the *pattern* of `Purchases::_checkAllocationConsumed()`, not its code (General Purchase's check is simpler since there's no project/general split to check, only one GENERAL balance per product).
2. If unconsumed: delete this purchase's `stock_ledger` rows (`reference_type='GENERAL_PURCHASE'`, `reference_id=id`), update header, delete+reinsert items, reinsert ledger rows — identical re-post pattern to `Purchases::update()`.

### 6.3 Delete General Purchase
Same consumed-check, then delete ledger rows → items → header, in a transaction, exactly mirroring `Purchases::delete()`.

### 6.4 Save Draft
Not requested by the client clarifications above and no equivalent exists for Project Purchase today (Project Purchase has no draft state). **Recommendation: do not build Draft in 4.8.1** — out of scope unless the client asks explicitly; flagged as an open question in Phase J's edge-case handling if needed.

### 6.5 Partial / Full Payment
Advance is captured at creation only (per client clarification). Additional payments belong to Release 4.8.2's dedicated payment screen, which will insert rows into `general_purchase_payments` and update `balance_amount`/`payment_status` on the affected `general_purchases` rows via the allocation table. 4.8.1 only needs the header columns to exist so 4.8.2 doesn't require an ALTER TABLE.

### 6.6 Warehouse Stock Update (on General Purchase create/edit/delete)
As described in 6.1–6.3 — a pure IN posting to `stock_ledger` with `source='GENERAL'`.

### 6.7 Future Warehouse Issue (design only, not built in 4.8.1 — client said "later")
1. User selects a product with available GENERAL stock, a quantity, and a target Project.
2. System validates `quantity ≤ getAvailableStock(productId, 'GENERAL')` (never allow negative stock — Phase J).
3. Posts the two-row transfer described in 5.3 (`OUT` GENERAL + `IN` PROJECT), `reference_type='WAREHOUSE_ISSUE'`.
4. Project cost increases at issue time: valued at the *warehouse's own cost basis* for that product (recommend **FIFO cost of the general_purchase lines that are being drawn down**, consistent with the existing FIFO philosophy already used elsewhere in the app — see Phase C), not at the product's `selling_price`.
5. `ProjectModel::getWarehouseIssueCost()` (new additive method) sums these issue costs per project, feeding the timeline and project cost total alongside `getAllocatedPurchaseCost()`.

### 6.8 Report Update
Additive-only; see Phase I.

---

## 7. Warehouse Flow Diagram (textual)

```
                          ┌────────────────────────┐
                          │   General Purchase      │
                          │  (Supplier Invoice)     │
                          └───────────┬─────────────┘
                                      │ posts IN (source=GENERAL,
                                      │ reference_type=GENERAL_PURCHASE)
                                      ▼
                          ┌────────────────────────┐
                          │   stock_ledger          │
                          │   (existing table,      │
                          │   GENERAL rows)         │
                          └───────────┬─────────────┘
                                      │
                     ┌────────────────┴─────────────────┐
                     │                                   │
      Warehouse Issue (future)                 Manual Stock Adjustment
      posts OUT(GENERAL) + IN(PROJECT,          (existing Stock module,
      reference_type=WAREHOUSE_ISSUE)           unchanged)
                     │
                     ▼
        ┌────────────────────────┐
        │  Project stock          │──▶ available for Sales (existing,
        │  (source=PROJECT rows)  │    stock_source='PROJECT', unchanged)
        └────────────────────────┘
                     │
                     ▼
        Project cost += FIFO cost of issued qty
        (new: ProjectModel::getWarehouseIssueCost(),
        additive alongside existing getAllocatedPurchaseCost())
```

Note that GENERAL stock can *also* still be sold directly (existing Sales `stock_source='GENERAL'` path) without ever passing through a project — that flow is untouched.

---

## 8. UI Page Plan

All pages follow the existing AdminLTE 3.1 card/table conventions from [app/Views/purchases/](app/Views/purchases) and [app/Views/products/](app/Views/products).

| Page | Route (proposed) | Layout Basis |
|---|---|---|
| General Purchase List | `general-purchases` | Mirrors [purchases/index.php](app/Views/purchases/index.php): table with Purchase No, Supplier, Date, Invoice No, Total, Payment Status badge, Actions. Adds a Payment Status filter (Project Purchase list has none today since it has no payment concept yet). |
| General Purchase Create | `general-purchases/create` | Mirrors [purchases/create.php](app/Views/purchases/create.php) minus the Project dropdown and minus the Project Qty / General Qty split columns in the line grid (every line is implicitly 100% warehouse). Adds an "Advance Paid" field in the header card. |
| General Purchase Edit | `general-purchases/edit/(:num)` | Mirrors [purchases/edit.php](app/Views/purchases/edit.php), same simplifications as Create. |
| General Purchase View | `general-purchases/view/(:num)` | Mirrors [purchases/view.php](app/Views/purchases/view.php): read-only header + GST summary panel (reusing `gst_summarize_items()`), plus payment summary block (Advance / Balance / Status). |
| Warehouse Stock Summary | `warehouse-stock` | Extends the existing [stock/index.php](app/Views/stock/index.php) pattern (product, unit, current GENERAL balance) — this page effectively already exists in primitive form as `stock-entry`; the new page is the same query surfaced under warehouse branding, potentially adding "Reserved" and "Available" columns once Warehouse Issue exists. |
| Warehouse Stock Ledger | `warehouse-stock/ledger/(:product_id)` | New: full chronological `stock_ledger` history for one product filtered `source='GENERAL'`, showing both GENERAL_PURCHASE INs and WAREHOUSE_ISSUE OUTs — the drill-down [stock/view.php](app/Views/stock/view.php) does not currently provide (it only shows the latest row). |

---

## 9. Number Generation

**Current state:** no auto-generated running number exists for Project Purchase (`invoice_no` is the supplier's own number, user-entered). The closest existing precedent is `ProjectCashReceiptModel::nextReceiptNo()`:

```php
$last = $this->select('id')->orderBy('id', 'DESC')->first();
$next = $last ? ((int) $last['id'] + 1) : 1;
return 'CASH-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
```

**Recommendation:** add an analogous `GeneralPurchaseModel::nextPurchaseNo()` following the identical id+1, zero-padded, prefixed pattern:

```php
'GP-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT)   // GP-000001
```

This is safe and independent because:
- It reads only from the new `general_purchases` table's own `id` sequence — it cannot collide with or be affected by Project Purchase's numbering (which doesn't exist) or `purchases.id`.
- Generated once at `store()` time and persisted in `purchase_no` (not recomputed on every read), matching how `receipt_no` is persisted rather than derived on the fly — safe against future ID gaps from deleted rows.

No changes to any existing numbering logic are required since none currently exists for purchases.

---

## 10. Reports Impact (Analysis Only)

| Report | Current Data Source | Future Impact |
|---|---|---|
| Dashboard | Various aggregate queries (not audited in depth here — out of strict scope of this release's touched modules) | Will eventually need a "Warehouse Stock Value" tile and a "Supplier Outstanding" tile once 4.8.2 payments exist. No change in 4.8.1. |
| Purchase Report | `Reports::purchases()` (reads `purchases`/`purchase_items`) | Will need an additive query branch or a second report tab for General Purchase — recommend **keeping it a separate report/tab** rather than a UNION, so Project Purchase's report output is provably unchanged. |
| Stock Summary | `Reports::stock()` | Already reads `stock_ledger`; once `GENERAL_PURCHASE`/`WAREHOUSE_ISSUE` reference types exist, this report's GENERAL balances **automatically include General Purchase stock** with zero code change, since it aggregates by `source`, not by `reference_type`. Worth confirming this in Phase 2 by reading `Reports::stock()` in full before implementation. |
| Balance Sheet | Not yet audited (module exists per Controllers list but out of this release's scope) | Will need Warehouse Stock valuation as a new inventory asset line, and Supplier Outstanding (from 4.8.2) as a new liability line. |
| Profit & Loss | `ProjectModel::getAllocatedPurchaseCost*()` | Will need `getWarehouseIssueCost()` added alongside it (additive, described in Phase 6.7) once Warehouse Issue exists — General Purchase cost hits P&L only at issue time, never at purchase time, per the client's core requirement. |
| Supplier Ledger | Not yet audited | Will need to include General Purchase bills and (in 4.8.2) General Purchase payments alongside whatever it already shows for Project Purchase, if a supplier-level ledger exists today. |

No existing report file is modified in this phase; the table above is for planning the *next* implementation phase only.

---

## 11. Edge Cases

| Case | Handling Design |
|---|---|
| Advance greater than bill amount | Reject at creation (validation error), same posture as would be expected of any invoice; `balance_amount` must never be negative from an over-advance — block, don't silently allow a credit balance (differs from Project's `unused_advance` concept, which is a project-level running account, not a per-bill one). |
| Duplicate supplier invoice number | Recommend a soft warning (not a hard unique constraint), since the same supplier could legitimately reuse a number format across years, and Project Purchase itself has no uniqueness constraint on `invoice_no` today — stay consistent. |
| Purchase edited after payment | Block if `advance_paid > 0` and the edit would reduce `total_amount` below `advance_paid` (would make balance negative); otherwise allow, matching Project Purchase's philosophy of allowing edits until something downstream depends on the original values. |
| Purchase deleted after payment | Block delete once `advance_paid > 0` (money has already changed hands) — stricter than the "consumed stock" check, since financial commitments can't simply be reversed by deleting the record. Force the user to handle refund/credit note manually first (out of scope). |
| Warehouse issue exceeding stock | Hard block: `quantity_requested ≤ getAvailableStock(productId, 'GENERAL')`, identical guard style to `Sales::_getAvailableStock()`'s existing check — never allow negative stock. |
| GST / no-GST products | Per-line `gst_applicable` flag, identical to existing Purchase/Sale item behavior — no new logic needed, `gst_calculate_line()` already handles both. |
| Multiple units | Already handled by `products.unit` (free-text, e.g. Ltr/Pcs/Bag) — General Purchase items simply inherit the product's unit for display; no conversion logic exists or is proposed (matches current app-wide behavior — no unit conversion anywhere today). |
| Negative stock prevention | Enforced at two points: (1) Warehouse Issue validation above, and (2) the existing edit/delete "already consumed" guard pattern applied to General Purchase edits/deletes, preventing a purchase from being reduced/removed below what's already been issued out. |

---

## 12. Risks & Regression Points

1. **Shared `stock_ledger` table.** Because Warehouse Issue and General Purchase post into the same physical table Project Purchase and Sales already write to, any bug in the new insert/delete code has a blast radius that includes existing stock balances. Mitigation: strictly scope all new deletes/updates by `reference_type IN ('GENERAL_PURCHASE','WAREHOUSE_ISSUE')`, exactly as `Purchases::update()` already scopes its own deletes by `reference_type='PURCHASE'` — never a broad `DELETE FROM stock_ledger`.
2. **ENUM widening migration.** Adding `'GENERAL_PURCHASE'` and `'WAREHOUSE_ISSUE'` to `stock_ledger.reference_type` is a schema change to a live, populated table. Must be a plain `ALTER TABLE ... MODIFY COLUMN` (additive to the ENUM list, existing values unaffected) — never a table rebuild. Low risk but must be reviewed against the actual MySQL/MariaDB version in use.
3. **Duplicated GST/availability logic.** `Sales::_getAvailableStock()` already duplicates `StockLedgerModel::getAvailableStock()` instead of calling it — a pre-existing inconsistency. New General Purchase / Warehouse Issue code should call the model method, not re-duplicate the SQL a third time, to avoid the divergence risk growing.
4. **Project cost double-counting.** `getAllocatedPurchaseCost()` (from Project Purchase) and the future `getWarehouseIssueCost()` (from Warehouse Issue) must remain additive and mutually exclusive by construction — a unit brought in via General Purchase must never also appear in `purchase_items`, and vice versa, since they're now physically separate tables. This is inherently safe under the "separate tables" design, but must be explicitly tested once implementation begins (a project's total cost = sum of both, never overlapping).
5. **Payment fields added ahead of the payment screen.** `general_purchases.advance_paid`/`balance_amount`/`payment_status` are designed now but only partially used until 4.8.2. Risk: if 4.8.2's design changes the payment model shape (e.g., decides payments must be bill-specific after all), these columns may need revision. Mitigation: these are new columns on a new table with no other 4.8.1 code depending on their exact final semantics beyond "advance captured at creation" — low regression risk even if reshaped later.
6. **Sidebar/menu file location unconfirmed.** The exact partial that renders the AdminLTE sidebar wasn't located during this analysis pass — must be identified before implementation so the new menu entries are added in the one correct place rather than guessed.

---

## 13. Estimated Development Order

1. Migration: create `general_purchases`, `general_purchase_items`; widen `stock_ledger.reference_type` ENUM.
2. Models: `GeneralPurchaseModel`, `GeneralPurchaseItemModel` (thin, mirroring `PurchaseModel`).
3. Controller: `GeneralPurchases` — `index`, `create`, `store` (header + items + stock post + numbering), `view`.
4. Views: List, Create, View (Edit/Delete follow once Create is validated end-to-end, reusing the same consumed-check pattern).
5. `GeneralPurchaseModel::nextPurchaseNo()` — numbering.
6. Warehouse Stock Summary page (can launch in parallel with step 3–4, since it only reads existing `stock_ledger` GENERAL rows — works correctly the moment step 3 starts posting `GENERAL_PURCHASE` rows).
7. Warehouse Stock Ledger (drill-down) page.
8. Edit + Delete for General Purchase, with the "already issued" consumed-check (depends on Warehouse Issue existing to be meaningfully tested, or can ship with the check as future-proofing and no-op until Warehouse Issue lands).
9. Sidebar menu entries + route wiring.
10. *(Next release, 4.8.1-b or 4.8.2 lead-in)* Warehouse Issue feature (transfer GENERAL→PROJECT, project cost hook).
11. *(Release 4.8.2)* Supplier payment screen + `general_purchase_payments` + allocation table.
12. Reports integration (Phase 10) — deferred until the above are live and reviewed.

---

## Open Questions for Client Confirmation Before Implementation

1. **Save Draft** — client clarifications don't request it and no precedent exists in Project Purchase; confirm it's genuinely out of scope for 4.8.1.
2. **Duplicate invoice number** — confirm soft-warning (not hard block) is acceptable, consistent with Project Purchase's current lack of a uniqueness constraint.
3. **Separate ledger table vs. shared `stock_ledger`** (Section 5) — this document recommends reusing `stock_ledger` with new `reference_type` values; confirm this is acceptable versus a fully isolated `warehouse_stock_ledger` table, since it's the single highest-leverage architectural decision in this design.
4. **Warehouse Issue costing method** — this document recommends FIFO cost of the specific General Purchase lines drawn down (consistent with the app's existing FIFO philosophy); confirm this matches client expectation versus, e.g., weighted-average cost.
