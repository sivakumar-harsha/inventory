<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= $this->include('expenses/partials/ui_styles') ?>

<style>
.exp-view-print-header { display: none; }

@media print {
    .exp-breadcrumb, .page-title a { display: none !important; }
    .exp-view-print-header { display: block; margin-bottom: 10px; font-size: .75rem; color: #64748b; }
    .card-custom { box-shadow: none; animation: none; break-inside: avoid; }
}
</style>

<nav aria-label="breadcrumb" class="exp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item"><a href="<?= base_url('expenses') ?>">Expenses</a></li>
        <li class="breadcrumb-item active" aria-current="page" id="breadcrumbExpenseNo">Loading...</li>
    </ol>
</nav>

<div class="page-title">
    <span><i class="bi bi-credit-card me-2"></i>Expense <span id="pageTitleExpenseNo">-</span></span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('expenses/edit/') . $id ?>" class="btn-save" id="editExpenseLink"><i class="bi bi-pencil"></i> Edit</a>
        <a href="javascript:window.print()" class="btn-save" style="background:#6c757d;"><i class="bi bi-printer"></i> Print</a>
        <a href="<?= base_url('expenses/export/pdf/') . $id ?>" class="btn-cancel"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="<?= base_url('expenses/export/excel/') . $id ?>" class="btn-cancel"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <a href="<?= base_url('expenses') ?>" class="btn-cancel"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div id="notFoundBanner" class="alert alert-danger d-none" role="alert">Expense not found.</div>

<div class="exp-view-print-header">A&amp;A Inventory &middot; Printed <?= esc(date('d-m-Y H:i')) ?></div>

<div id="expenseDetail" class="row g-3" style="display:none;">
    <div class="col-md-6">
        <div class="card-custom mb-3">
            <div class="card-custom-header"><i class="bi bi-cash-coin me-2"></i>Financial Summary</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Expense No</strong></td><td id="sumExpenseNo">-</td></tr>
                    <tr><td><strong>Date</strong></td><td id="sumDate">-</td></tr>
                    <tr><td><strong>Category</strong></td><td id="sumCategory">-</td></tr>
                    <tr><td><strong>Paid To</strong></td><td id="sumPaidTo">-</td></tr>
                    <tr><td><strong>Amount</strong></td><td id="sumAmount" style="font-weight:700;color:#2F7E8A;">-</td></tr>
                    <tr><td><strong>Status</strong></td><td id="sumStatus">-</td></tr>
                    <tr id="sumRemarksRow" style="display:none;"><td><strong>Remarks</strong></td><td id="sumRemarks">-</td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-custom-header"><i class="bi bi-credit-card-2-front me-2"></i>Payment Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Payment Method</strong></td><td id="payMethod">-</td></tr>
                    <tr id="payBankRow" style="display:none;"><td><strong>Bank Account</strong></td><td id="payBank">-</td></tr>
                    <tr id="payAccountNoRow" style="display:none;"><td><strong>Account Number</strong></td><td id="payAccountNo">-</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom mb-3">
            <div class="card-custom-header"><i class="bi bi-kanban me-2"></i>Project Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Project</strong></td><td id="projName">General / Not linked to a project</td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-custom-header"><i class="bi bi-clock-history me-2"></i>Audit Information</div>
            <div class="card-custom-body">
                <table class="table-custom">
                    <tr><td><strong>Created By</strong></td><td id="auditCreatedBy">-</td></tr>
                    <tr><td><strong>Created At</strong></td><td id="auditCreatedAt">-</td></tr>
                    <tr><td><strong>Last Updated</strong></td><td id="auditUpdatedAt">-</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var baseUrl = "<?= base_url() ?>";
var expenseId = <?= (int) $id ?>;

function statusBadge(status) {
    return status === 'CANCELLED'
        ? '<span class="exp-badge exp-badge-gray">Cancelled</span>'
        : '<span class="exp-badge exp-badge-green">Paid</span>';
}

var METHOD_BADGE_CLASS = { CASH: 'exp-badge-m-cash', BANK: 'exp-badge-m-bank', CHEQUE: 'exp-badge-m-cheque', UPI: 'exp-badge-m-upi', OTHER: 'exp-badge-m-other' };

function methodBadge(method) {
    var cls = METHOD_BADGE_CLASS[method] || 'exp-badge-m-other';
    var label = method ? method.charAt(0) + method.slice(1).toLowerCase() : '-';
    return '<span class="exp-badge ' + cls + '">' + label + '</span>';
}

function formatDateTime(value) {
    if (!value) return '-';
    return value.replace('T', ' ');
}

$.ajax({ url: baseUrl + 'expenses/view/' + expenseId, type: 'GET', dataType: 'json' }).done(function (resp) {
    if (!resp.status) {
        $('#notFoundBanner').removeClass('d-none');
        $('#editExpenseLink').addClass('d-none');
        return;
    }

    var e = resp.expense;

    $('#breadcrumbExpenseNo').text(e.expense_no);
    $('#pageTitleExpenseNo').text(e.expense_no);

    $('#sumExpenseNo').text(e.expense_no);
    $('#sumDate').text(e.expense_date);
    $('#sumCategory').text(e.category_name || '-');
    $('#sumPaidTo').text(e.paid_to);
    $('#sumAmount').text(Number(e.amount).toFixed(2));
    $('#sumStatus').html(statusBadge(e.status));

    if (e.remarks) {
        $('#sumRemarks').text(e.remarks);
        $('#sumRemarksRow').show();
    }

    $('#payMethod').html(methodBadge(e.payment_method));
    if (e.bank_account) {
        $('#payBank').text(e.bank_account.bank_name + ' - ' + e.bank_account.account_name);
        $('#payAccountNo').text(e.bank_account.account_number);
        $('#payBankRow').show();
        $('#payAccountNoRow').show();
    }

    $('#projName').text(e.project_name || 'General / Not linked to a project');

    $('#auditCreatedBy').text(e.created_by_name || 'System');
    $('#auditCreatedAt').text(formatDateTime(e.created_at));
    $('#auditUpdatedAt').text(formatDateTime(e.updated_at));

    if (e.status === 'CANCELLED') {
        $('#editExpenseLink').addClass('d-none');
    }

    $('#expenseDetail').show();
}).fail(function () {
    $('#notFoundBanner').text('Failed to load expense. Please refresh the page.').removeClass('d-none');
    $('#editExpenseLink').addClass('d-none');
});
</script>
<?= $this->endSection() ?>
