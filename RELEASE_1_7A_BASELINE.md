# Release 1.7A — Baseline Freeze (Documentation Only)

Date: 2026-08-28
Scope: This document freezes the state of the application as of Release 1.7A. It is a **documentation-only** artifact — no code, migrations, or calculations were changed while producing it. Historical facts below (1.5.x, 1.6.x) are compiled from the prior baseline/acceptance documents already in the project root (`RELEASE_1_3.md`, `PHASE1–5_BASELINE.md`, `RELEASE_1_5_1..1_5_6_BASELINE.md`, `RELEASE_1_6A_APPROVED_DESIGN.md`, `RELEASE_1_6B_BASELINE.md`, `RELEASE_1_6C_ACCEPTANCE_TEST.md`, `RELEASE_1_6_4_*`) plus the current, as-implemented code for Releases 1.6.5/1.6.6/1.7A (which had no separate baseline doc at the time).

---

## 1. Release Summary

### Pre-1.5 (Phases 1–3, consolidated as Release 1.3)

* **Phase 1 — Project Financial Fields**: added `total_project_value`, `advance_amount`, `advance_date`, `advance_notes` to `projects`; introduced `ProjectModel::getFinancialSummary()` and `getPaymentStatus()`; Project View gained a Financial Summary card.
* **Phase 2 — GST Per Line Item**: added `gst_applicable` to `sale_items`/`purchase_items`; introduced the shared `gst_helper.php` (`gst_calculate_line()`, `gst_summarize_items()`) as the single source of truth for GST math on both Sales and Purchases.
* **Phase 3 — Project Stock Return**: introduced the Stock Return module (`StockReturns` controller/model/views) — Project → General stock movement only, FIFO allocation against original purchase lines, create-and-delete only (no edit), delete blocked once returned stock is re-sold from General. Purchases became locked once any line has a linked return.
* **Phase 4 — Live Project Billing Tracker**: added a live "Project Financial Summary" card to Sales Create/Edit, backed by a new AJAX endpoint (`Sales::projectFinancialSummary()`) and an `excludeSaleId` parameter on `getFinancialSummary()` to avoid double-counting the invoice being edited.
* **Phase 5 — Project Statement**: introduced the Project Statement page/export (Excel + PDF) with a chronological timeline merging Advance, Sales Invoices, Payments, Purchases, Expenses, and Stock Returns.

### Release 1.5.x

* **1.5.1 — Export Infrastructure Patch**: installed and wired the Excel/PDF export packages (PhpSpreadsheet, Dompdf) correctly for the deployment PHP runtime; fixed 10 previously-failing export endpoints. No business logic changed.
* **1.5.2 — Project Billing Logic Correction**: fixed `remaining_billable_value` to subtract `advance_amount` (`Total Project Value − Advance − Total Billed`), correcting an overstatement of remaining billable value. One-line model fix; all consumers inherited it automatically.
* **1.5.3 — Sales Project Billing Tracker UI Cleanup**: visual cleanup of the Sales Create/Edit billing tracker card.
* **1.5.4 — Compact Sales Billing Summary UI**: condensed the tracker card layout.
* **1.5.5 — Sales Invoice Screen UX Cleanup**: compacted the Project Financial Summary and GST Summary blocks on the Sales invoice screen.
* **1.5.6 — Replace "Billed Progress" with "Remaining Project Balance"**: Sales tracker's progress chip changed from a cumulative billed-progress percentage to a "Remaining Project Balance" figure (`Total Project Value − This Invoice`), with its own bar/percentage; the overall over/near/within-limit badge logic was kept unchanged. UI-only, no `ProjectModel`/controller/route/DB change.

### Release 1.6.x

