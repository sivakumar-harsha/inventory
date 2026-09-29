# Release 2.0A — Long Duration Project Workflow Analysis (Phase A/B/C, read-only)

Scope reminder: this document is **analysis only**. No code, database, migration, controller,
model, view, JS, or export file was modified to produce it. All facts below were verified by
reading the current codebase (`app/Controllers`, `app/Models`, `app/Views`, `app/Database/Migrations`,
`public/assets/js`) as of 2026-08-31, cross-checked against the live schema dump
`aan6amed1cal_inv.sql` plus the three post-dump migrations
(`2026-08-27-000000_AddProjectValueFields.php`, `2026-08-27-000001_AddGstApplicableFields.php`,
`2026-08-27-000003_AddPurchaseAllocationFields.php`, `2026-08-28-000000_AddSalesAdvanceApplied.php`).

## Background restated

A project (e.g. ₹24,00,000 contract) runs 3–12 months. Over that time: multiple purchase invoices,
multiple sales invoices, multiple customer payment installments, and one advance received up front
and consumed gradually. The client's complaint: the current app behaves as if "invoice fully paid"
== "project done," which is wrong for long-running project billing.

---

# Phase A — Dependency Analysis (current system, as-built)

## A.1 Current project lifecycle

`projects` table (`aan6amed1cal_inv.sql:119-129` + `AddProjectValueFields.php:11-35`):

| Column | Notes |
|---|---|
| `status` | ENUM `ACTIVE` / `ON_HOLD` / `COMPLETED`, default `ACTIVE` |
| `start_date`, `end_date` | nullable DATE |
| `total_project_value` | DECIMAL(12,2), default 0 — the contract value |
| `advance_amount`, `advance_date`, `advance_notes` | one advance per project |
| `customer_id`, `name`, `description` | — |

`status` is **already manual-only today** — good news for Phase B, since this part needs the
least structural change:

* `Projects::store()` (`app/Controllers/Projects.php:96-107`) — set from POST at creation.
* `Projects::update()` (`Projects.php:119-148`) — set from the Edit form; if set to `COMPLETED`
  with no `end_date`, auto-stamps today's date (lines 126-128) but does not touch invoices/payments.
* `Projects::updateStatus()` (`Projects.php:160-195`) — AJAX quick-toggle from the Projects index
  dropdown, validated against the 3-value whitelist.

No code path anywhere infers or writes `COMPLETED` from invoice/payment state — this was
confirmed by tracing every write to `projects.status` (only the three methods above) and every
read of `sales.status`/payment sums in `ProjectModel`/`SaleModel`/`Payments` controller (none of
them call back into `ProjectModel::update()`).

**One coupling that already leans the wrong way today (project → invoice, not invoice → project,
but still a coupling worth flagging for Phase B):**

* `Sales::store()` (`Sales.php:115-121`) and `Purchases::store()` (`Purchases.php:54-58`) **reject
  creating a new sale/purchase** if the parent project is `COMPLETED`.
* `Sales::create()` (`Sales.php:32`) and `Purchases::create()` (`Purchases.php:33`) filter their
  project dropdown to `status = 'ACTIVE'` only, so a `COMPLETED` or `ON_HOLD` project cannot even
  be selected for a new invoice/purchase.
* Neither `Sales::update()` nor `Purchases::update()` re-check project status on edit, so an
  existing invoice tied to a since-completed project can still be edited — an inconsistency, not
  a by-design safeguard.

## A.2 Current invoice (sale) lifecycle

`sales` table (`aan6amed1cal_inv.sql:176-190` + `AddSalesAdvanceApplied.php:22-25`):

| Column | Notes |
|---|---|
| `project_id` | **NOT NULL**, FK → projects — plain FK, no uniqueness constraint |
| `total_amount` | invoice total |
| `advance_applied` | portion of the project's single advance FIFO-allocated to this invoice |
| `paid_amount`, `balance_amount` | derived/persisted, see below |
| `status` | ENUM `UNPAID` / `PARTIAL` / `PAID`, default `UNPAID` |

**One project already has many sales today** — this is not a gap to build, it is the existing,
actively-relied-upon design:

* `Projects::view()` lists all sales for a project (`Projects.php:215-219`).
* `ProjectModel::getFinancialSummary()` sums `total_amount`/`paid_amount` across *all* sales for
  the project (`ProjectModel.php:59-71`).
