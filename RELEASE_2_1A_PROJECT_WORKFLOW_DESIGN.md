# Release 2.1A — Project Completion & Multi-Invoice Workflow Design (Phase A — Design Only)

Status: **DESIGN ONLY — NOT IMPLEMENTED.** No schema, migration, controller, or model changes were
made while producing this document. Every mechanism described below is checked against the
existing codebase (`app/Models/ProjectModel.php`, `app/Controllers/Projects.php`,
`app/Controllers/Sales.php`, `app/Controllers/Payments.php`, `app/Controllers/Expenses.php`,
`app/Controllers/Purchases.php`) as it stands after Release 2.0D, and against the findings in
`RELEASE_2_0C_LAST_PROJECT_AUDIT.md` and `RELEASE_2_0D_BASELINE.md`. Phase B (implementation) is
out of scope for this document.

> **Revision note.** Sections 1–5 below are the original Phase A draft and are kept for their
> code-tracing detail (exact files/lines checked). A follow-up business review changed two
> decisions from that draft — most importantly, **Work Status = COMPLETED no longer locks Sales,
> Purchases, or Expenses** (the original Section 4 "Completion Lock Rules" is superseded), and the
> Project Billing Status value set changed from a 3-state contract-vs-collection label to a
> 4-state PAID/PARTIAL/PENDING/N/A rollup of invoice statuses. The authoritative, current design is
> **"Final Approved Business Workflow (v2.1A)"** at the end of this document. Superseded passages
> are marked inline; nothing below was deleted so the reasoning trail stays intact.

## Inputs read

* `RELEASE_2_0D_BASELINE.md` — confirms `projects.status`, `sales.status`, and the Health
  Dashboard/Statement display fixes (RC1–RC5) are presentation-only and still open at the
  calculation-consolidation level (RC4/RC5 deferred).
* `RELEASE_2_0C_LAST_PROJECT_AUDIT.md` — confirms `projects.status` and `sales.status` are two
  independent enums by design (2.0A decision), traces every figure to
  `ProjectModel::getFinancialSummary()` / `getPaymentStatus()`, and documents the one-way
  create-time guard pattern (`Sales::store()`, `Expenses::store()` reject writes when
  `projects.status === 'COMPLETED'`).
* Current code, read directly: `ProjectModel::getPaymentStatus()`, `ProjectModel::getFinancialSummary()`,
  `Projects::updateStatus()` (the existing Work Status quick-toggle), `Sales::store()`,
  `Expenses::store()`/`update()`, `Purchases.php` (no completion guard currently exists there —
  see §4).

---

## Section 1 — Status Architecture

Three statuses already partially exist in the codebase under different names. This section gives
each a precise, single-purpose definition so they stop overlapping (this is the direct fix for
audit findings RC1/RC3: two different concepts sharing one label/color).

### 1.1 Work Status

* **What it answers:** "Is field/delivery work on this project still happening?"
* **Values:** `ACTIVE`, `ON_HOLD`, `COMPLETED` — this is the existing `projects.status` column,
  unchanged. No new value, no new column.
* **Who controls it:** The user, manually, via the existing quick-toggle
  (`Projects::updateStatus()`, already live on the Projects index) or the full Edit form. Nothing
  in Sales/Purchases/Expenses/Payments ever writes this field — confirmed one-way in the 2.0C
  audit and unchanged since.
* **Where it appears:** Projects index (status dropdown/badge), Project View header badge,
  Project Edit form.
* **2.1A meaning change (SUPERSEDED):** the line originally here proposed `COMPLETED` as a trigger
  that locks Sales/Purchases/Expenses. The final business decision (see **Final Approved Business
  Workflow §4**) is that `COMPLETED` locks nothing — all four modules (Sales, Purchases, Expenses,
  Payments) remain fully writable after completion. `COMPLETED` is purely a manual work-finished
  marker; today's existing create-time guards in `Sales::store()`/`Expenses::store()` are therefore
  a pre-2.1A behavior this design does not ask to extend further.

### 1.2 Invoice Payment Status

* **What it answers:** "Has this one invoice been paid off?"
* **Values:** `PAID`, `PARTIAL`, `UNPAID` — the existing `sales.status` column, unchanged.
* **Who controls it:** Nobody manually. It is fully derived and written by
  `SaleModel::_recalculateSaleRow()` / `recalculatePaymentState()` from
  `total_amount − advance_applied − paid_amount`, triggered by Payment create/edit/delete and by
  advance FIFO recalculation. No screen in 2.1A introduces a manual override of this field —
  Business Decision #1 ("pay any invoice in any order") is satisfied entirely by *which* invoice a
  payment is recorded against, not by a status field the user sets directly.
