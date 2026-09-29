# Release 1.8B — Dependency Analysis (Phase A, read-only)

Scope reminder: UI/UX only. Purchase Create/Edit (Release 1.8A) is the design standard.
No GST, project-financial, advance-application, stock-source, controller/model/DB changes.

## Files compared

* `app/Views/sales/create.php`
* `app/Views/sales/edit.php`
* `app/Views/purchases/create.php` / `app/Views/purchases/edit.php` (1.8A reference)
* `assets/js/gst-calc.js` (unchanged dependency)
* `assets/js/project-financial.js` (unchanged dependency)

## 1. Dynamic row template

**Purchases (post-1.8A):** one shared `addItem(prefill)` in `assets/js/purchase-items.js`, used
identically by both pages. Product list is a page-supplied static `products` array — no AJAX per
row.

**Sales (pre-1.8B):** row template is **duplicated and diverges** between the two pages:

* `create.php` builds the row inline via `addItem()` (no prefill support needed — always empty).
* `edit.php` builds the row via a separate `buildItemRow(num, prefill)` function, with parallel
  `addNewItem()` (empty row) and `addPrefillItem(prefill, num)` (existing row, AJAX-driven).

Field layout also differs slightly: Product column is `col-md-2` on create, `col-md-3` on edit.
Both use the same field set: Source, Product, Available Qty, Qty, Unit Price, GST %, GST
applicable, Line Total — **no Project Qty / General Qty split** (that concept is Purchase-only;
Sales instead assigns one `stock_source` — GENERAL or PROJECT — per line, with no per-line mixed
allocation in the current data model).

## 2. Add Item JS

* `create.php`: `addItem()` appends the row, then **immediately calls
  `$('#item-' + itemCount + ' select').select2(...)`** inline, then `bindRowEvents(itemCount)`.
* `edit.php`: `addNewItem()` appends via `buildItemRow(num, null)` then calls
  `bindRowEvents(num, null, null)` — **no `.select2()` call anywhere in edit.php.** Newly-added
  rows on Edit rely entirely on the one-shot global `$(document).ready` select2 sweep in
  `app/Views/layouts/main.php`, which only catches selects present at initial `DOMContentLoaded`.
  This is the **same latent bug class as the pre-1.8A Purchase Edit page**: a row added later via
  "Add Item" on Sales Edit does not reliably get styled Select2 initialization.

## 3. Remove Item JS

Identical trivial logic on both pages (`$('#item-' + num).remove(); calcTotal();`). No divergence,
no change needed beyond the compact-row wrapper.

## 4. Product auto-fill JS

Structurally different from Purchases because Sales product options are **source-dependent** and
fetched via AJAX (`sales/get-products`), not a static preloaded array:

* Selecting **Stock Source** on a row triggers `$.get(ajaxBase, {source, project_id?})` which
  repopulates that row's `.product-select` options (with `data-price`, `data-qty`, `data-gst`).
* Selecting **Product** then fills Unit Price / Available Qty / GST % from the chosen option's
  data attributes (`row.find('.product-select').on('change', ...)`), same pattern as Purchases'
  product auto-fill but sourced from AJAX-populated `data-*` instead of a static `products` array.
* `edit.php`'s `addPrefillItem()` additionally does an AJAX fetch on load to rebuild the full
  option list for the row's saved `stock_source`, re-select the saved `product_id`, and restore
  the saved qty/price afterward (AJAX repopulation would otherwise reset them) — `create.php` has
  no equivalent because it never has prefilled rows.

**Consequence for the shared implementation:** unlike `purchase-items.js` (which assumes a page
global `products` array), the shared `sales-items.js` must call `$.get(ajaxBase, ...)` itself —
both for source-change repopulation and for prefill-row hydration — and both pages must declare
`ajaxBase` and `excludeSaleId` before including it (mirroring how both Purchase pages declare
`products` before including `purchase-items.js`).

## 5. GST JS

Both pages call the same unchanged `gstCalculateLine()` / `gstSummarize()` from
`assets/js/gst-calc.js`, identically to Purchases. No divergence. **Not modified.**

## 6. Project Qty / General Qty JS

**Not applicable to Sales** — this concept does not exist in the Sales item model. The nearest
per-row concept is the `stock_source` (`GENERAL` / `PROJECT`) select, which is a single-value
field, not a Purchases-style split calculation. Release 1.8B's Phase B spec asks for a compact
"Status Badge" showing `General Stock` / `Project Stock` (and reserves `Mixed Source` for future
logic the current single-source-per-row model does not support) — this is a **read-only display**
derived from the existing `stock_source` value, not a new calculation.

## 7. Total footer calculation

Both pages already compute the footer via the same `calcTotal()` pattern as Purchases
(`gstSummarize()` → `#taxableAmount` / `#gstTotal` / `#grandTotal`), **plus** a Sales-only call to
`updateFinancialPreview(summary.grandTotal)` (from `assets/js/project-financial.js`) that drives
the Project Financial Summary chips. The footer markup itself (`.pf-gst-strip`) is **already** a
single horizontal "Taxable + GST = Grand Total" strip — Sales got ahead of Purchases here. Release
1.8B's Phase D just needs to restyle this strip to visually match Purchases' `.pr-totals-strip`
(sticky, highlighted Grand Total chip) without touching the three IDs or the `updateFinancialPreview`
call.

## 8. Project Financial Summary (Phase C)

Already implemented as four `.pf-chip` cards (Project Value, This Invoice, Remaining Balance +
progress bar, Status) inside `#projectFinancialCard.pf-compact`, driven entirely by
`assets/js/project-financial.js`. This already matches the Release 1.8B Phase C spec structurally.
**Not modified** beyond what's needed to keep it visually consistent with the new compact item
rows — no ID or calculation changes.

## Why Sales Edit has the same "Add Item" styling risk as pre-1.8A Purchase Edit

Same root cause as documented in `RELEASE_1_8A_DEPENDENCY_ANALYSIS.md`: the global one-shot
`$(document).ready` Select2 sweep in `layouts/main.php` only initializes selects that exist at
`DOMContentLoaded`. `sales/create.php` works around this today by explicitly calling `.select2()`
inside its own `addItem()`. `sales/edit.php` never does — for both its prefill rows on page load
*and* new rows added later via "Add Item". Unifying both pages onto one shared `bindRowEvents()`
that always explicitly initializes Select2 (the same fix applied to Purchases in 1.8A) resolves
this for Sales as well.

## Scope guard — shared base CSS

`.item-row` / `.total-row` / `.row-num` / `.remove-item` in `assets/css/style.css` are shared
between Sales and Purchases. Release 1.8A already scoped all its compact overrides under
`#purchaseForm` for exactly this reason. Release 1.8B follows the same pattern: all new compact
styling for Sales lives in a new `app/Views/sales/_item_row_style.php`, scoped under `#saleForm`
(Sales' own form ID) — Purchases pages and their `#purchaseForm`-scoped styling are provably
unaffected by this release.

## Not touched by this release

* `assets/js/gst-calc.js`
* `assets/js/project-financial.js`
* `app/Controllers/`, `app/Models/` (any)
* `app/Views/purchases/*` (already released under 1.8A)
* Database / migrations
