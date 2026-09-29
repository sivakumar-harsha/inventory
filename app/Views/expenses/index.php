<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= $this->include('expenses/partials/ui_styles') ?>

<style>
	.dataTables_wrapper .dataTables_paginate {
		margin-top: 10px;
		text-align: right;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button {
		background: #f1f5f9 !important;
		border: 1px solid #e2e8f0 !important;
		color: #334155 !important;
		padding: 4px 10px !important;
		margin: 2px !important;
		border-radius: 6px !important;
		font-size: 12px !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button.current {
		background: #2F7E8A !important;
		color: #fff !important;
		border: none !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
		background: #1e293b !important;
		color: #fff !important;
	}

	.custom-search-box { max-width: 300px; }
	.custom-search-box input {
		border-radius: 8px;
		border: 1px solid #e2e8f0;
		padding: 6px 12px;
		font-size: 13px;
	}
	.custom-search-box input:focus {
		border-color: #2F7E8A;
		box-shadow: 0 0 0 2px rgba(47,126,138,0.15);
	}
	.search-icon { position: absolute; top: 8px; left: 9px; color: #94a3b8; font-size: 12px; }

	.filter-toolbar .form-section { margin-bottom: 0; }
	.filter-toolbar .form-label { font-size: .72rem; margin-bottom: 3px; }
	.filter-toolbar .form-control { padding: 6px 10px; font-size: .82rem; height: auto; }
	.filter-toolbar .btn-cancel, .filter-toolbar .btn-save { padding: 6px 12px; font-size: .8rem; white-space: nowrap; }

	#expenseTable.table-custom th,
	#expenseTable.table-custom td { padding: 0 10px; height: 44px; font-size: .78rem; vertical-align: middle; }
	#expenseTable.table-custom th { height: 34px; }
</style>

<nav aria-label="breadcrumb" class="exp-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Home</a></li>
        <li class="breadcrumb-item">Transactions</li>
        <li class="breadcrumb-item active" aria-current="page">Expense Register</li>
    </ol>
</nav>

<div class="page-title d-flex align-items-center justify-content-between flex-wrap">
    <span class="d-flex align-items-center"><i class="bi bi-credit-card me-2"></i>Expense Register</span>
    <div class="d-flex gap-2">
        <a href="<?= base_url('expense-reports') ?>" class="btn-cancel"><i class="bi bi-journal-text"></i> Expense Ledger</a>
        <a href="<?= base_url('expenses/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> Add Expense</a>
    </div>
</div>

<!-- FILTER TOOLBAR -->
<div class="card-custom mb-3">
    <div class="card-custom-header">Filters</div>
    <div class="card-custom-body">
        <div class="row g-2 align-items-end filter-toolbar">
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Category</label>
                    <select id="filterCategory" class="form-control">
                        <option value="">All Categories</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Project</label>
                    <select id="filterProject" class="form-control">
                        <option value="">All Projects</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Payment Method</label>
                    <select id="filterPaymentMethod" class="form-control no-search">
                        <option value="">All Methods</option>
                        <option value="CASH">Cash</option>
                        <option value="BANK">Bank</option>
                        <option value="CHEQUE">Cheque</option>
                        <option value="UPI">UPI</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Status</label>
                    <select id="filterStatus" class="form-control no-search">
                        <option value="">All</option>
                        <option value="PAID">Paid</option>
                        <option value="CANCELLED">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Date From</label>
                    <input type="date" id="filterFromDate" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-section">
                    <label class="form-label">Date To</label>
                    <input type="date" id="filterToDate" class="form-control">
                </div>
            </div>
        </div>
        <div class="row g-2 align-items-end filter-toolbar mt-1">
            <div class="col-12 col-md-4">
                <div class="custom-search-box position-relative">
                    <label class="form-label">Search</label>
                    <i class="bi bi-search search-icon" style="top:34px;"></i>
                    <input type="text" id="customSearch" class="form-control ps-4" placeholder="Search expense no, paid to...">
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <a href="javascript:void(0)" id="applyFilters" class="btn-save"><i class="bi bi-funnel"></i> Filter</a>
            </div>
            <div class="col-6 col-md-auto">
                <a href="javascript:void(0)" id="resetFilters" class="btn-cancel"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-custom-header exp-card-head">
        <span>Expenses</span>
        <span class="exp-count" id="expenseCountChip">Loading&hellip;</span>
    </div>
    <div class="table-responsive exp-scroll exp-sticky">
        <table id="expenseTable" class="table-custom">
            <thead>
                <tr>
                    <th>Voucher No</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Project</th>
                    <th>Payment Method</th>
                    <th style="text-align:right">Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr class="exp-empty-row"><td colspan="8" class="exp-empty"><i class="bi bi-hourglass-split"></i>Loading expenses...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;border-radius:50%;background:#fee2e2;color:#dc2626;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </span>
                    Delete Expense
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete expense <strong id="deleteExpenseNo">-</strong>? This cannot be undone.</p>
                <div id="deleteExpenseError" class="alert alert-danger d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                <button type="button" class="btn-delete" id="confirmDeleteExpenseBtn">
                    <span id="deleteExpenseSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var baseUrl = "<?= base_url() ?>";
var expenseTable = null;
var pendingDeleteId = null;

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

function expenseRowHtml(e) {
    var actions =
        '<a href="' + baseUrl + 'expenses/view/' + e.id + '" class="btn-view" title="View"><i class="bi bi-eye"></i></a> ' +
        '<a href="' + baseUrl + 'expenses/edit/' + e.id + '" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a> ' +
        '<a href="javascript:void(0)" class="btn-delete" title="Delete" onclick="openDeleteModal(' + e.id + ', \'' + (e.expense_no || '').replace(/'/g, "") + '\')"><i class="bi bi-trash"></i></a>';

    return '<tr data-category="' + (e.category_name || '') + '" data-project="' + (e.project_name || '') + '" ' +
        'data-method="' + e.payment_method + '" data-status="' + e.status + '" data-date="' + e.expense_date + '">' +
        '<td><a href="' + baseUrl + 'expenses/view/' + e.id + '"><strong>' + (e.expense_no || '') + '</strong></a></td>' +
        '<td>' + e.expense_date + '</td>' +
        '<td>' + (e.category_name || '-') + '</td>' +
        '<td>' + (e.project_name || 'General') + '</td>' +
        '<td>' + methodBadge(e.payment_method) + '</td>' +
        '<td style="text-align:right">' + Number(e.amount).toFixed(2) + '</td>' +
        '<td>' + statusBadge(e.status) + '</td>' +
        '<td>' + actions + '</td>' +
    '</tr>';
}

function populateSelectFromUnique(selectId, values, allLabel) {
    var select = $(selectId);
    var unique = [];
    values.forEach(function (v) {
        if (v && unique.indexOf(v) === -1) unique.push(v);
    });
    unique.sort();
    select.find('option:not(:first)').remove();
    unique.forEach(function (v) {
        select.append($('<option></option>').val(v).text(v));
    });
    select.trigger('change');
}

function openDeleteModal(id, expenseNo) {
    pendingDeleteId = id;
    $('#deleteExpenseNo').text(expenseNo);
    $('#deleteExpenseError').addClass('d-none').text('');
    var modal = new bootstrap.Modal(document.getElementById('deleteExpenseModal'));
    modal.show();
}

$('#confirmDeleteExpenseBtn').on('click', function () {
    if (!pendingDeleteId) return;

    var btn = $(this);
    var spinner = $('#deleteExpenseSpinner');
    var errorBox = $('#deleteExpenseError');
    errorBox.addClass('d-none').text('');
    btn.prop('disabled', true);
    spinner.removeClass('d-none');

    $.ajax({
        url: baseUrl + 'expenses/delete/' + pendingDeleteId,
        type: 'POST',
        dataType: 'json'
    }).done(function (resp) {
        if (resp.status) {
            window.location.reload();
        } else {
            errorBox.text((resp.errors && resp.errors.join(' ')) || 'Failed to delete expense.').removeClass('d-none');
        }
    }).fail(function (xhr) {
        var data = xhr.responseJSON;
        errorBox.text((data && data.errors && data.errors.join(' ')) || 'A network error occurred.').removeClass('d-none');
    }).always(function () {
        btn.prop('disabled', false);
        spinner.addClass('d-none');
    });
});

function loadExpenses() {
    $.ajax({ url: baseUrl + 'expenses', type: 'GET', dataType: 'json' }).done(function (resp) {
        if (!resp.status) return;

        var rows = resp.expenses.map(expenseRowHtml).join('');
        $('#expenseTable tbody').html(rows || '<tr class="exp-empty-row"><td colspan="8" class="exp-empty"><i class="bi bi-inbox"></i>No expenses found.</td></tr>');
        $('#expenseCountChip').text(resp.expenses.length + ' record' + (resp.expenses.length === 1 ? '' : 's'));

        populateSelectFromUnique('#filterCategory', resp.expenses.map(function (e) { return e.category_name; }));
        populateSelectFromUnique('#filterProject', resp.expenses.map(function (e) { return e.project_name; }));

        if (expenseTable) {
            expenseTable.destroy();
            expenseTable = null;
        }

        expenseTable = $('#expenseTable').DataTable({
            paging: true,
            searching: true,
            lengthChange: false,
            info: false,
            ordering: true,
            order: [[1, 'desc']],
            pageLength: 10,
            dom: 'tp',
            columnDefs: [{ orderable: false, targets: 7 }],
            language: {
                paginate: {
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>'
                },
                emptyTable: 'No expenses found.'
            }
        });

        bindFilterSearch();
    }).fail(function () {
        $('#expenseTable tbody').html('<tr class="exp-empty-row"><td colspan="8" class="exp-empty"><i class="bi bi-exclamation-triangle"></i>Failed to load expenses. Please refresh the page.</td></tr>');
        $('#expenseCountChip').text('—');
    });
}

function bindFilterSearch() {
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function (fn) {
        return fn.__expenseFilter !== true;
    });

    var filterFn = function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'expenseTable') return true;

        var row = settings.aoData[dataIndex].nTr;
        var category = $('#filterCategory').val();
        var project  = $('#filterProject').val();
        var method   = $('#filterPaymentMethod').val();
        var status   = $('#filterStatus').val();
        var from     = $('#filterFromDate').val();
        var to       = $('#filterToDate').val();
        var date     = row.getAttribute('data-date');

        if (category && row.getAttribute('data-category') !== category) return false;
        if (project && row.getAttribute('data-project') !== project) return false;
        if (method && row.getAttribute('data-method') !== method) return false;
        if (status && row.getAttribute('data-status') !== status) return false;
        if (from && date < from) return false;
        if (to && date > to) return false;
        return true;
    };
    filterFn.__expenseFilter = true;

    $.fn.dataTable.ext.search.push(filterFn);

    $('#customSearch').off('keyup').on('keyup', function () {
        expenseTable.search(this.value).draw();
    });

    $('#applyFilters').off('click').on('click', function () {
        expenseTable.draw();
    });

    $('#resetFilters').off('click').on('click', function () {
        $('#filterCategory, #filterProject, #filterPaymentMethod, #filterStatus').val('').trigger('change');
        $('#filterFromDate, #filterToDate, #customSearch').val('');
        expenseTable.search('').draw();
    });
}

$(document).ready(function () {
    loadExpenses();
});
</script>
<?= $this->endSection() ?>
