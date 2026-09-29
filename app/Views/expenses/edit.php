<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= $this->include('expenses/partials/ui_styles') ?>

<style>
.invalid-feedback-text { font-size: .8rem; color: var(--danger, #dc2626); margin-top: 6px; display: flex; align-items: center; gap: 4px; }
.invalid-feedback-text::before { content: "\f33a"; font-family: "bootstrap-icons"; font-size: .85rem; }
.form-control.is-invalid { border-color: #dc2626; background: #fef2f2; }

#expenseNo[readonly] { background: #f1f5f9; color: #64748b; font-weight: 600; letter-spacing: .02em; cursor: not-allowed; }

.exp-summary-card { position: sticky; top: 12px; }
.exp-summary-card .exp-summary-amount { font-size: 1.6rem; font-weight: 700; color: #2F7E8A; }
.exp-summary-card .exp-summary-row { display: flex; justify-content: space-between; font-size: .8rem; padding: 4px 0; border-bottom: 1px dashed #e2e8f0; }
.exp-summary-card .exp-summary-row:last-child { border-bottom: none; }

#cancelledBanner { display: flex; align-items: center; gap: 8px; border-left: 4px solid #d97706; }
</style>

<nav aria-label="breadcrumb" class="exp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item"><a href="<?= base_url('expenses') ?>">Expenses</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Expense</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-credit-card me-2"></i>Edit Expense</span>
    <a href="<?= base_url('expenses') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div id="cancelledBanner" class="alert alert-warning d-none" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    This expense has been cancelled and cannot be edited.
</div>

<div id="formErrorBanner" class="alert alert-danger d-none" role="alert"></div>

<div class="row">
<div class="col-lg-8">
<form id="expenseForm">
    <div class="card-custom mb-3">
        <div class="card-custom-header">Expense Information</div>
        <div class="card-custom-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-section">
                        <label class="form-label">Expense No</label>
                        <input type="text" id="expenseNo" class="form-control" value="Loading..." readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-section">
                        <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" id="expenseDate" class="form-control" required>
                        <div class="invalid-feedback-text d-none" id="expenseDate_error"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-section">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select id="categoryId" class="form-control" required>
                            <option value="">-- Select Category --</option>
                        </select>
                        <div class="invalid-feedback-text d-none" id="categoryId_error"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-section">
                        <label class="form-label">Project</label>
                        <select id="projectId" class="form-control">
                            <option value="">-- No Project (General) --</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-section">
                        <label class="form-label">Paid To <span class="text-danger">*</span></label>
                        <input type="text" id="paidTo" class="form-control" maxlength="150" required>
                        <div class="invalid-feedback-text d-none" id="paidTo_error"></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-section">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select id="paymentMethod" class="form-control no-search" required>
                            <option value="CASH">Cash</option>
                            <option value="BANK">Bank</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="UPI">UPI</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-section">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" id="amount" class="form-control" step="0.01" min="0.01" required>
                        <div class="invalid-feedback-text d-none" id="amount_error"></div>
                    </div>
                </div>
            </div>

            <div class="form-section d-none" id="bankAccountSection">
                <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                <select id="bankAccountId" class="form-control">
                    <option value="">-- Select Bank Account --</option>
                </select>
                <div class="invalid-feedback-text d-none" id="bankAccountId_error"></div>
            </div>

            <div class="form-section">
                <label class="form-label">Remarks</label>
                <textarea id="remarks" class="form-control"></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn-save" id="saveExpenseBtn" disabled>
            <span id="saveExpenseSpinner" class="spinner-border spinner-border-sm d-none"></span>
            <i class="bi bi-save"></i> Save Changes
        </button>
        <a href="<?= base_url('expenses') ?>" class="btn-cancel"><i class="bi bi-x"></i> Cancel</a>
    </div>
</form>
</div>

<div class="col-lg-4">
    <div class="card-custom exp-summary-card">
        <div class="card-custom-header"><i class="bi bi-receipt me-2"></i>Summary</div>
        <div class="card-custom-body">
            <div class="exp-summary-amount" id="summaryAmount">0.00</div>
            <div class="exp-summary-row"><span>Category</span><strong id="summaryCategory">&mdash;</strong></div>
            <div class="exp-summary-row"><span>Project</span><strong id="summaryProject">General</strong></div>
            <div class="exp-summary-row"><span>Payment Method</span><strong id="summaryMethod">Cash</strong></div>
            <div class="exp-summary-row"><span>Date</span><strong id="summaryDate">&mdash;</strong></div>
        </div>
    </div>
</div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var baseUrl = "<?= base_url() ?>";
var expenseId = <?= (int) $id ?>;
var isCancelled = false;

function isBankMethod(method) {
    return ['BANK', 'CHEQUE', 'UPI'].indexOf(method) !== -1;
}

function toggleBankAccount() {
    var bank = isBankMethod($('#paymentMethod').val());
    $('#bankAccountSection').toggleClass('d-none', !bank);
    if (!bank) { $('#bankAccountId').val(''); }
    validateForm();
}
$('#paymentMethod').on('change', toggleBankAccount);

function updateSummary() {
    var amount = parseFloat($('#amount').val());
    $('#summaryAmount').text(isNaN(amount) ? '0.00' : amount.toFixed(2));
    $('#summaryCategory').text($('#categoryId option:selected').text() !== '-- Select Category --' ? $('#categoryId option:selected').text() : '—');
    $('#summaryProject').text($('#projectId option:selected').text() || 'General');
    $('#summaryMethod').text($('#paymentMethod option:selected').text() || 'Cash');
    var d = $('#expenseDate').val();
    $('#summaryDate').text(d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB') : '—');
}
$('#expenseDate, #categoryId, #projectId, #paymentMethod, #amount').on('input change blur', updateSummary);

function fieldValid(el) {
    return el.val() !== null && String(el.val()).trim() !== '';
}

function validateForm() {
    if (isCancelled) {
        $('#saveExpenseBtn').prop('disabled', true);
        return false;
    }

    var valid = fieldValid($('#expenseDate')) &&
        fieldValid($('#categoryId')) &&
        fieldValid($('#paidTo')) &&
        fieldValid($('#paymentMethod')) &&
        parseFloat($('#amount').val()) > 0;

    if (isBankMethod($('#paymentMethod').val())) {
        valid = valid && fieldValid($('#bankAccountId'));
    }

    $('#saveExpenseBtn').prop('disabled', !valid);
    return valid;
}

$('#expenseDate, #paidTo, #amount').on('input blur', validateForm);
$('#categoryId, #projectId, #bankAccountId').on('change', validateForm);

function scrollToFirstInvalid() {
    var first = $('.is-invalid').first();
    if (first.length) {
        $('html, body').animate({ scrollTop: first.offset().top - 100 }, 300);
        first.trigger('focus');
    }
}

function clearFieldErrors() {
    $('.form-control').removeClass('is-invalid');
    $('.invalid-feedback-text').addClass('d-none').text('');
    $('#formErrorBanner').addClass('d-none').text('');
}

function lockFormForCancelled() {
    isCancelled = true;
    $('#cancelledBanner').removeClass('d-none');
    $('#expenseForm').find('input, select, textarea, button').prop('disabled', true);
}

function loadFormData() {
    $.ajax({ url: baseUrl + 'expenses/edit/' + expenseId, type: 'GET', dataType: 'json' }).done(function (resp) {
        if (!resp.status) {
            $('#formErrorBanner').text((resp.errors || ['Expense not found.']).join(' ')).removeClass('d-none');
            $('#expenseForm').find('input, select, textarea, button').prop('disabled', true);
            return;
        }

        var e = resp.expense;

        $('#expenseNo').val(e.expense_no);
        $('#expenseDate').val(e.expense_date);
        $('#paidTo').val(e.paid_to);
        $('#amount').val(parseFloat(e.amount).toFixed(2));
        $('#remarks').val(e.remarks || '');
        $('#paymentMethod').val(e.payment_method);

        var categorySelect = $('#categoryId');
        resp.categories.forEach(function (c) {
            categorySelect.append($('<option></option>').val(c.id).text(c.category_name));
        });
        categorySelect.val(e.category_id).trigger('change');

        var projectSelect = $('#projectId');
        resp.projects.forEach(function (p) {
            projectSelect.append($('<option></option>').val(p.id).text(p.name));
        });
        projectSelect.val(e.project_id || '').trigger('change');

        var bankSelect = $('#bankAccountId');
        resp.bank_accounts.forEach(function (b) {
            bankSelect.append($('<option></option>').val(b.id).text(b.bank_name + ' - ' + b.account_name + ' (' + b.account_number + ')'));
        });
        bankSelect.val(e.bank_account_id || '').trigger('change');

        toggleBankAccount();
        updateSummary();

        if (e.status === 'CANCELLED') {
            lockFormForCancelled();
        } else {
            validateForm();
        }
    }).fail(function () {
        $('#formErrorBanner').text('Failed to load expense. Please refresh the page.').removeClass('d-none');
    });
}

$('#expenseForm').on('submit', function (e) {
    e.preventDefault();
    if (isCancelled) return;

    clearFieldErrors();

    if (!validateForm()) {
        scrollToFirstInvalid();
        return;
    }

    var btn = $('#saveExpenseBtn');
    var spinner = $('#saveExpenseSpinner');
    btn.prop('disabled', true);
    spinner.removeClass('d-none');

    $.ajax({
        url: baseUrl + 'expenses/update/' + expenseId,
        type: 'POST',
        dataType: 'json',
        data: {
            expense_date: $('#expenseDate').val(),
            category_id: $('#categoryId').val(),
            project_id: $('#projectId').val(),
            paid_to: $('#paidTo').val(),
            payment_method: $('#paymentMethod').val(),
            bank_account_id: isBankMethod($('#paymentMethod').val()) ? $('#bankAccountId').val() : '',
            amount: parseFloat($('#amount').val()).toFixed(2),
            remarks: $('#remarks').val()
        }
    }).done(function (resp) {
        if (resp.status) {
            try { sessionStorage.setItem('expenseFlash', resp.message || 'Expense updated successfully.'); } catch (x) {}
            window.location.href = baseUrl + 'expenses/view/' + expenseId;
        } else {
            $('#formErrorBanner').text((resp.errors || []).join(' ')).removeClass('d-none');
            scrollToFirstInvalid();
            btn.prop('disabled', false);
            spinner.addClass('d-none');
        }
    }).fail(function (xhr) {
        var data = xhr.responseJSON;
        $('#formErrorBanner').text((data && data.errors ? data.errors.join(' ') : 'A network error occurred. Please try again.')).removeClass('d-none');
        btn.prop('disabled', false);
        spinner.addClass('d-none');
    });
});

loadFormData();
</script>
<?= $this->endSection() ?>
