<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
/* Release 4.8.6G: compact General Purchase entry screen, laid out like the Supplier
   Payment voucher screen. Controls are 32px on this page only.
   Grid areas: >=1200px = voucher + items | summary (sticky); 768-1199px = voucher,
   summary, items; <768px = voucher, items, summary. */
.gp-page { --input-height: 32px; }
.gp-page .page-title { margin-bottom: 10px; }
.gp-page .card-custom { margin-bottom: 0; }
.gp-page .card-custom-header { padding: 7px 12px; }
.gp-page .card-custom-body { padding: 9px 12px; }

.gp-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas: "voucher" "summary" "items";
    gap: 14px;
    align-items: start;
}
.gp-voucher { grid-area: voucher; }
.gp-items   { grid-area: items; }
.gp-summary { grid-area: summary; }
@media (max-width: 767.98px) {
    .gp-layout { grid-template-areas: "voucher" "items" "summary"; }
}
@media (min-width: 1200px) {
    .gp-layout {
        grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr);
        grid-template-areas: "voucher summary" "items summary";
        grid-template-rows: auto 1fr;
    }
    .gp-summary { position: sticky; top: 72px; max-height: calc(100vh - 88px); overflow-y: auto; }
}

/* Select2 sized to match the 32px controls used on this page */
.gp-page .select2-container--default .select2-selection--single {
    height: var(--input-height);
    border: 1px solid #d1d5db;
    border-radius: var(--border-radius);
}
.gp-page .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: calc(var(--input-height) - 2px);
    font-size: var(--font-size-sm);
    padding-left: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gp-page .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(var(--input-height) - 2px); }
.gp-page .select2-container { width: 100% !important; }

