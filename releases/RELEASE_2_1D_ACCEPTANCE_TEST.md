# Release 2.1D — Payment Module UX Fix — Acceptance Test Plan

Manual pass to run once the local server is reachable, using existing project/invoice data — no new
test data required.

## Payments Index (Problem 1)

1. Open `payments` (index).
   - [ ] Columns are exactly `#`, Invoice, Project, Customer, Amount, Date, Method, Actions.
   - [ ] No project dropdown, invoice table, or billing figures (Advance/Pending/Status) appear on
     this page — it remains a pure payment ledger.
   - [ ] "Record Payment" button still links to `payments/create`.

## Payment Create (Problems 2, 3, 4)

2. Open `payments/create`.
   - [ ] Step 2 shows the centered placeholder — icon, "Select a project to view invoices.", and
     "Choose an active project above." — with **no table visible** (view page source: `#invoiceTableWrap`
     is `display:none`, not merely an empty `<tbody>`).
   - [ ] Step 1 dropdown reads "-- Select Project --" and lists only Active projects, sorted
     alphabetically; no Completed project appears.
3. Select an Active project with at least one invoice.
   - [ ] Placeholder disappears; the invoice table appears showing **only** that project's invoices.
   - [ ] Table headers read exactly: Invoice, Date, Total, Advance, Paid, Pending, Status, Action.
   - [ ] A fully paid invoice is visible with badge "Invoice Paid" and its Record Payment button
     disabled.
   - [ ] A pending/partial invoice is visible with the correct badge and an enabled Record Payment
     button.
4. Re-select the blank "-- Select Project --" option.
   - [ ] Table disappears again; placeholder reappears (round-trips cleanly).
5. Select a different Active project.
   - [ ] Only that project's invoices show; the previous project's invoices are not present.
6. Click Record Payment on an enabled row.
   - [ ] Scrolls to Step 3, highlights the row, populates the detail table
     (`#detTotal`/`#detAdvance`/`#detPaid`/`#detPending`), sets `#amountInput`'s `max` — unchanged
     from before.
7. Submit a valid amount; then, separately, an amount exceeding pending.
   - [ ] Recorded successfully / rejected with the existing error message respectively — confirms
     `Payments::store()` behavior is unchanged.

## Payment Edit

8. Open `payments/edit/<payment_id>` for a payment on an Active project.
   - [ ] Step 1 opens with that project already selected.
   - [ ] Step 2 shows the invoice table immediately (not the placeholder), already filtered to that
     project.
   - [ ] The invoice currently attached to this payment is highlighted (`is-selected`, button reads
     "Selected"), with its Invoice Pending figure including this payment's own amount added back.
   - [ ] Table headers match the new short wording (Invoice/Date/Total/Advance/Paid/Pending/Status/Action).
9. On the same edit screen, select a different invoice (click its Record Payment button) and switch
   projects if desired.
   - [ ] The newly clicked row becomes highlighted instead; detail table and `#amountInput` max
     update accordingly.
10. Save the edit after re-assigning to a different invoice.
    - [ ] Both the old and new invoices' `paid_amount`/`balance_amount`/`status` update correctly —
      confirms `recalculatePaymentState()` still runs for both, unchanged.
11. Open `payments/edit/<payment_id>` for a payment whose project is Completed (if such data exists).
    - [ ] That Completed project still appears in the dropdown for this one edit screen (2.1C's
      exception, unaffected by this release) and its invoices are shown filtered/highlighted as above.

## Regression checks (things that must NOT have changed)

- [ ] `app/Controllers/Payments.php` — no diff from before this release (Problems 1/3/4 were already
  satisfied; only the two view files changed).
- [ ] `Payments::store()`, `update()`, `delete()` — not opened for editing in this release.
- [ ] `SaleModel::recalculatePaymentState()`, `ProjectModel::*` — not opened for editing.
- [ ] Hidden `#saleSelect`, `#saleDetail`, `#detTotal`/`#detAdvance`/`#detPaid`/`#detPending`,
  `#amountInput` — same IDs, same `change`-handler JS, byte-for-byte unchanged.
- [ ] `.record-payment-btn` click handler — unchanged (still just sets `#saleSelect` and fires
  `change`).
- [ ] No new database migration is present in `app/Database/Migrations/`.
- [ ] `php -l` clean on both touched files (already confirmed in this session; re-run after any
  further edits): `app/Views/payments/create.php`, `app/Views/payments/edit.php`.
