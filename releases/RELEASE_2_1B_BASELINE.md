# Release 2.1B — Project Billing Overview & Payment UX — Baseline

Implements Phases 1-4 of `RELEASE_2_1B` against the frozen spec in
`RELEASE_2_1A_PROJECT_WORKFLOW_DESIGN.md`. Scope confirmed against
`RELEASE_2_1B_DEPENDENCY_ANALYSIS.md` before any file was edited: **no schema, migration, payment
calculation, FIFO advance allocation, project financial calculation, report, export, or Dashboard
change was made.**

## Files touched

| File | What changed |
|---|---|
| `app/Controllers/Projects.php` | One line added to `view()`: calls the existing `ProjectModel::getTimelineEvents()` (already used by the Statement page) into `$data['timeline']`. |
| `app/Controllers/Payments.php` | `create()` and `edit()`: widened the existing sales SELECT to also fetch `project_id`, `sale_date`, `status`; added a `$data['projects']` SELECT (`id`, `name`) for the new Step 1 filter; dropped the `WHERE s.status != 'PAID'` filter in `create()` so every invoice is listed for context (per Final §2). No change to `store()`, `update()`, `delete()`, or any validation logic. |
| `app/Views/projects/view.php` | Added: Billing Overview section (Phase 1 — KPI cards, two progress strips, Invoice Count Summary), Customer Payment History card (Phase 3), Recent Project Activity card (Phase 4). Pre-existing Health Dashboard, old billing-progress strip, Project Progress Summary, Project Details, Sales, and Purchases sections are unchanged. |
| `app/Views/payments/create.php` | Rewritten: Step 1 project filter, Step 2 invoice cards (replacing the `<select>` as the visible picker), Step 3 unchanged payment form. |
| `app/Views/payments/edit.php` | Same restructure, plus the pre-existing "add this payment's own amount back to pending" logic for the currently-selected invoice, preserved exactly. |

**Not touched:** any Model file, `SaleModel`, migrations, `app/Config/Routes.php` (all required
routes already existed), `Reports::*`, `Projects::statement*()` / `statementExport()` /
`statementPdf()`, `app/Views/dashboard/*`.

---

## Phase 1 — Billing Overview

New `card-custom` block titled "Billing Overview," placed directly below the existing "Project
Progress Summary" card and above "Project Details" on `projects/view.php`.

* **KPI cards** — Contract Value, Total Invoiced, Total Customer Paid always shown; Customer
  Pending / Advance Credit Remaining / Remaining To Bill each conditionally shown only when their
  underlying signed value is non-trivial (`> 0.004` or `< -0.004`), matching the existing 2.0D
  sign-aware convention already used elsewhere on this same page for the Health Dashboard's pending
  card. All six values read straight from `$financial_summary` (`ProjectModel::getFinancialSummary()`)
  — no new field.
* **Progress Area** — two separate strips, not merged:
  * Contract Billing Progress reuses `$financial_summary['billing_progress_percent']`
    (`$csPercent`), the same variable the pre-existing billing-progress strip above already
    computes — no duplicate calculation, just a second render of the same number in the new panel.
  * Invoice Collection Progress is computed in the view exactly as specified in Final §3.0.2 of the
    design doc: `(total_billed − outstanding_collection_balance) / total_billed × 100`. This is
    display-only PHP over two existing `$financial_summary` fields; not clamped to 100%, so an
    Advance Credit project can show a percentage above 100% as the design explicitly calls for.
* **Invoice Count Summary** — Total/Paid/Partial/Pending, each an `array_filter()` count over
  `$sales`, the array `Projects::view()` already loads. No new query.

## Phase 2 — Payment Screen Redesign

`payments/create.php` and `payments/edit.php` both restructured into three visible steps:

1. **Step 1 — Select Project.** A `<select>` populated from the new `$data['projects']` list.
   Selecting a project hides non-matching invoice cards client-side (`data-project-id` match); "--
   All Projects --" (the default) shows every card.
2. **Step 2 — Invoice List/Cards.** Each invoice renders as a card with exactly the seven fields
   the design specifies (Invoice No, Invoice Date, Invoice Total, Advance Applied, Paid Amount,
   Invoice Pending, Invoice Payment Status) plus a **Record Payment** button, disabled when
   `Invoice Pending ≤ 0.004`. All invoices are listed (not just unpaid ones) for context, per Final
   §2's "All invoices are listed... for context" instruction.
3. **Step 3 — Payment Entry.** The original form, byte-for-byte unchanged in its fields, IDs, and
   validation. `#saleSelect` is still present but now a hidden `<select>`; clicking a card's Record
   Payment button sets its value and fires `change`, which runs the exact same pre-existing handler
   that populates `#detTotal`/`#detAdvance`/`#detPaid`/`#detPending` and sets `#amountInput`'s `max`
   attribute. **No JS validation logic was rewritten** — only how the select's value gets set.

`edit.php` additionally keeps the pre-existing "add this payment's own amount back to the pending
figure for its own invoice" adjustment (`$selfAmount`), applied identically whether the invoice is
shown via its card or the hidden select.

**Compatibility confirmed:** `#saleSelect`, `#saleDetail`, `#detTotal`, `#detAdvance`, `#detPaid`,
`#detPending`, `#amountInput` all still exist with the same IDs and the same event wiring.
`Payments::store()`/`update()` were not opened for editing.

## Phase 3 — Customer Payment History

New read-only card on `projects/view.php`, filtered from the single `$timeline` array now fetched
by `Projects::view()` (`ProjectModel::getTimelineEvents()`, unchanged method) to `category ===
'Payment'`, reversed to newest-first. Displays each row's date, a badge distinguishing **Project
Advance** from **Invoice Payment**, amount, and the reference (`ADV-{project}` or the invoice
number the payment applied to) — reusing `getTimelineEvents()`'s existing `reference` field
verbatim.

## Phase 4 — Recent Project Activity

New read-only card alongside Phase 3's, using the same `$timeline` array filtered to
`type IN ('Sales Invoice', 'Invoice Payment', 'Purchase', 'Expense')` (Project Advance excluded —
it's the exclusive subject of Phase 3), reversed to newest-first, with each `type` relabeled to the
requested wording (Invoice Created / Payment Received / Purchase Added / Expense Added) via a small
display-only map, the same house pattern 2.0D used for `$statusLabels`.

**Stock Return excluded automatically** — `getTimelineEvents()` has never emitted a Stock Return
event type (the feature was added and removed at the migration level before this release, per the
2.1A design's Final §3.4 finding); no filtering code was needed to keep it out, and none was added.

---

## Known duplication carried forward (not fixed in this release)

`projects/view.php` now shows Contract Value, Total Invoiced, and Customer Pending/Advance Credit
in **two** places: the pre-existing Health Dashboard KPI row (2.0C) and the new Billing Overview
section (this release). Both read the identical `$financial_summary` fields, so they cannot drift
out of sync with each other, but the visual duplication itself was not asked to be removed in this
release's scope ("add" the new section, not "replace" the old one) and was left as-is, following
the same flag-don't-fix precedent the 2.0C audit set for RC4/RC5. Recommend a future release
consolidate the two once the new section has been used in practice.

## Manual verification note

`php -l` was run against all five edited files — no syntax errors. A live browser check could not
be completed in this session (no dev server was started as part of this change). See
`RELEASE_2_1B_ACCEPTANCE_TEST.md` for the manual test plan to run once the app is reachable.
