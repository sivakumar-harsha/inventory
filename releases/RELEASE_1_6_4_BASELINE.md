# Release 1.6.4 — Apply Project Advance to Invoice Payments: Implementation Baseline

Date: 2026-08-28
Scope: Implements approved business Rules 1-9 from the release brief, following the read-only findings in `RELEASE_1_6_4_DEPENDENCY_ANALYSIS.md`. No approved rule was changed during implementation.

---

## What changed

### Database

- **New migration**: `app/Database/Migrations/2026-08-28-000000_AddSalesAdvanceApplied.php`
  - Adds `sales.advance_applied DECIMAL(15,2) NOT NULL DEFAULT 0`, positioned after `total_amount`.
  - `down()` drops the column — rollback-safe, touches nothing else.
  - Applied: `php spark migrate` ran cleanly.
  - **One-time backfill**: a temporary spark command (`backfill:advance`, created and removed within this release) called `SaleModel::recalculateProjectBilling()` for every project that has sales, bringing all historical `sales.advance_applied`/`paid_amount`/`balance_amount`/`status` in line with the new rule. Verified live: Project 16's invoice `INV/28/08/26` (total ₹102,751.20, project advance ₹10,000) now shows `advance_applied=10000.00`, `balance_amount=92751.20`, `status=PARTIAL` — an exact match for the release brief's worked example.

### Models

