# Release 2.1B — Dependency Analysis (Pre-Implementation)

Spec: `RELEASE_2_1A_PROJECT_WORKFLOW_DESIGN.md` (Final Approved Business Workflow + Final §9 review
findings), treated as frozen. This document maps every Phase 1-4 UI requirement to the exact
existing code it must read from, and confirms the "no calculation change" boundary before any file
is edited.

## Files that will be touched

| File | Phase | Kind of change |
|---|---|---|
| `app/Views/projects/view.php` | 1, 3, 4 | New markup sections only |
| `app/Views/payments/create.php` | 2 | Markup + JS restructure (dropdown → cards) |
| `app/Views/payments/edit.php` | 2 | Same, plus pre-selected-card logic |
| `app/Controllers/Projects.php` | 3, 4 | One added line in `view()`: call the existing `getTimelineEvents()` method (already used by `statement()`), no new query authored |
| `app/Controllers/Payments.php` | 2 | `create()`/`edit()`: widen the existing sales SELECT column list (add `project_id`, `sale_date`, `status`) and add one project-list SELECT for the Step 1 filter. No WHERE-clause business logic, no write path touched. |

**Not touched:** any Model file, `store()`/`update()`/`delete()` in Payments, `SaleModel`,
migrations, routes (all needed routes already exist — see below), reports, exports, Dashboard.

## Phase 1 — Billing Overview KPIs → existing source fields

All six required KPI cards and both progress figures already exist as fields returned by
`ProjectModel::getFinancialSummary()` (unchanged since 2.0D/2.1A design). No new query, no new
model method:

| Required card | Field | Already computed by |
|---|---|---|
| Contract Value | `total_project_value` | `getFinancialSummary()` |
| Total Invoiced | `total_billed` | `getFinancialSummary()` |
| Total Customer Paid | `total_paid` | `getFinancialSummary()` |
| Customer Pending | `outstanding_collection_balance` (shown when > 0.004) | `getFinancialSummary()` |
| Advance Credit Remaining | `outstanding_collection_balance` (shown when < -0.004) | `getFinancialSummary()`, same field as above, opposite sign |
| Remaining To Bill | `remaining_billable_value` | `getFinancialSummary()` — already returned today, simply never displayed on `projects/view.php` before now |
| Contract Billing Progress % | `billing_progress_percent` | `getFinancialSummary()` — same field `view.php` already uses for its existing "% billed" strip |
| Invoice Collection Progress % | *(display-only formula over two existing fields)* | `(total_billed − outstanding_collection_balance) / total_billed × 100` — per Final §3.0.2 of the design doc, computed in the view exactly like the existing `$csPercent` local variable pattern already in `view.php` line 13 |

**Invoice Count Summary** (Total/Paid/Partial/Pending) — all four counts are `array_filter()` over
`$data['sales']`, the same array `Projects::view()` already loads and the view already uses once
(`$pendingInvoicesCount` at line 21). No new query.

**Controller change needed for Phase 1: none.** Every figure and count is already present in the
`$data` array `Projects::view()` passes to the view today.

## Phase 2 — Payment screen dependency map

* **Existing data source, unchanged validation:** `Payments::store()` / `update()` /
  `SaleModel::recalculatePaymentState()` — not opened for this release beyond confirming (again)
  that the pending-amount check (`amount > balance_amount + 0.01`) lives entirely server-side and is
  independent of how the invoice was selected on the client. Card-based selection changes nothing
  about that check.
* **Existing IDs the JS depends on today** (`payments/create.php` / `edit.php`, both files):
  `#saleSelect`, `#saleDetail`, `#detTotal`, `#detAdvance`, `#detPaid`, `#detPending`,
  `#amountInput`. Per the Compatibility Rules, these must survive. Plan: `#saleSelect` becomes a
  hidden `<select>` driven by clicking a card's **Record Payment** button (sets its value, fires
  `change`); the existing `change` handler — already reading `data-total/-advance/-paid/-pending`
  off the selected `<option>` and writing `#detTotal` etc. plus the `#amountInput` max attribute — is
  reused byte-for-byte. No JS logic is rewritten, only how the `<select>`'s value gets set.