* `SaleModel::recalculateProjectBilling()` explicitly iterates every sale for the project ordered
  by date to FIFO-allocate the advance (`SaleModel.php:37-45`).

Invoice `status` is a **stored, code-maintained column**, recomputed by
`SaleModel::_recalculateSaleRow()` (`SaleModel.php:69-90`) every time a payment or the project's
advance changes:

```
pending = total_amount - advance_applied - SUM(payments.amount)
status  = PAID       if pending <= 0
        = PARTIAL     if advance_applied > 0 or paid > 0
        = UNPAID      otherwise
```

This recalculation is triggered from: `Payments::store/update/delete`, `Sales::store/update/delete`,
and `Projects::update()` (whenever `advance_amount` changes, since that shifts every invoice's
`advance_applied`).

## A.3 Current payment lifecycle

`payments` table (`aan6amed1cal_inv.sql:65-75`): linked **only to `sale_id`**, no `project_id`
column. All project-level payment reporting joins payment → sale → project
(`ProjectModel::getTimelineEvents()`, `ProjectModel.php:223-228`; `Payments::index()`,
`Payments.php:13-21`).

Advance handling exists but is **project-level, single-shot, not a payments/installment concept**:

* `projects.advance_amount` / `advance_date` / `advance_notes` — one value per project, entered
  once on the project form.
* `sales.advance_applied` — the FIFO-allocated slice of that one advance assigned to each invoice.
* There is **no dedicated advance-payments table**, no `payments.type` flag, and (confirmed by
  full-tree grep) **no "installment" concept anywhere in the codebase.** Every row in `payments`
  is a plain payment against one invoice.

"Amount pending" is computed at three levels today, each with a **different formula**:

* Per invoice: `sales.balance_amount` (persisted, advance-aware, per §A.2).
* Per project ("Customer Pending" / "Outstanding Collection Balance"):
  `ProjectModel::getFinancialSummary()` (`ProjectModel.php:78-90`) —
  `total_billed − advance_amount − total_paid`, i.e. it subtracts the *whole* project advance
  **once** against project-wide totals, not the sum of each invoice's already-applied
  `advance_applied`. In the steady state these two should reconcile (the FIFO allocator caps
  cumulative `advance_applied` at `advance_amount`), but they are two independently written
  formulas over the same underlying numbers, which is a latent-drift risk worth naming for
  Phase B.
* Per customer: no dedicated rollup function exists; Reports → Sales sums `balance_amount` over
  a filtered row-set (`Reports.php:908`), which is invoice-level filtered by customer, not a
  first-class "customer balance" query.

## A.4 Current project status logic (exhaustive)

**Writes:** `Projects.php:99`, `Projects.php:133`, `Projects.php:180` — all manual, whitelisted
against `['ACTIVE','ON_HOLD','COMPLETED']`. No automatic write exists.

**The one place status and payment state visually intersect — and it is display-only, not a state
mutation** — `app/Views/projects/view.php:124-134`:

```php
<?php if ($project['status'] === 'COMPLETED' && $payment_status === 'PAID'): ?>
    ... "This project is fully completed and settled." ...
<?php elseif ($project['status'] === 'COMPLETED'): ?>
    ... "Work completed. Payment status: <?= $payment_status ?>" ...
<?php endif; ?>
```

This confirms the client's complaint is **perceptual, not a hard bug**: the code never
auto-completes a project on full payment, but the UI's binary "Work Status" badge next to a
"Payment Status" badge, with no visible notion of *invoicing progress against contract value*,
creates the impression that paid == done. The missing piece is a **progress signal**, not a status
fix.

**Reads:** `Projects::index()` tab filter (`Projects.php:52-60`), the COMPLETED-blocks-creation
guards in `Sales.php:118` / `Purchases.php:56`, the ACTIVE-only dropdown filters in `Sales.php:32`
/ `Purchases.php:33`, and status badges in `projects/index.php:225-249` and `projects/view.php:99-104`.

## A.5 Current payment status logic (exhaustive — five parallel implementations)

The research surfaced **five separate, independently-coded status/label vocabularies** derived
from the same underlying numbers. This fragmentation is itself a Phase B risk to note, independent
of the advance/completion issue:

