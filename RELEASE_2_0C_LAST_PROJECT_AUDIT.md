# Release 2.0C Hotfix Audit — Last Project Billing Flow (READ ONLY)

Scope: root-cause investigation only. No code, database, or migration changes were made while
producing this report. All figures below were pulled directly from `aandainventory_db` via the
MySQL CLI (`D:\xampp\mysql\bin\mysql.exe`) on 2026-08-31.

The most recently created project is **id 29 — "shreeya clinic chennai"** (`projects.id = 29`,
`created_at` is the highest in the table). All sections below cover this project only.

---

## 1. Project

| Field | Value |
|---|---|
| Project ID | 29 |
| Project Name | shreeya clinic chennai |
| Customer | Dr.VIJAYALAKSHMI SHREEYA CLINIC |
| Contract Value (`total_project_value`) | ₹2,586,869.00 |
| Advance Amount | ₹50,000.00 |
| Advance Date | 2026-04-01 |
| Project Status (`status`) | **ACTIVE** |
| Work Status (UI) | same field as Project Status — `projects/view.php` renders `$project['status']` directly, no separate "work status" column exists in the schema |
| Payment Status (computed) | **PAID** — via `ProjectModel::getPaymentStatus(29)`: 1 invoice total, 1 invoice with `status='PAID'` → `paid_count === total` → `'PAID'` |

Source: `projects` table, row id 29. Displayed by `Projects::view()` (`app/Controllers/Projects.php:197-213`) and `Projects::statement()` → `_buildStatementData()` (`app/Controllers/Projects.php:496-545`).

## 2. Purchases

One purchase is linked to project 29:

| Purchase ID | Date | Supplier ID | Invoice No | Total Amount |
|---|---|---|---|---|
| 39 | 2026-02-02 | 14 | VHFMC1015 | ₹14,160.00 |

Purchase items (`purchase_items` for purchase 39):

| Product ID | Qty | Project Qty | General Qty | Line Total (ex-GST) | Line Total (incl. GST) |
|---|---|---|---|---|---|
| 43 | 1 | 1 | 0 | ₹12,000.00 | ₹14,160.00 |

**Allocation:** the single line is 100% `project_qty` (1 of 1) — nothing allocated to General stock, so this purchase is not "mixed."

**Total project cost (allocation-aware):** `ProjectModel::getAllocatedPurchaseCost(29)` sums only the `project_qty` portion of every line, GST-adjusted via `gst_calculate_line()`. For this project that equals the full invoice: **₹14,160.00**. Matches `purchases.total_amount` exactly because the one line is fully project-allocated.

Source/method: `ProjectModel::getAllocatedPurchaseCost()` / `getAllocatedPurchaseCostByProject()` (`app/Models/ProjectModel.php:101-154`), called from `Projects::view()` (`$data['total_purchases']`) and `Projects::_buildStatementData()` (`$totalPurchases`).

## 3. Sales

One sales invoice is linked to project 29:

| Invoice No | Sale Date | Invoice Total | Advance Applied | Paid Amount | Balance Amount | Status |
|---|---|---|---|---|---|---|
| C/INV/10 | 2026-04-01 | ₹21,240.00 | ₹21,240.00 | ₹0.00 | ₹0.00 | **PAID** |

**Cumulative billed amount after each invoice:** since there is only one invoice, cumulative billed = ₹21,240.00 after (and only after) this invoice. This is 0.82% of the ₹2,586,869.00 contract value.

**Why is it PAID with `paid_amount = 0`?** The invoice's entire ₹21,240.00 was absorbed by the project's ₹50,000.00 advance via FIFO allocation (`SaleModel::recalculateProjectBilling()`, `app/Models/SaleModel.php:30-46`), leaving ₹28,760.00 of advance unconsumed. See §6 for the exact status-derivation logic and confirmation this is correct, not a bug.

Source: `sales` table row id 35, queried by `Projects::view()` (`$data['sales']`), `Sales::index()`, `Sales::view()`. Status/amounts are written exclusively by `SaleModel::_recalculateSaleRow()` (`app/Models/SaleModel.php:69-90`).

## 4. Payments

**Zero rows.** No `payments` table records exist for any sale under project 29:

```sql
SELECT py.* FROM payments py INNER JOIN sales s ON py.sale_id = s.id WHERE s.project_id = 29;
-- 0 rows
```

This is expected, not an error: the invoice was fully settled by advance allocation
(`sales.advance_applied = 21240.00 = sales.total_amount`), so no separate "Invoice Payment"
was ever needed. Cumulative customer payments (`SUM(payments.amount)` for this project) = **₹0.00**.

This is also why `financial_summary['total_paid']` (§5) is ₹0.00 even though the invoice shows
"PAID" — `total_paid` sums only the `payments` table, and advance is tracked separately. This
distinction is real and by design (advance ≠ a payment row), but it is also the source of one of
the display inconsistencies in §6.

