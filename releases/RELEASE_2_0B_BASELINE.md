# Release 2.0B — Baseline (implementation deliverable)

Scope: UI/UX only. Project View redesign, Contract Billing Progress, Project Progress Summary,
completion messaging wording, and Project Statement timeline label renaming — per the approved
Release 2.0B spec that followed the Release 2.0A dependency analysis.

## Files touched

* `app/Controllers/Projects.php` — `view()` gained one additional read-only query,
  `$data['total_payments_count']`, counting `payments` rows joined to `sales.project_id` for the
  Project Progress Summary strip. No existing query, calculation, or return value changed. No
  other controller touched.
* `app/Views/projects/view.php` — financial summary section replaced; Contract Billing Progress
  and Project Progress Summary sections added; completion-messaging alert text updated.
* `app/Views/projects/statement.php` — timeline event type labels remapped for display only.

## Files NOT touched

* `app/Models/*` — no model method added, removed, or changed. All new UI reads
  `ProjectModel::getFinancialSummary()`'s existing fields (`total_project_value`, `advance_amount`,
  `total_billed`, `total_paid`, `outstanding_collection_balance`, `billing_progress_percent`) and
  `ProjectModel::getAllocatedPurchaseCost()` (already called by `Projects::view()` before this
  release).
* `app/Controllers/Sales.php`, `Purchases.php`, `Payments.php`, `Reports.php`, `Dashboard.php` —
  untouched; no completion/payment/purchase/sales/FIFO/advance-allocation logic changed anywhere.
* Database schema / migrations — none added or changed.
* Export/PDF generation (`Projects::statementExport()`, `statementPdf()`, all of
  `Reports.php`'s export/pdf methods) — untouched.
* `app/Views/projects/statements_list.php`, `create.php`, `edit.php`, `index.php` — not in scope,
  untouched.
* `public/assets/js/project-financial.js` — untouched; no new client-side calculation added.

## 1. Project View Redesign (`projects/view.php`)

**Removed:** the old 4-card "Total Sales / Purchases / Expenses / Net Profit" row and the old
"Project Financial Summary" table block, including the Remaining Billable Value figure, the old
Billing Progress badge (with its "Over Contract Value" red state), and the duplicate advance
line that appeared in both the KPI row and the financial summary table.

**Added:** exactly six compact KPI cards, all reading existing `getFinancialSummary()` fields —

| Card | Source field |
|---|---|
| Contract Value | `financial_summary['total_project_value']` |
| Advance Received | `financial_summary['advance_amount']` |
| Total Invoiced | `financial_summary['total_billed']` |
| Customer Paid | `financial_summary['total_paid']` |
| Customer Pending | `financial_summary['outstanding_collection_balance']` |
| Project Cost Till Date | `$total_purchases + $total_expenses` (both already computed in the controller pre-2.0B; summed in the view, not recalculated) |

The old "Total Sales / Net Profit" cards (revenue/profit figures) are no longer shown on this page
per the approved six-card spec — the Sales and Purchases detail tables further down the page still
list every invoice/purchase individually, so no underlying data was hidden, only the top-of-page
KPI summary was narrowed to the six approved cards.

## 2. Contract Billing Progress

New card below the KPI row. Reads `financial_summary['billing_progress_percent']` (already
computed by `ProjectModel::getFinancialSummary()`, previously unused in `view.php`) directly — no
new percentage math introduced. Displays:

* A blue progress bar (`width` = percent clamped to `[0, 100]` for the bar only; the printed
  percentage itself is not clamped, so contract-exceeding invoicing still shows its true number).
* "₹X billed of ₹Y contract" using `total_billed` / `total_project_value`.
* A two-state badge: **green "Within Contract"** below 90%, **orange "Near Contract"** at 90% and
  above. No "Over Contract" red state — this replaces the old badge's three-state logic (which
  included a red "Over Contract Value" state) with the approved two-state version.

## 3. Project Progress Summary

New compact 4-cell strip. All four counts come from data already loaded/queryable by
`Projects::view()`:

