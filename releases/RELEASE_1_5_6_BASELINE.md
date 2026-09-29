# Release 1.5.6 — Replace "Billed Progress" with "Remaining Project Balance": Acceptance Baseline

Date: 2026-08-27
Scope: UI refinement only — the Sales Create/Edit financial summary chip previously labeled "Billed Progress" is replaced with "Remaining Project Balance". No business logic, `ProjectModel` calculations, controller, route, or database changes.

## Change 1 — Metric Chips

The card still shows 4 compact chips in one row:

1. **Project Value** — `#pfTotalValue` (unchanged)
2. **This Invoice** — `#pfInvoiceTotal` (unchanged, live)
3. **Remaining Balance** (new, replaces "Billed Progress") — new `#pfRemainingBalance`, computed as `totalProjectValue - currentInvoiceTotal` (advance amount is **not** subtracted, per the instruction). Styled with a green wallet icon (`bi-wallet2`), uppercase label "REMAINING BALANCE", and a large bold green value — visually the most prominent chip in the row.
4. **Status badge** — `#pfWarning`, unchanged green/orange/red badge logic.

Removed from the chip: the billed-after amount and the progress percentage text (`#pfBilledAfterAmount` is no longer displayed — kept as a hidden `<span>` for compatibility since nothing else on the page still needs it visible).

## Change 2 — Progress Bar

The thin progress bar (`#pfProgressAfterBar`) is kept, now placed under the Remaining Balance chip's value with `#pfProgressAfterLabel` (the percentage) right-aligned beside it via a flex row (`.pf-progress-row`).

**Formula changed for the bar/label only**, per explicit instruction: `invoicePct = (currentInvoiceTotal / totalProjectValue) * 100` — i.e. *Invoice Progress Against Project Value*, not the previous cumulative "billed-after-this-invoice" percentage. The bar's color still uses the existing `pfProgressColor()` thresholds (green &lt;80%, amber 80–99.99%, red ≥100%), now evaluated against this new percentage.

The status badge (`#pfWarning`) tiering is **unchanged** — it still uses the original `billedAfter`/`progressAfter`/`excess` values (cumulative total billed + this invoice, vs. total project value), so "Exceeds Project Value" / "Near Project Limit" / "Within Limit" continue to reflect the project's overall billing position, independent of the bar's now invoice-only percentage. This was a deliberate choice to satisfy "Keep the existing green/orange/red badge" (i.e., don't change its logic) while satisfying the bar's new, different formula.

## Change 3 — Remaining Balance Styling

`.pf-chip-remaining` gets a light green background/border (`#f0fdf4` / `#bbf7d0`); `.pf-icon-remaining` (`bi-wallet2`) is green (`#16a34a`); `.pf-chip-value-remaining` is large (1.15rem), bold (800 weight), green (`#15803d`) — visually more prominent than the percentage label beside the bar.

## Allowed Files Changed

| File | Change |
|---|---|
| `app/Views/sales/create.php` | Replaced the "Billed Progress" chip markup with the "Remaining Balance" chip (new `#pfRemainingBalance`, wallet icon, progress row); `#pfBilledAfterAmount` kept as hidden span; added scoped CSS for the new chip styling |
| `app/Views/sales/edit.php` | Identical markup/id/style changes |
| `public/assets/js/project-financial.js` | Added `remainingBalance = totalProjectValue - currentInvoiceTotal` and a `.text()` write to `#pfRemainingBalance` (new display value, reusing already-available `totalProjectValue`/`currentInvoiceTotal`); introduced `invoicePct = (currentInvoiceTotal / totalProjectValue) * 100` and switched the progress bar width/color and `#pfProgressAfterLabel` text to use it instead of the old `progressAfter` (billed-after-based); the badge logic block (`excess`, `progressAfter >= 80`, `pfProgressColor()` thresholds) is untouched — same variables, same branches, same copy |

**No changes to:** `ProjectModel.php`, any controller, migrations, routes, or the `sales/project-financial-summary/{id}` endpoint/payload.

## Acceptance Testing

Tested live against `http://localhost/aandainventory`, authenticated session, using Project 16 ("Medical Project": `total_project_value` = ₹5,00,000, `total_billed` = ₹0, confirmed via `sales/project-financial-summary/16`).

| Check | Result |
|---|---|
| `sales/create` — 4-chip row renders with new "Remaining Balance" chip, wallet icon, green large value | **PASS** |
| Old "Billed Progress" label and layout fully removed from rendered HTML | **PASS** — zero matches |
| `#pfRemainingBalance` id present and populated by JS | **PASS** |
| `#pfBilledAfterAmount` still present in DOM but hidden (kept for compatibility) | **PASS** |
| `#pfRemainingAfter` still present in DOM but hidden (unchanged compatibility id from 1.5.5) | **PASS** |
| Math: Project Value ₹500,000, Invoice ₹14,000 → Remaining Balance = ₹500,000 − ₹14,000 = **₹486,000** | **PASS** |
| Math: Progress bar = 14,000 / 500,000 × 100 = **2.8%** | **PASS** |
| Advance amount (₹100,000 on Project 16) is **not** subtracted from Remaining Balance | **PASS** — confirmed formula uses only `total_project_value` and the live invoice total, never `advance_amount` or `remaining_billable_value` |
| Status badge still evaluates Green/Orange/Red thresholds correctly (0% → "Within Limit" green; would show "Near Project Limit" amber at ≥80% cumulative billed; "Exceeds Project Value by ₹X" red if cumulative billed exceeds project value) | **PASS** — badge logic untouched, verified against unchanged threshold constants |
| `sales/edit/20` — identical layout, ids, and formulas | **PASS** — no regression |
| Project View / Project Statement (full detail: Advance Amount, Outstanding Collection Balance, Remaining Billable Value, Total Billed) | **PASS** — unchanged, still shown in full |

## Regression Check

| Check | Result |
|---|---|
| `ProjectModel.php`, `Projects.php`, `Sales.php`, migrations, routes | untouched |
| `sales/project-financial-summary/{id}` JSON payload | unchanged shape/values |
| GST strip (`#taxableAmount`, `#gstTotal`, `#grandTotal`) and `calcTotal()` | untouched, live updates still call `updateFinancialPreview(grandTotal)` |
| Project View (`projects/view/16`) | unchanged |
| Project Statement (`projects/statement/16`) | unchanged |

## Summary

| Check | Result |
|---|---|
| "Billed Progress" chip replaced with "Remaining Project Balance" (Project Value − Invoice, no advance subtraction) | PASS |
| Progress bar now shows Invoice / Project Value % beside the bar, billed amount removed from chip | PASS |
| Remaining Balance styled as the visually dominant green metric | PASS |
| Status badge thresholds unchanged and still functioning | PASS |
| Hidden compatibility ids preserved (`#pfRemainingAfter`, `#pfBilledAfterAmount`) | PASS |
| No backend/business-logic changes | PASS |
| No regression in Sales Edit | PASS |
| Project View / Project Statement unchanged | PASS |

**Overall: PASS.** The Sales Create/Edit screen now presents Remaining Project Balance (against full contract value) as its primary at-a-glance metric, with invoice-vs-project-value progress shown separately beside the bar — matching an invoice-entry mental model rather than a cumulative billing dashboard.
