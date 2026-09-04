# Release 2.1C — Fix Project Payment Status & Payment Screen Workflow — Dependency Analysis (Phase A)

Confirms every location that must change for Bug 1 and Bug 2, and confirms nothing else touches the
same logic. No file is edited in this phase.

## Bug 1 — Project Payment Status

### Single source of truth today (the bug)

`app/Models/ProjectModel.php::getPaymentStatus(int $projectId): string` (lines 26-44) derives status
from **invoice-level `sales.status` counts** — `PAID` only when *every* invoice row is `PAID`,
`PARTIAL` when at least one invoice is `PARTIAL`/`PAID`, else `UNPAID`, `N/A` when zero invoices. This
never looks at Contract Value vs Total Invoiced, so a project with one fully-paid invoice out of a
₹25,86,869 contract reads `PAID` — the reported bug.

### Every consumer of project-level payment status

| Location | How it gets the status | Fix needed |
|---|---|---|
| `app/Controllers/Projects.php::view()` line 210 | Calls `ProjectModel::getPaymentStatus()` directly, stored as `$data['payment_status']` | None — automatically fixed once the model method is corrected |
| `app/Views/projects/view.php` lines 319-326, 336 | Reads `$payment_status` (badge + the "COMPLETED and PAID" congrats banner) | None — consumes the corrected value as-is |
| `app/Controllers/Projects.php::index()` lines 33-81 | **Does NOT call `getPaymentStatus()`.** Duplicates the same invoice-count logic inline (`paid_sale_count`/`covered_sale_count` correlated subqueries, lines 43-45, 62-72) — a second, independent implementation of the same wrong rule | Must be rewritten to the same new rule, reusing `$fs` (the `getFinancialSummary()` call already made per-row at line 77 for the Contract Value / Customer Pending columns) instead of a third calculation |
| `app/Views/projects/index.php` lines 253-257 | Reads `$p['payment_status']` | None — consumes the corrected value as-is |

**Confirmed: exactly one real implementation exists today (`sales.status` counts), duplicated in two
places (`ProjectModel::getPaymentStatus()` and the inline block in `Projects::index()`).** Both must
move to the new rule so there is truly one source of truth going forward — `Projects::index()` will
no longer duplicate the calculation; it will use `getFinancialSummary()` per project exactly as it
already does for the Contract Value / Customer Pending columns.

### Audited and confirmed NOT affected

- **`app/Controllers/Projects.php::statement()`, `statementExport()`, `statementPdf()`,
  `_buildStatementData()`** (lines 279, 288, 408, 502) — grepped for `payment_status`/`getPaymentStatus`:
  no match. The Statement page has never shown a project-level payment status; nothing to change.
- **`app/Views/dashboard/*`** — grepped for `payment_status`/`getPaymentStatus`: no match. Dashboard
  does not show this field.
- **`app/Controllers/Reports.php`** — grepped for `payment_status`/`PaymentStatus`: no match.
- **`app/Views/sales/index.php`** — grepped for `payment_status`: no match (that page shows
  *invoice*-level status, i.e. `sales.status` directly, which is a different, correct concept and is
  explicitly out of scope — Bug 1 is only about the project-level rollup).
- **`ProjectModel::getFinancialSummary()`** — not modified. It already exposes every field the new
  rule needs (`remaining_billable_value`, `outstanding_collection_balance`); confirmed by reading its
  body (lines 51-91).

### New rule → exact fields

| Rule condition | Field(s) used | Threshold |
|---|---|---|
| No invoices | `COUNT(*)` on `sales WHERE project_id = ?` | `= 0` |
| Some invoices, Remaining To Bill exists | `remaining_billable_value` | `> 0.004` (existing sign-aware epsilon convention, same as the Billing Overview cards) |
| Fully invoiced, customer still owes | `outstanding_collection_balance` | `> 0.004` |
| Fully invoiced AND Customer Pending = 0 (includes Advance Credit, which is `≤ 0.004` on this signed field) | both of the above `≤ 0.004` | — |

No new query beyond one `COUNT(*)` (cheap, indexed on `project_id` via existing FK usage elsewhere) —
`getFinancialSummary()` itself is reused unchanged, satisfying "Do not change
`ProjectModel::getFinancialSummary()`."

Manual trace against the reported project ("shreeya clinic chennai"): Contract Value ₹25,86,869,
Total Invoiced ₹21,240 ⇒ `remaining_billable_value` = 2,586,869 − advance − 21,240, which is far above
the 0.004 threshold regardless of advance ⇒ rule returns `PARTIAL` on the very first condition,
matching the expected Phase C result before even considering Customer Pending.

## Bug 2 — Payment Create/Edit Screen

