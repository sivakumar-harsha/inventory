# Phase 4 Baseline — Live Project Billing Tracker

Status: Designed (4A), implemented (4B), acceptance-tested (4C — this file).
Scope: Adds a live "Project Financial Summary" tracker to Sales Create/Edit. No other workflow's behavior was altered.

---

## 1. Files Modified

| File | Type | Change |
|---|---|---|
| `app/Models/ProjectModel.php` | Modified | `getFinancialSummary()` gained an optional `?int $excludeSaleId = null` parameter — excludes one sale from `total_billed`/`total_paid` (edit mode) |
| `app/Controllers/Sales.php` | Modified | Added `projectFinancialSummary($projectId)` — thin JSON wrapper around `ProjectModel::getFinancialSummary()` |
| `app/Config/Routes.php` | Modified | Added 1 route: `GET sales/project-financial-summary/(:num)` |
| `app/Views/sales/create.php` | Modified | Added the Project Financial Summary card; loads `project-financial.js`; hooks `#projectSelect` change and `calcTotal()` into the tracker |
| `app/Views/sales/edit.php` | Modified | Same card + hooks, plus loads the summary on page-ready (project pre-selected) using the existing `excludeSaleId` variable |
| `public/assets/js/project-financial.js` | New | Client-side tracker: fetches the summary, renders Current Project Status, computes the After-This-Invoice preview from `calcTotal()`'s own output |
| `assets/js/project-financial.js` | New (deployment mirror) | Copy of the above — see §9 |
| `assets/js/gst-calc.js` | New (deployment mirror) | Copy of the pre-existing `public/assets/js/gst-calc.js` — see §9 |

No other controller, model, view, route, or migration file was touched. No database migration was created (per Phase 4B scope).

---

## 2. New AJAX Endpoint

```
GET sales/project-financial-summary/{projectId}[?exclude_sale_id={id}]
```

