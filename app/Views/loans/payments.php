<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$today    = date('Y-m-d');
$fmtDate  = static fn ($d) => $d ? date('d-m-Y', strtotime($d)) : '—';
$isActive = $loan['status'] === 'ACTIVE';

$methodLabel = ['CASH' => 'Cash', 'BANK' => 'Bank', 'CHEQUE' => 'Cheque', 'UPI' => 'UPI', 'OTHER' => 'Other'];

// Release 4.9.0BA: a manual transaction register — one Payment Amount per payment, every figure comes from loan_payments.total_paid.
$payCount = count($payments);

$paymentData = [];
foreach ($payments as $p) {
    $paymentData[(int) $p['id']] = [
        'id'        => (int) $p['id'],
        'date'      => $p['payment_date'],
        'method'    => $p['payment_method'],
        'bank_id'   => $p['bank_account_id'] ? (int) $p['bank_account_id'] : 0,
        'amount'    => round((float) $p['total_paid'], 2),
        'ref'       => (string) $p['reference_no'],
        'remarks'   => (string) $p['remarks'],
    ];
}
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<?= $this->include('loans/partials/ui_styles') ?>

<style>
	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.ln-table-wrap { overflow-x: auto; }
	#payTable { min-width: 820px; }
	#payTable th, #payTable td { padding: 6px 10px; font-size: .78rem; white-space: nowrap; }
	#payTable .num { text-align: right; }

	/* quick pending totals above the EMI table */
	.ln-summary-strip { display: grid; grid-template-columns: repeat(3, 1fr); margin-bottom: 10px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
	.ln-summary-cell { padding: 8px 14px; background: #f8fafc; border-right: 1px solid #e2e8f0; }
	.ln-summary-cell:last-child { border-right: 0; }
	.ln-summary-cell .l { font-size: .7rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
	.ln-summary-cell .v { font-size: 1.05rem; font-weight: 700; color: #1e293b; }
	.ln-summary-cell .s { font-size: .68rem; color: #94a3b8; }
	@media (max-width: 575.98px) {
		.ln-summary-strip { grid-template-columns: 1fr; }
		.ln-summary-cell { border-right: 0; border-bottom: 1px solid #e2e8f0; }
		.ln-summary-cell:last-child { border-bottom: 0; }
	}

	.form-label .req { color: #dc2626; }
	.invalid-feedback { font-size: .75rem; }
	/* a field that failed validation: the input, or the search box standing in for a select2 select */
	#payForm .form-control.is-invalid { border-color: #dc2626; background-color: #fef2f2; }
	#payForm .is-invalid + .select2-container .select2-selection { border-color: #dc2626; background-color: #fef2f2; }
	.pay-total { font-weight: 700; }
	#payHint { font-size: .78rem; color: #64748b; }
	#payHint.bad { color: #b91c1c; }

	@media print {
		.sidebar, .topbar { display: none !important; }
		.main-content { margin-left: 0 !important; }
		.no-print, .ln-breadcrumb, #loanFlash, #payCard { display: none !important; }
		.ln-table-wrap { overflow: visible; }
		#payTable { min-width: 0; }
		.card-custom { box-shadow: none; }
	}
</style>

<nav aria-label="breadcrumb" class="ln-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans') ?>">Loan Management</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans/view/' . $loan['id']) ?>"><?= esc($loan['loan_no']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Payments</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi bi-wallet2 me-2"></i>Loan Payments — <?= esc($loan['loan_no']) ?></span>
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="<?= base_url('loans/view/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back to Loan</a>
        <a href="<?= base_url('loans/ledger/' . $loan['id']) ?>" class="btn-save" style="background:#6c757d;"><i class="bi bi-journal-text"></i> View Ledger</a>
        <?php if ($isActive): ?>
        <a href="javascript:void(0)" class="btn-save" id="btnNewPay"><i class="bi bi-plus-circle"></i> Record Payment</a>
        <?php endif; ?>
    </div>
</div>

<div id="loanFlash" class="alert alert-success alert-dismissible fade show" role="alert" style="display:none;">
    <i class="bi bi-check-circle-fill me-2"></i><span id="loanFlashText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if (! $isActive): ?>
<div class="alert alert-info" role="alert">
    <i class="bi bi-info-circle-fill me-2"></i>This loan is closed — no further payments can be recorded. Deleting a payment reopens it.
</div>
<?php endif; ?>

<!-- LOAN SUMMARY -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Loan Summary</div>
    <div class="card-custom-body">
        <div class="row g-3">
            <div class="col-md-6">
                <table class="table-custom">
                    <tr><td><strong>Loan Number</strong></td><td><?= esc($loan['loan_no']) ?> <?= ln_loan_status_badge($loan['status']) ?></td></tr>
                    <tr><td><strong>Lender</strong></td><td><?= esc($loan['lender_name']) ?></td></tr>
                    <tr><td><strong>Payments Recorded</strong></td><td><?= (int) $payCount ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table-custom">
                    <tr><td>Sanctioned Amount</td><td style="text-align:right">₹<?= number_format((float) $loan['sanctioned_amount'], 2) ?></td></tr>
                    <tr><td>Total Paid</td><td style="text-align:right">₹<?= number_format((float) $loan['total_paid'], 2) ?></td></tr>
                    <tr><td><strong>Outstanding Amount</strong></td><td id="sumOutstanding" style="text-align:right"><strong>₹<?= number_format((float) $loan['outstanding_principal'], 2) ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- RECORD PAYMENT -->
<?php if ($isActive || ! empty($payments)): ?>
<div class="card-custom mb-3 no-print" id="payCard" style="display:none;">
    <div class="card-custom-header" id="payCardTitle">Record Payment</div>
    <div class="card-custom-body">
        <form id="payForm" novalidate>
            <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Payment Date <span class="req">*</span></label>
                    <input type="date" name="payment_date" id="fDate" class="form-control" value="<?= esc($today) ?>">
                    <div class="invalid-feedback" data-for="payment_date"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method <span class="req">*</span></label>
                    <select name="payment_method" id="fMethod" class="form-control no-search">
                        <?php foreach ($methodLabel as $k => $lbl): ?>
                        <option value="<?= $k ?>"><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="fBankRow">
                    <label class="form-label">Payment From Bank Account <span class="req">*</span></label>
                    <select name="bank_account_id" id="fBank" class="form-control" data-placeholder="Select bank account">
                        <option value=""></option>
                        <?php foreach ($bank_accounts as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"><?= esc($b['bank_name'] . ' - ' . $b['account_name'] . ' (' . $b['account_number'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Select the company bank account from which this loan payment was made.</div>
                    <div class="invalid-feedback" data-for="bank_account_id"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Amount <span class="req">*</span></label>
                    <input type="number" name="payment_amount" id="fAmount" class="form-control" step="0.01" min="0.01" inputmode="decimal" placeholder="0.00">
                    <div class="invalid-feedback" data-for="payment_amount"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_no" id="fRef" class="form-control" maxlength="100" autocomplete="off" placeholder="Cheque / UTR / transaction no.">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" id="fRemarks" class="form-control" autocomplete="off">
                </div>
            </div>
            <div id="payHint" class="mt-2"></div>
            <div id="payErrors" class="alert alert-danger mt-3" style="display:none;"></div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn-save" id="btnPaySave"><i class="bi bi-check-lg"></i> <span id="btnPayLabel">Save Payment</span></button>
                <button type="button" class="btn-cancel" id="btnPayCancel">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- PAYMENT HISTORY -->
<div class="card-custom">
    <div class="card-custom-header">Payment History</div>
    <div class="card-custom-body">
        <div class="ln-table-wrap">
            <table id="payTable" class="table-custom">
                <thead>
                    <tr>
                        <th class="sno-col">S.No.</th>
                        <th>Payment Date</th>
                        <th class="num">Payment Amount</th>
                        <th>Payment Method</th>
                        <th>Bank Account</th>
                        <th>Reference</th>
                        <th>Remarks</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#94a3b8;">No payments recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="sno-col sno-auto" data-label="S.No."></td>
                        <td><?= $fmtDate($p['payment_date']) ?><?php if (! empty($p['loan_emi_id'])): ?> <small class="text-muted">(old EMI ref. #<?= (int) $p['emi_no'] ?>)</small><?php endif; ?></td>
                        <td class="num"><strong><?= number_format((float) $p['total_paid'], 2) ?></strong></td>
                        <td><?= ln_method_badge($p['payment_method']) ?></td>
                        <td><?= $p['bank_name'] ? esc($p['bank_name'] . ' - ' . $p['account_name']) : '—' ?></td>
                        <td><?= esc($p['reference_no'] ?: '—') ?></td>
                        <td><?= esc($p['remarks'] ?: '—') ?></td>
                        <td class="no-print">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn-edit js-edit-pay" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-pencil"></i> Edit</button>
                                <button type="button" class="btn-delete js-del-pay" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-trash"></i> Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
	var LOAN_ID = <?= (int) $loan['id'] ?>;
	var OUTSTANDING = <?= json_encode(round((float) $loan['outstanding_principal'], 2)) ?>;
	var BANK_METHODS = <?= json_encode($bank_methods) ?>;
	var PAYMENTS = <?= json_encode((object) $paymentData, $jsonFlags) ?>;
	var URL_STORE = "<?= base_url('loans/payments/store') ?>";
	var URL_UPDATE = "<?= base_url('loans/payments/update/') ?>";
	var URL_DELETE = "<?= base_url('loans/payments/delete/') ?>";
	var editing = null;   // the payment being edited, or null
	var saving = false;

	function money(n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function cents(v) { return Math.round(parseFloat(v) * 100); }
	function decimals(v) { var i = v.indexOf('.'); return i < 0 ? 0 : v.length - i - 1; }
	function bankMethod() { return BANK_METHODS.indexOf($('#fMethod').val()) !== -1; }

	// Release 4.9.0BA: the user enters ONE Payment Amount, the actual amount paid. Nothing is calculated,
	// split into principal / interest, or pre-filled; the only cap is the outstanding amount.
	function availableAmount() {
		return Math.round((parseFloat(OUTSTANDING) + (editing ? editing.amount : 0)) * 100) / 100;
	}

	function validate() {
		var err = {}, hint = '', v;

		if (!$('#fDate').val()) err.payment_date = 'Payment date is required.';

		v = $.trim($('#fAmount').val());
		if (v === '' || isNaN(v)) err.payment_amount = 'Enter the payment amount.';
		else if (+v <= 0) err.payment_amount = 'Payment amount must be greater than zero.';
		else if (decimals(v) > 2) err.payment_amount = 'No more than two decimal places.';
		else if (+v > availableAmount() + 0.004) err.payment_amount = 'More than the outstanding amount of ' + money(availableAmount()) + '.';

		if (bankMethod() && !$('#fBank').val()) err.bank_account_id = 'A bank account is required for Bank, Cheque and UPI payments.';

		hint += 'Outstanding amount: ' + money(availableAmount()) + '.';
		$('#payHint').toggleClass('bad', !!err.payment_amount).text(hint);
		return err;
	}

	var touched = {};
	var serverErr = {};   // field -> the message the server rejected it with; dropped as soon as that field is edited

	// Save stays clickable while the form is invalid: clicking it shows every problem and jumps to the first one.
	// The submit handler still refuses to send an invalid form, and the server re-validates regardless.
	function refresh(showAll) {
		var err = validate();
		$('#payForm .invalid-feedback').each(function () {
			var k = $(this).data('for'),
				msg = (err[k] && (showAll || touched[k])) ? err[k] : (serverErr[k] || ''),
				show = msg !== '';
			$(this).text(msg).toggle(show);
			$('#payForm [name="' + k + '"]').toggleClass('is-invalid', show).attr('aria-invalid', show ? 'true' : null);
		});
		$('#btnPaySave').prop('disabled', saving);
		return err;
	}

	// Which field a server message is about, so it can be marked as well as listed. Unmatched ones only appear in the alert.
	function fieldFor(msg) {
		if (/payment amount/i.test(msg)) return 'payment_amount';
		if (/payment date/i.test(msg)) return 'payment_date';
		if (/bank account/i.test(msg)) return 'bank_account_id';
		return null;
	}

	// Bring the first invalid field (or, failing that, the error box) into view and focus it. Entered values are never touched.
	function scrollToError() {
		var $bad = $('#payForm .is-invalid').filter(function () { return $(this).closest('[class*="col-"]').is(':visible'); }).first(),
			$focus = $bad.hasClass('select2-hidden-accessible') ? $bad.next('.select2-container').find('.select2-selection') : $bad,
			$anchor = $bad.length ? ($bad.hasClass('select2-hidden-accessible') ? $bad.next('.select2-container') : $bad) : $('#payErrors:visible');

		if (!$anchor.length) return;
		$('html, body').animate({ scrollTop: Math.max(0, $anchor.offset().top - 90) }, 200);
		if ($focus.length && $focus[0].focus) $focus[0].focus({ preventScroll: true });
	}

	function syncBankRow() {
		$('#fBankRow').toggle(bankMethod());
	}

	function open(title, label) {
		touched = {};
		serverErr = {};
		saving = false;
		$('#payErrors').hide().empty();
		$('#payCardTitle').text(title);
		$('#btnPayLabel').text(label);
		$('#payCard').show();
		syncBankRow();
		refresh(false);
		$('html, body').animate({ scrollTop: $('#payCard').offset().top - 70 }, 200);
	}

	function openNew() {
		editing = null;
		$('#fDate').val('<?= esc($today) ?>');
		$('#fMethod').val('BANK');
		$('#fBank').val('').trigger('change');
		$('#fRef, #fRemarks').val('');
		$('#fAmount').val('');
		open('Record Payment', 'Save Payment');
	}

	function openEdit(id) {
		var p = PAYMENTS[id];
		if (!p) return;
		editing = { id: p.id, amount: p.amount };
		$('#fDate').val(p.date);
		$('#fMethod').val(p.method);
		$('#fBank').val(p.bank_id ? String(p.bank_id) : '').trigger('change');
		$('#fRef').val(p.ref);
		$('#fRemarks').val(p.remarks);
		$('#fAmount').val(p.amount.toFixed(2));
		open('Edit Payment', 'Update Payment');
	}

	function fail(xhr) {
		var r = xhr.responseJSON, msgs = (r && r.errors) ? r.errors : ['The payment could not be saved. Please try again.'];
		var $b = $('#payErrors').empty();
		$.each(msgs, function (_, m) { $b.append($('<div>').text(m)); });
		$b.show();
		serverErr = {};
		$.each(msgs, function (_, m) { var k = fieldFor(m); if (k && !serverErr[k]) serverErr[k] = m; });
		saving = false;
		refresh(true);
		scrollToError();
	}

	function done(msg) {
		try { sessionStorage.setItem('loanFlash', msg); } catch (x) {}
		location.reload();
	}

	$('#btnNewPay').on('click', openNew);
	$(document).on('click', '.js-edit-pay', function () { openEdit($(this).data('id')); });
	$('#btnPayCancel').on('click', function () { $('#payCard').hide(); editing = null; });

	$('#fMethod').on('change', function () { delete serverErr.bank_account_id; syncBankRow(); refresh(false); });
	$('#fBank').on('change', function () { touched.bank_account_id = true; delete serverErr.bank_account_id; refresh(false); });
	$('#fDate, #fAmount').on('input change', function () {
		var key = ({ fDate: 'payment_date', fAmount: 'payment_amount' })[this.id];
		touched[key] = true;
		delete serverErr[key];
		refresh(false);
	});

	$('#payForm').on('submit', function (ev) {
		ev.preventDefault();
		if (saving) return;
		if (Object.keys(refresh(true)).length) { scrollToError(); return; }
		saving = true;
		$('#btnPaySave').prop('disabled', true);
		$('#payErrors').hide();

		$.ajax({
			url: editing ? URL_UPDATE + editing.id : URL_STORE,
			method: 'POST',
			dataType: 'json',
			data: $(this).serialize()
		}).done(function (r) {
			done(r.message || 'Payment saved.');
		}).fail(fail);
	});

	$(document).on('click', '.js-del-pay', function () {
		var id = $(this).data('id');
		if (!confirm('Delete this payment? The total paid and outstanding amount will be restored and any bank withdrawal reversed.')) return;
		$.ajax({ url: URL_DELETE + id, method: 'POST', dataType: 'json' })
			.done(function (r) { done(r.message || 'Payment deleted.'); })
			.fail(function (xhr) {
				var r = xhr.responseJSON;
				alert((r && r.errors && r.errors.join(' ')) || 'The payment could not be deleted.');
			});
	});

	// Loan View's Edit links open this page with ?edit=<payment id>.
	try {
		var editId = new URLSearchParams(window.location.search).get('edit');
		if (editId && PAYMENTS[editId]) openEdit(editId);
	} catch (x) {}

	// One-time message left before a reload.
	try {
		var msg = sessionStorage.getItem('loanFlash');
		if (msg) {
			sessionStorage.removeItem('loanFlash');
			$('#loanFlashText').text(msg);
			$('#loanFlash').show();
		}
	} catch (x) {}
})();
</script>
<?= $this->endSection() ?>
