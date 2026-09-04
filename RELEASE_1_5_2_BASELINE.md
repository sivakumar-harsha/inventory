# Release 1.5.2 — Project Billing Logic Correction: Acceptance Baseline

Date: 2026-08-27
Scope: Correct the "Remaining Amount to Bill" formula so Advance Amount reduces the remaining billable value.

## Problem

`ProjectModel::getFinancialSummary()` computed:

```
Remaining Billable Value = Total Project Value − Total Billed
```

This ignored the advance as part of contract value, overstating how much was still available to invoice.

## Fix

**File changed: `app/Models/ProjectModel.php` (single line, line 80)**

```diff
- 'remaining_billable_value'       => $totalProjectValue - $totalBilled,
+ 'remaining_billable_value'       => $totalProjectValue - $advanceAmount - $totalBilled,
```

`outstanding_collection_balance` (`total_billed − advance_amount − total_paid`) was already correct and was **not modified**.

### Why no other file needed a code change

Every other file in the allowed list only *consumes* `remaining_billable_value` from `getFinancialSummary()` — none duplicate the formula:

| File | Role | Change needed? |
|---|---|---|
| `app/Models/ProjectModel.php` | Computes the formula | **Yes — 1 line** |
| `app/Controllers/Projects.php` | Reads `$fs['remaining_billable_value']` for view/statement/export | No — passes the model's value through |
| `app/Views/projects/view.php` | Displays `$financial_summary['remaining_billable_value']` | No — display only |
| `app/Views/projects/statement.php` | Displays `$financial_summary['remaining_billable_value']` | No — display only |
| `public/assets/js/project-financial.js` | Renders the value from `sales/project-financial-summary/{id}` JSON and computes `remainingAfter = remaining − currentInvoiceTotal` client-side | No — client-side arithmetic already operates on the (now-corrected) server value |

`Sales::projectFinancialSummary()` (the endpoint the JS calls, used by the Sales Create/Edit tracker) itself just delegates to `ProjectModel::getFinancialSummary()` — confirmed by reading `app/Controllers/Sales.php:95-103` — so it inherits the fix automatically without any Sales controller change, honoring "No Sales controller changes."

No migrations. No schema changes. No changes to Reports, GST, Payments, or Stock Return code.

## Acceptance Testing

Used a real, existing project that already matched the example in the spec exactly: **Project 16 ("Medical Project")**, Total Project Value = ₹5,00,000, Advance = ₹1,00,000, Total Billed = ₹0 (verified via DB before testing).

### Step 1 — Before any sale

Queried live via `GET sales/project-financial-summary/16`:

| Metric | Expected | Actual | Result |
|---|---|---|---|
| Remaining Amount to Bill | ₹4,00,000 | ₹4,00,000.00 | **PASS** |
| Outstanding Collection Balance | ₹-1,00,000 | ₹-1,00,000.00 | **PASS** |

### Step 2 — After creating one real sale of ₹50,000

Submitted a genuine sale through `POST sales/store` (invoice `REL152-TEST-1`, project 16, 1 × Phase3D Product 2 @ ₹50,000, GST not applicable to isolate the total) — confirmed inserted in `sales` table (`id=21, total_amount=50000.00, paid_amount=0.00`).

Re-queried `GET sales/project-financial-summary/16`:

| Metric | Expected | Actual | Result |
|---|---|---|---|
| Remaining Amount to Bill | ₹3,50,000 | ₹3,50,000.00 | **PASS** |
| Outstanding Collection Balance | ₹-50,000 | ₹-50,000.00 | **PASS** |

### Verification across all 4 required surfaces

| Surface | Route | Remaining Amount to Bill | Outstanding Collection Balance | Result |
|---|---|---|---|---|
| Project View | `projects/view/16` (HTML) | 350,000.00 | -50,000.00 | **PASS** |
| Project Statement | `projects/statement/16` (HTML) | 350,000.00 | -50,000.00 | **PASS** |
| Sales Create tracker | `sales/project-financial-summary/16` (JSON consumed by `project-financial.js`) | 350,000.00 | -50,000.00 | **PASS** |
| Sales Edit tracker | `sales/project-financial-summary/16?exclude_sale_id=21` (JSON, simulating editing invoice 21) | 400,000.00 *(correctly excludes the invoice being edited, reverting to pre-sale baseline)* | -100,000.00 | **PASS** |

The Edit tracker's `exclude_sale_id` parameter continues to work correctly with the corrected formula — it recomputes the baseline as if the in-edit invoice didn't exist yet, which is exactly what the create/edit page's client-side "after this invoice" preview needs.

## Regression Check

| Check | Result |
|---|---|
| Project 14 (Advance = ₹0): Remaining Billable Value unchanged at 98,813.84, identical to the Phase 5B baseline figure | **PASS** — confirms the fix is a no-op when there's no advance, as it must be |
| `dashboard`, `sales`, `reports`, `reports/profit-loss`, `reports/ledger`, `projects`, `stock-returns` | all HTTP 200, no fatals |
| No changes to Sales controller, Payments, GST, Stock Return, or Reports code | confirmed — only `app/Models/ProjectModel.php` was modified |
| No schema/migration changes | confirmed — no migrations created, no `ALTER TABLE` run |

## Note on Test Data

Sale `id=21` (invoice `REL152-TEST-1`, project 16, ₹50,000) was created against the live `aandainventory_db` database as part of this acceptance test, per the task's explicit instruction to "create one sale of ₹50,000." It is left in place as evidence of the passing test. If this was not intended to persist (e.g., this is a shared/staging database), delete `sales.id = 21` (and its corresponding `sale_items` row) to restore project 16 to its pre-test state.

## Summary

| Check | Result |
|---|---|
| Formula correction (`ProjectModel.php`) | PASS — single-line change, verified against spec example exactly |
| Project View | PASS |
| Project Statement | PASS |
| Sales Create tracker | PASS |
| Sales Edit tracker | PASS |
| Regression (zero-advance project, other modules) | PASS |

**Overall: PASS.** Remaining Amount to Bill now correctly subtracts Advance Amount; Outstanding Collection Balance formula was already correct and is unchanged. Fix is a single line in `app/Models/ProjectModel.php`.