* **New per-card fields needed that the current `Payments::create()`/`edit()` SELECT does not
  fetch:** `project_id` (to filter cards by Step 1's project choice), `sale_date` (card shows
  Invoice Date), `status` (card shows Invoice Payment Status badge). All three already exist as
  plain columns on `sales` and are already read elsewhere (`Projects::view()`'s sales query already
  selects `s.*`) — this is a SELECT-list widening, not a new calculation.
* **Step 1 project list:** needs `id`, `name` from `projects` — the same two columns
  `Projects::statementsList()` already selects for an analogous project picker. No new table, no
  new join beyond what other controllers already do.
* **`WHERE s.status != 'PAID'`** in today's `Payments::create()` query is dropped per the design
  ("All invoices are listed, not just unpaid ones, for context" — Final §2 Step 2): the Record
  Payment action itself stays conditionally shown (`Invoice Pending > 0.004`) in the view, which is
  where the equivalent restriction now lives instead of the SQL WHERE clause.

## Phase 3 — Customer Payment History dependency map

Design (Final §3.3) asks for a chronological list separating **Project Advance** from **Invoice
Payment**. `ProjectModel::getTimelineEvents()` (already implemented, already used by
`statement()`/`statementExport()`/`statementPdf()`, never previously called from `view()`) already
returns exactly these two event types tagged `category = 'Payment'`, with `type` distinguishing
`'Project Advance'` from `'Invoice Payment'`. Plan: call this existing method once from
`Projects::view()` and filter to `category === 'Payment'` in the view. No new query, no new model
method, no change to `getTimelineEvents()` itself.

## Phase 4 — Recent Project Activity dependency map

Same `getTimelineEvents()` call (shared with Phase 3 — one controller-side call, two different
view-side filters, avoiding the RC4/RC5-style duplicate-query problem flagged in the 2.0C audit).
Required event types (Invoice Created, Payment Received, Purchase Added, Expense Added) map onto
the existing `type` values `'Sales Invoice'`, `'Invoice Payment'`, `'Purchase'`, `'Expense'`
respectively. `'Project Advance'` events are filtered out of this feed (reserved for Phase 3) per
the spec's exact 4-item list. **Stock Return is excluded automatically** — `getTimelineEvents()`
has never emitted a Stock Return event (the feature was added then removed at the migration level,
per the 2.1A design's Final §3.4 finding), so no explicit filtering code is even needed for that
exclusion; documented here so it isn't mistaken for an oversight.

## Confirmed: nothing here requires schema, migration, model, report, export, or Dashboard changes

* No new column, table, or migration — every figure/count/event above is read from columns and
  methods that exist today.
* `SaleModel` (FIFO/advance allocation, payment-state recalculation) is not opened for editing.
* `app/Views/dashboard/*` is not touched.
* `Reports::*` and the two `Projects::statement*Export/Pdf` methods are not touched.
* Routes: `projects/view/(:num)`, `payments/create`, `payments/create/(:num)`, `payments/edit/(:num)`
  already exist and already route to the controllers being lightly extended — no route file change.

## Risk notes carried into implementation

* `projects/view.php` will end up with two places showing Contract Value / Total Invoiced /
  Customer Pending (the pre-existing Health Dashboard cards, left untouched per "add, don't remove"
  scope, and the new Billing Overview section). This is a known, accepted duplication for this
  release — flagged the same way the 2.0C audit flagged RC4/RC5, not fixed here since removing the
  old cards was not asked for. Recorded again in `RELEASE_2_1B_BASELINE.md`.
* Removing the `status != 'PAID'` filter in `Payments::create()` means the invoice list can grow
  large on projects with long invoice history (a long-duration-project scenario this whole release
  targets) — filtering/sorting is client-side only in this implementation (no pagination), consistent
  with "no controller changes beyond fetching data," but noted as a Phase C acceptance-test item.
