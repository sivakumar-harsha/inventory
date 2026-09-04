# Release 2.0D — Project Workflow UX Hotfix (P0) — Baseline

Scope: presentation-only fixes for the five root causes (RC1–RC5) identified in
`RELEASE_2_0C_LAST_PROJECT_AUDIT.md`. No schema, migration, payment, FIFO, advance-allocation,
outstanding-balance, report, or export logic was touched. Every displayed figure continues to come
from `ProjectModel::getFinancialSummary()` / `sales.*` / `SaleModel` exactly as before.

## Scope decisions confirmed before implementation

Two points in the request were ambiguous against the actual codebase and were confirmed before
editing:

1. **RC1 tab mapping.** `sales/index.php` has no existing "Pending Sales" label — its two tabs are
   "Active Sales" (`status != PAID`) and "Completed Sales" (`status = PAID`). Confirmed: rename
   "Active Sales" → "Payment Pending Invoices" (it already filters to payment-pending invoices)
   and "Completed Sales" → "Paid Invoices".
2. **RC4/RC5 scope.** The duplicate Contract-Status/Net-Profit logic the audit flagged lives
   between `projects/view.php` and `projects/statement.php` (RC4), and between
   `Projects::view()` and `Projects::_buildStatementData()` in the controller (RC5) — fixing
   either fully would mean editing `statement.php` and/or `Projects.php`, which this release's
   rules explicitly forbid ("No controller/model calculation changes"). Confirmed: view-only
   verification, no edits to `statement.php` or the controller. See §4 below for what was
   verified.

## Files touched

* `app/Views/sales/index.php` — RC1 (invoice terminology) + RC3 (Sales-side "Invoice Pending"
  column label).