* **Where it appears:** Sales List (per-invoice badge, per 2.0D labels "Invoice Paid" / "Partial
  Payment" / "Payment Pending"), Sale View, the new Payment screen's invoice list (§2), Project
  Billing Overview's invoice table (§3).
* **Relationship to Work Status:** none, structurally. The 2.0C audit's root-cause finding — that a
  green "PAID" invoice badge reads as project-level "COMPLETED" — is a naming/color problem
  (`badge-paid` vs `badge-completed`), not a data coupling; 2.0D already renamed the Sales tab away
  from "Completed." 2.1A does not touch `sales.status` or its derivation.

### 1.3 Project Billing Status (SUPERSEDED — see Final Approved Business Workflow §1)

> The 3-state value set below (Fully Billed & Settled / Billing In Progress / Billed, Collection
> Pending) was the original proposal. The final decision reuses the existing 4-state
> `ProjectModel::getPaymentStatus()` vocabulary instead (`PAID` / `PARTIAL` / `PENDING` / `N/A`),
> since it is simpler and already implemented. Kept below for the reasoning trail only.

* **What it answers:** "Has the contract value been fully invoiced and collected?" — a
  project-level rollup, not per-invoice.
* **Values (proposed, 3-state, matching the already-correct pattern the audit found in
  `projects/statement.php:136-159`):**
  * **Fully Billed & Settled** — `total_billed ≈ total_project_value` (within the existing 0.004
    epsilon) AND `outstanding_collection_balance ≤ 0.004`.
  * **Billing In Progress** — `total_billed < total_project_value`, i.e. contract value remains
    unbilled (this is `remaining_billable_value > 0.004`, a field `getFinancialSummary()` already
    returns today but which the audit noted "is not currently displayed anywhere").
  * **Billed, Collection Pending** — `total_billed ≈ total_project_value` but
    `outstanding_collection_balance > 0.004` (fully invoiced, customer still owes money).
* **Who controls it:** Nobody. Fully derived, every time, from
  `ProjectModel::getFinancialSummary()` fields that already exist (`total_billed`,
  `total_project_value`, `remaining_billable_value`, `outstanding_collection_balance`). No new
  calculation is introduced — this status is a *label* over three existing numbers, following the
  exact same "reuse an existing pattern, don't invent a new one" approach 2.0D used for RC2.
* **Where it appears:** New Project Billing Overview (§3) as the headline badge; optionally the
  Project View Health Dashboard (placement decision, not made here — Phase B call).
* **Relationship to the other two statuses:** This is the status Manual Completion (§4/§5) should
  be checked against before locking — it is the thing the Section 5 warnings are actually warning
  about. It is independent of Work Status (a project can be `Billing In Progress` while Work Status
  is `COMPLETED` if the user completes work before finishing billing — Business Decision #2 says
  that's allowed, it's just something to warn about, per §5) and independent of Invoice Payment
  Status (a project can be `Fully Billed & Settled` while individual invoices show a mix of `PAID`
  history, since it's a sum, not a per-row state).

### 1.4 Summary table

| Status | Column | Set by | Meaning | Consumed by |
|---|---|---|---|---|
| Work Status | `projects.status` | User, manual (Projects index/Edit) | Is field work happening | Completion Lock (§4) |
| Invoice Payment Status | `sales.status` | System, derived (`SaleModel`) | Is this invoice paid | Sales List, Payment screen, Billing Overview |
| Project Billing Status | *(derived, not stored)* | System, derived (`getFinancialSummary()`) | Is the whole contract billed & collected | Billing Overview badge, Completion warnings (§5) |

---

## Section 2 — Payment Workflow

Business Decision #5: **Project → Invoice List → Payment Entry.** Business Decision #1: any
invoice, any order.

### 2.1 Screen flow

```
Step 1: Select Project
  ├─ Dropdown or searchable list of projects (all Work Statuses included — a
  │  COMPLETED project must still be able to receive payments per Decision #4)
  └─ On select → load Step 2

Step 2: Invoice List (for the selected project only)
  ├─ Table of every sales.* row where project_id = selected project
  │  (NOT filtered to status != 'PAID' — a fully paid invoice should still be
  │  visible here for context, just not selectable for new payment)
  ├─ Columns: Invoice No, Invoice Date, Total Amount, Advance Applied,
  │  Paid Amount, Invoice Pending (balance_amount), Invoice Payment Status badge
  ├─ Any invoice with balance_amount > 0.004 is selectable for payment,
  │  regardless of position/date — satisfies Decision #1 directly, since
  │  "any order" simply means no ordering constraint is enforced here
  └─ Selecting one invoice → load Step 3

Step 3: Payment Entry
  ├─ This IS the existing payments/create.php form, reused as-is — same fields
  │  (amount, payment_date, method, reference, notes), same max-amount JS
  │  guard against balance_amount, same POST to Payments::store()
  └─ Only the entry point changes (arrives pre-scoped to one project → one
     invoice instead of one global invoice dropdown); the form and its
     validation logic are not redesigned
```

### 2.2 Reuse, not replacement

* **`Payments::store()` / `update()` / `recalculatePaymentState()`** — unchanged. The pending-amount
  validation (`amount > balance_amount + 0.01` rejected) already implements "can't overpay one
  invoice"; nothing about multi-invoice selection changes that per-invoice rule.
* **`payments/create.php` markup** — the existing invoice `<select>` + detail table
  (`#detPending`, `#amountInput` max-attr JS) becomes the Step 3 partial. Today it lists *every*
  unpaid sale system-wide with a project name column; in the new flow it would be pre-filtered to
  the chosen project's invoices by the time it renders. This is presented as the natural extension
  of the existing component, not a rebuild — the actual filtering mechanism (query scope vs.
  client-side JS filter vs. a `project_id` route/query param) is a Phase B implementation choice.
* **Sales List and Sale View** are unaffected — this is an additional entry path into the same
  Payment Entry step, not a replacement for the existing global `/payments/create` (a user who
  already knows the invoice number can presumably still reach Payment Entry directly; whether the
  global entry point stays, is deprecated, or redirects through Step 1 is a Phase B decision).

### 2.3 Why Project-first satisfies "any invoice, any order"

Scoping the invoice list to one project first does not conflict with Decision #1 — it only adds a
navigation step ahead of the existing invoice picker. Once inside Step 2, every invoice for that
project is shown and selectable regardless of its invoice date, sequence number, or current
balance size; nothing here re-introduces a "must pay oldest invoice first" rule (which the codebase
has never had — advance FIFO in `SaleModel::recalculateProjectBilling()` allocates the *advance*,
not manual payments, oldest-invoice-first, and that mechanism is untouched by this design).

---

## Section 3 — Project Billing Overview

A new section inside `projects/view.php` (placement: below the existing Health Dashboard KPI
cards, above or alongside the existing sales table — exact position is a Phase B layout decision).

### 3.1 Invoice counts

Four counts, all derivable from the same `sales` rows `Projects::view()` already loads
(`$data['sales']`) or a `COUNT(...) GROUP BY status` query on `sales WHERE project_id = ?` — no new
join, no new table:

| Count | Definition |
|---|---|
| Total Invoices | all rows for the project |
| Paid Invoices | `status = 'PAID'` |
| Partially Paid | `status = 'PARTIAL'` |
| Payment Pending | `status = 'UNPAID'` |

### 3.2 Invoice summary table

One row per invoice, project-scoped (this is the same result set Step 2 of the Payment Workflow
(§2) needs — the two can share one query/partial rather than being built twice, avoiding a new
RC4/RC5-style duplicate-calculation site):

| Column | Source |
|---|---|
| Invoice No | `sales.invoice_no` |
| Invoice Date | `sales.sale_date` |
| Total Amount | `sales.total_amount` |
| Advance Applied | `sales.advance_applied` |
| Paid Amount | `sales.paid_amount` |
| Invoice Pending | `sales.balance_amount` |
| Invoice Payment Status | `sales.status`, rendered with the 2.0D `$statusLabels` map (Invoice Paid / Partial Payment / Payment Pending) — same convention, not a new one |
| Action | link into Payment Entry (§2, Step 3) pre-selecting this invoice, when `balance_amount > 0.004` |

### 3.3 Billing Status badge

The Project Billing Status (§1.3) headline badge sits above the table: "Fully Billed & Settled" /
"Billing In Progress" / "Billed, Collection Pending", each with the color convention already
established (green / orange / amber-orange) rather than inventing new colors — following the same
"reuse the statement.php pattern" principle 2.0D applied to RC2.

### 3.4 What this section explicitly does not duplicate

`getFinancialSummary()` remains the single source for the rollup numbers (`total_billed`,
`outstanding_collection_balance`, etc.). The Billing Overview's invoice-level table is a *new*
per-invoice view that does not currently exist anywhere (Sales List shows invoices across all
projects; Statement shows a timeline, not an invoice table) — it is additive, not a re-derivation
of an existing screen.

---

## Section 4 — Completion Lock Rules (SUPERSEDED — see Final Approved Business Workflow §4)

> **This entire section is superseded.** The business decision it was built on ("Completed
> projects lock Sales, Purchases, and Expenses") was revised in the follow-up review: completion is
> now purely a Work Status marker and locks nothing. Kept below only for the code-tracing detail
> (which files currently have a `COMPLETED` guard and which don't) — that inventory is still
> factually accurate about the codebase, it is the *lock proposal* built on top of it that no
> longer applies.

Business Decision #3 (ORIGINAL, no longer in effect): **Completed projects lock Sales, Purchases,
and Expenses.** Business Decision #4: **Payments remain allowed after completion until all
invoices are settled.**

### 4.1 Trigger

Work Status = `COMPLETED` (§1.1), set manually via the existing `Projects::updateStatus()` toggle
or Edit form. No new field is needed to represent "locked" — `projects.status === 'COMPLETED'` *is*
the lock condition, matching the pattern already partially implemented in `Sales::store()` and
`Expenses::store()`.

### 4.2 What becomes read-only

| Module | Current state (post-2.0D) | 2.1A target state |
|---|---|---|
| **Sales** — create | Already blocked (`Sales::store()` line 115-121 rejects when project is `COMPLETED`) | No change needed |
| **Sales** — edit/update existing invoice | **Not currently guarded** — no check found in `Sales.php`'s update path | Must become read-only: editing an invoice's line items/amounts on a completed project should be blocked the same way create is |
| **Sales** — delete | Not currently guarded | Must become read-only, same reasoning |
| **Purchases** — create | **Not currently guarded at all** — no `COMPLETED` check exists anywhere in `Purchases.php` (confirmed by direct read; contrast with `Expenses.php`'s explicit `where('status !=', 'COMPLETED')` dropdown filter and store-time guard) | Must gain the same create-time guard Sales/Expenses already have |
| **Purchases** — edit/delete | Not currently guarded | Must become read-only |
| **Expenses** — create | Already blocked (`Expenses::store()` line 45-48) and already excluded from the project dropdown (line 32-34) | No change needed |
| **Expenses** — edit/delete | Edit form already includes the completed project in its dropdown "so the form is valid" (line 69-75) if the expense already belongs to one, but the store-time guard (line 97-100) blocks saving changes to it | Confirm this fully covers "read-only" (view allowed, save blocked) — likely already correct, verify in Phase B |
| **Payments** — create/edit/delete | Not guarded, and *should not be* per Decision #4 | Explicitly exempted from the lock — a completed project keeps accepting payments against its existing invoices until `outstanding_collection_balance ≤ 0.004` for every invoice |
| **Project record itself** (name, contract value, advance, dates) | `Projects::update()` has no completion guard | Not addressed by Decisions #1-5; left open as a Phase B question — locking the project's own financial fields after completion is a plausible follow-on but wasn't asked for here |

### 4.3 What is explicitly NOT locked

* Payments (Decision #4) — the sole intentional exception, and the reason "Completed" cannot simply
  mean "no more writes anywhere."
* Read/view access — Sales List, Sale View, Purchase view, Expense view, Project View, Statement,
  and the new Billing Overview remain fully viewable for a completed project; "lock" means write
  operations only, never visibility.

### 4.4 Enforcement pattern to reuse

Both existing guards follow the same two-layer shape and 2.1A should extend that shape rather than
invent a new one:

1. **Query-level exclusion** where relevant (e.g. `Expenses::create()`'s dropdown already excludes
   `COMPLETED` projects from the picklist so the invalid state is hard to even reach through the UI).
2. **Server-side rejection** at the controller action that performs the write (`if ($project &&
   $project['status'] === 'COMPLETED') { reject }`), so a direct POST can't bypass the UI-level
   exclusion.

Purchases needs both layers added (currently has neither); Sales needs layer 2 extended to its
edit/delete actions (currently has it only on create).

---

## Section 5 — Warning Rules

Warnings only — surfaced when the user attempts to set Work Status to `COMPLETED` (i.e., inside
`Projects::updateStatus()` / the Edit form flow), before the status is written. None of these block
completion; Decision #2 says completion is manual and the user's call.

### 5.1 Remaining contract value not billed

* **Condition:** `remaining_billable_value > 0.004` (i.e., `total_project_value − advance_amount −
  total_billed > 0` — already computed by `getFinancialSummary()`, currently unused/undisplayed per
  the 2.0C audit's note).
* **Message shape:** "This project still has ₹X of contract value that has not been invoiced.
  Completing work now means that amount may never be billed."
* **Corresponds to:** Project Billing Status = `Billing In Progress` (§1.3).

### 5.2 Pending invoices still exist

* **Condition:** any `sales` row for the project with `status IN ('PARTIAL', 'UNPAID')` — i.e.
  `Invoice Payment Status` (§1.2) not yet `PAID` for at least one invoice. Equivalent to
  `ProjectModel::getPaymentStatus($id) !== 'PAID'` (excluding the `'N/A'`/no-invoices case, which
  isn't a "pending invoice" condition).
* **Message shape:** "N invoice(s) on this project are not fully paid ({{list of invoice numbers}}).
  You can still record payments after completion, but consider reviewing them first."
* **Corresponds to:** the invoice-level counts already designed in §3.1 (Partially Paid + Payment
  Pending counts).

### 5.3 Customer pending amount exists

* **Condition:** `outstanding_collection_balance > 0.004` (strictly positive — the existing
  sign-aware convention from 2.0D's RC2 fix; a *negative* balance is Advance Credit, not something
  to warn about here).
* **Message shape:** "Customer still owes ₹X on this project (Customer Pending). This will remain
  collectible after completion per policy, but is shown here for visibility."
* **Corresponds to:** Project Billing Status = `Billed, Collection Pending` (§1.3), or the pending
  portion of `Billing In Progress`.

### 5.4 Presentation

All three checks run from data `getFinancialSummary()` and `getPaymentStatus()` already provide —
no new calculation. Suggested shape (Phase B decision): a single confirmation dialog at the
completion action listing every triggered warning as a bullet, with an explicit "Complete Anyway"
action — never a hard block, consistent with Decision #2's "completion is manual" meaning the
user's decision is final, not gated on these conditions being clear first.

---

## Open questions carried to Phase B (not decided here) — ORIGINAL DRAFT, see also Final §7

1. Exact route/param shape for scoping the Payment screen's invoice list to a project (§2.2).
2. Whether the existing global `/payments/create` entry point is kept, redirected, or deprecated.
3. Whether Project Billing Status also gets a home on the Health Dashboard KPI row, or stays
   exclusive to the new Billing Overview (§3).
4. Exact confirmation-dialog UX for the Section 5 warnings (inline banner vs. modal vs. toast list).
5. ~~Whether the Project record's own fields... should lock on completion~~ — moot: the final
   lifecycle decision (Final §4) locks nothing on completion, so this question no longer applies.

---

# Final Approved Business Workflow (v2.1A)

**This section is authoritative.** Where anything above (Sections 1–5 of the original draft)
conflicts with what follows, this section governs. It reflects the finalized business workflow
agreed in review, still design-only — no code, schema, controller, model, view, route, migration,
or export was changed to produce it.

## Final §1 — Three-Status Architecture (Frozen)

Three completely independent statuses. None derives from or writes to either of the others.

### 1.1 Work Status — Manual

| | |
|---|---|
| Values | `ACTIVE`, `ON HOLD`, `COMPLETED` |
| Purpose | Physical/project execution status only — is the field work happening |
| Set by | User, manually — existing `projects.status` column and existing `Projects::updateStatus()` quick-toggle / Edit form. No new field. |
| Never | Changes automatically from invoice payments, invoice status, or any Sales/Purchases/Expenses/Payments activity |
| Appears in UI | Projects index (status badge/dropdown), Project View header badge, Project Edit form |

### 1.2 Invoice Payment Status — Automatic

| | |
|---|---|
| Values | `PAID`, `PARTIAL`, `UNPAID` |
| Purpose | Payment status of one individual invoice |
| Set by | Nobody manually — already fully driven by existing invoice payment logic (`SaleModel::_recalculateSaleRow()` / `recalculatePaymentState()`), unchanged by this release |
| Appears in UI | Sales List badge, Sale View, Payment screen's invoice list (Final §2 Step 2), Billing Overview invoice table (Final §3) |

### 1.3 Project Billing Status — Automatic, Derived

| | |
|---|---|
| Values | `PAID`, `PARTIAL`, `PENDING`, `N/A` |
| Purpose | Overall billing/payment health of the project — one label summarizing all of that project's invoices |
| Derived by | Aggregating Invoice Payment Statuses for every invoice on the project — the same rule already implemented in `ProjectModel::getPaymentStatus()` (all invoices `PAID` → `PAID`; at least one `PARTIAL` or `PAID` among the rest → `PARTIAL`; none paid at all → `PENDING`/`UNPAID`; zero invoices on the project → `N/A`). No new aggregation rule is introduced — this is that existing method's output, relabeled `UNPAID`→`PENDING` for UI consistency with the "Pending" vocabulary used elsewhere. |
| Stored? | **No.** Never written to the database — computed fresh on every read, exactly like today. |
| Appears in UI | Project View (new Billing Overview badge, Final §3), Projects index (optional additional column — placement is a Phase B call), Completion warning dialog (Final §5) |

### 1.4 Where each status appears — quick reference

| Screen | Work Status | Invoice Payment Status | Project Billing Status |
|---|---|---|---|
| Projects index | ✅ badge + toggle | — | optional column |
| Project View header | ✅ badge | — | — |
| Project View → Billing Overview (new) | — | ✅ per-invoice, in table | ✅ headline badge |
| Sales List | — | ✅ per row | — |
| Sale View | — | ✅ | — |
| Payment screen (new, Final §2) | — | ✅ per invoice in list | — |
| Completion confirmation dialog (Final §5) | (the field being changed) | referenced in warning text | ✅ referenced in warning text |

### 1.5 Independence, stated explicitly

* Work Status never changes because an invoice got paid, because all invoices got paid, or because
  Project Billing Status reaches `PAID`. The only writer of `projects.status` is the user, via the
  existing manual toggle/Edit form.
* Invoice Payment Status never changes because Work Status becomes `COMPLETED`. It is untouched by
  this release.
* Project Billing Status is read-only math over Invoice Payment Statuses; it never writes anything
  back to `sales` or `projects`.

## Final §2 — Payment Workflow (Final, UX revised)

Same 3-step shape as the original draft (Section 2 above); this revision replaces the *presentation*
of Step 2 — a project-scoped **invoice list/card layout**, explicitly superseding the legacy
`payments/create.php` global `<select>` dropdown as the selection mechanism (the dropdown's
underlying data source and validation are still reused — see 2.2 — only the picker UI changes).

```
┌─────────────────────┐     ┌──────────────────────────────┐     ┌───────────────────────┐
│ Step 1               │     │ Step 2                        │     │ Step 3                │
│ Select Project        │ ──▶ │ Invoice List/Cards (project)  │ ──▶ │ Payment Entry          │
│                       │     │                                │     │                        │
│ Project dropdown/     │     │ Per card: Invoice No | Invoice │     │ Existing payment form, │
│ search — all Work     │     │ Total | Advance Applied | Paid │     │ reused as-is, opens    │
│ Statuses included     │     │ Amount | Invoice Pending |     │     │ for the invoice whose  │
│                       │     │ Invoice Payment Status |       │     │ Record Payment action  │
│                       │     │ [Record Payment] action        │     │ was clicked            │
└─────────────────────┘     └──────────────────────────────┘     └───────────────────────┘
```

* **Step 1 — Select Project.** No Work Status filter — a `COMPLETED` project still appears and is
  selectable, since Payments (and, per Final §4, everything else) remain allowed after completion.
* **Step 2 — Invoice List/Cards (UX revised).** System loads every invoice belonging to the
  selected project and renders it as a list of invoice cards/rows (list on desktop widths, card
  stack on narrow widths — a Phase B responsive-layout choice, not decided here) instead of a
  `<select>` dropdown. **This is the design that replaces the legacy invoice dropdown from
  `payments/create.php`.** Each invoice card/row shows exactly:
  * Invoice No
  * Invoice Total
  * Advance Applied
  * Paid Amount
  * Invoice Pending
  * Invoice Payment Status
  * **Record Payment** action (button/link on the card itself)

  All invoices are listed, not just unpaid ones, for context; **Record Payment** is only shown/
  enabled when `Invoice Pending > 0`. Clicking **Record Payment** on a card is what advances to
  Step 3 for that invoice — the action lives on the card, not behind a separate "select then
  continue" click.
* **Step 3 — Payment Entry.** The payment form opens (below the selected card, or as a panel —
  Phase B layout call) pre-scoped to that invoice. Reuses the existing `payments/create.php` fields
  and validation (`Payments::store()`, `recalculatePaymentState()`) unchanged — only the *entry
  point* into this form changes (arrives from a card's Record Payment action instead of a
  dropdown selection). **No new payment logic. No FIFO payment ordering.** The user may pay any
  invoice, in any order — satisfied simply by every card being independently actionable regardless
  of invoice date or position in the list.

## Final §3 — Project Billing Overview (Final, expanded)

New section inside Project View — designed as **the financial center of Project View**: the one
place a user reads to understand a project's entire money story (contract, invoicing, collection,
history, and recent activity) without cross-referencing Statement or Sales List.

### 3.0 Financial Center Summary

The headline block at the top of Billing Overview. Seven figures, all already available from
`ProjectModel::getFinancialSummary()` / `projects` table — no new calculation source:

| Figure | Source | Notes |
|---|---|---|
| Contract Value | `total_project_value` | `projects.total_project_value`, unchanged |
| Total Invoiced | `total_billed` | `SUM(sales.total_amount)` for the project |
| Total Customer Paid | `total_paid` | `SUM(sales.paid_amount)` — payments table only, excludes advance (per the 2.0C audit's distinction: advance ≠ a payment row) |
| Customer Pending | `outstanding_collection_balance` (when > 0.004) | Sign-aware, per 2.0D's RC2 pattern — shown only when positive (money owed) |
| Advance Credit Remaining | `abs(outstanding_collection_balance)` (when < -0.004) | Same field, opposite sign — the unconsumed portion of `advance_amount` not yet applied to any invoice |
| Remaining To Bill | `remaining_billable_value` | **Recommended addition (see 3.0.3 review note).** Already computed by `getFinancialSummary()` and already consumed internally by the Final §5 warning (condition 1), but never surfaced to the user as a plain rupee figure — today it is only implied through Contract Billing Progress %. For a long-duration project, users think in rupees left to invoice, not just a percentage; shown only when `> 0.004`. |
| Contract Billing Progress | see 3.0.1 | How much of the contract has been invoiced |
| Invoice Collection Progress | see 3.0.2 | How much of what's been invoiced has actually been collected |

Customer Pending and Advance Credit Remaining are mutually exclusive displays of the same signed
field (§Final 5's conditions 3/4 use the identical sign split) — a project shows one or the other,
never both.

#### 3.0.1 Contract Billing Progress

**What it answers:** "Of the total contract value, how much has been invoiced so far?"

```
Contract Billing Progress % = (Total Invoiced / Contract Value) × 100
                             = (total_billed / total_project_value) × 100
```

This is the existing `billing_progress_percent` field `getFinancialSummary()` already returns —
no new formula. Measures progress against the **contract**, independent of whether invoiced
amounts have been collected yet.

#### 3.0.2 Invoice Collection Progress

**What it answers:** "Of what has already been invoiced, how much has actually been collected
(via advance application + direct payment)?" — a distinct question from 3.0.1, deliberately kept
as a separate figure rather than merged into one "progress" number, since a project can be 100%
billed and 0% collected (or vice versa isn't possible, but partial/partial combinations are common
and the two audits both flagged conflating billing with collection as a source of confusion).

```
Invoice Collection Progress % = ((Total Invoiced − Customer Pending) / Total Invoiced) × 100
                              = ((total_billed − outstanding_collection_balance) / total_billed) × 100
```

When `outstanding_collection_balance` is negative (Advance Credit state), this formula naturally
produces a result **above 100%** — correctly signaling that collection (advance + payments) has
outpaced billing itself, which is exactly project 29's situation in the 2.0C audit (₹21,240
invoiced, ₹50,000 advance received → collection outpaces billing). This is display-only math over
existing fields (`total_billed`, `outstanding_collection_balance`) — no new query, no new stored
value; a display convention for the >100% case (e.g. cap the progress bar visually at 100% while
still stating the true percentage in text) is a Phase B decision, not made here.

Both progress figures read from the same `getFinancialSummary()` call already used everywhere else
on this page — this section adds no new database access.

#### 3.0.3 Reading Work Status, Contract Billing Progress, and Invoice Collection Progress together

**Recommended addition** (review finding — see Final §9). These three signals are individually
documented (§1.1, §3.0.1, §3.0.2) but nothing today states that they are three *independent axes*
that can each sit at a different point at the same time — exactly the kind of drift that shows up
on a long-duration project and is easy to misread if only one number is glanced at. No new
calculation; this is a presentation-order/explanation recommendation over figures already designed.

| Work Status | Contract Billing Progress | Invoice Collection Progress | What it actually means |
|---|---|---|---|
| ACTIVE | 100% | 60% | Fully invoiced, still collecting — normal end-of-billing state, work may still be ongoing |
| COMPLETED | 40% | 100% | Work finished early relative to contract scope; 60% of contract value was never invoiced (see Remaining To Bill above and Final §5 condition 1) |
| ACTIVE | 30% | 100%+ | Advance received exceeds what's been invoiced so far (Advance Credit state) — collection is *ahead* of billing, not behind |
| COMPLETED | 100% | 100% | Fully billed and fully collected — the clean-close case |

Recommended framing for Phase B: present Contract Billing Progress and Invoice Collection Progress
side by side (not stacked as if they were one bar), with Work Status shown separately as the badge
it already is (§1.1) — never implying that 100% on either progress figure means the project is
"done" or should be `COMPLETED`, since that remains an entirely manual, unrelated decision
(Decision #2, Final §1.5).

### 3.1 KPI Summary

| KPI | Definition |
|---|---|
| Total Invoices Raised | count of all `sales` rows for the project |
| Paid Invoices | count where Invoice Payment Status = `PAID` |
| Partial Invoices | count where Invoice Payment Status = `PARTIAL` |
| Pending Invoices | count where Invoice Payment Status = `UNPAID` |
| Invoice Pending Amount | `SUM(sales.balance_amount)` across the project's invoices |

### 3.2 Invoice Summary Table

One row per invoice: **Invoice No, Invoice Total, Paid, Pending, Status, View Invoice (action),
Record Payment (action).** "Record Payment" links into Final §2 Step 3, pre-selecting that invoice
— reusing the same query/result set as the Payment screen's Step 2 list rather than building it
twice (avoids a new duplicate-calculation site, the RC4/RC5 problem from the 2.0C audit).

### 3.3 Customer Payment History

Chronological list of money movements for the project, with the advance kept visually distinct
from invoice payments (they are different mechanisms — `projects.advance_amount`/`advance_date` is
a single project-level field consumed via FIFO into invoices, while `payments` rows are per-invoice
transactions):

| Date | Type | Amount | Applied To |
|---|---|---|---|
| `projects.advance_date` | Advance Payment | `projects.advance_amount` | Project (pre-allocated across invoices by existing FIFO logic) |
| `payments.payment_date` (one row per payment) | Invoice Payment | `payments.amount` | `sales.invoice_no` (via `payments.sale_id`) |

This reuses `ProjectModel::getTimelineEvents()`'s existing event sourcing (already assembles
Purchase/Advance/Sales-Invoice rows chronologically for the Statement page) as the pattern to
follow — Customer Payment History is the same idea narrowed to just the two payment-type events
(Advance, Invoice Payment) instead of also including Purchase/cost rows. UI only; no new
calculation beyond reading `payments` and the project's advance fields, both already queried
elsewhere.

### 3.4 Recent Project Activity

A compact activity feed, newest first, distinct from 3.3 in scope: 3.3 covers *money received from
the customer only* (advance + invoice payments); 3.4 covers *everything that happened on the
project* — the broader event set. Event types:

| Event | Source | Notes |
|---|---|---|
| Invoice created | `sales` row insert (`sale_date`/`created_at`) | |
| Payment received | `payments` row insert (`payment_date`/`created_at`) | Same rows as §3.3's Invoice Payment line |
| Purchase added | `purchases` row insert (`purchase_date`/`created_at`) | Project-linked purchases only (`purchases.project_id`) |
| Expense added | `expenses` row insert (`expense_date`/`created_at`) | Project-linked expenses only |
| Stock Return | **Not currently available** — see note below | Included conditionally, per "(if retained)" |

**Stock Return verified against current codebase:** a Stock Returns feature existed at one point
(`app/Database/Migrations/2026-08-27-000002_CreateStockReturns.php`) but was subsequently removed
(`app/Database/Migrations/2026-08-28-000001_RemoveStockReturns.php`) — there is no `stock_returns`
table or controller in the codebase today. Per the "(if retained)" qualifier in the request, this
event type is **not included** in the activity feed as designed; it is documented here only so a
future release that reintroduces Stock Returns knows where it would plug into this feed.

This feed is a superset view over the same rows §3.4's sibling sections and `getTimelineEvents()`
already touch (`sales`, `payments`, `purchases`, `expenses`) — no new table, no new calculation,
just a merged/sorted read across four already-queried sources, newest first. Whether it lives
inside Billing Overview or as its own Project View panel is a Phase B layout decision.

## Final §4 — Project Lifecycle (Final)

Replaces the original Section 4 "Completion Lock Rules" in full.

| Work Status | Sales | Purchases | Expenses | Payments | Notes |
|---|---|---|---|---|---|
| **ACTIVE** | ✅ allowed | ✅ allowed | ✅ allowed | ✅ allowed | Normal project |
| **ON HOLD** | ✅ allowed | ✅ allowed | ✅ allowed | ✅ allowed | Optional pause state — transactions still allowed, no different from ACTIVE for write purposes |
| **COMPLETED** | ✅ allowed | ✅ allowed | ✅ allowed | ✅ allowed | Work finished; financial activity may continue |

* **No locking of any module in any Work Status.** This replaces the original Business Decision #3
  ("Completed projects lock Sales, Purchases, and Expenses") — that decision was revised in
  review. `COMPLETED` now means only "the field/delivery work is finished," nothing about write
  permissions.
* **This is manual**, exactly as before: the user sets Work Status via the existing
  `Projects::updateStatus()` toggle or the Edit form. Nothing else sets it.
* **No `CLOSED` or `ARCHIVED` status is introduced in this release.** The lifecycle stays
  exactly the three existing values (`ACTIVE` / `ON HOLD` / `COMPLETED`) — no new enum value, no
  schema change.
* Consequently, the entire original Section 4 enforcement inventory (which controllers currently
  guard `COMPLETED`, which don't) becomes informational only under this final decision — it
  documents a pre-existing partial guard in `Sales::store()`/`Expenses::store()` that this release
  does not ask to extend, remove, or make consistent. Whether that pre-existing guard should
  eventually be removed for consistency with the new "COMPLETED locks nothing" rule is a Phase B/
  future-release question, not decided here.

## Final §5 — Completion Warning Design (Final)

Confirmation dialog shown when the user changes Work Status to `COMPLETED`. Four warning
conditions, all computed from existing data, all non-blocking:

```
┌───────────────────────────────────────────────────────────┐
│  Mark project "…" as COMPLETED?                            │
│                                                              │
│  ⚠ Remaining contract value has not been invoiced           │
│     (₹{{remaining_billable_value}} unbilled)                │
│                                                              │
│  ⚠ Pending invoices still exist                             │
│     ({{n}} invoice(s) not fully paid)                       │
│                                                              │
│  ⚠ Customer pending amount exists                           │
│     (₹{{outstanding_collection_balance}} owed by customer)  │
│                                                              │
│  ⚠ Advance credit still exists                              │
│     (₹{{|outstanding_collection_balance|}} of advance       │
│      not yet consumed by any invoice)                       │
│                                                              │
│              [ Cancel ]        [ Complete Anyway ]           │
└───────────────────────────────────────────────────────────┘
```

Each condition is independent and only shown when triggered — a project with none of the four
active shows no warnings and completes immediately (or with a plain confirm, Phase B's call).

| # | Condition | Existing source |
|---|---|---|
| 1 | Remaining contract value not invoiced | `remaining_billable_value > 0.004`, already returned by `getFinancialSummary()` |
| 2 | Pending invoices still exist | any invoice with Invoice Payment Status `PARTIAL` or `UNPAID`; equivalent to Project Billing Status ≠ `PAID` and ≠ `N/A` |
| 3 | Customer pending amount exists | `outstanding_collection_balance > 0.004` (strictly positive — money owed *to* the business) |
| 4 | Advance credit still exists **(new in this revision)** | `outstanding_collection_balance < -0.004` (strictly negative — unconsumed advance sitting against the project, the same sign convention 2.0D's RC2 fix already established for the "Advance Credit" display state) |

Conditions 3 and 4 are mutually exclusive (the balance can't be both positive and negative), so at
most one of them fires per project — a project is never shown both "customer owes money" and
"unused advance remains" at once.

* **Warnings only — no blocking logic.** `[Complete Anyway]` always succeeds regardless of how
  many warnings fired; there is no state that prevents the status write.
* Consistent with Final §4: since completion no longer locks anything, these warnings exist purely
  to inform the user before they flip the switch, not to gate a lock they're about to trigger.

## Final §6 — UI Vocabulary Standardization

Terminology to use everywhere, replacing any inconsistent labels found in the 2.0C/2.0D audits:

**Project screens** (Project View, Project Edit, Projects index, Statement):
* "Customer Pending"
* "Project Billing Status"
* "Work Status"

**Sales and Payments screens** (Sales List, Sale View, Payment screen, Payments index):
* "Invoice Pending"
* "Invoice Payment Status"

**Banned term:** "Completed Sale" — must not appear anywhere in the UI. Use **"Paid Invoice"**
instead. (This continues, rather than reverses, 2.0D's RC1 fix, which already renamed the Sales
List's "Completed Sales" tab to "Paid Invoices" — this section makes that vocabulary rule explicit
and permanent project-wide, not just for that one tab.)

| Concept | Correct term | Screens |
|---|---|---|
| Work-status field | Work Status | Project screens |
| Project-level billing rollup | Project Billing Status | Project screens |
| Project-level amount owed/credit | Customer Pending | Project screens |
| Per-invoice amount owed | Invoice Pending | Sales/Payments screens |
| Per-invoice payment state | Invoice Payment Status | Sales/Payments screens |
| A `sales.status = PAID` row | Paid Invoice | everywhere — never "Completed Sale" |

## Final §7 — Open Questions Carried Forward

1. Exact route/param shape for scoping the Payment screen's invoice list to a project.
2. Whether the existing global `/payments/create` entry point is kept, redirected, or deprecated
   now that a project-first entry path exists.
3. Exact confirmation-dialog UX mechanics for Final §5 (modal vs. inline banner vs. toast list).
4. Whether Project Billing Status gets a column on the Projects index in addition to the Billing
   Overview badge.
5. Whether the pre-existing `COMPLETED` guards in `Sales::store()`/`Expenses::store()` (which
   predate this release) should be removed for consistency with Final §4's "COMPLETED locks
   nothing," or left as-is. Not decided in this design.

No implementation approach for any of the above is recommended here — Phase B's job.

## Final §8 — Future Enhancement Notes

Documented for visibility only. **None of the following are part of Release 2.1B** (the next
implementation phase for this workflow) — they are **Future Release** candidates, listed here so
the idea isn't lost, with no design detail, no schema, and no implementation approach committed:

* **Completion Date** — a distinct timestamp for "when Work Status became COMPLETED," separate
  from the existing `end_date` field (`Projects::updateStatus()` currently auto-fills `end_date`
  when completing without one already set — whether that's the same concept as a proposed
  "Completion Date" or a new field is an open question for whichever future release picks this up).
* **Completed By** — recording which user performed the completion action. Not designed here;
  the codebase's current auth/user-attribution model would need review first.
* **Auto Allocate Project Payment** — a mode where a single payment entered against a *project*
  (rather than one invoice) is automatically distributed across that project's multiple pending
  invoices, instead of the user picking one invoice per payment as designed in Final §2. This
  would be a materially different mechanism from today's per-invoice `Payments::store()` and is
  explicitly **not** designed, sequenced, or scoped here — flagged as an idea only.

**Marked explicitly: Future Release — not Release 2.1B.**

---

## Final §9 — Design Review: Progress, Counts, Remaining-to-Bill, and Gaps for Long-Duration Projects

Design-only review pass requested before implementation. No code, schema, controller, or model was
read beyond what was already checked for the sections above; conclusions rest entirely on the
figures/fields already inventoried in this document.

### 9.1 Should Project Progress (Billing vs Collection vs Work Status) be formally added?

**Yes — already mostly present, now made explicit.** Contract Billing Progress (§3.0.1) and Invoice
Collection Progress (§3.0.2) were already designed; the gap was that nothing tied them to Work
Status as three independent, sometimes-diverging signals. Addressed by adding §3.0.3 above. No new
calculation was introduced — §3.0.3 is a reading/interpretation guide over existing figures.

### 9.2 Should Invoice Count Summary be formally added?

**Already present — no gap.** §3.1 KPI Summary already covers this exactly (Total Invoices Raised,
Paid, Partial, Pending, plus Invoice Pending Amount). No addition needed; noting here only because
the review question named it explicitly and it's worth confirming it isn't missing.

### 9.3 Should Remaining To Bill be formally added?

**Yes — this was a real gap, now fixed.** `remaining_billable_value` was computed by
`getFinancialSummary()` and used internally by the Final §5 warning, but was never surfaced as a
headline rupee figure anywhere in §3.0 — a user could only infer it indirectly from 100% minus
Contract Billing Progress. Added as a row in the §3.0 Financial Center Summary table above, shown
only when `> 0.004`, consistent with how Customer Pending / Advance Credit Remaining are each shown
only when their own sign condition triggers.

### 9.4 Other business concepts still missing — flagged, not designed

These are surfaced for awareness because this release is explicitly framed around **long-duration**
projects, where drift and staleness are the actual risk (not any single figure being wrong). None
of these are designed or scoped here — they are candidates for Final §8 (Future Enhancement Notes)
or a future Phase B open question, listed here only so they aren't lost:

* **Project duration / age.** No figure today shows "how long has this project been running"
  (`start_date` → today, or `start_date` → `end_date`). For a release specifically about
  long-duration projects, the absence of any duration indicator on Project View is a notable gap —
  a project active for 3 months and one active for 3 years currently look identical except for raw
  dates the user must subtract manually.
* **Staleness / last-activity signal.** §3.4 Recent Project Activity (already designed) shows *what*
  happened recently, but nothing flags *that nothing has happened in a while* — e.g. no invoice,
  payment, purchase, or expense in N days/months. On a long-duration project this is the difference
  between "quietly progressing" and "forgotten while still ACTIVE," and the current design has no
  way to distinguish the two at a glance.
* **Unresolved outcome for an unbilled remainder at completion.** Final §5 condition 1 warns when
  `remaining_billable_value > 0.004` at completion time, but the design never states what happens to
  that amount afterward — is it implicitly written off, does it stay a permanent gap in the
  project's financial history, or could the project be reopened/re-invoiced later? Today's answer is
  "nothing happens automatically, it's just a number that stops changing" (Work Status changes lock
  nothing per Final §4), which may be exactly right, but it has not been stated as a decision
  anywhere — only implied by absence. Worth an explicit one-line decision before Phase B rather than
  leaving it to be inferred.

No schema, controller, model, or view changes were made or proposed while producing this review;
all findings are documentation-level additions to sections already present in this file.
