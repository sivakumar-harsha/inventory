<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$typeLabels = ['BANK' => 'Bank Loan', 'PERSONAL' => 'Personal', 'VEHICLE' => 'Vehicle', 'OD' => 'Overdraft', 'OTHER' => 'Other'];
$locked     = ! $schedule_editable;                // payments exist -> terms are frozen
$dis        = $locked ? 'disabled' : '';
$rate       = rtrim(rtrim(number_format((float) $loan['interest_rate'], 3, '.', ''), '0'), '.');

// Static figures for the summary card when there is no live preview.
$totalInterest = round(array_sum(array_column($emis, 'interest_amount')), 2);
$totalPayable  = round(array_sum(array_column($emis, 'emi_amount')), 2);
?>

<style>
	.ln-breadcrumb { margin-bottom: 8px; }
	.ln-breadcrumb .breadcrumb { margin-bottom: 0; font-size: .78rem; padding: 0; background: transparent; }
	.ln-breadcrumb .breadcrumb-item a { color: #2F7E8A; text-decoration: none; }
	.ln-breadcrumb .breadcrumb-item a:hover { text-decoration: underline; }
	.ln-breadcrumb .breadcrumb-item.active { color: #64748b; }

	/* Form + schedule on the left, sticky EMI summary on the right (desktop).
	   Below 992px everything stacks: form, then summary, then schedule. */
	.loan-grid {
		display: grid;
		gap: 16px;
		grid-template-columns: minmax(0, 1fr);
		grid-template-areas: "form" "summary" "schedule";
	}
	.loan-grid > * { min-width: 0; }
	.loan-area-form { grid-area: form; }
	.loan-area-summary { grid-area: summary; }
	.loan-area-schedule { grid-area: schedule; }
	@media (min-width: 992px) {
		.loan-grid {
			grid-template-columns: minmax(0, 1fr) 340px;
			grid-template-areas: "form summary" "schedule summary";
			align-items: start;
		}
		.loan-area-summary { align-self: stretch; }
		.loan-area-summary .card-custom { position: sticky; top: 12px; }
	}

	.ln-summary-table { width: 100%; font-size: .85rem; }
	.ln-summary-table td { padding: 6px 2px; border-bottom: 1px dashed #e2e8f0; }
	.ln-summary-table tr:last-child td { border-bottom: none; }
	.ln-summary-table td.amt { text-align: right; }
	.ln-summary-table tr.grand td { font-weight: 700; font-size: .95rem; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; }

	.ln-schedule-wrap { max-height: 420px; overflow: auto; }
	#schedTable { min-width: 720px; }
	#schedTable th, #schedTable td { padding: 6px 10px; font-size: .78rem; white-space: nowrap; }
	#schedTable thead th { position: sticky; top: 0; background: #f8fafc; z-index: 1; }
	#schedTable .num { text-align: right; }
	.ln-empty { text-align: center; color: #94a3b8; padding: 18px 10px; }

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
    <strong>Loan terms cannot be modified after EMI payments exist.</strong>
    The sanctioned amount, interest rate, tenure and start date are locked. The lender, remarks, bank account and end date can still be changed.
</div>
<?php endif; ?>

<form id="loanForm" novalidate>
<?php if ($locked): ?>
    <!-- Disabled inputs are not submitted, so the locked terms travel as hidden fields. -->
    <input type="hidden" name="sanctioned_amount" value="<?= esc(number_format((float) $loan['sanctioned_amount'], 2, '.', '')) ?>">
    <input type="hidden" name="interest_rate" value="<?= esc($rate) ?>">
    <input type="hidden" name="tenure_months" value="<?= (int) $loan['tenure_months'] ?>">
    <input type="hidden" name="start_date" value="<?= esc($loan['start_date']) ?>">
<?php endif; ?>
<div class="loan-grid">

    <!-- LOAN INFORMATION -->
    <div class="loan-area-form">
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
                    <div class="col-md-6" id="bankRow">
                        <label class="form-label">Bank Account <span class="req" id="bankReq">*</span></label>
                        <select name="bank_account_id" id="bankAccount" class="form-control" data-placeholder="Select bank account">
                            <option value=""></option>
                            <?php foreach ($bank_accounts as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= (int) $loan['bank_account_id'] === (int) $b['id'] ? 'selected' : '' ?>><?= esc($b['bank_name'] . ' - ' . $b['account_name'] . ' (' . $b['account_number'] . ')') ?><?= (int) $b['is_active'] === 1 ? '' : ' [inactive]' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback" data-for="bank_account_id"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" id="accountNumber" class="form-control" maxlength="50" autocomplete="off" placeholder="Loan account number at the lender" value="<?= esc($loan['account_number'] ?? '') ?>">
                        <div class="invalid-feedback" data-for="account_number"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sanctioned Amount <span class="req">*</span></label>
                        <input type="number" <?= $locked ? '' : 'name="sanctioned_amount"' ?> id="sanctionedAmount" class="form-control" step="0.01" min="0" inputmode="decimal" value="<?= esc(number_format((float) $loan['sanctioned_amount'], 2, '.', '')) ?>" <?= $dis ?>>
                        <div class="invalid-feedback" data-for="sanctioned_amount"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Interest Rate (% per year) <span class="req">*</span></label>
                        <input type="number" <?= $locked ? '' : 'name="interest_rate"' ?> id="interestRate" class="form-control" step="0.001" min="0" max="100" inputmode="decimal" value="<?= esc($rate) ?>" <?= $dis ?>>
                        <div class="invalid-feedback" data-for="interest_rate"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tenure (Months) <span class="req">*</span></label>
                        <input type="number" <?= $locked ? '' : 'name="tenure_months"' ?> id="tenureMonths" class="form-control" step="1" min="1" max="600" inputmode="numeric" value="<?= (int) $loan['tenure_months'] ?>" <?= $dis ?>>
                        <div class="invalid-feedback" data-for="tenure_months"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Date <span class="req">*</span></label>
                        <input type="date" <?= $locked ? '' : 'name="start_date"' ?> id="startDate" class="form-control" value="<?= esc($loan['start_date']) ?>" <?= $dis ?>>
                        <div class="invalid-feedback" data-for="start_date"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="endDate" class="form-control" value="<?= esc($loan['end_date'] ?? '') ?>">
                        <div class="form-text"><?= $locked ? 'Can still be adjusted.' : 'Follows the schedule unless you set it yourself; clear it to go back to the calculated date.' ?></div>
                        <div class="invalid-feedback" data-for="end_date"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="2"><?= esc($loan['remarks'] ?? '') ?></textarea>
                    </div>
                </div>

                <div id="formErrors" class="alert alert-danger mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- EMI SUMMARY (sticky on desktop) -->
    <div class="loan-area-summary">
        <div class="card-custom">
            <div class="card-custom-header"><?= $locked ? 'EMI Summary' : 'EMI Preview' ?></div>
            <div class="card-custom-body">
                <table class="ln-summary-table">
                    <?php if ($locked): ?>
                    <tr class="grand"><td>Monthly EMI</td><td class="amt">₹<?= number_format((float) $loan['emi_amount'], 2) ?></td></tr>
                    <tr><td>Total Interest</td><td class="amt">₹<?= number_format($totalInterest, 2) ?></td></tr>
                    <tr><td>Total Payable</td><td class="amt">₹<?= number_format($totalPayable, 2) ?></td></tr>
                    <tr><td>Loan End Date</td><td class="amt"><?= $loan['end_date'] ? date('d-m-Y', strtotime($loan['end_date'])) : '—' ?></td></tr>
                    <tr><td>Outstanding Principal</td><td class="amt">₹<?= number_format((float) $loan['outstanding_principal'], 2) ?></td></tr>
                    <?php else: ?>
                    <tr class="grand"><td>Monthly EMI</td><td class="amt">₹<span id="sumEmi">—</span></td></tr>
                    <tr><td>Total Interest</td><td class="amt">₹<span id="sumInterest">—</span></td></tr>
                    <tr><td>Total Payable</td><td class="amt">₹<span id="sumPayable">—</span></td></tr>
                    <tr><td>Loan End Date</td><td class="amt" id="sumEnd">—</td></tr>
                    <tr><td>Outstanding Principal</td><td class="amt">₹<span id="sumOutstanding">—</span></td></tr>
                    <?php endif; ?>
                </table>

                <div class="mt-3">
                    <button type="submit" id="btnSave" class="btn-save w-100"><i class="bi bi-save"></i> Update Loan</button>
                    <a href="<?= base_url('loans') ?>" class="btn-cancel w-100 mt-2 d-block text-center"><i class="bi bi-x"></i> Cancel</a>
                    <div class="form-text mt-2" id="saveHint" style="display:none;">Fix the highlighted fields to enable Update.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- EMI SCHEDULE PREVIEW (only while the terms can still change) -->
    <?php if (! $locked): ?>
    <div class="loan-area-schedule">
        <div class="card-custom">
            <div class="card-custom-header d-flex align-items-center justify-content-between">
                <span>EMI Schedule Preview</span>
                <span id="schedSpinner" style="display:none;"><span class="spinner-border spinner-border-sm" role="status"></span> <small>Calculating...</small></span>
            </div>
            <div class="card-custom-body">
                <div class="form-text mb-2">Saving rebuilds the schedule from these terms (no payments have been recorded yet).</div>
                <div id="previewError" class="alert alert-warning py-2" style="display:none;"></div>
                <div class="ln-schedule-wrap">
                    <table id="schedTable" class="table-custom">
                        <thead>
                            <tr>
                                <th>EMI</th>
                                <th>Due Date</th>
                                <th class="num">Opening Balance</th>
                                <th class="num">Principal</th>
                                <th class="num">Interest</th>
                                <th class="num">EMI</th>
                                <th class="num">Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody id="schedBody">
                            <tr><td colspan="7" class="ln-empty">Calculating schedule...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
	var LOCKED = <?= $locked ? 'true' : 'false' ?>;
	var MAX_AMOUNT = 9999999999999.99, MAX_TENURE = 600, MAX_RATE = 100;
	var BANK_REQUIRED = <?= json_encode($bank_required) ?>;
	var PREVIEW_URL = "<?= base_url('loans/generate-schedule') ?>";
	var STORED_END = <?= json_encode($loan['end_date'] ?? '') ?>;
	var previewTimer = null, previewXhr = null, previewSeq = 0;
	var endAuto = true, firstPreview = true, lastEnd = '';

	var FIELDS = {
		lender_name: '#lenderName',
		bank_account_id: '#bankAccount',
		account_number: '#accountNumber',
		sanctioned_amount: '#sanctionedAmount',
		interest_rate: '#interestRate',
		tenure_months: '#tenureMonths',
		start_date: '#startDate',
		end_date: '#endDate'
	};
	var TERM_KEYS = ['sanctioned_amount', 'interest_rate', 'tenure_months', 'start_date'];

	function money(n) {
		return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	function dmy(s) {
		var p = String(s).split('-');
		return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : s;
	}
	function decimals(v) {
		var i = v.indexOf('.');
		return i < 0 ? 0 : v.length - i - 1;
	}
	function val(sel) { return $.trim($(sel).val() || ''); }
	function bankRequired() { return BANK_REQUIRED.indexOf($('#loanType').val()) !== -1; }

	// ---------- validation (client side only; the server re-validates) ----------
	function fieldErrors() {
		var e = {}, v;

		v = val('#lenderName');
		if (!v) e.lender_name = 'Lender name is required.';
		else if (v.length > 150) e.lender_name = 'Lender name cannot be longer than 150 characters.';

		if (bankRequired() && !$('#bankAccount').val()) e.bank_account_id = 'A bank account is required for Bank and Overdraft loans.';

		if (val('#accountNumber').length > 50) e.account_number = 'Account number cannot be longer than 50 characters.';

		if (!LOCKED) {
			v = val('#sanctionedAmount');
			if (v === '' || isNaN(v)) e.sanctioned_amount = 'Enter the sanctioned amount.';
			else if (+v <= 0) e.sanctioned_amount = 'Sanctioned amount must be greater than zero.';
			else if (+v > MAX_AMOUNT) e.sanctioned_amount = 'Sanctioned amount is too large.';
			else if (decimals(v) > 2) e.sanctioned_amount = 'No more than two decimal places.';

			v = val('#interestRate');
			if (v === '' || isNaN(v)) e.interest_rate = 'Enter the interest rate (0 if none).';
			else if (+v < 0) e.interest_rate = 'Interest rate cannot be negative.';
			else if (+v > MAX_RATE) e.interest_rate = 'Interest rate cannot be more than ' + MAX_RATE + '%.';
			else if (decimals(v) > 3) e.interest_rate = 'No more than three decimal places.';

			v = val('#tenureMonths');
			if (v === '') e.tenure_months = 'Enter the tenure in months.';
			else if (!/^\d+$/.test(v)) e.tenure_months = 'Tenure must be a whole number of months.';
			else if (+v < 1) e.tenure_months = 'Tenure must be at least 1 month.';
			else if (+v > MAX_TENURE) e.tenure_months = 'Tenure cannot be more than ' + MAX_TENURE + ' months.';

			if (!val('#startDate')) e.start_date = 'Start date is required.';
		}

		if (val('#endDate') && val('#startDate') && val('#endDate') < val('#startDate')) e.end_date = 'End date cannot be before the start date.';

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

	function termsValid(e) {
		return TERM_KEYS.every(function (k) { return !e[k]; });
	}

	// ---------- bank account row: BANK / OD need one; others only keep it if chosen ----------
	function syncBankRow() {
		var req = bankRequired();
		$('#bankReq').toggle(req);
		$('#bankRow').toggle(req || !!$('#bankAccount').val());
	}

	// ---------- live EMI preview (the server is the only place the EMI is calculated) ----------
	function clearPreview(message) {
		if (previewXhr) previewXhr.abort();
		previewSeq++;
		clearTimeout(previewTimer);
		$('#schedSpinner').hide();
		$('#previewError').hide();
		$('#sumEmi, #sumInterest, #sumPayable, #sumOutstanding').text('—');
		$('#sumEnd').text('—');
		$('#schedBody').html('<tr><td colspan="7" class="ln-empty">' + message + '</td></tr>');
	}

	function renderPreview(r) {
		var rows = [];
		$.each(r.schedule, function (i, s) {
			rows.push('<tr><td>' + s.emi_no + '</td><td>' + dmy(s.due_date) + '</td>'
				+ '<td class="num">' + money(s.opening_balance) + '</td>'
				+ '<td class="num">' + money(s.principal_amount) + '</td>'
				+ '<td class="num">' + money(s.interest_amount) + '</td>'
				+ '<td class="num">' + money(s.emi_amount) + '</td>'
				+ '<td class="num">' + money(s.closing_balance) + '</td></tr>');
		});
		$('#schedBody').html(rows.join(''));
		$('#sumEmi').text(money(r.emi_amount));
		$('#sumInterest').text(money(r.total_interest));
		$('#sumPayable').text(money(r.total_payable));
		$('#sumEnd').text(dmy(r.end_date));
		$('#sumOutstanding').text(money(r.schedule[0].opening_balance));

		// A stored end date that differs from the schedule was set by hand: keep it.
		if (firstPreview) {
			firstPreview = false;
			if (STORED_END && STORED_END !== r.end_date) endAuto = false;
		}
		lastEnd = r.end_date;
		if (endAuto) $('#endDate').val(r.end_date);
		refresh(false);
	}

	function runPreview() {
		if (previewXhr) previewXhr.abort();
		var seq = ++previewSeq;
		$('#schedSpinner').show();
		$('#previewError').hide();

		previewXhr = $.ajax({
			url: PREVIEW_URL,
			type: 'POST',
			dataType: 'json',
			data: {
				sanctioned_amount: val('#sanctionedAmount'),
				interest_rate: val('#interestRate'),
				tenure_months: val('#tenureMonths'),
				start_date: val('#startDate')
			}
		}).done(function (r) {
			if (seq !== previewSeq) return;
			if (r.status) renderPreview(r);
		}).fail(function (xhr) {
			if (seq !== previewSeq || xhr.statusText === 'abort') return;
			var d = xhr.responseJSON;
			clearPreview('Schedule not available for these terms.');
			$('#previewError').text((d && d.errors && d.errors.join(' ')) || 'Could not calculate the schedule. Please try again.').show();
		}).always(function () {
			if (seq === previewSeq) $('#schedSpinner').hide();
		});
	}

	function queuePreview(delay) {
		clearTimeout(previewTimer);
		if (!termsValid(fieldErrors())) {
			clearPreview('Enter the amount, interest rate, tenure and start date to see the schedule.');
			return;
		}
		$('#schedSpinner').show();
		previewTimer = setTimeout(runPreview, delay);
	}

	// ---------- events ----------
	$.each(FIELDS, function (key, sel) {
		$(sel).on('blur change', function () { $(this).data('touched', true); refresh(false); });
	});

	$('#lenderName, #accountNumber, #bankAccount, #endDate').on('input change', function () {
		refresh(false);
	});

	if (!LOCKED) {
		$('#sanctionedAmount, #interestRate, #tenureMonths, #startDate').on('input change', function () {
			refresh(false);
			queuePreview(400);
		});

		// Typing an end date takes it off auto; clearing it puts it back on auto.
		$('#endDate').on('change', function () {
			if (this.value) {
				endAuto = false;
			} else {
				endAuto = true;
				if (lastEnd) this.value = lastEnd;
			}
			refresh(false);
		});
	}

	$('#loanType').on('change', function () {
		syncBankRow();
		refresh(false);
	});
	$('#bankAccount').on('change', syncBankRow);

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

	$(document).ready(function () {
		syncBankRow();
		refresh(false);
		if (!LOCKED) queuePreview(0);
	});
})();
</script>
<?= $this->endSection() ?>
