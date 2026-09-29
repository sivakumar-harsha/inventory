# Release 1.8B — Baseline (Phase A deliverable)

Scope: UI/UX only. Sales Create/Edit redesigned to visually match Purchase Create/Edit (1.8A).

## Files touched

* `app/Views/sales/create.php` — shared style include, compact `.pr-item-row`/`.pr-totals-strip`
  markup, script trimmed to page globals + shared `sales-items.js` include.
* `app/Views/sales/edit.php` — same treatment; boot logic now calls the same `addItem(item)` as
  Create for every existing row (Phase D fix), instead of the removed page-local
  `buildItemRow`/`addPrefillItem`/`addNewItem`.
* `app/Views/sales/_item_row_style.php` (new) — compact CSS, scoped under `#saleForm` only.
* `assets/js/sales-items.js` (new) — single shared implementation of `addItem` / `removeItem` /
  `bindRowEvents` / `loadRowProducts` / `calcLineTotal` / `calcTotal`, used identically by both
  pages.

## Files NOT touched

* `assets/js/gst-calc.js`
* `assets/js/project-financial.js`
* Any file under `app/Controllers/` or `app/Models/`
* `app/Views/purchases/*` (1.8A — untouched by this release)
* `app/Views/sales/view.php` (not in scope)
* Database / migrations

## Pre-change behavior (for regression comparison)

* **Create**: no initial row; user must click "Add Item" to get a row (this is unchanged —
  Sales' "Add Item" behavior, unlike Purchases, was never auto-triggered on page load, because
  the row's Product list depends on picking a Stock Source first).
* **Edit**: pre-existing rows loaded via AJAX per row (`sales/get-products`) to rebuild the full
  option list and mark the saved product selected, then restore saved qty/price/GST — this AJAX
  hydration flow is preserved unchanged in `loadRowProducts()`.
* Row layout pre-1.8B: Source | Product | Available Qty | Qty | Unit Price | GST% | GST |
  Line Total — one wide Bootstrap-grid row, not compact.
* Footer was already a single horizontal strip (`.pf-gst-strip`) pre-1.8B — restyled to
  `.pr-totals-strip` to match Purchases' visual treatment (sticky, highlighted Grand Total),
  same three IDs.
* Project Financial Summary chips (`#projectFinancialCard`) were already implemented pre-1.8B in
  the compact 4-chip form the spec asks for — left functionally and visually as-is.

## Verified-unchanged calculation entry points (regression anchors)

* `gstCalculateLine(qty, price, gstPercent, gstApplicable)` / `gstSummarize(lines)` — byte-
  identical signatures and call sites to pre-1.8B.
* `updateFinancialPreview(grandTotal)` — called at the end of `calcTotal()`, unchanged.
* Field `name` attributes unchanged: `items[N][stock_source]`, `items[N][product_id]`,
  `items[N][quantity]`, `items[N][unit_price]`, `items[N][gst_applicable]`.
* Footer IDs unchanged: `#taxableAmount`, `#gstTotal`, `#grandTotal`.
* Qty-vs-available-stock validation (`qty > avail` → clamp + alert) — unchanged logic, unchanged
  data source (`data-qty` on the selected product option, itself unchanged from the
  `sales/get-products` AJAX response).
* `stock_source` AJAX-driven product list — unchanged endpoint, params (`source`, `project_id`,
  `exclude_sale_id`), and response field usage (`data-price`, `data-qty`, `data-gst`).

## New in this release (UI-only additions)

* Row 2 "Status Badge" (`General Stock` / `Project Stock`) — a read-only label derived from the
  existing `stock_source` value; sets no new field, computes nothing.
* Row 2 "Project" label — read-only display of the form-level selected project's name when
  `stock_source === 'PROJECT'`, purely informational (the actual project association is still
  the form-level `project_id`, unchanged).

## Root cause fixed (Phase D — mirrors 1.8A)

`sales/edit.php` never called `.select2()` on its rows (neither for prefilled rows on load nor
for rows added later via "Add Item"); it relied entirely on the global one-shot
`$(document).ready` Select2 sweep in `layouts/main.php`, which only catches selects present at
initial `DOMContentLoaded`. `sales/create.php` worked around this for its own rows only, with an
inline `.select2()` call inside its own `addItem()`. The unified `bindRowEvents()` in
`sales-items.js` always explicitly initializes Select2 for every row on both pages, closing this
gap for Sales the same way it was closed for Purchases in 1.8A.