- Route: `app/Config/Routes.php`, gated by `['filter' => 'auth']`, same as every other protected route.
- Controller: `Sales::projectFinancialSummary($projectId)` — reads optional `exclude_sale_id` from the query string, calls `(new ProjectModel())->getFinancialSummary((int)$projectId, $excludeSaleId)`, returns it as JSON.
- No new SQL was written for the response body — the endpoint is a pass-through to the existing (extended) model method also used by `Projects::view()`.
- Unknown/non-existent `projectId` returns `[]` (matches `getFinancialSummary()`'s existing not-found behavior; verified in 4C).

---

## 3. `excludeSaleId` — Edit-Mode Double-Counting Fix

`ProjectModel::getFinancialSummary()`'s two aggregate queries (`total_billed`, `total_paid`) now accept an optional excluded sale ID:

```php
public function getFinancialSummary(int $projectId, ?int $excludeSaleId = null): array
```

When editing sale #20, the card must show the project's figures *as if sale #20 didn't exist yet*, then let the live invoice total (from the fields currently being edited) represent its contribution — otherwise sale #20's already-saved total would be counted twice (once inside `total_billed`, once again as "current invoice total"). `sales/edit.php` passes its existing `excludeSaleId` variable (already used by the pre-existing `get-products` AJAX call) into `initProjectFinancialTracker()`, which appends it to every summary request as `exclude_sale_id`.

Verified live in 4C against a real sale (project 14, sale #20, ₹6.16): with `exclude_sale_id=20`, `total_billed` reverted from `1186.16` to the pre-sale `1180.00`; without it, the full `1186.16` was returned.

---

## 4. Card Layout

One card, "Project Financial Summary", inserted between the "Sale Details" and "Sale Items" cards in both `create.php` and `edit.php`. Hidden (`display:none`) until a project is selected. Two columns, per the approved 4A design:

- **Current Project Status** (static per project-load): Total Project Value, Advance Amount, Total Billed, Remaining Billable Value, Total Invoice Payments Received, Outstanding Collection Balance, Billing Progress bar + %.
- **After This Invoice Preview** (live, recomputed on every item change): Current Invoice Total, Remaining After This Invoice, Billing Progress After Invoice bar + %, and the warning banner.

---

## 5. Calculation Formulas

```
currentInvoiceTotal          = grandTotal                      // taken directly from calcTotal()'s own summary.grandTotal
remainingAfterInvoice        = remaining_billable_value - currentInvoiceTotal
billedAfter                  = total_billed + currentInvoiceTotal
billingProgressAfterInvoice  = total_project_value > 0 ? (billedAfter / total_project_value) * 100 : 0
excess                       = billedAfter - total_project_value
```

`total_billed` / `remaining_billable_value` above are the endpoint's response values — already exclude-adjusted in edit mode, unadjusted in create mode — so this one formula set is correct in both modes without branching in the JS.

---

## 6. Live Update Wiring (reuses `calcTotal()` output, no duplicate math)

`calcTotal()` in both `create.php` and `edit.php` already computed `summary.grandTotal` before Phase 4B (via `gstSummarize()` in `gst-calc.js`). The only addition is one line at the end of each:

```js
updateFinancialPreview(summary.grandTotal);
```

`project-financial.js` never recomputes the invoice total independently — it only consumes the value `calcTotal()` already produced, per requirement 6.

Project selection wiring (additive `.on('change')` bindings, do not replace any existing handler):
- `create.php`: `$('#projectSelect').on('change', ...)` → `loadProjectFinancialSummary(projectId)`.
- `edit.php`: same binding, plus one call on `$(document).ready()` since the project is pre-selected on page load.

---

## 7. Billing Progress Color Rules

| Range | Color | Applies to |
|---|---|---|
| < 80% | Green `#22c55e` | Both the Current Status bar and the After-Invoice bar |
| 80% – 99.99% | Amber `#f59e0b` | Same |
| ≥ 100% | Red `#dc3545` | Same |

Verified via direct logic testing against the shipped `project-financial.js` (Node `vm` harness, jQuery stubbed): 70% → green, 85% → amber, 110% → red.

---

## 8. Three Warning Levels (soft only — never blocks Save)

| Level | Condition (on `billedAfter` vs `total_project_value`) | Style | Message |
|---|---|---|---|
| Within limit | `excess < -0.01` | `alert-success` | "Within remaining billable value." |
| Fully billed | `-0.01 ≤ excess ≤ 0.01` | `alert-info` | "This invoice will fully bill the contract value." |
| Exceeds contract value | `excess > 0.01` | `alert-danger` | "Exceeds contract value by {excess}. Saving is still allowed." |

If `total_project_value <= 0` (not yet set on the project), the warning is suppressed entirely and the After-Invoice progress shows "N/A (project value not set)" — avoids a false-positive warning on every invoice for untracked projects, per the 4A design note.

All three branches, plus the untracked-project case, were exercised directly against the real shipped JS file in 4C (not reimplemented for the test) and returned the correct class/message/percentage in every case.

No server-side validation changes were made anywhere — `Sales::store()` and `Sales::update()` are byte-for-byte unchanged from before Phase 4B; over-limit invoices still save exactly as they did before this feature existed.

---

## 9. Deployment Finding: Static JS Delivery Path (fixed, flagged to user, approved)

Acceptance testing discovered that this live deployment serves static assets only from a **top-level `assets/` folder** (containing `css/` and `images/`), not from CodeIgniter's conventional `public/assets/`. The `.htaccess` at the project root serves any file that exists on disk directly; anything else falls through to `index.php` and 404s as an unmatched CI4 route.

Consequence: `http://localhost/aandainventory/assets/js/gst-calc.js` — the Phase 2 GST calculator script, referenced by both `sales/create.php` and `sales/edit.php` since Phase 2 — **404'd**, meaning the client-side GST live-calc has apparently never actually loaded in a real browser in this deployment. The new `project-financial.js` would have hit the identical gap.

This was flagged to the user mid-session (outside the Phase 4B "Files Allowed" list, since it required touching the deployment layout rather than a PHP/view/route file). With explicit approval, both `public/assets/js/project-financial.js` and `public/assets/js/gst-calc.js` were mirrored (byte-identical copy, verified via `diff`) to `assets/js/project-financial.js` and `assets/js/gst-calc.js`. Both now return HTTP 200 at their real served URLs. No `public/` file was altered — only new copies were added under the top-level `assets/js/` folder, matching the existing `assets/css/`, `assets/images/` pattern.

This is a pre-existing Phase 2 deployment gap, not something introduced by Phase 4B — documented here because it was discovered and remediated during Phase 4C.

---

## 10. Phase 4C Acceptance Testing — Evidence

Methodology: same as Phase 3D — authenticated HTTP requests (curl + cookie jar) against the live app, cross-checked with direct SQL (`aandainventory_db`), plus a Node `vm`-based unit check of the shipped JS file's pure logic (jQuery stubbed to capture DOM writes) since no browser-automation tool is available in this toolset. Disclosed as a methodology substitution, same as prior phases.

| Test | Result |
|---|---|
| `GET sales/project-financial-summary/14` (no exclude) | PASS — total_billed 1180, remaining 98820, progress 1.18%, matches SQL |
| `GET .../14?exclude_sale_id=19` | PASS — total_billed drops to 590 (excludes sale #19's ₹590), matches SQL |
| `GET .../9999` (non-existent project) | PASS — returns `[]`, no error |
| `GET .../15` (COMPLETED project) | PASS — returns correct figures; endpoint imposes no status restriction (by design — the tracker is informational for any project a sale can reference) |
| `sales/create`, `sales/edit/16` render (HTTP 200) | PASS — new card markup, new `<script>` tag, `initProjectFinancialTracker()` call all present exactly once |
| Live regression: `POST sales/store` for project 14 (₹6.16 GENERAL sale) | PASS — sale #20 created (303 redirect), `stock_ledger`/`sale_items` populated as before Phase 4B |
| Summary reflects new sale, exclude param nullifies it correctly | PASS — total_billed 1186.16 with sale #20 included, back to 1180.00 with `exclude_sale_id=20` |
| Warning-level logic (within/fully-billed/exceeds/untracked) | PASS — all 4 branches verified against the actual shipped `project-financial.js`, correct class + message + excess amount each time |
| Progress color thresholds (70/85/110%) | PASS — green/amber/red exactly at the documented breakpoints |
| `php -l` on all modified PHP files | PASS — no syntax errors |
| Static asset delivery (`gst-calc.js`, `project-financial.js`) | PASS after remediation (§9) — both return HTTP 200 |
| Regression spot-check: `sales`, `sales/view/16`, `dashboard`, `reports/sales`, `reports/ledger`, `projects/view/14`, `stock-returns` | PASS — all HTTP 200, unaffected |

**No regressions found.** Phase 4 is complete.

Test data note: sale #20 (`PF-TEST-1`, project 14, ₹6.16) was created live during acceptance testing and left in place, consistent with the Phase 3D precedent of not cleaning up test data after acceptance runs.

---

## 11. Known Limitations (deferred)

- The tracker is Sales-only; Purchases/Expenses do not appear in "Total Billed" (matches the existing `getFinancialSummary()` scope, unchanged from Phase 1).
- No caching of the summary response — every project change or page load re-fetches; acceptable given the endpoint is a single indexed aggregate query.
- The `assets/js/` mirror (§9) is a manual copy, not a build step — any future edit to `public/assets/js/gst-calc.js` or `project-financial.js` must be manually re-copied to `assets/js/` until a proper build/deploy step is introduced (out of scope for Phase 4).