- **`app/Models/SaleModel.php`** — two new public methods, sharing one private helper (Decision: keep the FIFO pass and the payment-only recompute DRY):
  - `recalculateProjectBilling(int $projectId)` (Rules 1-3): loads the project's `advance_amount`, loads all its sales ordered `sale_date ASC, id ASC` (FIFO, oldest first), and for each sale in order applies `min(remaining advance, that invoice's total_amount)` — capped, never negative, never re-derived from what's already been paid, so re-running it any number of times is idempotent and never "reapplies" or "removes" advance (Rule 2).
  - `recalculatePaymentState(int $saleId)` (Rule 6): re-reads the sale's current `advance_applied` (left untouched — payments never re-trigger FIFO) and recomputes everything else from source.
  - `_recalculateSaleRow()` (private, shared by both): derives `paid_amount` fresh as `SUM(payments.amount) WHERE sale_id = ?` (no longer incremental ± math — Rule 6's "Current Payments" is now literally a live SUM, immune to drift from a missed/duplicated update), then `balance_amount = max(0, total_amount − advance_applied − paid_amount)` and `status` per Rule 7's three-way rule (PAID when pending ≤ 0, PARTIAL when pending > 0 but `advance_applied > 0 OR paid_amount > 0`, else UNPAID).
  - `getPendingAmount(int $saleId)`: thin read of the current `balance_amount` — used for Rule 5 validation call sites, documents intent at the call site rather than reading the raw column name directly.

### Controllers

- **`app/Controllers/Sales.php`**
  - `store()`: after the existing insert/stock-OUT logic, calls `SaleModel::recalculateProjectBilling($projectId)` inside the same transaction — a new invoice can shift how much advance every other invoice in the project is entitled to (Rule 3).
  - `update($id)`: captures `$oldProjectId` before mutating; the old inline `paid_amount`-only balance/status recompute (lines removed) is replaced by a call to `recalculateProjectBilling()` for the new `project_id`, and — if the invoice moved to a different project — for the old `project_id` too, since both projects' FIFO allocations are now stale.
  - `delete($id)`: captures the sale's `project_id` before deleting, then re-runs `recalculateProjectBilling()` for that project — freeing the advance the deleted invoice had absorbed so it flows to the next-oldest invoice (Rule 3/6).
- **`app/Controllers/Projects.php`**
  - `update($id)`: after saving the project (which may have changed `advance_amount`), calls `recalculateProjectBilling((int) $id)` — the only other place `advance_amount` can change.
  - **Not changed**: `Projects::view()`/`_buildStatementData()`/`getPaymentStatus()`/`index()`'s payment-status aggregation — see "Explicitly not touched" below.
- **`app/Controllers/Payments.php`** — rewritten to eliminate the three duplicated incremental balance/status blocks (store/update/delete each had their own copy):
  - `create()` / `edit()`: sale list query now also selects `advance_applied`/`paid_amount` (in addition to the already-selected `total_amount`/`balance_amount`) so the view can show all four figures from Rule 4 without an extra query. `create()`'s `WHERE s.status != 'PAID'` filter is unchanged in text but now correctly excludes invoices that are fully covered by advance alone (status is advance-aware).
  - `store()` (Rule 5): looks up the target sale, validates `amount <= balance_amount + 0.01` (small float-safety epsilon) *before* writing anything; on failure, redirects back with the invoice's actual pending amount in the error message. On success, inserts the payment then calls `recalculatePaymentState($saleId)` — no more inline balance math.
  - `update($id)` (Rules 5-6): captures the old `sale_id`/`amount`; computes `pendingBeforeThisPayment` as `target_sale.balance_amount + old_amount` when the sale is unchanged (giving back the room the old payment was occupying) or simply `target_sale.balance_amount` when the payment is being moved to a different invoice; validates the new amount against that before writing; updates the payment row; calls `recalculatePaymentState()` for the new sale, and additionally for the old sale if the invoice was changed. This replaces the previous "reverse old effect, then reapply new effect" pattern with a single derive-from-source recompute per affected sale (Rule 6).
  - `delete($id)`: captures the payment's `sale_id` before deleting the row, then calls `recalculatePaymentState()` — `advance_applied` is never touched by a payment deletion (Rule 6: "Never remove advance").

### Views

- **`app/Views/payments/create.php`**: invoice dropdown options now carry `data-total`/`data-advance`/`data-paid`/`data-pending` attributes (label text changed from "Balance" to "Pending" for clarity); a new detail panel (Invoice Total / Advance Applied / Amount Already Paid / Amount Pending — Rule 4) and the Amount input's `max` attribute are populated/updated by a small inline script on `change` (and on page load, for a pre-selected sale). No server round-trip added.
- **`app/Views/payments/edit.php`**: same detail panel and script. The currently-selected invoice's `data-pending`/label add back this payment's own `amount` (since the stored `balance_amount` already reflects it having been applied) — client-side mirror of the server-side `pendingBeforeThisPayment` logic in `Payments::update()`, so the max shown to the user matches what the server will actually accept.

---

## Explicitly not touched

Per Rule 8/9 and the dependency analysis (§3): `ProjectModel::getFinancialSummary()` (Outstanding Collection Balance formula), `ProjectModel::getPaymentStatus()`, `Projects::index()`'s project-level payment-status badge aggregation, `ProjectModel::getTimelineEvents()` (Project Statement timeline), `Sales::create()`/`edit()` views' Billing Tracker AJAX (`Sales::projectFinancialSummary()`), Dashboard controller code, Reports controller code, all Purchase/Stock/GST logic — none of these were modified. Each of them reads `sales.balance_amount`/`sales.status` (or, for the project-aggregate formula, `sales.total_amount`/`sales.paid_amount`/`projects.advance_amount` directly) as stored values, so they automatically reflect the corrected, advance-aware figures without any code change (dependency analysis §5).

---

## Acceptance Testing (performed live, via a temporary spark command exercising the real `SaleModel` methods, then all test data deleted)

See `RELEASE_1_6_4_ACCEPTANCE_TEST.md` for the full scenario-by-scenario log.

## Summary

| Check | Result |
|---|---|
| Rule 1 — Advance stays a separate project event, not a payments row | PASS |
| Rule 2 — Advance applied once, never re-applied/removed | PASS |
| Rule 3 — FIFO allocation by sale_date | PASS |
| Rule 4 — Payment screen shows Invoice Total / Advance Applied / Paid / Pending | PASS |
| Rule 5 — Payment validated against pending, overpayment rejected | PASS |
| Rule 6 — Edit/delete recompute from source, advance never duplicated/removed | PASS |
| Rule 7 — Status computed after advance allocation | PASS |
| Rule 8 — Timeline & Outstanding Collection Balance formula unchanged | PASS |
| Rule 9 — Dashboard/Reports/Project pages continue working, no double-count | PASS |
| No unapproved business-rule changes | PASS |

**Overall: PASS.** Implementation matches Rules 1-9 as specified in the release brief.
