<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
/* Release 4.8.6E-1: compact Supplier Payment entry screen (Tally/Zoho-style voucher
   entry). Controls are 32px on this page only, so the four mandated voucher rows
   (header / supplier / 48px summary strip / reference+remarks) fit in one short card.
   Grid areas: >=1200px = content 2fr | summary 1fr (sticky); 768-1199px =
   voucher, summary, bills; <768px = voucher, bills, summary. */
.sp-page { --input-height: 32px; }
.sp-page .page-title { margin-bottom: 10px; }
.sp-page .card-custom { margin-bottom: 0; }
.sp-page .card-custom-header { padding: 7px 12px; }
.sp-page .card-custom-body { padding: 9px 12px; }

.sp-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas: "voucher" "summary" "bills";
    gap: 14px;
    align-items: start;
}
.sp-voucher { grid-area: voucher; }
.sp-bills   { grid-area: bills; }
.sp-summary { grid-area: summary; }
@media (max-width: 767.98px) {
    .sp-layout { grid-template-areas: "voucher" "bills" "summary"; }
}
@media (min-width: 1200px) {
    .sp-layout {
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        grid-template-areas: "voucher summary" "bills summary";
        grid-template-rows: auto 1fr; /* extra summary height goes below the bills card, not between the cards */
    }
    .sp-summary { position: sticky; top: 72px; max-height: calc(100vh - 88px); overflow-y: auto; }
}

/* Select2 sized to match the 36px form controls used on this page */
.sp-page .select2-container--default .select2-selection--single {
    height: var(--input-height);
    border: 1px solid #d1d5db;
    border-radius: var(--border-radius);
}
.sp-page .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: calc(var(--input-height) - 2px);
    font-size: var(--font-size-sm);
    padding-left: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sp-page .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(var(--input-height) - 2px); }

/* Compact voucher header: tight labels, four fields on one row */
.sp-page .form-label { font-size: .7rem; font-weight: 600; margin-bottom: 2px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .02em; }
.sp-page .form-section { margin-bottom: 5px; }
.sp-page .form-section:last-child { margin-bottom: 0; }
.sp-hdr-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 10px;
}
@container sp-voucher-body (min-width: 560px) {
    .sp-hdr-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
}
.sp-page .sp-hdr-grid .form-section { min-width: 0; margin-bottom: 5px; }

/* Supplier row: 85% dropdown / 15% add button */
.sp-supplier-grid { margin-bottom: 6px; }
.sp-supplier-pick { display: flex; gap: 8px; }
.sp-supplier-pick .sp-sel { flex: 0 0 calc(85% - 4px); min-width: 0; }
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