* **1.6 (design, "1.6A") — Purchase Allocation, Approved Design**: froze the business design for splitting one purchase line's quantity between Project and General stock in a single purchase (no more separate purchases for warehouse vs. project stock).
* **1.6B — Purchase Allocation, Implementation**: added `purchase_items.project_qty`/`general_qty` (migration, backfilled 100%-project for existing rows); `Purchases::store()/update()` now validate and persist the split and write up to two `stock_ledger` rows per line (`PROJECT` and `GENERAL` sources); project purchase cost became allocation-aware (`ProjectModel::getAllocatedPurchaseCost*()`, using only the `project_qty` portion, reusing `gst_calculate_line()`); `StockReturns::_allocateFifo()` now draws only against `project_qty` (a return can never touch General-allocated stock); Purchase edit/delete gained consumption guards (Stock Return linkage, PROJECT-side sold, GENERAL-side sold — three independent checks); Reports' COGS became allocation-aware for project breakdowns.
* **1.6C — Purchase Allocation, Acceptance Test**: full PASS across allocation math, stock ledger PROJECT/GENERAL independence, profit calculation, FIFO return restriction, edit/delete guards, reports, and regression.
* **1.6.4 — Apply Project Advance to Invoice Payments**: added `sales.advance_applied` (migration + one-time backfill); `SaleModel::recalculateProjectBilling()` FIFO-allocates a project's advance across its invoices (oldest first, capped per invoice, idempotent); `SaleModel::recalculatePaymentState()` recomputes `paid_amount` (live `SUM(payments.amount)`), `balance_amount = max(0, total − advance_applied − paid_amount)`, and `status` (PAID/PARTIAL/UNPAID) from source on every relevant Sales/Payments/Projects mutation. Payments Create/Edit screens gained an Invoice Total / Advance Applied / Amount Paid / Amount Pending breakdown.
* **1.6.5 — Project Payment Status Fix**: `ProjectModel::getPaymentStatus()` and `Projects::index()` were rewritten to derive project-level payment status from an aggregation of invoice-level `sales.status` counts (already advance-aware since 1.6.4), replacing the old `total_paid`/`total_billed` sums that ignored `advance_applied` and could show PARTIAL for an invoice that was actually fully covered by advance. UI unchanged.
* **1.6.6 — Project Statement UX Improvement**: Statement timeline's "Running Balance" column relabeled "Customer Pending After Transaction" with Advance Credit/Settled pill states instead of raw negative numbers; Purchase/Expense rows tagged "Project Cost Only"; added a "Project Statements" sidebar entry and list page (`projects/statements`) reusing the existing per-project statement route. `getTimelineEvents()` and the Excel/PDF exports were not touched — display only.

### Release 1.7A (this release)

UI/UX polish only, no calculation changes:

1. **Project Statement redesign** — summary table replaced with 5 compact metric cards (Project Value, Advance Received, Total Invoiced, Total Paid by Customer, Customer Pending); "Remaining Billable Value" removed from the UI; Contract Status collapsed into one compact green/orange/red badge; timeline rows gained type icons, and Purchase/Expense/Stock Return rows show a "Project Cost Only" badge instead of a balance.
2. **Purchase Create/Edit UX** — added "Project Qty + General Qty = Purchased Qty" helper text and inline validation feedback when Project Qty is corrected down to Purchased Qty.
3. **Project List UX** — added Contract Value and Customer Pending columns, sourced from the existing `ProjectModel::getFinancialSummary()`.
4. **Stock Return navigation cleanup** — removed from the sidebar; added a "Stock Returns" button on Project View (pre-selects the project); added an explanatory info banner on the Stock Return screens clarifying it is for returning unused project stock, not initial purchase allocation.

---

## 2. Business Workflow

### Project Billing

```
Project Value  →  Advance  →  Invoice  →  Payment
```

