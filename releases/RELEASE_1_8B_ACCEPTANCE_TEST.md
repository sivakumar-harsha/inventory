# Release 1.8B — Acceptance Test (manual browser verification checklist)

Scope reminder: UI/UX only. No GST, project-financial, advance-application, stock-source,
controller/model/DB changes.

## Setup

1. Log in, navigate to **Sales → New Sale**.
2. Navigate to an existing sale's **Edit** page (`/sales/edit/{id}`) for a sale with at least
   2 saved items, ideally one GENERAL and one PROJECT source item.

## Create page

- [ ] Page loads with **no** item row yet (unchanged — Sales rows need a Stock Source picked
      first, same as pre-1.8B).
- [ ] Click **Add Item** → one compact row appears: Product / Qty / Unit Price / GST% / GST /
      Line Total on row 1; Stock Source / Project / Available Qty / Status Badge on row 2.
- [ ] Product select shows "Select Source First" until a Stock Source is chosen.
- [ ] Select Stock Source = GENERAL → Product dropdown populates via AJAX with a working Select2
      (search box, styled); Status Badge shows "General Stock"; Project label shows "-".
- [ ] Select a Product → Unit Price, Available Qty, GST % auto-fill from the product's
      `data-price`/`data-qty`/`data-gst`.
- [ ] Change Stock Source to PROJECT (with a Project selected in Sale Details) → Product list
      reloads for PROJECT stock; Status Badge shows "Project Stock"; Project label shows the
      selected project's name.
- [ ] Click **Add Item** again 2–3 times → each new row is independent, numbered `Item #N`, own
      working Select2.
- [ ] Click **Remove** (×) on a row → row disappears, footer totals recompute, other rows'
      values untouched.
- [ ] Enter Qty greater than Available Qty → alert fires, value clamps back to Available Qty
      (unchanged validation).
- [ ] Toggle GST select "With GST" / "Without GST" → Line Total and footer GST update
      immediately; Taxable Amount stays constant.
- [ ] Footer shows one horizontal strip: `Taxable + GST = Grand Total`, Grand Total visually
      highlighted, matching Purchases' footer styling.
- [ ] Project Financial Summary chips (Project Value / This Invoice / Remaining Balance /
      Status) update live as items are added — unchanged from pre-1.8B.
- [ ] Submit with valid data → sale saves (no console errors), redirects as before.

## Edit page

- [ ] Page loads with all previously-saved items rendered as rows, each with correct product,
      quantity, price, GST applicable, and stock source (not reset).
- [ ] Every pre-existing row's Product dropdown is a working Select2 (Phase D fix — previously
      inconsistent/absent on Edit).
- [ ] Click **Add Item** → a new empty row appears, also with a working Select2, visually
      identical to the pre-existing rows.
- [ ] Click **Add Item** again (repeat 3+ times) → works unlimited times, no console errors.
- [ ] Remove a newly-added row → works. Remove a pre-existing (saved) row → works, doesn't
      affect other rows.
- [ ] Change Stock Source on a pre-existing row → Product list reloads for the new source,
      Status Badge/Project label update live.
- [ ] Change the form-level Project selector → every PROJECT-source row's Project label updates
      to match.
- [ ] GST toggle recalculates Line Total and footer totals identically to Create.
- [ ] Footer strip renders identically to Create (`#taxableAmount`, `#gstTotal`, `#grandTotal`
      IDs unchanged, values correct).
- [ ] Submit update with a newly-added row included → sale updates successfully, new item
      persists.

## Responsive (Phase F)

- [ ] Desktop (≥1200px): one compact row per item, no wrapping within a field.
- [ ] Tablet (~768–991px): row fields wrap into roughly two/three lines without a horizontal
      scrollbar on the page.
- [ ] Mobile (≤575px): every input stacks to full width, no horizontal overflow; Add Item /
      Remove / totals strip remain usable.

## Regression (no business logic touched, verify outputs unchanged)

- [ ] **Save Sale** (`sales/store`): items persist with identical
      `product_id`/`quantity`/`unit_price`/`gst_applicable`/`stock_source` values — field `name`
      attributes unchanged.
- [ ] **Update Sale** (`sales/update/{id}`): same as above for existing sales.
- [ ] **Payments**: unaffected — this release does not touch payment views/controllers/models.
- [ ] **Project tracker** (Project Financial Summary): values match pre-1.8B for the same sale
      data — same `updateFinancialPreview()` / `project-financial.js`, untouched.
- [ ] **Stock ledger**: entries generated from saved sale items unaffected — no
      model/controller changes shipped in this release.
- [ ] **Dashboard**: unaffected — no shared aggregation logic touched.

## Non-goals confirmed absent from the diff

- [ ] No changes to `assets/js/gst-calc.js`.
- [ ] No changes to `assets/js/project-financial.js`.
- [ ] No changes to any file under `app/Controllers/` or `app/Models/`.
- [ ] No changes to `app/Views/purchases/*` (share `.item-row`/`.total-row` base classes with
      Sales in `assets/css/style.css`; all new compact styling lives in
      `app/Views/sales/_item_row_style.php`, scoped under `#saleForm`).
- [ ] No DB migrations added.
