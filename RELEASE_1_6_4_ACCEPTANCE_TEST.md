# Release 1.6.4 — Apply Project Advance to Invoice Payments: Acceptance Test

Date: 2026-08-28
Method: A temporary spark command (`test:advance`, created and removed within this release) built a real test project + invoices + payments via direct table inserts, then called the actual `SaleModel::recalculateProjectBilling()` / `recalculatePaymentState()` methods used by `Sales.php`/`Payments.php`/`Projects.php` in production — i.e. the same code path the controllers call, not a re-implementation of the math for testing purposes. All test data (project #17 and its sales/payments) was deleted at the end of the run; the database is unchanged from before this session except for the new `advance_applied` column and the backfilled values on real historical data (recorded in `RELEASE_1_6_4_BASELINE.md`).

---

## Scenario 1 — Advance smaller than the only invoice

Setup: Project advance ₹10,000. One invoice, ₹40,000 (2026-08-05).

Expected: Pending = ₹40,000 − ₹10,000 = ₹30,000, status PARTIAL (advance covers part of it, no payment yet).

**Result: PASS** — `advance_applied=10000.00, paid_amount=0.00, balance_amount=30000.00, status=PARTIAL`.

---

## Scenario 2 — Advance larger than the first (chronological) invoice; FIFO rollover

Setup: same project. A second invoice is added **dated earlier** than Scenario 1's invoice: ₹6,000 on 2026-08-02 (Scenario 1's invoice is 2026-08-05).

Expected (Rule 3, FIFO by `sale_date`): the ₹6,000 invoice, being chronologically first, absorbs ₹6,000 of the ₹10,000 advance and becomes fully PAID. The remaining ₹4,000 of advance rolls forward to the next-oldest invoice (the ₹40,000 one from Scenario 1), reducing its `advance_applied` from ₹10,000 to ₹4,000.

**Result: PASS**
- Oldest invoice (₹6,000, dated 08-02): `advance_applied=6000.00, balance_amount=0.00, status=PAID`.
- Next invoice (₹40,000, dated 08-05): `advance_applied=4000.00, balance_amount=36000.00, status=PARTIAL` — automatically recomputed by the same `recalculateProjectBilling()` call, confirming the FIFO re-pass correctly re-derives *every* invoice's allocation from the full ordered set each time, not just the newly-added one.

---

## Scenario 3 — Multiple invoices, partial payments, FIFO allocation verified end-to-end

Setup: same project, now three invoices ordered by `sale_date`: ₹6,000 (08-02), ₹40,000 (08-05), ₹20,000 (08-20). Advance ₹10,000 is fully consumed by the first two (₹6,000 + ₹4,000); the third gets none.

**Result: PASS** — third invoice: `advance_applied=0.00, balance_amount=20000.00, status=UNPAID`.

A payment of ₹15,000 is then recorded against the second invoice (pending was ₹36,000 after advance):

**Result: PASS** — `advance_applied=4000.00 (unchanged), paid_amount=15000.00, balance_amount=21000.00, status=PARTIAL`. Confirms Rule 5's "pending already excludes advance" — the payment was validated and applied against ₹36,000 of room, not the full ₹40,000 invoice total.

---

## Scenario 4 — Edit payment

The ₹15,000 payment above is edited to ₹21,000 (simulating `Payments::update()`, which re-derives `paid_amount` from `SUM(payments.amount)` rather than incrementally adjusting it).

Expected: `paid_amount=21000.00`, pending = ₹40,000 − ₹4,000 (advance, untouched) − ₹21,000 = ₹15,000, status PARTIAL (not yet fully paid — this invoice needs ₹36,000 total to clear, not ₹21,000).

**Result: PASS** — `advance_applied=4000.00 (unchanged — Rule 6: never duplicated, never removed), paid_amount=21000.00, balance_amount=15000.00, status=PARTIAL`. (Note: an earlier draft of this test incorrectly expected this edit alone to fully settle the invoice — corrected once the arithmetic was checked; ₹4,000 advance + ₹21,000 paid = ₹25,000 against a ₹40,000 invoice leaves ₹15,000 pending, which is exactly what the system computed. This is the code behaving correctly, not a defect.)

---

## Scenario 5 — Delete payment

The ₹21,000 payment is deleted (simulating `Payments::delete()`).

Expected: `paid_amount` reverts to ₹0 (derived fresh from `SUM(payments.amount)`, now empty for this sale), `advance_applied` stays at ₹4,000 (Rule 6: "Advance stays attached to the project"), pending reverts to ₹36,000, status PARTIAL (advance alone still covers part of it).

**Result: PASS** — `advance_applied=4000.00, paid_amount=0.00, balance_amount=36000.00, status=PARTIAL`.

---

## Scenario 6 — Project Statement Outstanding Collection Balance matches invoice pending totals

Expected (Rule 8): `ProjectModel::getFinancialSummary()`'s `outstanding_collection_balance` (`total_billed − advance_amount − total_paid`, unchanged formula) equals `SUM(sales.balance_amount)` across the project's invoices, since the FIFO allocation is invariant under aggregation.

**Result: PASS** — both sides computed to ₹56,000.00 (₹6,000+₹40,000+₹20,000 billed − ₹10,000 advance − ₹0 paid = ₹56,000; sum of the three invoices' individual `balance_amount` = ₹0+₹36,000+₹20,000 = ₹56,000).

---

## Rule 5 — Overpayment rejection

Third invoice's pending was ₹20,000. An attempted payment of ₹25,000 (pending + ₹5,000) was checked against the same `amount > pending + 0.01` guard used in `Payments::store()`/`update()`.

**Result: PASS** — correctly identified as rejectable (`correctly rejected=YES`).

---

## Regression checks (per Rule 9)

| Area | Check | Result |
|---|---|---|
| Dashboard "Total Outstanding" | Reads `SUM(sales.balance_amount)` — automatically reflects advance now; no code touched | **PASS** (verified by code inspection — see dependency analysis §5; no separate advance subtraction exists anywhere else in Dashboard.php, so no double-count is possible) |
| Sales report / export / PDF | Reads `s.total_amount/paid_amount/balance_amount/status` directly, no separate advance logic | **PASS** — same reasoning |
| Payments report / list | `payments/index.php` shows raw `payments.amount`, unrelated to advance | **PASS** — unaffected, not touched |
| Project View | `getFinancialSummary()` formula unchanged (Rule 8); verified live on Project 16 (`INV/28/08/26`: advance_applied=10000.00, balance=92751.20, matches brief's worked example exactly) | **PASS** |
| Project Statement | Same `getFinancialSummary()` + unchanged `getTimelineEvents()` | **PASS** |
| Profit & Loss | Reads `total_amount` for revenue only, never `balance_amount`/`status`/`advance_amount` | **PASS** — unaffected, not touched |

## Summary

All Phase C scenarios (1-6) and the Rule 5 validation check **PASS**. All Rule 9 regression areas **PASS** by direct verification or code-path inspection (none of them contain independent advance-subtraction logic that this release's change could double up with).
