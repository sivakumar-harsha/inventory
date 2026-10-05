<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$typeLabels = \App\Models\LoanTypeModel::labels();
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
        <li class="breadcrumb-item active" aria-current="page">New Loan</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-cash-coin me-2"></i>New Loan</span>
</div>

<form id="loanForm" novalidate>
<div class="card-custom">
    <div class="card-custom-header">Loan Information</div>
    <div class="card-custom-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Loan Number</label>
                <input type="text" id="loanNo" class="form-control" value="<?= esc($next_loan_no) ?>" readonly>
                <div class="form-text">Assigned automatically when the loan is saved.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Lender Name <span class="req">*</span></label>
                <input type="text" name="lender_name" id="lenderName" class="form-control" maxlength="150" autocomplete="off">
                <div class="invalid-feedback" data-for="lender_name"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Loan Type <span class="req">*</span></label>
                <div class="d-flex align-items-start gap-2">
                    <div class="flex-grow-1" style="min-width:0;">
                        <select name="loan_type" id="loanType" class="form-control no-search">
                            <?php foreach ($loan_types as $t): ?>
                            <option value="<?= esc($t) ?>"><?= esc($typeLabels[$t] ?? $t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn-cancel" id="quickLoanTypeBtn" title="Add Loan Type" aria-label="Add Loan Type" style="padding:0;width:38px;height:38px;justify-content:center;flex:0 0 auto;"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Account Number</label>
                <input type="text" name="account_number" id="accountNumber" class="form-control" maxlength="50" autocomplete="off" placeholder="Loan account number at the lender">
                <div class="invalid-feedback" data-for="account_number"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sanctioned Amount <span class="req">*</span></label>
                <input type="number" name="sanctioned_amount" id="sanctionedAmount" class="form-control" step="0.01" min="0" inputmode="decimal">
                <div class="invalid-feedback" data-for="sanctioned_amount"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Loan Date / Start Date <span class="req">*</span></label>
                <input type="date" name="start_date" id="startDate" class="form-control">
                <div class="invalid-feedback" data-for="start_date"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Interest Rate (% per year)</label>
                <input type="number" name="interest_rate" id="interestRate" class="form-control" step="0.001" min="0" max="100" inputmode="decimal">
                <div class="form-text">Optional, for information only.</div>
                <div class="invalid-feedback" data-for="interest_rate"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tenure (Months)</label>
                <input type="number" name="tenure_months" id="tenureMonths" class="form-control" step="1" min="1" max="600" inputmode="numeric">
                <div class="form-text">Optional, for information only.</div>
                <div class="invalid-feedback" data-for="tenure_months"></div>
            </div>
            <div class="col-12">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" id="remarks" class="form-control" rows="2"></textarea>
            </div>
        </div>

        <div id="formErrors" class="alert alert-danger mt-3" style="display:none;"></div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" id="btnSave" class="btn-save" disabled><i class="bi bi-save"></i> Save Loan</button>
            <a href="<?= base_url('loans') ?>" class="btn-cancel"><i class="bi bi-x"></i> Cancel</a>
        </div>
        <div class="form-text mt-2" id="saveHint">Fill in all required fields to enable Save.</div>
    </div>
</div>
</form>

<div class="modal fade" id="quickLoanTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Loan Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Loan Type Name <span class="req">*</span></label>
                <input type="text" id="quickLoanTypeName" class="form-control" maxlength="100" autocomplete="off">
                <div class="invalid-feedback" id="quickLoanTypeName_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save" id="quickLoanTypeSave">Save Loan Type</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
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

		v = val('#sanctionedAmount');
		if (v === '' || isNaN(v)) e.sanctioned_amount = 'Enter the sanctioned amount.';
		else if (+v <= 0) e.sanctioned_amount = 'Sanctioned amount must be greater than zero.';
		else if (+v > MAX_AMOUNT) e.sanctioned_amount = 'Sanctioned amount is too large.';
		else if (decimals(v) > 2) e.sanctioned_amount = 'No more than two decimal places.';

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
			url: "<?= base_url('loans/store') ?>",
			type: 'POST',
			dataType: 'json',
			data: $('#loanForm').serialize()
		}).done(function (resp) {
			if (resp.status) {
				try { sessionStorage.setItem('loanFlash', 'Loan ' + resp.loan_no + ' saved successfully.'); } catch (x) {}
				window.location.href = "<?= base_url('loans/view/') ?>" + resp.id;
			} else {
				$('#formErrors').html((resp.errors || ['Failed to save loan.']).join('<br>')).show();
				$btn.prop('disabled', false);
			}
		}).fail(function (xhr) {
			var d = xhr.responseJSON;
			$('#formErrors').html(((d && d.errors) || ['A network error occurred.']).join('<br>')).show();
			$btn.prop('disabled', false);
		});
	});

	// Quick-add Loan Type (stores into the Loan Type master via loan-types/quick-store).
	var quickTypeModal = null;
	$('#quickLoanTypeBtn').on('click', function () {
		quickTypeModal = quickTypeModal || new bootstrap.Modal(document.getElementById('quickLoanTypeModal'));
		$('#quickLoanTypeName').val('').removeClass('is-invalid');
		$('#quickLoanTypeName_error').text('').css('display', 'none');
		quickTypeModal.show();
	});
	$('#quickLoanTypeModal').on('shown.bs.modal', function () { $('#quickLoanTypeName').trigger('focus'); });
	$('#quickLoanTypeName').on('keydown', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); $('#quickLoanTypeSave').trigger('click'); }
	});
	$('#quickLoanTypeSave').on('click', function () {
		var $btn = $(this), name = $.trim($('#quickLoanTypeName').val());
		function showErr(msg) {
			$('#quickLoanTypeName').addClass('is-invalid');
			$('#quickLoanTypeName_error').text(msg).css('display', 'block');
		}
		$('#quickLoanTypeName').removeClass('is-invalid');
		$('#quickLoanTypeName_error').text('').css('display', 'none');
		if (name === '') { showErr('Loan type name is required.'); return; }
		$btn.prop('disabled', true);
		$.ajax({ url: "<?= base_url('loan-types/quick-store') ?>", type: 'POST', dataType: 'json', data: { loan_type_name: name } })
			.done(function (resp) {
				if (resp.status) {
					var t = resp.loan_type;
					$('#loanType').append($('<option></option>').val(t.code).text(t.label)).val(t.code).trigger('change');
					quickTypeModal.hide();
				} else { showErr((resp.errors || []).join(' ') || 'Could not save the loan type.'); }
			})
			.fail(function (xhr) {
				var d = xhr.responseJSON;
				showErr(d && d.errors ? d.errors.join(' ') : 'Could not save the loan type. Please try again.');
			})
			.always(function () { $btn.prop('disabled', false); });
	});

	$(document).ready(function () { refresh(false); });
})();
</script>
<?= $this->endSection() ?>