## 5. Project Statement Verification

Rebuilt independently from raw SQL and cross-checked against both code paths that compute these
numbers (`ProjectModel::getFinancialSummary()`, used by both `Projects::view()` and
`Projects::statement()`):

| Metric | Value | Formula | Verified |
|---|---|---|---|
| Contract Value | ₹2,586,869.00 | `projects.total_project_value` | ✅ |
| Total Invoiced (`total_billed`) | ₹21,240.00 | `SUM(sales.total_amount)` | ✅ matches single invoice |
| Advance Received | ₹50,000.00 | `projects.advance_amount` | ✅ |
| Customer Paid (`total_paid`) | ₹0.00 | `SUM(sales.paid_amount)` | ✅ matches §4 (0 payment rows) |
| Customer Pending (`outstanding_collection_balance`) | **−₹28,760.00** | `total_billed − advance_amount − total_paid` = 21240 − 50000 − 0 | ✅ arithmetic confirmed |
| Remaining Billable Value | ₹2,515,629.00 | `total_project_value − advance_amount − total_billed` | ✅ (not currently displayed anywhere per 2.0B/2.0C spec) |
| Billing Progress % | 0.82% | `(total_billed / total_project_value) × 100` = 21240/2586869 | ✅ |
| Project Cost (allocation-aware) | ₹14,160.00 | `getAllocatedPurchaseCost(29)` + `expenses` (0 rows) | ✅ |
| Net Profit | ₹7,080.00 | `total_billed − total_cost` (statement) **and** `total_sales − total_purchases − total_expenses` (view) | ✅ both formulas agree — see RC5 in §6 |

**The `outstanding_collection_balance` of −₹28,760.00 is negative**, meaning: of the ₹50,000
advance received, ₹21,240 has now been consumed by the one invoice raised, leaving ₹28,760 of
**unbilled advance credit** sitting with the customer — not an amount owed by the customer. This
sign matters directly for the root-cause findings below.

### Timeline reconstruction

`ProjectModel::getTimelineEvents(29)` (`app/Models/ProjectModel.php:187-297`) produces, sorted
chronologically:

| Date | Type (stored) | Amount | Category | Running Balance |
|---|---|---|---|---|
| 2026-02-02 | Purchase | ₹14,160.00 | Cost | — (cost rows never touch running balance) |
| 2026-04-01 | Project Advance | ₹50,000.00 | Payment | −₹50,000.00 |
| 2026-04-01 | Sales Invoice | ₹21,240.00 | Billing | −₹28,760.00 |

Verified against raw DB rows: matches `purchases` (1 row), the advance fields on `projects`, and
`sales` (1 row) exactly — no missing or extra events. Final running balance (−₹28,760.00) equals
`outstanding_collection_balance` computed independently in §5 — **the two code paths agree.**
`projects/statement.php` correctly renders this final row as a green "Advance Credit ₹28,760.00"
pill (its running-balance rendering already sign-checks: `< -0.004` → Advance Credit,
`statement.php:265-266`).

---

## 6. Root Cause Investigation

### Q: Why is the sale showing Completed/PAID even though the overall project is still ACTIVE?

**This is correct, decoupled data — the confusion is purely presentational, not a logic bug.**
`sales.status` and `projects.status` are two independent enums by design (Release 2.0A's approved
flow), and nothing in `Sales.php` or `SaleModel.php` sets one from the other except the one-way
create-time guard (`Sales::store()` blocks creating a *new* invoice when `projects.status ===
'COMPLETED'` — it never reads invoice status back into project status). The visible confusion
comes from two UI/naming choices that make an invoice-level "PAID" *read* as project-level
"Completed":

* **RC1 — shared vocabulary/color between unrelated statuses.** `public/assets/css/style.css:410`
  and `:414`:
  ```css
  .badge-paid      { background: #dcfce7; color: #15803d; }   /* sales.status = PAID */
  .badge-completed { background: #dcfce7; color: #15803d; }   /* projects.status = COMPLETED */
  ```
  Identical color, identical pill shape, identical `badge-status badge-<value>` class pattern.
  A green "PAID" invoice badge is visually indistinguishable from a green "COMPLETED" project
  badge.
* Compounding it, `app/Views/sales/index.php:154-157` and `:200` name the tab that lists
  `status='PAID'` invoices **"Completed Sales"** — literally reusing the word "Completed" for an
  invoice-level concept, right next to a project workflow whose own status value is also
  "COMPLETED". Two unrelated enums now share a word and a color.

### Q: Is the invoice correctly marked PAID because advance covered it?

