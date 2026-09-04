# Release 2.1C — Fix Project Payment Status & Payment Screen Workflow — Baseline

Implements Bug 1 and Bug 2 against the analysis in `RELEASE_2_1C_DEPENDENCY_ANALYSIS.md`. Confirmed
before any file was edited: **no schema, migration, FIFO advance allocation, payment calculation
change, `SaleModel::recalculatePaymentState()` change, `ProjectModel::getFinancialSummary()` change,
report, or export change was made.** This release is business workflow + UI filtering only.

## Files touched

| File | What changed |
|---|---|
| `app/Models/ProjectModel.php` | `getPaymentStatus()` body rewritten to the whole-contract rule (Bug 1). Signature/return type contract (`string`) unchanged; call sites unaffected. |
| `app/Controllers/Projects.php` | `index()`: removed the duplicate invoice-count status logic and its two now-unused SQL subqueries (`paid_sale_count`, `covered_sale_count`); `payment_status` now derives from the same `getFinancialSummary()` call already made per row for the Contract Value / Customer Pending columns. `view()` unchanged (already the single call site for the model method). |
| `app/Controllers/Payments.php` | `create()`/`edit()`: project dropdown query now filters `WHERE status = 'ACTIVE'`. `edit()` additionally appends the payment's own project (re-sorted alphabetically) if it was excluded by that filter, so a payment against a now-Completed project's invoice still opens with its project selected. |
| `app/Views/projects/index.php` | Badge class map: added `'PENDING' => 'pending'`. |
| `app/Views/projects/view.php` | Badge class map: added `'PENDING' => 'pending'`. The "COMPLETED and PAID" success banner already keyed off `payment_status === 'PAID'` — no change needed there. |
| `public/assets/css/style.css` | Added `.badge-pending` (gray, matching the existing `.badge-on_hold` treatment) — the only new status word introduced by Bug 1. |
| `app/Views/payments/create.php` | Step 1 dropdown default option relabeled "-- Select Project --" (was "-- All Projects --", which no longer describes what happens). JS: invoice rows start hidden behind a "Select a project to view invoices." placeholder row; the `#projectFilter` `change` handler now only reveals matching rows once a project is actually chosen, instead of showing everything by default. |
| `app/Views/payments/edit.php` | Same dropdown relabel and placeholder row. JS: the filter logic was pulled into one `applyProjectFilter()` function, run both on `#projectFilter` change **and once on page load**, so the pre-selected project's invoices are filtered immediately instead of showing every invoice until a manual reselect. |

**Not touched:** any migration, `SaleModel`, `ProjectModel::getFinancialSummary()`,
`ProjectModel::getAllocatedPurchaseCost*()`, `Payments::store()`/`update()`/`delete()`, the hidden
`#saleSelect` `change` handler, `.record-payment-btn` click handler, `Reports::*`,
`Projects::statement*()`/`statementExport()`/`statementPdf()`, `app/Views/dashboard/*`.

---

## Bug 1 — Project Payment Status

`ProjectModel::getPaymentStatus()` no longer counts `sales.status` per invoice. It now:

1. Counts this project's invoices (`COUNT(*)` on `sales`). Zero invoices → `PENDING`.
2. Calls `getFinancialSummary()` (unchanged) and checks, in order:
   - `remaining_billable_value > 0.004` → `PARTIAL` (still more contract to bill).
   - else `outstanding_collection_balance > 0.004` → `PARTIAL` (fully billed, customer still owes).
   - else → `PAID` (fully billed and fully collected — an Advance Credit balance, which is
     `≤ 0.004` on this signed field, counts as "Customer Pending = 0").

`Projects::index()` previously computed the *same wrong rule* a second time, independently, via
correlated subqueries (`paid_sale_count`, `covered_sale_count`) rather than calling
`getPaymentStatus()`. That duplicate implementation is gone — the index page now applies the
identical three-step rule above using the `getFinancialSummary()` result it was already fetching per
row for the Contract Value / Customer Pending columns (Release 1.7A), so there is exactly one rule,
expressed twice in code (model method for single-project reads, inline in the list query's loop to
avoid N+1), never two different rules.

**Wording:** the model now returns `PENDING`/`PARTIAL`/`PAID` (previously `N/A`/`UNPAID`/`PARTIAL`/`PAID`).
Both consuming views' badge-class maps were extended with `'PENDING' => 'pending'`; the `'UNPAID'`/`'N/A'`
map entries were left in place as dead-but-harmless fallbacks (defensive, not currently reachable) rather
than removed, since removing them has no behavioral effect either way. A new `.badge-pending` CSS rule
(gray) was added since `PENDING` is a genuinely new status word with no prior style.

## Bug 2 — Payment Create/Edit Screen

**Issue A — Active projects only.** `Payments::create()` and `edit()`'s project-dropdown query gained
`WHERE status = 'ACTIVE'`. `edit()` has one exception: if the payment's own invoice belongs to a
project that fails that filter (e.g. it was later marked Completed), that project is queried
separately and appended to the list (then the list is re-sorted alphabetically) so the edit screen can
still pre-select it — otherwise the pre-selected `<option value="...">` would not exist in the
dropdown and the invoice filter below would incorrectly show nothing.

**Issue B — No invoices before project selection.** Both `create.php` and `edit.php` add a
`#selectProjectMsg` placeholder row reading "Select a project to view invoices." `create.php`: all
`.invoice-row` elements are hidden on load (`$('.invoice-row').hide()`); the existing
`#projectFilter` `change` handler was extended so choosing the empty option re-hides everything and
shows the placeholder, while choosing a project hides the placeholder and filters rows exactly as
Release 2.1B/2.1C already did. `edit.php`: the same logic was refactored into a single
`applyProjectFilter()` function called both on `change` and once unconditionally on page load, so the
pre-selected project's invoices are visible immediately (never the "every invoice" default, and never
stuck on the placeholder despite already having a project chosen).

**Issue C — Table columns.** Already matched the spec from the prior UI-cleanup release (Invoice No,
Date, Invoice Total, Advance Applied, Paid Amount, Invoice Pending, Status, Record Payment). No
column change was needed; Record Payment stays disabled at `Invoice Pending ≤ 0.004`, unchanged.

**Compatibility confirmed:** `#saleSelect`, `#saleDetail`, `#detTotal`, `#detAdvance`, `#detPaid`,
`#detPending`, `#amountInput` all still exist with the same IDs and the same `change`-handler JS,
untouched by this release. `.record-payment-btn`'s click handler is unchanged — it still only sets
`#saleSelect`'s value and fires `change`. `Payments::store()`/`update()`/`delete()` were not opened
for editing.

## Manual verification note

`php -l` was run against all seven edited files — no syntax errors:
`app/Models/ProjectModel.php`, `app/Controllers/Projects.php`, `app/Controllers/Payments.php`,
`app/Views/projects/index.php`, `app/Views/projects/view.php`, `app/Views/payments/create.php`,
`app/Views/payments/edit.php`. A live browser check could not be completed in this session (no dev
server was started as part of this change). See `RELEASE_2_1C_ACCEPTANCE_TEST.md` for the manual test
plan to run once the app is reachable.