### Current SQL (`app/Controllers/Payments.php`)

- `create()` line 52-54 and `edit()` line 124-126: `$data['projects'] = ... SELECT id, name FROM
  projects ORDER BY name ASC` — **no status filter**, so Completed projects appear. Already sorted
  alphabetically (no change needed there).
- `create()` line 40-48 and `edit()` line 114-122: `$data['sales']` loads **every** sale across every
  project in one query (used both for the hidden `#saleSelect` options and, in the view, to render
  every visible table row). No project filter exists at the query level today.

### Current JS/view (`app/Views/payments/create.php`, `app/Views/payments/edit.php`)

- All `<tr class="invoice-row" data-project-id="...">` rows render server-side for every sale, visible
  by default (no `display:none`).
- `#projectFilter` `change` handler (both files, ~line 188-197 in create.php / ~206-215 in edit.php)
  toggles `.invoice-row` visibility by `data-project-id` match, and toggles `#noRowsMsg` — but this
  only runs **after** a user changes the dropdown; the initial page state shows every row because
  nothing hides them on load.
- `edit.php` additionally pre-selects `#projectFilter` to the current sale's project (line 29) but
  never triggers the filter on load — so today it still shows every invoice for every project until
  the user manually reselects the same project.
- The hidden `#saleSelect` and its `change` handler (`#detTotal`/`#detAdvance`/`#detPaid`/
  `#detPending`, `#amountInput` max) are identical in both files and are the thing Bug 2 explicitly
  says not to rewrite — confirmed no changes needed there; only what drives visibility of the table
  above it changes.

### Fix approach (client-side only, no new route/query)

Because `$data['sales']` already carries every row's `project_id`, and `$data['projects']` already
lists every project name, the required workflow is achievable entirely by:

1. Restricting `$data['projects']` to `WHERE status = 'ACTIVE'` (Issue A). `edit.php` needs one
   exception: if the payment's own sale belongs to a non-Active (e.g. Completed) project, that one
   project must still be appended to the list so "Edit page must auto-select its own project" holds
   even for a payment tied to a now-completed project — otherwise the pre-selected `<option>` would
   not exist and the filter would silently show nothing.
2. Changing the default rendered/JS state of the invoice table so **no rows are visible and no
   `data-project-id` filtering has occurred** until a project is chosen — an initial placeholder row
   ("Select a project to view invoices.") replaces today's "show everything" default (Issue B).
3. `edit.php` must run the same filter logic once, on page load, against its pre-selected project
   (instead of only on `change`), so its invoices appear immediately, matching "Edit page must
   auto-select its own project and invoice" (Issue B, edit-specific).
4. Table columns/behavior (Issue C) already match the spec (Invoice No, Date, Invoice Total, Advance
   Applied, Paid Amount, Invoice Pending, Status, Record Payment button disabled at `≤ 0.004`) — this
   was already true after 2.1C's prior table conversion; no column change needed, only the visibility
   timing above.

**Not touched:** `Payments::store()`, `update()`, `delete()`, `SaleModel::recalculatePaymentState()`,
the hidden `#saleSelect` `change` handler, `.record-payment-btn` click handler logic (still just sets
`#saleSelect` and fires `change`).

## Files that will change in Phase B

| File | Change |
|---|---|
| `app/Models/ProjectModel.php` | Rewrite `getPaymentStatus()` body only (signature/return type unchanged) |
| `app/Controllers/Projects.php` | `index()`: drop `paid_sale_count`/`covered_sale_count` subqueries and their inline status block; compute `payment_status` from the already-fetched `$fs` + `sale_count`. `view()`: unchanged (already calls the model method). |
| `app/Controllers/Payments.php` | `create()`/`edit()`: add `WHERE status = 'ACTIVE'` to the projects query; `edit()` additionally appends the current sale's project if it was excluded by that filter |
| `app/Views/projects/index.php` | Badge class map: add `'PENDING' => 'pending'` |
| `app/Views/projects/view.php` | Badge class map: add `'PENDING' => 'pending'`; the "COMPLETED" banner logic already keys off `payment_status === 'PAID'`, no change needed |
| `public/assets/css/style.css` | Add `.badge-pending` rule (new status word has no existing style) |
| `app/Views/payments/create.php` | JS: default-hide invoice rows behind a "Select a project..." placeholder; only reveal on `#projectFilter` change |
| `app/Views/payments/edit.php` | Same JS change, plus run the filter once on load against the pre-selected project |

**Not touched:** any migration, `SaleModel`, `ProjectModel::getFinancialSummary()`,
`ProjectModel::getAllocatedPurchaseCost*()`, Reports, exports, Dashboard, Statement.
