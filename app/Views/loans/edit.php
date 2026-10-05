<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$typeLabels = \App\Models\LoanTypeModel::labels();
$locked     = ! $schedule_editable;                // payments exist -> only the sanctioned amount is frozen
$rate       = (float) $loan['interest_rate'] > 0 ? rtrim(rtrim(number_format((float) $loan['interest_rate'], 3, '.', ''), '0'), '.') : '';
$tenure     = (int) $loan['tenure_months'] > 0 ? (int) $loan['tenure_months'] : '';
?>

<style>
	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	.form-label .req { color: #dc2626; }
	.invalid-feedback { font-size: .75rem; }
</style>

<nav aria-label="breadcrumb" class="ln-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Accounts</li>
        <li class="breadcrumb-item"><a href="<?= base_url('loans') ?>">Loan Management</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Loan</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span><i class="bi bi-cash-coin me-2"></i>Edit Loan <?= esc($loan['loan_no']) ?></span>
    <a href="<?= base_url('loans/view/' . $loan['id']) ?>" class="btn-cancel"><i class="bi bi-eye"></i> View Loan</a>
</div>

<?php if ($locked): ?>
<div class="alert alert-warning">
    <i class="bi bi-lock-fill me-2"></i>
    <strong>The sanctioned amount cannot be changed once payments are recorded.</strong>
    Everything else on the loan can still be edited. Recorded payments are not modified.
</div>
<?php endif; ?>

<form id="loanForm" novalidate>
<?php if ($locked): ?>
    <!-- A disabled input is not submitted, so the locked amount travels as a hidden field. -->
    <input type="hidden" name="sanctioned_amount" value="<?= esc(number_format((float) $loan['sanctioned_amount'], 2, '.', '')) ?>">
<?php endif; ?>
<div class="card-custom">
    <div class="card-custom-header">Loan Information</div>
    <div class="card-custom-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Loan Number</label>
                <input type="text" id="loanNo" class="form-control" value="<?= esc($loan['loan_no']) ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Lender Name <span class="req">*</span></label>
                <input type="text" name="lender_name" id="lenderName" class="form-control" maxlength="150" autocomplete="off" value="<?= esc($loan['lender_name']) ?>">
                <div class="invalid-feedback" data-for="lender_name"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Loan Type <span class="req">*</span></label>
                <select name="loan_type" id="loanType" class="form-control no-search">
                    <?php foreach ($loan_types as $t): ?>
                    <option value="<?= esc($t) ?>" <?= $loan['loan_type'] === $t ? 'selected' : '' ?>><?= esc($typeLabels[$t] ?? $t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Account Number</label>
                <input type="text" name="account_number" id="accountNumber" class="form-control" maxlength="50" autocomplete="off" placeholder="Loan account number at the lender" value="<?= esc($loan['account_number'] ?? '') ?>">
                <div class="invalid-feedback" data-for="account_number"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sanctioned Amount <span class="req">*</span></label>
                <input type="number" <?= $locked ? '' : 'name="sanctioned_amount"' ?> id="sanctionedAmount" class="form-control" step="0.01" min="0" inputmode="decimal" value="<?= esc(number_format((float) $loan['sanctioned_amount'], 2, '.', '')) ?>" <?= $locked ? 'disabled' : '' ?>>
                <div class="invalid-feedback" data-for="sanctioned_amount"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Loan Date / Start Date <span class="req">*</span></label>
                <input type="date" name="start_date" id="startDate" class="form-control" value="<?= esc($loan['start_date']) ?>">
                <div class="invalid-feedback" data-for="start_date"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Interest Rate (% per year)</label>
                <input type="number" name="interest_rate" id="interestRate" class="form-control" step="0.001" min="0" max="100" inputmode="decimal" value="<?= esc((string) $rate) ?>">
                <div class="form-text">Optional, for information only.</div>
                <div class="invalid-feedback" data-for="interest_rate"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tenure (Months)</label>
                <input type="number" name="tenure_months" id="tenureMonths" class="form-control" step="1" min="1" max="600" inputmode="numeric" value="<?= esc((string) $tenure) ?>">
                <div class="form-text">Optional, for information only.</div>
                <div class="invalid-feedback" data-for="tenure_months"></div>
            </div>
            <div class="col-12">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" id="remarks" class="form-control" rows="2"><?= esc($loan['remarks'] ?? '') ?></textarea>
            </div>
        </div>

        <div id="formErrors" class="alert alert-danger mt-3" style="display:none;"></div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" id="btnSave" class="btn-save"><i class="bi bi-save"></i> Update Loan</button>
            <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-x"></i> Cancel</a>
        </div>
        <div class="form-text mt-2" id="saveHint" style="display:none;">Fix the highlighted fields to enable Update.</div>
    </div>
</div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
	var LOCKED = <?= $locked ? 'true' : 'false' ?>;
	var MAX_AMOUNT = 9999999999999.99, MAX_TENURE = 600, MAX_RATE = 100;

	var FIELDS = {
		lender_name: '#lenderName',
		account_number: '#accountNumber',
		sanctioned_amount: '#sanctionedAmount',
		interest_rate: '#interestRate',
		tenure_months: '#tenureMonths',
		start_date: '#startDate'
	};

	function decimals(v) {
		var i = v.indexOf('.');
		return i < 0 ? 0 : v.length - i - 1;
	}
	function val(sel) { return $.trim($(sel).val() || ''); }

	// ---------- validation (client side only; the server re-validates) ----------
	function fieldErrors() {
		var e = {}, v;

		v = val('#lenderName');
		if (!v) e.lender_name = 'Lender name is required.';
		else if (v.length > 150) e.lender_name = 'Lender name cannot be longer than 150 characters.';

		if (val('#accountNumber').length > 50) e.account_number = 'Account number cannot be longer than 50 characters.';

		if (!LOCKED) {
			v = val('#sanctionedAmount');
			if (v === '' || isNaN(v)) e.sanctioned_amount = 'Enter the sanctioned amount.';
			else if (+v <= 0) e.sanctioned_amount = 'Sanctioned amount must be greater than zero.';
			else if (+v > MAX_AMOUNT) e.sanctioned_amount = 'Sanctioned amount is too large.';
			else if (decimals(v) > 2) e.sanctioned_amount = 'No more than two decimal places.';
		}

		// interest rate and tenure are optional
		v = val('#interestRate');
		if (v !== '') {
			if (isNaN(v)) e.interest_rate = 'Interest rate must be a number.';
			else if (+v < 0) e.interest_rate = 'Interest rate cannot be negative.';
			else if (+v > MAX_RATE) e.interest_rate = 'Interest rate cannot be more than ' + MAX_RATE + '%.';
			else if (decimals(v) > 3) e.interest_rate = 'No more than three decimal places.';
		}

		v = val('#tenureMonths');
		if (v !== '') {
			if (!/^\d+$/.test(v)) e.tenure_months = 'Tenure must be a whole number of months.';
			else if (+v > MAX_TENURE) e.tenure_months = 'Tenure cannot be more than ' + MAX_TENURE + ' months.';
		}

		if (!val('#startDate')) e.start_date = 'Loan date / start date is required.';

		return e;
	}

	function showErrors(e, showAll) {
		$.each(FIELDS, function (key, sel) {
			var $el = $(sel), $fb = $('.invalid-feedback[data-for="' + key + '"]');
			var show = e[key] && (showAll || $el.data('touched'));
			$el.toggleClass('is-invalid', !!show);
			$fb.text(show ? e[key] : '').css('display', show ? 'block' : 'none');
		});
	}

	function refresh(showAll) {
		var e = fieldErrors(), invalid = Object.keys(e).length > 0;
		showErrors(e, showAll);
		$('#btnSave').prop('disabled', invalid);
		$('#saveHint').toggle(invalid);
		return e;
	}

	// ---------- events ----------
	$.each(FIELDS, function (key, sel) {
		$(sel).on('blur change', function () { $(this).data('touched', true); refresh(false); });
		$(sel).on('input change', function () { refresh(false); });
	});

	$('#loanForm').on('submit', function (ev) {
		ev.preventDefault();
		$('#formErrors').hide();

		var e = refresh(true);
		if (Object.keys(e).length) return;

		var $btn = $('#btnSave').prop('disabled', true);

		$.ajax({
			url: "<?= base_url('loans/update/' . $loan['id']) ?>",
			type: 'POST',
			dataType: 'json',
			data: $('#loanForm').serialize()
		}).done(function (resp) {
			if (resp.status) {
				try { sessionStorage.setItem('loanFlash', 'Loan ' + resp.loan_no + ' updated successfully.'); } catch (x) {}
				window.location.href = "<?= base_url('loans/view/' . $loan['id']) ?>";
			} else {
				$('#formErrors').html((resp.errors || ['Failed to update loan.']).join('<br>')).show();
				$btn.prop('disabled', false);
			}
		}).fail(function (xhr) {
			var d = xhr.responseJSON;
			$('#formErrors').html(((d && d.errors) || ['A network error occurred.']).join('<br>')).show();
			$btn.prop('disabled', false);
		});
	});

	$(document).ready(function () { refresh(false); });
})();
</script>
<?= $this->endSection() ?>