**Yes — confirmed correct.** `SaleModel::_recalculateSaleRow()` (`app/Models/SaleModel.php:69-90`):
```php
$pending = round($totalAmount - $advanceApplied - $paid, 2);   // 21240 - 21240 - 0 = 0.00
$balance = max(0.0, $pending);                                  // 0.00
if ($pending <= 0.004) { $status = 'PAID'; }                     // 0.00 <= 0.004 → PAID
```
This matches the raw DB row exactly (`advance_applied = 21240.00`, `balance_amount = 0.00`,
`status = 'PAID'`). Working as designed since Release 1.6.4.

### Q: Is the project incorrectly displaying a completed message?

**No.** `projects/view.php:168-183` gates the completion alert strictly on `$project['status']`:
```php
<?php if ($project['status'] === 'COMPLETED' && $payment_status === 'PAID'): ?> ... success ...
<?php elseif ($project['status'] === 'COMPLETED'): ?> ... warning ...
<?php elseif ($project['status'] === 'ACTIVE'): ?>
    Project work is currently in progress.
```
Project 29's `status` is `ACTIVE`, so it renders the third (ACTIVE/in-progress) branch. No
completed messaging is shown. Verified correct.

### Q: Is any UI still coupling invoice completion with project completion?

**No hard coupling in business logic.** The only cross-reference is the intentional, one-way
create-time guard in `Sales::store()` (`app/Controllers/Sales.php:115-121`) that blocks a *new*
invoice on a COMPLETED project — it does not read invoice status, and does not write project
status. The apparent coupling users perceive is the presentational overlap described in RC1 above,
plus two further display defects found while tracing every value shown for this project:

* **RC2 — sign-unaware "Customer Pending" card on the Project Health Dashboard.**
  `app/Views/projects/view.php:46-54` renders `outstanding_collection_balance` raw:
  ```php
  <div class="kpi-card kpi-orange">
      <div class="kpi-value"><?= number_format($financial_summary['outstanding_collection_balance'] ?? 0, 2) ?></div>
      <div class="kpi-label">Customer Pending</div>
  </div>
  ```
  For project 29 this literally renders **an orange "Customer Pending" card reading "-28,760.00"**
  — a negative number, unlabeled as such, in a color that signals money owed. The true meaning
  (₹28,760 of unbilled advance credit) is only made legible by the *older* card on
  `projects/statement.php:136-159`, which already has the correct sign-aware branches (positive →
  orange "Customer Pending", `< -0.004` → green "Advance Credit", else → green "Settled"). The
  Health Dashboard (introduced 2.0C) does not reuse that existing, already-correct pattern for the
  *same field* — this is the most concrete "incorrect" display currently reachable for this
  project.
* **RC3 — the label "Customer Pending" now names two different metrics.** The 2.0C Sales List
  addition (`app/Views/sales/index.php:167,183-184` / `:207,223-224`) also titles a new column
  "Customer Pending", but it renders `sales.balance_amount` — a **per-invoice** figure that is
  always ≥ 0 (₹0.00 for this project's one invoice, correctly, since it's fully settled). Right
  next to the Project Health Dashboard's **project-level**, sign-able "Customer Pending"
  (−₹28,760.00), the identical label now refers to two different quantities with different
  ranges. Looking at both screens for project 29, a user sees "Customer Pending: 0.00" on the
  Sales List and "Customer Pending: -28,760.00" on the Project view — same words, opposite
  implications, for the same project.

### Q: Are there duplicate calculations between Project View, Sales List, Statement, and Payments?

**Yes, two found:**

* **RC4 — two independent Contract/Billing status badges that will diverge.**
  `projects/view.php:11-19` (introduced 2.0B, revised 2.0C) computes a 2-state badge (Within
  Contract / Near Contract ≥90%) from `financial_summary['billing_progress_percent']`.
  `projects/statement.php:77-95` — an older "Project Summary" card explicitly marked out-of-scope
  in both the 2.0B and 2.0C baseline docs — independently computes a **3-state** badge (adds a red
  "Over Contract by ₹X" state) directly from `$csBilled` vs `$csValue`, not from
  `billing_progress_percent`. For project 29 both agree (0.82% → green "Within Contract" on both
  pages) so it is *not currently visible as a bug*, but the two implementations are not the same
  code and will disagree the moment any project is billed past 100% of contract value (view.php
  would still show orange "Near Contract"; statement.php would show red "Over Contract"). This is
  a latent duplicate-calculation risk, not an active defect for project 29.
* **RC5 — net profit computed by two separate expressions.** `Projects::view()`
  (`app/Controllers/Projects.php:245-248`) computes `total_sales − total_purchases −
  total_expenses` where `total_sales = array_sum(array_column($data['sales'], 'total_amount'))`.
  `Projects::_buildStatementData()` (`app/Controllers/Projects.php:521-524`) computes
  `total_billed − total_cost` where `total_billed` comes from
  `ProjectModel::getFinancialSummary()`. Both currently agree (₹7,080.00 both ways) because
  `total_sales` and `total_billed` sum the identical rows — but it is genuinely duplicated logic
  living in two files, not a shared calculation, so a future change to one query (e.g. excluding a
  cancelled sale in one but not the other) could silently desync Project View's Net Profit from
  the Statement's Net Profit.

