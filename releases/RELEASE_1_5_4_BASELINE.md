# Release 1.5.4 — Compact Sales Billing Summary UI: Acceptance Baseline

Date: 2026-08-27
Scope: UI refinement only — shrink the Project Financial Summary card on Sales Create/Edit to a compact horizontal strip. No business logic, calculation, route, database, or JS-formula changes.

## Fix

**Files changed:**

| File | Change |
|---|---|
| `app/Views/sales/create.php` | Replaced the 3-row `table-custom` layout with a compact strip: 3 equal-width metric chips (Project / This Invoice / Remaining) in a Bootstrap `row-cols` grid, a 6px progress bar with the percentage inline on the right, and a one-line badge-style status message. Added a small scoped `<style>` block (chip/progress/badge sizing only). |
| `app/Views/sales/edit.php` | Identical layout/markup/ids/style block |
| `public/assets/js/project-financial.js` | **Not modified** — not needed. All six element ids the script reads/writes (`#pfTotalValue`, `#pfInvoiceTotal`, `#pfRemainingAfter`, `#pfProgressAfterBar`, `#pfProgressAfterLabel`, `#pfWarning`) are unchanged in the new markup, so `renderCurrentStatus()` and `updateFinancialPreview()` — including the `remainingAfter` / `progressAfter` / `excess` math and the green/orange/red thresholds — run byte-for-byte as before. Verified via checksum: `public/assets/js/project-financial.js` MD5 is identical to the file shipped in Release 1.5.3. |

**No changes to:** `ProjectModel.php`, `Projects.php`, `Sales.php` (or any other controller/model), migrations, routes, GST logic, Stock Return logic.

## New Layout

- **Metric row** — `<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">`: 3 chips (📁 Project / 🧾 This Invoice / 💰 Remaining), each a flex row (icon + stacked small-caps label + bold value), fixed `height:56px`, light background/border, no table, no separate left/right sections.
- **Progress row** — single flex row: a 6px, rounded, `overflow:hidden` track holding the existing `#pfProgressAfterBar` (JS still just sets `width`/`background-color` on it), with `#pfProgressAfterLabel` (the percentage JS already writes) positioned to its right via `text-align:right`.
- **Status line** — `#pfWarning` kept as the same Bootstrap `.alert` element JS toggles (`alert alert-success/info/danger`), but scoped CSS (`#projectFinancialCard .alert`) shrinks it to `display:inline-flex`, small padding/font-size, and no forced full-width block — so it renders as a single-line badge instead of a large alert box, without touching the JS that sets its class/text.
- **Responsiveness** — pure Bootstrap 5 grid: `row-cols-1` (mobile: stacked), `row-cols-md-2` (tablet: 2 + 1), `row-cols-lg-3` (desktop: 3 in one row) — exactly the required breakpoints, no custom JS/media-query logic needed.

### Why the JS didn't need to change

`renderCurrentStatus()` writes to `#pfTotalValue` only; `updateFinancialPreview()` writes to `#pfInvoiceTotal`, `#pfRemainingAfter`, sets `.css({width, background-color})` on `#pfProgressAfterBar`, `.text()` on `#pfProgressAfterLabel`, and sets `.attr('class', ...)`/`.html(...)` on `#pfWarning`. None of these selectors or the values they compute changed — only the surrounding HTML/CSS that presents them.

## Acceptance Testing

Tested live against `http://localhost/aandainventory`, authenticated session.

| Check | Result |
|---|---|
| `sales/create` — old table markup (`Total Project Value` row label, `Advance Amount`, `Total Billed`, `Outstanding Collection`, old "Billing Progress After Invoice" text) fully gone | **PASS** — zero matches in rendered HTML |
| New compact grid (`row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2`, 3 `pf-chip` blocks, `pf-progress-track`) present | **PASS** |
| All 6 ids used by `project-financial.js` present and unchanged: `pfTotalValue`, `pfInvoiceTotal`, `pfRemainingAfter`, `pfProgressAfterBar`, `pfProgressAfterLabel`, `pfWarning` | **PASS** — confirmed on both `sales/create` and `sales/edit/20` |
| `project-financial.js` byte-identical to Release 1.5.3 (MD5 checksum match, `public/assets` and root mirror both match) | **PASS** |
| Sale Items section appears immediately after the compact summary card in DOM order | **PASS** — verified `projectFinancialCard` block precedes the `Sale Items` header in the rendered HTML with no other block in between |
| Live invoice updates (`calcTotal()` → `updateFinancialPreview()`) still fire on qty/price/GST change — unchanged call sites in both views | **PASS** — `calcTotal()` function body untouched in both views' inline `<script>` blocks |
| Progress bar / warning color logic unchanged (`pfProgressColor()`, green/orange/red thresholds, `excess` calc) | **PASS** — `project-financial.js` untouched |

## Regression Check

| Check | Result |
|---|---|
| `ProjectModel.php`, `Projects.php`, `Sales.php`, migrations, routes | untouched |
| `sales/project-financial-summary/{id}` JSON payload | unchanged shape/values |
| Project View / Project Statement (full detail table) | untouched — out of scope for this release, unaffected |

## Summary

| Check | Result |
|---|---|
| Compact 3-chip metric strip replaces table layout | PASS |
| 6px progress bar with inline right-aligned percentage | PASS |
| One-line badge-style status message | PASS |
| Responsive breakpoints (3 / 2+1 / stacked) | PASS |
| Element IDs preserved — zero JS changes needed | PASS |
| Live updates & calculations unchanged | PASS |
| Sale Items starts immediately below the compact card | PASS |

**Overall: PASS.** The Project Financial Summary on Sales Create/Edit is now a compact horizontal strip; all underlying calculations, element ids, and JS logic are unchanged — verified by checksum.