/* Supplier summary: ONE compact 48px information strip (profile details live on the Supplier page) */
.sp-strip {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(0, .5fr) minmax(0, 1fr) minmax(0, 1fr);
    align-items: center;
    gap: 2px 12px;
    min-height: 58px;
    padding: 5px 12px;
    margin-bottom: 6px;
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
}
.sp-strip .st-item { display: flex; flex-direction: column; min-width: 0; }
.sp-strip .st-item > span { font-size: .62rem; line-height: 1.2; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
.sp-strip .st-item > b { font-size: .86rem; line-height: 1.3; color: var(--text-dark); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sp-strip .st-name > b { font-size: .95rem; font-weight: 700; }
.sp-strip .st-name > span.gst { font-size: .68rem; text-transform: none; letter-spacing: 0; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sp-strip .st-due > b { color: #c2410c; }
.sp-strip .st-adv > b { color: #166534; }
@media (max-width: 575.98px) {
    .sp-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.sp-ref-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 0 10px; }
.sp-page textarea.form-control { min-height: 0; height: auto; padding: 5px 10px; font-size: .8rem; line-height: 1.35; resize: vertical; }
@container sp-voucher-body (max-width: 460px) {
    .sp-ref-grid { grid-template-columns: minmax(0, 1fr); }
}
.sp-voucher .card-custom-body { container-type: inline-size; container-name: sp-voucher-body; }

/* Outstanding bills: 7 columns, sized to fit the card with no horizontal scroll */
.bills-table-wrap { container-type: inline-size; container-name: sp-bills; }
#billsTable { width: 100%; table-layout: fixed; }
#billsTable thead th { padding: 6px 8px; font-size: .66rem; letter-spacing: .02em; white-space: nowrap; }
#billsTable td { padding: 3px 8px; vertical-align: middle; font-size: .8rem; }
#billsTable tr.bill-row { height: 41px; }
#billsTable .c-chk { width: 30px; padding-right: 0; }
#billsTable .c-date { width: 88px; }
#billsTable .c-bill { width: 92px; }
#billsTable .c-paid { width: 80px; }
#billsTable .c-out { width: 96px; }
#billsTable .c-pay { width: 100px; }
#billsTable .c-date, #billsTable .c-amt { white-space: nowrap; }
#billsTable .c-amt, #billsTable th.c-amt, #billsTable .c-pay, #billsTable th.c-pay { text-align: right; font-variant-numeric: tabular-nums; }
#billsTable .c-no .bill-no { font-weight: 700; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#billsTable td.c-out { font-weight: 700; color: #c2410c; }
#billsTable input.pay-now-input { width: 100%; height: 30px; padding: 0 6px; text-align: right; font-size: .8rem; }
#billsTable tr.row-fully-allocated { background: #f0fdf4; }
#billsTable tr.row-fully-allocated td { color: #166534; }
#billsTable tr.row-fully-allocated td.c-out { color: #166534; }
.bills-empty-row td { text-align: center; color: #94a3b8; padding: 20px 12px; font-size: .85rem; }

/* Phones and any narrow card (tablet with the sidebar open): one bill per stacked block */
@media (max-width: 767.98px) {
    #billsTable thead { display: none; }
    #billsTable, #billsTable tbody { display: block; width: 100%; }
    #billsTable tr.bill-row { display: block; height: auto; padding: 8px 2px; border-bottom: 1px solid var(--border-color); }
    #billsTable tr.bill-row td { display: flex; justify-content: space-between; align-items: center; width: auto; padding: 2px 6px; }
    #billsTable tr.bill-row td::before { content: attr(data-label); font-size: .7rem; color: var(--text-muted); }
    #billsTable tr.bill-row td.c-no { flex-direction: column; align-items: flex-start; order: -1; font-size: .85rem; }
    #billsTable tr.bill-row td.c-no::before { content: none; }
    #billsTable tr.bill-row td.c-pay input { width: 100%; max-width: 130px; }
    #billsTable .bills-empty-row, #billsTable .bills-empty-row td { display: block; width: auto; }
}
@container sp-bills (max-width: 540px) {
    #billsTable thead { display: none; }
    #billsTable, #billsTable tbody { display: block; width: 100%; }
    #billsTable tr.bill-row { display: block; height: auto; padding: 8px 2px; border-bottom: 1px solid var(--border-color); }
    #billsTable tr.bill-row td { display: flex; justify-content: space-between; align-items: center; width: auto; padding: 2px 6px; }
    #billsTable tr.bill-row td::before { content: attr(data-label); font-size: .7rem; color: var(--text-muted); }
    #billsTable tr.bill-row td.c-no { flex-direction: column; align-items: flex-start; order: -1; font-size: .85rem; }
    #billsTable tr.bill-row td.c-no::before { content: none; }
    #billsTable tr.bill-row td.c-pay input { width: 100%; max-width: 130px; }
    #billsTable .bills-empty-row, #billsTable .bills-empty-row td { display: block; width: auto; }
}

/* Payment summary: three sections + one total */
.sp-sec { padding: 6px 0; border-bottom: 1px dashed var(--border-color); }
.sp-sec:first-child { padding-top: 0; }
.sp-sec-title { font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; }
.sp-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; font-size: .84rem; padding: 2px 0; }
.sp-row .amt { font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
.sp-row.due .amt { color: #c2410c; }
.sp-row.adv .amt { color: #166534; }
.sp-row.cash .amt { color: var(--primary); }
.sp-check { display: flex; align-items: flex-start; gap: 8px; margin: 0; font-size: .82rem; font-weight: 600; color: var(--text-dark); cursor: pointer; }
.sp-check input { margin: 2px 0 0; cursor: pointer; flex: 0 0 auto; }
.sp-help { font-size: .7rem; color: var(--text-muted); line-height: 1.3; margin-top: 3px; }
.sp-adv-card { margin-top: 6px; padding: 8px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--border-radius); }
.sp-adv-q { font-size: .74rem; color: #166534; line-height: 1.3; margin-bottom: 5px; }
.sp-adv-choice { display: flex; gap: 18px; margin-bottom: 6px; }
.sp-adv-note { font-size: .74rem; font-weight: 600; color: #166534; line-height: 1.3; margin-bottom: 6px; }
#advanceWrap { margin-top: 6px; }
.sp-total { padding: 6px 0 0; }
.sp-total .sp-row.grand { margin-top: 2px; padding-top: 8px; border-top: 1px solid var(--border-color); font-size: 1.25rem; font-weight: 700; color: var(--primary-dark); }
.sp-total .sp-row.grand > span:first-child { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
.sp-err { color: #dc2626; font-size: .74rem; margin-top: 3px; line-height: 1.3; }
.sp-page .sp-invalid { border-color: #dc2626 !important; }
.sp-page select.sp-invalid + .select2-container--default .select2-selection--single { border-color: #dc2626; }
#sumHint { text-align: center; margin: 6px 0 0; }
.sp-actions { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; margin-top: 8px; }
.sp-pay-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 2px; }
.sp-pay-row input { max-width: 140px; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }

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
.quick-add-toast.error { background: #dc2626; }

.sp-breadcrumb { margin-bottom: 8px; }
.sp-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
.sp-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
.sp-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
.sp-breadcrumb .breadcrumb-item.active { color: #64748b; }
</style>

<div class="sp-page">

<nav aria-label="breadcrumb" class="sp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item"><a href="<?= base_url('supplier-payments') ?>">Supplier Payments</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit <?= esc($payment['payment_no']) ?></li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-wallet2 me-2"></i>Edit Supplier Payment — <?= esc($payment['payment_no']) ?></span>
    <a href="<?= base_url('supplier-payments') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div id="formErrorBanner" class="alert alert-danger d-none" role="alert"></div>

<form id="voucherForm">

<div class="sp-layout">

    <!-- Voucher Information: voucher/date/method/bank, supplier, one summary strip, reference/remarks -->
    <div class="card-custom sp-voucher">
        <div class="card-custom-header">Voucher Information</div>
        <div class="card-custom-body">
            <div class="sp-hdr-grid">
                <div class="form-section">
                    <label class="form-label">Voucher No</label>
                    <input type="text" class="form-control" value="<?= esc($payment['payment_no']) ?>" readonly>
                </div>
                <div class="form-section">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" id="paymentDate" class="form-control" value="<?= esc($payment['payment_date']) ?>" required>
                </div>
            </div>

            <div class="sp-supplier-grid">
                <label class="form-label">Supplier <span class="text-danger">*</span></label>
                <div class="sp-supplier-pick">
                    <div class="sp-sel">
                        <select id="supplierSelect" class="form-control" required>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                data-name="<?= esc($s['name'] ?? '') ?>"
                                data-gst="<?= esc($s['gst'] ?? '') ?>"
                                <?= (int) $s['id'] === (int) $payment['supplier_id'] ? 'selected' : '' ?>>
                                <?= esc($s['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn-quick-add-supplier" data-bs-toggle="modal" data-bs-target="#quickAddSupplierModal" title="Add New Supplier">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <div class="sp-err d-none" id="err-supplier"></div>
            </div>

            <!-- Supplier summary: one compact strip -->
            <div class="sp-strip" id="supplierStrip">
                <div class="st-item st-name"><b id="infoName">No supplier selected</b><span class="gst" id="infoGst">-</span></div>
                <div class="st-item"><span>Bills</span><b id="infoBillsCount">0</b></div>
                <div class="st-item st-due"><span>Outstanding</span><b>&#8377;<span id="infoTotalOutstanding">0.00</span></b></div>
                <div class="st-item st-adv"><span>Advance</span><b>&#8377;<span id="infoAdvAvail">0.00</span></b></div>
            </div>

            <div class="sp-ref-grid">
                <div class="form-section">
                    <label class="form-label">Reference Number</label>
                    <input type="text" id="referenceNo" class="form-control" value="<?= esc($payment['reference_no']) ?>">
                </div>
                <div class="form-section">
                    <label class="form-label">Remarks</label>
                    <textarea id="remarks" class="form-control" rows="2"><?= esc($payment['remarks']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Outstanding Bills -->
    <div class="card-custom sp-bills">
        <div class="card-custom-header">Outstanding Bills</div>
        <div class="card-custom-body">
            <div class="bills-table-wrap">
                <table class="table-custom" id="billsTable">
                    <thead>
                        <tr>
                            <th class="c-chk"></th>
                            <th class="c-no">Bill No</th>
                            <th class="c-date">Date</th>
                            <th class="c-amt c-bill">Bill Amount</th>
                            <th class="c-amt c-paid">Paid</th>
                            <th class="c-amt c-out">Outstanding</th>
                            <th class="c-pay">Pay Today</th>
                        </tr>
                    </thead>
                    <tbody id="billsContainer">
                        <tr class="bills-empty-row" id="billsEmptyRow">
                            <td colspan="7">Loading outstanding bills...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Summary (sticky on wide screens) -->
    <div class="card-custom sp-summary">
        <div class="card-custom-header">Payment Summary</div>
        <div class="card-custom-body">

            <!-- C. Supplier advance reminder. Rendered only when the supplier has
                 unused advance, and answered YES by default so the accountant is
                 always reminded before paying cash a second time. There is no
                 manual amount box: the answer alone decides how much is used. -->
            <div class="sp-sec d-none" id="advSection">
                <div class="sp-sec-title">Supplier Advance Available</div>
                <div class="sp-adv-card">
                    <div class="sp-adv-note">&#8377;<span id="advNoteAmt">0.00</span> Available</div>
                    <div class="sp-adv-choice">
                        <label class="sp-check"><input type="radio" name="useAdvanceChoice" id="useAdvanceYes" value="1" checked> Use Supplier Advance</label>
                        <label class="sp-check"><input type="radio" name="useAdvanceChoice" id="useAdvanceNo" value="0"> Don't Use Advance</label>
                    </div>
                </div>
            </div>

            <!-- D. BILL SUMMARY -->
            <div class="sp-sec">
                <div class="sp-sec-title">Bill Summary</div>
                <div class="sp-row"><span>Selected Bills</span><span class="amt" id="sumSelectedBills">0</span></div>
                <div class="sp-row due"><span>Bill Outstanding</span><span class="amt">&#8377;<span id="sumBillOutstanding">0.00</span></span></div>
                <div class="sp-row adv"><span>Advance Used</span><span class="amt">&#8377;<span id="advUsedAmt">0.00</span></span></div>
                <div class="sp-row due"><span>Outstanding After Advance</span><span class="amt">&#8377;<span id="sumOutAfterAdvance">0.00</span></span></div>
            </div>

            <!-- D. CASH / BANK PAYMENT — how the rest is actually paid. Method and
                 bank account live here (not in the voucher header) because they
                 describe this section's money and nothing else. -->
            <div class="sp-sec">
                <div class="sp-sec-title">Cash / Bank Payment</div>
                <div class="form-section">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select id="paymentMethod" class="form-control">
                        <option value="">-- Select --</option>
                        <?php foreach (['Cash', 'Bank Transfer', 'Cheque', 'UPI'] as $method): ?>
                            <option value="<?= $method ?>" <?= $payment['payment_method'] === $method ? 'selected' : '' ?>><?= $method ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="sp-err d-none" id="err-method"></div>
                </div>
                <!-- Bank Account applies to Bank Transfer / Cheque / UPI only. Saving reverses
                the previous withdrawal and posts a new one against whichever account is
                chosen here. -->
                <div class="form-section d-none" id="bankAccountSection">
                    <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                    <select id="bankAccountId" class="form-control">
                        <option value="">-- Select --</option>
                        <?php foreach ($bankAccounts as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= (int) $selectedBankAccountId === (int) $b['id'] ? 'selected' : '' ?>><?= esc($b['bank_name'] . ' - ' . $b['account_name']) ?> (<?= esc($b['account_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="sp-err d-none" id="err-bank"></div>
                </div>
                <div class="sp-pay-row">
                    <label class="form-label mb-0" for="cashAmount">Payment Amount</label>
                    <input type="number" id="cashAmount" class="form-control" step="0.01" min="0" value="0">
                </div>
                <label class="sp-check mt-2"><input type="checkbox" id="payAdvance" <?= (float) $payment['advance_amount'] > 0 ? 'checked' : '' ?>> Pay Advance to Supplier</label>
                <div id="advanceWrap" class="d-none">
                    <label class="form-label" for="advanceAmount">Advance Amount</label>
                    <input type="number" id="advanceAmount" class="form-control" step="0.01" min="0" value="<?= esc($payment['advance_amount']) ?>" disabled>
                    <div class="sp-err d-none" id="err-advance"></div>
                </div>
            </div>

            <div class="sp-total">
                <div class="sp-row grand"><span>Total Payment</span><span class="amt">&#8377;<span id="sumTotalPayment">0.00</span></span></div>
                <div class="sp-help" id="sumHint">Select bills or enter supplier advance.</div>
            </div>

            <div class="sp-actions">
                <button type="submit" class="btn-save w-100 justify-content-center" id="saveVoucherBtn" disabled>
                    <span id="saveVoucherSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-save"></i> Update Payment
                </button>
                <a href="<?= base_url('supplier-payments') ?>" class="btn-cancel w-100 justify-content-center"><i class="bi bi-x"></i> Cancel</a>
            </div>
        </div>
    </div>

</div>

</form>

</div>

<!-- Quick Add Supplier Modal (Release 4.7.2, reused exactly as in General Purchase) -->
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
<script>
var baseUrl = "<?= base_url() ?>";
var paymentId = <?= (int) $payment['id'] ?>;
// Release 4.8.6D: previously paid supplier advance this voucher applies to its bills.
var restoreUsed = <?= json_encode((float) ($advanceUsed ?? 0)) ?>;
var advAvail = 0;       // unused supplier advance available to this voucher
var supOutstanding = 0; // total outstanding of the loaded supplier bills

// This voucher's own existing allocations, enriched with purchase_no/bill_no/
// purchase_date by the controller's read-only _billLabel() helper. Since
// ajaxBills() only returns bills with LIVE outstanding > 0 — and a bill this
// voucher fully paid now shows 0 there — these rows are merged in client-side
// rather than expecting the (unchanged) ajaxBills() endpoint to know about
// "this voucher's own contribution". outstanding-for-editing is derived
// entirely from data already on the allocation row: balance_amount (the
// outstanding AFTER this payment) + paid_amount (this voucher's own paid) =
// what the bill's outstanding was before this voucher touched it.
var existingAllocations = <?= json_encode($allocations) ?>;

function money(n) { return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2); }
// Display only (grouped, e.g. 40,007.90). Inputs and the posted payload always use money().
function fmt(n) { return Number(money(n)).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }

function showToast(message, isError) {
    var toast = $('<div class="quick-add-toast"></div>').addClass(isError ? 'error' : '').text(message);
    $('body').append(toast);
    setTimeout(function () { toast.addClass('show'); }, 10);
    setTimeout(function () {
        toast.removeClass('show');
        setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
}

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
}

function toggleEmptyBillsRow(message) {
    var hasRows = $('.bill-row').length > 0;
    if (!hasRows) {
        $('#billsEmptyRow td').text(message || 'No outstanding bills for this supplier.');
    }
    $('#billsEmptyRow').toggle(!hasRows);
}

// Bill No only — the bill's type and purchase number stay on the row's tooltip
// (and in the posted allocation), so the grid keeps to the seven useful columns.
function billRowHtml(bill) {
    var billNo = bill.bill_no || bill.purchase_no || '-';
    var title = (bill.purchase_type === 'PROJECT' ? 'Project Purchase' : 'General Purchase') + (bill.purchase_no ? ' - ' + bill.purchase_no : '');
    var checked = bill.pay_now > 0 ? 'checked' : '';
    var disabled = bill.pay_now > 0 ? '' : 'disabled';
    return '<tr class="bill-row" data-type="' + bill.purchase_type + '" data-id="' + bill.purchase_id + '" data-outstanding="' + bill.outstanding_amount + '">' +
        '<td class="c-chk" data-label="Select"><input type="checkbox" class="bill-checkbox" ' + checked + '></td>' +
        '<td class="c-no"><span class="bill-no" title="' + escapeHtml(title) + '">' + escapeHtml(billNo) + '</span></td>' +
        '<td class="c-date" data-label="Date">' + escapeHtml(bill.purchase_date || '-') + '</td>' +
        '<td class="c-amt c-bill" data-label="Bill Amount">' + fmt(bill.bill_amount) + '</td>' +
        '<td class="c-amt c-paid" data-label="Paid">' + fmt(bill.paid_amount) + '</td>' +
        '<td class="c-amt c-out" data-label="Outstanding">' + fmt(bill.outstanding_amount) + '</td>' +
        '<td class="c-pay" data-label="Pay Today"><input type="number" class="form-control pay-now-input" step="0.01" min="0" max="' + bill.outstanding_amount + '" value="' + money(bill.pay_now) + '" ' + disabled + '></td>' +
    '</tr>';
}

function mergeBills(freshBills) {
    var merged = [];
    var seen = {};

    existingAllocations.forEach(function (a) {
        var outstandingForEditing = Number(a.balance_amount) + Number(a.paid_amount);
        var paidByOthers = Number(a.bill_amount) - outstandingForEditing;
        var key = a.purchase_type + '-' + a.purchase_id;
        seen[key] = true;

        merged.push({
            purchase_type: a.purchase_type,
            purchase_id: a.purchase_id,
            purchase_no: a.purchase_no,
            bill_no: a.bill_no,
            purchase_date: a.purchase_date,
            bill_amount: Number(a.bill_amount),
            paid_amount: paidByOthers,
            outstanding_amount: outstandingForEditing,
            pay_now: Number(a.paid_amount)
        });
    });

    (freshBills || []).forEach(function (b) {
        var key = b.purchase_type + '-' + b.purchase_id;
        if (seen[key]) return;
        merged.push({
            purchase_type: b.purchase_type,
            purchase_id: b.purchase_id,
            purchase_no: b.purchase_no,
            bill_no: b.bill_no,
            purchase_date: b.purchase_date,
            bill_amount: Number(b.bill_amount),
            paid_amount: Number(b.paid_amount),
            outstanding_amount: Number(b.outstanding_amount),
            pay_now: 0
        });
    });

    merged.sort(function (a, b) { return (a.purchase_date || '').localeCompare(b.purchase_date || ''); });

    return merged;
}

function renderBillsTable(bills) {
    $('#billsContainer .bill-row').remove();

    bills.forEach(function (bill) {
        $('#billsContainer').append(billRowHtml(bill));
    });

    $('.bill-row').each(function () {
        updateRowRemaining($(this));
    });

    bindBillRowEvents();
    toggleEmptyBillsRow();
    updateSupplierInfoCounts(bills);
    recalcSummary();
}

function bindBillRowEvents() {
    $('.bill-checkbox').off('change').on('change', function () {
        var row = $(this).closest('tr');
        var input = row.find('.pay-now-input');
        var outstanding = parseFloat(row.data('outstanding')) || 0;

        if ($(this).is(':checked')) {
            input.prop('disabled', false).val(outstanding.toFixed(2));
        } else {
            input.prop('disabled', true).val(0);
        }
        updateRowRemaining(row);
        recalcSummary();
    });

    $('.pay-now-input').off('input').on('input', function () {
        var row = $(this).closest('tr');
        var outstanding = parseFloat(row.data('outstanding')) || 0;
        var val = parseFloat($(this).val()) || 0;

        if (val < 0) val = 0;
        if (val > outstanding) val = outstanding;
        $(this).val(val);

        updateRowRemaining(row);
        recalcSummary();
    });
}

function updateRowRemaining(row) {
    var outstanding = parseFloat(row.data('outstanding')) || 0;
    var payNow = parseFloat(row.find('.pay-now-input').val()) || 0;
    var remaining = Math.max(0, outstanding - payNow);

    row.toggleClass('row-fully-allocated', row.find('.bill-checkbox').is(':checked') && remaining <= 0.004);
}

function updateSupplierInfoCounts(bills) {
    var totalOutstanding = 0;
    bills.forEach(function (b) { totalOutstanding += Number(b.outstanding_amount); });
    supOutstanding = totalOutstanding;
    $('#infoBillsCount').text(bills.length);
    $('#infoTotalOutstanding').text(fmt(totalOutstanding));
}

function collectAllocations() {
    var allocations = [];
    $('.bill-row').each(function () {
        var row = $(this);
        if (row.find('.bill-checkbox').is(':checked')) {
            var payNow = parseFloat(row.find('.pay-now-input').val()) || 0;
            if (payNow > 0) {
                allocations.push({
                    purchase_type: row.data('type'),
                    purchase_id: row.data('id'),
                    paid_amount: payNow
                });
            }
        }
    });
    return allocations;
}

// The advance payment is only part of the voucher while "Pay Advance to Supplier" is ticked.
function getAdvance() {
    if (!$('#payAdvance').is(':checked')) { return 0; }
    var advance = parseFloat($('#advanceAmount').val()) || 0;
    if (advance < 0) { advance = 0; $('#advanceAmount').val(0); }
    return round2(advance);
}

// Release 4.8.6E-1: no partial advance. While the reminder checkbox is ticked the
// voucher always consumes the maximum possible advance:
//     advance used = MIN(selected bill outstanding, advance available)
function useAdvanceOn() { return $('#useAdvanceYes').is(':checked'); }
function advanceUsedFor(allocated) {
    if (!useAdvanceOn()) { return 0; }
    return round2(Math.max(0, Math.min(advAvail, allocated)));
}
function getAdvanceUsed() {
    var selected = 0;
    collectAllocations().forEach(function (a) { selected += a.paid_amount; });
    return advanceUsedFor(selected);
}

// Field-level validation messages, shown directly below the field.
var errFields = { supplier: '#supplierSelect', method: '#paymentMethod', bank: '#bankAccountId', advance: '#advanceAmount' };
var advanceTouched = false;
function setFieldError(key, message) {
    $('#err-' + key).text(message || '').toggleClass('d-none', !message);
    $(errFields[key]).toggleClass('sp-invalid', !!message);
}
function clearFieldErrors() {
    Object.keys(errFields).forEach(function (key) { setFieldError(key, ''); });
}
// A ticked "Pay Advance to Supplier" needs an amount greater than zero.
function advanceInvalid() {
    return $('#payAdvance').is(':checked') && (parseFloat($('#advanceAmount').val()) || 0) <= 0;
}

// Supplier advance available to this voucher. On the edit page the default is OFF
// and restoreApplied() re-ticks it only when this voucher already used advance.
function setAdvAvail(value, defaultOn) {
    advAvail = Math.max(0, round2(value));
    var has = advAvail > 0.004;

    $('#advSection').toggleClass('d-none', !has);
    $('#advNoteAmt').text(fmt(advAvail));
    $('#infoAdvAvail').text(fmt(advAvail));

    if (!has) { $('#useAdvanceNo').prop('checked', true); }
    else if (defaultOn === true) { $('#useAdvanceYes').prop('checked', true); }

    recalcSummary();
}

function toggleAdvance(focus) {
    var on = $('#payAdvance').is(':checked');
    $('#advanceWrap').toggleClass('d-none', !on);
    $('#advanceAmount').prop('disabled', !on);
    if (!on) { advanceTouched = false; }
    if (on && focus) { $('#advanceAmount').trigger('focus').trigger('select'); }
    recalcSummary();
}

function recalcSummary() {
    var allocations = collectAllocations();
    var allocated = round2(allocations.reduce(function (sum, a) { return sum + a.paid_amount; }, 0));
    var advance = getAdvance();

    // Bill allocations are the full settlement of each bill; the part funded by a
    // previously paid advance moves no money, so cash/bank = allocated - used.
    var used = advanceUsedFor(allocated);
    var cash = round2(allocated - used);
    var totalPayment = round2(cash + advance);

    var billOut = 0;
    $('.bill-row').each(function () {
        var r = $(this);
        if (r.find('.bill-checkbox').is(':checked')) {
            billOut += parseFloat(r.data('outstanding')) || 0;
        }
    });

    $('#sumSelectedBills').text($('.bill-checkbox:checked').length);
    $('#sumBillOutstanding').text(fmt(billOut));
    $('#advUsedAmt').text(fmt(used));
    // Release 4.8.6F-1: what the selected bills still owe once the advance is
    // applied — the figure Payment Amount defaults to. Trimming Payment Amount
    // for a partial payment deliberately does not move it.
    $('#sumOutAfterAdvance').text(fmt(Math.max(0, billOut - used)));
    $('#advRemaining').text(fmt(advAvail - used));
    // Release 4.8.6E: Payment Amount is an editable control that always mirrors the
    // cash part of the voucher. Never rewritten while the accountant is typing in it.
    if (document.activeElement !== document.getElementById('cashAmount')) {
        $('#cashAmount').val(money(cash));
    }
    $('#sumTotalPayment').text(fmt(totalPayment));

    var supplierSelected = !!$('#supplierSelect').val();
    var idle = !supplierSelected || (allocated <= 0 && advance <= 0);
    var badAdvance = advanceInvalid();

    $('#sumHint').toggleClass('d-none', !idle);
    $('#saveVoucherBtn').prop('disabled', idle || badAdvance);
    setFieldError('advance', badAdvance && advanceTouched ? 'Advance amount must be greater than zero.' : '');
}

function loadOutstandingBills(supplierId, defaultOn) {
    $.ajax({
        url: baseUrl + 'supplier-payments/get-bills',
        type: 'POST',
        data: { supplier_id: supplierId, with_advance: 1, exclude_payment_id: paymentId },
        dataType: 'json'
    }).done(function (resp) {
        renderBillsTable(mergeBills((resp && resp.bills) || []));
        setAdvAvail(resp && resp.advance_available, defaultOn);
        restoreApplied();
    }).fail(function () {
        renderBillsTable(mergeBills([]));
        setAdvAvail(restoreUsed, defaultOn);
        restoreApplied();
        toggleEmptyBillsRow('Failed to load additional outstanding bills — previously selected bills are still shown.');
    });
}

// Restores the advance answer this voucher was saved with (first load only), so
// re-saving an untouched voucher never changes how it was funded.
function restoreApplied() {
    if (restoreUsed > 0) {
        $('#useAdvanceYes').prop('checked', true);
        recalcSummary();
    }
    restoreUsed = 0;
}

function clearBillsTable() {
    existingAllocations = [];
    $('.bill-row').remove();
    toggleEmptyBillsRow('Select a supplier to load outstanding bills.');
    supOutstanding = 0;
    $('#infoBillsCount').text(0);
    $('#infoTotalOutstanding').text('0.00');
    recalcSummary();
}

// Release 4.8.6E: partial payment. Lowering Payment Amount re-spreads the money over
// the ticked bills (oldest first) and writes it back into Pay Today, so the grid and
// the summary can never disagree. Advance is always consumed before cash, so the
// bills settled today = advance used + payment amount.
function distributeCash(cashWanted) {
    var rows = $('.bill-row').filter(function () { return $(this).find('.bill-checkbox').is(':checked'); });
    var ceiling = 0;
    rows.each(function () { ceiling += parseFloat($(this).data('outstanding')) || 0; });

    var funds = round2(useAdvanceOn() ? Math.min(advAvail, ceiling) + cashWanted : cashWanted);
    var left = round2(Math.min(ceiling, Math.max(0, funds)));

    rows.each(function () {
        var row = $(this);
        var take = round2(Math.min(parseFloat(row.data('outstanding')) || 0, left));
        left = round2(left - take);
        row.find('.pay-now-input').val(money(take));
        updateRowRemaining(row);
    });
    recalcSummary();
}

// Answering the advance reminder re-offers each ticked bill in full; the accountant
// then trims Payment Amount if only part of it is being paid today.
function resetAllocationsToFull() {
    $('.bill-row').each(function () {
        var row = $(this);
        if (row.find('.bill-checkbox').is(':checked')) {
            row.find('.pay-now-input').val(money(parseFloat(row.data('outstanding')) || 0));
            updateRowRemaining(row);
        }
    });
}

$('#cashAmount').on('input', function () {
    var val = parseFloat($(this).val());
    distributeCash(isNaN(val) || val < 0 ? 0 : round2(val));
});
$('#cashAmount').on('blur change', function () { recalcSummary(); });
$('#useAdvanceYes, #useAdvanceNo').on('change', function () { resetAllocationsToFull(); recalcSummary(); });
$('#advanceAmount').on('input blur', function () { advanceTouched = true; recalcSummary(); });
$('#payAdvance').on('change', function () { toggleAdvance(true); });

// Moving the voucher to another supplier drops this voucher's own allocations and
// loads that supplier's bills, with the advance reminder defaulted ON as on Create.
$('#supplierSelect').on('change', function () {
    var selected = $(this).find(':selected');
    var gst = selected.attr('data-gst') || '';
    var supplierId = $(this).val();
    setFieldError('supplier', '');
    restoreUsed = 0;
    existingAllocations = [];
    setAdvAvail(0);

    $('#infoName').text(selected.attr('data-name') || '-');
    $('#infoGst').text(gst || '-').attr('title', gst);

    if (!supplierId) {
        $('#infoName').text('No supplier selected');
        $('#infoGst').text('-');
        clearBillsTable();
        return;
    }

    loadOutstandingBills(supplierId, true);
});

function resetQuickAddSupplierForm() {
    $('#quickAddSupplierForm')[0].reset();
    $('#quickAddSupplierModal .invalid-feedback-text').addClass('d-none').text('');
    $('#quickAddSupplierModal .form-control').removeClass('is-invalid');
    $('#quickAddSupplierError').addClass('d-none').text('');
}

$('#quickAddSupplierModal').on('hidden.bs.modal', resetQuickAddSupplierForm);

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
        url: baseUrl + 'suppliers/ajax-store',
        type: 'POST',
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
            showToast(data.message || 'Supplier Added Successfully.');
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

// "Bank Account" applies to bank-type methods only.
function isBankMethod(method) {
    return ['Bank Transfer', 'Cheque', 'UPI'].indexOf(method) !== -1;
}
function toggleBankAccount() {
    var bank = isBankMethod($('#paymentMethod').val());
    $('#bankAccountSection').toggleClass('d-none', !bank);
    if (!bank) { $('#bankAccountId').val('').trigger('change.select2'); }
}
$('#paymentMethod').on('change', function () { setFieldError('method', ''); setFieldError('bank', ''); toggleBankAccount(); });
$('#bankAccountId').on('change', function () { setFieldError('bank', ''); });
toggleBankAccount();

$('#voucherForm').on('submit', function (e) {
    e.preventDefault();

    var allocations = collectAllocations();
    var advance = getAdvance();
    var advanceUsed = getAdvanceUsed();
    var method = $('#paymentMethod').val();
    var totalPayment = round2(allocations.reduce(function (s, a) { return s + a.paid_amount; }, 0) + advance - advanceUsed);

    var errorBanner = $('#formErrorBanner');
    errorBanner.addClass('d-none').text('');
    clearFieldErrors();

    if (!$('#supplierSelect').val()) {
        setFieldError('supplier', 'Supplier is required.');
    }
    if (advanceInvalid()) {
        advanceTouched = true;
        setFieldError('advance', 'Advance amount must be greater than zero.');
    } else if (allocations.length === 0 && advance <= 0) {
        errorBanner.text('At least one bill allocation or an advance payment is required.').removeClass('d-none');
    }
    // A voucher settled purely from advance moves no money, so it needs no method or bank account.
    if (totalPayment > 0) {
        if (!method) {
            setFieldError('method', 'Payment method is required.');
        } else if (isBankMethod(method) && !$('#bankAccountId').val()) {
            setFieldError('bank', 'Please select the bank account this payment is made from.');
        }
    }
    if ($('.sp-err:not(.d-none)').length || !errorBanner.hasClass('d-none')) {
        var first = $('.sp-err:not(.d-none)').first();
        var target = first.length ? first : errorBanner;
        target[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
        return;
    }

    var btn = $('#saveVoucherBtn');
    var spinner = $('#saveVoucherSpinner');
    btn.prop('disabled', true);
    spinner.removeClass('d-none');

    $.ajax({
        url: baseUrl + 'supplier-payments/update/' + paymentId,
        type: 'POST',
        dataType: 'json',
        data: $.extend({
            supplier_id: $('#supplierSelect').val(),
            payment_date: $('#paymentDate').val(),
            payment_method: method,
            bank_account_id: isBankMethod(method) ? $('#bankAccountId').val() : '',
            reference_no: $('#referenceNo').val(),
            remarks: $('#remarks').val(),
            total_amount: totalPayment,
            advance_amount: advance,
            pay_advance: $('#payAdvance').is(':checked') ? 1 : 0,
            use_advance: (advAvail > 0.004 && useAdvanceOn()) ? 1 : 0,
            allocations: JSON.stringify(allocations)
        }, advanceUsed > 0 ? { advance_used: advanceUsed } : {})
    }).done(function (resp) {
        if (resp.status) {
            showToast(resp.message || 'Supplier payment updated successfully.');
            setTimeout(function () { window.location.href = baseUrl + 'supplier-payments/view/' + paymentId; }, 800);
        } else {
            errorBanner.text((resp.errors || [resp.message]).join(' ')).removeClass('d-none');
            btn.prop('disabled', false);
            spinner.addClass('d-none');
        }
    }).fail(function (xhr) {
        var data = xhr.responseJSON;
        errorBanner.text((data && data.errors ? data.errors.join(' ') : 'A network error occurred. Please try again.')).removeClass('d-none');
        btn.prop('disabled', false);
        spinner.addClass('d-none');
    });
});

// Initial load — fill the supplier strip, then merge this voucher's own allocations
// with a fresh ajaxBills() call for the same supplier and restore its advance answer.
(function () {
    var picked = $('#supplierSelect').find(':selected');
    var gst = picked.attr('data-gst') || '';
    $('#infoName').text(picked.attr('data-name') || picked.text().trim() || 'No supplier selected');
    $('#infoGst').text(gst || '-').attr('title', gst);
})();
toggleAdvance(false);
loadOutstandingBills($('#supplierSelect').val(), false);
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