**Payments** has no duplicate calculation — it always reads through
`SaleModel::recalculatePaymentState()` → `_recalculateSaleRow()`, the single source of truth;
`Payments.php` itself contains no independent status/amount math.

---

## Summary table — file & method responsible for every displayed figure (project 29)

| Displayed value | Screen | File : method | Notes |
|---|---|---|---|
| Contract Value, Advance, dates | Project View / Statement | `projects` table direct query in `Projects::view()` / `_buildStatementData()` | — |
| Total Invoiced, Total Paid, Customer Pending, Billing % | Project View / Statement | `ProjectModel::getFinancialSummary()` | single source, no divergence |
| Payment Status badge | Project View | `ProjectModel::getPaymentStatus()` | correct for this project |
| Project purchase cost | Project View / Statement | `ProjectModel::getAllocatedPurchaseCost()` | correct, allocation-aware |
| Net Profit | Project View | `Projects::view()` inline | RC5 — duplicated formula |
| Net Profit | Statement | `Projects::_buildStatementData()` inline | RC5 — duplicated formula |
| Timeline rows + running balance | Statement | `ProjectModel::getTimelineEvents()` | verified correct |
| Contract status badge (2-state) | Project View | `projects/view.php:11-19` | RC4 — diverges from statement.php |
| Contract status badge (3-state, "Over Contract") | Statement | `projects/statement.php:77-95` | RC4 — pre-2.0B, out of scope both releases |
| Invoice status/amounts | Sales List / Sale View / Project View | `SaleModel::_recalculateSaleRow()` | verified correct |
| "Completed Sales" tab label | Sales List | `sales/index.php:157,200` | RC1 |
| `badge-paid` / `badge-completed` colors | global | `public/assets/css/style.css:410,414` | RC1 |
| Health Dashboard "Customer Pending" card | Project View | `projects/view.php:46-54` | RC2 — no sign handling |
| Statement "Customer Pending" card | Statement | `projects/statement.php:136-159` | correct reference implementation |
| Sales List "Customer Pending" column | Sales List | `sales/index.php:183-184,223-224` | RC3 — same label, different metric |

---

## Smallest safe fix per root cause (proposed only — not implemented, per audit rules)

All five are pure display/wording changes; none touch a calculation, migration, or business rule.

1. **RC1** — Rename the Sales List tab/label "Completed Sales" → e.g. "Paid Invoices" (2 string
   literals in `sales/index.php`, lines 157 and 266). Removes the word "Completed" from
   invoice-status UI entirely, eliminating the collision with `projects.status = COMPLETED`.
2. **RC2** — Reuse the exact sign-aware branch already in `projects/statement.php:136-159` inside
   `projects/view.php`'s Health Dashboard "Customer Pending" card, so the same
   `outstanding_collection_balance` field renders identically (and correctly) on both pages. No
   new calculation — copies an existing, already-approved pattern.
3. **RC3** — Rename the 2.0C Sales List column from "Customer Pending" to something
   invoice-scoped, e.g. "Invoice Balance", to stop it colliding with the project-level metric of
   the same name. Header/label only; the underlying `balance_amount` value is unchanged.
4. **RC1 (secondary)** — Give `badge-completed` (project status) a distinct color from
   `badge-paid` (invoice status) in `style.css`, e.g. shift COMPLETED to blue/teal, keeping green
   reserved for "money settled" concepts. Optional if #1 alone resolves the confusion.
5. **RC4/RC5** — Not proposed as an immediate fix: both duplicate-calculation sites are
   currently numerically consistent for every project in the DB (not just #29), and consolidating
   them touches the older, explicitly out-of-scope `statement.php` Project Summary section from
   both 2.0B and 2.0C. Recommend flagging for a dedicated future release (e.g. 2.0D) rather than a
   hotfix, since it is a latent risk, not a currently-observable defect.

## Manual verification note

All figures in this report came directly from `aandainventory_db` via the MySQL CLI, not from the
running application (the local Apache instance still returns HTTP 500/403 on untouched routes —
the same pre-existing environment issue noted in `RELEASE_2_0B_BASELINE.md` and
`RELEASE_2_0C_BASELINE.md`). Code-path tracing (which file/method produces each value, and the
exact sign-handling gap in RC2) was done by reading the relevant PHP directly, not by observing
rendered HTML. Recommend a live-browser pass once the server issue is resolved to visually confirm
RC1–RC3 before implementing any of the proposed fixes.
