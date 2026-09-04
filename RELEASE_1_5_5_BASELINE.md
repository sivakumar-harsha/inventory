# Release 1.5.5 — Sales Invoice Screen UX Cleanup: Acceptance Baseline

Date: 2026-08-27
Scope: UI/UX refinement only — the Sales Create/Edit screen should read as an invoice entry form, not a financial dashboard. No calculation, API, controller, model, route, or database changes.

## Change 1 — Compact Project Financial Summary

**Removed the "Remaining" metric from display.** The card now shows 4 compact items in one row:

1. **Project Value** — `#pfTotalValue` (unchanged)
2. **This Invoice** — `#pfInvoiceTotal` (unchanged, live)
3. **Billed Progress** — new `#pfBilledAfterAmount` (₹ billed-after-this-invoice) + the existing `#pfProgressAfterLabel` (%), with the existing thin progress bar (`#pfProgressAfterBar`) directly beneath
4. **Status badge** — the existing `#pfWarning` element, restyled as a small pill badge

Not displayed: Remaining Billable Value, Remaining After Invoice, Advance Amount, Payments, Outstanding Balance — none of these were ever fetched separately (they're not part of this card's DOM), so nothing needed hiding beyond the one field below.

### Keeping `#pfRemainingAfter` for JS compatibility

`updateFinancialPreview()` still computes and writes to `#pfRemainingAfter` exactly as before (unchanged calculation) — the element is kept in the DOM as `<span id="pfRemainingAfter" style="display:none">`, present but never shown to users, per the task's compatibility instruction.

## Change 2 — Compact GST Summary

Replaced the 3 stacked `.total-row` blocks with one horizontal flex strip: **Taxable → + → GST → = → Grand Total**, Grand Total visually emphasized (larger, bolder). The three ids `calcTotal()` writes to and reads back (`#taxableAmount`, `#gstTotal`, `#grandTotal`) are unchanged.

## Files Changed

| File | Change |
|---|---|
| `app/Views/sales/create.php` | New compact 4-chip financial summary row + compact GST strip; added scoped `<style>` blocks (sizing/badge/strip presentation only) |
| `app/Views/sales/edit.php` | Identical layout/markup/ids/style changes |
| `public/assets/js/project-financial.js` | Structural selector additions only: (a) added `.text()` writes to the new `#pfBilledAfterAmount` element using the already-computed `billedAfter` value — no new arithmetic; (b) replaced the `.attr('class', 'alert alert-* mt-3')` / long-sentence `.html()` calls on `#pfWarning` with compact `pf-badge pf-badge-{success,warning,danger}` classes and short badge text ("Within Limit" / "Near Project Limit" / "Exceeds Project Value by ₹X"), using a 3-way branch on the **same already-computed `excess` and `progressAfter` values** (`excess > 0.01` → danger, else `progressAfter >= 80` → warning, else → success) — this reuses the exact thresholds `pfProgressColor()` already used for the bar's color, so the badge and bar now agree; no new formulas were introduced and `remainingAfter`/`billedAfter`/`progressAfter`/`excess` are computed identically to before |

**No changes to:** any controller, model, migration, route, or database. `ProjectModel.php`, `Projects.php`, `Sales.php` untouched.

## Acceptance Testing

Tested live against `http://localhost/aandainventory`, authenticated session.

| Check | Result |
|---|---|
| `sales/create` — 4-item compact row (Project Value / This Invoice / Billed Progress / Status) rendered, old `Remaining` chip removed from view | **PASS** |
| `#pfRemainingAfter` still present in DOM but hidden (`style="display:none"`) | **PASS** |
| No leftover references to Remaining Billable Value / Advance Amount / Outstanding Collection / Total Billed text on the Sales screen | **PASS** — zero matches in rendered HTML |
| GST strip (`Taxable | GST | Grand Total`) renders with `#taxableAmount`, `#gstTotal`, `#grandTotal` ids intact | **PASS** |
| `sales/edit/20` — identical compact layout, same ids | **PASS** — no regression |
| Live invoice amount updates | **PASS** — `calcTotal()` (unchanged) still calls `updateFinancialPreview(summary.grandTotal)` on every qty/price/GST change |
| Progress bar updates | **PASS** — `#pfProgressAfterBar` width/color logic (`pfProgressColor()`) untouched |
| Status badge updates | **PASS** — verified against Project 16 (Total Project Value ₹5,00,000, Total Billed ₹0 via `sales/project-financial-summary/16`): a ₹50,000 invoice → 10% → "Within Limit" (green); a ₹4,20,000 invoice → 84% → "Near Project Limit" (amber); a ₹5,20,000 invoice → excess ₹20,000 → "Exceeds Project Value by 20000.00" (red) — all three tiers computed from the unchanged `billedAfter`/`progressAfter`/`excess` values |
| Sale Items section moves higher on the page | **PASS** — financial card height reduced (52px chips + compact header/padding vs. the previous 3-column table layout), so `Sale Items` renders with substantially less scroll |
| No calculation changes | **PASS** — `remainingAfter`, `billedAfter`, `progressAfter`, `excess`, and `pfProgressColor()` thresholds are byte-identical to Release 1.5.4; only added `.text()` writes and restyled the status element's class/copy |

## Regression Check

| Check | Result |
|---|---|
| `ProjectModel.php`, `Projects.php`, `Sales.php`, migrations, routes | untouched |
| `sales/project-financial-summary/{id}` JSON payload | unchanged shape/values |
| Project View (`projects/view/16`) | unchanged — `Remaining Billable Value`, `Outstanding Collection Balance`, `Total Billed`, `Advance Amount` all still shown in full |
| Project Statement (`projects/statement/16`) | unchanged — `Outstanding Collection Balance` still present |

## Summary

| Check | Result |
|---|---|
| Remaining metric removed from Sales screen display | PASS |
| Compact 4-item financial summary row | PASS |
| Compact GST strip (Taxable / GST / Grand Total) | PASS |
| Existing IDs preserved (`#pfRemainingAfter` hidden, not removed) | PASS |
| Live updates, progress bar, status badge all functioning | PASS |
| No calculation changes | PASS |
| No regression in Sales Edit | PASS |
| Project View / Project Statement unchanged | PASS |

**Overall: PASS.** The Sales Create/Edit screen now reads as a compact invoice entry form — Project Value, live invoice total, billed progress, and a one-line status badge — with the full financial detail (advance, outstanding balance, remaining billable value) reserved for Project View and Project Statement.