1. **Invoice status** (source of truth) — `SaleModel::_recalculateSaleRow()` (`SaleModel.php:69-90`).
2. **Project payment status** — `ProjectModel::getPaymentStatus()` (`ProjectModel.php:26-44`):
   counts sales `PAID` / `PARTIAL or PAID` / total, buckets into `PAID` / `PARTIAL` / `UNPAID` / `N/A`.
3. **Duplicate re-implementation** of #2 inline in `Projects::index()` (`Projects.php:39-72`) —
   same rule, separate SQL and branch logic, explicitly commented as intentionally kept in sync
   but not actually sharing code.
4. **Balance-status label on the Project Statement** — `Projects::_buildStatementData()`
   (`Projects.php:517-524`) — a third vocabulary: `Outstanding` / `Customer Credit` / `Settled`,
   driven by sign of `outstanding_collection_balance`.
5. **"Customer Pending" tri-state badge** on the Projects index (`projects/index.php:213-220`) —
   a fourth ad-hoc derivation from the sign of `customer_pending`.
6. **Dashboard recent-sales badge** (`dashboard/index.php:518-520`) — collapses `PARTIAL` and
   `UNPAID` into one generic "pending" visual, losing the distinction entirely.

## A.6 Current dashboard/reports/project-statement dependencies

The system already treats project→sales as one-to-many everywhere it aggregates — **this is not
a gap Phase B needs to build, it needs to be preserved and given a completion-independent progress
metric on top of it.**

| Location | What it aggregates | Many-invoices-per-project aware? |
|---|---|---|
| `Dashboard::index()` (`Dashboard.php:17-74`) | Global sums (sales/purchases/payments/outstanding), active-project count, 5 most recent sales/purchases | N/A (global) |
| `Projects::index()` (`Projects.php:39-86`) | Per-project `SUM(DISTINCT ...)`, sale/paid/covered counts, `getFinancialSummary()` per row | Yes — DISTINCT used precisely because of many sales/expenses per project |
| `Projects::view()` (`Projects.php:197-242`) | All sales, purchases, expenses for one project; `net_profit` | Yes |
| `Projects::_buildStatementData()` / statement / export / PDF (`Projects.php:262-536`) | Chronological timeline merging Advance + Sales + Payments + Purchases + Expenses with running balance | Yes |
| `ProjectModel::getFinancialSummary()` (`ProjectModel.php:51-91`) | SUM across all sales for the project | Yes |
| `ProjectModel::getAllocatedPurchaseCostByProject()` (`ProjectModel.php:113-154`) | Batch allocation-aware purchase cost per project | Yes |
| `Reports::profitLoss()` + export/pdf (`Reports.php:24-280`) | Revenue/COGS per project, project breakdown | Yes |
| `Reports::sales()` + export/pdf (`Reports.php:842-1290`) | Filterable invoice list + KPIs (`kpi_total_sales`, `kpi_customer_received`, `kpi_customer_pending`) | Yes (row-level) |
| `Reports::purchases()` + export/pdf (`Reports.php:1288-1560`) | Purchase list filterable by project | Yes |
| `SaleModel::recalculateProjectBilling()` (`SaleModel.php:30-46`) | FIFO advance allocation across all sales for a project | Core function *is* the many-to-one relationship |

No query anywhere assumes 1 invoice = 1 project; the SUM/COUNT/DISTINCT patterns throughout
confirm the schema already models what the client described. **The gap is entirely in the status
semantics and the missing "invoiced vs contract value" progress metric — not in the data model.**

---

# Phase B — Proposed Business Flow

The design goal is narrow given the findings above: **project status is already decoupled from
invoice status at the write level.** Phase B does not need to invent a new decoupling — it needs
to (1) stop the one place completion silently blocks new invoices/purchases from a still-open
engagement, (2) add the missing progress metric, (3) collapse the five parallel status
vocabularies into one, and (4) formalize per-invoice advance/pending math so it's computed once.

### B.1 Project stays ACTIVE until manually completed

Already true today (§A.4) — **no change needed to the write path.** The one behavior to correct:
remove (or make configurable/warn-only) the `COMPLETED`-blocks-new-sale/purchase guard in
`Sales.php:115-121` / `Purchases.php:54-58`, since a long-running project may legitimately need a
"reopen to add one more invoice" flow today that instead requires flipping status back to `ACTIVE`
first. Recommend keeping the guard on `ON_HOLD` (explicit pause) but not treating `COMPLETED` as
absolutely immutable — or, alternatively, require explicit reopen-to-ACTIVE which is already
possible via `updateStatus()`. Either is a small policy decision, not a structural change.

