<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
helper('loan_ui');
$methodLabel = ['CASH' => 'Cash', 'BANK' => 'Bank', 'CHEQUE' => 'Cheque', 'UPI' => 'UPI', 'OTHER' => 'Other'];
// Room available for this receipt's own amount: remaining_to_receive already excludes it (totalReceived includes
// the current row), so give its own amount back before showing the cap.
$roomForThis = round($remaining_to_receive + (float) $receipt['amount'], 2);
?>

<style>
	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.form-label .req { color: #dc2626; }
	.invalid-feedback { font-size: .75rem; }

	.ln-summary-strip { display: grid; grid-template-columns: repeat(3, 1fr); margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
	.ln-summary-cell { padding: 8px 14px; background: #f8fafc; border-right: 1px solid #e2e8f0; }
	.ln-summary-cell:last-child { border-right: 0; }
	.ln-summary-cell .l { font-size: .7rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
	.ln-summary-cell .v { font-size: 1.05rem; font-weight: 700; color: #1e293b; }
	@media (max-width: 575.98px) {
		.ln-summary-strip { grid-template-columns: 1fr; }
		.ln-summary-cell { border-right: 0; border-bottom: 1px solid #e2e8f0; }
		.ln-summary-cell:last-child { border-bottom: 0; }
	}
</style>

<nav aria-label="breadcrumb" class="ln-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans') ?>">Loan Management</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans/view/' . $loan['id']) ?>"><?= esc($loan['loan_no']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Receipt <?= esc($receipt['receipt_no']) ?></li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-piggy-bank me-2"></i>Edit Receipt <?= esc($receipt['receipt_no']) ?> — <?= esc($loan['loan_no']) ?></span>
</div>

<div class="ln-summary-strip">
    <div class="ln-summary-cell">
        <div class="l">Sanctioned Amount</div>
        <div class="v">₹<?= number_format((float) $loan['sanctioned_amount'], 2) ?></div>
    </div>
    <div class="ln-summary-cell">
        <div class="l">Total Received</div>
        <div class="v">₹<?= number_format($total_received, 2) ?></div>
    </div>
    <div class="ln-summary-cell">
        <div class="l">Remaining to Receive</div>
        <div class="v">₹<?= number_format($remaining_to_receive, 2) ?></div>
    </div>
</div>

<form id="receiptForm" novalidate>
<input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
<div class="card-custom">
    <div class="card-custom-header">Receipt Details</div>
    <div class="card-custom-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Receipt Date <span class="req">*</span></label>
                <input type="date" name="receipt_date" id="fDate" class="form-control" value="<?= esc($receipt['receipt_date']) ?>">
                <div class="invalid-feedback" data-for="receipt_date"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Amount <span class="req">*</span></label>
                <input type="number" name="amount" id="fAmount" class="form-control" step="0.01" min="0.01" inputmode="decimal" value="<?= number_format((float) $receipt['amount'], 2, '.', '') ?>">
                <div class="invalid-feedback" data-for="amount"></div>
                <div class="form-text">Room available for this receipt: ₹<?= number_format($roomForThis, 2) ?>.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Payment Mode <span class="req">*</span></label>
                <select name="payment_method" id="fMethod" class="form-control no-search">
                    <?php foreach ($methodLabel as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $receipt['payment_method'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6" id="fBankRow">
                <label class="form-label">Receive Into Bank Account <span class="req">*</span></label>
                <select name="bank_account_id" id="fBank" class="form-control" data-placeholder="Select bank account">
                    <option value=""></option>
                    <?php foreach ($bank_accounts as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === (int) ($receipt['bank_account_id'] ?? 0) ? 'selected' : '' ?>><?= esc($b['bank_name'] . ' - ' . $b['account_name'] . ' (' . $b['account_number'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select the company bank account where the loan amount was received. This is not the lender's bank account.</div>
                <div class="invalid-feedback" data-for="bank_account_id"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Reference No</label>
                <input type="text" name="reference_no" id="fRef" class="form-control" maxlength="100" autocomplete="off" value="<?= esc($receipt['reference_no'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Remarks</label>
                <input type="text" name="remarks" id="fRemarks" class="form-control" autocomplete="off" value="<?= esc($receipt['remarks'] ?? '') ?>">
            </div>
        </div>

        <div id="formErrors" class="alert alert-danger mt-3" style="display:none;"></div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn-save" id="btnSave"><i class="bi bi-check-lg"></i> Update Receipt</button>
            <a href="<?= base_url('loans/view/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-x"></i> Cancel</a>
        </div>
    </div>
</div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
	var BANK_METHODS = <?= json_encode($bank_methods) ?>;
	var ROOM = <?= json_encode($roomForThis) ?>;
	var URL_UPDATE = "<?= base_url('loans/receipts/update/' . (int) $receipt['id']) ?>";
	var URL_LOAN = "<?= base_url('loans/view/' . $loan['id']) ?>";
	var saving = false;

	function money(n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function decimals(v) { var i = v.indexOf('.'); return i < 0 ? 0 : v.length - i - 1; }
	function bankMethod() { return BANK_METHODS.indexOf($('#fMethod').val()) !== -1; }

	function syncBankRow() { $('#fBankRow').toggle(bankMethod()); }

	function validate() {
		var err = {}, v;

		if (!$('#fDate').val()) err.receipt_date = 'Receipt date is required.';

		v = $.trim($('#fAmount').val());
		if (v === '' || isNaN(v)) err.amount = 'Enter the amount.';
		else if (+v <= 0) err.amount = 'Amount must be greater than zero.';
		else if (decimals(v) > 2) err.amount = 'No more than two decimal places.';
		else if (+v > ROOM + 0.004) err.amount = 'More than the room available for this receipt (' + money(ROOM) + ').';

		if (bankMethod() && !$('#fBank').val()) err.bank_account_id = 'A bank account is required for Bank, Cheque and UPI receipts.';

		return err;
	}

	var touched = {};
	var serverErr = {};

	function refresh(showAll) {
		var err = validate();
		$('#receiptForm .invalid-feedback').each(function () {
			var k = $(this).data('for'),
				msg = (err[k] && (showAll || touched[k])) ? err[k] : (serverErr[k] || ''),
				show = msg !== '';
			$(this).text(msg).toggle(show);
			$('#receiptForm [name="' + k + '"]').toggleClass('is-invalid', show).attr('aria-invalid', show ? 'true' : null);
		});
		$('#btnSave').prop('disabled', saving);
		return err;
	}

	function fieldFor(msg) {
		if (/amount|receipts to|sanctioned/i.test(msg)) return 'amount';
		if (/receipt date/i.test(msg)) return 'receipt_date';
		if (/bank account/i.test(msg)) return 'bank_account_id';
		return null;
	}

	function scrollToError() {
		var $bad = $('#receiptForm .is-invalid').filter(function () { return $(this).closest('[class*="col-"]').is(':visible'); }).first(),
			$focus = $bad.hasClass('select2-hidden-accessible') ? $bad.next('.select2-container').find('.select2-selection') : $bad,
			$anchor = $bad.length ? ($bad.hasClass('select2-hidden-accessible') ? $bad.next('.select2-container') : $bad) : $('#formErrors:visible');

		if (!$anchor.length) return;
		$('html, body').animate({ scrollTop: Math.max(0, $anchor.offset().top - 90) }, 200);
		if ($focus.length && $focus[0].focus) $focus[0].focus({ preventScroll: true });
	}

	function fail(xhr) {
		var r = xhr.responseJSON, msgs = (r && r.errors) ? r.errors : ['The receipt could not be updated. Please try again.'];
		var $b = $('#formErrors').empty();
		$.each(msgs, function (_, m) { $b.append($('<div>').text(m)); });
		$b.show();
		serverErr = {};
		$.each(msgs, function (_, m) { var k = fieldFor(m); if (k && !serverErr[k]) serverErr[k] = m; });
		saving = false;
		refresh(true);
		scrollToError();
	}

	$('#fMethod').on('change', function () { delete serverErr.bank_account_id; syncBankRow(); refresh(false); });
	$('#fBank').on('change', function () { touched.bank_account_id = true; delete serverErr.bank_account_id; refresh(false); });
	$('#fDate, #fAmount').on('input change', function () {
		var key = ({ fDate: 'receipt_date', fAmount: 'amount' })[this.id];
		touched[key] = true;
		delete serverErr[key];
		refresh(false);
	});

	$('#receiptForm').on('submit', function (ev) {
		ev.preventDefault();
		if (saving) return;
		if (Object.keys(refresh(true)).length) { scrollToError(); return; }
		saving = true;
		$('#btnSave').prop('disabled', true);
		$('#formErrors').hide();

		$.ajax({
			url: URL_UPDATE,
			method: 'POST',
			dataType: 'json',
			data: $(this).serialize()
		}).done(function (r) {
			try { sessionStorage.setItem('loanFlash', r.message || 'Receipt updated.'); } catch (x) {}
			window.location.href = URL_LOAN;
		}).fail(fail);
	});

	syncBankRow();
	refresh(false);
})();
</script>
<?= $this->endSection() ?>
