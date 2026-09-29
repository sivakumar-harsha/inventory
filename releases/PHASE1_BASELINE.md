# Phase 1 Baseline — Project Financials Feature

Status: **Phase 1 complete, acceptance-tested (PASS), ready for Phase 2.**
This document is the rollback / regression reference point before Phase 2 begins.

---

## 1. Files Modified or Created in Phase 1

| File | Type | Change |
|---|---|---|
| `app/Database/Migrations/2026-08-27-000000_AddProjectValueFields.php` | Created | Adds 4 columns to `projects` |
| `app/Models/ProjectModel.php` | Modified | Added `total_project_value`, `advance_amount`, `advance_date`, `advance_notes` to `$allowedFields`; added `getFinancialSummary()` and `getPaymentStatus()` methods |
| `app/Controllers/Projects.php` | Modified | `store()`/`update()` now write the 4 new fields; `view()` now calls `getFinancialSummary()` and `getPaymentStatus()` and passes them to the view |
| `app/Views/projects/create.php` | Modified | Added 4 new input fields: Total Project Value, Advance Amount, Advance Date, Advance Notes |
| `app/Views/projects/edit.php` | Modified | Same 4 fields added, pre-populated from `$project` |
| `app/Views/projects/view.php` | Modified | Added "Project Financial Summary" card (4-column layout + billing progress bar) |

No other controllers, models, views, routes, or config files were touched.

---

## 2. Database Changes

New columns added to `projects` (migration `2026-08-27-000000_AddProjectValueFields`):

| Column | Type | Default | Nullable | Position |
|---|---|---|---|---|
| `total_project_value` | `DECIMAL(12,2)` | `0.00` | No | after `description` |
| `advance_amount` | `DECIMAL(12,2)` | `0.00` | No | after `total_project_value` |
| `advance_date` | `DATE` | — | Yes | after `advance_amount` |
| `advance_notes` | `VARCHAR(255)` | — | Yes | after `advance_date` |

Rollback: `php spark migrate:rollback` reverses via the migration's `down()`, which drops all 4 columns. No other schema objects (tables, indexes, foreign keys) were changed.

---

## 3. New or Modified Methods

| Method | File | Purpose |
|---|---|---|
| `ProjectModel::getFinancialSummary(int $projectId): array` | `ProjectModel.php` | Returns `total_project_value`, `advance_amount`, `advance_date`, `advance_notes`, `total_billed`, `total_paid`, `remaining_billable_value`, `outstanding_collection_balance`, `billing_progress_percent` for one project. Sources billed/paid from `SUM(sales.total_amount)` / `SUM(sales.paid_amount)` for that `project_id`. |
| `ProjectModel::getPaymentStatus(int $projectId): string` | `ProjectModel.php` | Returns `PAID` / `PARTIAL` / `UNPAID` / `N/A` based on aggregated `sales.total_amount` vs `sales.paid_amount` for the project. Pre-existing logic, now exposed as a reusable model method. |
| `Projects::store()` | `Projects.php` | Modified — now also persists the 4 new fields (defaults to `0`/`null` if not posted). |
| `Projects::update()` | `Projects.php` | Modified — same as `store()`. |
| `Projects::view()` | `Projects.php` | Modified — now also calls `getFinancialSummary()` and `getPaymentStatus()` and adds `financial_summary` / `payment_status` to view data. |

---

## 4. New Calculations

All computed in `ProjectModel::getFinancialSummary()`:

- **Remaining Billable Value** = `total_project_value − total_billed`
  Not clamped — a negative value means the project has been billed beyond its stated contract value (intentional, surfaces over-billing).

- **Outstanding Collection Balance** = `total_billed − advance_amount − total_paid`
  Not clamped — a negative value means collections (advance + payments) exceed what's been billed (intentional, surfaces over-collection).

- **Billing Progress %** = `(total_billed / total_project_value) × 100`, or `0` if `total_project_value` is `0` (guards div-by-zero).

Verified during Phase 1C acceptance testing against synthetic data (value 200,000 / advance 30,000 / billed 120,000 / paid 90,000): Remaining Billable Value = 80,000, Outstanding Collection Balance = 0, Billing Progress % = 60% — all matched hand-computed expected values exactly, including the zero-boundary case.

---

## 5. UI Changes

| Screen | New Fields / Elements |
|---|---|
| `projects/create.php` (Add Project) | 4 new inputs: Total Project Value, Advance Amount Received, Advance Date, Advance Notes |
| `projects/edit.php` (Edit Project) | Same 4 inputs, pre-filled from existing project data |
| `projects/view.php` (Project View) | New "Project Financial Summary" card: Total Project Value, Advance Received, Total Billed, Remaining Billable Value, Total Payments Received, Outstanding Collection Balance, Billing Progress bar (with advance date/notes shown if present) |

`projects/index.php` (list view) was **not** modified in Phase 1.

---

## 6. Regression Baseline (confirmed unchanged)

| Module | Status | Basis |
|---|---|---|
| Sales | Unchanged | No files touched; `sales.paid_amount`/`balance_amount` sync logic in `Payments.php` untouched and re-verified consistent with `getFinancialSummary()`'s assumptions |
| Purchases | Unchanged | No files touched |
| Payments | Unchanged | No files touched; `store()`/`update()`/`delete()` re-verified during Phase 1C to still correctly sync `sales.paid_amount` |
| Reports | Unchanged | No files touched |
| Dashboard | Unchanged | No files touched |
| Stock Entry | Unchanged | No files touched |

`Projects::index()`'s pre-existing aggregate SQL (joins `sales`/`expenses`, computes `total_sales`/`sale_count`) was also untouched by Phase 1 and was re-run during acceptance testing with no errors.

---

## Acceptance Testing Summary (Phase 1C)

Full checklist passed (PASS) — see prior conversation for detail. Notable disclosures carried into this baseline:
- Production database had zero rows in `projects`/`sales`/`payments` at test time; verification used synthetic data inserted and cleaned up within a transaction (via a temporary Spark CLI command, since deleted).
- Create/Edit/View were verified at the model/controller-logic level, not via a live browser click-through (no dev server was started).
- Purchases/Dashboard/Reports/Stock Entry were confirmed unchanged by code-diff reasoning (no files touched), not by a fresh functional re-test.