### B.2 Multiple purchases and multiple sales per project

No change needed — already the schema and query design (§A.2, §A.6).

### B.3 Each sale is an independent billing milestone; invoice status independent from project status

Already true at the data-write level (§A.4). What needs to change is **presentation**, not logic:
the "This project is fully completed and settled" UI copy in `projects/view.php:124-134` should be
rephrased so a `PAID` payment_status on the currently-invoiced set is never implied to mean the
*engagement* is finished — because more invoices may still be coming. Recommend the badge read
something like "All invoiced amounts settled — N invoices to date" rather than any phrase
resembling "completed," reserving "completed" language exclusively for the manual project status.

### B.4 Project progress = Total Invoiced vs Contract Value

**New metric, not present today.** `ProjectModel::getFinancialSummary()` already computes
`total_billed` and has `total_project_value` available (`ProjectModel.php:59-71`, `78-90`) but
does not expose a `billing_progress_percent` display anywhere prominent — it exists as a raw field
(`ProjectModel.php:85` per the research) but is not the primary status signal on the Projects
index or Project view. Phase 2.0B should promote `total_invoiced / total_project_value * 100` to
be the **primary progress indicator** shown wherever "Work Status" is shown today, e.g. a progress
bar ("₹14,50,000 of ₹24,00,000 invoiced — 60%") alongside — not instead of — the manual
ACTIVE/ON_HOLD/COMPLETED status.

### B.5 Customer Pending = Invoices − Advance − Payments

Already computed (§A.3) but by **two independently-written formulas** (`getFinancialSummary()`'s
project-level formula vs the per-invoice `balance_amount` sum). Phase 2.0B should pick one as the
single source of truth — recommend keeping the per-invoice `balance_amount` (already advance-aware
via FIFO `advance_applied`, already the finer-grained and independently-verifiable number) and
deriving all project/customer-level rollups as `SUM(sales.balance_amount)` rather than maintaining
`ProjectModel::getFinancialSummary()`'s separate `total_billed − advance_amount − total_paid`
subtraction. This removes one of the two formulas rather than adding a third.

### B.6 Consolidate the five payment-status vocabularies (§A.5)

Recommend one shared status-derivation function (in `ProjectModel` or a small helper), called by:
`Projects::index()` (replacing its inline duplicate), `Projects::view()`, the Project Statement
balance-status label, the Projects-index Customer Pending badge, and the Dashboard recent-sales
badge — so `PARTIAL` is never silently collapsed into a generic "pending" anywhere, and a future
change to the pending-amount formula (B.5) only has to happen once.

---

# Phase C — Impact Analysis

Legend: **UI** = view/badge/label text only · **Logic** = controller/model business rule ·
**DB** = schema/migration · **No change** = confirmed unaffected, listed for completeness.

