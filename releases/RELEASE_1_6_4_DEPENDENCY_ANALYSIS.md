# Release 1.6.4 — Apply Project Advance to Invoice Payments

## Dependency Analysis (read-only — Phase A)

Date: 2026-08-28
Status: **ANALYSIS ONLY at time of writing. No code changed in this section.**

---

## 0. The problem, precisely

`projects.advance_amount` exists and is already used in exactly one place: `ProjectModel::getFinancialSummary()`, which computes a **project-level aggregate**:

```
outstanding_collection_balance = total_billed - advance_amount - total_paid
```

This is correct at the project level and is **not changing** (Rule 8). But nothing today applies the advance at the **invoice level**. `sales.balance_amount` / `sales.status` are computed purely from `total_amount` and `paid_amount`, with zero awareness of the project's advance. The Payment screen, Sales report, Sales list, and Dashboard "Total Outstanding" all read `sales.balance_amount` / `sales.status` directly — so today a customer with a fully-advance-covered invoice still shows as UNPAID with a full balance on every one of those screens.

---

## 1. `sales.total_amount` / `paid_amount` / `balance_amount` — how they're maintained today

Schema (`aandainventory_db.sales`): `total_amount decimal(15,2)`, `paid_amount decimal(15,2)`, `balance_amount decimal(15,2)`, `status enum('UNPAID','PARTIAL','PAID')`.

- **`Sales::store()`** (`app/Controllers/Sales.php:150-162`): sets `total_amount` from summed `gst_calculate_line()` results, `paid_amount = 0`, `balance_amount = total_amount`, `status = 'UNPAID'`. No `advance_amount` read anywhere in this path.
- **`Sales::update($id)`** (`app/Controllers/Sales.php:246-362`): recomputes `total_amount` from the (possibly changed) line items; **preserves existing `paid_amount`** (comment: "Preserve existing payments — only total & balance change"); recomputes `balance_amount = newTotal - paidAmount` and `status` from that pair. Again, no advance involved.
- **`Sales::delete($id)`** (`app/Controllers/Sales.php:466-476`): deletes `stock_ledger`, `sale_items`, `payments`, and the `sales` row itself — no balance recalculation needed since the row is gone, but **other sales in the same project are never told the advance they may now be entitled to has changed** (this is the gap Rule 3/6 close).
- **`paid_amount` is maintained incrementally**, not derived: `Payments::store/update/delete` (`app/Controllers/Payments.php`) each do `paid_amount = paid_amount ± amount` directly against the row that existed at that instant, rather than re-summing `payments.amount`. This is a drift risk (any missed/duplicated update permanently skews the column) but is out of scope to "fix" wholesale — however, see §6, where switching to a derived `SUM(payments.amount)` is the natural way to satisfy Rule 6 ("Recalculate using... Current Payments") without extending the existing reverse/reapply pattern to now also juggle advance.

---

## 2. `Payments::store()` / `update()` / `delete()` — exact current balance/status math

All three (`app/Controllers/Payments.php:42-195`) share one formula, duplicated three times:

```php
$newPaid    = $sale['paid_amount'] ± $amount;
$newBalance = $sale['total_amount'] - $newPaid;         // clamped to 0 in store/update, not in delete's mirror
$status = $newPaid >= $sale['total_amount'] ? 'PAID' : ($newPaid > 0 ? 'PARTIAL' : 'UNPAID');
```

`store()` has **no validation at all** — it will happily accept `amount` greater than `balance_amount` and drive `balance_amount` negative (only clamped to 0, so effectively silently swallowed, but the payment row itself still records the excess). `update()`'s reverse-then-reapply pattern only reads `payment.amount` (the row already in the DB) to "undo" the old effect, then reapplies — this is exactly the incremental-math pattern flagged above as fragile, and it is the pattern Rule 6 explicitly asks to replace with a derive-from-source recomputation.

None of the three methods currently touch `projects.advance_amount` or any other sale in the same project.

---

## 3. Where payment status (UNPAID/PARTIAL/PAID) is calculated

Two independent places, today based on different pairs of numbers:

1. **Per-invoice** (`sales.status`): `Payments.php` (above) and `Sales::store()/update()` — from `paid_amount` vs `total_amount` only.
2. **Per-project** (badge on Project index/view, `ProjectModel::getPaymentStatus()` and the inline aggregation in `Projects::index()` lines 57-68): from `SUM(sales.total_amount)` vs `SUM(sales.paid_amount)` only — **also** advance-unaware today.

