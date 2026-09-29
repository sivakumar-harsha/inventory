# Release 1.6 — Purchase Allocation (Project + General in One Purchase): Approved Business Design

Date: 2026-08-27
Status: **APPROVED — design frozen. No implementation performed under this document.** Implementation is scoped to Release 1.6B and requires a separate, explicit go-ahead.

This document supersedes the open questions in `RELEASE_1_6A_DEPENDENCY_ANALYSIS.md` with final decisions. It is the single source of truth for Release 1.6B implementation.

---

## Decision 1 — Schema: Approach A (approved)

Add two columns to `purchase_items`:

- `project_qty`
- `general_qty`

One row per purchased product line remains. No row-splitting.

**Backward compatibility:** existing rows are backfilled as `project_qty = quantity`, `general_qty = 0`.

**Reason:** preserves `purchase_items.id` identity, which `stock_return_items.purchase_item_id` (RESTRICT FK) and FIFO ordering both depend on.

---

## Decision 2 — Purchase Allocation UI (approved)

Purchase Create/Edit line layout becomes:

| Product | Purchased Qty | Project Qty | General Qty | Unit Price |
|---|---|---|---|---|

Rules:

- User enters **Purchased Qty**.
- User enters **Project Qty**.
- **General Qty** is auto-calculated, read-only: `General Qty = Purchased Qty − Project Qty`.

Validation:

- Project Qty ≤ Purchased Qty.
- Project Qty ≥ 0.
- Purchased Qty > 0.

---

## Decision 3 — Stock Ledger (approved)

Purchase posts stock immediately at save time, per line:

- If Project Qty > 0 → `stock_ledger` row: `IN`, `source = PROJECT`, `quantity = project_qty`, `project_id = <purchase's project>`.
- If General Qty > 0 → `stock_ledger` row: `IN`, `source = GENERAL`, `quantity = general_qty`, `project_id = NULL`.

No Stock Return record is created at purchase time. `reference_type` stays `PURCHASE` for both rows; `reference_id` = the purchase (or purchase item) id.

---

## Decision 4 — Project Financial Cost (approved)

The supplier invoice is untouched: `purchases.total_amount` always represents the full invoice as billed by the supplier, regardless of allocation.

Allocation-aware cost split, per line:

- **Project Cost** = `Project Qty × Unit Price` + GST on the Project Qty portion.
- **General Inventory Value** = `General Qty × Unit Price` + GST on the General Qty portion.

GST follows the quantity split (pro-rata by qty, not re-derived) and reuses the existing Phase 2 GST helper — no duplicate GST calculation logic is introduced.

Example: Purchased 5 × ₹100 = ₹500; Project Qty 2 → Project Cost = 2 × ₹100 (+ GST on 2 units); General Qty 3 → General Inventory Value = 3 × ₹100 (+ GST on 3 units).

---

## Decision 5 — Reports (approved)

**Allocation-aware (must change), project-scoped only:**

- Project View purchase total
- Project Statement purchase timeline amount
- Profit & Loss report (screen, Export, PDF)

**Unchanged:**

- Dashboard totals
- Purchase header totals / purchase invoice amount
- Sales reports
- Payments reports

Dashboard keeps showing total purchases at full supplier-invoice value; only project-scoped views/reports switch to allocated project cost.

---

## Decision 6 — Stock Return Compatibility (approved)

Stock Return's own workflow is unchanged from Release 1.5. Its purpose is now scoped explicitly: used only when stock already allocated to PROJECT is later moved back to GENERAL. Purchase Allocation (this feature) and Stock Return remain two distinct workflows and are not merged.

---

## Decision 7 — FIFO (mandatory)

`StockReturns::_allocateFifo()` must key off `project_qty` instead of `quantity`.

Returnable quantity formula:

```
Remaining Project Qty = Project Qty − Sold From Project − Previously Returned
```

General Qty never participates in FIFO or in Stock Return allocation.

---

## Decision 8 — Purchase Edit/Delete Protection (mandatory)

Edit/delete on a purchase (or a purchase line) must block if **any** of the following is true:

1. A Stock Return exists against the purchase item.
2. Project-side stock from this purchase has already been sold.
3. General-side stock allocated from this purchase has already been sold.

All three checks are independent and all three are required — passing one does not waive the others.

---

## Decision 9 — Backward Compatibility (mandatory)

Existing purchases behave exactly as today after migration:

- Migration does not touch historical `stock_ledger` rows.
- Historical `purchase_items` rows: `project_qty = quantity`, `general_qty = 0`.
- Historical reports produce identical output to pre-1.6.
- Allocation (split Project/General entry) applies only to purchases created after Release 1.6 ships.

---

## Decision 10 — Acceptance Testing Plan (for Release 1.6B)

Implementation checklist to be verified before Release 1.6B sign-off:

1. 100% Project allocation (General Qty = 0)
2. 100% General allocation (Project Qty = 0)
3. Mixed allocation (both > 0)
4. Edit allocation (re-splitting an existing line)
5. Delete allocation (purchase/line removal)
6. General stock sold (post-purchase)
7. Project stock sold (post-purchase)
8. FIFO behavior after allocation (Stock Return against a split purchase)
9. Profit calculation verification (Project Cost uses allocated qty only)
10. Project Statement verification (purchase timeline reflects allocated cost)
11. Dashboard regression (still shows full supplier-invoice totals)
12. Stock Report regression (unaffected by allocation split)
13. Stock Return regression (workflow unchanged, FIFO source field corrected per Decision 7)

---

## Status

**This document is the final approved business design for Release 1.6.**

No implementation has been performed. Release 1.6B implementation may proceed only against this document, under a separate, explicit implementation request.