| File | Change type | Why |
|---|---|---|
| `app/Controllers/Projects.php` (store/update/updateStatus) | No change | Status write path already manual-only (§A.4) |
| `app/Controllers/Projects.php` (index — inline payment-status duplicate) | Logic | Replace inline duplicate with shared status-derivation call (§B.6) |
| `app/Controllers/Projects.php` (`_buildStatementData` balance-status label) | Logic + UI | Switch to shared pending formula (§B.5); relabel to avoid "completed" implication (§B.3) |
| `app/Controllers/Sales.php` (`store()` COMPLETED-block guard) | Logic | Reconsider/relax per §B.1 policy decision |
| `app/Controllers/Purchases.php` (`store()` COMPLETED-block guard) | Logic | Same as above |
| `app/Controllers/Sales.php` / `Purchases.php` (`create()` ACTIVE-only dropdown) | Logic (conditional) | Only if B.1 policy allows invoicing a COMPLETED project without reopening |
| `app/Controllers/Payments.php` | No change | Payment recording logic already invoice-scoped and correct; no completion coupling found |
| `app/Controllers/Reports.php` (`profitLoss`, `sales`, `purchases` + export/pdf variants) | UI (labels only) | Already many-to-one aware (§A.6); only status-label wording may shift if B.6 vocabulary changes |
| `app/Controllers/Dashboard.php` | UI | Recent-sales badge should use shared status vocabulary (§B.6) instead of PAID-vs-everything-else collapse |
| `app/Models/ProjectModel.php` (`getPaymentStatus()`) | Logic | Candidate to become (or be replaced by) the single shared status function (§B.6) |
| `app/Models/ProjectModel.php` (`getFinancialSummary()`) | Logic | Pending-amount formula consolidation (§B.5); promote `billing_progress_percent` to primary metric (§B.4) |
| `app/Models/ProjectModel.php` (`getTimelineEvents()`, `getAllocatedPurchaseCost*()`) | No change | Already correctly many-to-one; not implicated in completion coupling |
| `app/Models/SaleModel.php` (`_recalculateSaleRow`, `recalculatePaymentState`, `recalculateProjectBilling`) | No change | Invoice status math is already correct and independent of project status |
| `app/Models/PaymentModel.php` | No change | Thin CRUD model, no status logic |
| `app/Models/PurchaseModel.php` | No change | Thin CRUD model, no status logic |
| `app/Views/projects/index.php` | UI | Progress bar (B.4), Customer Pending badge via shared formula (B.5/B.6) |
| `app/Views/projects/view.php` | UI | Remove "fully completed and settled" phrasing tied to payment_status (§B.3); add progress bar (B.4) |
| `app/Views/projects/statement.php`, `statements_list.php` | UI | Balance-status label via shared vocabulary (B.6) |
| `app/Views/sales/index.php`, `view.php` | No change | Invoice status badge already correctly invoice-scoped |
| `app/Views/payments/index.php`, `create.php`, `edit.php` | No change | Already invoice-scoped, advance displayed read-only for context |
| `app/Views/reports/sales.php`, `reports/ledger.php` | UI (labels only) | Only if B.6 vocabulary changes status label text |
| `app/Views/dashboard/index.php` | UI | Recent-sales badge should show PARTIAL distinctly (B.6) |
| `public/assets/js/project-financial.js` | UI (minor) | If a client-side progress-bar mirror is added per B.4, extend this script; otherwise no change |
| `app/Database/Migrations/*` (existing) | No change | `projects.status`, `total_project_value`, `advance_amount`, `sales.advance_applied` already support the proposed flow — no new columns required for B.1–B.6 as scoped |
| New migration (if B.6 consolidation needs a stored "unified status" rather than computed-on-read) | DB (only if chosen) | Optional — a computed/shared PHP function avoids a migration entirely; recommend computed-on-read to keep Release 2.0B migration-free |

**Headline finding for Phase C:** the overwhelming majority of the required work is **UI/labeling
and formula-consolidation (Logic)**, not schema change. No new tables or columns are required to
satisfy the business flow described in the background — `projects.total_project_value`,
`projects.advance_amount`, and `sales.advance_applied` already carry everything needed for
B.4 and B.5. The one genuine **Logic** decision requiring product sign-off before 2.0B starts is
§B.1 (whether a `COMPLETED` project may still receive new invoices, or must be explicitly reopened
first).

---

# Recommended implementation order for Release 2.0B

1. **B.6 — consolidate the five status vocabularies** into one shared function first. Every other
   change (B.3, B.4, B.5 UI work) depends on having one place to call for a consistent label, so
   sequencing this first avoids re-touching the same view files twice.
2. **B.5 — pick one pending-amount formula** (recommend the per-invoice `balance_amount` sum) and
   point `getFinancialSummary()`'s "Customer Pending"/"Outstanding Collection Balance" figures at
   it. Do this alongside step 1 since both touch `ProjectModel`.
3. **B.4 — promote billing-progress-percent to a visible primary metric** on Projects index and
   Project view (progress bar). Purely additive UI; no risk to existing data.
4. **B.3 — reword the "completed and settled" messaging** in `projects/view.php` so paid-in-full
   invoices are never described in language that implies the project itself is done. Low risk,
   isolated to view text.
5. **B.1 — the COMPLETED-blocks-new-invoice guard** — decide and implement last, since it is the
   only item that changes a validation/business rule (not just a label or read-only formula), and
   should be confirmed with the client first (does "COMPLETED" mean "no more invoices ever" or
   "paused, reopen to add more"?).

This order front-loads pure-presentation and formula-consolidation work (low risk, immediately
visible to the client for validation) and defers the one actual business-rule change to last, once
the client has seen the corrected status/progress presentation and can confirm whether the
create-guard behavior needs to change at all.
