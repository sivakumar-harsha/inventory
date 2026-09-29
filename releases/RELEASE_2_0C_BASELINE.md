# Release 2.0C — Baseline (implementation deliverable)

Scope: UI polish (Project Health Dashboard redesign, compact Billing Progress strip, Timeline
relabeling, Sales list columns) plus one small server-side validation rule (block a new invoice
once a project's contract value is already fully billed). Follows Release 2.0B.

## Files touched

* `app/Views/projects/view.php` — top KPI section replaced with the six-item Project Health
  Dashboard; Contract Billing Progress card compacted into one strip. Project Progress Summary
  strip, Project Details card, Sales/Purchases detail tables, and completion messaging (all added
  in 2.0B) are unchanged.
* `app/Views/projects/statement.php` — `$typeLabels` display-map values updated to the new 2.0C
  wording (keys, i.e. the underlying stored `sales`/timeline event types, are unchanged from 2.0B).
* `app/Views/sales/index.php` — two new columns (Advance Applied, Customer Pending) added to both
  the Active and Completed sales tables.
* `app/Controllers/Sales.php` — `store()` gained one additional guard: reject creating a sale if
  the project's contract value is already fully billed by its existing invoices.

## Files NOT touched

* `app/Models/*` — no model method added or changed. The new validation reads
  `ProjectModel::getFinancialSummary()`'s existing `total_project_value`/`total_billed` fields;
  no new calculation was introduced.
* `app/Controllers/Projects.php`, `Purchases.php`, `Payments.php`, `Reports.php`, `Dashboard.php`
  — untouched.
* Database schema / migrations — none added or changed.
* Export/PDF generation — untouched.
* Payment recording logic (`Payments::store/update/delete`, `SaleModel::recalculatePaymentState`)
  and purchase allocation logic (`ProjectModel::getAllocatedPurchaseCost*`) — untouched.

## 1. Project View — Project Health Dashboard

Replaced the six-card row introduced in 2.0B (Contract Value / Advance Received / Total Invoiced /
Customer Paid / Customer Pending / Project Cost Till Date) with the newly approved six items —
same card style, all still reading existing `financial_summary`/controller values, no new
calculation:

| Card | Source |
|---|---|
| Contract Value | `financial_summary['total_project_value']` |
| Total Invoiced | `financial_summary['total_billed']` |
| Customer Pending | `financial_summary['outstanding_collection_balance']` |
| Work Status | `$project['status']` (existing badge, same classes/labels as the Project Details table below) |
| Project Cost Till Date | `$total_purchases + $total_expenses` (unchanged sum from 2.0B) |
| Customer Payments Received | `financial_summary['total_paid']` |

**Advance Received is no longer its own card** — the raw advance amount is still used internally
(it's baked into `total_billed`/`outstanding_collection_balance` and the billing-progress percent
via `ProjectModel::getFinancialSummary()`, unchanged), but is not displayed as a standalone KPI,
satisfying "no duplicate advance displays." No "Remaining Billable Value" card, matching spec.

## 2. Billing Progress — compact strip

Replaced the 2.0B card (header + "₹X billed of ₹Y contract" line + badge + bar, each on its own
row) with a single-row strip: `X% billed` — progress bar — `₹Billed of ₹Contract` — status badge,
all inline. **Calculation unchanged**: still reads
`financial_summary['billing_progress_percent']` for the percentage and
`total_billed`/`total_project_value` for the currency figures; the bar-width clamp
(`max(0, min(100, $csPercent))`) and the two-state Within Contract (green, <90%) / Near Contract
(orange, ≥90%) badge logic are carried over byte-for-byte from 2.0B.

## 3. Project Timeline — label rewording

`app/Views/projects/statement.php`'s `$typeLabels` map (introduced in 2.0B to relabel
`ProjectModel::getTimelineEvents()`'s stored `type` values for display) was updated in place:

| Stored type (unchanged) | 2.0B label | 2.0C label |
|---|---|---|
| `Project Advance` | Advance Received | Customer Advance Received |
| `Purchase` | Material Purchase | Purchased Materials for Project |
| `Sales Invoice` | Invoice Raised | Billing Raised to Customer |
| `Invoice Payment` | Customer Payment Received | Payment Collected from Customer |
| `Expense` | Project Expense | Project Expense Recorded |

`data-type="<?= esc($ev['type']) ?>"` still carries the original stored value (unchanged since
2.0B) for JS/filter compatibility. Icons, category pills/colors, and the running-balance
calculation are untouched — only the `$typeLabels` array's values changed.

## 4. Sales List — two new columns

Added **Advance Applied** (`sales.advance_applied`, already selected via `s.*` in
`Sales::index()`'s existing query — no query change) and **Customer Pending**
(`sales.balance_amount`, the same value already shown in the existing "Balance" column, itself
already advance-aware per `SaleModel::_recalculateSaleRow()`) to both the Active and Completed
sales tables in `app/Views/sales/index.php`.

**Column position — chosen to preserve existing JavaScript column indices.** The two new columns
were inserted between the existing "Status" column (index 8) and "Actions" (was index 9, now 11).
The page's DataTables `columnDefs` (`targets: 3`, `targets: 8`) and the combined status/customer
filter (`data[8]` for status, `data[3]` for customer) all reference columns before index 8, which
are unchanged in position — confirmed unaffected. `Actions` is referenced only by CSS/markup, not
by index, in this file.

No payment logic changed — both new columns render existing row values already present in the
`$sales` array; no new query, join, or calculation was added to `Sales::index()`.

## 5. Validation — block new invoice once contract value is fully billed

`app/Controllers/Sales.php:store()`, immediately after the existing COMPLETED-project guard:

```php
if ($project) {
    $summary       = $projectModel->getFinancialSummary((int) $projectId);
    $contractValue = (float) ($summary['total_project_value'] ?? 0);
    $totalBilled   = (float) ($summary['total_billed'] ?? 0);
    if ($contractValue > 0 && $totalBilled >= $contractValue - 0.005) {
        return redirect()->back()->with('error', "This project's contract value has already been fully billed. Create a new project for additional work.");
    }
}
```

Reuses `ProjectModel::getFinancialSummary()` exactly as already called elsewhere (e.g.
`Sales::projectFinancialSummary()` AJAX endpoint, `Projects::view()`) — no new calculation. Uses a
0.005 epsilon (matching the tolerance already used for balance/settlement comparisons elsewhere in
the codebase, e.g. `Projects::_buildStatementData()`) so floating-point rounding on the stored
`DECIMAL` sums can't leave the check permanently just-under. The check only fires when
`total_project_value > 0`, so projects with no contract value set (0, the column default) are
unaffected and behave exactly as before — this matches the existing pattern in the Contract
Billing Progress badge logic, which also gates on `$csValue > 0`.

**Not changed:** item/GST calculation, stock-source validation, project-status write path, the
FIFO advance-allocation trigger (`recalculateProjectBilling()`), or any other part of `store()`.
The guard is a pure pre-check that returns before the transaction (`$db->transStart()`) begins.

## Verified-unchanged calculation entry points (regression anchors)

* `ProjectModel::getFinancialSummary()` — no line changed.
* `SaleModel::_recalculateSaleRow()`, `recalculatePaymentState()`, `recalculateProjectBilling()`
  — no line changed.
* `ProjectModel::getAllocatedPurchaseCost()`, `getPaymentStatus()`, `getTimelineEvents()` — no
  line changed.
* DataTables filtering in `sales/index.php` (`data[3]` customer, `data[8]` status) — column
  positions preserved; new columns appended after index 8.
* `sales.advance_applied` / `sales.balance_amount` — read-only display, same values already
  maintained by existing invoice-recalculation logic.

## Manual verification note

`php -l` was run against all four edited files (`app/Views/projects/view.php`,
`app/Views/projects/statement.php`, `app/Views/sales/index.php`, `app/Controllers/Sales.php`) — no
syntax errors. As in 2.0B, a live browser check could not be completed in this session due to a
pre-existing local Apache/environment issue unrelated to this change (confirmed by 500/403
responses on untouched routes such as `/` and `/login`). Recommend a manual pass once the local
server issue is resolved: load `projects/view/<id>` to confirm the six-card layout and compact
progress strip, `projects/statement/<id>` for the new timeline wording, `sales` (both tabs) for the
two new columns and unaffected filters, and attempt creating a sale against a project whose
`total_billed` already equals its `total_project_value` to confirm the new block message appears.
