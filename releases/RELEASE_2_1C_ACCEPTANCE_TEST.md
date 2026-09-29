# Release 2.1C — Fix Project Payment Status & Payment Screen Workflow — Acceptance Test Plan

Manual pass to run once the local server is reachable, using existing project/invoice data — no new
test data required beyond what's already noted per check.

## Bug 1 — Project Payment Status

1. Open `projects/view/<id>` for "shreeya clinic chennai" (Contract Value ₹25,86,869, Total Invoiced
   ₹21,240, one invoice paid, Customer Pending shown as Advance Credit).
   - [ ] Work Status still shows `ACTIVE` (unrelated field, untouched).
   - [ ] Customer Pending / Advance Credit card still shows Advance Credit ₹28,760 (unrelated —
     `getFinancialSummary()` was not modified).
   - [ ] **Project Payment Status now shows `PARTIAL`**, not `PAID`.
2. Open `projects` (list view), same project's row.
   - [ ] Payment Status badge also shows `PARTIAL` — matches the detail page exactly (single source
     of truth, no drift between the two pages).
3. Find or create a project with zero invoices.
   - [ ] Both the list and detail page show `PENDING` (not the old `N/A`/blank), styled with the new
     gray `.badge-pending` class.
4. Find a project that is fully invoiced (Remaining To Bill = 0) and fully collected (Customer
   Pending = 0, not in Advance Credit).
   - [ ] Both pages show `PAID`.
5. Find a project that is fully invoiced but the customer still owes money (Customer Pending > 0,
   Remaining To Bill = 0).
   - [ ] Both pages show `PARTIAL` (not `PAID`) — confirms the "fully invoiced but still owed"
     branch of the rule, distinct from case 1's "not fully invoiced" branch.
6. On the project detail page, for a project that is `COMPLETED` work-status AND now correctly reads
   `PAID`:
   - [ ] The green "Project work is completed and all invoices are settled" banner still appears
     (banner logic unchanged, just now driven by a correct `payment_status`).
7. For a project that is `COMPLETED` but payment status is `PARTIAL` under the new rule (e.g. it was
   previously misreported as `PAID`):
   - [ ] The banner now correctly switches to the amber "Customer payment is still pending" warning —
     this is the direct fix for the reported bug, since this project would have shown the false
     green banner before.

## Bug 2 — Payment Create/Edit Screen

8. Open `payments/create` (no invoice pre-selected).
   - [ ] Step 1 dropdown reads "-- Select Project --" and lists **only Active projects**, sorted
     alphabetically. A Completed project's name does not appear anywhere in the list.
   - [ ] Step 2 shows no invoice rows and displays "Select a project to view invoices." — no invoice
     data is visible before a project is chosen.
9. Select an Active project with at least one invoice.
   - [ ] Only that project's invoices appear (matching `data-project-id`); the placeholder message is
     gone.
   - [ ] Table columns are Invoice No, Date, Invoice Total, Advance Applied, Paid Amount, Invoice
     Pending, Status, Action — unchanged from the prior release.
   - [ ] A fully paid invoice still appears in the list (for context) with its Record Payment button
     disabled; a pending/partial invoice's button is enabled.
10. Re-open the Step 1 dropdown and select the blank option again.
    - [ ] Invoice rows are hidden again and the "Select a project to view invoices." placeholder
      reappears (round-trips cleanly, not just one-directional).
11. Click Record Payment on a visible row.
    - [ ] Scrolls to Step 3, highlights the row (`is-selected`), populates the detail table
      (`Invoice Total`/`Advance Applied`/`Amount Received`/`Invoice Pending`), and sets the Amount
      field's `max` to the invoice's pending amount — identical to pre-2.1C behavior.
12. Submit a valid payment amount.
    - [ ] Recorded exactly as before (redirect + success flash, `payments` row inserted,
      `sales.status`/`balance_amount` recomputed) — confirms `Payments::store()` behavior is
      unchanged.
13. Submit an amount greater than pending.
    - [ ] Rejected with the existing error message — confirms `Payments::store()`'s validation is
      unchanged.
14. Confirm a Completed project genuinely cannot be selected: it does not appear anywhere in the Step
    1 dropdown's option list (view-source check is sufficient — there is no way to "try" selecting an
    option that was never rendered).
15. Open `payments/edit/<payment_id>` for a payment whose invoice belongs to an **Active** project.
    - [ ] Step 1 dropdown opens with that project already selected.
    - [ ] Step 2's invoice list is already filtered to that project on page load — no manual
      reselect needed, and the placeholder message never flashes.
    - [ ] The invoice currently attached to this payment shows as pre-selected (`is-selected` row,
      button reads "Selected") and its Invoice Pending figure includes this payment's own amount
      added back (`$selfAmount`), matching pre-2.1C behavior exactly.
16. Open `payments/edit/<payment_id>` for a payment whose invoice belongs to a project that is now
    **Completed** (if such data exists; otherwise mark one project Completed temporarily to verify,
    then revert it).
    - [ ] That Completed project still appears in the dropdown for this one edit screen (appended
      exception) and is pre-selected; its invoice list is filtered and shown correctly.
    - [ ] The dropdown's other options are still Active-only — the Completed project is present only
      because it is this payment's own project, not because the filter was silently dropped.
17. On the edit screen, re-assign the payment to a different invoice under a different Active project
    (change Step 1's project, click that invoice's Record Payment button) and save.
    - [ ] Both the old and new invoices' `paid_amount`/`balance_amount`/`status` update correctly —
      confirms `recalculatePaymentState()` still runs for both, unchanged from before.

## Regression checks (things that must NOT have changed)

- [ ] `ProjectModel::getFinancialSummary()` — byte-for-byte unchanged (diff the method body against
  `RELEASE_2_1B_BASELINE.md`'s description if in doubt).
- [ ] `SaleModel::recalculatePaymentState()` — not opened for editing in this release.
- [ ] `Payments::store()`, `update()`, `delete()` — not opened for editing in this release.
- [ ] `app/Views/sales/index.php`, `sales/view.php` — untouched, still show invoice-level status
  wording (Invoice Paid / Partial Payment / Payment Pending), which is a separate concept from
  project-level Payment Status and was never part of this bug.
- [ ] `projects/statement/<id>`, `statement-export`, `statement-pdf` — unaffected;
  `_buildStatementData()` was not touched and never referenced payment status.
- [ ] Dashboard (`app/Views/dashboard/*`) — unaffected, does not show project payment status.
- [ ] No new database migration is present in `app/Database/Migrations/`.
- [ ] `php -l` clean on all seven touched files (already confirmed in this session; re-run after any
  further edits): `app/Models/ProjectModel.php`, `app/Controllers/Projects.php`,
  `app/Controllers/Payments.php`, `app/Views/projects/index.php`, `app/Views/projects/view.php`,
  `app/Views/payments/create.php`, `app/Views/payments/edit.php`.