* **Project Value** (`projects.total_project_value`) is the contracted ceiling for the project.
* **Advance** (`projects.advance_amount`) is received once (or updated) at the project level, not tied to any single invoice.
* **Invoice** (`sales` row with `project_id`) bills against the project. On every invoice create/update/delete, `SaleModel::recalculateProjectBilling()` re-runs FIFO across *all* of the project's invoices (oldest `sale_date` first) and assigns each invoice `min(remaining advance, that invoice's total_amount)` as `advance_applied` — so the advance is consumed by the oldest unpaid invoices first, and re-running the allocation is idempotent.
* **Payment** (`payments` row against a `sale_id`) is recorded against a specific invoice, on top of whatever advance that invoice already absorbed. `SaleModel::recalculatePaymentState()` recomputes that invoice's `paid_amount` (live `SUM(payments.amount)`), `balance_amount`, and `status` from source every time.
* An invoice can therefore reach `PAID` status purely from advance, purely from payments, or a mix of both — this is the fix that Release 1.6.5 propagated up to the Project List/Project View payment-status badges.

### Purchase Allocation

```
Purchased Qty  →  Project Qty + General Qty
```

* A single purchase line's **Purchased Qty** is entered once, against one supplier invoice line.
* The user then splits it into **Project Qty** (goes to this purchase's project as project-committed stock) and **General Qty** (goes to warehouse/general stock), where `General Qty = Purchased Qty − Project Qty` is always derived, never entered directly.
* This single split writes up to two `stock_ledger` IN rows (`source = PROJECT` and `source = GENERAL`), both tagged `reference_type = PURCHASE` — replacing the pre-1.6 workaround of creating two separate purchases to achieve the same split.
* Project cost (for Project View, Project Statement, and Reports' COGS) only ever counts the `project_qty` portion of a line — the `general_qty` portion is warehouse inventory and is excluded from project cost.

### Stock Return

```
Project Stock  →  General Stock   (unused materials only)
```

* Stock Return exists to move **unused** materials that were allocated to a project (the `project_qty` portion of a purchase) back into General stock — for example, over-ordered material that a project didn't end up needing.
* It can only draw against the `project_qty` portion of a purchase line, via FIFO against the original purchase lines — it can never draw against `general_qty`, because that stock was never project stock to begin with.
* **Stock Return is NOT part of the Purchase Allocation workflow.** Purchase Allocation happens once, at purchase time, and decides how a purchased quantity is split between Project and General. Stock Return is a separate, later, optional workflow that only reverses the Project side of an *already-allocated* purchase, after the fact — it never edits or re-splits a purchase line.

---

## 3. Project Financial Definitions

All formulas below are implemented in `ProjectModel::getFinancialSummary()` (unchanged since Release 1.5.2, reused by Project View, Project Statement, and the Sales billing tracker) unless noted otherwise.

| Term | Formula | Notes |
|---|---|---|
| **Project Value** | `projects.total_project_value` | Contracted ceiling for the project, set on Project Create/Edit. |
| **Advance Received** | `projects.advance_amount` | Set on Project Create/Edit; FIFO-allocated across invoices by `SaleModel::recalculateProjectBilling()` (Release 1.6.4). |
| **Total Invoiced** | `SUM(sales.total_amount)` for the project | Labeled "Total Billed" in `getFinancialSummary()`'s return array (`total_billed`); "Total Invoiced" is the Release 1.7A display label for the same figure. |
| **Total Paid by Customer** | `SUM(sales.paid_amount)` for the project | Labeled `total_paid` in the model; `sales.paid_amount` itself is `SUM(payments.amount)` for that invoice (Release 1.6.4), i.e. payments only — advance is tracked separately as `advance_applied`. |
| **Customer Pending** | `total_billed − advance_amount − total_paid` | Labeled `outstanding_collection_balance` in the model (unchanged since Release 1.5.2's advance-aware fix). Not clamped — negative means Advance Credit (collections exceed what's been billed so far), zero means Settled, positive means the customer still owes that amount. |
| **Contract Status** | `total_billed` vs `total_project_value` | **Within Contract** (green) when `total_billed < 90%` of `total_project_value`, shows `% billed`. **Near Contract** (orange) when `90% ≤ total_billed ≤ 100%` of `total_project_value`, shows `% billed`. **Over Contract** (red) when `total_billed > total_project_value`, shows the excess amount (`total_billed − total_project_value`). Same thresholds/logic as before 1.7A — only the presentation (one compact badge instead of a labeled section) changed. |

Two formulas exist elsewhere and are **not** the same as Customer Pending — worth keeping distinct:

* `remaining_billable_value = total_project_value − advance_amount − total_billed` — "how much of the contract ceiling is left to invoice," not shown in the Release 1.7A statement UI (removed per this release), still computed by the model and still used by exports.
* Sales Create/Edit's "Remaining Project Balance" chip (Release 1.5.6) = `total_project_value − this_invoice_total` (does not subtract advance) — a different, invoice-entry-time-only figure, unrelated to Customer Pending.

---

## 4. Navigation Changes

| Change | Since | Detail |
|---|---|---|
| **Project Statements** menu | 1.6.6 (added), 1.7A (unchanged) | Sidebar entry under CRM linking to `projects/statements` — a list of all projects with a "View Statement" button per row, reusing the existing `projects/statement/{id}` route/view. |
| **Stock Return removed from sidebar** | 1.7A | The "Stock Returns" sidebar link (previously under TRANSACTIONS) was removed. The route (`stock-returns`), controller, model, and workflow are unchanged and still reachable by URL. |
| **Stock Return accessed from Project View** | 1.7A | Project View now has a "Stock Returns" button next to "View Statement", linking to `stock-returns/create?project_id={id}` — the Stock Return create screen pre-selects that project and auto-loads its returnable products. |

---

## 5. UI Changes (Release 1.7A)

| Screen | Changes |
|---|---|
| **Project Statement** (`projects/statement.php`) | Summary table → 5 metric cards (Project Value, Advance Received, Total Invoiced, Total Paid by Customer, Customer Pending); "Remaining Billable Value" removed from view; Contract Status → single compact badge (green/orange/red); timeline "Running Balance" → "Customer Pending After Transaction" with Customer Pending/Settled/Advance Credit pills; Purchase/Expense/Stock Return rows show a "Project Cost Only" badge instead of a balance; each timeline row gained a type icon (Advance, Sales Invoice, Invoice Payment, Purchase, Expense, Stock Return). |
| **Project View** (`projects/view.php`) | Added a "Stock Returns" button next to "View Statement", linking to the project-scoped Stock Return create screen. |
| **Project List** (`projects/index.php`) | Added "Contract Value" and "Customer Pending" columns. |
| **Purchase Create/Edit** (`purchases/create.php`, `purchases/edit.php`) | Added "Project Qty + General Qty = Purchased Qty" helper text under both fields; added inline red validation text + red border when Project Qty is corrected down to not exceed Purchased Qty. Purchased Qty remains user-editable (see Regression Baseline note below). |
| **Stock Return — list** (`stock_returns/index.php`) | Added an info banner clarifying Stock Return's purpose. |
| **Stock Return — create** (`stock_returns/create.php`) | Added the same info banner; supports pre-selecting a project via `?project_id=` (auto-loads that project's returnable products on page load). |
| **Sidebar** (`layouts/main.php`) | Removed the Stock Returns link. |

---

## 6. Regression Baseline

**Confirmed: no controller/model/database/export calculation changed in Release 1.7A.**

* No migrations were created or run.
* No `ProjectModel`, `SaleModel`, `StockLedgerModel`, or `StockReturns` allocation/FIFO logic was modified.
* `ProjectModel::getFinancialSummary()`, `getPaymentStatus()`, `getTimelineEvents()`, `getAllocatedPurchaseCost*()` are byte-for-byte unchanged from Release 1.6.6.
* `Projects::statementExport()` (Excel) and `Projects::statementPdf()` were not touched — both still render "Running Balance" and the full summary table as before; only the on-screen HTML view changed.
* `Projects::index()` gained a loop that calls the existing, unmodified `getFinancialSummary()` per row to populate two new display fields (`contract_value`, `customer_pending`) — this reuses an existing formula, it does not introduce a new one. The pre-existing payment-status aggregation (from Release 1.6.5) in the same method was not touched.
* Stock Return's controller, model, FIFO allocation (`_allocateFifo()`), database tables, and routes are unchanged — only its sidebar visibility and an added entry point/banner changed.
* Purchases `store()`/`update()`, validation, and stock-ledger writes are unchanged — only the create/edit view's helper text and client-side validation display changed.

### Files modified in Release 1.7A

| File | Type of change |
|---|---|
| `app/Views/projects/statement.php` | View only — metric cards, contract badge, timeline pills/icons |
| `app/Views/projects/index.php` | View only — two new columns |
| `app/Views/projects/view.php` | View only — Stock Returns button |
| `app/Controllers/Projects.php` | Controller — `index()` populates `contract_value`/`customer_pending` from the existing `getFinancialSummary()`; no new formulas |
| `app/Views/layouts/main.php` | View only — sidebar link removed |
| `app/Views/purchases/create.php` | View only — helper text, inline validation |
| `app/Views/purchases/edit.php` | View only — helper text, inline validation |
| `app/Views/stock_returns/index.php` | View only — info banner |
| `app/Views/stock_returns/create.php` | View only — info banner, `?project_id=` pre-select |

No other files were modified in this release.

---

## 7. Future Release Candidates

UI/UX ideas only — **nothing below is scheduled or implemented.**

* Project List: make the new Contract Value / Customer Pending columns sortable and filterable (e.g. "show only projects with pending balance").
* Project Statement: collapsible/paginated timeline for projects with a long transaction history.
* Project Statement: a small trend indicator (e.g. sparkline) next to Customer Pending showing whether it's improving or worsening over the last few transactions.
* Stock Return: surface a "Returnable Now" count/badge on the Project View button itself, so users know at a glance whether there's anything eligible to return without opening the screen.
* Purchase Create/Edit: a visual split bar (like a two-color progress bar) under Project Qty / General Qty instead of just text, for faster at-a-glance allocation reading.
* Dashboard: a "Customer Pending" or "Advance Credit" summary tile aggregating across all active projects, using the same definitions frozen in §3.
* Project Statements list page: add search/filter (by customer, status, or pending balance) now that it's a first-class sidebar destination.
* Contract Status badge: consider showing it on the Project List row too (not just the Statement page), reusing the same thresholds.
* Consistent iconography pass: extend the Release 1.7A timeline icon set to other transaction-list screens (Sales, Purchases, Payments) for visual consistency.
