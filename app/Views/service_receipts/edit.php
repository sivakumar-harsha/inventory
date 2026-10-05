<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$modeLabels = ['CASH' => 'Cash', 'BANK' => 'Bank', 'CHEQUE' => 'Cheque', 'UPI' => 'UPI', 'OTHER' => 'Other'];
?>

<style>
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

/* Customer information card (same design as the Supplier Info Card) */
.customer-info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: .8rem;
    color: #334155;
}
.customer-info-card .info-row { margin-bottom: 3px; }
.customer-info-card .info-label { color: #64748b; display:inline-block; min-width:90px; }

/* Payment status badge — Pending orange / Partial blue / Paid green */
.badge-sr {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 600;
}
.badge-sr.badge-pending { background:#ffedd5; color:#c2410c; }
.badge-sr.badge-partial { background:#dbeafe; color:#1d4ed8; }
.badge-sr.badge-paid    { background:#dcfce7; color:#166534; }

.sr-breadcrumb { margin-bottom: 8px; }
.sr-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
.sr-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
.sr-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
.sr-breadcrumb .breadcrumb-item.active { color: #64748b; }

/* Service items table (Section 3) */
.sr-item-toolbar { display:flex; justify-content:flex-end; margin-bottom:10px; }
.sr-item-table { min-width: 760px; }
.sr-item-table th, .sr-item-table td { vertical-align: middle; }
.sr-item-table .desc-cell { min-width: 240px; }
.sr-item-table input.form-control,
.sr-item-table select.form-control { font-size: .8rem; padding: 5px 8px; height:auto; }
.sr-remove-row { background:none; border:none; color:#dc2626; cursor:pointer; font-size:1rem; }
.sr-remove-row:hover { color:#7f1d1d; }
.sr-empty-row td { text-align:center; color:#94a3b8; padding: 24px 12px; font-size:.85rem; }

/* Payment summary (Section 4) — compact accounting style, same as General Purchase */
.sr-summary-table { width:100%; font-size:.85rem; }
.sr-summary-table td { padding:6px 2px; border-bottom:1px dashed #e2e8f0; }
.sr-summary-table tr:last-child td { border-bottom:none; }
.sr-summary-table td.amt { text-align:right; }
.sr-summary-table tr.grand td { font-weight:700; font-size:.95rem; border-top:1px solid #cbd5e1; border-bottom:1px solid #cbd5e1; }

/* Sticky on desktop only; tablet and below stack and scroll normally */
.sr-sticky { position: sticky; top: 12px; }
@media (max-width: 991.98px) {
    .sr-sticky { position: static; }
}

/* Inline validation */
.field-error { display:none; color:#dc2626; font-size:.72rem; margin-top:3px; }
.field-error.show { display:block; }
#saveHint { font-size:.72rem; color:#94a3b8; margin-top:8px; }
.btn-save:disabled { opacity:.55; cursor:not-allowed; }

/* Release 4.9.0BJ: simplified service rows (Description + Amount) and the quick-add customer button. */
.sr-item-table { min-width: 0 !important; }
.sr-item-table .amount-input { text-align: right; }
.sr-legacy-note { font-size: .7rem; color: #94a3b8; margin-top: 2px; }
.customer-select-row { display: flex; align-items: flex-start; gap: 6px; }
.customer-select-row .select2-container { flex: 1 1 auto; min-width: 0; }
.customer-select-row > select { flex: 1 1 auto; min-width: 0; }
.btn-quick-add-customer { width: 34px; height: 34px; flex-shrink: 0; border: none; border-radius: 6px; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.btn-quick-add-customer:hover { background: var(--primary-dark); }
#quickAddCustomerModal .invalid-feedback-text { font-size: .8rem; }
#quickAddCustomerModal .qc-existing { font-size: .8rem; }
</style>

<nav aria-label="breadcrumb" class="sr-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item"><a href="<?= base_url('service-receipts') ?>">Service Received</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit <?= esc($receipt['receipt_no']) ?></li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-receipt-cutoff me-2"></i>Edit Service Receipt — <?= esc($receipt['receipt_no']) ?></span>
    <a href="<?= base_url('service-receipts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div id="formErrorBanner" class="alert alert-danger d-none" role="alert"></div>

<form id="receiptForm" novalidate>

<div class="row g-3">
    <div class="col-lg-8">

        <!-- Section 1 — Receipt Information -->
        <div class="card-custom mb-3">
            <div class="card-custom-header">Receipt Information</div>
            <div class="card-custom-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" class="form-control" value="<?= esc($receipt['receipt_no']) ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                            <input type="date" id="receiptDate" class="form-control" value="<?= esc($receipt['receipt_date']) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Receipt Type <span class="text-danger">*</span></label>
                            <!-- Release 4.9.0BQ: the type is fixed. The server keeps the stored value (a historical Invoice stays an Invoice) and ignores anything submitted. -->
                            <input type="text" id="receiptType" class="form-control" value="<?= $receipt['receipt_type'] === 'INVOICE' ? 'Invoice (historical)' : 'Direct Receipt' ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Payment Mode</label>
                            <select id="paymentMode" class="form-control no-search">
                                <?php foreach ($payment_modes as $m): ?>
                                <option value="<?= esc($m) ?>" <?= $m === $receipt['payment_mode'] ? 'selected' : '' ?>><?= esc($modeLabels[$m] ?? $m) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-section">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select id="customerSelect" class="form-control">
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $receipt['customer_id'] ? 'selected' : '' ?>
                                    data-name="<?= esc($c['name'] ?? '') ?>"
                                    data-gst="<?= esc($c['gst'] ?? '') ?>"
                                    data-phone="<?= esc($c['phone'] ?? '') ?>"
                                    data-email="<?= esc($c['email'] ?? '') ?>"
                                    data-address="<?= esc($c['address'] ?? '') ?>">
                                    <?= esc($c['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="err-customer">Customer is required.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-section">
                            <label class="form-label">Customer Name</label>
                            <input type="text" id="customerNameDisplay" class="form-control" readonly placeholder="Auto-filled">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-section">
                            <label class="form-label">Customer Address</label>
                            <input type="text" id="customerAddressDisplay" class="form-control" readonly placeholder="Auto-filled">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-section">
                            <label class="form-label">Attended Person <span class="text-danger">*</span></label>
                            <input type="text" id="attendedPerson" class="form-control" maxlength="150" value="<?= esc($receipt['attended_person']) ?>">
                            <div class="field-error" id="err-attended">Attended person is required.</div>
                        </div>
                    </div>
                    <div class="col-md-6 d-none" id="bankAccountSection">
                        <div class="form-section">
                            <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                            <select id="bankAccountId" class="form-control">
                                <option value="">-- Select Bank Account --</option>
                                <?php foreach ($bank_accounts as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === (int) $receipt['bank_account_id'] ? 'selected' : '' ?>><?= esc($b['bank_name'] . ' - ' . $b['account_name'] . ' (' . $b['account_number'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="err-bank">Please select the bank account the amount was received in.</div>
                            <?php $savedBank = (int) $receipt['bank_account_id']; ?>
                            <?php if ($savedBank && ! in_array($savedBank, array_map('intval', array_column($bank_accounts, 'id')), true)): ?>
                            <div class="text-warning" style="font-size:.72rem;margin-top:3px;">The saved account (<?= esc(trim(($receipt['bank_name'] ?? '') . ' ' . ($receipt['account_number'] ?? ''))) ?>) is inactive — please choose an active account.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-section">
                            <label class="form-label">Remarks</label>
                            <input type="text" id="remarks" class="form-control" value="<?= esc($receipt['remarks'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2 — Customer Information (shown after a customer is selected) -->
        <div class="card-custom mb-3 d-none" id="customerInfoCard">
            <div class="card-custom-header">Customer Information</div>
            <div class="card-custom-body">
                <div class="customer-info-card">
                    <div class="info-row"><span class="info-label">Name:</span> <span id="infoName">-</span></div>
                    <div class="info-row"><span class="info-label">Mobile:</span> <span id="infoPhone">-</span></div>
                    <div class="info-row"><span class="info-label">Email:</span> <span id="infoEmail">-</span></div>
                    <div class="info-row"><span class="info-label">GST Number:</span> <span id="infoGst">-</span></div>
                    <div class="info-row"><span class="info-label">Address:</span> <span id="infoAddress">-</span></div>
                </div>
            </div>
        </div>

        <!-- Section 3 — Service Items -->
        <div class="card-custom mb-3">
            <div class="card-custom-header">Service Items</div>
            <div class="card-custom-body">
                <div class="sr-item-toolbar">
                    <button type="button" class="btn-save" id="addItemBtn"><i class="bi bi-plus"></i> Add Service</button>
                </div>
                <div class="table-responsive">
                    <table class="table-custom sr-item-table">
                        <thead>
                            <tr>
                                <th class="desc-cell">Description</th>
                                <th style="width:140px;text-align:right">Amount</th>
                                <th style="width:48px"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsContainer">
                            <tr class="sr-empty-row" id="emptyItemsRow">
                                <td colspan="3">No service items added yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4 — Payment Summary -->
    <div class="col-lg-4">
        <div class="card-custom sr-sticky">
            <div class="card-custom-header">Payment Summary</div>
            <div class="card-custom-body">
                <table class="sr-summary-table">
                    <tr><td>Subtotal</td><td class="amt">₹<span id="sumSubtotal">0.00</span></td></tr>
                    <tr id="sumGstRow" class="d-none"><td>GST Total</td><td class="amt">₹<span id="sumGstTotal">0.00</span></td></tr>
                    <tr class="grand"><td>Grand Total</td><td class="amt">₹<span id="sumGrandTotal">0.00</span></td></tr>
                    <?php if (! $legacy_partial): ?>
                    <tr><td>Amount Received</td><td class="amt">₹<span id="sumReceived">0.00</span></td></tr>
                    <?php endif; ?>
                </table>

                <?php if ($legacy_partial): ?>
                <!-- Historical receipt with a genuine balance: its Received/Outstanding data is preserved and stays editable. -->
                <div class="form-section mt-3">
                    <label class="form-label">Received Amount</label>
                    <input type="number" id="receivedAmount" class="form-control" step="0.01" min="0" value="<?= esc($receipt['received_amount']) ?>">
                    <div class="field-error" id="err-received"></div>
                </div>

                <table class="sr-summary-table mt-2">
                    <tr><td>Outstanding Amount</td><td class="amt">₹<span id="sumOutstanding">0.00</span></td></tr>
                </table>

                <div class="mt-2">
                    <span class="badge-sr badge-pending" id="paymentStatusBadge">Pending</span>
                </div>
                <?php endif; ?>

                <div class="mt-3">
                    <button type="button" class="btn-save w-100" id="saveBtn" data-after="list" disabled>
                        <span class="spinner-border spinner-border-sm d-none save-spinner"></span>
                        <i class="bi bi-save"></i> Save
                    </button>
                    <button type="button" class="btn-save w-100 mt-2" id="saveViewBtn" data-after="view" style="background:#6c757d;" disabled>
                        <span class="spinner-border spinner-border-sm d-none save-spinner"></span>
                        <i class="bi bi-eye"></i> Save &amp; View
                    </button>
                    <a href="<?= base_url('service-receipts') ?>" class="btn-cancel w-100 mt-2 d-block text-center"><i class="bi bi-x"></i> Cancel</a>
                    <div id="saveHint"></div>
                </div>
            </div>
        </div>
    </div>
</div>

</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var baseUrl   = "<?= base_url() ?>";
var BANK_MODES = <?= json_encode($bank_modes) ?>;
var RECEIPT_ID = <?= (int) $receipt['id'] ?>;
var RECEIPT_TYPE = <?= json_encode($receipt['receipt_type']) ?>;
var LEGACY_PARTIAL =<?= $legacy_partial ? 'true' : 'false' ?>;   // historical receipt with an outstanding balance
var SAVE_URL   = baseUrl + 'service-receipts/update/' + RECEIPT_ID;
// this receipt's saved service rows (loaded through addItem(), which sets values via .val())
var EXISTING_ITEMS = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var itemCount  = 0;
var saving     = false;
var touched    = {};

// ---------- calculation ----------

// Release 4.9.0BJ: a service row is just Description + Amount (the final amount for that service).
// Rows opened from a historical receipt that really used qty x rate and/or GST keep those figures
// (shown read-only, submitted unchanged), so editing never silently flattens old accounting data.
var AMOUNT_RE = /^\d+(\.\d{1,2})?$/;

function cents(v) { return Math.round((parseFloat(v) || 0) * 100); }

// Same rule as ServiceReceipts::_recalculateStatus() / Supplier Payment.
function statusFor(grand, outstanding, hasItems) {
    if (!hasItems) return 'Pending';
    if (outstanding <= 0.004) return 'Paid';
    if (Math.abs(outstanding - grand) <= 0.004) return 'Pending';
    return 'Partial';
}

function isBankMode(mode) {
    return BANK_MODES.indexOf(mode) !== -1;
}

function isDirect() {
    return RECEIPT_TYPE === 'DIRECT';
}

// ---------- service rows ----------

function toggleEmptyRow() {
    $('#emptyItemsRow').toggle($('.item-row').length === 0);
}

// data: {description, amount} for a simple row, or {description, legacy: {qty, rate, gst_percent, gst_amount, line_total}}.
function addItem(data) {
    data = data || {};
    itemCount++;

    var $row = $(
        '<tr class="item-row" id="item-' + itemCount + '">' +
            '<td class="desc-cell"><input type="text" class="form-control desc-input" maxlength="500" placeholder="Service description">' +
                '<div class="sr-legacy-note d-none">Earlier receipt line (qty &times; rate + GST). Remove and re-add it to change the amount.</div></td>' +
            '<td><input type="number" class="form-control amount-input" min="0.01" step="0.01" placeholder="0.00"></td>' +
            '<td><button type="button" class="sr-remove-row" title="Remove row"><i class="bi bi-x-circle-fill"></i></button></td>' +
        '</tr>'
    );

    // values are set through .val() (never string-concatenated into HTML)
    if (data.description !== undefined) $row.find('.desc-input').val(data.description);
    if (data.legacy) {
        $row.data('legacy', data.legacy);
        $row.find('.amount-input').val(parseFloat(data.legacy.line_total).toFixed(2)).prop('readonly', true);
        $row.find('.sr-legacy-note').removeClass('d-none');
    } else if (data.amount !== undefined) {
        $row.find('.amount-input').val(data.amount);
    }

    $('#itemsContainer').append($row);
    toggleEmptyRow();
    recalc();
}

// ---------- live recalculation + validation ----------

function markInvalid($el, bad) {
    $el.toggleClass('is-invalid', bad && $el.data('touched') === true);
}

function showError(id, show, message) {
    var $e = $('#' + id);
    if (message !== undefined) $e.text(message);
    $e.toggleClass('show', !!show);
}

function recalc() {
    var rowsOk = true;
    var taxableC = 0, gstC = 0, count = 0;

    $('.item-row').each(function () {
        var $r      = $(this);
        var desc    = $.trim($r.find('.desc-input').val());
        var legacy  = $r.data('legacy');
        var amtText = $.trim($r.find('.amount-input').val());
        count++;

        var amtBad = false;
        if (legacy) {
            taxableC += cents(legacy.line_total) - cents(legacy.gst_amount);
            gstC     += cents(legacy.gst_amount);
        } else {
            amtBad = !(AMOUNT_RE.test(amtText) && parseFloat(amtText) > 0);
            if (!amtBad) taxableC += cents(amtText);
        }

        var descBad = desc === '';
        if (descBad || amtBad) rowsOk = false;

        markInvalid($r.find('.desc-input'), descBad);
        markInvalid($r.find('.amount-input'), amtBad);
    });

    var hasItems = count > 0;
    var subtotal = taxableC / 100;
    var gstTotal = gstC / 100;
    var grand    = (taxableC + gstC) / 100;

    $('#sumSubtotal').text(subtotal.toFixed(2));
    $('#sumGstTotal').text(gstTotal.toFixed(2));
    $('#sumGstRow').toggleClass('d-none', gstC === 0);   // only a historical GST line has any to show
    $('#sumGrandTotal').text(grand.toFixed(2));

    // Received / outstanding
    var receivedOk = true;
    var receivedMsg = '';
    var received, outstanding;

    if (!LEGACY_PARTIAL) {
        // Release 4.9.0BM: money received in full — Amount Received is the Grand Total.
        $('#sumReceived').text(grand.toFixed(2));
    } else if (isDirect()) {
        // Direct: paid in full on the spot — received follows the grand total.
        received = grand;
        outstanding = 0;
        $('#receivedAmount').val(grand.toFixed(2)).prop('readonly', true);
    } else {
        $('#receivedAmount').prop('readonly', false);
        var raw = $('#receivedAmount').val();
        received = raw === '' ? 0 : parseFloat(raw);

        if (isNaN(received) || received < 0) {
            receivedOk = false;
            receivedMsg = 'Received amount must be zero or more.';
            received = 0;
        } else if (cents(received) > cents(grand)) {
            receivedOk = false;
            receivedMsg = 'Received amount cannot exceed the grand total of ' + grand.toFixed(2) + '.';
        }
        outstanding = Math.max(0, grand - received);
    }

    if (LEGACY_PARTIAL) {
        $('#sumOutstanding').text(outstanding.toFixed(2));
        var status = statusFor(grand, outstanding, hasItems && grand > 0);
        $('#paymentStatusBadge').text(status).attr('class', 'badge-sr badge-' + status.toLowerCase());
    }

    // Required fields
    var customerOk    = !!$('#customerSelect').val();
    var attendedOk    = $.trim($('#attendedPerson').val()) !== '';
    var dateOk        = !!$('#receiptDate').val();
    var paymentModeOk = true;   // always set on an existing receipt
    var bankNeeded    = isBankMode($('#paymentMode').val());
    var bankOk        = !bankNeeded || !!$('#bankAccountId').val();

    showError('err-customer', touched.customer && !customerOk);
    showError('err-attended', touched.attended && !attendedOk);
    showError('err-payment-mode', touched.paymentMode && !paymentModeOk);
    showError('err-bank', bankNeeded && touched.bank && !bankOk);
    showError('err-received', !receivedOk, receivedMsg);

    var missing = [];
    if (!customerOk) missing.push('customer');
    if (!dateOk) missing.push('receipt date');
    if (!attendedOk) missing.push('attended person');
    if (!paymentModeOk) missing.push('payment mode');
    if (!bankOk) missing.push('bank account');
    if (!hasItems) missing.push('at least one service');
    else if (!rowsOk) missing.push('a description and an amount above zero on every row');
    if (!receivedOk) missing.push('a valid received amount');

    var canSave = missing.length === 0 && !saving;
    $('#saveBtn, #saveViewBtn').prop('disabled', !canSave);
    $('#saveHint').text(missing.length ? 'To save, complete: ' + missing.join(', ') + '.' : '');

    return { ok: missing.length === 0, grand: grand };
}

// ---------- form behaviour ----------

function toggleBankAccount() {
    var bank = isBankMode($('#paymentMode').val());
    $('#bankAccountSection').toggleClass('d-none', !bank);
    if (!bank) {
        touched.bank = false;
        $('#bankAccountId').val('').trigger('change.select2');
    }
}

function fillCustomer() {
    var $opt = $('#customerSelect').find(':selected');
    var has  = !!$('#customerSelect').val();

    $('#customerNameDisplay').val(has ? ($opt.attr('data-name') || '') : '');
    $('#customerAddressDisplay').val(has ? ($opt.attr('data-address') || '') : '').attr('title', has ? ($opt.attr('data-address') || '') : '');

    $('#infoName').text(($opt.attr('data-name') || '') || '-');
    $('#infoPhone').text(($opt.attr('data-phone') || '') || '-');
    $('#infoEmail').text(($opt.attr('data-email') || '') || '-');
    $('#infoGst').text(($opt.attr('data-gst') || '') || '-');
    $('#infoAddress').text(($opt.attr('data-address') || '') || '-');

    $('#customerInfoCard').toggleClass('d-none', !has);
}

function showToast(message, isError) {
    var toast = $('<div class="quick-add-toast"></div>').addClass(isError ? 'error' : '').text(message);
    $('body').append(toast);
    setTimeout(function () { toast.addClass('show'); }, 10);
    setTimeout(function () {
        toast.removeClass('show');
        setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
}

function saveReceipt(after) {
    var state = recalc();
    if (!state.ok || saving) return;

    var items = [];
    $('.item-row').each(function () {
        var $r = $(this), legacy = $r.data('legacy');
        if (legacy) {
            items.push({ description: $.trim($r.find('.desc-input').val()), qty: legacy.qty, rate: legacy.rate, gst_percent: legacy.gst_percent });
        } else {
            items.push({ description: $.trim($r.find('.desc-input').val()), amount: $.trim($r.find('.amount-input').val()) });
        }
    });

    var bank = isBankMode($('#paymentMode').val());
    var errorBanner = $('#formErrorBanner');
    errorBanner.addClass('d-none').text('');

    saving = true;
    $('#saveBtn, #saveViewBtn').prop('disabled', true);
    $('.save-spinner').removeClass('d-none');

    // Round Off is display-only and is not part of this payload.
    // received_amount is sent only for a historical partial receipt; otherwise the server derives it from the grand total.
    var payload = {
            customer_id:    $('#customerSelect').val(),
            receipt_date:    $('#receiptDate').val(),
            attended_person: $.trim($('#attendedPerson').val()),
            payment_mode:    $('#paymentMode').val(),
            bank_account_id: bank ? $('#bankAccountId').val() : '',
            remarks:         $('#remarks').val(),
            items:           items
    };
    if (LEGACY_PARTIAL) payload.received_amount = $('#receivedAmount').val();

    $.ajax({
        url: SAVE_URL,
        type: 'POST',
        dataType: 'json',
        data: payload
    }).done(function (resp) {
        if (resp.status) {
            showToast(resp.message || 'Service receipt saved successfully.');
            var target = (after === 'view') ? 'service-receipts/view/' + resp.id : 'service-receipts';
            setTimeout(function () { window.location.href = baseUrl + target; }, 700);
        } else {
            failSave((resp.errors || [resp.message]).join(' '));
        }
    }).fail(function (xhr) {
        var data = xhr.responseJSON;
        failSave(data && data.errors ? data.errors.join(' ') : 'A network error occurred. Please try again.');
    });
}

function failSave(message) {
    saving = false;
    $('.save-spinner').addClass('d-none');
    $('#formErrorBanner').text(message).removeClass('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    recalc();
}

// ---------- events ----------

$('#addItemBtn').on('click', function () { addItem(); });

$('#itemsContainer').on('click', '.sr-remove-row', function () {
    $(this).closest('tr').remove();
    toggleEmptyRow();
    recalc();
});
$('#itemsContainer').on('input change', 'input, select', recalc);
$('#itemsContainer').on('focusout', 'input', function () {
    $(this).data('touched', true);
    recalc();
});

$('#paymentMode').on('change', function () { toggleBankAccount(); recalc(); });
if (LEGACY_PARTIAL) $('#receivedAmount').on('input', recalc);
$('#receiptDate').on('input change', recalc);
$('#customerSelect').on('change', function () { touched.customer = true; fillCustomer(); recalc(); });
$('#attendedPerson').on('input', recalc).on('blur', function () { touched.attended = true; recalc(); });
$('#bankAccountId').on('change', function () { touched.bank = true; recalc(); });

$('#saveBtn, #saveViewBtn').on('click', function () { saveReceipt($(this).data('after')); });
$('#receiptForm').on('submit', function (e) { e.preventDefault(); });

// ---------- initial state ----------
fillCustomer();
toggleBankAccount();
EXISTING_ITEMS.forEach(function (it) {
    var plain = parseFloat(it.qty) === 1 && parseFloat(it.gst_percent) === 0 && Math.abs(parseFloat(it.rate) - parseFloat(it.line_total)) < 0.005;
    addItem(plain
        ? { description: it.description, amount: parseFloat(it.line_total).toFixed(2) }
        : { description: it.description, legacy: { qty: it.qty, rate: it.rate, gst_percent: it.gst_percent, gst_amount: it.gst_amount, line_total: it.line_total } });
});
toggleEmptyRow();
recalc();
</script>
<?= $this->endSection() ?>