Rule 7 only asks for the **per-invoice** status to become advance-aware. The **per-project** status/badge is not named in the approved rules or in the "Update" sections, and Rule 8 explicitly scopes this release to "only invoice pending/payment allocation" — so `ProjectModel::getPaymentStatus()` and `Projects::index()`'s badge aggregation are **left unchanged**. This creates one deliberate, documented inconsistency: a project can show "PARTIAL" at the project-badge level while every one of its individual invoices already shows "PAID" (because advance covered them) at the invoice level. This is called out explicitly here so it isn't rediscovered later as a "bug" — it is a scope boundary, not an oversight. If desired, extending Rule 7 to the project badge would be a natural, small follow-up release.

---

## 4. Project Statement — Outstanding Collection Balance

`ProjectModel::_getFinancialSummary()`-equivalent used by the statement (`ProjectModel::getFinancialSummary()`, reused by `Projects::view()` and `Projects::_buildStatementData()`) computes `outstanding_collection_balance = total_billed - advance_amount - total_paid`, a **single project-wide subtraction**, independent of which invoice the advance is "assigned" to. This formula is invariant under any FIFO allocation order — it will always equal `SUM(sales.total_amount) - advance_amount - SUM(sales.paid_amount)` for the project regardless of how the advance is distributed invoice-by-invoice. **This means Rule 8 ("Outstanding Collection Balance formula remains unchanged") and the new per-invoice FIFO allocation are automatically consistent by construction — no reconciliation code is needed between them.** `ProjectModel::getTimelineEvents()` independently derives its own running balance from the same Advance/Sales/Payments rows and is likewise unaffected (Rule 8's "Do NOT change timeline behavior").

---

## 5. Reports / Dashboard that depend on `sales.balance_amount` / `sales.status`

| Consumer | File | Reads | Effect of this release |
|---|---|---|---|
| Dashboard "Total Outstanding" | `Dashboard.php:30` | `SUM(sales.balance_amount)` (all sales) | Becomes advance-aware automatically — this is a **correction**, not a regression: today it overstates outstanding by ignoring advances entirely. |
| Sales report + Excel/PDF export | `Reports.php` (`sales()`/`salesExport()`/`salesPdf()`, ~840-1220) | `s.total_amount`, `s.paid_amount`, `s.balance_amount`, `s.status` (filter) | Same correction, automatically, since the report reads the stored columns rather than recomputing. `total_revenue` elsewhere in Reports.php (line 55, `profitLoss()`) sums `total_amount` only — untouched. |
| Sales list / view | `sales/index.php`, `sales/view.php` | `balance_amount` | Same correction, automatically. |
| Payments create/edit | `payments/create.php`, `payments/edit.php` | `balance_amount` (shown per option; `edit.php` also derives its `max` attribute from it) | Same correction, automatically, plus new fields added per Rule 4. |
| `ProjectModel::getFinancialSummary()` | — | `advance_amount`, `SUM(total_amount)`, `SUM(paid_amount)` directly from `sales`/`projects` — **never reads `balance_amount`/`status`** | Untouched, unaffected either way (§4). |

**Key architectural conclusion driving the implementation design:** because every one of these consumers reads `sales.balance_amount` / `sales.status` as stored columns (none of them recompute pending-after-advance themselves), the correct and minimally-invasive implementation is to **make those stored columns advance-aware at the source** (recomputed inside a shared model method whenever `total_amount`, `paid_amount`, or the project's `advance_amount` changes) rather than teaching every consumer to compute pending amounts itself. This is what makes Dashboard/Reports/Sales-list "just work" per Rule 9 without touching any of those files.

---

## 6. Consequence for the implementation (preview, not yet built)

- A new persisted column, `sales.advance_applied`, is needed — the FIFO allocation is a project-wide computation (it depends on the position of *every* invoice in the project, not just one), so it cannot be derived per-row at read time without re-running the whole project's FIFO on every page load of every report. Storing it keeps every existing raw-SQL consumer correct for free (§5).
- `advance_applied` must be recomputed (full FIFO re-pass over the project's invoices, oldest `sale_date` first) whenever: a sale is created, a sale's `total_amount` or `project_id` changes, a sale is deleted, or the project's `advance_amount` changes. This means `Sales::store()/update()/delete()` and `Projects::update()` each need one new call into a shared recompute method — no business logic duplicated across them.
- `paid_amount` should switch from incremental (`± $amount`) to derived (`SUM(payments.amount) WHERE sale_id = ?`) at the same time, satisfying Rule 6's "Current Payments" language and removing `Payments::update()`'s fragile reverse/reapply dance — this is a **local simplification of an existing bug-prone pattern**, not a new business rule.
- `balance_amount`/`status` become a pure function of `(total_amount, advance_applied, paid_amount)` and can be recomputed by one shared helper called from both the FIFO pass and the (lighter-weight, advance-untouched) payment-record recompute.

No code has been changed as part of this document. Implementation follows in a subsequent commit, against the approved business rules already specified in the release brief (Rules 1-9), and is recorded in `RELEASE_1_6_4_BASELINE.md`.