* `app/Views/projects/view.php` — RC2 (sign-aware Customer Pending card).
* `app/Views/payments/create.php`, `app/Views/payments/edit.php` — RC3 (Payments-side "Invoice
  Pending" labels), extending the same naming convention to the screen category the request names
  explicitly ("Sales / Payments screens: Invoice Pending"). IDs (`#detPending`, `#saleSelect`) and
  all JavaScript are unchanged — labels only.

## Files NOT touched

* `app/Models/*`, `app/Controllers/*` — no calculation, query, or business-logic change anywhere.
* `app/Views/projects/statement.php` — its 3-state Contract badge and sign-aware Customer Pending
  card were the reference pattern copied into `view.php`; the file itself is unchanged.
* `app/Views/sales/view.php` — not named in the 2.0D request; its status badge still shows the
  raw `PAID`/`PARTIAL`/`UNPAID` text (out of this release's stated file scope).
* Database schema / migrations — none added or changed.
* Export/PDF generation — untouched.

---

## Fix RC1 — Sales Invoice Terminology (`app/Views/sales/index.php`)

| Old | New |
|---|---|
| Tab: "Active Sales" | Tab: "Payment Pending Invoices" |
| Tab: "Completed Sales" | Tab: "Paid Invoices" |
| Column header: "Status" | Column header: "Invoice Payment Status" |
| Badge text: `PAID` | Badge text: "Invoice Paid" |
| Badge text: `PARTIAL` | Badge text: "Partial Payment" |
| Badge text: `UNPAID` | Badge text: "Payment Pending" |
| Status filter dropdown: "Partial" / "Unpaid" | "Partial Payment" / "Payment Pending" (`value="PARTIAL"`/`"UNPAID"` unchanged) |
| `emptyTable` message | "No paid invoices found." / "No payment pending invoices found." |

**Database/status values unchanged** — `sales.status` is still written and read as
`PAID`/`PARTIAL`/`UNPAID` everywhere; only display text changed, via a small `$statusLabels` map
(same house pattern as `statement.php`'s `$typeLabels`, introduced in 2.0B):

```php
$statusLabels = [
    'PAID'    => 'Invoice Paid',
    'PARTIAL' => 'Partial Payment',
    'UNPAID'  => 'Payment Pending',
];
```

**Filters and DataTables indexes preserved.** The combined status/customer filter
(`sales/index.php`'s inline script) matches `data[8]` against the *raw* uppercase values
(`"PARTIAL"`, `"UNPAID"`) from the `#statusFilter` dropdown. Since the new badge text
("Partial Payment", "Payment Pending") no longer contains those exact uppercase substrings, a
visually-hidden span carrying the raw status was added next to each badge:

```php
<span class="badge-status badge-<?= strtolower($s['status']) ?>"><?= esc($statusLabels[$s['status']] ?? $s['status']) ?></span>
<span class="visually-hidden"><?= esc($s['status']) ?></span>
```

DataTables' default search data for a DOM-sourced column is the cell's full text content
(hidden-via-CSS text nodes included), so `data[8]` still contains the raw `PARTIAL`/`UNPAID`
substring the filter checks for — the filter keeps matching without any JS change. `columnDefs`
(`targets: 3`, `targets: 8`) and `data[3]` (customer) are untouched; no column was added, removed,
or reordered.

**Badge CSS classes unchanged** — still `badge-status badge-paid` / `badge-partial` /
`badge-unpaid`, so `public/assets/css/style.css` needed no edit and existing styling is preserved.

## Fix RC2 — Project Health Dashboard Pending Card (`app/Views/projects/view.php`)

The "Customer Pending" KPI card previously rendered `outstanding_collection_balance` raw
(`number_format($financial_summary['outstanding_collection_balance'] ?? 0, 2)`) in a hardcoded
orange card — showing e.g. "-28,760.00" for a project in advance-credit, with no sign handling.

Replaced with the same sign-aware branch already used for the identical field on
`projects/statement.php:136-159` (same 0.004 epsilon, same three states):

```php
$pending = (float) ($financial_summary['outstanding_collection_balance'] ?? 0);
if ($pending > 0.004) {
    $pendKpiCls = 'kpi-orange'; $pendIcon = 'bi-hourglass-split';
    $pendValue  = number_format($pending, 2);
    $pendLabel  = 'Customer Pending';
} elseif ($pending < -0.004) {
    $pendKpiCls = 'kpi-green'; $pendIcon = 'bi-award';
    $pendValue  = number_format(abs($pending), 2);
    $pendLabel  = 'Advance Credit';
} else {
    $pendKpiCls = 'kpi-green'; $pendIcon = 'bi-check-circle';
    $pendValue  = 'Settled';
    $pendLabel  = 'Customer Pending';
}
```

The card markup now reads `$pendKpiCls`/`$pendIcon`/`$pendValue`/`$pendLabel` instead of a
hardcoded `kpi-orange` class and a raw number. **No new calculation** — `outstanding_collection_balance`
still comes straight from `ProjectModel::getFinancialSummary()`; only how it's *displayed* changed.
Verified against the audited project (id 29, balance = −₹28,760.00): the card now shows a green
"Advance Credit 28,760.00" instead of a misleading orange "-28,760.00".

## Fix RC3 — Metric Naming Consistency

Applied the two-tier convention from the request exactly:

* **Project screens keep "Customer Pending"** — `projects/view.php`'s Health Dashboard card
  (RC2 above) and `projects/statement.php`'s existing card both already say "Customer Pending";
  no change needed there.
* **Sales / Payments screens now say "Invoice Pending"**:
  * `sales/index.php` — the 2.0C-added "Customer Pending" column header (both Active/Pending and
    Paid tables) renamed to "Invoice Pending". Underlying value (`sales.balance_amount`)
    unchanged.
  * `payments/create.php` / `payments/edit.php` — the invoice-picker dropdown's inline
    "Pending: X" text and the detail table's "Amount Pending" row label both renamed to "Invoice
    Pending". `id="detPending"` and all JS reading/writing that id are unchanged.

No model field, controller variable, or JS variable was renamed anywhere — only the label text
shown to the user.

## Fix RC4 / RC5 — Remove Duplicate Presentation Logic

**Verified, no edit needed in `projects/view.php`.** Re-checked the file line by line:

* The Contract/Billing-status badge (`$csLabel`/`$csBg`/`$csColor`) already derives solely from
  `financial_summary['billing_progress_percent']` — no independent re-derivation of billing
  percentage from raw totals.
* `projects/view.php` does not display Net Profit at all (it was dropped from the visible KPI set
  back in the 2.0B/2.0C Project Health Dashboard redesign) — there is no duplicate net-profit
  expression in this file to remove.
* The "Customer Pending" figure now (post-RC2) reuses the exact same field and threshold pattern
  as `statement.php`, removing the *presentational* divergence between the two pages for this
  metric.

**Confirmed still open, intentionally deferred (per the accepted scope decision above):**
`projects/statement.php`'s older 3-state Contract badge ("Over Contract by ₹X" / "Near Contract" /
"Within Contract", computed directly from `$csBilled` vs `$csValue`) remains architecturally
separate from `view.php`'s 2-state badge (`billing_progress_percent`-driven). They will still
diverge for a project billed past 100% of contract value. Fixing this requires editing
`statement.php` and/or consolidating the controller's two Net Profit expressions
(`Projects::view()` vs `Projects::_buildStatementData()`) — both out of this release's "no
controller/model changes" boundary. Recommend a dedicated future release for that consolidation.

---

## Verified-unchanged calculation entry points

* `ProjectModel::getFinancialSummary()`, `ProjectModel::getPaymentStatus()`,
  `ProjectModel::getAllocatedPurchaseCost()`, `ProjectModel::getTimelineEvents()` — no line
  changed.
* `SaleModel::_recalculateSaleRow()`, `recalculatePaymentState()`, `recalculateProjectBilling()`
  — no line changed.
* `sales.status`, `sales.advance_applied`, `sales.balance_amount`, `sales.paid_amount` — written
  exclusively by `SaleModel`, unchanged; 2.0D only changes how these are *labeled* in the UI.
* DataTables filtering in `sales/index.php` (`data[3]` customer, `data[8]` status,
  `columnDefs targets: 3/8`) — preserved via the visually-hidden raw-status span (see RC1 above).
* `Projects::view()` / `Projects::_buildStatementData()` controller methods — no line changed.

## Manual verification note

`php -l` was run against all four edited files (`app/Views/sales/index.php`,
`app/Views/projects/view.php`, `app/Views/payments/create.php`, `app/Views/payments/edit.php`) —
no syntax errors. As in prior releases, a live browser check could not be completed in this
session due to the pre-existing local Apache/environment issue (500/403 on untouched routes,
unrelated to this change). Recommend a manual pass once the local server issue is resolved:

* `sales?tab=active` and `sales?tab=completed` — confirm new tab labels, badge wording, and that
  the Status/Customer filter dropdowns still correctly filter rows (specifically: selecting
  "Partial Payment" or "Payment Pending" in the status filter should still show only the matching
  rows, despite the badge text no longer containing the raw enum value).
* `projects/view/29` (or any project with a negative `outstanding_collection_balance`) — confirm
  the Customer Pending card now shows a green "Advance Credit ₹X" instead of a raw negative
  orange number.
* `payments/create/<sale_id>` and `payments/edit/<payment_id>` — confirm the invoice dropdown and
  detail table now say "Invoice Pending" and that the max-amount JS logic (`$('#amountInput').attr('max', ...)`)
  still works unchanged.
