# Release 2.1D — Payment Module UX Fix — Dependency Analysis (Phase A)

Audits `Payments::create()`, `Payments::edit()`, `payments/create.php`, `payments/edit.php`, and
`payments/index.php` against the four problems in the request. No file is edited in this phase.

## Problem 1 — Duplicate Invoice Lists

`app/Views/payments/index.php` (lines 92-115) already renders **only** a payment ledger: columns
are `#`, Invoice, Project, Customer, Amount, Date, Method, Actions — an exact match for "Invoice /
Project / Customer / Amount / Date / Method / Actions." It contains no project dropdown, no invoice
table, no billing figures (Advance Applied / Pending / Status) — those only exist on
`payments/create.php` / `edit.php`.

**Finding: Problem 1 is already satisfied. No change needed to `payments/index.php` or
`Payments::index()`.** This was true before this release; nothing in 2.1B/2.1C added invoice
selection to the index page.

## Problem 2/3/4 — Record Payment Workflow

### Project dropdown query (Problem 3)

`Payments::create()` line ~50 and `Payments::edit()` line ~114 (`app/Controllers/Payments.php`):
both already run `SELECT id, name FROM projects WHERE status = 'ACTIVE' ORDER BY name ASC` (added in
Release 2.1C, Bug 2/Issue A). `edit()` additionally appends the current payment's own project if a
Completed project was excluded, so its pre-selection still resolves.

**Finding: Problem 3 is already satisfied by 2.1C.** No controller change needed.

### Invoice query (Problem 2/4)

Both methods load every sale in one query (`$data['sales']`, all projects, carrying `project_id` per
row) — unchanged since 2.1B. This is still the correct approach for Problem 4's instruction
("Client-side filtering is acceptable because invoices are already fetched") — no new route or query
is needed.

**Finding: no controller change needed for the invoice query either.**

### Current JS/markup state (`payments/create.php`, `payments/edit.php`)

From 2.1C:
- Every `.invoice-row` is rendered into `#invoiceTableBody` server-side, then hidden via
  `$('.invoice-row').hide()` on `create.php`'s load (`edit.php` uses `applyProjectFilter()` on load
  instead, which hides all rows when no project is selected — not applicable here since `edit.php`
  always has a project preselected).
- The empty/placeholder state today is a `<tr id="selectProjectMsg">` **inside the same table**,
  i.e. still literally "a table row," not the centered standalone placeholder block ("📄 Select a
  project to view invoices." / "Choose an active project above.") the new spec describes. The table
  element itself (`<table class="invoice-table">`) is present in the DOM and visible at all times;
  only its body rows are toggled.
- Table header wording is "Invoice No / Date / Invoice Total / Advance Applied / Paid Amount /
  Invoice Pending / Status / Action" — the new spec's table shows simplified header text ("Invoice /
  Date / Total / Advance / Paid / Pending / Status / Action"). Underlying data/columns are identical;
  only the header labels differ.
- `#projectFilter` `change` handler (`create.php` ~line 193, `edit.php`'s `applyProjectFilter()`)
  already filters `.invoice-row` by `data-project-id`, toggles a `#noRowsMsg` row — this logic is
  correct and is being preserved, not rewritten.
- `.record-payment-btn` click handler and the hidden `#saleSelect`'s `change` handler
  (`#detTotal`/`#detAdvance`/`#detPaid`/`#detPending`, `#amountInput` max) are byte-for-byte unchanged
  since 2.1B — confirmed no edit needed, per the explicit "Preserve hidden select + existing
  validation JS" rule.

**Finding: the remaining gap is presentation only** — replace the in-table placeholder row with a
real standalone centered placeholder block that fully hides the invoice table (not just its rows)
until a project is chosen, and relabel the table headers to the shorter wording. No filtering logic,
query, or validation changes are required to close this gap.

## Edit Payment Page

Already auto-selects its project (`$currentSale`), already auto-filters on load
(`applyProjectFilter()` called unconditionally after definition), already highlights the current
invoice (`is-selected` class + "Selected" button label), already allows switching invoice (clicking
another row's Record Payment button re-targets `#saleSelect`, unchanged validation still runs in
`update()`).

**Finding: `edit.php`'s workflow already matches Release 2.1D's requirement.** It gets the same
placeholder/header presentation change as `create.php` for visual consistency, and nothing else.

## Files that will change in Phase B

| File | Change |
|---|---|
| `app/Views/payments/create.php` | Replace the in-table `#selectProjectMsg` row with a standalone centered placeholder block (icon + "Select a project to view invoices." + "Choose an active project above."); hide the entire invoice table (not just rows) until a project is selected; relabel table headers to Invoice/Date/Total/Advance/Paid/Pending/Status/Action. |
| `app/Views/payments/edit.php` | Same placeholder/header changes; `applyProjectFilter()` extended to toggle the table-vs-placeholder visibility (it already ran correctly on load — only what it shows/hides changes). |

**Not touched:** `app/Controllers/Payments.php` (already correct per above), `app/Views/payments/index.php`,
any Model, migration, `SaleModel`, `ProjectModel`, `Payments::store()`/`update()`/`delete()`, the hidden
`#saleSelect` `change` handler, `.record-payment-btn` click handler.
