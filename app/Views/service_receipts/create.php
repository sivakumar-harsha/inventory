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

/* Release 4.9.0AD: compact New Service Receipt screen. The separate "Customer
   Information" card was a read-only duplicate of the Customer dropdown's own
   data (never posted to the server — customer_name/customer_address are
   derived server-side from customer_id), so it and the auto-filled
   Name/Address display fields were dropped rather than made compact. */
.sr-page .page-title { margin-bottom: 10px; }
.sr-page .card-custom { margin-bottom: 10px; }
.sr-page .card-custom-header { padding: 7px 12px; }
.sr-page .card-custom-body { padding: 10px 12px; }
.sr-page .form-label { margin-bottom: 3px; }
.sr-page .form-section { margin-bottom: 8px; }
.sr-page { --bs-gutter-y: .5rem; }
/* Bootstrap's .row is a flex container; without this, a flex column can't
   shrink below the intrinsic width of the item table inside it, and the
   whole page gains a horizontal scrollbar instead of just the table. */
.sr-page > .row > [class^="col-"],
.sr-page > .row > [class*=" col-"] { min-width: 0; }

.sr-breadcrumb { margin-bottom: 8px; }
.sr-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
.sr-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
.sr-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
.sr-breadcrumb .breadcrumb-item.active { color: #64748b; }

/* Release 4.9.0AE: Add Service moves into the Service Items card header. */
.sr-item-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.sr-item-header .btn-save { padding: 3px 10px; font-size: .78rem; }

/* Service items table (Section 3) — compact fixed columns, flexible description,
   sized to fit inside the left column without page-level horizontal scroll. */
.sr-item-table { width: 100%; min-width: 600px; table-layout: fixed; }
.sr-item-table th, .sr-item-table td { vertical-align: middle; }
.sr-item-table .desc-cell { width: auto; min-width: 160px; }
.sr-item-table input.form-control,
.sr-item-table select.form-control { font-size: .8rem; padding: 5px 6px; height:auto; }
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
.customer-select-row .select2-container { flex: 1 1 auto; min-width: 0; width: auto !important; }
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
        <li class="breadcrumb-item active" aria-current="page">New Service Receipt</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-receipt-cutoff me-2"></i>New Service Receipt</span>
    <a href="<?= base_url('service-receipts') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div id="formErrorBanner" class="alert alert-danger d-none" role="alert"></div>

<form id="receiptForm" novalidate>

<div class="sr-page row g-3">
    <div class="col-lg-8">

        <!-- Section 1 — Receipt Information -->
        <div class="card-custom mb-3">
            <div class="card-custom-header">Receipt Information</div>
            <div class="card-custom-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" class="form-control" value="<?= esc($nextReceiptNo) ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                            <input type="date" id="receiptDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Receipt Type <span class="text-danger">*</span></label>
                            <!-- Release 4.9.0BQ: a new receipt is always a Direct Receipt (the server forces it); no choice to offer. -->
                            <input type="text" id="receiptType" class="form-control" value="Direct Receipt" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Payment Mode <span class="text-danger">*</span></label>
                            <select id="paymentMode" class="form-control no-search">
                                <option value="">-- Select --</option>
                                <?php foreach ($payment_modes as $m): ?>
                                <option value="<?= esc($m) ?>"><?= esc($modeLabels[$m] ?? $m) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="err-payment-mode">Payment mode is required.</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5">
                        <div class="form-section">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <div class="customer-select-row">
                                <select id="customerSelect" class="form-control">
                                    <option value="">-- Select Customer --</option>
                                    <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn-quick-add-customer" data-bs-toggle="modal" data-bs-target="#quickAddCustomerModal" title="Add New Customer"><i class="bi bi-plus-lg"></i></button>
                            </div>
                            <div class="field-error" id="err-customer">Customer is required.</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-section">
                            <label class="form-label">Attended Person <span class="text-danger">*</span></label>
                            <input type="text" id="attendedPerson" class="form-control" maxlength="150">
                            <div class="field-error" id="err-attended">Attended person is required.</div>
                        </div>
                    </div>
                    <div class="col-md-4 d-none" id="bankAccountSection">
                        <div class="form-section">
                            <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                            <select id="bankAccountId" class="form-control">
                                <option value="">-- Select Bank Account --</option>
                                <?php foreach ($bank_accounts as $b): ?>
                                <option value="<?= (int) $b['id'] ?>"><?= esc($b['bank_name'] . ' - ' . $b['account_name'] . ' (' . $b['account_number'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="err-bank">Please select the bank account the amount was received in.</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-section">
                            <label class="form-label">Remarks</label>
                            <input type="text" id="remarks" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2 — Service Items -->
        <div class="card-custom mb-3">
            <div class="card-custom-header sr-item-header">
                <span>Service Items</span>
                <button type="button" class="btn-save" id="addItemBtn"><i class="bi bi-plus"></i> Add Service</button>
            </div>
            <div class="card-custom-body">
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

    <!-- Section 3 — Payment Summary -->
    <div class="col-lg-4">
        <div class="card-custom sr-sticky">
            <div class="card-custom-header">Payment Summary</div>
            <div class="card-custom-body">
                <table class="sr-summary-table">
                    <tr><td>Subtotal</td><td class="amt">₹<span id="sumSubtotal">0.00</span></td></tr>
                    <tr id="sumGstRow" class="d-none"><td>GST Total</td><td class="amt">₹<span id="sumGstTotal">0.00</span></td></tr>
                    <tr class="grand"><td>Grand Total</td><td class="amt">₹<span id="sumGrandTotal">0.00</span></td></tr>
                    <tr><td>Amount Received</td><td class="amt">₹<span id="sumReceived">0.00</span></td></tr>
                </table>

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

<!-- Quick Add Customer (uses the existing customers/ajax-store endpoint: auth filter, name + mobile required) -->
<div class="modal fade" id="quickAddCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="quickAddCustomerError" class="alert alert-danger d-none" role="alert"></div>
                <div id="quickAddCustomerExisting" class="alert alert-warning qc-existing d-none" role="alert"></div>
                <form id="quickAddCustomerForm" onsubmit="return false;">
                    <div class="form-section">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="qc_name" class="form-control" maxlength="200" autocomplete="off">
                        <div class="invalid-feedback-text text-danger d-none" id="qc_name_error"></div>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Mobile / Contact <span class="text-danger">*</span></label>
                        <input type="text" name="mobile" id="qc_mobile" class="form-control" maxlength="50" autocomplete="off">
                        <div class="invalid-feedback-text text-danger d-none" id="qc_mobile_error"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                <button type="button" class="btn-save" id="quickAddCustomerSaveBtn">
                    <span id="quickAddCustomerSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-save"></i> Save Customer
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var baseUrl   = "<?= base_url() ?>";
var BANK_MODES = <?= json_encode($bank_modes) ?>;
var SAVE_URL   = baseUrl + 'service-receipts/store';
var itemCount  = 0;
var saving     = false;
var touched    = {};

// ---------- calculation ----------

// Release 4.9.0BJ: a service row is just Description + Amount (the final amount for that service).
// Rows opened from a historical receipt that really used qty x rate and/or GST keep those figures
// (shown read-only, submitted unchanged), so editing never silently flattens old accounting data.
var AMOUNT_RE = /^\d+(\.\d{1,2})?$/;

function cents(v) { return Math.round((parseFloat(v) || 0) * 100); }

function isBankMode(mode) {
    return BANK_MODES.indexOf(mode) !== -1;
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

    // Release 4.9.0BM: a service receipt is money received in full — Amount Received is always the Grand Total.
    $('#sumReceived').text(grand.toFixed(2));

    // Required fields
    var customerOk    = !!$('#customerSelect').val();
    var attendedOk    = $.trim($('#attendedPerson').val()) !== '';
    var dateOk        = !!$('#receiptDate').val();
    var paymentModeOk = !!$('#paymentMode').val();
    var bankNeeded    = isBankMode($('#paymentMode').val());
    var bankOk        = !bankNeeded || !!$('#bankAccountId').val();

    showError('err-customer', touched.customer && !customerOk);
    showError('err-attended', touched.attended && !attendedOk);
    showError('err-payment-mode', touched.paymentMode && !paymentModeOk);
    showError('err-bank', bankNeeded && touched.bank && !bankOk);

    var missing = [];
    if (!customerOk) missing.push('customer');
    if (!dateOk) missing.push('receipt date');
    if (!attendedOk) missing.push('attended person');
    if (!paymentModeOk) missing.push('payment mode');
    if (!bankOk) missing.push('bank account');
    if (!hasItems) missing.push('at least one service');
    else if (!rowsOk) missing.push('a description and an amount above zero on every row');

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
    $.ajax({
        url: SAVE_URL,
        type: 'POST',
        dataType: 'json',
        data: {
            customer_id:    $('#customerSelect').val(),
            receipt_date:    $('#receiptDate').val(),
            attended_person: $.trim($('#attendedPerson').val()),
            payment_mode:    $('#paymentMode').val(),
            bank_account_id: bank ? $('#bankAccountId').val() : '',
            remarks:         $('#remarks').val(),
            items:           items
        }
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

$('#paymentMode').on('change', function () { touched.paymentMode = true; toggleBankAccount(); recalc(); });
$('#receiptDate').on('input change', recalc);
$('#customerSelect').on('change', function () { touched.customer = true; recalc(); });
$('#attendedPerson').on('input', recalc).on('blur', function () { touched.attended = true; recalc(); });
$('#bankAccountId').on('change', function () { touched.bank = true; recalc(); });

$('#saveBtn, #saveViewBtn').on('click', function () { saveReceipt($(this).data('after')); });
$('#receiptForm').on('submit', function (e) { e.preventDefault(); });


// ---------- quick add customer ----------
(function () {
    var modalEl = document.getElementById('quickAddCustomerModal');
    var $modal  = $(modalEl);

    function clearErrors() {
        $modal.find('.invalid-feedback-text').addClass('d-none').text('');
        $modal.find('.form-control').removeClass('is-invalid');
        $('#quickAddCustomerError, #quickAddCustomerExisting').addClass('d-none').empty();
    }

    function selectCustomer(id, name) {
        var $sel = $('#customerSelect');
        if (!$sel.find('option[value="' + id + '"]').length) {
            $sel.append($('<option></option>').val(id).text(name));
        }
        $sel.val(String(id)).trigger('change');   // select2 + the page's own change handler (nothing else is reset)
    }

    // Client-side duplicate hint against the customers already in the dropdown. The server's
    // customers/ajax-store has no duplicate rule of its own (the Customer Master does not either),
    // so this only offers the existing customer; it does not change the shared endpoint.
    function findExisting(name) {
        var n = $.trim(name).toLowerCase(), hit = null;
        $('#customerSelect option').each(function () {
            if (this.value && $.trim($(this).text()).toLowerCase() === n) { hit = { id: this.value, name: $(this).text() }; return false; }
        });
        return hit;
    }

    $modal.on('hidden.bs.modal', function () { document.getElementById('quickAddCustomerForm').reset(); clearErrors(); });
    $modal.on('shown.bs.modal', function () { $('#qc_name').trigger('focus'); });
    $modal.on('keydown', 'input', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#quickAddCustomerSaveBtn').trigger('click'); } });

    $('#quickAddCustomerSaveBtn').on('click', function () {
        var btn = this;
        clearErrors();

        var existing = findExisting($('#qc_name').val());
        if (existing) {
            $('#quickAddCustomerExisting')
                .text('A customer named "' + existing.name + '" already exists. ')
                .append($('<a href="#" class="alert-link">Use existing customer</a>').on('click', function (ev) {
                    ev.preventDefault();
                    selectCustomer(existing.id, existing.name);
                    bootstrap.Modal.getInstance(modalEl).hide();
                }))
                .removeClass('d-none');
            return;
        }

        btn.disabled = true;
        $('#quickAddCustomerSpinner').removeClass('d-none');

        $.ajax({
            url: baseUrl + 'customers/ajax-store',
            type: 'POST',
            dataType: 'json',
            // gst/email/address are sent empty: customers.gst is NOT NULL and ajax-store inserts whatever it receives
            data: { name: $.trim($('#qc_name').val()), mobile: $.trim($('#qc_mobile').val()), gst: '', email: '', address: '' }
        }).done(function (data) {
            if (data.status) {
                selectCustomer(data.customer.id, data.customer.name);
                bootstrap.Modal.getInstance(modalEl).hide();
                showToast(data.message || 'Customer created successfully.');
            } else {
                $('#quickAddCustomerError').text(data.message || 'Unable to save customer.').removeClass('d-none');
            }
        }).fail(function (xhr) {
            var data = xhr.responseJSON || {};
            if (data.errors) {
                Object.keys(data.errors).forEach(function (field) {
                    $('#qc_' + field).addClass('is-invalid');
                    $('#qc_' + field + '_error').text(data.errors[field]).removeClass('d-none');
                });
            } else {
                $('#quickAddCustomerError').text(data.message || 'A network error occurred. Please try again.').removeClass('d-none');
            }
        }).always(function () {
            btn.disabled = false;
            $('#quickAddCustomerSpinner').addClass('d-none');
        });
    });
})();

// ---------- initial state ----------
toggleBankAccount();
addItem();   // start with one blank service row
toggleEmptyRow();
recalc();
</script>
<?= $this->endSection() ?>