* **Total Purchase Entries** — `count($purchases)` (array already loaded pre-2.0B).
* **Total Sales Invoices** — `count($sales)` (array already loaded pre-2.0B).
* **Total Customer Payments** — `$total_payments_count`, the one new controller-side read
  (`COUNT(*)` over `payments` joined to `sales.project_id`; no aggregation or business rule, a
  plain count mirroring the existing payment→sale→project join pattern already used elsewhere,
  e.g. `ProjectModel::getTimelineEvents()`).
* **Pending Invoices Count** — `count(array_filter($sales, fn($s) => $s['status'] !== 'PAID'))`,
  computed in the view from the already-loaded `$sales` array's existing `status` column (no new
  query, no new status logic — reuses `sales.status` as already maintained by
  `SaleModel::_recalculateSaleRow()`).

## 4. Project Completion Messaging

Wording-only change to the existing status-driven alert block in `projects/view.php`. Status
read/branch logic (`$project['status']`, `$payment_status`) is unchanged — only the message text
and an added `ACTIVE` branch (previously ACTIVE projects showed no message at all):

| Condition | New message |
|---|---|
| `status === 'ACTIVE'` | "Project work is currently in progress." *(new — previously silent)* |
| `status === 'COMPLETED' && payment_status === 'PAID'` | "Project work is completed and all invoices are settled." |
| `status === 'COMPLETED'` (payment not fully paid) | "Project work is completed. Customer payment is still pending." |

`ON_HOLD` projects still show no message, matching pre-2.0B behavior (out of the approved wording
list).

## 5. Project Statement (`projects/statement.php`)

Timeline event **type labels only** are remapped for display, via a `$typeLabels` lookup array
keyed by the same `$ev['type']` values `ProjectModel::getTimelineEvents()` already returns
(`Project Advance`, `Sales Invoice`, `Invoice Payment`, `Purchase`, `Expense`):

| Stored type (unchanged) | Displayed label (2.0B) |
|---|---|
| `Project Advance` | Advance Received |
| `Sales Invoice` | Invoice Raised |
| `Invoice Payment` | Customer Payment Received |
| `Purchase` | Material Purchase |
| `Expense` | Project Expense |

`data-type="<?= esc($ev['type']) ?>"` on each `<tr>` still carries the **original, unmapped**
value — this preserves any existing JS filtering/sorting keyed off `data-type` (none currently
present in the file, but kept intact per the "keep existing IDs and JavaScript compatibility"
rule). Icons (`$typeIcons`), category pills, category colors, and the running-balance calculation
are all unchanged — only the visible `<td>` text next to the icon changed. The Project Summary
KPI block and its own "Over Contract" badge further up `statement.php` were **not** touched — the
approved 2.0B spec's "No Over Contract logic" instruction applied to the new Contract Billing
Progress section on `projects/view.php` only; `statement.php`'s pre-existing three-state contract
badge was out of scope for this release and is unchanged.

## Verified-unchanged calculation entry points (regression anchors)

* `ProjectModel::getFinancialSummary()` — no line changed; all six new/relabeled fields in
  `view.php` map 1:1 to keys this method already returned.
* `ProjectModel::getAllocatedPurchaseCost()`, `getPaymentStatus()`, `getTimelineEvents()` — no
  line changed.
* `SaleModel::_recalculateSaleRow()`, `recalculatePaymentState()`, `recalculateProjectBilling()` —
  no line changed; `sales.status` values read by the new Pending Invoices Count are exactly what
  this logic already maintains.
* `Projects::updateStatus()`, `store()`, `update()` — project status write paths unchanged; the
  completion-messaging update (§4) only changes what is displayed after a status is already set,
  never how or when it is set.
* Purchase allocation math (`purchase_items` project/general split) — untouched; "Project Cost
  Till Date" is a straight sum of two pre-existing controller variables, not a new formula.

## Manual verification note

`php -l` was run against all three edited files (`app/Controllers/Projects.php`,
`app/Views/projects/view.php`, `app/Views/projects/statement.php`) — no syntax errors. A live
browser check of the rendered pages could not be completed in this session: the local XAMPP
Apache instance returned HTTP 500/403 on unrelated, untouched routes (`/`, `/login`) as well,
indicating a pre-existing local server/environment issue unrelated to this change, not a
regression introduced by it. Recommend a manual page load of `projects/view/<id>` and
`projects/statement/<id>` once the local server issue is resolved, to visually confirm KPI card
layout, progress bar rendering, and timeline label text before sign-off.
