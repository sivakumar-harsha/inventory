# Release 2.1B — Acceptance Test Plan

Manual pass to run once the local server is reachable. Every check below traces back to a Phase in
`RELEASE_2_1B_BASELINE.md`; none require new test data beyond a project with at least one paid, one
partial, and one unpaid invoice, plus a project with advance credit (e.g. project id 29 used in the
2.0C audit) if still present.

## Phase 1 — Billing Overview

1. Open `projects/view/<id>` for a project with invoices in more than one payment status.
   - [ ] "Billing Overview" card appears below the existing "Project Progress Summary" card.
   - [ ] Contract Value / Total Invoiced / Total Customer Paid always show; values match the
     existing Health Dashboard cards above (same source field — should never disagree).
   - [ ] On a project with `outstanding_collection_balance > 0`: Customer Pending card appears;
     Advance Credit Remaining does not.
   - [ ] On a project in advance credit (`outstanding_collection_balance < 0`, e.g. project 29):
     Advance Credit Remaining card appears instead, showing the absolute value; Customer Pending
     does not.
   - [ ] On a project with `remaining_billable_value > 0`: Remaining To Bill card appears with the
     correct rupee amount; on a fully-billed project it does not appear.
   - [ ] Contract Billing Progress % matches the existing "% billed" strip above it exactly (same
     `billing_progress_percent` field).
   - [ ] Invoice Collection Progress % is `(Total Invoiced − Customer Pending) / Total Invoiced ×
     100`; on the advance-credit project it should read **above 100%**, and the progress bar should
     not visually break or overflow the row (it's capped at 100% width in the bar, while the text
     states the true percentage — confirm both render sensibly).
   - [ ] Invoice Count Summary's four counters sum to the project's total invoice count, and each
     count matches a manual count of that project's invoices by status.

## Phase 2 — Payment Screen

2. Open `payments/create` (no invoice pre-selected).
   - [ ] Step 1 project dropdown lists every project; "-- All Projects --" is selected by default
     and every invoice card is visible.
   - [ ] Selecting a project in Step 1 hides cards for every other project; selecting "--All
     Projects--" again shows all cards.
   - [ ] Each card shows Invoice No, Invoice Date, Invoice Total, Advance Applied, Paid Amount,
     Invoice Pending, and an Invoice Payment Status badge with 2.0D wording (Invoice Paid / Partial
     Payment / Payment Pending).
   - [ ] A fully paid invoice's card still renders (for context) but its Record Payment button is
     disabled.
   - [ ] Clicking Record Payment on a card scrolls to Step 3, highlights that card
     (`is-selected`), populates the detail table (`Invoice Total`/`Advance Applied`/`Amount
     Received`/`Invoice Pending`), and sets the Amount field's `max` attribute to the invoice's
     pending amount.
   - [ ] Submitting a valid amount records the payment exactly as before (redirect + success flash,
     `payments` table row inserted, `sales.status`/`balance_amount` recomputed) — confirms
     `Payments::store()` was not altered in behavior.
   - [ ] Submitting an amount greater than pending is rejected with the existing error message
     (`Payments::store()`'s `> $pending + 0.01` check still fires).
3. Open `payments/create/<sale_id>` (pre-selected via route param, e.g. from a "Record Payment"
   link elsewhere).
   - [ ] Behaves identically; `selected_sale_id` still pre-selects the corresponding option in the
     hidden select (confirm via the detail table populating on page load without any click).
4. Open `payments/edit/<payment_id>`.
   - [ ] Step 1 defaults to the payment's own project already selected.
   - [ ] The invoice currently attached to this payment shows as pre-selected
     (`is-selected` card, button reads "Selected") and its Invoice Pending figure includes this
     payment's own amount added back (matches pre-2.1B behavior).
   - [ ] Re-assigning the payment to a different invoice (click that invoice's Record Payment
     button) and saving updates both invoices' `paid_amount`/`balance_amount`/`status` correctly
     (confirms `recalculatePaymentState()` still runs for both the old and new sale id, unchanged
     from before).

## Phase 3 — Customer Payment History

5. On `projects/view/<id>` for a project with both an advance and at least one payment:
   - [ ] "Customer Payment History" card lists rows newest-first.
   - [ ] Project Advance row (if `advance_amount > 0`) is visually distinguished (different badge)
     from Invoice Payment rows.
   - [ ] Every `payments` row for the project's invoices appears exactly once, with the correct
     invoice number in "Applied To."
   - [ ] On a project with no advance and no payments yet, the card shows "No payments recorded
     yet" instead of an empty table.

## Phase 4 — Recent Project Activity

6. Same project view page:
   - [ ] "Recent Project Activity" card lists Invoice Created, Payment Received, Purchase Added,
     and Expense Added events, newest-first.
   - [ ] Project Advance does **not** appear in this feed (it's exclusive to Phase 3's card).
   - [ ] No Stock Return entry appears anywhere (expected — the feature doesn't exist in the
     codebase; nothing to toggle).
   - [ ] Counts of each event type match the project's actual `sales`/`payments`/`purchases`/
     `expenses` row counts.

## Regression checks (things that must NOT have changed)

- [ ] `sales/index.php`, `sales/view.php` — untouched, still show 2.0D wording.
- [ ] Project Health Dashboard KPI row and the original "% billed" strip on `projects/view.php` —
  still present, values unchanged, positioned above the new Billing Overview section.
- [ ] `projects/statement/<id>`, `statement-export`, `statement-pdf` — unaffected (their controller
  method, `_buildStatementData()`, was not touched).
- [ ] Dashboard (`app/Views/dashboard/*`) — unaffected, not opened for this release.
- [ ] No new database migration is present in `app/Database/Migrations/`.
- [ ] `php -l` clean on all five touched files (already confirmed in this session; re-run after any
  further edits): `app/Controllers/Projects.php`, `app/Controllers/Payments.php`,
  `app/Views/projects/view.php`, `app/Views/payments/create.php`, `app/Views/payments/edit.php`.
