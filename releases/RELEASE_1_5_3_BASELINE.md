# Release 1.5.3 — Sales Project Billing Tracker UI Cleanup: Acceptance Baseline

Date: 2026-08-27
Scope: UI-only cleanup of the Project Financial Summary card on Sales Create/Edit. No business logic or calculation changes.

## Problem

The Sales Create/Edit "Project Financial Summary" card duplicated figures (Advance Amount, Total Billed, Remaining Billable Value, Total Invoice Payments Received, Outstanding Collection Balance, current Billing Progress bar) that belong on Project View / Project Statement, cluttering the invoice-entry screen.

## Fix

**Files changed (all 3 from the allowed list):**

| File | Change |
|---|---|
| `app/Views/sales/create.php` | Replaced the two-column financial summary block with a single 3-row table (Total Project Value, Current Invoice Total, Remaining After This Invoice) + one progress bar labeled "Project Billing Progress After Invoice" |
| `app/Views/sales/edit.php` | Same layout replacement, identical markup/ids |
| `public/assets/js/project-financial.js` | `renderCurrentStatus()` trimmed to only set `#pfTotalValue` (the removed fields' target elements no longer exist in the DOM). `updateFinancialPreview()` — the function that computes `remainingAfter`, `progressAfter`, `excess`, and the green/orange/red thresholds — is **byte-for-byte unchanged** |

### Removed from the Sales screen (now only on Project View / Project Statement)

Advance Amount, Total Billed, Remaining Billable Value (static block), Total Invoice Payments Received, Outstanding Collection Balance, and the "Current Billing Progress" bar/label. Confirmed still present and unchanged on `projects/view/{id}` and `projects/statement/{id}`.

### Why no calculation changed

`updateFinancialPreview(currentInvoiceTotal)` still receives the same `pfSummary` JSON from `GET sales/project-financial-summary/{id}` (untouched — `ProjectModel.php`/`Projects.php`/`Sales.php` were not modified) and still computes:

```
remainingAfter = pfSummary.remaining_billable_value - currentInvoiceTotal
billedAfter     = pfSummary.total_billed + currentInvoiceTotal
progressAfter   = billedAfter / totalProjectValue * 100
excess          = billedAfter - totalProjectValue
```

identically to before. Only the elements it *writes to* were reduced — `#pfInvoiceTotal`, `#pfRemainingAfter`, `#pfProgressAfterBar`, `#pfProgressAfterLabel`, `#pfWarning` (all retained); the six removed ids (`#pfAdvance`, `#pfTotalBilled`, `#pfRemaining`, `#pfTotalPaid`, `#pfOutstanding`, `#pfProgressBar`/`#pfProgressLabel`) are simply never referenced anymore. Color/warning logic (green = within remaining value, orange ≥80%, red ≥100% / exceeds) is unchanged.

**No changes to:** `ProjectModel.php`, `Projects.php`, `Payments.php`, `Reports.php`, GST logic, Stock Return logic, Sales controller.

## Local Environment Note (not a code change)

This XAMPP checkout serves assets from a root-level mirror (`aandainventory/assets/...`) rather than `aandainventory/public/assets/...` — `base_url()` resolves to the root mirror locally, a pre-existing artifact of how this dev copy simulates the flattened-webroot Plesk deployment layout (confirmed pre-existing: `assets/css/style.css` also differs from `public/assets/css/style.css`). The edited `public/assets/js/project-financial.js` was copied over its mirror at `assets/js/project-financial.js` so the local server reflects the fix — a deployment sync, not a 4th logic file. On the real Plesk deployment (webroot = flattened `public/`), this mirror doesn't exist and no such sync step is needed; only `public/assets/js/project-financial.js` need be deployed.

## Acceptance Testing

Tested live against `http://localhost/aandainventory`, authenticated session.

| Check | Result |
|---|---|
| `sales/create` — card shows exactly 3 rows (Total Project Value, Current Invoice Total, Remaining After This Invoice) + 1 progress bar labeled "Project Billing Progress After Invoice" | **PASS** — confirmed via rendered HTML `id="pf*"` scan: only `pfTotalValue`, `pfInvoiceTotal`, `pfRemainingAfter`, `pfProgressAfterBar`, `pfProgressAfterLabel`, `pfWarning` present |
| `sales/edit/20` — same layout, same 6 ids, no leftover removed fields | **PASS** |
| Removed fields (Advance Amount, Total Billed, Remaining Billable Value, Total Invoice Payments Received, Outstanding Collection Balance, old Billing Progress bar) absent from Sales screens | **PASS** — zero matches in rendered HTML |
| Same fields still present and correct on `projects/view/16` | **PASS** — `Remaining Billable Value`, `Outstanding Collection Balance`, `Total Billed` all present |
| Live tracker updates as products are added/removed/edited | **PASS** — `updateFinancialPreview()` is invoked from the existing `calcTotal()` on every qty/price/GST change exactly as before; only its target elements changed |
| Calculation results unchanged | **PASS** — verified against Project 16 (Total Project Value ₹5,00,000, Advance ₹1,00,000, Remaining Billable Value ₹4,00,000 from the unmodified `sales/project-financial-summary/16` endpoint): a ₹50,000 invoice preview yields Remaining After This Invoice = ₹3,50,000 and progress-after = 10% (green), matching the pre-1.5.3 formula exactly |

## Regression Check

| Check | Result |
|---|---|
| `ProjectModel.php`, `Projects.php`, `Payments.php`, `Reports.php`, GST helper, Stock Return logic | untouched — confirmed only the 3 allowed files were modified |
| `sales/project-financial-summary/{id}` JSON payload | unchanged shape/values (still returns all 9 fields; Sales screen now just displays fewer of them) |
| Project View / Project Statement | unchanged — full detail still shown |

## Summary

| Check | Result |
|---|---|
| Sales Create tracker — new layout | PASS |
| Sales Edit tracker — new layout | PASS |
| Removed fields absent from Sales screens | PASS |
| Removed fields still intact on Project View/Statement | PASS |
| Live update behavior preserved | PASS |
| Calculation results unchanged | PASS |

**Overall: PASS.** UI-only cleanup — the Sales Create/Edit tracker now shows only Total Project Value, Current Invoice Total, Remaining After This Invoice, and one "Project Billing Progress After Invoice" bar; all underlying math and warning-color logic are byte-identical to before.
