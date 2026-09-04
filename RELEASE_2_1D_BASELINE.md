# Release 2.1D — Payment Module UX Fix — Baseline

Implements the four problems in the request against `RELEASE_2_1D_DEPENDENCY_ANALYSIS.md`. Confirmed
before any file was edited: **no schema, migration, payment calculation, FIFO advance allocation,
`SaleModel`, `ProjectModel`, or validation-logic change was made.** This release is UI/UX workflow
only, and touches only two view files.

## Files touched

| File | What changed |
|---|---|
| `app/Views/payments/create.php` | Replaced the in-table "Select a project…" row with a standalone centered placeholder block (icon + title + subtext) that fully replaces the invoice table (not just its rows) until a project is chosen. Relabeled table headers to the shorter wording (Invoice/Date/Total/Advance/Paid/Pending/Status/Action). `#projectFilter`'s `change` handler now toggles the table wrapper (`#invoiceTableWrap`) against the placeholder (`#invoicePlaceholder`), in addition to its existing row-level filtering. |
| `app/Views/payments/edit.php` | Same placeholder/header changes. `applyProjectFilter()` (already run on load from 2.1C) now also toggles the table wrapper vs. placeholder, so the edit screen shows the invoice table immediately (never the placeholder) since it always opens with a project preselected. |

**Not touched:** `app/Controllers/Payments.php`, `app/Views/payments/index.php`, any Model, migration,
`SaleModel`, `ProjectModel`, `Payments::store()`/`update()`/`delete()`, the hidden `#saleSelect`
`change` handler, `.record-payment-btn` click handler.

---

## Problem 1 — Duplicate Invoice Lists

**Already satisfied, confirmed in Phase A — no change made.** `payments/index.php` was already a
pure payment ledger (Invoice / Project / Customer / Amount / Date / Method / Actions), with no
invoice-selection or billing UI. Nothing needed to change here.

## Problem 2 — Invoice List Must Be Empty Initially

The invoice table (`#invoiceTableWrap`, the whole `<table>` element, not just its rows) now starts
`display:none` in both `create.php` and `edit.php`'s markup. In its place, a standalone
`#invoicePlaceholder` block is shown by default, reading:

> 📄 Select a project to view invoices.
> Choose an active project above.

This replaces the prior release's in-table `<tr id="selectProjectMsg">` row, which — while
functionally empty — was still literally a row inside a rendered `<table>`. Now nothing table-shaped
renders until a project is picked, matching "No invoice table. No cards. No hidden rows visible."

`create.php`: `#projectFilter`'s `change` handler hides the placeholder and reveals
`#invoiceTableWrap` when a project is chosen, and reverses that when the blank option is re-selected.
`edit.php`: the same swap happens inside `applyProjectFilter()`, called once on page load against the
pre-selected project so the table (never the placeholder) is what the edit screen actually shows.

## Problem 3 — Project Filter (Active projects only)

**Already satisfied by Release 2.1C** (`Payments::create()`/`edit()`'s project query:
`WHERE status = 'ACTIVE' ORDER BY name ASC`). Re-verified in Phase A; no controller change was needed
in this release.

## Problem 4 — Invoice Table Filtering

**Filtering logic already satisfied by Release 2.1C** (client-side `data-project-id` match against
`#projectFilter`'s value, no page reload, invoices already fetched in one query). This release only
changes what surrounds that filter — the placeholder swap above and the header relabel:

| Old header | New header |
|---|---|
| Invoice No | Invoice |
| Invoice Total | Total |
| Advance Applied | Advance |
| Paid Amount | Paid |
| Invoice Pending | Pending |

Status badge wording (Invoice Paid / Partial Payment / Payment Pending) and the Record Payment
button's enabled/disabled rule (`Pending > 0.004`) are unchanged — the columns' underlying data and
`data-*` attributes on the hidden `#saleSelect` options were not touched.

## Edit Payment Page

Confirmed unchanged behavior: project auto-selected, invoice table auto-filtered on load (via
`applyProjectFilter()`, already present from 2.1C, now also revealing the table instead of the
placeholder), current invoice highlighted (`is-selected` row, "Selected" button label), and switching
to a different invoice still works exactly as before (`.record-payment-btn` → `#saleSelect` →
`change` → same detail-table/validation JS). `Payments::update()` was not opened for editing.

## Manual verification note

`php -l` was run against both edited files — no syntax errors:
`app/Views/payments/create.php`, `app/Views/payments/edit.php`. A live browser check could not be
completed in this session (no dev server was started as part of this change). See
`RELEASE_2_1D_ACCEPTANCE_TEST.md` for the manual test plan to run once the app is reachable.