/* Compact voucher header: tight labels, four fields on one row */
.gp-page .form-label { font-size: .7rem; font-weight: 600; margin-bottom: 2px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .02em; }
.gp-page .form-section { margin-bottom: 5px; }
.gp-page .form-section:last-child { margin-bottom: 0; }
.gp-hdr-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 10px; }
@container gp-voucher-body (min-width: 560px) {
    .gp-hdr-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.gp-page .gp-hdr-grid .form-section { min-width: 0; margin-bottom: 5px; }
.gp-voucher .card-custom-body { container-type: inline-size; container-name: gp-voucher-body; }
@container gp-voucher-body (max-width: 300px) {
    .gp-hdr-grid { grid-template-columns: minmax(0, 1fr); }
}
.gp-page textarea.form-control { min-height: 0; height: auto; padding: 5px 10px; font-size: .8rem; line-height: 1.35; resize: vertical; }

/* Supplier row: 85% dropdown / 15% add button */
.gp-supplier-grid { margin-bottom: 6px; }
.gp-supplier-pick { display: flex; gap: 8px; }
.gp-supplier-pick .gp-sel { flex: 0 0 calc(85% - 4px); min-width: 0; }
.btn-quick-add-supplier {
    flex: 1 1 0;
    min-width: 36px;
    height: var(--input-height);
    border: none;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s ease;
}
.btn-quick-add-supplier:hover { background: var(--primary-dark); }
#quickAddSupplierModal .invalid-feedback-text { font-size: 0.8rem; }

/* Supplier summary: one compact strip (profile details live on the Supplier page) */
.gp-strip {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(0, .7fr) minmax(0, 1fr);
    align-items: center;
    gap: 2px 12px;
    min-height: 55px;
    padding: 5px 12px;
    margin-bottom: 6px;
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
}
.gp-strip .st-item { display: flex; flex-direction: column; min-width: 0; }
.gp-strip .st-item > span { font-size: .62rem; line-height: 1.2; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
.gp-strip .st-item > b { font-size: .86rem; line-height: 1.3; color: var(--text-dark); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gp-strip .st-name > b { font-size: .95rem; font-weight: 700; }
.gp-strip .st-name > span.gst { font-size: .68rem; text-transform: none; letter-spacing: 0; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gp-strip .st-due > b { color: #c2410c; }
@media (min-width: 576px) { .gp-strip { max-height: 55px; } }
@media (max-width: 575.98px) {
    .gp-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .gp-strip .st-name { grid-column: 1 / -1; }
}
@container gp-voucher-body (max-width: 300px) {
    .gp-strip { grid-template-columns: minmax(0, 1fr); gap: 1px; padding: 4px 10px; max-height: none; }
    .gp-strip .st-item:not(.st-name) { flex-direction: row; justify-content: space-between; align-items: baseline; gap: 8px; }
}

/* Product items: 7 columns, fixed layout so it fits the card with no horizontal scroll */
.gp-items-wrap { container-type: inline-size; container-name: gp-items; }
.gp-items .card-custom-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px 8px; }
.gp-items .card-custom-header .btn-save { padding: 3px 12px; font-size: .78rem; line-height: 1.4; }
.gp-item-table { width: 100%; table-layout: fixed; }
.gp-item-table th, .gp-item-table td { vertical-align: middle; }
.gp-item-table thead th { padding: 6px 4px; font-size: .68rem; letter-spacing: .02em; white-space: nowrap; }
.gp-item-table td { padding: 3px 4px; }
.gp-item-table .c-wh  { width: 58px; text-align: center; }
.gp-item-table .c-qty { width: 78px; }
.gp-item-table .c-rate { width: 88px; }
.gp-item-table .c-gst { width: 60px; }
.gp-item-table .c-tot { width: 100px; text-align: right; vertical-align: middle; }
.gp-item-table .c-del { width: 34px; text-align: center; }
.gp-item-table th.gp-num { text-align: right; padding-right: 8px; }
.gp-item-table .select2-container--default .select2-selection--single { height: 30px; }
.gp-item-table .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px; }
.gp-item-table .select2-container--default .select2-selection--single .select2-selection__arrow { height: 28px; }
.gp-item-table input.form-control { height: 30px; font-size: var(--font-size-sm); padding: 0 6px; text-align: right; font-variant-numeric: tabular-nums; }
.gp-item-table input[type=number]::-webkit-inner-spin-button,
.gp-item-table input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.gp-item-table input[type=number] { -moz-appearance: textfield; appearance: textfield; }
.gp-item-table .warehouse-qty-badge { display: block; padding: 2px 3px; font-size: .72rem; text-align: center; overflow: hidden; text-overflow: ellipsis; }
.warehouse-qty-badge { display: inline-block; border-radius: 6px; background: #eef2f7; color: #334155; font-weight: 600; white-space: nowrap; }
.warehouse-qty-badge.zero { background: #fef2f2; color: #991b1b; }
.gp-item-table .line-total { display: block; font-weight: 800; line-height: 30px; font-size: .84rem; font-variant-numeric: tabular-nums; padding-right: 4px; }
.gp-item-table .line-gst { display: none; font-size: .68rem; color: var(--text-muted); }
.gp-remove-row { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 1rem; padding: 0; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; }
.gp-remove-row:hover { color: #7f1d1d; }
.gp-empty-row td { text-align: center; color: #94a3b8; padding: 22px 12px; font-size: .85rem; }

/* Inline validation: red border + one small message, no extra row height */
.gp-err { color: #dc2626; font-size: .74rem; margin-top: 3px; line-height: 1.3; }
#itemsErr { margin: 4px 2px 0; }
.gp-page .gp-invalid { border-color: #dc2626 !important; }
.gp-page select.gp-invalid + .select2-container--default .select2-selection--single { border-color: #dc2626; }

/* Phones and any narrow card (tablet with the sidebar open): one product per stacked block */
@media (max-width: 767.98px) {
    .gp-item-table thead { display: none; }
    .gp-item-table, .gp-item-table tbody { display: block; width: 100%; }
    .gp-item-table tr.item-row {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px 8px;
        position: relative; padding: 8px 34px 8px 2px; border-bottom: 1px solid var(--border-color);
    }
    .gp-item-table tr.item-row td { display: block; width: auto; padding: 0; text-align: left; }
    .gp-item-table tr.item-row td::before { content: attr(data-label); display: block; font-size: .64rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1px; }
    .gp-item-table tr.item-row td.c-prod { grid-column: 1 / -1; }
    .gp-item-table tr.item-row td.c-prod::before { content: none; }
    .gp-item-table tr.item-row td.c-tot, .gp-item-table tr.item-row td.c-wh { text-align: left; }
    .gp-item-table tr.item-row td.c-del { position: absolute; top: 6px; right: 2px; width: auto; }
    .gp-item-table tr.item-row td.c-del::before { content: none; }
    .gp-item-table .line-gst { display: block; }
    .gp-item-table .warehouse-qty-badge { display: inline-block; padding: 3px 8px; }
    .gp-item-table .gp-empty-row, .gp-item-table .gp-empty-row td { display: block; width: auto; }
}
@container gp-items (max-width: 540px) {
    .gp-item-table thead { display: none; }
    .gp-item-table, .gp-item-table tbody { display: block; width: 100%; }
    .gp-item-table tr.item-row {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px 8px;
        position: relative; padding: 8px 34px 8px 2px; border-bottom: 1px solid var(--border-color);
    }
    .gp-item-table tr.item-row td { display: block; width: auto; padding: 0; text-align: left; }
    .gp-item-table tr.item-row td::before { content: attr(data-label); display: block; font-size: .64rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1px; }
    .gp-item-table tr.item-row td.c-prod { grid-column: 1 / -1; }
    .gp-item-table tr.item-row td.c-prod::before { content: none; }
    .gp-item-table tr.item-row td.c-tot, .gp-item-table tr.item-row td.c-wh { text-align: left; }
    .gp-item-table tr.item-row td.c-del { position: absolute; top: 6px; right: 2px; width: auto; }
    .gp-item-table tr.item-row td.c-del::before { content: none; }
    .gp-item-table .line-gst { display: block; }
    .gp-item-table .warehouse-qty-badge { display: inline-block; padding: 3px 8px; }
    .gp-item-table .gp-empty-row, .gp-item-table .gp-empty-row td { display: block; width: auto; }
}

/* Payment summary: purchase summary + payment information */
.gp-sec { padding: 6px 0; border-bottom: 1px dashed var(--border-color); }
.gp-sec:first-child { padding-top: 0; }
.gp-sec:last-of-type { border-bottom: none; }
.gp-sec-title { font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; }
.gp-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: .84rem; padding: 2px 0; }
.gp-row .amt { font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
.gp-row.sm { font-size: .74rem; color: var(--text-muted); }
.gp-row.sm .amt { font-weight: 500; }
.gp-row.total { margin-top: 2px; padding-top: 6px; border-top: 1px solid var(--border-color); }
.gp-row.total > span:first-child { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
.gp-row.total .amt { font-size: 1.25rem; font-weight: 700; color: var(--primary-dark); }
.gp-row.due { margin-top: 4px; }
.gp-row.due > span:first-child { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
.gp-row.due .amt { color: #c2410c; font-weight: 700; font-size: 1.4rem; }
.gp-row.due .amt.ok { color: #15803d; }
.gp-sub { font-size: .7rem; color: var(--text-muted); line-height: 1.3; text-align: right; font-variant-numeric: tabular-nums; }

.gp-adv-row input { max-width: 130px; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
.gp-badge { display: inline-block; padding: 1px 9px; font-size: .64rem; font-weight: 700; border-radius: 20px; letter-spacing: .05em; text-transform: uppercase; margin-left: 6px; }
.gp-status-paid    { background: #dcfce7; color: #15803d; }
.gp-status-partial { background: #fef3c7; color: #92400e; }
.gp-status-pending { background: #f1f5f9; color: #475569; }
.gp-actions { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; margin-top: 8px; }

.gp-breadcrumb { margin-bottom: 8px; }
.gp-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
.gp-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
.gp-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
.gp-breadcrumb .breadcrumb-item.active { color: #64748b; }

.quick-add-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #16a34a;
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    font-size: 0.875rem;
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    z-index: 2000;
}
.quick-add-toast.show { opacity: 1; transform: translateY(0); }
</style>

<div class="gp-page">

<nav aria-label="breadcrumb" class="gp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('purchases') ?>">Purchases</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('general-purchases') ?>">General Purchase</a></li>
        <li class="breadcrumb-item active" aria-current="page">Create</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-boxes me-2"></i>New General Purchase</span>
    <a href="<?= base_url('general-purchases') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php $old = $old ?? []; // Release 4.8.4H: posted values, only set when the server re-renders this form with a 422 ?>
<?php if (! empty($formErrors)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(implode(' ', $formErrors)) ?></div>
<?php endif; ?>

<form action="<?= base_url('general-purchases/store') ?>" method="POST" id="gpForm" novalidate>

<div class="gp-layout">

    <!-- Voucher Information -->
    <div class="card-custom gp-voucher">
        <div class="card-custom-header">Voucher Information</div>
        <div class="card-custom-body">
            <div class="gp-hdr-grid">
                <div class="form-section">
                    <label class="form-label">GP Number</label>
                    <input type="text" class="form-control" value="<?= esc($nextPurchaseNo) ?>" readonly>
                </div>
                <div class="form-section">
                    <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" id="purchaseDate" class="form-control" value="<?= esc($old['purchase_date'] ?? date('Y-m-d')) ?>" required>
                    <div class="gp-err d-none" id="err-purchase_date"></div>
                </div>
                <div class="form-section">
                    <label class="form-label">Bill Number</label>
                    <input type="text" name="bill_no" class="form-control" value="<?= esc($old['bill_no'] ?? '') ?>">
                </div>
                <div class="form-section">
                    <label class="form-label">Bill Date <span class="text-danger">*</span></label>
                    <input type="date" name="bill_date" id="billDate" class="form-control" value="<?= esc($old['bill_date'] ?? date('Y-m-d')) ?>" required>
                    <div class="gp-err d-none" id="err-bill_date"></div>
                </div>
            </div>

            <div class="gp-supplier-grid">
                <label class="form-label">Supplier <span class="text-danger">*</span></label>
                <div class="gp-supplier-pick">
                    <div class="gp-sel">
                        <select name="supplier_id" id="supplierSelect" class="form-control" required>
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                data-name="<?= esc($s['name'] ?? '') ?>"
                                data-gst="<?= esc($s['gst'] ?? '') ?>"
                                <?= (string) ($old['supplier_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn-quick-add-supplier" data-bs-toggle="modal" data-bs-target="#quickAddSupplierModal" title="Add New Supplier">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <div class="gp-err d-none" id="err-supplier"></div>
            </div>

            <!-- Supplier summary: one compact strip -->
            <div class="gp-strip" id="supplierStrip">
                <div class="st-item st-name"><b id="infoName">No supplier selected</b><span class="gst" id="infoGst">-</span></div>
                <div class="st-item"><span>GP Outstanding Bills</span><b id="infoBillsCount">0</b></div>
                <div class="st-item st-due"><span>GP Outstanding Amount</span><b>&#8377;<span id="infoTotalOutstanding">0.00</span></b></div>
            </div>

            <div class="form-section">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="2"><?= esc($old['remarks'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Product Items -->
    <div class="card-custom gp-items">
        <div class="card-custom-header">
            <span>Product Items</span>
            <button type="button" class="btn-save btn-sm" id="addItemBtn"><i class="bi bi-plus"></i> Add Product</button>
        </div>
        <div class="card-custom-body">
            <div class="gp-items-wrap">
                <table class="table-custom gp-item-table">
                    <thead>
                        <tr>
                            <th class="c-prod">Product</th>
                            <th class="c-wh" title="Warehouse Qty">WH Qty</th>
                            <th class="c-qty gp-num">Qty</th>
                            <th class="c-rate gp-num">Rate</th>
                            <th class="c-gst gp-num">GST %</th>
                            <th class="c-tot gp-num" title="Line total including GST">Total</th>
                            <th class="c-del"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsContainer">
                        <tr class="gp-empty-row" id="emptyItemsRow">
                            <td colspan="7">No products added yet. Click "+ Add Product" to begin.</td>
                        </tr>
                    </tbody>
                </table>
                <div class="gp-err d-none" id="itemsErr"></div>
            </div>
        </div>
    </div>

    <!-- Payment Summary (sticky on wide screens) -->
    <div class="card-custom gp-summary">
        <div class="card-custom-header">Payment Summary</div>
        <div class="card-custom-body">

            <div class="gp-sec">
                <div class="gp-sec-title">Purchase Summary</div>
                <div class="gp-row sm"><span>Subtotal</span><span class="amt">&#8377;<span id="sumSubtotal">0.00</span></span></div>
                <div class="gp-row sm"><span>GST</span><span class="amt">&#8377;<span id="sumGstTotal">0.00</span></span></div>
                <div class="gp-row total"><span>Grand Total</span><span class="amt">&#8377;<span id="sumGrandTotal">0.00</span></span></div>

                <div class="gp-row gp-adv-row" style="margin-top:6px">
                    <label class="form-label mb-0" for="advancePaid" style="text-transform:none; font-size:.84rem; color:var(--text-dark)">Advance Paid Now</label>
                    <input type="number" name="advance_paid" id="advancePaid" class="form-control" step="0.01" min="0" value="<?= esc($old['advance_paid'] ?? 0) ?>">
                </div>
                <div class="gp-err d-none" id="err-advance"></div>

                <div class="gp-row due"><span>Outstanding Balance</span><span class="amt">&#8377;<span id="sumOutstanding">0.00</span></span></div>
            </div>

            <!-- Release 4.8.4H: shown only while Advance Paid > 0 -->
            <div class="gp-sec d-none" id="paymentMethodSection">
                <div class="gp-sec-title">Payment Information</div>
                <div class="form-section">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" id="paymentMethod" class="form-control">
                        <?php $oldMethod = (string) ($old['payment_method'] ?? ''); ?>
                        <option value="">-- Not Specified --</option>
                        <option value="CASH" <?= $oldMethod === 'CASH' ? 'selected' : '' ?>>Cash</option>
                        <option value="BANK_TRANSFER" <?= $oldMethod === 'BANK_TRANSFER' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="CHEQUE" <?= $oldMethod === 'CHEQUE' ? 'selected' : '' ?>>Cheque</option>
                        <option value="UPI" <?= $oldMethod === 'UPI' ? 'selected' : '' ?>>UPI</option>
                        <option value="OTHER" <?= $oldMethod === 'OTHER' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <!-- Bank Transfer / Cheque / UPI only; the chosen account is the one the
                     automatic WITHDRAWAL is posted to. -->
                <div class="form-section d-none" id="bankAccountSection">
                    <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                    <select name="bank_account_id" id="bankAccountId" class="form-control">
                        <option value="">-- Select Bank Account --</option>
                        <?php foreach ($bankAccounts as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= (string) ($old['bank_account_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>><?= esc($b['bank_name'] . ' - ' . $b['account_name']) ?> (<?= esc($b['account_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="gp-err d-none" id="err-bank"></div>
                </div>
            </div>

            <div class="gp-actions">
                <button type="submit" class="btn-save w-100 justify-content-center"><i class="bi bi-save"></i> Save General Purchase</button>
                <a href="<?= base_url('general-purchases') ?>" class="btn-cancel w-100 justify-content-center"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </div>
    </div>

</div>

</form>

</div>

<!-- Quick Add Supplier Modal (Release 4.7.2, reused exactly as in Purchases) -->
<div class="modal fade" id="quickAddSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="quickAddSupplierError" class="alert alert-danger d-none" role="alert"></div>
                <form id="quickAddSupplierForm">
                    <div class="form-section">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="qs_name" class="form-control">
                        <div class="invalid-feedback-text text-danger d-none" id="qs_name_error"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-section">
                                <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" name="mobile" id="qs_mobile" class="form-control">
                                <div class="invalid-feedback-text text-danger d-none" id="qs_mobile_error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-section">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="qs_email" class="form-control">
                                <div class="invalid-feedback-text text-danger d-none" id="qs_email_error"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <label class="form-label">GST Number</label>
                        <input type="text" name="gst" id="qs_gst" class="form-control">
                        <div class="invalid-feedback-text text-danger d-none" id="qs_gst_error"></div>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="qs_address" class="form-control"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                <button type="button" class="btn-save" id="quickAddSupplierSaveBtn" onclick="saveQuickAddSupplier()">
                    <span id="quickAddSupplierSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-save" id="quickAddSupplierSaveIcon"></i> Save Supplier
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/gst-calc.js') ?>"></script>
<script>
var products     = <?= json_encode($products) ?>;
var warehouseQty = <?= json_encode($warehouseQty) ?>;
var itemCount    = 0;

function buildProductOptions(selectedId) {
    var opts = '<option value="">-- Select Product --</option>';
    products.forEach(function (p) {
        var sel = (selectedId !== undefined && selectedId !== '' && parseInt(p.id) === parseInt(selectedId)) ? ' selected' : '';
        opts += '<option value="' + p.id + '" data-price="' + (p.selling_price || 0) + '" data-gst="' + (p.gst_percent || 0) + '" data-unit="' + (p.unit || '') + '"' + sel + '>'
              + p.name + '</option>';
    });
    return opts;
}

function toggleEmptyRow() {
    $('#emptyItemsRow').toggle($('.item-row').length === 0);
}

function addItem(prefill) {
    itemCount++;

    var selectedId  = prefill ? prefill.product_id : '';
    var prefillQty  = prefill ? prefill.quantity    : '';
    var prefillRate = prefill ? prefill.rate        : '';
    var prefillGst  = prefill ? prefill.gst_percent : 0;

    var lineResult = gstCalculateLine(prefillQty || 0, prefillRate || 0, prefillGst || 0, true);

    var html = '<tr class="item-row" id="item-' + itemCount + '">' +
            '<td class="c-prod product-cell" data-label="Product">' +
                '<select name="items[' + itemCount + '][product_id]" class="form-control product-select" required>' + buildProductOptions(selectedId) + '</select>' +
            '</td>' +
            '<td class="c-wh" data-label="WH Qty"><span class="warehouse-qty-badge zero">0</span></td>' +
            '<td class="c-qty" data-label="Qty"><input type="number" name="items[' + itemCount + '][quantity]" class="form-control qty-input" min="0.001" step="0.001" value="' + prefillQty + '" required></td>' +
            '<td class="c-rate" data-label="Rate"><input type="number" name="items[' + itemCount + '][rate]" class="form-control rate-input" step="0.01" min="0" value="' + prefillRate + '" required></td>' +
            '<td class="c-gst" data-label="GST %"><input type="number" name="items[' + itemCount + '][gst_percent]" class="form-control gst-percent-input" step="0.01" min="0" value="' + prefillGst + '"></td>' +
            '<td class="c-tot" data-label="Total" title="GST &#8377;' + lineResult.gstAmount.toFixed(2) + '"><span class="line-total">' + lineResult.total.toFixed(2) + '</span><span class="line-gst">GST &#8377;<span class="line-gst-val">' + lineResult.gstAmount.toFixed(2) + '</span></span></td>' +
            '<td class="c-del"><button type="button" class="gp-remove-row" onclick="removeItem(' + itemCount + ')" title="Remove row"><i class="bi bi-x-circle-fill"></i></button></td>' +
        '</tr>';

    $('#itemsContainer').append(html);
    bindRowEvents(itemCount);

    var row = $('#item-' + itemCount);
    row.find('.product-select').select2({ width: '100%', placeholder: 'Select Product', dropdownAutoWidth: true });

    if (selectedId) {
        var qty = warehouseQty[selectedId] !== undefined ? warehouseQty[selectedId] : 0;
        var badge = row.find('.warehouse-qty-badge');
        badge.text(qty);
        badge.toggleClass('zero', parseFloat(qty) <= 0);
    }

    checkDuplicateProducts();
    toggleEmptyRow();
    calcTotal();
}

function removeItem(num) {
    $('#item-' + num).remove();
    checkDuplicateProducts();
    toggleEmptyRow();
    calcTotal();
}

function bindRowEvents(num) {
    var row = $('#item-' + num);

    row.find('.product-select').on('change', function () {
        var selected = $(this).find(':selected');
        var price = parseFloat(selected.attr('data-price')) || 0;
        var gst   = parseFloat(selected.attr('data-gst')) || 0;
        var pid   = $(this).val();

        row.find('.rate-input').val(price.toFixed(2));
        row.find('.gst-percent-input').val(gst);

        var qty = warehouseQty[pid] !== undefined ? warehouseQty[pid] : 0;
        var badge = row.find('.warehouse-qty-badge');
        badge.text(qty);
        badge.toggleClass('zero', parseFloat(qty) <= 0);

        $(this).removeClass('gp-invalid');
        checkDuplicateProducts();
        calcLineTotal(row);
    });

    row.find('.qty-input, .rate-input, .gst-percent-input').on('input', function () {
        $(this).removeClass('gp-invalid');
        calcLineTotal(row);
    });
}

// A duplicate product is flagged with the red border on the second select and one message under the table.
function checkDuplicateProducts() {
    var seen = {};
    var hasDuplicate = false;

    $('.item-row').each(function () {
        var sel = $(this).find('.product-select');
        var pid = sel.val();

        if (pid && seen[pid]) {
            sel.addClass('gp-invalid');
            hasDuplicate = true;
        } else if (sel.data('dupFlag')) {
            sel.removeClass('gp-invalid');
        }
        sel.data('dupFlag', !!(pid && seen[pid]));
        if (pid) seen[pid] = true;
    });

    if (hasDuplicate) {
        showItemsErr('This product is already added in another row.', 'dup');
    } else if ($('#itemsErr').data('kind') === 'dup') {
        showItemsErr('');
    }

    return hasDuplicate;
}

function showItemsErr(msg, kind) {
    $('#itemsErr').data('kind', kind || '').text(msg).toggleClass('d-none', !msg);
}

function calcLineTotal(row) {
    var qty  = parseFloat(row.find('.qty-input').val()) || 0;
    var rate = parseFloat(row.find('.rate-input').val()) || 0;
    var gst  = parseFloat(row.find('.gst-percent-input').val()) || 0;

    var result = gstCalculateLine(qty, rate, gst, true);
    row.find('.line-gst-val').text(result.gstAmount.toFixed(2));
    row.find('.c-tot').attr('title', 'GST ₹' + result.gstAmount.toFixed(2));
    row.find('.line-total').text(result.total.toFixed(2));
    calcTotal();
}

function calcTotal() {
    var lines = [];
    $('.item-row').each(function () {
        var row  = $(this);
        var qty  = parseFloat(row.find('.qty-input').val()) || 0;
        var rate = parseFloat(row.find('.rate-input').val()) || 0;
        var gst  = parseFloat(row.find('.gst-percent-input').val()) || 0;
        lines.push(gstCalculateLine(qty, rate, gst, true));
    });

    var summary = gstSummarize(lines);

    $('#sumSubtotal').text(summary.taxable.toFixed(2));
    $('#sumGstTotal').text(summary.gst.toFixed(2));
    $('#sumGrandTotal').text(summary.grandTotal.toFixed(2));

    updateOutstanding(summary.grandTotal);
}

function updateOutstanding(grandTotal) {
    var advance = parseFloat($('#advancePaid').val()) || 0;

    if (advance > grandTotal) {
        advance = grandTotal;
        $('#advancePaid').val(advance.toFixed(2));
    }

    var outstanding = Math.max(0, grandTotal - advance);
    $('#sumOutstanding').text(outstanding.toFixed(2));
    $('#sumOutstanding').closest('.amt').toggleClass('ok', outstanding <= 0.004);


    // The clamp above can change the advance without firing an input event.
    toggleBankAccount();
}

$('#advancePaid').on('input', function () {
    $(this).removeClass('gp-invalid');
    setErr('advance', '');
    var grandTotal = parseFloat($('#sumGrandTotal').text()) || 0;
    updateOutstanding(grandTotal);
});

$(document).on('click', '#addItemBtn', function () {
    showItemsErr('');
    addItem();
});

// ---- inline validation (server-side validation is unchanged and still authoritative) ----
function setErr(key, msg) {
    $('#err-' + key).text(msg).toggleClass('d-none', !msg);
}

function validateForm() {
    var ok = true;
    $('.gp-invalid').removeClass('gp-invalid');
    ['supplier', 'purchase_date', 'bill_date', 'advance', 'bank'].forEach(function (k) { setErr(k, ''); });
    showItemsErr('');

    if (!$('#supplierSelect').val()) {
        $('#supplierSelect').addClass('gp-invalid'); setErr('supplier', 'Select a supplier.'); ok = false;
    }
    if (!$('#purchaseDate').val()) {
        $('#purchaseDate').addClass('gp-invalid'); setErr('purchase_date', 'Required.'); ok = false;
    }
    if (!$('#billDate').val()) {
        $('#billDate').addClass('gp-invalid'); setErr('bill_date', 'Required.'); ok = false;
    }

    var rows = $('.item-row');
    if (rows.length === 0) {
        showItemsErr('Add at least one product.'); ok = false;
    } else {
        var rowBad = false;
        rows.each(function () {
            var r = $(this);
            var product = r.find('.product-select');
            var qty  = r.find('.qty-input');
            var rate = r.find('.rate-input');
            if (!product.val()) { product.addClass('gp-invalid'); rowBad = true; }
            if (!(parseFloat(qty.val()) > 0)) { qty.addClass('gp-invalid'); rowBad = true; }
            if (rate.val() === '' || parseFloat(rate.val()) < 0) { rate.addClass('gp-invalid'); rowBad = true; }
        });
        if (rowBad) { showItemsErr('Complete the highlighted rows: product, quantity greater than 0, and rate.'); ok = false; }
        if (checkDuplicateProducts()) { ok = false; }
    }

    var advance = parseFloat($('#advancePaid').val()) || 0;
    if (advance < 0) {
        $('#advancePaid').addClass('gp-invalid'); setErr('advance', 'Advance cannot be negative.'); ok = false;
    }
    if (advance > 0 && isBankMethod($('#paymentMethod').val()) && !$('#bankAccountId').val()) {
        $('#bankAccountId').addClass('gp-invalid'); setErr('bank', 'Select the bank account.'); ok = false;
    }

    return ok;
}

$('#gpForm').on('submit', function (e) {
    if (!validateForm()) {
        e.preventDefault();
        var first = $('.gp-invalid').first();
        if (first.length) { first[0].scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    }
});

$('#supplierSelect, #purchaseDate, #billDate, #bankAccountId').on('change input', function () {
    $(this).removeClass('gp-invalid');
    var id = this.id;
    setErr(id === 'supplierSelect' ? 'supplier' : id === 'purchaseDate' ? 'purchase_date' : id === 'billDate' ? 'bill_date' : 'bank', '');
});

// Release 4.8.4H: Payment Method shows only while an advance is entered, and Bank Account only for
// bank-type methods — the same isBankMethod()/toggleBankAccount() pattern Expenses and Supplier
// Payments use, plus the "advance > 0" condition. Bank Account is required while it is shown.
function isBankMethod(method) {
    return ['BANK', 'BANK_TRANSFER', 'CHEQUE', 'UPI'].indexOf(method) !== -1;
}
function toggleBankAccount() {
    var hasAdvance = (parseFloat($('#advancePaid').val()) || 0) > 0;
    var bank = hasAdvance && isBankMethod($('#paymentMethod').val());
    $('#paymentMethodSection').toggleClass('d-none', !hasAdvance);
    $('#bankAccountSection').toggleClass('d-none', !bank);
    $('#bankAccountId').prop('required', bank);
    if (!isBankMethod($('#paymentMethod').val())) { $('#bankAccountId').val('').trigger('change'); }
}
$('#paymentMethod').on('change', toggleBankAccount);
$('#advancePaid').on('input', toggleBankAccount);

// Release 4.8.4H: refill the product rows after the server rejected the form (HTTP 422). Every
// addItem() re-runs calcTotal(), whose clamp would pull the advance down while the total is still
// partial, so the advance is put back once all rows are in.
var oldItems = <?= json_encode($oldItems ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
if (oldItems.length) {
    var oldAdvance = $('#advancePaid').val();
    oldItems.forEach(function (it) {
        addItem(it);
    });
    $('#advancePaid').val(oldAdvance);
}

toggleEmptyRow();
calcTotal();

// Supplier strip: name, GST, and the supplier's open bills (same endpoint the Supplier Payment screen uses)
var stripRequest = 0;
function loadSupplierStrip() {
    var sel = $('#supplierSelect').find(':selected');

    if (!$('#supplierSelect').val()) {
        $('#infoName').text('No supplier selected');
        $('#infoGst').text('-');
        $('#infoBillsCount').text('0');
        $('#infoTotalOutstanding').text('0.00');
        return;
    }

    $('#infoName').text(sel.attr('data-name') || '-');
    $('#infoGst').text(sel.attr('data-gst') ? 'GST ' + sel.attr('data-gst') : 'GST -');
    $('#infoBillsCount').text('…');
    $('#infoTotalOutstanding').text('…');

    var ticket = ++stripRequest;
    $.ajax({
        url: "<?= base_url('supplier-payments/get-bills') ?>",
        type: 'POST',
        data: { supplier_id: $('#supplierSelect').val() },
        dataType: 'json'
    }).done(function (bills) {
        if (ticket !== stripRequest) return;
        // General Purchase bills only (project bills are not counted here).
        bills = (bills || []).filter(function (b) { return b.purchase_type === 'GENERAL'; });
        var total = 0;
        bills.forEach(function (b) { total += parseFloat(b.outstanding_amount) || 0; });
        $('#infoBillsCount').text(bills.length);
        $('#infoTotalOutstanding').text(total.toFixed(2));
    }).fail(function () {
        if (ticket !== stripRequest) return;
        $('#infoBillsCount').text('-');
        $('#infoTotalOutstanding').text('-');
    });
}
$('#supplierSelect').on('change', loadSupplierStrip);

// A supplier restored after a 422 (or preselected on edit) needs its strip filled on load.
if ($('#supplierSelect').val()) { loadSupplierStrip(); }

function resetQuickAddSupplierForm() {
    $('#quickAddSupplierForm')[0].reset();
    $('#quickAddSupplierModal .invalid-feedback-text').addClass('d-none').text('');
    $('#quickAddSupplierModal .form-control').removeClass('is-invalid');
    $('#quickAddSupplierError').addClass('d-none').text('');
}

$('#quickAddSupplierModal').on('hidden.bs.modal', resetQuickAddSupplierForm);

function showQuickAddSupplierToast(message) {
    var toast = $('<div class="quick-add-toast"></div>').text(message);
    $('body').append(toast);
    setTimeout(function () { toast.addClass('show'); }, 10);
    setTimeout(function () {
        toast.removeClass('show');
        setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
}

function saveQuickAddSupplier() {
    var btn = $('#quickAddSupplierSaveBtn');
    var spinner = $('#quickAddSupplierSpinner');
    var errorBanner = $('#quickAddSupplierError');

    $('#quickAddSupplierModal .invalid-feedback-text').addClass('d-none').text('');
    $('#quickAddSupplierModal .form-control').removeClass('is-invalid');
    errorBanner.addClass('d-none').text('');

    btn.prop('disabled', true);
    spinner.removeClass('d-none');

    $.ajax({
        url: "<?= base_url('suppliers/ajax-store') ?>",
        type: "POST",
        data: $('#quickAddSupplierForm').serialize(),
        dataType: 'json'
    }).done(function (data) {
        if (data.status) {
            var select = $('#supplierSelect');
            var opt = new Option(data.supplier.name, data.supplier.id, true, true);
            $(opt).attr('data-name', data.supplier.name || '');
            $(opt).attr('data-gst', $('#qs_gst').val() || '');
            select.append(opt).trigger('change');

            bootstrap.Modal.getInstance(document.getElementById('quickAddSupplierModal')).hide();
            showQuickAddSupplierToast(data.message || 'Supplier Added Successfully.');
        } else if (data.errors) {
            Object.keys(data.errors).forEach(function (field) {
                $('#qs_' + field).addClass('is-invalid');
                $('#qs_' + field + '_error').text(data.errors[field]).removeClass('d-none');
            });
        } else {
            errorBanner.text(data.message || 'Unable to save supplier.').removeClass('d-none');
        }
    }).fail(function (xhr) {
        var data = xhr.responseJSON;
        if (data && data.errors) {
            Object.keys(data.errors).forEach(function (field) {
                $('#qs_' + field).addClass('is-invalid');
                $('#qs_' + field + '_error').text(data.errors[field]).removeClass('d-none');
            });
        } else if (data && data.message) {
            errorBanner.text(data.message).removeClass('d-none');
        } else {
            errorBanner.text('A network error occurred. Please try again.').removeClass('d-none');
        }
    }).always(function () {
        btn.prop('disabled', false);
        spinner.addClass('d-none');
    });
}
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
